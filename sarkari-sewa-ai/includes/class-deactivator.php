<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Deactivator {

	/**
	 * Clean up scheduled cron events on deactivation.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'sarkari_sewa_ai_cron_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'sarkari_sewa_ai_cron_sync' );
		}
	}
}
