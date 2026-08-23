<?php
/**
 * Hybrid Search Engine Coordinator.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Search_Engine {

	/**
	 * Run hybrid search (Keyword + Semantic + Intent Boost + Re-ranking).
	 *
	 * @param string $query User input string.
	 * @param array  $options Optional overrides for threshold, limit, filters.
	 * @return array Array of ranked results and contextual chunks.
	 */
	public static function search( $query, $options = array() ) {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );

		$threshold = isset( $options['threshold'] ) ? (float) $options['threshold'] : ( isset( $settings['similarity_threshold'] ) ? (float) $settings['similarity_threshold'] : 0.65 );
		$limit     = isset( $options['limit'] ) ? (int) $options['limit'] : ( isset( $settings['max_search_results'] ) ? (int) $settings['max_search_results'] : 5 );

		// 1. Detect query intent & metadata hints
		$intent = self::detect_intent( $query );

		// 2. Fetch keyword search results
		$keyword_results = array();
		if ( ! empty( $settings['enable_keyword_search'] ) ) {
			$keyword_results = Sarkari_Sewa_AI_Keyword_Search::search( $query, $limit * 2, array( 'intent' => $intent ) );
		}

		// 3. Fetch semantic search results
		$semantic_results = array();
		if ( ! empty( $settings['enable_semantic_search'] ) ) {
			$semantic_results = Sarkari_Sewa_AI_Semantic_Search::search( $query, $threshold, $limit * 2 );
		}

		// 4. Combine and deduplicate by post_id
		$combined_posts = array();

		// Add semantic results
		foreach ( $semantic_results as $item ) {
			$pid = $item['post_id'];
			if ( $pid <= 0 ) {
				continue;
			}

			$combined_posts[ $pid ] = array(
				'post_id'        => $pid,
				'title'          => $item['title'],
				'url'            => $item['url'],
				'category'       => $item['category'],
				'content'        => $item['content'],
				'semantic_score' => $item['score'],
				'keyword_score'  => 0.0,
				'published_at'   => $item['published_at'],
				'metadata'       => isset( $item['metadata'] ) ? $item['metadata'] : array(),
			);
		}

		// Merge keyword results
		foreach ( $keyword_results as $item ) {
			$pid = $item['post_id'];
			if ( $pid <= 0 ) {
				continue;
			}

			if ( isset( $combined_posts[ $pid ] ) ) {
				$combined_posts[ $pid ]['keyword_score'] = $item['score'];
				if ( empty( $combined_posts[ $pid ]['content'] ) ) {
					$combined_posts[ $pid ]['content'] = $item['content'];
				}
			} else {
				$combined_posts[ $pid ] = array(
					'post_id'        => $pid,
					'title'          => $item['title'],
					'url'            => $item['url'],
					'category'       => $item['category'],
					'content'        => $item['content'],
					'semantic_score' => 0.0,
					'keyword_score'  => $item['score'],
					'published_at'   => $item['published_at'],
					'metadata'       => array(),
				);
			}
		}

		if ( empty( $combined_posts ) ) {
			return array(
				'has_results' => false,
				'results'     => array(),
				'intent'      => $intent,
			);
		}

		// 5. Calculate Weighted Fusion Score
		$w_sem   = isset( $settings['semantic_weight'] ) ? (float) $settings['semantic_weight'] : 0.45;
		$w_kw    = isset( $settings['keyword_weight'] ) ? (float) $settings['keyword_weight'] : 0.35;
		$w_title = isset( $settings['title_match_weight'] ) ? (float) $settings['title_match_weight'] : 0.15;
		$w_fresh = isset( $settings['freshness_weight'] ) ? (float) $settings['freshness_weight'] : 0.05;

		$query_lower = mb_strtolower( $query );

		$ranked_list = array();
		foreach ( $combined_posts as $pid => $post_data ) {
			$title_score = 0.0;
			$words = explode( ' ', $query_lower );
			$match_count = 0;
			foreach ( $words as $w ) {
				if ( mb_strlen( $w ) >= 2 && mb_stripos( $post_data['title'], $w ) !== false ) {
					$match_count++;
				}
			}
			if ( count( $words ) > 0 ) {
				$title_score = min( 1.0, $match_count / count( $words ) );
			}

			// Category intent boost
			$cat_boost = 0.0;
			if ( ! empty( $intent['category_match'] ) && mb_stripos( $post_data['category'], $intent['category_match'] ) !== false ) {
				$cat_boost = 0.2;
			}

			// Freshness score (exponential decay based on days since publication)
			$freshness_score = 0.5;
			if ( ! empty( $post_data['published_at'] ) ) {
				$days_old = ( time() - strtotime( $post_data['published_at'] ) ) / ( DAY_IN_SECONDS );
				$freshness_score = exp( - $days_old / 365.0 ); // decay over 1 year
			}

			$final_score = ( $post_data['semantic_score'] * $w_sem )
						+ ( $post_data['keyword_score'] * $w_kw )
						+ ( $title_score * $w_title )
						+ ( $cat_boost * 0.1 )
						+ ( $freshness_score * $w_fresh );

			$post_data['final_score'] = round( $final_score, 4 );

			// Filter out results that have virtually zero relevance
			if ( $final_score >= ( $threshold * 0.5 ) ) {
				$ranked_list[] = $post_data;
			}
		}

		// Sort descending by final score
		usort( $ranked_list, function ( $a, $b ) {
			if ( $a['final_score'] === $b['final_score'] ) {
				return 0;
			}
			return ( $a['final_score'] > $b['final_score'] ) ? -1 : 1;
		} );

		// Slice top results
		$top_results = array_slice( $ranked_list, 0, $limit );

		// Anti-hallucination check: if all top results have very low score, return empty
		$has_results = false;
		if ( ! empty( $top_results ) ) {
			$highest_score = $top_results[0]['final_score'];
			if ( $highest_score >= ( $threshold * 0.6 ) ) {
				$has_results = true;
			}
		}

		return array(
			'has_results'      => $has_results,
			'results'          => $has_results ? $top_results : array(),
			'intent'           => $intent,
			'keyword_count'    => count( $keyword_results ),
			'semantic_count'   => count( $semantic_results ),
			'unfiltered_count' => count( $ranked_list ),
		);
	}

	/**
	 * Detect user query intent.
	 *
	 * @param string $query
	 * @return array
	 */
	public static function detect_intent( $query ) {
		$query_lower = mb_strtolower( $query );

		$intent = array(
			'type'           => 'general',
			'category_match' => '',
			'active_only'    => false,
		);

		if ( preg_match( '/\b(result|marks|merit list|cut off|score card)\b/i', $query_lower ) ) {
			$intent['type']           = 'results';
			$intent['category_match'] = 'Result';
		} elseif ( preg_match( '/\b(admit card|hall ticket|call letter|exam city)\b/i', $query_lower ) ) {
			$intent['type']           = 'admit_cards';
			$intent['category_match'] = 'Admit Card';
		} elseif ( preg_match( '/\b(answer key|response sheet|objection)\b/i', $query_lower ) ) {
			$intent['type']           = 'answer_keys';
			$intent['category_match'] = 'Answer Key';
		} elseif ( preg_match( '/\b(syllabus|exam pattern|previous paper)\b/i', $query_lower ) ) {
			$intent['type']           = 'syllabus';
			$intent['category_match'] = 'Syllabus';
		} elseif ( preg_match( '/\b(admission|counselling|entrance exam)\b/i', $query_lower ) ) {
			$intent['type']           = 'admission';
			$intent['category_match'] = 'Admission';
		} elseif ( preg_match( '/\b(job|vacancy|bharti|recruitment|post|form)\b/i', $query_lower ) ) {
			$intent['type']           = 'latest_jobs';
			$intent['category_match'] = 'Latest Jobs';
		}

		if ( preg_match( '/\b(active|currently open|baaki hai|last date bachi hai|running)\b/i', $query_lower ) ) {
			$intent['active_only'] = true;
		}

		return $intent;
	}
}
