<?php
/**
 * Fired during plugin activation.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Activator {

	/**
	 * Activate plugin: check requirements, install DB tables, set defaults.
	 */
	public static function activate() {
		// Verify PHP Version
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( SARKARI_SEWA_AI_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Sarkari Sewa AI requires PHP 8.1 or higher. You are currently running PHP ' . PHP_VERSION, 'sarkari-sewa-ai' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Verify WordPress Version
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			deactivate_plugins( SARKARI_SEWA_AI_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Sarkari Sewa AI requires WordPress 6.0 or higher.', 'sarkari-sewa-ai' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Create Custom Tables
		require_once SARKARI_SEWA_AI_PLUGIN_DIR . 'includes/class-database.php';
		Sarkari_Sewa_AI_Database::create_tables();

		// Set Default Settings if not already set
		self::set_default_options();

		// Schedule periodic health check cron if needed
		if ( ! wp_next_scheduled( 'sarkari_sewa_ai_cron_sync' ) ) {
			wp_schedule_event( time(), 'daily', 'sarkari_sewa_ai_cron_sync' );
		}
	}

	/**
	 * Initialize default settings in wp_options.
	 */
	public static function set_default_options() {
		$defaults = array(
			// AI Provider Settings
			'ai_provider'              => 'openai', // 'openai' or 'gemini'
			'openai_api_key'           => '',
			'openai_model'             => 'gpt-4o-mini',
			'gemini_api_key'           => '',
			'gemini_model'             => 'gemini-1.5-flash',
			'temperature'              => 0.2,
			'max_tokens'               => 500,

			// Vector Search & Embeddings
			'vector_provider'          => 'supabase', // 'supabase'
			'supabase_url'             => '',
			'supabase_service_role_key'=> '',
			'supabase_table'           => 'sarkari_ai_documents',
			'embedding_model'          => 'text-embedding-3-small',

			// Search & Ranking Engine
			'enable_keyword_search'    => true,
			'enable_semantic_search'   => true,
			'similarity_threshold'     => 0.65,
			'max_search_results'       => 5,
			'keyword_weight'           => 0.35,
			'semantic_weight'          => 0.45,
			'title_match_weight'       => 0.15,
			'freshness_weight'         => 0.05,

			// Indexing
			'batch_size'               => 25,
			'enabled_post_types'       => array( 'post', 'page' ),
			'enabled_taxonomies'       => array( 'category', 'post_tag' ),
			'auto_indexing'            => true,

			// Chatbot UI & Appearance
			'enable_chatbot'           => true,
			'chatbot_title'            => 'Sarkari Sewa AI',
			'chatbot_subtitle'         => 'Official search assistant for SarkariSewa.com',
			'chatbot_greeting'         => "Namaste! 👋\n\nI can help you find jobs, results, admit cards & exam notices published on SarkariSewa.com.\n\nWhat can I help you find today?",
			'suggested_questions'      => array(
				'Railway ki latest vacancy?',
				'SSC ka latest result?',
				'10th pass government jobs?',
				'Latest admit cards?',
				'UP government vacancies?',
				'BPSC teacher notification?',
			),
			'chatbot_position'         => 'bottom-right',
			'primary_color'            => '#8B0000', // Maroon
			'secondary_color'          => '#0A192F', // Dark Navy
			'accent_color'             => '#D32F2F',

			// Security, Rate Limiting & Daily Quotas
			'daily_message_limit'      => 10,
			'daily_limit_message'      => "Aapki aaj ki message limit poori ho chuki hai. Naye sawal puchne ke liye kal (24 ghante baad) wapas aayein! 😊",
			'rate_limit_requests'      => 10,
			'rate_limit_window_secs'   => 60,
			'max_message_length'       => 500,
			'enable_anonymous_logging' => false,
			'cache_duration_minutes'   => 10,
			'debug_mode'               => false,
		);

		$existing = get_option( 'sarkari_sewa_ai_settings', array() );
		$merged   = wp_parse_args( $existing, $defaults );
		update_option( 'sarkari_sewa_ai_settings', $merged );
	}
}
