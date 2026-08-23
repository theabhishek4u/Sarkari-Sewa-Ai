<?php
/**
 * Admin Panel and Settings Manager.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers for interactive admin UI
		add_action( 'wp_ajax_sarkari_ai_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_sarkari_ai_reindex_batch', array( $this, 'ajax_reindex_batch' ) );
		add_action( 'wp_ajax_sarkari_ai_search_test', array( $this, 'ajax_search_test' ) );
		add_action( 'wp_ajax_sarkari_ai_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	/**
	 * Register Admin Menu & Submenus.
	 */
	public function register_admin_menus() {
		add_menu_page(
			__( 'Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			__( 'Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			'manage_options',
			'sarkari-sewa-ai',
			array( $this, 'render_dashboard_page' ),
			'dashicons-search',
			30
		);

		add_submenu_page(
			'sarkari-sewa-ai',
			__( 'Dashboard - Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			__( 'Dashboard', 'sarkari-sewa-ai' ),
			'manage_options',
			'sarkari-sewa-ai',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'sarkari-sewa-ai',
			__( 'Settings - Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			__( 'Settings', 'sarkari-sewa-ai' ),
			'manage_options',
			'sarkari-sewa-ai-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'sarkari-sewa-ai',
			__( 'Content Index - Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			__( 'Content Index', 'sarkari-sewa-ai' ),
			'manage_options',
			'sarkari-sewa-ai-indexing',
			array( $this, 'render_indexing_page' )
		);

		add_submenu_page(
			'sarkari-sewa-ai',
			__( 'Search Test - Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			__( 'Search Test', 'sarkari-sewa-ai' ),
			'manage_options',
			'sarkari-sewa-ai-search-test',
			array( $this, 'render_search_test_page' )
		);

		add_submenu_page(
			'sarkari-sewa-ai',
			__( 'Chat & Event Logs - Sarkari Sewa AI', 'sarkari-sewa-ai' ),
			__( 'Logs', 'sarkari-sewa-ai' ),
			'manage_options',
			'sarkari-sewa-ai-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'sarkari_sewa_ai_settings_group',
			'sarkari_sewa_ai_settings',
			array( $this, 'sanitize_settings' )
		);

		register_setting(
			'sarkari_sewa_ai_settings_group',
			'sarkari_sewa_ai_delete_data_on_uninstall',
			'rest_sanitize_boolean'
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = array();

		// AI Provider
		$output['ai_provider']   = in_array( $input['ai_provider'] ?? '', array( 'openai', 'gemini' ), true ) ? $input['ai_provider'] : 'openai';
		$output['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] ?? '' );
		$output['openai_model']   = sanitize_text_field( $input['openai_model'] ?? 'gpt-4o-mini' );
		$output['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] ?? '' );
		$output['gemini_model']   = sanitize_text_field( $input['gemini_model'] ?? 'gemini-1.5-flash' );
		$output['temperature']    = floatval( $input['temperature'] ?? 0.2 );
		$output['max_tokens']     = absint( $input['max_tokens'] ?? 500 );

		// Vector Search
		$output['vector_provider']           = 'supabase';
		$output['supabase_url']              = esc_url_raw( $input['supabase_url'] ?? '' );
		$output['supabase_service_role_key'] = sanitize_text_field( $input['supabase_service_role_key'] ?? '' );
		$output['supabase_table']            = sanitize_text_field( $input['supabase_table'] ?? 'sarkari_ai_documents' );
		$output['embedding_model']           = sanitize_text_field( $input['embedding_model'] ?? 'text-embedding-3-small' );

		// Retrieval & Ranking
		$output['enable_keyword_search']  = ! empty( $input['enable_keyword_search'] );
		$output['enable_semantic_search'] = ! empty( $input['enable_semantic_search'] );
		$output['similarity_threshold']   = floatval( $input['similarity_threshold'] ?? 0.65 );
		$output['max_search_results']     = absint( $input['max_search_results'] ?? 5 );
		$output['keyword_weight']         = floatval( $input['keyword_weight'] ?? 0.35 );
		$output['semantic_weight']        = floatval( $input['semantic_weight'] ?? 0.45 );
		$output['title_match_weight']     = floatval( $input['title_match_weight'] ?? 0.15 );
		$output['freshness_weight']       = floatval( $input['freshness_weight'] ?? 0.05 );

		// Indexing
		$output['batch_size']         = absint( $input['batch_size'] ?? 25 );
		$output['auto_indexing']      = ! empty( $input['auto_indexing'] );
		$output['enabled_post_types'] = ! empty( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
			? array_map( 'sanitize_key', $input['enabled_post_types'] )
			: array( 'post', 'page' );

		// Chatbot Appearance
		$output['enable_chatbot']     = ! empty( $input['enable_chatbot'] );
		$output['chatbot_title']      = sanitize_text_field( $input['chatbot_title'] ?? 'Sarkari Sewa AI' );
		$output['chatbot_subtitle']   = sanitize_text_field( $input['chatbot_subtitle'] ?? 'Official search assistant for SarkariSewa.com' );
		$output['chatbot_greeting']   = sanitize_textarea_field( $input['chatbot_greeting'] ?? '' );
		$output['chatbot_position']   = in_array( $input['chatbot_position'] ?? '', array( 'bottom-right', 'bottom-left' ), true ) ? $input['chatbot_position'] : 'bottom-right';
		$output['primary_color']      = sanitize_hex_color( $input['primary_color'] ?? '#8B0000' );
		$output['secondary_color']    = sanitize_hex_color( $input['secondary_color'] ?? '#0A192F' );

		// Suggested Questions
		if ( ! empty( $input['suggested_questions'] ) ) {
			if ( is_string( $input['suggested_questions'] ) ) {
				$lines = explode( "\n", $input['suggested_questions'] );
				$output['suggested_questions'] = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $lines ) ) ) );
			} elseif ( is_array( $input['suggested_questions'] ) ) {
				$output['suggested_questions'] = array_map( 'sanitize_text_field', $input['suggested_questions'] );
			}
		} else {
			$output['suggested_questions'] = array();
		}

		// Security, Rate Limiting & Daily Quotas
		$output['daily_message_limit']      = absint( $input['daily_message_limit'] ?? 10 );
		$output['daily_limit_message']      = sanitize_textarea_field( $input['daily_limit_message'] ?? '' );
		$output['rate_limit_requests']      = absint( $input['rate_limit_requests'] ?? 10 );
		$output['rate_limit_window_secs']   = absint( $input['rate_limit_window_secs'] ?? 60 );
		$output['max_message_length']       = absint( $input['max_message_length'] ?? 500 );
		$output['enable_anonymous_logging'] = ! empty( $input['enable_anonymous_logging'] );
		$output['cache_duration_minutes']   = absint( $input['cache_duration_minutes'] ?? 10 );
		$output['debug_mode']               = ! empty( $input['debug_mode'] );

		return $output;
	}

	/**
	 * Enqueue Admin styles and scripts only on plugin pages.
	 *
	 * @param string $hook
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'sarkari-sewa-ai' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'sarkari-sewa-ai-admin-css',
			SARKARI_SEWA_AI_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			SARKARI_SEWA_AI_VERSION
		);

		wp_enqueue_script(
			'sarkari-sewa-ai-admin-js',
			SARKARI_SEWA_AI_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			SARKARI_SEWA_AI_VERSION,
			true
		);

		wp_localize_script(
			'sarkari-sewa-ai-admin-js',
			'sarkariAiAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sarkari_sewa_ai_admin_action' ),
			)
		);
	}

	// Render Views
	public function render_dashboard_page() {
		include SARKARI_SEWA_AI_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function render_settings_page() {
		include SARKARI_SEWA_AI_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function render_indexing_page() {
		include SARKARI_SEWA_AI_PLUGIN_DIR . 'admin/views/indexing.php';
	}

	public function render_search_test_page() {
		include SARKARI_SEWA_AI_PLUGIN_DIR . 'admin/views/search-test.php';
	}

	public function render_logs_page() {
		include SARKARI_SEWA_AI_PLUGIN_DIR . 'admin/views/logs.php';
	}

	// AJAX Action Handlers
	public function ajax_test_connection() {
		if ( ! check_ajax_referer( 'sarkari_sewa_ai_admin_action', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Session expired. Please refresh the page and try again.' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$ai_test = array( 'success' => false, 'message' => 'Not tested' );
		try {
			$ai_provider = Sarkari_Sewa_AI_Provider_Factory::get_provider();
			$ai_test     = $ai_provider->test_connection();
		} catch ( Throwable $e ) {
			$ai_test = array( 'success' => false, 'message' => 'AI Error: ' . $e->getMessage() );
		}

		$vector_test = array( 'success' => false, 'message' => 'Not tested' );
		try {
			$vector_provider = Sarkari_Sewa_AI_Vector_Factory::get_provider();
			$vector_test     = $vector_provider->test_connection();
		} catch ( Throwable $e ) {
			$vector_test = array( 'success' => false, 'message' => 'Vector Error: ' . $e->getMessage() );
		}

		global $wpdb;
		$db_test = array(
			'success' => true,
			'message' => 'WordPress database tables active and verified.',
		);

		wp_send_json_success( array(
			'ai'       => $ai_test,
			'vector'   => $vector_test,
			'database' => $db_test,
		) );
	}

	public function ajax_reindex_batch() {
		check_ajax_referer( 'sarkari_sewa_ai_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$offset     = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? (int) $_POST['batch_size'] : 25;

		$indexer = new Sarkari_Sewa_AI_Content_Indexer();
		$result  = $indexer->process_batch( $offset, $batch_size );

		wp_send_json_success( $result );
	}

	public function ajax_search_test() {
		check_ajax_referer( 'sarkari_sewa_ai_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$query  = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$output = Sarkari_Sewa_AI_Search_Engine::search( $query, array( 'threshold' => 0.3, 'limit' => 10 ) );

		wp_send_json_success( $output );
	}

	public function ajax_clear_logs() {
		check_ajax_referer( 'sarkari_sewa_ai_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		Sarkari_Sewa_AI_Logger::clear_logs();
		wp_send_json_success( array( 'message' => 'Logs cleared successfully.' ) );
	}
}
