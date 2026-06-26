<?php
/**
 * Template: All Schemas list page.
 *
 * @package SchemaBulkImportManager
 * @var object[] $items
 * @var int      $total
 * @var int      $total_pages
 * @var int      $page
 * @var string   $search
 * @var string   $status
 * @var int      $per_page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbim-wrap">

	<div class="sbim-header">
		<div class="sbim-header__inner">
			<div class="sbim-header__brand">
				<span class="dashicons dashicons-list-view"></span>
				<h1><?php esc_html_e( 'All Schemas', 'schema-bulk-import-manager' ); ?></h1>
				<span class="sbim-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
			</div>
			<div class="sbim-header__actions">
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'sbim_action' => 'export_csv' ], admin_url( 'admin.php' ) ), 'sbim_export_csv' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export CSV', 'schema-bulk-import-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-import' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import CSV', 'schema-bulk-import-manager' ); ?>
				</a>
			</div>
		</div>
	</div>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="sbim-notice sbim-notice--success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Schema deleted successfully.', 'schema-bulk-import-manager' ); ?></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="sbim-notice sbim-notice--success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Schema updated successfully.', 'schema-bulk-import-manager' ); ?></div>
	<?php endif; ?>

	<div class="sbim-card">
		<div class="sbim-card__header sbim-card__header--flex">
			<!-- Filters -->
			<div class="sbim-filters">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="sbim-search-form">
					<input type="hidden" name="page" value="sbim-schemas" />
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search URLs...', 'schema-bulk-import-manager' ); ?>" class="sbim-search-input" />
					<select name="status" class="sbim-select">
						<option value=""><?php esc_html_e( 'All Statuses', 'schema-bulk-import-manager' ); ?></option>
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'schema-bulk-import-manager' ); ?></option>
						<option value="not_found" <?php selected( $status, 'not_found' ); ?>><?php esc_html_e( 'Not Found', 'schema-bulk-import-manager' ); ?></option>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'schema-bulk-import-manager' ); ?></button>
					<?php if ( $search || $status ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-schemas' ) ); ?>" class="button button-link"><?php esc_html_e( 'Clear', 'schema-bulk-import-manager' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<!-- Bulk actions -->
			<div class="sbim-bulk-actions">
				<button type="button" id="sbim-bulk-delete-btn" class="button button-link-delete">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Delete Selected', 'schema-bulk-import-manager' ); ?>
				</button>
			</div>
		</div>

		<div class="sbim-card__body sbim-card__body--flush">
			<?php if ( empty( $items ) ) : ?>
				<div class="sbim-empty-state">
					<span class="dashicons dashicons-database"></span>
					<h3><?php esc_html_e( 'No schemas found.', 'schema-bulk-import-manager' ); ?></h3>
					<p>
						<?php if ( $search || $status ) : ?>
							<?php esc_html_e( 'Try adjusting your search or filters.', 'schema-bulk-import-manager' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Import your first CSV to get started.', 'schema-bulk-import-manager' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-import' ) ); ?>"><?php esc_html_e( 'Import CSV →', 'schema-bulk-import-manager' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			<?php else : ?>
				<div class="sbim-table-wrap">
					<table class="sbim-table sbim-table--striped">
						<thead>
							<tr>
								<th style="width:40px;"><input type="checkbox" id="sbim-check-all" title="<?php esc_attr_e( 'Select all', 'schema-bulk-import-manager' ); ?>" /></th>
								<th style="width:50px;"><?php esc_html_e( 'ID', 'schema-bulk-import-manager' ); ?></th>
								<th><?php esc_html_e( 'URL', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:120px;"><?php esc_html_e( 'Status', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:80px;"><?php esc_html_e( 'Post ID', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:180px;"><?php esc_html_e( 'Date Added', 'schema-bulk-import-manager' ); ?></th>
								<th style="width:150px;"><?php esc_html_e( 'Actions', 'schema-bulk-import-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr data-id="<?php echo esc_attr( $item->id ); ?>">
									<td><input type="checkbox" class="sbim-row-check" value="<?php echo esc_attr( $item->id ); ?>" /></td>
									<td><?php echo esc_html( $item->id ); ?></td>
									<td class="sbim-table__url-cell">
										<a href="<?php echo esc_url( $item->url ); ?>" target="_blank" class="sbim-url-link" title="<?php echo esc_attr( $item->url ); ?>">
											<?php echo esc_html( $item->url ); ?>
											<span class="dashicons dashicons-external sbim-ext-icon"></span>
										</a>
									</td>
									<td>
										<?php if ( $item->status === 'active' ) : ?>
											<span class="sbim-badge sbim-badge--success"><?php esc_html_e( 'Active', 'schema-bulk-import-manager' ); ?></span>
										<?php else : ?>
											<span class="sbim-badge sbim-badge--danger"><?php esc_html_e( 'Not Found', 'schema-bulk-import-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo $item->post_id ? esc_html( $item->post_id ) : '<span class="sbim-muted">—</span>'; ?></td>
									<td><span class="sbim-date"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->created_at ) ) ); ?></span></td>
									<td class="sbim-table__actions">
										<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'sbim-edit', 'id' => $item->id ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e( 'Edit', 'schema-bulk-import-manager' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'sbim-schemas', 'action' => 'delete', 'id' => $item->id ], admin_url( 'admin.php' ) ), 'sbim_delete_' . $item->id ) ); ?>"
										   class="button button-small button-link-delete sbim-delete-link"
										   data-confirm="<?php esc_attr_e( 'Are you sure you want to delete this schema?', 'schema-bulk-import-manager' ); ?>">
											<span class="dashicons dashicons-trash"></span>
											<?php esc_html_e( 'Delete', 'schema-bulk-import-manager' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="sbim-pagination">
						<div class="sbim-pagination__info">
							<?php
							$from = ( ( $page - 1 ) * $per_page ) + 1;
							$to   = min( $page * $per_page, $total );
							printf(
								/* translators: 1: from, 2: to, 3: total */
								esc_html__( 'Showing %1$s–%2$s of %3$s schemas', 'schema-bulk-import-manager' ),
								esc_html( number_format_i18n( $from ) ),
								esc_html( number_format_i18n( $to ) ),
								esc_html( number_format_i18n( $total ) )
							);
							?>
						</div>
						<div class="sbim-pagination__links">
							<?php
							$base_url = add_query_arg(
								array_filter( [ 'page' => 'sbim-schemas', 's' => $search, 'status' => $status ] ),
								admin_url( 'admin.php' )
							);

							if ( $page > 1 ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base_url ) ); ?>" class="button button-small">&#8592; <?php esc_html_e( 'Previous', 'schema-bulk-import-manager' ); ?></a>
							<?php endif;

							for ( $i = max( 1, $page - 2 ); $i <= min( $total_pages, $page + 2 ); $i++ ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>"
								   class="button button-small <?php echo $i === $page ? 'button-primary' : ''; ?>">
									<?php echo esc_html( $i ); ?>
								</a>
							<?php endfor;

							if ( $page < $total_pages ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Next', 'schema-bulk-import-manager' ); ?> &#8594;</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

			<?php endif; ?>
		</div>
	</div>

</div><!-- .sbim-wrap -->