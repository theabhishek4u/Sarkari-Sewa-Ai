<?php
/**
 * OpenAI Provider Implementation.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_OpenAI_Provider implements Sarkari_Sewa_AI_Provider_Interface, Sarkari_Sewa_AI_Embedding_Interface {

	const API_URL_CHAT       = 'https://api.openai.com/v1/chat/completions';
	const API_URL_EMBEDDINGS = 'https://api.openai.com/v1/embeddings';

	/**
	 * Get OpenAI API Key from settings.
	 *
	 * @return string
	 */
	private function get_api_key() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		return isset( $settings['openai_api_key'] ) ? trim( $settings['openai_api_key'] ) : '';
	}

	/**
	 * Generate chat completion.
	 *
	 * @param string $system_prompt
	 * @param string $user_prompt
	 * @param array  $options
	 * @return array
	 */
	public function generate_chat_response( $system_prompt, $user_prompt, $options = array() ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => 'OpenAI API key is missing. Please configure it in plugin settings.',
			);
		}

		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$model    = ! empty( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4o-mini';
		$temp     = isset( $settings['temperature'] ) ? (float) $settings['temperature'] : 0.2;
		$max_tok  = isset( $settings['max_tokens'] ) ? (int) $settings['max_tokens'] : 500;

		$payload = array(
			'model'       => $model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
			'temperature' => $temp,
			'max_tokens'  => $max_tok,
		);

		$response = wp_remote_post( self::API_URL_CHAT, array(
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 30,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			Sarkari_Sewa_AI_Logger::log( 'provider_error', 'OpenAI HTTP request failed: ' . $response->get_error_message(), array(), 'error' );
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => 'Service temporarily unavailable. Please try again shortly.',
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'OpenAI HTTP ' . $status_code;
			Sarkari_Sewa_AI_Logger::log( 'provider_error', 'OpenAI Error: ' . $error_msg, array( 'status' => $status_code ), 'error' );
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => 'Unable to generate response at this time.',
			);
		}

		if ( empty( $body['choices'][0]['message']['content'] ) ) {
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => 'Empty response received from AI provider.',
			);
		}

		$answer = trim( $body['choices'][0]['message']['content'] );

		return array(
			'success' => true,
			'answer'  => $answer,
			'raw'     => $body,
			'error'   => '',
		);
	}

	/**
	 * Generate vector embeddings using text-embedding-3-small.
	 *
	 * @param string|array $input
	 * @return array
	 */
	public function generate_embeddings( $input ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return array(
				'success'    => false,
				'embeddings' => array(),
				'dimensions' => 0,
				'error'      => 'OpenAI API key is missing.',
			);
		}

		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$model    = ! empty( $settings['embedding_model'] ) ? $settings['embedding_model'] : 'text-embedding-3-small';

		$payload = array(
			'model' => $model,
			'input' => $input,
		);

		$response = wp_remote_post( self::API_URL_EMBEDDINGS, array(
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 30,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			Sarkari_Sewa_AI_Logger::log( 'provider_error', 'OpenAI Embeddings HTTP error: ' . $response->get_error_message(), array(), 'error' );
			return array(
				'success'    => false,
				'embeddings' => array(),
				'dimensions' => 0,
				'error'      => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 || empty( $body['data'] ) ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Embeddings HTTP ' . $status_code;
			Sarkari_Sewa_AI_Logger::log( 'provider_error', 'OpenAI Embedding Error: ' . $error_msg, array( 'status' => $status_code ), 'error' );
			return array(
				'success'    => false,
				'embeddings' => array(),
				'dimensions' => 0,
				'error'      => $error_msg,
			);
		}

		// Check if batch or single
		$embeddings = array();
		foreach ( $body['data'] as $item ) {
			$embeddings[] = $item['embedding'];
		}

		$dimensions = ! empty( $embeddings[0] ) ? count( $embeddings[0] ) : 1536;

		return array(
			'success'    => true,
			'embeddings' => is_array( $input ) ? $embeddings : ( ! empty( $embeddings[0] ) ? $embeddings[0] : array() ),
			'dimensions' => $dimensions,
			'error'      => '',
		);
	}

	/**
	 * Test OpenAI connectivity with a minimal prompt.
	 *
	 * @return array
	 */
	public function test_connection() {
		$result = $this->generate_chat_response( 'You are a test assistant.', 'Hello, reply with OK.' );
		if ( $result['success'] ) {
			return array(
				'success' => true,
				'message' => 'Connected successfully to OpenAI API.',
			);
		}

		return array(
			'success' => false,
			'message' => $result['error'],
		);
	}
}
