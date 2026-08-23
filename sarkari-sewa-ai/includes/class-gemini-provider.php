<?php
/**
 * Google Gemini Provider Implementation.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Gemini_Provider implements Sarkari_Sewa_AI_Provider_Interface, Sarkari_Sewa_AI_Embedding_Interface {

	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Get Gemini API key from settings.
	 *
	 * @return string
	 */
	private function get_api_key() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		return isset( $settings['gemini_api_key'] ) ? trim( $settings['gemini_api_key'] ) : '';
	}

	/**
	 * Generate chat response via Gemini.
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
				'error'   => 'Google Gemini API key is missing.',
			);
		}

		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$model    = ! empty( $settings['gemini_model'] ) ? trim( $settings['gemini_model'] ) : 'gemini-1.5-flash';
		$model    = preg_replace( '#^models/#', '', $model );
		$temp     = isset( $settings['temperature'] ) ? (float) $settings['temperature'] : 0.2;
		$max_tok  = isset( $settings['max_tokens'] ) ? (int) $settings['max_tokens'] : 500;

		$url = sprintf( '%s/models/%s:generateContent?key=%s', self::API_BASE, rawurlencode( $model ), rawurlencode( $api_key ) );

		$combined_prompt = $system_prompt . "\n\n" . $user_prompt;

		$payload = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $combined_prompt ),
					),
				),
			),
			'generationConfig' => array(
				'temperature'     => $temp,
				'maxOutputTokens' => $max_tok,
			),
		);

		$response = wp_remote_post( $url, array(
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 30,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			Sarkari_Sewa_AI_Logger::log( 'provider_error', 'Gemini HTTP error: ' . $response->get_error_message(), array(), 'error' );
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => 'Gemini connection error: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( $status_code !== 200 ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Gemini HTTP ' . $status_code;
			Sarkari_Sewa_AI_Logger::log( 'provider_error', 'Gemini API Error: ' . $error_msg, array( 'status' => $status_code, 'body' => $raw_body ), 'error' );
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => $error_msg,
			);
		}

		if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return array(
				'success' => false,
				'answer'  => '',
				'error'   => 'Empty response received from Gemini.',
			);
		}

		$answer = trim( $body['candidates'][0]['content']['parts'][0]['text'] );

		return array(
			'success' => true,
			'answer'  => $answer,
			'raw'     => $body,
			'error'   => '',
		);
	}

	/**
	 * Generate text embedding via text-embedding-004.
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
				'error'      => 'Gemini API key is missing.',
			);
		}

		$model = 'text-embedding-004';

		if ( is_array( $input ) ) {
			// Batch embedding request
			$url = sprintf( '%s/models/%s:batchEmbedContents?key=%s', self::API_BASE, rawurlencode( $model ), rawurlencode( $api_key ) );
			$requests = array();
			foreach ( $input as $text ) {
				$requests[] = array(
					'model'   => 'models/' . $model,
					'content' => array(
						'parts' => array( array( 'text' => $text ) ),
					),
				);
			}
			$payload = array( 'requests' => $requests );

			$response = wp_remote_post( $url, array(
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload ),
				'timeout'     => 30,
				'data_format' => 'body',
			) );

			if ( is_wp_error( $response ) ) {
				return array( 'success' => false, 'embeddings' => array(), 'dimensions' => 0, 'error' => $response->get_error_message() );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['embeddings'] ) ) {
				return array( 'success' => false, 'embeddings' => array(), 'dimensions' => 0, 'error' => 'No embeddings in Gemini response' );
			}

			$embeddings = array();
			foreach ( $body['embeddings'] as $emb ) {
				$embeddings[] = $emb['values'];
			}

			return array(
				'success'    => true,
				'embeddings' => $embeddings,
				'dimensions' => ! empty( $embeddings[0] ) ? count( $embeddings[0] ) : 768,
				'error'      => '',
			);
		}

		// Single text embedding
		$url = sprintf( '%s/models/%s:embedContent?key=%s', self::API_BASE, rawurlencode( $model ), rawurlencode( $api_key ) );
		$payload = array(
			'model'   => 'models/' . $model,
			'content' => array(
				'parts' => array( array( 'text' => $input ) ),
			),
		);

		$response = wp_remote_post( $url, array(
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 30,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'embeddings' => array(), 'dimensions' => 0, 'error' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['embedding']['values'] ) ) {
			return array( 'success' => false, 'embeddings' => array(), 'dimensions' => 0, 'error' => 'No embedding values returned' );
		}

		return array(
			'success'    => true,
			'embeddings' => $body['embedding']['values'],
			'dimensions' => count( $body['embedding']['values'] ),
			'error'      => '',
		);
	}

	/**
	 * Test Gemini connectivity.
	 *
	 * @return array
	 */
	public function test_connection() {
		$result = $this->generate_chat_response( 'You are a test assistant.', 'Reply with OK.' );
		if ( $result['success'] ) {
			return array(
				'success' => true,
				'message' => 'Connected successfully to Google Gemini API.',
			);
		}

		return array(
			'success' => false,
			'message' => $result['error'],
		);
	}
}
