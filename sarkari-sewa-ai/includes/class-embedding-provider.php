<?php
/**
 * Embedding Provider Interface and Factory.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Sarkari_Sewa_AI_Embedding_Interface {

	/**
	 * Generate vector embedding for a single text or batch of texts.
	 *
	 * @param string|array $input Single text string or array of text strings.
	 * @return array Array with 'success' => bool, 'embeddings' => array, 'dimensions' => int, 'error' => string.
	 */
	public function generate_embeddings( $input );
}

class Sarkari_Sewa_AI_Embedding_Factory {

	/**
	 * Get embedding provider based on configured AI provider or dedicated model.
	 *
	 * @return Sarkari_Sewa_AI_Embedding_Interface
	 */
	public static function get_provider() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$provider_type = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'openai';

		if ( $provider_type === 'gemini' ) {
			return new Sarkari_Sewa_AI_Gemini_Provider();
		}

		return new Sarkari_Sewa_AI_OpenAI_Provider();
	}
}
