<?php
/**
 * Transient Cache Manager for Repeated Queries and Responses.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Cache {

	/**
	 * Get cached response for a query.
	 *
	 * @param string $query User query.
	 * @return array|false Cached array or false.
	 */
	public static function get_query_cache( $query ) {
		$key = self::generate_cache_key( $query );
		return get_transient( $key );
	}

	/**
	 * Set cached response for a query.
	 *
	 * @param string $query User query.
	 * @param array  $data Response data to store.
	 * @return bool
	 */
	public static function set_query_cache( $query, $data ) {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$duration = isset( $settings['cache_duration_minutes'] ) ? (int) $settings['cache_duration_minutes'] : 10;

		if ( $duration <= 0 ) {
			return false;
		}

		$key = self::generate_cache_key( $query );
		return set_transient( $key, $data, $duration * MINUTE_IN_SECONDS );
	}

	/**
	 * Invalidate all cached queries (e.g. after index changes).
	 */
	public static function invalidate_all_query_caches() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sse_ai_qc_%' OR option_name LIKE '_transient_timeout_sse_ai_qc_%'" );
	}

	/**
	 * Generate uniform transient key.
	 *
	 * @param string $query
	 * @return string
	 */
	private static function generate_cache_key( $query ) {
		$normalized = Sarkari_Sewa_AI_Security::normalize_query( $query );
		return 'sse_ai_qc_' . md5( $normalized );
	}
}
