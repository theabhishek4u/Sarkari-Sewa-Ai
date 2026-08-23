<?php
/**
 * Security & Sanitization Helper.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Security {

	/**
	 * Verify admin capabilities.
	 *
	 * @param string $capability Capability required.
	 * @return bool
	 */
	public static function check_admin_permission( $capability = 'manage_options' ) {
		return current_user_can( $capability );
	}

	/**
	 * Verify nonce.
	 *
	 * @param string $nonce Nonce token.
	 * @param string $action Action name.
	 * @return bool
	 */
	public static function verify_nonce( $nonce, $action = 'sarkari_sewa_ai_admin_action' ) {
		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Sanitize user chat message.
	 *
	 * @param string $message Raw user input.
	 * @param int    $max_length Max allowable character length.
	 * @return string Sanitized clean text.
	 */
	public static function sanitize_chat_query( $message, $max_length = 500 ) {
		if ( ! is_string( $message ) ) {
			return '';
		}

		// Strip HTML tags and null bytes
		$cleaned = wp_strip_all_tags( $message );
		$cleaned = str_replace( chr( 0 ), '', $cleaned );

		// Remove excessive whitespace and carriage returns
		$cleaned = preg_replace( '/\s+/u', ' ', $cleaned );
		$cleaned = trim( $cleaned );

		// Truncate to maximum configured length
		if ( mb_strlen( $cleaned ) > $max_length ) {
			$cleaned = mb_substr( $cleaned, 0, $max_length );
		}

		return $cleaned;
	}

	/**
	 * Normalize user query for consistent search and caching.
	 *
	 * @param string $query User query string.
	 * @return string Normalized lowercase query.
	 */
	public static function normalize_query( $query ) {
		$normalized = mb_strtolower( $query, 'UTF-8' );

		// Remove common punctuation that doesn't aid semantic search
		$normalized = preg_replace( '/[?!,.:;\"\'`]/u', '', $normalized );

		// Normalize Hinglish common synonyms
		$synonyms = array(
			'/\bbharti\b/u'     => 'vacancy',
			'/\bnaukri\b/u'     => 'job',
			'/\bkab aayega\b/u' => 'date',
			'/\bkab tak\b/u'    => 'last date',
			'/\bpatra\b/u'      => 'eligibility',
			'/\byogyata\b/u'    => 'eligibility',
			'/\bpriksha\b/u'    => 'exam',
			'/\bpariksha\b/u'   => 'exam',
		);

		foreach ( $synonyms as $pattern => $replacement ) {
			$normalized = preg_replace( $pattern, $replacement, $normalized );
		}

		return trim( preg_replace( '/\s+/u', ' ', $normalized ) );
	}
}
