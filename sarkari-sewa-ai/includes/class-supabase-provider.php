<?php
/**
 * Supabase pgvector Provider Implementation.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Supabase_Provider implements Sarkari_Sewa_AI_Vector_Interface {

	/**
	 * Get Supabase configurations from settings.
	 *
	 * @return array
	 */
	private function get_config() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		return array(
			'url'        => isset( $settings['supabase_url'] ) ? rtrim( trim( $settings['supabase_url'] ), '/' ) : '',
			'api_key'    => isset( $settings['supabase_service_role_key'] ) ? trim( $settings['supabase_service_role_key'] ) : '',
			'table'      => ! empty( $settings['supabase_table'] ) ? trim( $settings['supabase_table'] ) : 'sarkari_ai_documents',
		);
	}

	/**
	 * Upsert document chunks and vectors to Supabase.
	 *
	 * @param array $documents
	 * @return array
	 */
	public function upsert_vectors( $documents ) {
		$config = $this->get_config();

		if ( empty( $config['url'] ) || empty( $config['api_key'] ) ) {
			return array(
				'success'        => false,
				'inserted_count' => 0,
				'error'          => 'Supabase credentials not configured.',
			);
		}

		if ( empty( $documents ) ) {
			return array( 'success' => true, 'inserted_count' => 0, 'error' => '' );
		}

		$rows = array();
		foreach ( $documents as $doc ) {
			$rows[] = array(
				'post_id'      => (int) $doc['post_id'],
				'chunk_index'  => (int) $doc['chunk_index'],
				'content'      => $doc['content'],
				'metadata'     => isset( $doc['metadata'] ) ? $doc['metadata'] : array(),
				'embedding'    => $doc['embedding'],
				'updated_at'   => current_time( 'mysql' ),
			);
		}

		$endpoint = sprintf( '%s/rest/v1/%s?on_conflict=post_id,chunk_index', $config['url'], rawurlencode( $config['table'] ) );

		$response = wp_remote_post( $endpoint, array(
			'headers'     => array(
				'apikey'        => $config['api_key'],
				'Authorization' => 'Bearer ' . $config['api_key'],
				'Content-Type'  => 'application/json',
				'Prefer'        => 'resolution=merge-duplicates',
			),
			'body'        => wp_json_encode( $rows ),
			'timeout'     => 30,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			Sarkari_Sewa_AI_Logger::log( 'vector_error', 'Supabase upsert failed: ' . $response->get_error_message(), array(), 'error' );
			return array(
				'success'        => false,
				'inserted_count' => 0,
				'error'          => $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status >= 200 && $status < 300 ) {
			return array(
				'success'        => true,
				'inserted_count' => count( $rows ),
				'error'          => '',
			);
		}

		$error_body = wp_remote_retrieve_body( $response );
		Sarkari_Sewa_AI_Logger::log( 'vector_error', 'Supabase upsert error HTTP ' . $status . ': ' . $error_body, array(), 'error' );

		return array(
			'success'        => false,
			'inserted_count' => 0,
			'error'          => 'Supabase error: ' . $error_body,
		);
	}

	/**
	 * Delete all vectors associated with a post ID from Supabase.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function delete_vectors_by_post_id( $post_id ) {
		$config = $this->get_config();

		if ( empty( $config['url'] ) || empty( $config['api_key'] ) ) {
			return array( 'success' => false, 'deleted_count' => 0, 'error' => 'Supabase credentials missing' );
		}

		$endpoint = sprintf( '%s/rest/v1/%s?post_id=eq.%d', $config['url'], rawurlencode( $config['table'] ), (int) $post_id );

		$response = wp_remote_request( $endpoint, array(
			'method'  => 'DELETE',
			'headers' => array(
				'apikey'        => $config['api_key'],
				'Authorization' => 'Bearer ' . $config['api_key'],
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'deleted_count' => 0, 'error' => $response->get_error_message() );
		}

		return array( 'success' => true, 'deleted_count' => 1, 'error' => '' );
	}

	/**
	 * Search similar vectors using Supabase match_documents RPC function.
	 *
	 * @param array $query_vector
	 * @param float $threshold
	 * @param int   $limit
	 * @param array $filters
	 * @return array
	 */
	public function search_similarity( $query_vector, $threshold = 0.65, $limit = 5, $filters = array() ) {
		$config = $this->get_config();

		if ( empty( $config['url'] ) || empty( $config['api_key'] ) ) {
			return array();
		}

		$endpoint = sprintf( '%s/rest/v1/rpc/match_documents', $config['url'] );

		$payload = array(
			'query_embedding' => $query_vector,
			'match_threshold' => (float) $threshold,
			'match_count'     => (int) $limit,
		);

		$response = wp_remote_post( $endpoint, array(
			'headers'     => array(
				'apikey'        => $config['api_key'],
				'Authorization' => 'Bearer ' . $config['api_key'],
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 15,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			Sarkari_Sewa_AI_Logger::log( 'vector_error', 'Supabase similarity search failed: ' . $response->get_error_message(), array(), 'error' );
			return array();
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			Sarkari_Sewa_AI_Logger::log( 'vector_error', 'Supabase search HTTP ' . $status . ': ' . wp_remote_retrieve_body( $response ), array(), 'error' );
			return array();
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $results ) ) {
			return array();
		}

		$formatted = array();
		foreach ( $results as $row ) {
			$metadata = isset( $row['metadata'] ) && is_array( $row['metadata'] ) ? $row['metadata'] : array();
			$formatted[] = array(
				'post_id'    => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
				'content'    => isset( $row['content'] ) ? $row['content'] : '',
				'similarity' => isset( $row['similarity'] ) ? (float) $row['similarity'] : 0.0,
				'metadata'   => $metadata,
				'title'      => isset( $metadata['title'] ) ? $metadata['title'] : get_the_title( $row['post_id'] ),
				'url'        => isset( $metadata['url'] ) ? $metadata['url'] : get_permalink( $row['post_id'] ),
				'category'   => isset( $metadata['category'] ) ? $metadata['category'] : 'General',
			);
		}

		return $formatted;
	}

	/**
	 * Test Supabase connectivity.
	 *
	 * @return array
	 */
	public function test_connection() {
		$config = $this->get_config();

		if ( empty( $config['url'] ) || empty( $config['api_key'] ) ) {
			return array(
				'success' => false,
				'message' => 'Supabase URL or API Key is missing.',
			);
		}

		// Try querying the table with limit 1
		$endpoint = sprintf( '%s/rest/v1/%s?select=id&limit=1', $config['url'], rawurlencode( $config['table'] ) );

		$response = wp_remote_get( $endpoint, array(
			'headers' => array(
				'apikey'        => $config['api_key'],
				'Authorization' => 'Bearer ' . $config['api_key'],
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Supabase connection error: ' . $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status === 200 ) {
			return array(
				'success' => true,
				'message' => 'Connected successfully to Supabase pgvector table.',
			);
		}

		return array(
			'success' => false,
			'message' => 'Supabase returned HTTP ' . $status . ': ' . wp_remote_retrieve_body( $response ),
		);
	}
}
