<?php
/**
 * Structured Logger Class.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Logger {

	/**
	 * Log an event.
	 *
	 * @param string $event_type Event identifier (e.g., 'chat_request', 'index_success', 'provider_error').
	 * @param string $message Descriptive message.
	 * @param array  $context Context data.
	 * @param string $severity Severity level ('info', 'warning', 'error', 'debug').
	 */
	public static function log( $event_type, $message, $context = array(), $severity = 'info' ) {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );

		// If debug message and debug mode is off, skip
		if ( $severity === 'debug' && empty( $settings['debug_mode'] ) ) {
			return;
		}

		// Check if chat requests are logged or if anonymous logging is disabled
		if ( $event_type === 'chat_request' && empty( $settings['enable_anonymous_logging'] ) ) {
			return;
		}

		global $wpdb;
		$logs_table = $wpdb->prefix . 'sarkari_ai_logs';

		// Sanitize context - remove secret keys before saving
		$sanitized_context = self::sanitize_context( $context );

		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			// Mask last octet for privacy
			$ip_parts = explode( '.', $ip );
			if ( count( $ip_parts ) === 4 ) {
				$ip_parts[3] = 'xxx';
				$ip          = implode( '.', $ip_parts );
			}
		}

		$wpdb->insert(
			$logs_table,
			array(
				'event_type'   => sanitize_key( $event_type ),
				'severity'     => sanitize_key( $severity ),
				'message'      => sanitize_textarea_field( $message ),
				'context_json' => wp_json_encode( $sanitized_context ),
				'ip_address'   => $ip,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Remove sensitive API keys and tokens from context logs.
	 *
	 * @param array $context
	 * @return array
	 */
	private static function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}

		$sensitive_keys = array( 'api_key', 'openai_api_key', 'gemini_api_key', 'supabase_service_role_key', 'authorization', 'bearer' );

		foreach ( $context as $key => $val ) {
			if ( in_array( strtolower( (string) $key ), $sensitive_keys, true ) ) {
				$context[ $key ] = '***REDACTED***';
			} elseif ( is_array( $val ) ) {
				$context[ $key ] = self::sanitize_context( $val );
			}
		}

		return $context;
	}

	/**
	 * Retrieve latest log entries.
	 *
	 * @param int    $limit Max records.
	 * @param string $severity Optional severity filter.
	 * @return array
	 */
	public static function get_logs( $limit = 50, $severity = '' ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'sarkari_ai_logs';

		$limit = absint( $limit );
		if ( ! empty( $severity ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$logs_table} WHERE severity = %s ORDER BY created_at DESC LIMIT %d",
				$severity,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$logs_table} ORDER BY created_at DESC LIMIT %d",
				$limit
			);
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Clear all log entries.
	 */
	public static function clear_logs() {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'sarkari_ai_logs';
		$wpdb->query( "TRUNCATE TABLE {$logs_table}" );
	}
}
