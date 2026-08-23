<?php
/**
 * IP-based Rate Limiter & Daily Usage Manager.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Rate_Limiter {

	/**
	 * Check if current client IP is within per-minute burst rate limits.
	 *
	 * @return bool True if allowed, false if limit exceeded.
	 */
	public static function check_rate_limit() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$limit    = isset( $settings['rate_limit_requests'] ) ? (int) $settings['rate_limit_requests'] : 10;
		$window   = isset( $settings['rate_limit_window_secs'] ) ? (int) $settings['rate_limit_window_secs'] : 60;

		// If 0, rate limiting is disabled
		if ( $limit <= 0 ) {
			return true;
		}

		$ip = self::get_client_ip();
		$transient_key = 'sse_ai_rl_' . md5( $ip );

		$current_count = (int) get_transient( $transient_key );

		if ( $current_count >= $limit ) {
			Sarkari_Sewa_AI_Logger::log(
				'rate_limit',
				'Burst rate limit exceeded for client',
				array( 'ip' => $ip, 'count' => $current_count ),
				'warning'
			);
			return false;
		}

		if ( $current_count === 0 ) {
			set_transient( $transient_key, 1, $window );
		} else {
			set_transient( $transient_key, $current_count + 1, $window );
		}

		return true;
	}

	/**
	 * Check if current client IP is within 24-hour daily quota.
	 *
	 * @return bool True if allowed, false if daily limit reached.
	 */
	public static function check_daily_limit() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$daily_limit = isset( $settings['daily_message_limit'] ) ? (int) $settings['daily_message_limit'] : 10;

		// 0 means unlimited
		if ( $daily_limit <= 0 ) {
			return true;
		}

		$ip = self::get_client_ip();
		$transient_key = 'sse_ai_day_' . md5( $ip );

		$current_count = (int) get_transient( $transient_key );

		return ( $current_count < $daily_limit );
	}

	/**
	 * Increment daily usage count for the client IP.
	 */
	public static function increment_daily_usage() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$daily_limit = isset( $settings['daily_message_limit'] ) ? (int) $settings['daily_message_limit'] : 10;

		if ( $daily_limit <= 0 ) {
			return;
		}

		$ip = self::get_client_ip();
		$transient_key = 'sse_ai_day_' . md5( $ip );

		$current_count = (int) get_transient( $transient_key );

		if ( $current_count === 0 ) {
			// Set 24 hours expiry (DAY_IN_SECONDS = 86400)
			set_transient( $transient_key, 1, DAY_IN_SECONDS );
		} else {
			set_transient( $transient_key, $current_count + 1, DAY_IN_SECONDS );
		}
	}

	/**
	 * Get remaining message count for today.
	 *
	 * @return int Remaining queries count (-1 for unlimited).
	 */
	public static function get_remaining_daily_messages() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$daily_limit = isset( $settings['daily_message_limit'] ) ? (int) $settings['daily_message_limit'] : 10;

		if ( $daily_limit <= 0 ) {
			return -1; // Unlimited
		}

		$ip = self::get_client_ip();
		$transient_key = 'sse_ai_day_' . md5( $ip );
		$current_count = (int) get_transient( $transient_key );

		return max( 0, $daily_limit - $current_count );
	}

	/**
	 * Get client IP safely (supporting Cloudflare / Proxies if headers present).
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip      = trim( $ip_list[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1';
	}
}
