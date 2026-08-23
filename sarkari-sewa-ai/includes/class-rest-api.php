<?php
/**
 * REST API Endpoints Handler.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Rest_API {

	/**
	 * Register REST routes hook.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all plugin endpoints.
	 */
	public function register_routes() {
		// 1. Chat Endpoint (Public)
		register_rest_route(
			SARKARI_SEWA_AI_REST_NAMESPACE,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Sarkari_Sewa_AI_Chat_Controller', 'handle_chat_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// 2. Status Endpoint (Public/Diagnostic)
		register_rest_route(
			SARKARI_SEWA_AI_REST_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_status_request' ),
				'permission_callback' => '__return_true',
			)
		);

		// 3. Search Testing Endpoint (Admin only)
		register_rest_route(
			SARKARI_SEWA_AI_REST_NAMESPACE,
			'/search',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_search_test' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'query' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// 4. Batch Reindex Endpoint (Admin only)
		register_rest_route(
			SARKARI_SEWA_AI_REST_NAMESPACE,
			'/reindex',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_reindex_batch' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// 5. Test Connection Endpoint (Admin only)
		register_rest_route(
			SARKARI_SEWA_AI_REST_NAMESPACE,
			'/test-connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_test_connection' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);
	}

	/**
	 * Permission callback for admin-only endpoints.
	 *
	 * @return bool
	 */
	public function admin_permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle status request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_status_request() {
		$stats = Sarkari_Sewa_AI_Database::get_index_stats();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'status'        => 'healthy',
					'version'       => SARKARI_SEWA_AI_VERSION,
					'site_url'      => home_url(),
					'total_indexed' => $stats['total_indexed'],
					'total_chunks'  => $stats['total_chunks'],
					'last_sync'     => $stats['last_sync'],
				),
			),
			200
		);
	}

	/**
	 * Handle admin search testing.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_search_test( $request ) {
		$query = sanitize_text_field( $request->get_param( 'query' ) );
		$output = Sarkari_Sewa_AI_Search_Engine::search( $query, array( 'threshold' => 0.4, 'limit' => 10 ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $output,
			),
			200
		);
	}

	/**
	 * Handle batch reindexing.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_reindex_batch( $request ) {
		$offset     = (int) $request->get_param( 'offset' );
		$batch_size = (int) $request->get_param( 'batch_size' );
		if ( $batch_size <= 0 ) {
			$settings   = get_option( 'sarkari_sewa_ai_settings', array() );
			$batch_size = ! empty( $settings['batch_size'] ) ? (int) $settings['batch_size'] : 25;
		}

		$indexer = new Sarkari_Sewa_AI_Content_Indexer();
		$result  = $indexer->process_batch( $offset, $batch_size );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Handle connection test.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_test_connection() {
		$ai_provider     = Sarkari_Sewa_AI_Provider_Factory::get_provider();
		$ai_test         = $ai_provider->test_connection();

		$vector_provider = Sarkari_Sewa_AI_Vector_Factory::get_provider();
		$vector_test     = $vector_provider->test_connection();

		global $wpdb;
		$db_test = array(
			'success' => (bool) $wpdb->check_connection(),
			'message' => $wpdb->check_connection() ? 'Local WordPress Database connected.' : 'Database error',
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'ai'       => $ai_test,
					'vector'   => $vector_test,
					'database' => $db_test,
				),
			),
			200
		);
	}
}
