<?php
/**
 * Admin Dashboard View.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats    = Sarkari_Sewa_AI_Database::get_index_stats();
$settings = get_option( 'sarkari_sewa_ai_settings', array() );
$provider = ! empty( $settings['ai_provider'] ) ? ucfirst( $settings['ai_provider'] ) : 'OpenAI';
?>

<div class="wrap sarkari-ai-wrap">
	<div class="sarkari-ai-header">
		<div class="sarkari-ai-header-title">
			<span class="sarkari-ai-badge">PRO</span>
			<h1>Sarkari Sewa AI — Control Center</h1>
		</div>
		<p class="sarkari-ai-header-desc">
			Production source-grounded search assistant powering <a href="<?php echo esc_url( home_url() ); ?>" target="_blank"><?php echo esc_html( home_url() ); ?></a>.
		</p>
	</div>

	<!-- System Status Alerts / Quick Actions -->
	<div class="sarkari-ai-metrics-grid">
		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-posts">
				<span class="dashicons dashicons-admin-post"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Indexed Posts</span>
				<span class="metric-value"><?php echo esc_html( number_format_i18n( $stats['posts_indexed'] ) ); ?></span>
			</div>
		</div>

		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-pages">
				<span class="dashicons dashicons-admin-page"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Indexed Pages</span>
				<span class="metric-value"><?php echo esc_html( number_format_i18n( $stats['pages_indexed'] ) ); ?></span>
			</div>
		</div>

		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-chunks">
				<span class="dashicons dashicons-networking"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Vector Chunks</span>
				<span class="metric-value"><?php echo esc_html( number_format_i18n( $stats['total_chunks'] ) ); ?></span>
			</div>
		</div>

		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-sync">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Last Sync</span>
				<span class="metric-value metric-value-sm"><?php echo esc_html( $stats['last_sync'] ); ?></span>
			</div>
		</div>
	</div>

	<div class="sarkari-ai-dashboard-columns">
		<!-- Left: Quick Health Check & Wizard -->
		<div class="sarkari-ai-col-main">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2><span class="dashicons dashicons-heart"></span> Connection & System Health</h2>
					<button id="sarkari-btn-test-conn" class="button button-secondary">
						<span class="dashicons dashicons-update-alt"></span> Run Health Check
					</button>
				</div>
				<div class="panel-body">
					<div id="conn-test-results" class="sarkari-ai-health-list">
						<div class="health-item">
							<div class="health-label">
								<strong>WordPress Database</strong>
								<span>Local index and chunks tables</span>
							</div>
							<span class="health-status status-ok"><span class="dashicons dashicons-yes-alt"></span> Connected</span>
						</div>
						<div class="health-item">
							<div class="health-label">
								<strong>AI Provider (<?php echo esc_html( $provider ); ?>)</strong>
								<span>Model: <?php echo esc_html( ( $settings['ai_provider'] ?? 'openai' ) === 'gemini' ? ( $settings['gemini_model'] ?? 'gemini-1.5-flash' ) : ( $settings['openai_model'] ?? 'gpt-4o-mini' ) ); ?></span>
							</div>
							<span class="health-status" id="status-ai-provider"><span class="dashicons dashicons-minus"></span> Click Run Health Check</span>
						</div>
						<div class="health-item">
							<div class="health-label">
								<strong>Vector Database (Supabase)</strong>
								<span>pgvector table: <?php echo esc_html( $settings['supabase_table'] ?? 'sarkari_ai_documents' ); ?></span>
							</div>
							<span class="health-status" id="status-vector-provider"><span class="dashicons dashicons-minus"></span> Click Run Health Check</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Quick Setup Checklist -->
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2><span class="dashicons dashicons-clipboard"></span> Production Setup Guide</h2>
				</div>
				<div class="panel-body">
					<ul class="sarkari-ai-setup-steps">
						<li class="completed">
							<span class="step-num">1</span>
							<div class="step-content">
								<strong>Plugin Installed & Tables Created</strong>
								<p>Database schema is ready for index tracking and caching.</p>
							</div>
						</li>
						<li class="<?php echo ! empty( $settings['openai_api_key'] ) || ! empty( $settings['gemini_api_key'] ) ? 'completed' : 'pending'; ?>">
							<span class="step-num">2</span>
							<div class="step-content">
								<strong>Configure AI API Keys</strong>
								<p>Provide your OpenAI or Google Gemini API key in <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-settings' ) ); ?>">Settings</a>.</p>
							</div>
						</li>
						<li class="<?php echo ! empty( $settings['supabase_url'] ) ? 'completed' : 'pending'; ?>">
							<span class="step-num">3</span>
							<div class="step-content">
								<strong>Configure Supabase pgvector</strong>
								<p>Set Supabase Project URL & Service Key to enable fast semantic similarity search.</p>
							</div>
						</li>
						<li class="<?php echo $stats['total_indexed'] > 0 ? 'completed' : 'pending'; ?>">
							<span class="step-num">4</span>
							<div class="step-content">
								<strong>Build Content Search Index</strong>
								<p>Run initial batch indexer on <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-indexing' ) ); ?>">Content Index</a> page.</p>
							</div>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<!-- Right: Quick actions and Info -->
		<div class="sarkari-ai-col-side">
			<div class="sarkari-ai-panel">
				<div class="panel-header">
					<h2>Quick Navigation</h2>
				</div>
				<div class="panel-body quick-nav-body">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-indexing' ) ); ?>" class="sarkari-nav-btn">
						<span class="dashicons dashicons-database-add"></span>
						<div>
							<strong>Content Indexer</strong>
							<span>Sync all posts to vector index</span>
						</div>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-search-test' ) ); ?>" class="sarkari-nav-btn">
						<span class="dashicons dashicons-search"></span>
						<div>
							<strong>Search Test Sandbox</strong>
							<span>Simulate queries and debug scores</span>
						</div>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-settings' ) ); ?>" class="sarkari-nav-btn">
						<span class="dashicons dashicons-admin-settings"></span>
						<div>
							<strong>Plugin Settings</strong>
							<span>Manage models, UI colors, and weights</span>
						</div>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-logs' ) ); ?>" class="sarkari-nav-btn">
						<span class="dashicons dashicons-list-view"></span>
						<div>
							<strong>Event Logs</strong>
							<span>Monitor requests and errors</span>
						</div>
					</a>
				</div>
			</div>

			<div class="sarkari-ai-panel help-panel">
				<div class="panel-header">
					<h2>Non-Negotiable Rule</h2>
				</div>
				<div class="panel-body">
					<p class="rule-quote">
						"Sarkari Sewa AI answers <strong>ONLY</strong> from published WordPress posts. It never invents jobs, admit cards, or dates."
					</p>
				</div>
			</div>
		</div>
	</div>
</div>
