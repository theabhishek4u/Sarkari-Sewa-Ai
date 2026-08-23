<?php
/**
 * Admin Content Indexing View.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats    = Sarkari_Sewa_AI_Database::get_index_stats();
$settings = get_option( 'sarkari_sewa_ai_settings', array() );
$batch_sz = ! empty( $settings['batch_size'] ) ? (int) $settings['batch_size'] : 25;

// Count total published posts across enabled post types
$post_types = ! empty( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array( 'post', 'page' );
$total_posts = 0;
foreach ( $post_types as $pt ) {
	$counts = wp_count_posts( $pt );
	$total_posts += isset( $counts->publish ) ? (int) $counts->publish : 0;
}
?>

<div class="wrap sarkari-ai-wrap">
	<div class="sarkari-ai-header">
		<div class="sarkari-ai-header-title">
			<span class="sarkari-ai-badge">INDEXER</span>
			<h1>Content Index & Synchronization</h1>
		</div>
		<p class="sarkari-ai-header-desc">
			Extract published jobs, results, and admit cards, break them into contextual chunks, generate vector embeddings, and synchronize with Supabase pgvector.
		</p>
	</div>

	<div class="sarkari-ai-metrics-grid">
		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-posts">
				<span class="dashicons dashicons-admin-post"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Total Published Posts</span>
				<span class="metric-value"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span>
			</div>
		</div>

		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-chunks">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Currently Indexed</span>
				<span class="metric-value"><?php echo esc_html( number_format_i18n( $stats['total_indexed'] ) ); ?></span>
			</div>
		</div>

		<div class="sarkari-ai-metric-card">
			<div class="metric-icon metric-icon-sync">
				<span class="dashicons dashicons-networking"></span>
			</div>
			<div class="metric-info">
				<span class="metric-label">Generated Chunks</span>
				<span class="metric-value"><?php echo esc_html( number_format_i18n( $stats['total_chunks'] ) ); ?></span>
			</div>
		</div>
	</div>

	<div class="sarkari-ai-panel">
		<div class="panel-header">
			<h2><span class="dashicons dashicons-update"></span> Bulk Batch Indexer</h2>
		</div>
		<div class="panel-body">
			<p>
				Processing posts in safe batches of <strong><?php echo esc_html( $batch_sz ); ?> posts</strong> per request. This prevents memory spikes or PHP execution timeouts on large catalogs.
			</p>

			<!-- Progress Bar -->
			<div class="sarkari-ai-progress-wrapper" style="display:none;" id="indexer-progress-box">
				<div class="progress-labels">
					<span id="indexer-progress-status">Preparing indexer...</span>
					<span id="indexer-progress-percent">0%</span>
				</div>
				<div class="progress-bar-track">
					<div class="progress-bar-fill" id="indexer-progress-fill" style="width: 0%;"></div>
				</div>
				<div class="progress-counts" id="indexer-progress-counts">
					0 / <?php echo esc_html( $total_posts ); ?> posts processed
				</div>
			</div>

			<div class="indexer-actions" style="margin-top:20px;">
				<button type="button" id="btn-start-index" class="button button-primary button-hero">
					<span class="dashicons dashicons-controls-play"></span> Start Bulk Indexing
				</button>
				<button type="button" id="btn-pause-index" class="button button-secondary" style="display:none;">
					<span class="dashicons dashicons-controls-pause"></span> Pause
				</button>
				<button type="button" id="btn-resume-index" class="button button-primary" style="display:none;">
					<span class="dashicons dashicons-controls-play"></span> Resume
				</button>
			</div>

			<!-- Live Log Console -->
			<div class="sarkari-ai-indexer-console" id="indexer-console" style="margin-top:25px;display:none;">
				<h3>Live Indexing Output:</h3>
				<div class="console-box" id="console-logs"></div>
			</div>
		</div>
	</div>
</div>
