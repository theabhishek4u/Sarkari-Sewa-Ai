<?php
/**
 * Keyword Full-Text Search Engine.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Keyword_Search {

	/**
	 * Search WordPress content for keyword matches.
	 *
	 * @param string $query Clean normalized search term.
	 * @param int    $limit Maximum results to retrieve.
	 * @param array  $filters Additional taxonomy / post_type filters.
	 * @return array Array of scored post result items.
	 */
	public static function search( $query, $limit = 10, $filters = array() ) {
		if ( empty( $query ) ) {
			return array();
		}

		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$post_types = ! empty( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array( 'post', 'page' );

		// 1. First run a WP_Query with search parameters
		$args = array(
			's'                   => $query,
			'post_type'           => $post_types,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		// Apply category/taxonomy filters if requested
		if ( ! empty( $filters['category'] ) ) {
			$args['category_name'] = sanitize_title( $filters['category'] );
		}

		$wp_query = new WP_Query( $args );
		$results  = array();

		if ( $wp_query->have_posts() ) {
			$query_words = explode( ' ', mb_strtolower( $query ) );

			foreach ( $wp_query->posts as $post ) {
				$post_id    = $post->ID;
				$title      = get_the_title( $post_id );
				$url        = get_permalink( $post_id );
				$content    = wp_strip_all_tags( $post->post_content );
				$categories = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) );
				$category   = ! empty( $categories ) && ! is_wp_error( $categories ) ? $categories[0] : 'General';

				// Calculate simple keyword relevance score (0.0 to 1.0)
				$score = self::calculate_relevance( $query_words, $title, $content, $category );

				$results[] = array(
					'post_id'       => $post_id,
					'title'         => $title,
					'url'           => $url,
					'category'      => $category,
					'content'       => mb_substr( $content, 0, 1000 ),
					'score'         => $score,
					'type'          => 'keyword',
					'published_at'  => get_the_date( 'c', $post_id ),
				);
			}
		}

		// Also check local chunks table if WP_Query returned fewer than requested
		if ( count( $results ) < $limit ) {
			$chunk_results = self::search_local_chunks( $query, $limit - count( $results ), array_column( $results, 'post_id' ) );
			$results = array_merge( $results, $chunk_results );
		}

		return $results;
	}

	/**
	 * Calculate keyword match relevance score.
	 *
	 * @param array  $words Query terms.
	 * @param string $title Post title.
	 * @param string $content Post content.
	 * @param string $category Category name.
	 * @return float
	 */
	private static function calculate_relevance( $words, $title, $content, $category ) {
		$title_lower    = mb_strtolower( $title );
		$content_lower  = mb_strtolower( $content );
		$category_lower = mb_strtolower( $category );

		$matches = 0;
		$total   = count( $words );

		if ( $total === 0 ) {
			return 0.0;
		}

		foreach ( $words as $word ) {
			if ( mb_strlen( $word ) < 2 ) {
				continue;
			}

			if ( mb_strpos( $title_lower, $word ) !== false ) {
				$matches += 2.0; // Higher weight for title match
			} elseif ( mb_strpos( $category_lower, $word ) !== false ) {
				$matches += 1.5; // Higher weight for category match
			} elseif ( mb_strpos( $content_lower, $word ) !== false ) {
				$matches += 1.0;
			}
		}

		$raw_score = $matches / ( $total * 2.0 );
		return min( 1.0, max( 0.0, $raw_score ) );
	}

	/**
	 * Search fallback in local `wp_sarkari_ai_chunks` table.
	 *
	 * @param string $query
	 * @param int    $limit
	 * @param array  $exclude_post_ids
	 * @return array
	 */
	private static function search_local_chunks( $query, $limit = 5, $exclude_post_ids = array() ) {
		global $wpdb;
		$chunks_table = $wpdb->prefix . 'sarkari_ai_chunks';
		$index_table  = $wpdb->prefix . 'sarkari_ai_index';

		$like = '%' . $wpdb->esc_like( $query ) . '%';

		$exclude_sql = '';
		if ( ! empty( $exclude_post_ids ) ) {
			$ids_in = implode( ',', array_map( 'absint', $exclude_post_ids ) );
			$exclude_sql = " AND c.post_id NOT IN ({$ids_in})";
		}

		$sql = $wpdb->prepare(
			"SELECT c.post_id, c.chunk_content, c.metadata_json, i.title, i.url, i.category
			 FROM {$chunks_table} c
			 JOIN {$index_table} i ON c.post_id = i.post_id
			 WHERE c.chunk_content LIKE %s {$exclude_sql}
			 GROUP BY c.post_id
			 LIMIT %d",
			$like,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		$results = array();
		foreach ( $rows as $row ) {
			$results[] = array(
				'post_id'      => (int) $row['post_id'],
				'title'        => $row['title'],
				'url'          => $row['url'],
				'category'     => $row['category'],
				'content'      => $row['chunk_content'],
				'score'        => 0.5,
				'type'         => 'keyword_chunk',
				'published_at' => get_the_date( 'c', $row['post_id'] ),
			);
		}

		return $results;
	}
}
