<?php
/**
 * Template: Dashboard page.
 *
 * @package SchemaBulkImportManager
 * @var array $stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbim-wrap">

	<div class="sbim-header">
		<div class="sbim-header__inner">
			<div class="sbim-header__brand">
				<span class="dashicons dashicons-admin-site-alt3"></span>
				<h1><?php esc_html_e( 'Schema Bulk Import Manager', 'schema-bulk-import-manager' ); ?></h1>
			</div>
			<div class="sbim-header__actions">
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'sbim_action' => 'export_csv' ], admin_url( 'admin.php' ) ), 'sbim_export_csv' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export All Schemas', 'schema-bulk-import-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-import' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import CSV', 'schema-bulk-import-manager' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Stats Cards -->
	<div class="sbim-stats-grid">
		<div class="sbim-stat-card sbim-stat-card--total">
			<div class="sbim-stat-card__icon"><span class="dashicons dashicons-database"></span></div>
			<div class="sbim-stat-card__body">
				<div class="sbim-stat-card__value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div>
				<div class="sbim-stat-card__label"><?php esc_html_e( 'Total Schemas', 'schema-bulk-import-manager' ); ?></div>
			</div>
		</div>
		<div class="sbim-stat-card sbim-stat-card--active">
			<div class="sbim-stat-card__icon"><span class="dashicons dashicons-yes-alt"></span></div>
			<div class="sbim-stat-card__body">
				<div class="sbim-stat-card__value"><?php echo esc_html( number_format_i18n( $stats['active'] ) ); ?></div>
				<div class="sbim-stat-card__label"><?php esc_html_e( 'Active (Pages Found)', 'schema-bulk-import-manager' ); ?></div>
			</div>
		</div>
		<div class="sbim-stat-card sbim-stat-card--inactive">
			<div class="sbim-stat-card__icon"><span class="dashicons dashicons-warning"></span></div>
			<div class="sbim-stat-card__body">
				<div class="sbim-stat-card__value"><?php echo esc_html( number_format_i18n( $stats['inactive'] ) ); ?></div>
				<div class="sbim-stat-card__label"><?php esc_html_e( 'Not Found', 'schema-bulk-import-manager' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Quick Links -->
	<div class="sbim-card">
		<div class="sbim-card__header">
			<h2><?php esc_html_e( 'Quick Actions', 'schema-bulk-import-manager' ); ?></h2>
		</div>
		<div class="sbim-card__body sbim-quick-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-import' ) ); ?>" class="sbim-quick-action">
				<span class="dashicons dashicons-upload"></span>
				<span><?php esc_html_e( 'Import New CSV', 'schema-bulk-import-manager' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sbim-schemas' ) ); ?>" class="sbim-quick-action">
				<span class="dashicons dashicons-list-view"></span>
				<span><?php esc_html_e( 'View All Schemas', 'schema-bulk-import-manager' ); ?></span>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'sbim_action' => 'sample_csv' ], admin_url( 'admin.php' ) ), 'sbim_sample_csv' ) ); ?>" class="sbim-quick-action">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<span><?php esc_html_e( 'Download Sample CSV', 'schema-bulk-import-manager' ); ?></span>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'sbim_action' => 'export_csv' ], admin_url( 'admin.php' ) ), 'sbim_export_csv' ) ); ?>" class="sbim-quick-action">
				<span class="dashicons dashicons-download"></span>
				<span><?php esc_html_e( 'Export All Schemas', 'schema-bulk-import-manager' ); ?></span>
			</a>
		</div>
	</div>

	<!-- How to use -->
	<div class="sbim-card sbim-howto">
		<div class="sbim-card__header">
			<h2><?php esc_html_e( 'How To Use', 'schema-bulk-import-manager' ); ?></h2>
		</div>
		<div class="sbim-card__body">
			<div class="sbim-steps">
				<?php
				$steps = [
					[ 'icon' => 'media-spreadsheet', 'title' => __( 'Download Sample CSV', 'schema-bulk-import-manager' ), 'desc' => __( 'Click "Download Sample CSV" to get a pre-formatted template with example data.', 'schema-bulk-import-manager' ) ],
					[ 'icon' => 'admin-links',       'title' => __( 'Add Full URLs', 'schema-bulk-import-manager' ),       'desc' => __( 'In Column A, enter the complete page URL including https:// and domain.', 'schema-bulk-import-manager' ) ],
					[ 'icon' => 'editor-code',       'title' => __( 'Paste JSON-LD', 'schema-bulk-import-manager' ),       'desc' => __( 'In Column B, paste the complete JSON-LD block including &lt;script&gt; tags, exactly as your SEO agent delivers it.', 'schema-bulk-import-manager' ) ],
					[ 'icon' => 'upload',            'title' => __( 'Upload CSV', 'schema-bulk-import-manager' ),          'desc' => __( 'Go to Import CSV, upload your file. The plugin will parse and validate every row.', 'schema-bulk-import-manager' ) ],
					[ 'icon' => 'visibility',        'title' => __( 'Review Preview', 'schema-bulk-import-manager' ),      'desc' => __( 'Check the preview table. Rows marked Not Found or Invalid will be highlighted — review before saving.', 'schema-bulk-import-manager' ) ],
					[ 'icon' => 'saved',             'title' => __( 'Save Schemas', 'schema-bulk-import-manager' ),        'desc' => __( 'Click Save Schemas. Only valid Found rows are imported. You\'ll see a full import report.', 'schema-bulk-import-manager' ) ],
					[ 'icon' => 'search',            'title' => __( 'Verify with Google', 'schema-bulk-import-manager' ),  'desc' => __( 'Use Google Rich Results Test (search.google.com/test/rich-results) to confirm your schema is live.', 'schema-bulk-import-manager' ) ],
				];
				foreach ( $steps as $i => $step ) : ?>
					<div class="sbim-step">
						<div class="sbim-step__number"><?php echo esc_html( $i + 1 ); ?></div>
						<div class="sbim-step__icon"><span class="dashicons dashicons-<?php echo esc_attr( $step['icon'] ); ?>"></span></div>
						<div class="sbim-step__content">
							<strong><?php echo esc_html( $step['title'] ); ?></strong>
							<p><?php echo esc_html( $step['desc'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Footer -->
	<div class="sbim-footer">
		<p>
			<?php 
			printf(
				/* translators: %s: Author name */
				esc_html__( 'Schema Bulk Import Manager | Created by %s', 'schema-bulk-import-manager' ),
				'<a href="https://snehgohil-portfolio.netlify.app/" target="_blank" rel="noopener noreferrer">Sneh Gohil</a>'
			); 
			?>
		</p>
	</div>

</div><!-- .sbim-wrap -->