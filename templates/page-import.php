<?php
/**
 * Template: Import CSV page.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbim-wrap">

	<div class="sbim-header">
		<div class="sbim-header__inner">
			<div class="sbim-header__brand">
				<span class="dashicons dashicons-upload"></span>
				<h1><?php esc_html_e( 'Import CSV', 'schema-bulk-import-manager' ); ?></h1>
			</div>
			<div class="sbim-header__actions">
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'sbim_action' => 'sample_csv' ], admin_url( 'admin.php' ) ), 'sbim_sample_csv' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Download Sample CSV', 'schema-bulk-import-manager' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Upload Zone -->
	<div class="sbim-card" id="sbim-upload-card">
		<div class="sbim-card__header">
			<h2><?php esc_html_e( 'Upload CSV File', 'schema-bulk-import-manager' ); ?></h2>
		</div>
		<div class="sbim-card__body">
			<div class="sbim-upload-zone" id="sbim-upload-zone">
				<div class="sbim-upload-zone__icon">
					<span class="dashicons dashicons-media-spreadsheet"></span>
				</div>
				<div class="sbim-upload-zone__text">
					<p class="sbim-upload-zone__primary"><?php esc_html_e( 'Drop your CSV file here or click to browse', 'schema-bulk-import-manager' ); ?></p>
					<p class="sbim-upload-zone__secondary"><?php esc_html_e( 'Supports .csv files up to 10 MB', 'schema-bulk-import-manager' ); ?></p>
				</div>
				<input type="file" id="sbim-csv-file" name="csv_file" accept=".csv" class="sbim-upload-zone__input" />
				<div class="sbim-upload-zone__filename" id="sbim-filename" style="display:none;"></div>
			</div>

			<div class="sbim-upload-actions">
				<button type="button" id="sbim-upload-btn" class="button button-primary button-large" disabled>
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Upload & Validate CSV', 'schema-bulk-import-manager' ); ?>
				</button>
			</div>

			<!-- Progress bar -->
			<div class="sbim-progress" id="sbim-progress" style="display:none;">
				<div class="sbim-progress__bar">
					<div class="sbim-progress__fill" id="sbim-progress-fill"></div>
				</div>
				<div class="sbim-progress__text" id="sbim-progress-text">
					<?php esc_html_e( 'Processing...', 'schema-bulk-import-manager' ); ?>
				</div>
			</div>

			<!-- CSV format reminder -->
			<div class="sbim-notice sbim-notice--info">
				<span class="dashicons dashicons-info-outline"></span>
				<div>
					<strong><?php esc_html_e( 'CSV Format:', 'schema-bulk-import-manager' ); ?></strong>
					<?php esc_html_e( 'Column A = Full page URL (https://yourdomain.com/page/). Column B = Complete JSON-LD block including &lt;script type="application/ld+json"&gt; opening and &lt;/script&gt; closing tags.', 'schema-bulk-import-manager' ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Preview Table (hidden until CSV uploaded) -->
	<div id="sbim-preview-section" style="display:none;">

		<!-- Import Stats Summary -->
		<div class="sbim-stats-grid sbim-stats-grid--preview" id="sbim-preview-stats">
			<div class="sbim-stat-card sbim-stat-card--total">
				<div class="sbim-stat-card__icon"><span class="dashicons dashicons-database"></span></div>
				<div class="sbim-stat-card__body">
					<div class="sbim-stat-card__value" id="stat-total">0</div>
					<div class="sbim-stat-card__label"><?php esc_html_e( 'Total Rows', 'schema-bulk-import-manager' ); ?></div>
				</div>
			</div>
			<div class="sbim-stat-card sbim-stat-card--active">
				<div class="sbim-stat-card__icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="sbim-stat-card__body">
					<div class="sbim-stat-card__value" id="stat-found">0</div>
					<div class="sbim-stat-card__label"><?php esc_html_e( 'Pages Found', 'schema-bulk-import-manager' ); ?></div>
				</div>
			</div>
			<div class="sbim-stat-card sbim-stat-card--inactive">
				<div class="sbim-stat-card__icon"><span class="dashicons dashicons-no-alt"></span></div>
				<div class="sbim-stat-card__body">
					<div class="sbim-stat-card__value" id="stat-notfound">0</div>
					<div class="sbim-stat-card__label"><?php esc_html_e( 'Not Found', 'schema-bulk-import-manager' ); ?></div>
				</div>
			</div>
			<div class="sbim-stat-card sbim-stat-card--warning">
				<div class="sbim-stat-card__icon"><span class="dashicons dashicons-warning"></span></div>
				<div class="sbim-stat-card__body">
					<div class="sbim-stat-card__value" id="stat-invalid">0</div>
					<div class="sbim-stat-card__label"><?php esc_html_e( 'Invalid', 'schema-bulk-import-manager' ); ?></div>
				</div>
			</div>
		</div>

		<div class="sbim-card">
			<div class="sbim-card__header sbim-card__header--flex">
				<h2><?php esc_html_e( 'Preview — Review Before Saving', 'schema-bulk-import-manager' ); ?></h2>
				<div class="sbim-preview-actions">
					<button type="button" id="sbim-save-btn" class="button button-primary button-large">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Save Schemas', 'schema-bulk-import-manager' ); ?>
					</button>
					<button type="button" id="sbim-reset-btn" class="button button-secondary">
						<span class="dashicons dashicons-update-alt"></span>
						<?php esc_html_e( 'Upload New CSV', 'schema-bulk-import-manager' ); ?>
					</button>
				</div>
			</div>
			<div class="sbim-card__body sbim-card__body--flush">
				<div class="sbim-table-wrap">
					<table class="sbim-table" id="sbim-preview-table">
						<thead>
							<tr>
								<th style="width:40px;"><?php esc_html_e( 'Row', 'schema-bulk-import-manager' ); ?></th>
								<th><?php esc_html_e( 'URL', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:130px;"><?php esc_html_e( 'URL Status', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:130px;"><?php esc_html_e( 'Schema', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:150px;"><?php esc_html_e( 'Duplicate', 'schema-bulk-import-manager' ); ?></th>
								<th><?php esc_html_e( 'Page', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:100px;"><?php esc_html_e( 'Action', 'schema-bulk-import-manager' ); ?></th>
							</tr>
						</thead>
						<tbody id="sbim-preview-tbody">
							<!-- Populated by JS -->
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Import Report (shown after save) -->
	<div id="sbim-report-section" style="display:none;">
		<div class="sbim-card sbim-card--report">
			<div class="sbim-card__header sbim-card__header--flex">
				<h2><?php esc_html_e( 'Import Report', 'schema-bulk-import-manager' ); ?></h2>
				<div>
					<button type="button" id="sbim-download-report-btn" class="button button-secondary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Report CSV', 'schema-bulk-import-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-schemas' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View All Schemas', 'schema-bulk-import-manager' ); ?>
					</a>
				</div>
			</div>
			<div class="sbim-card__body">
				<div class="sbim-stats-grid sbim-stats-grid--report" id="sbim-report-stats"></div>
				<div id="sbim-report-detail"></div>
			</div>
		</div>
	</div>

</div><!-- .sbim-wrap -->