<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Sarkari_Sewa_AI
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'sarkari_sewa_ai_delete_data_on_uninstall', false );

if ( $delete_data ) {
	global $wpdb;

	// Drop custom tables
	$tables = array(
		$wpdb->prefix . 'sarkari_ai_index',
		$wpdb->prefix . 'sarkari_ai_chunks',
		$wpdb->prefix . 'sarkari_ai_logs',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete plugin options
	delete_option( 'sarkari_sewa_ai_settings' );
	delete_option( 'sarkari_sewa_ai_delete_data_on_uninstall' );
	delete_option( 'sarkari_sewa_ai_db_version' );
	delete_option( 'sarkari_sewa_ai_last_sync' );

	// Delete transients
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sse_ai_%' OR option_name LIKE '_transient_timeout_sse_ai_%'" );
}
