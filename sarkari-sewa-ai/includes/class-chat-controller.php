<?php
/**
 * Chat Request Controller.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Chat_Controller {

	/**
	 * Process incoming chat query.
	 *
	 * @param WP_REST_Request $request REST API request.
	 * @return WP_REST_Response|WP_Error
	 */
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );

		// 1. Burst Rate Limiting Check (per minute)
		if ( ! Sarkari_Sewa_AI_Rate_Limiter::check_rate_limit() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Too many requests. Please wait a moment before asking again.',
					'code'    => 'rate_limit_exceeded',
				),
				429
			);
		}

		// 2. 24-Hour Daily Message Quota Check
		if ( ! Sarkari_Sewa_AI_Rate_Limiter::check_daily_limit() ) {
			$daily_limit = isset( $settings['daily_message_limit'] ) ? (int) $settings['daily_message_limit'] : 10;
			$limit_msg   = ! empty( $settings['daily_limit_message'] )
				? $settings['daily_limit_message']
				: "Aapki aaj ki message limit ({$daily_limit} sawal) poori ho chuki hai. Naye sawal puchne ke liye kal (24 ghante baad) wapas aayein! 😊";

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $limit_msg,
					'code'    => 'daily_limit_reached',
					'data'    => array(
						'remaining_today' => 0,
						'daily_limit'     => $daily_limit,
					),
				),
				429
			);
		}

		// 3. Validate & Sanitize Message
		$raw_message = $request->get_param( 'message' );
		$max_len     = isset( $settings['max_message_length'] ) ? (int) $settings['max_message_length'] : 500;

		$clean_query = Sarkari_Sewa_AI_Security::sanitize_chat_query( $raw_message, $max_len );

		if ( empty( $clean_query ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Please enter a valid search question.',
					'code'    => 'invalid_query',
				),
				400
			);
		}

		// Count this request towards the user's daily quota
		Sarkari_Sewa_AI_Rate_Limiter::increment_daily_usage();
		$remaining_today = Sarkari_Sewa_AI_Rate_Limiter::get_remaining_daily_messages();

		// 4. Check Transient Cache
		$cached = Sarkari_Sewa_AI_Cache::get_query_cache( $clean_query );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			$cached['remaining_today'] = $remaining_today;
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $cached,
					'cached'  => true,
				),
				200
			);
		}

		// 4. Run Hybrid Retrieval Engine
		$search_output = Sarkari_Sewa_AI_Search_Engine::search( $clean_query );

		// 5. Anti-Hallucination Gate: If no reliable results found, return polite fallback without calling AI
		if ( empty( $search_output['has_results'] ) || empty( $search_output['results'] ) ) {
			$fallback_answer = "मुझे Sarkari Sewa पर इस विषय से संबंधित कोई पोस्ट या सूचना नहीं मिली। मैं केवल SarkariSewa.com पर प्रकाशित आधिकारिक जानकारी के आधार पर उत्तर दे सकता हूँ।";

			$response_data = array(
				'answer'      => $fallback_answer,
				'has_results' => false,
				'results'     => array(),
			);

			// Cache no-result response briefly to save resources
			Sarkari_Sewa_AI_Cache::set_query_cache( $clean_query, $response_data );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $response_data,
				),
				200
			);
		}

		// 6. Build Context and Invoke AI Provider
		$retrieved_results = $search_output['results'];
		$system_prompt     = Sarkari_Sewa_AI_Prompt_Manager::get_system_prompt();
		$user_prompt       = Sarkari_Sewa_AI_Prompt_Manager::build_user_prompt( $clean_query, $retrieved_results );

		$ai_provider = Sarkari_Sewa_AI_Provider_Factory::get_provider();
		$ai_response = $ai_provider->generate_chat_response( $system_prompt, $user_prompt );

		if ( ! $ai_response['success'] ) {
			// Friendly fallback on provider failure
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'answer'      => "यहाँ Sarkari Sewa पर संबंधित जानकारी उपलब्ध है, लेकिन AI उत्तर तैयार करने में समस्या आ रही है। कृपया नीचे दिए गए लिंक देखें:",
						'has_results' => true,
						'results'     => self::format_source_cards( $retrieved_results ),
					),
				),
				200
			);
		}

		// 7. Format Clean Output Payload with Trusted WordPress Permalinks
		$final_results = self::format_source_cards( $retrieved_results );

		$response_data = array(
			'answer'          => $ai_response['answer'],
			'has_results'     => true,
			'results'         => $final_results,
			'remaining_today' => $remaining_today,
		);

		// 8. Cache response & log
		Sarkari_Sewa_AI_Cache::set_query_cache( $clean_query, $response_data );

		Sarkari_Sewa_AI_Logger::log(
			'chat_request',
			"Query: {$clean_query}",
			array(
				'query'         => $clean_query,
				'results_count' => count( $final_results ),
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $response_data,
			),
			200
		);
	}

	/**
	 * Attach verified post details and permalinks to source cards.
	 *
	 * @param array $posts
	 * @return array
	 */
	private static function format_source_cards( $posts ) {
		$cards = array();

		foreach ( $posts as $p ) {
			$post_id = isset( $p['post_id'] ) ? (int) $p['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$title     = get_the_title( $post_id );
			$permalink = get_permalink( $post_id );

			// Format last date / status if present in metadata
			$last_date = isset( $p['metadata']['last_date'] ) ? $p['metadata']['last_date'] : null;

			$cards[] = array(
				'post_id'      => $post_id,
				'title'        => $title ? $title : $p['title'],
				'url'          => $permalink ? $permalink : $p['url'],
				'category'     => ! empty( $p['category'] ) ? $p['category'] : 'General',
				'last_date'    => $last_date,
				'relevance'    => isset( $p['final_score'] ) ? (float) $p['final_score'] : (float) $p['semantic_score'],
				'published_at' => get_the_date( 'd M Y', $post_id ),
			);
		}

		return $cards;
	}
}
