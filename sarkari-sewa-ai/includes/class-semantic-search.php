<?php
/**
 * Semantic Vector Search Engine.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Semantic_Search {

	/**
	 * Search vector database using embeddings.
	 *
	 * @param string $query User query text.
	 * @param float  $threshold Similarity threshold.
	 * @param int    $limit Max matches.
	 * @param array  $filters Metadata filters.
	 * @return array
	 */
	public static function search( $query, $threshold = 0.65, $limit = 5, $filters = array() ) {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );

		if ( empty( $settings['enable_semantic_search'] ) ) {
			return array();
		}

		// 1. Generate embedding vector for the search query
		$embedding_provider = Sarkari_Sewa_AI_Embedding_Factory::get_provider();
		$emb_response       = $embedding_provider->generate_embeddings( $query );

		if ( empty( $emb_response['success'] ) || empty( $emb_response['embeddings'] ) ) {
			Sarkari_Sewa_AI_Logger::log(
				'search_warning',
				'Failed to generate embedding for query: ' . ( isset( $emb_response['error'] ) ? $emb_response['error'] : 'Unknown error' ),
				array( 'query' => $query ),
				'warning'
			);
			return array();
		}

		$query_vector = $emb_response['embeddings'];

		// 2. Query Vector Provider
		$vector_provider = Sarkari_Sewa_AI_Vector_Factory::get_provider();
		$results         = $vector_provider->search_similarity( $query_vector, $threshold, $limit, $filters );

		// Standardize format
		$standardized = array();
		foreach ( $results as $item ) {
			$post_id   = isset( $item['post_id'] ) ? (int) $item['post_id'] : 0;
			$score     = isset( $item['similarity'] ) ? (float) $item['similarity'] : 0.0;
			$title     = isset( $item['title'] ) ? $item['title'] : get_the_title( $post_id );
			$url       = isset( $item['url'] ) ? $item['url'] : get_permalink( $post_id );
			$category  = isset( $item['category'] ) ? $item['category'] : 'General';
			$content   = isset( $item['content'] ) ? $item['content'] : '';

			$standardized[] = array(
				'post_id'       => $post_id,
				'title'         => $title,
				'url'           => $url,
				'category'      => $category,
				'content'       => $content,
				'score'         => $score,
				'type'          => 'semantic',
				'metadata'      => isset( $item['metadata'] ) ? $item['metadata'] : array(),
				'published_at'  => get_the_date( 'c', $post_id ),
			);
		}

		return $standardized;
	}
}
