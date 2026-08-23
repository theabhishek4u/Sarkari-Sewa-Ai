<?php
/**
 * Admin Settings View.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'sarkari_sewa_ai_settings', array() );
$delete_on_uninstall = get_option( 'sarkari_sewa_ai_delete_data_on_uninstall', false );

$suggested_text = ! empty( $settings['suggested_questions'] ) && is_array( $settings['suggested_questions'] )
	? implode( "\n", $settings['suggested_questions'] )
	: '';
?>

<div class="wrap sarkari-ai-wrap">
	<div class="sarkari-ai-header">
		<div class="sarkari-ai-header-title">
			<span class="sarkari-ai-badge">CONFIGURATION</span>
			<h1>Plugin Settings</h1>
		</div>
		<p class="sarkari-ai-header-desc">
			Configure AI providers, Supabase pgvector database credentials, hybrid search weights, and frontend chatbot appearance.
		</p>
	</div>

	<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php esc_html_e( 'Settings saved successfully.', 'sarkari-sewa-ai' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="sarkari-ai-form">
		<?php settings_fields( 'sarkari_sewa_ai_settings_group' ); ?>

		<!-- Tab Navigation -->
		<div class="sarkari-ai-tabs-nav">
			<button type="button" class="tab-btn active" data-tab="tab-ai"><span class="dashicons dashicons-superhero"></span> AI Provider</button>
			<button type="button" class="tab-btn" data-tab="tab-vector"><span class="dashicons dashicons-database"></span> Vector DB (Supabase)</button>
			<button type="button" class="tab-btn" data-tab="tab-search"><span class="dashicons dashicons-search"></span> Search & Weights</button>
			<button type="button" class="tab-btn" data-tab="tab-indexing"><span class="dashicons dashicons-update"></span> Content Indexing</button>
			<button type="button" class="tab-btn" data-tab="tab-ui"><span class="dashicons dashicons-format-chat"></span> Chatbot Appearance</button>
			<button type="button" class="tab-btn" data-tab="tab-security"><span class="dashicons dashicons-shield"></span> Security & Privacy</button>
		</div>

		<!-- Tab 1: AI Provider -->
		<div class="sarkari-ai-tab-pane active" id="tab-ai">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>AI Model Configuration</h2>
				</div>
				<div class="panel-body">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="ai_provider">Primary AI Provider</label></th>
							<td>
								<select name="sarkari_sewa_ai_settings[ai_provider]" id="ai_provider" class="regular-text">
									<option value="openai" <?php selected( $settings['ai_provider'] ?? 'openai', 'openai' ); ?>>OpenAI (GPT-4o-mini / text-embedding-3-small)</option>
									<option value="gemini" <?php selected( $settings['ai_provider'] ?? 'openai', 'gemini' ); ?>>Google Gemini (Gemini 1.5 Flash / text-embedding-004)</option>
								</select>
								<p class="description">Select the LLM provider used to generate responses from retrieved Sarkari Sewa content.</p>
							</td>
						</tr>

						<!-- OpenAI Settings -->
						<tr class="provider-field provider-openai">
							<th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
							<td>
								<input type="password" name="sarkari_sewa_ai_settings[openai_api_key]" id="openai_api_key" value="<?php echo esc_attr( $settings['openai_api_key'] ?? '' ); ?>" class="regular-text" placeholder="sk-proj-..." autocomplete="off">
								<p class="description">Stored securely on server. Never exposed to browser or visitors.</p>
							</td>
						</tr>
						<tr class="provider-field provider-openai">
							<th scope="row"><label for="openai_model">OpenAI Chat Model</label></th>
							<td>
								<input type="text" name="sarkari_sewa_ai_settings[openai_model]" id="openai_model" value="<?php echo esc_attr( $settings['openai_model'] ?? 'gpt-4o-mini' ); ?>" class="regular-text">
								<p class="description">Recommended: <code>gpt-4o-mini</code> for fast, cost-efficient answers.</p>
							</td>
						</tr>

						<!-- Gemini Settings -->
						<tr class="provider-field provider-gemini" style="display:none;">
							<th scope="row"><label for="gemini_api_key">Google Gemini API Key</label></th>
							<td>
								<input type="password" name="sarkari_sewa_ai_settings[gemini_api_key]" id="gemini_api_key" value="<?php echo esc_attr( $settings['gemini_api_key'] ?? '' ); ?>" class="regular-text" placeholder="AIzaSy..." autocomplete="off">
								<p class="description">Gemini API key from Google AI Studio.</p>
							</td>
						</tr>
						<tr class="provider-field provider-gemini" style="display:none;">
							<th scope="row"><label for="gemini_model">Gemini Model</label></th>
							<td>
								<input type="text" name="sarkari_sewa_ai_settings[gemini_model]" id="gemini_model" value="<?php echo esc_attr( $settings['gemini_model'] ?? 'gemini-1.5-flash' ); ?>" class="regular-text">
								<p class="description">Recommended: <code>gemini-1.5-flash</code>.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="temperature">Model Temperature</label></th>
							<td>
								<input type="number" step="0.05" min="0.0" max="1.0" name="sarkari_sewa_ai_settings[temperature]" id="temperature" value="<?php echo esc_attr( $settings['temperature'] ?? 0.2 ); ?>" class="small-text">
								<p class="description">Keep low (0.1 - 0.3) for factual, hallucination-free answers.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="max_tokens">Max Output Tokens</label></th>
							<td>
								<input type="number" step="50" min="100" max="2000" name="sarkari_sewa_ai_settings[max_tokens]" id="max_tokens" value="<?php echo esc_attr( $settings['max_tokens'] ?? 500 ); ?>" class="small-text">
								<p class="description">Maximum token length for generated responses.</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<!-- Tab 2: Vector DB (Supabase) -->
		<div class="sarkari-ai-tab-pane" id="tab-vector">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>Supabase PostgreSQL + pgvector Settings</h2>
				</div>
				<div class="panel-body">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="supabase_url">Supabase Project URL</label></th>
							<td>
								<input type="url" name="sarkari_sewa_ai_settings[supabase_url]" id="supabase_url" value="<?php echo esc_attr( $settings['supabase_url'] ?? '' ); ?>" class="regular-text" placeholder="https://xyzproject.supabase.co">
								<p class="description">Your Supabase project URL.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="supabase_service_role_key">Supabase Service Role Key / Secret</label></th>
							<td>
								<input type="password" name="sarkari_sewa_ai_settings[supabase_service_role_key]" id="supabase_service_role_key" value="<?php echo esc_attr( $settings['supabase_service_role_key'] ?? '' ); ?>" class="regular-text" autocomplete="off">
								<p class="description">Required for vector upserting and RPC similarity search.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="supabase_table">Documents Table Name</label></th>
							<td>
								<input type="text" name="sarkari_sewa_ai_settings[supabase_table]" id="supabase_table" value="<?php echo esc_attr( $settings['supabase_table'] ?? 'sarkari_ai_documents' ); ?>" class="regular-text">
								<p class="description">Default: <code>sarkari_ai_documents</code>.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="embedding_model">Embedding Model Name</label></th>
							<td>
								<input type="text" name="sarkari_sewa_ai_settings[embedding_model]" id="embedding_model" value="<?php echo esc_attr( $settings['embedding_model'] ?? 'text-embedding-3-small' ); ?>" class="regular-text">
								<p class="description">OpenAI: <code>text-embedding-3-small</code> (1536 dims) | Gemini: <code>text-embedding-004</code> (768 dims).</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<!-- Tab 3: Search & Weights -->
		<div class="sarkari-ai-tab-pane" id="tab-search">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>Hybrid Retrieval & Ranking Configuration</h2>
				</div>
				<div class="panel-body">
					<table class="form-table">
						<tr>
							<th scope="row">Retrieval Layers</th>
							<td>
								<label>
									<input type="checkbox" name="sarkari_sewa_ai_settings[enable_keyword_search]" value="1" <?php checked( ! empty( $settings['enable_keyword_search'] ) ); ?>>
									Enable Keyword Full-Text Search
								</label><br>
								<label>
									<input type="checkbox" name="sarkari_sewa_ai_settings[enable_semantic_search]" value="1" <?php checked( ! empty( $settings['enable_semantic_search'] ) ); ?>>
									Enable Semantic Vector Search
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="similarity_threshold">Similarity Threshold</label></th>
							<td>
								<input type="number" step="0.05" min="0.1" max="1.0" name="sarkari_sewa_ai_settings[similarity_threshold]" id="similarity_threshold" value="<?php echo esc_attr( $settings['similarity_threshold'] ?? 0.65 ); ?>" class="small-text">
								<p class="description">Minimum score needed to consider a post relevant. (Recommended: 0.60 - 0.70).</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_search_results">Max Retrieved Results</label></th>
							<td>
								<input type="number" step="1" min="1" max="10" name="sarkari_sewa_ai_settings[max_search_results]" id="max_search_results" value="<?php echo esc_attr( $settings['max_search_results'] ?? 5 ); ?>" class="small-text">
								<p class="description">Number of top source post cards returned to user.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Hybrid Ranking Weights</th>
							<td>
								<div class="weights-grid">
									<div>
										<label>Semantic Weight:</label>
										<input type="number" step="0.05" min="0" max="1" name="sarkari_sewa_ai_settings[semantic_weight]" value="<?php echo esc_attr( $settings['semantic_weight'] ?? 0.45 ); ?>" class="small-text">
									</div>
									<div>
										<label>Keyword Weight:</label>
										<input type="number" step="0.05" min="0" max="1" name="sarkari_sewa_ai_settings[keyword_weight]" value="<?php echo esc_attr( $settings['keyword_weight'] ?? 0.35 ); ?>" class="small-text">
									</div>
									<div>
										<label>Title Match Weight:</label>
										<input type="number" step="0.05" min="0" max="1" name="sarkari_sewa_ai_settings[title_match_weight]" value="<?php echo esc_attr( $settings['title_match_weight'] ?? 0.15 ); ?>" class="small-text">
									</div>
									<div>
										<label>Freshness Weight:</label>
										<input type="number" step="0.05" min="0" max="1" name="sarkari_sewa_ai_settings[freshness_weight]" value="<?php echo esc_attr( $settings['freshness_weight'] ?? 0.05 ); ?>" class="small-text">
									</div>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<!-- Tab 4: Indexing -->
		<div class="sarkari-ai-tab-pane" id="tab-indexing">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>Content Indexing Options</h2>
				</div>
				<div class="panel-body">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="batch_size">Indexing Batch Size</label></th>
							<td>
								<input type="number" step="5" min="5" max="100" name="sarkari_sewa_ai_settings[batch_size]" id="batch_size" value="<?php echo esc_attr( $settings['batch_size'] ?? 25 ); ?>" class="small-text">
								<p class="description">Number of posts processed per AJAX request during bulk indexing.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Automatic Lifecycle Indexing</th>
							<td>
								<label>
									<input type="checkbox" name="sarkari_sewa_ai_settings[auto_indexing]" value="1" <?php checked( ! empty( $settings['auto_indexing'] ) ); ?>>
									Automatically index, update, and remove posts on publish / edit / trash.
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Indexed Post Types</th>
							<td>
								<?php
								$public_types = get_post_types( array( 'public' => true ), 'objects' );
								$enabled_types = $settings['enabled_post_types'] ?? array( 'post', 'page' );
								foreach ( $public_types as $pt ) :
									if ( in_array( $pt->name, array( 'attachment' ), true ) ) continue;
								?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="sarkari_sewa_ai_settings[enabled_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $enabled_types, true ) ); ?>>
										<?php echo esc_html( $pt->label . ' (' . $pt->name . ')' ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<!-- Tab 5: Chatbot UI -->
		<div class="sarkari-ai-tab-pane" id="tab-ui">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>Chatbot Appearance & Branding</h2>
				</div>
				<div class="panel-body">
					<table class="form-table">
						<tr>
							<th scope="row">Chatbot Widget</th>
							<td>
								<label>
									<input type="checkbox" name="sarkari_sewa_ai_settings[enable_chatbot]" value="1" <?php checked( ! empty( $settings['enable_chatbot'] ) ); ?>>
									Enable floating chatbot on frontend
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="chatbot_title">Chatbot Title</label></th>
							<td>
								<input type="text" name="sarkari_sewa_ai_settings[chatbot_title]" id="chatbot_title" value="<?php echo esc_attr( $settings['chatbot_title'] ?? 'Sarkari Sewa AI' ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="chatbot_greeting">Welcome Greeting</label></th>
							<td>
								<textarea name="sarkari_sewa_ai_settings[chatbot_greeting]" id="chatbot_greeting" rows="4" class="large-text"><?php echo esc_textarea( $settings['chatbot_greeting'] ?? '' ); ?></textarea>
								<p class="description">Displayed in the chat panel when opened for the first time.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="suggested_questions">Suggested Questions (One per line)</label></th>
							<td>
								<textarea name="sarkari_sewa_ai_settings[suggested_questions]" id="suggested_questions" rows="6" class="large-text"><?php echo esc_textarea( $suggested_text ); ?></textarea>
								<p class="description">Quick prompt chips displayed above the input box.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="chatbot_position">Position</label></th>
							<td>
								<select name="sarkari_sewa_ai_settings[chatbot_position]" id="chatbot_position">
									<option value="bottom-right" <?php selected( $settings['chatbot_position'] ?? 'bottom-right', 'bottom-right' ); ?>>Bottom Right</option>
									<option value="bottom-left" <?php selected( $settings['chatbot_position'] ?? 'bottom-right', 'bottom-left' ); ?>>Bottom Left</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">Brand Colors</th>
							<td>
								<div style="display:flex;gap:20px;align-items:center;">
									<div>
										<label>Primary (Maroon):</label><br>
										<input type="color" name="sarkari_sewa_ai_settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ?? '#8B0000' ); ?>">
									</div>
									<div>
										<label>Secondary (Dark Navy):</label><br>
										<input type="color" name="sarkari_sewa_ai_settings[secondary_color]" value="<?php echo esc_attr( $settings['secondary_color'] ?? '#0A192F' ); ?>">
									</div>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<!-- Tab 6: Security & Privacy -->
		<div class="sarkari-ai-tab-pane" id="tab-security">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>Security, Rate Limiting & Privacy</h2>
				</div>
				<div class="panel-body">
					<table class="form-table">
						<tr style="background:#FEF2F2;">
							<th scope="row"><label for="daily_message_limit"><strong>Daily Message Limit per User (24 Hours)</strong></label></th>
							<td>
								<input type="number" min="0" max="500" name="sarkari_sewa_ai_settings[daily_message_limit]" id="daily_message_limit" value="<?php echo esc_attr( $settings['daily_message_limit'] ?? 10 ); ?>" class="small-text" style="font-weight:700;font-size:15px;"> messages per IP per 24 hours.
								<p class="description"><strong>Cost Protection:</strong> Limits how many questions a single visitor can ask in 24 hours (e.g. 10, 15, 20). Set to <code>0</code> for unlimited.</p>
							</td>
						</tr>
						<tr style="background:#FEF2F2;">
							<th scope="row"><label for="daily_limit_message">Daily Limit Reached Message</label></th>
							<td>
								<textarea name="sarkari_sewa_ai_settings[daily_limit_message]" id="daily_limit_message" rows="2" class="large-text"><?php echo esc_textarea( $settings['daily_limit_message'] ?? 'Aapki aaj ki message limit poori ho chuki hai. Naye sawal puchne ke liye kal (24 ghante baad) wapas aayein! 😊' ); ?></textarea>
								<p class="description">Message shown to user when they exceed their daily allowed question quota.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rate_limit_requests">Burst Rate Limit Requests</label></th>
							<td>
								<input type="number" min="0" max="60" name="sarkari_sewa_ai_settings[rate_limit_requests]" id="rate_limit_requests" value="<?php echo esc_attr( $settings['rate_limit_requests'] ?? 10 ); ?>" class="small-text">
								requests per IP per
								<input type="number" min="10" max="300" name="sarkari_sewa_ai_settings[rate_limit_window_secs]" id="rate_limit_window_secs" value="<?php echo esc_attr( $settings['rate_limit_window_secs'] ?? 60 ); ?>" class="small-text">
								seconds. (Set 0 to disable).
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_message_length">Max Message Character Length</label></th>
							<td>
								<input type="number" min="50" max="2000" name="sarkari_sewa_ai_settings[max_message_length]" id="max_message_length" value="<?php echo esc_attr( $settings['max_message_length'] ?? 500 ); ?>" class="small-text"> characters.
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cache_duration_minutes">Response Cache TTL</label></th>
							<td>
								<input type="number" min="0" max="1440" name="sarkari_sewa_ai_settings[cache_duration_minutes]" id="cache_duration_minutes" value="<?php echo esc_attr( $settings['cache_duration_minutes'] ?? 10 ); ?>" class="small-text"> minutes.
							</td>
						</tr>
						<tr>
							<th scope="row">Anonymous Chat Logging</th>
							<td>
								<label>
									<input type="checkbox" name="sarkari_sewa_ai_settings[enable_anonymous_logging]" value="1" <?php checked( ! empty( $settings['enable_anonymous_logging'] ) ); ?>>
									Log anonymous user queries to database table for retrieval analysis.
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Debug Mode</th>
							<td>
								<label>
									<input type="checkbox" name="sarkari_sewa_ai_settings[debug_mode]" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?>>
									Enable detailed debug logs.
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Uninstall Behavior</th>
							<td>
								<label style="color:#d63638;">
									<input type="checkbox" name="sarkari_sewa_ai_delete_data_on_uninstall" value="1" <?php checked( ! empty( $delete_on_uninstall ) ); ?>>
									Delete all plugin database tables and options upon plugin uninstall.
								</label>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div class="sarkari-ai-submit-bar">
			<?php submit_button( 'Save All Settings', 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
