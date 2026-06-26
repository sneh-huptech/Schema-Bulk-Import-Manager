<?php
/**
 * Template: Generate functions.php Code page.
 *
 * @package SchemaBulkImportManager
 * @var array  $records
 * @var string $generated_code  — passed from page_generate() in SBIM_Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_schemas  = ! empty( $generated_code );
$active_count = count( array_filter( $records, fn( $r ) => $r['status'] === 'active' ) );
?>

<div class="wrap sbim-wrap">

	<div class="sbim-header">
		<div class="sbim-header__inner">
			<div class="sbim-header__brand">
				<span class="dashicons dashicons-editor-code"></span>
				<h1><?php esc_html_e( 'Generate functions.php Code', 'schema-bulk-import-manager' ); ?></h1>
				<?php if ( $has_schemas ) : ?>
					<span class="sbim-count-badge">
						<?php echo esc_html( $active_count ); ?> <?php esc_html_e( 'schemas', 'schema-bulk-import-manager' ); ?>
					</span>
				<?php endif; ?>
			</div>
			<div class="sbim-header__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-schemas' ) ); ?>" class="button button-secondary">
					← <?php esc_html_e( 'Back to All Schemas', 'schema-bulk-import-manager' ); ?>
				</a>
			</div>
		</div>
	</div>

	<?php if ( ! $has_schemas ) : ?>

		<!-- No schemas state -->
		<div class="sbim-card">
			<div class="sbim-card__body">
				<div class="sbim-empty-state">
					<span class="dashicons dashicons-editor-code"></span>
					<h3><?php esc_html_e( 'No active schemas found.', 'schema-bulk-import-manager' ); ?></h3>
					<p>
						<?php esc_html_e( 'Import a CSV first to generate your functions.php code.', 'schema-bulk-import-manager' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-import' ) ); ?>">
							<?php esc_html_e( 'Import CSV →', 'schema-bulk-import-manager' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>

	<?php else : ?>

		<!-- Info notice -->
		<div class="sbim-notice sbim-notice--info">
			<span class="dashicons dashicons-info-outline"></span>
			<div>
				<strong><?php esc_html_e( 'How to use:', 'schema-bulk-import-manager' ); ?></strong>
				<?php esc_html_e( 'Copy the code below and paste it into your theme\'s functions.php file. Your schemas will work permanently — even if this plugin is deactivated or deleted.', 'schema-bulk-import-manager' ); ?>
			</div>
		</div>

		<!-- Step Guide -->
		<div class="sbim-card">
			<div class="sbim-card__header">
				<h2><?php esc_html_e( '3 Steps to Make Schemas Permanent', 'schema-bulk-import-manager' ); ?></h2>
			</div>
			<div class="sbim-card__body">
				<div class="sbim-steps">

					<div class="sbim-step">
						<div class="sbim-step__number">1</div>
						<div class="sbim-step__icon"><span class="dashicons dashicons-editor-code"></span></div>
						<div class="sbim-step__content">
							<strong><?php esc_html_e( 'Copy the Code', 'schema-bulk-import-manager' ); ?></strong>
							<p><?php esc_html_e( 'Click the "Copy Code" button below. All your schemas are combined into one PHP function automatically.', 'schema-bulk-import-manager' ); ?></p>
						</div>
					</div>

					<div class="sbim-step">
						<div class="sbim-step__number">2</div>
						<div class="sbim-step__icon"><span class="dashicons dashicons-edit"></span></div>
						<div class="sbim-step__content">
							<strong><?php esc_html_e( 'Open functions.php', 'schema-bulk-import-manager' ); ?></strong>
							<p><?php esc_html_e( 'Go to Appearance → Theme File Editor → functions.php. Or open via FTP / cPanel file manager.', 'schema-bulk-import-manager' ); ?></p>
						</div>
					</div>

					<div class="sbim-step">
						<div class="sbim-step__number">3</div>
						<div class="sbim-step__icon"><span class="dashicons dashicons-saved"></span></div>
						<div class="sbim-step__content">
							<strong><?php esc_html_e( 'Paste & Save', 'schema-bulk-import-manager' ); ?></strong>
							<p><?php esc_html_e( 'Paste the code at the very bottom of functions.php. Save the file. Your schemas are now permanent and plugin-independent.', 'schema-bulk-import-manager' ); ?></p>
						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- Generated Code Block -->
		<div class="sbim-card">
			<div class="sbim-card__header sbim-card__header--flex">
				<h2><?php esc_html_e( 'Generated Code', 'schema-bulk-import-manager' ); ?></h2>
				<div style="display:flex; gap:8px; flex-wrap:wrap;">
					<button type="button" id="sbim-copy-btn" class="button button-primary">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy Code', 'schema-bulk-import-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'sbim_action' => 'download_php' ], admin_url( 'admin.php' ) ), 'sbim_download_php' ) ); ?>"
					   class="button button-secondary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download .php File', 'schema-bulk-import-manager' ); ?>
					</a>
				</div>
			</div>
			<div class="sbim-card__body sbim-card__body--flush">

				<!-- Copy success notice -->
				<div id="sbim-copy-success" style="display:none;" class="sbim-notice sbim-notice--success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Code copied to clipboard! Now paste it into your functions.php file.', 'schema-bulk-import-manager' ); ?>
				</div>

				<!-- Code textarea -->
				<div class="sbim-code-wrap">
					<textarea
						id="sbim-generated-code"
						class="sbim-code-textarea"
						readonly
						spellcheck="false"
						autocomplete="off"
					><?php echo esc_textarea( $generated_code ); ?></textarea>
				</div>

			</div>
		</div>

		<!-- Warning notice -->
		<div class="sbim-notice sbim-notice--warning">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong><?php esc_html_e( 'Important:', 'schema-bulk-import-manager' ); ?></strong>
				<?php esc_html_e( 'Every time you add new schemas via CSV, come back to this page and regenerate the code. Replace the old code in functions.php with the newly generated code.', 'schema-bulk-import-manager' ); ?>
			</div>
		</div>

	<?php endif; ?>

</div><!-- .sbim-wrap -->