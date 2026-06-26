<?php
/**
 * Template: Edit Schema page.
 *
 * @package SchemaBulkImportManager
 * @var object $row
 * @var int    $id
 * @var string $error
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbim-wrap">

	<div class="sbim-header">
		<div class="sbim-header__inner">
			<div class="sbim-header__brand">
				<span class="dashicons dashicons-edit"></span>
				<h1><?php esc_html_e( 'Edit Schema', 'schema-bulk-import-manager' ); ?></h1>
			</div>
			<div class="sbim-header__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-schemas' ) ); ?>" class="button button-secondary">
					← <?php esc_html_e( 'Back to All Schemas', 'schema-bulk-import-manager' ); ?>
				</a>
			</div>
		</div>
	</div>

	<?php if ( $error ) : ?>
		<div class="sbim-notice sbim-notice--error">
			<span class="dashicons dashicons-warning"></span>
			<?php echo esc_html( $error ); ?>
		</div>
	<?php endif; ?>

	<div class="sbim-edit-layout">

		<div class="sbim-edit-main">
			<div class="sbim-card">
				<div class="sbim-card__header">
					<h2><?php esc_html_e( 'Schema Details', 'schema-bulk-import-manager' ); ?></h2>
				</div>
				<div class="sbim-card__body">
					<form method="post" action="" id="sbim-edit-form">
						<?php wp_nonce_field( 'sbim_edit_' . $id, 'sbim_edit_nonce' ); ?>

						<div class="sbim-field">
							<label for="sbim-url" class="sbim-label">
								<?php esc_html_e( 'Page URL', 'schema-bulk-import-manager' ); ?>
								<span class="sbim-required">*</span>
							</label>
							<input type="url"
								   id="sbim-url"
								   name="url"
								   value="<?php echo esc_url( $row->url ); ?>"
								   class="sbim-input sbim-input--full"
								   required
								   placeholder="https://example.com/your-page/" />
							<p class="sbim-field__hint"><?php esc_html_e( 'Enter the full URL including https:// and domain.', 'schema-bulk-import-manager' ); ?></p>
						</div>

						<div class="sbim-field">
							<label for="sbim-schema" class="sbim-label">
								<?php esc_html_e( 'JSON-LD Schema Markup', 'schema-bulk-import-manager' ); ?>
								<span class="sbim-required">*</span>
							</label>
							<textarea id="sbim-schema"
									  name="schema_markup"
									  class="sbim-textarea sbim-textarea--code"
									  rows="25"
									  required><?php echo esc_textarea( $row->schema_markup ); ?></textarea>
							<p class="sbim-field__hint"><?php esc_html_e( 'Must include the full &lt;script type="application/ld+json"&gt;...&lt;/script&gt; block.', 'schema-bulk-import-manager' ); ?></p>
						</div>

						<div class="sbim-form-actions">
							<button type="submit" class="button button-primary button-large">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Save Changes', 'schema-bulk-import-manager' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-schemas' ) ); ?>" class="button button-secondary button-large">
								<?php esc_html_e( 'Cancel', 'schema-bulk-import-manager' ); ?>
							</a>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="sbim-edit-sidebar">
			<!-- Record Meta -->
			<div class="sbim-card">
				<div class="sbim-card__header">
					<h3><?php esc_html_e( 'Record Info', 'schema-bulk-import-manager' ); ?></h3>
				</div>
				<div class="sbim-card__body">
					<dl class="sbim-meta-list">
						<dt><?php esc_html_e( 'ID', 'schema-bulk-import-manager' ); ?></dt>
						<dd><?php echo esc_html( $row->id ); ?></dd>

						<dt><?php esc_html_e( 'Post ID', 'schema-bulk-import-manager' ); ?></dt>
						<dd>
							<?php if ( $row->post_id ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>" target="_blank">
									<?php echo esc_html( $row->post_id ); ?>
									<span class="dashicons dashicons-external sbim-ext-icon"></span>
								</a>
							<?php else : ?>
								<span class="sbim-muted"><?php esc_html_e( 'Not resolved', 'schema-bulk-import-manager' ); ?></span>
							<?php endif; ?>
						</dd>

						<dt><?php esc_html_e( 'Status', 'schema-bulk-import-manager' ); ?></dt>
						<dd>
							<?php if ( $row->status === 'active' ) : ?>
								<span class="sbim-badge sbim-badge--success"><?php esc_html_e( 'Active', 'schema-bulk-import-manager' ); ?></span>
							<?php else : ?>
								<span class="sbim-badge sbim-badge--danger"><?php esc_html_e( 'Not Found', 'schema-bulk-import-manager' ); ?></span>
							<?php endif; ?>
						</dd>

						<dt><?php esc_html_e( 'Created', 'schema-bulk-import-manager' ); ?></dt>
						<dd><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at ) ) ); ?></dd>

						<dt><?php esc_html_e( 'Updated', 'schema-bulk-import-manager' ); ?></dt>
						<dd><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->updated_at ) ) ); ?></dd>
					</dl>

					<?php if ( $row->post_id ) : ?>
						<a href="<?php echo esc_url( get_permalink( $row->post_id ) ); ?>" target="_blank" class="button button-secondary sbim-btn-full">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'View Live Page', 'schema-bulk-import-manager' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Delete -->
			<div class="sbim-card sbim-card--danger">
				<div class="sbim-card__header">
					<h3><?php esc_html_e( 'Danger Zone', 'schema-bulk-import-manager' ); ?></h3>
				</div>
				<div class="sbim-card__body">
					<p><?php esc_html_e( 'Permanently delete this schema. This cannot be undone.', 'schema-bulk-import-manager' ); ?></p>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'sbim-schemas', 'action' => 'delete', 'id' => $row->id ], admin_url( 'admin.php' ) ), 'sbim_delete_' . $row->id ) ); ?>"
					   class="button button-link-delete sbim-delete-confirm"
					   data-confirm="<?php esc_attr_e( 'Are you sure you want to delete this schema? This cannot be undone.', 'schema-bulk-import-manager' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Delete This Schema', 'schema-bulk-import-manager' ); ?>
					</a>
				</div>
			</div>
		</div>

	</div>

</div><!-- .sbim-wrap -->