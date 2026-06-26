<?php
/**
 * Plugin Name:       Schema Bulk Import Manager
 * Plugin URI:        https://snehgohil-portfolio.netlify.app/
 * Description:       Bulk import JSON-LD schema markup via CSV. Automatically injects schemas into matching pages with zero modification.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://snehgohil-portfolio.netlify.app/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       schema-bulk-import-manager
 * Domain Path:       /languages
 *
 * @package SchemaBulkImportManager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SBIM_VERSION',      '1.0.0' );
define( 'SBIM_FILE',         __FILE__ );
define( 'SBIM_PATH',         plugin_dir_path( __FILE__ ) );
define( 'SBIM_URL',          plugin_dir_url( __FILE__ ) );
define( 'SBIM_BASENAME',     plugin_basename( __FILE__ ) );
define( 'SBIM_TABLE_NAME',   'sbim_schemas' );

/**
 * Main plugin class — singleton.
 */
final class Schema_Bulk_Import_Manager {

	/** @var Schema_Bulk_Import_Manager|null */
	private static $instance = null;

	/** @return Schema_Bulk_Import_Manager */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		require_once SBIM_PATH . 'includes/class-sbim-database.php';
		require_once SBIM_PATH . 'includes/class-sbim-csv-handler.php';
		require_once SBIM_PATH . 'includes/class-sbim-validator.php';
		require_once SBIM_PATH . 'includes/class-sbim-url-matcher.php';
		require_once SBIM_PATH . 'includes/class-sbim-frontend.php';
		require_once SBIM_PATH . 'admin/class-sbim-admin.php';
		require_once SBIM_PATH . 'admin/class-sbim-ajax.php';
	}

	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init',           [ $this, 'init' ] );

		// Frontend injection.
		SBIM_Frontend::instance();

		// Admin.
		if ( is_admin() ) {
			SBIM_Admin::instance();
			SBIM_Ajax::instance();
		}
	}

	public function init(): void {
		// Flush rewrite rules once after activation if needed.
		if ( get_option( 'sbim_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'sbim_flush_rewrite' );
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'schema-bulk-import-manager',
			false,
			SBIM_PATH . 'languages'
		);
	}
}

/**
 * Activation hook.
 */
function sbim_activate(): void {
	require_once SBIM_PATH . 'includes/class-sbim-database.php';
	SBIM_Database::create_tables();
	add_option( 'sbim_version', SBIM_VERSION );
	add_option( 'sbim_flush_rewrite', true );
}
register_activation_hook( SBIM_FILE, 'sbim_activate' );

/**
 * Deactivation hook.
 */
function sbim_deactivate(): void {
	// Clean up transients.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sbim_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sbim_%'" );
}
register_deactivation_hook( SBIM_FILE, 'sbim_deactivate' );

/**
 * Boot the plugin.
 */
function sbim(): Schema_Bulk_Import_Manager {
	return Schema_Bulk_Import_Manager::instance();
}
sbim();