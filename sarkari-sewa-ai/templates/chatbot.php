<?php
/**
 * Frontend Chatbot Floating Markup Template.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'sarkari_sewa_ai_settings', array() );
$position = isset( $settings['chatbot_position'] ) ? esc_attr( $settings['chatbot_position'] ) : 'bottom-right';
$title    = isset( $settings['chatbot_title'] ) && ! empty( $settings['chatbot_title'] ) ? esc_html( $settings['chatbot_title'] ) : 'Sarkari Sewa AI';
$subtitle = isset( $settings['chatbot_subtitle'] ) && ! empty( $settings['chatbot_subtitle'] ) ? esc_html( $settings['chatbot_subtitle'] ) : 'Official search assistant for SarkariSewa.com';
?>

<div id="sarkari-sewa-ai-widget" class="sarkari-ai-widget position-<?php echo $position; ?>" aria-label="Sarkari Sewa AI Chatbot">
	
	<!-- Floating Animated Trigger Button -->
	<button id="sarkari-ai-trigger" class="sarkari-ai-trigger-btn" aria-expanded="false" aria-controls="sarkari-ai-panel" aria-label="Open Sarkari Sewa AI Assistant">
		<div class="trigger-bot-pulse"></div>
		<div class="trigger-bot-avatar">
			<span class="trigger-icon-chat">
				<!-- Animated Robot SVG -->
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="11" width="18" height="10" rx="4"></rect>
					<circle cx="9" cy="16" r="1.5" fill="currentColor"></circle>
					<circle cx="15" cy="16" r="1.5" fill="currentColor"></circle>
					<path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"></path>
					<line x1="12" y1="3" x2="12" y2="7"></line>
				</svg>
			</span>
			<span class="trigger-icon-close" style="display:none;">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</span>
			<span class="trigger-status-dot"></span>
		</div>
		<span class="trigger-label">Sarkari Sewa AI</span>
	</button>

	<!-- Chat Drawer / Panel -->
	<div id="sarkari-ai-panel" class="sarkari-ai-panel" role="region" aria-label="Sarkari Sewa AI Chat Assistant" style="display:none;">
		
		<!-- Premium Header -->
		<div class="sarkari-ai-header">
			<div class="header-branding">
				<div class="header-avatar-wrap">
					<div class="header-avatar">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="11" width="18" height="10" rx="4"></rect>
							<circle cx="9" cy="16" r="1.5" fill="#fff"></circle>
							<circle cx="15" cy="16" r="1.5" fill="#fff"></circle>
							<path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"></path>
						</svg>
					</div>
					<span class="avatar-online-dot"></span>
				</div>
				<div class="header-titles">
					<div class="title-row">
						<h3 class="panel-title"><?php echo $title; ?></h3>
						<span class="verified-check-icon" title="Verified SarkariSewa.com Assistant">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="#10B981">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
							</svg>
						</span>
					</div>
					<span class="panel-subtitle">Official Search Assistant</span>
				</div>
			</div>
			<div class="header-actions">
				<button type="button" id="sarkari-ai-clear-chat" class="header-btn" title="Clear Conversation" aria-label="Clear chat">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
					</svg>
				</button>
				<button type="button" id="sarkari-ai-close-panel" class="header-btn header-btn-close" title="Close Chatbot" aria-label="Close chat window">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
		</div>

		<!-- Grounding Trust Bar -->
		<div class="sarkari-ai-grounding-bar">
			<span class="shield-icon">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
				</svg>
			</span>
			<span>Answers grounded strictly on <strong>SarkariSewa.com</strong> published data</span>
		</div>

		<!-- Messages Scrollable Conversation Area -->
		<div id="sarkari-ai-messages" class="sarkari-ai-messages" role="log" aria-live="polite">
			<!-- Dynamic message bubbles injected by chatbot.js -->
		</div>

		<!-- Suggested Quick Prompts -->
		<div class="suggestions-container">
			<div class="suggestions-header">Quick questions:</div>
			<div id="sarkari-ai-suggestions" class="sarkari-ai-suggestions" aria-label="Suggested questions">
				<!-- Injected via JavaScript from settings -->
			</div>
		</div>

		<!-- Input Box Footer -->
		<form id="sarkari-ai-form" class="sarkari-ai-input-bar">
			<textarea id="sarkari-ai-input" class="sarkari-ai-input" placeholder="Ask in Hindi, English, or Hinglish..." rows="1" maxlength="500" aria-label="Your search question"></textarea>
			<button type="submit" id="sarkari-ai-send-btn" class="sarkari-ai-send-btn" aria-label="Send message" disabled>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="22" y1="2" x2="11" y2="13"></line>
					<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
				</svg>
			</button>
		</form>

		<!-- Footer Notice -->
		<div class="sarkari-ai-footer-note">
			<span>Always verify official notifications before applying.</span>
		</div>

	</div>
</div>
