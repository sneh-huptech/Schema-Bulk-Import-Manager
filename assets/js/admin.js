/**
 * Schema Bulk Import Manager — Admin JS
 * Version: 1.0.0
 */
/* global SBIM, jQuery */

(function ($) {
    'use strict';

    // =========================================================================
    // State
    // =========================================================================
    let previewData = [];
    let duplicateActions = {}; // { url: 'replace'|'skip' }

    // =========================================================================
    // DOM Ready
    // =========================================================================
    $(function () {
        initUploadZone();
        initUploadButton();
        initSaveButton();
        initResetButton();
        initBulkDelete();
        initCheckAll();
        initDeleteConfirms();
        initDownloadReport();
        initCopyCode();
    });

    // =========================================================================
    // Upload Zone — drag & drop + file input
    // =========================================================================
    function initUploadZone() {
        var $zone = $('#sbim-upload-zone');
        var $input = $('#sbim-csv-file');
        var $btn = $('#sbim-upload-btn');
        var $name = $('#sbim-filename');

        if (!$zone.length) return;

        // File selected via input.
        $input.on('change', function () {
            var file = this.files[0];
            if (file) {
                $name.text('📄 ' + file.name).show();
                $btn.prop('disabled', false);
            } else {
                $name.hide();
                $btn.prop('disabled', true);
            }
        });

        // Drag events.
        $zone.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.addClass('sbim-dragover');
        });

        $zone.on('dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.removeClass('sbim-dragover');
        });

        $zone.on('drop', function (e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length) {
                // Assign dropped file to the input.
                var dt = new DataTransfer();
                dt.items.add(files[0]);
                $input[0].files = dt.files;
                $name.text('📄 ' + files[0].name).show();
                $btn.prop('disabled', false);
            }
        });
    }

    // =========================================================================
    // Upload & Validate CSV
    // =========================================================================
    function initUploadButton() {
        var $btn = $('#sbim-upload-btn');
        if (!$btn.length) return;

        $btn.on('click', function () {
            var file = document.getElementById('sbim-csv-file');
            if (!file || !file.files.length) {
                alert(SBIM.strings.no_file);
                return;
            }

            var formData = new FormData();
            formData.append('action', 'sbim_upload_csv');
            formData.append('nonce', SBIM.nonce);
            formData.append('csv_file', file.files[0]);

            showProgress(SBIM.strings.uploading, 30);
            $btn.prop('disabled', true).html('<span class="sbim-spinner"></span> ' + SBIM.strings.processing);

            $.ajax({
                url: SBIM.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    showProgress(SBIM.strings.uploading, 100);

                    setTimeout(function () {
                        hideProgress();
                        $btn.prop('disabled', false).html(
                            '<span class="dashicons dashicons-upload"></span> Upload &amp; Validate CSV'
                        );

                        if (res.success) {
                            previewData = res.data.preview;
                            duplicateActions = {};
                            renderPreview(res.data.preview, res.data.stats);
                        } else {
                            showInlineError(res.data.message || 'Upload failed.');
                        }
                    }, 400);
                },
                error: function (xhr) {
                    hideProgress();
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-upload"></span> Upload &amp; Validate CSV'
                    );
                    showInlineError('AJAX error: ' + (xhr.statusText || 'unknown'));
                }
            });
        });
    }

    // =========================================================================
    // Render Preview Table
    // =========================================================================
    function renderPreview(rows, stats) {
        // Update stat counters.
        $('#stat-total').text(stats.total);
        $('#stat-found').text(stats.found);
        $('#stat-notfound').text(stats.not_found);
        $('#stat-invalid').text(stats.invalid);

        var $tbody = $('#sbim-preview-tbody');
        $tbody.empty();

        if (!rows.length) {
            $tbody.append('<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">No rows found in CSV.</td></tr>');
        } else {
            rows.forEach(function (row) {
                $tbody.append(buildPreviewRow(row));
            });
        }

        $('#sbim-upload-card').hide();
        $('#sbim-preview-section').show();
        $('html, body').animate({ scrollTop: $('#sbim-preview-section').offset().top - 40 }, 400);
    }

    function buildPreviewRow(row) {
        var urlBadge, schemaBadge, dupCell;

        // URL status badge.
        if (row.url_status === 'found') {
            urlBadge = '<span class="sbim-badge sbim-badge--success">✓ Found</span>';
        } else {
            urlBadge = '<span class="sbim-badge sbim-badge--danger">✗ Not Found</span>';
        }

        // Schema valid badge.
        if (!row.schema_valid) {
            schemaBadge = '<span class="sbim-badge sbim-badge--danger" title="' + esc(row.schema_error) + '">✗ Invalid</span>';
        } else {
            schemaBadge = '<span class="sbim-badge sbim-badge--success">✓ Valid</span>';
        }

        // Duplicate cell.
        if (row.duplicate_id) {
            dupCell =
                '<div class="sbim-duplicate-actions">' +
                '<span class="sbim-badge sbim-badge--warning">Duplicate #' + esc(row.duplicate_id) + '</span>' +
                '<label><input type="radio" name="dup_' + esc(row.row_number) + '" value="replace" data-url="' + esc(row.url) + '" class="sbim-dup-action"> Replace</label>' +
                '<label><input type="radio" name="dup_' + esc(row.row_number) + '" value="skip"    data-url="' + esc(row.url) + '" class="sbim-dup-action" checked> Skip</label>' +
                '</div>';
            // Default: skip.
            duplicateActions[row.url] = 'skip';
        } else {
            dupCell = '<span class="sbim-muted">—</span>';
        }

        // Schema preview snippet.
        var schemaSnippet = row.schema_markup
            ? '<code class="sbim-schema-preview">' + esc(row.schema_markup.substring(0, 80)) + '…</code>'
            : '<span class="sbim-muted">—</span>';

        // Page info.
        var pageInfo = row.post_title
            ? esc(row.post_title) + ' <em class="sbim-muted">(' + esc(row.post_type) + ')</em>'
            : '<span class="sbim-muted">—</span>';

        // Row classes.
        var rowClass = '';
        if (!row.importable) {
            rowClass = row.schema_valid === false ? 'sbim-row--error' : 'sbim-row--warning';
        }

        return '<tr class="' + rowClass + '">' +
            '<td>' + esc(row.row_number) + '</td>' +
            '<td class="sbim-table__url-cell"><span class="sbim-url-link" title="' + esc(row.url) + '">' + esc(row.url) + '</span>' +
            (row.schema_error ? '<div class="sbim-notice sbim-notice--error" style="margin-top:6px;padding:6px 10px;font-size:12px;">' + esc(row.schema_error) + '</div>' : '') +
            '</td>' +
            '<td>' + urlBadge + '</td>' +
            '<td>' + schemaBadge + '</td>' +
            '<td>' + dupCell + '</td>' +
            '<td>' + pageInfo + '</td>' +
            '<td>' +
            (row.importable ? '<span class="sbim-badge sbim-badge--success">Will Import</span>' : '<span class="sbim-badge sbim-badge--neutral">Skipped</span>') +
            '</td>' +
            '</tr>';
    }

    // Live duplicate radio handling.
    $(document).on('change', '.sbim-dup-action', function () {
        var url = $(this).data('url');
        var action = $(this).val();
        duplicateActions[url] = action;
    });

    // =========================================================================
    // Save Schemas
    // =========================================================================
    function initSaveButton() {
        $(document).on('click', '#sbim-save-btn', function () {
            if (!previewData.length) return;

            var importable = previewData.filter(function (r) { return r.importable; });
            if (!importable.length) {
                alert('No importable rows found. Check that your URLs match pages on this site and your JSON-LD is valid.');
                return;
            }

            if (!confirm(SBIM.strings.confirm_save + '\n\n' + importable.length + ' schema(s) will be saved.')) {
                return;
            }

            var $btn = $('#sbim-save-btn');
            $btn.prop('disabled', true).html('<span class="sbim-spinner"></span> ' + SBIM.strings.saving);

            $.post(
                SBIM.ajax_url,
                {
                    action: 'sbim_save_schemas',
                    nonce: SBIM.nonce,
                    duplicate_actions: JSON.stringify(duplicateActions),
                },
                function (res) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Schemas');

                    if (res.success) {
                        previewData = [];
                        duplicateActions = {};
                        renderReport(res.data.report);
                    } else {
                        alert(res.data.message || 'Save failed.');
                    }
                }
            ).fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Schemas');
                alert('AJAX error during save. Please try again.');
            });
        });
    }

    // =========================================================================
    // Render Import Report
    // =========================================================================
    function renderReport(report) {
        $('#sbim-preview-section').hide();

        // Stat cards.
        var statsHtml =
            statCard('total', 'database', report.total, 'Total Rows', '--total') +
            statCard('imported', 'yes-alt', report.imported, 'Imported', '--active') +
            statCard('skipped', 'minus', report.skipped, 'Skipped', '--neutral') +
            statCard('nf', 'no-alt', report.not_found, 'Not Found', '--inactive') +
            statCard('inv', 'warning', report.invalid_json, 'Invalid JSON', '--warning') +
            statCard('dup', 'randomize', report.duplicates, 'Duplicates', '--info');

        $('#sbim-report-stats').html(statsHtml);

        // Detail table.
        var rows = report.rows;
        if (rows && rows.length) {
            var tableHtml =
                '<div class="sbim-report-rows"><table>' +
                '<thead><tr><th>Row</th><th>URL</th><th>Result</th><th>Note</th></tr></thead><tbody>';

            rows.forEach(function (r) {
                var badge;
                switch (r.result) {
                    case 'imported':
                    case 'replaced': badge = '<span class="sbim-badge sbim-badge--success">' + esc(r.result) + '</span>'; break;
                    case 'not_found':
                    case 'invalid_json': badge = '<span class="sbim-badge sbim-badge--danger">' + esc(r.result.replace('_', ' ')) + '</span>'; break;
                    default: badge = '<span class="sbim-badge sbim-badge--neutral">' + esc(r.result) + '</span>';
                }
                tableHtml +=
                    '<tr>' +
                    '<td>' + esc(r.row_number) + '</td>' +
                    '<td style="word-break:break-all;max-width:300px;">' + esc(r.url) + '</td>' +
                    '<td>' + badge + '</td>' +
                    '<td style="font-size:12px;color:#64748b;">' + esc(r.note || '') + '</td>' +
                    '</tr>';
            });

            tableHtml += '</tbody></table></div>';
            $('#sbim-report-detail').html(tableHtml);
        }

        $('#sbim-report-section').show();
        $('html, body').animate({ scrollTop: $('#sbim-report-section').offset().top - 40 }, 400);
    }

    function statCard(id, icon, value, label, modifier) {
        return '<div class="sbim-stat-card sbim-stat-card' + modifier + '">' +
            '<div class="sbim-stat-card__icon"><span class="dashicons dashicons-' + icon + '"></span></div>' +
            '<div class="sbim-stat-card__body">' +
            '<div class="sbim-stat-card__value" id="rstat-' + id + '">' + value + '</div>' +
            '<div class="sbim-stat-card__label">' + label + '</div>' +
            '</div></div>';
    }

    // =========================================================================
    // Download Report
    // =========================================================================
    function initDownloadReport() {
        $(document).on('click', '#sbim-download-report-btn', function () {
            var url = SBIM.ajax_url.replace('admin-ajax.php', 'admin.php') +
                '?sbim_action=download_report&_wpnonce=' +
                encodeURIComponent(sbimGetNonce('sbim_download_report'));
            window.location.href = url;
        });
    }

    function sbimGetNonce(action) {
        // We use the pre-generated nonce from localized data where possible.
        // For download report we need a separate nonce: generate via a quick fetch.
        // Simpler: embed it as a data attribute on the button.
        var $btn = $('#sbim-download-report-btn');
        return $btn.data('nonce') || '';
    }

    // =========================================================================
    // Reset (upload new CSV)
    // =========================================================================
    function initResetButton() {
        $(document).on('click', '#sbim-reset-btn', function () {
            $('#sbim-preview-section').hide();
            $('#sbim-report-section').hide();
            $('#sbim-upload-card').show();
            $('#sbim-csv-file').val('');
            $('#sbim-filename').hide();
            $('#sbim-upload-btn').prop('disabled', true);
            previewData = [];
            duplicateActions = {};
            $('html, body').animate({ scrollTop: 0 }, 400);
        });
    }

    // =========================================================================
    // Bulk Delete (Schemas list page)
    // =========================================================================
    function initBulkDelete() {
        var $btn = $('#sbim-bulk-delete-btn');
        if (!$btn.length) return;

        $btn.on('click', function () {
            var ids = getCheckedIds();
            if (!ids.length) {
                alert(SBIM.strings.select_rows);
                return;
            }

            if (!confirm(SBIM.strings.confirm_bulk_delete + ' (' + ids.length + ' selected)')) {
                return;
            }

            $.post(
                SBIM.ajax_url,
                {
                    action: 'sbim_bulk_delete',
                    nonce: SBIM.nonce,
                    ids: ids,
                },
                function (res) {
                    if (res.success) {
                        ids.forEach(function (id) {
                            $('tr[data-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
                        });
                        showAdminNotice(res.data.message, 'success');
                    } else {
                        alert(res.data.message || 'Bulk delete failed.');
                    }
                }
            ).fail(function () {
                alert('AJAX error during bulk delete.');
            });
        });
    }

    function initCheckAll() {
        $(document).on('change', '#sbim-check-all', function () {
            $('.sbim-row-check').prop('checked', $(this).prop('checked'));
        });
    }

    function getCheckedIds() {
        var ids = [];
        $('.sbim-row-check:checked').each(function () {
            ids.push(parseInt($(this).val(), 10));
        });
        return ids;
    }

    // =========================================================================
    // Delete Confirms (single row)
    // =========================================================================
    function initDeleteConfirms() {
        $(document).on('click', '.sbim-delete-link, .sbim-delete-confirm', function (e) {
            var msg = $(this).data('confirm') || SBIM.strings.confirm_delete;
            if (!confirm(msg)) {
                e.preventDefault();
                return false;
            }
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================
    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showProgress(text, pct) {
        $('#sbim-progress').show();
        $('#sbim-progress-fill').css('width', pct + '%');
        $('#sbim-progress-text').text(text);
    }

    function hideProgress() {
        $('#sbim-progress').hide();
        $('#sbim-progress-fill').css('width', '0%');
    }

    function showInlineError(msg) {
        $('.sbim-upload-inline-error').remove();
        $('#sbim-upload-card .sbim-card__body').append(
            '<div class="sbim-notice sbim-notice--error sbim-upload-inline-error">' +
            '<span class="dashicons dashicons-warning"></span>' + esc(msg) + '</div>'
        );
    }

    function showAdminNotice(msg, type) {
        var $notice = $(
            '<div class="sbim-notice sbim-notice--' + type + '">' +
            '<span class="dashicons dashicons-' + (type === 'success' ? 'yes-alt' : 'warning') + '"></span>' +
            esc(msg) + '</div>'
        );
        $('.sbim-header').after($notice);
        setTimeout(function () { $notice.fadeOut(400, function () { $(this).remove(); }); }, 4000);
    }


    // =========================================================================
    // Generate Page — Copy Code to Clipboard
    // =========================================================================
    function initCopyCode() {
        var $btn = $('#sbim-copy-btn');
        var $textarea = $('#sbim-generated-code');
        var $success = $('#sbim-copy-success');

        if (!$btn.length) return;

        $btn.on('click', function () {
            var code = $textarea.val();

            if (!code) return;

            // Modern clipboard API.
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(function () {
                    showCopySuccess();
                }).catch(function () {
                    fallbackCopy($textarea[0]);
                });
            } else {
                // Fallback for older browsers.
                fallbackCopy($textarea[0]);
            }
        });

        function fallbackCopy(textarea) {
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (e) {
                alert('Could not copy automatically. Please select all text and copy manually (Ctrl+A, Ctrl+C).');
            }
        }

        function showCopySuccess() {
            var $btn = $('#sbim-copy-btn');
            $btn.html('<span class="dashicons dashicons-yes-alt"></span> Copied!');
            $success.show();

            setTimeout(function () {
                $btn.html('<span class="dashicons dashicons-clipboard"></span> Copy Code');
                $success.fadeOut(400);
            }, 3000);
        }
    }

}(jQuery));