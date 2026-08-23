<?php
/**
 * Vector Provider Interface and Factory.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Sarkari_Sewa_AI_Vector_Interface {

	/**
	 * Upsert document chunks and vectors into vector database.
	 *
	 * @param array $documents Array of items with 'post_id', 'chunk_index', 'content', 'embedding', 'metadata'.
	 * @return array Array with 'success' => bool, 'inserted_count' => int, 'error' => string.
	 */
	public function upsert_vectors( $documents );

	/**
	 * Delete all vectors associated with a specific post ID.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array Array with 'success' => bool, 'deleted_count' => int, 'error' => string.
	 */
	public function delete_vectors_by_post_id( $post_id );

	/**
	 * Perform vector similarity search.
	 *
	 * @param array $query_vector Embedding vector of user query.
	 * @param float $threshold Similarity threshold (0.0 to 1.0).
	 * @param int   $limit Max documents to return.
	 * @param array $filters Metadata filters.
	 * @return array Array of matching chunks with similarity scores.
	 */
	public function search_similarity( $query_vector, $threshold = 0.65, $limit = 5, $filters = array() );

	/**
	 * Test connection to Vector Provider.
	 *
	 * @return array Array with 'success' => bool, 'message' => string.
	 */
	public function test_connection();
}

class Sarkari_Sewa_AI_Vector_Factory {

	/**
	 * Get configured vector provider.
	 *
	 * @return Sarkari_Sewa_AI_Vector_Interface
	 */
	public static function get_provider() {
		return new Sarkari_Sewa_AI_Supabase_Provider();
	}
}
