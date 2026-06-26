<?php
/**
 * Admin UI — menus, pages, assets.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_Admin {

	/** @var SBIM_Admin|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',            [ $this, 'handle_direct_actions' ] );
		add_filter( 'plugin_action_links_' . SBIM_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	// -------------------------------------------------------------------------
	// Menus
	// -------------------------------------------------------------------------

	public function register_menus(): void {
		add_menu_page(
			__( 'Schema Manager', 'schema-bulk-import-manager' ),
			__( 'Schema Manager', 'schema-bulk-import-manager' ),
			'manage_options',
			'sbim-dashboard',
			[ $this, 'page_dashboard' ],
			'dashicons-admin-site-alt3',
			76
		);

		add_submenu_page(
			'sbim-dashboard',
			__( 'Dashboard', 'schema-bulk-import-manager' ),
			__( 'Dashboard', 'schema-bulk-import-manager' ),
			'manage_options',
			'sbim-dashboard',
			[ $this, 'page_dashboard' ]
		);

		add_submenu_page(
			'sbim-dashboard',
			__( 'Import CSV', 'schema-bulk-import-manager' ),
			__( 'Import CSV', 'schema-bulk-import-manager' ),
			'manage_options',
			'sbim-import',
			[ $this, 'page_import' ]
		);

		add_submenu_page(
			'sbim-dashboard',
			__( 'All Schemas', 'schema-bulk-import-manager' ),
			__( 'All Schemas', 'schema-bulk-import-manager' ),
			'manage_options',
			'sbim-schemas',
			[ $this, 'page_schemas' ]
		);

		add_submenu_page(
			'sbim-dashboard',
			__( 'Edit Schema', 'schema-bulk-import-manager' ),
			'',   // Hidden from menu.
			'manage_options',
			'sbim-edit',
			[ $this, 'page_edit' ]
		);

		add_submenu_page(
			'sbim-dashboard',
			__( 'Generate Code', 'schema-bulk-import-manager' ),
			__( 'Generate Code', 'schema-bulk-import-manager' ),
			'manage_options',
			'sbim-generate',
			[ $this, 'page_generate' ]
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		$sbim_pages = [
			'toplevel_page_sbim-dashboard',
			'schema-manager_page_sbim-import',
			'schema-manager_page_sbim-schemas',
			'schema-manager_page_sbim-edit',
			'schema-manager_page_sbim-generate',
		];

		if ( ! in_array( $hook, $sbim_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'sbim-admin',
			SBIM_URL . 'assets/css/admin.css',
			[],
			SBIM_VERSION
		);

		wp_enqueue_script(
			'sbim-admin',
			SBIM_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			SBIM_VERSION,
			true
		);

		wp_localize_script( 'sbim-admin', 'SBIM', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'sbim_ajax_nonce' ),
			'strings'  => [
				'confirm_delete'      => __( 'Are you sure you want to delete this schema?', 'schema-bulk-import-manager' ),
				'confirm_bulk_delete' => __( 'Are you sure you want to delete the selected schemas?', 'schema-bulk-import-manager' ),
				'confirm_save'        => __( 'Save the previewed schemas to the database?', 'schema-bulk-import-manager' ),
				'processing'          => __( 'Processing...', 'schema-bulk-import-manager' ),
				'uploading'           => __( 'Uploading CSV...', 'schema-bulk-import-manager' ),
				'saving'              => __( 'Saving schemas...', 'schema-bulk-import-manager' ),
				'select_rows'         => __( 'Please select at least one row to delete.', 'schema-bulk-import-manager' ),
				'no_file'             => __( 'Please select a CSV file to upload.', 'schema-bulk-import-manager' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Page Renderers
	// -------------------------------------------------------------------------

	public function page_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}
		$stats = SBIM_Database::get_stats();
		require SBIM_PATH . 'templates/page-dashboard.php';
	}

	public function page_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}
		require SBIM_PATH . 'templates/page-import.php';
	}

	public function page_schemas(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}

		// Handle single delete.
		if ( isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) && $_GET['action'] === 'delete' ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sbim_delete_' . absint( $_GET['id'] ) ) ) {
				$row = SBIM_Database::get( absint( $_GET['id'] ) );
				if ( $row ) {
					SBIM_Database::delete( absint( $_GET['id'] ) );
					SBIM_Database::flush_cache( (int) $row->post_id );
				}
				wp_safe_redirect( add_query_arg( [ 'page' => 'sbim-schemas', 'deleted' => '1' ], admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status      = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$per_page    = 20;
		$page        = max( 1, absint( $_GET['paged'] ?? 1 ) );

		$results = SBIM_Database::get_all( [
			'search'   => $search,
			'status'   => $status,
			'per_page' => $per_page,
			'page'     => $page,
		] );

		$items       = $results['items'];
		$total       = $results['total'];
		$total_pages = (int) ceil( $total / $per_page );

		require SBIM_PATH . 'templates/page-schemas.php';
	}

	public function page_edit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}

		$id    = absint( $_GET['id'] ?? 0 );
		$row   = $id ? SBIM_Database::get( $id ) : null;
		$error = '';

		if ( ! $row ) {
			wp_safe_redirect( admin_url( 'admin.php?page=sbim-schemas' ) );
			exit;
		}

		// Handle form submission.
		if ( isset( $_POST['sbim_edit_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sbim_edit_nonce'] ) ), 'sbim_edit_' . $id ) ) {
				$error = __( 'Security check failed. Please try again.', 'schema-bulk-import-manager' );
			} else {
				$new_url    = trim( sanitize_url( wp_unslash( $_POST['url'] ?? '' ) ) );
				$new_schema = trim( wp_unslash( $_POST['schema_markup'] ?? '' ) );

				$validation = SBIM_Validator::validate( $new_schema, 0 );

				if ( ! $validation['valid'] ) {
					$error = $validation['error'];
				} else {
					// Resolve post_id for new URL.
					$post_id = SBIM_URL_Matcher::url_to_post_id( $new_url );

					SBIM_Database::update( $id, [
						'url'           => $new_url,
						'post_id'       => $post_id,
						'schema_markup' => $new_schema,
						'status'        => $post_id ? 'active' : 'not_found',
					] );

					SBIM_Database::flush_cache( (int) $row->post_id );
					if ( $post_id ) {
						SBIM_Database::flush_cache( $post_id );
					}

					wp_safe_redirect( add_query_arg( [ 'page' => 'sbim-schemas', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
					exit;
				}
			}
		}

		require SBIM_PATH . 'templates/page-edit.php';
	}

	public function page_generate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}

		$records        = SBIM_Database::get_all_for_export();
		$generated_code = self::build_generated_code( $records );

		require SBIM_PATH . 'templates/page-generate.php';
	}

	// -------------------------------------------------------------------------
	// Code Generator
	// -------------------------------------------------------------------------

	public static function build_generated_code( array $records ): string {
		$active = array_filter( $records, function( $row ) {
			return $row['status'] === 'active';
		});

		if ( empty( $active ) ) {
			return '';
		}

		$date  = current_time( 'Y-m-d H:i:s' );
		$total = count( $active );

		$code  = "<?php\n";
		$code .= "// ============================================================\n";
		$code .= "// Schema Bulk Import Manager — Generated Code\n";
		$code .= "// Generated : {$date}\n";
		$code .= "// Total     : {$total} schema(s)\n";
		$code .= "// HOW TO USE: Copy everything below and paste into functions.php\n";
		$code .= "// ============================================================\n\n";

		$code .= "if ( ! function_exists( 'sbim_inject_schemas' ) ) {\n\n";
		$code .= "    function sbim_inject_schemas() {\n\n";
		$code .= "        // Get current full URL.\n";
		$code .= "        \$current_url = home_url( add_query_arg( [], \$_SERVER['REQUEST_URI'] ) );\n";
		$code .= "        \$current_url = rtrim( \$current_url, '/' );\n\n";
		$code .= "        \$schemas = [\n";

		foreach ( $active as $row ) {
			$url            = rtrim( $row['url'], '/' );
			$schema_escaped = str_replace( "'", "\\'", $row['schema_markup'] );
			$code .= "\n            // URL: {$url}\n";
			$code .= "            '" . addslashes( $url ) . "' => '" . $schema_escaped . "',\n";
		}

		$code .= "\n        ];\n\n";
		$code .= "        // Match current URL and output schema.\n";
		$code .= "        foreach ( \$schemas as \$url => \$schema ) {\n";
		$code .= "            \$match = rtrim( \$url, '/' );\n";
		$code .= "            if ( \$current_url === \$match ) {\n";
		$code .= "                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n";
		$code .= "                echo \$schema;\n";
		$code .= "                return;\n";
		$code .= "            }\n";
		$code .= "        }\n\n";
		$code .= "    }\n\n";
		$code .= "    add_action( 'wp_head', 'sbim_inject_schemas', 1 );\n\n";
		$code .= "}\n";

		return $code;
	}

	// -------------------------------------------------------------------------
	// Direct (non-AJAX) actions
	// -------------------------------------------------------------------------

	public function handle_direct_actions(): void {
		if ( ! isset( $_GET['sbim_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}

		$action = sanitize_key( $_GET['sbim_action'] );

		// Download sample CSV.
		if ( $action === 'sample_csv' ) {
			check_admin_referer( 'sbim_sample_csv' );
			SBIM_CSV_Handler::output_sample_csv();
		}

		// Export all schemas as CSV.
		if ( $action === 'export_csv' ) {
			check_admin_referer( 'sbim_export_csv' );
			$records = SBIM_Database::get_all_for_export();
			SBIM_CSV_Handler::output_export_csv( $records );
		}

		// Download generated PHP file.
		if ( $action === 'download_php' ) {
			check_admin_referer( 'sbim_download_php' );
			$records = SBIM_Database::get_all_for_export();
			$code    = self::build_generated_code( $records );

			if ( empty( $code ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=sbim-generate&error=no_schemas' ) );
				exit;
			}

			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="schema-functions-' . date( 'Y-m-d' ) . '.php"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $code;
			exit;
		}

		// Download last import report.
		if ( $action === 'download_report' ) {
			check_admin_referer( 'sbim_download_report' );
			$report = get_transient( 'sbim_last_import_report' );
			if ( $report ) {
				SBIM_CSV_Handler::output_report_csv( $report );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=sbim-import&error=no_report' ) );
				exit;
			}
		}
	}

	// -------------------------------------------------------------------------
	// Plugin links
	// -------------------------------------------------------------------------

	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=sbim-import' ) ) . '">' . esc_html__( 'Import CSV', 'schema-bulk-import-manager' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}