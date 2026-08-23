<?php
/**
 * Main Plugin Coordinator class.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Plugin {

	/**
	 * Run the plugin logic and register hooks.
	 */
	public function run() {
		// Initialize local database handler
		$database = new Sarkari_Sewa_AI_Database();

		// Initialize Logger
		$logger = new Sarkari_Sewa_AI_Logger();

		// Initialize Rate Limiter & Security
		$security     = new Sarkari_Sewa_AI_Security();
		$rate_limiter = new Sarkari_Sewa_AI_Rate_Limiter();

		// Initialize Indexer (Registers post lifecycle hooks)
		$indexer = new Sarkari_Sewa_AI_Content_Indexer();
		$indexer->init();

		// Initialize REST API
		$rest_api = new Sarkari_Sewa_AI_Rest_API();
		$rest_api->init();

		// Initialize Admin if in admin panel
		if ( is_admin() ) {
			$admin = new Sarkari_Sewa_AI_Admin();
			$admin->init();
		}

		// Initialize Public frontend assets & template
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_chatbot_template' ) );
	}

	/**
	 * Enqueue public assets only if chatbot is enabled.
	 */
	public function enqueue_frontend_assets() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$enabled  = isset( $settings['enable_chatbot'] ) ? (bool) $settings['enable_chatbot'] : true;

		if ( ! $enabled ) {
			return;
		}

		wp_enqueue_style(
			'sarkari-sewa-ai-chatbot-css',
			SARKARI_SEWA_AI_PLUGIN_URL . 'public/css/chatbot.css',
			array(),
			SARKARI_SEWA_AI_VERSION
		);

		wp_enqueue_script(
			'sarkari-sewa-ai-chatbot-js',
			SARKARI_SEWA_AI_PLUGIN_URL . 'public/js/chatbot.js',
			array(),
			SARKARI_SEWA_AI_VERSION,
			true
		);

		// Localize frontend script with configuration (strictly no secret API keys)
		wp_localize_script(
			'sarkari-sewa-ai-chatbot-js',
			'sarkariSewaAiConfig',
			array(
				'restUrl'            => esc_url_raw( rest_url( SARKARI_SEWA_AI_REST_NAMESPACE . '/chat' ) ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
				'greeting'           => isset( $settings['chatbot_greeting'] ) && ! empty( $settings['chatbot_greeting'] )
					? $settings['chatbot_greeting']
					: "Namaste! 👋\n\nI can help you find jobs, results, admit cards & exams published on SarkariSewa.com.",
				'suggestedQuestions' => isset( $settings['suggested_questions'] ) && is_array( $settings['suggested_questions'] )
					? $settings['suggested_questions']
					: array(
						'Railway ki latest vacancy?',
						'SSC ka latest result?',
						'10th pass government vacancy?',
						'Latest admit card?',
						'UP government jobs?',
						'BPSC teacher vacancy?',
					),
				'position'           => isset( $settings['chatbot_position'] ) ? $settings['chatbot_position'] : 'bottom-right',
				'primaryColor'       => isset( $settings['primary_color'] ) ? $settings['primary_color'] : '#8B0000',
				'secondaryColor'     => isset( $settings['secondary_color'] ) ? $settings['secondary_color'] : '#0A192F',
				'maxMessageLength'   => isset( $settings['max_message_length'] ) ? (int) $settings['max_message_length'] : 500,
				'dailyLimit'         => isset( $settings['daily_message_limit'] ) ? (int) $settings['daily_message_limit'] : 10,
				'remainingToday'     => Sarkari_Sewa_AI_Rate_Limiter::get_remaining_daily_messages(),
				'dailyLimitMessage'  => ! empty( $settings['daily_limit_message'] )
					? $settings['daily_limit_message']
					: "Aapki aaj ki message limit poori ho chuki hai. Naye sawal puchne ke liye kal (24 ghante baad) wapas aayein! 😊",
			)
		);
	}

	/**
	 * Render floating chatbot HTML markup into wp_footer.
	 */
	public function render_chatbot_template() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$enabled  = isset( $settings['enable_chatbot'] ) ? (bool) $settings['enable_chatbot'] : true;

		if ( ! $enabled ) {
			return;
		}

		$template_path = SARKARI_SEWA_AI_PLUGIN_DIR . 'templates/chatbot.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}
