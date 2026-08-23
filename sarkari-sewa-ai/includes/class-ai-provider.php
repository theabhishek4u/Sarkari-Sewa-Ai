<?php
/**
 * AI Provider Interface and Factory.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Sarkari_Sewa_AI_Provider_Interface {

	/**
	 * Generate chat completion.
	 *
	 * @param string $system_prompt Grounding system instructions.
	 * @param string $user_prompt Prompt containing query + retrieved context.
	 * @param array  $options Provider-specific parameters (temp, max_tokens, etc.).
	 * @return array Array with 'success' => bool, 'answer' => string, 'raw' => array, 'error' => string.
	 */
	public function generate_chat_response( $system_prompt, $user_prompt, $options = array() );

	/**
	 * Test provider connection.
	 *
	 * @return array Array with 'success' => bool, 'message' => string.
	 */
	public function test_connection();
}

class Sarkari_Sewa_AI_Provider_Factory {

	/**
	 * Create active AI Provider instance based on plugin settings.
	 *
	 * @return Sarkari_Sewa_AI_Provider_Interface|WP_Error
	 */
	public static function get_provider() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$provider_type = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'openai';

		switch ( $provider_type ) {
			case 'gemini':
				return new Sarkari_Sewa_AI_Gemini_Provider();

			case 'openai':
			default:
				return new Sarkari_Sewa_AI_OpenAI_Provider();
		}
	}
}
