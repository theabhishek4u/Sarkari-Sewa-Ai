<?php
/**
 * Database Management Class.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Database {

	const DB_VERSION = '1.0.0';

	/**
	 * Create or upgrade custom plugin database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$index_table  = $wpdb->prefix . 'sarkari_ai_index';
		$chunks_table = $wpdb->prefix . 'sarkari_ai_chunks';
		$logs_table   = $wpdb->prefix . 'sarkari_ai_logs';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Index Table: Tracks indexed post state, hash, and status
		$sql_index = "CREATE TABLE {$index_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(50) NOT NULL DEFAULT 'post',
			title text NOT NULL,
			url text NOT NULL,
			category varchar(100) DEFAULT '',
			content_hash varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			chunk_count int(11) NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			indexed_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY status (status),
			KEY post_type (post_type)
		) {$charset_collate};";

		// Chunks Table: Local chunk storage for text search and fallback retrieval
		$sql_chunks = "CREATE TABLE {$chunks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			chunk_index int(11) NOT NULL DEFAULT 0,
			chunk_content longtext NOT NULL,
			chunk_tokens int(11) NOT NULL DEFAULT 0,
			metadata_json longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY chunk_index (chunk_index)
		) {$charset_collate};";

		// Logs Table: Structured event logs for monitoring and debug
		$sql_logs = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context_json longtext DEFAULT NULL,
			ip_address varchar(45) DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_index );
		dbDelta( $sql_chunks );
		dbDelta( $sql_logs );

		update_option( 'sarkari_sewa_ai_db_version', self::DB_VERSION );
	}

	/**
	 * Get index statistics.
	 *
	 * @return array
	 */
	public static function get_index_stats() {
		global $wpdb;

		$index_table  = $wpdb->prefix . 'sarkari_ai_index';
		$chunks_table = $wpdb->prefix . 'sarkari_ai_chunks';

		// Verify tables exist first
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_table ) ) !== $index_table ) {
			return array(
				'total_indexed'   => 0,
				'posts_indexed'   => 0,
				'pages_indexed'   => 0,
				'total_chunks'    => 0,
				'failed_count'    => 0,
				'pending_count'   => 0,
				'last_sync'       => 'Never',
			);
		}

		$total_indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index_table} WHERE status = 'indexed'" );
		$posts_indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index_table} WHERE status = 'indexed' AND post_type = 'post'" );
		$pages_indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index_table} WHERE status = 'indexed' AND post_type = 'page'" );
		$failed_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index_table} WHERE status = 'failed'" );
		$pending_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index_table} WHERE status = 'pending'" );
		$total_chunks  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$chunks_table}" );

		$last_sync_opt = get_option( 'sarkari_sewa_ai_last_sync', '' );
		$last_sync_fmt = ! empty( $last_sync_opt ) ? wp_date( 'd M Y, h:i A', strtotime( $last_sync_opt ) ) : 'Never';

		return array(
			'total_indexed' => $total_indexed,
			'posts_indexed' => $posts_indexed,
			'pages_indexed' => $pages_indexed,
			'total_chunks'  => $total_chunks,
			'failed_count'  => $failed_count,
			'pending_count' => $pending_count,
			'last_sync'     => $last_sync_fmt,
		);
	}
}
