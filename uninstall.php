<?php
/**
 * Uninstall — runs when plugin is deleted from WP admin.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'sbim_schemas';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'sbim_version' );
delete_option( 'sbim_db_version' );
delete_option( 'sbim_flush_rewrite' );

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sbim_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sbim_%'" );