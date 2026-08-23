<?php
/**
 * Admin Search Testing View.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap sarkari-ai-wrap">
	<div class="sarkari-ai-header">
		<div class="sarkari-ai-header-title">
			<span class="sarkari-ai-badge">DEBUG TOOL</span>
			<h1>Search & Retrieval Testing Sandbox</h1>
		</div>
		<p class="sarkari-ai-header-desc">
			Test queries in English, Hindi, and Hinglish to inspect raw keyword matches, semantic vector similarity, intent categorization, and final fusion scores.
		</p>
	</div>

	<div class="sarkari-ai-panel">
		<div class="panel-header">
			<h2><span class="dashicons dashicons-search"></span> Query Simulator</h2>
		</div>
		<div class="panel-body">
			<div class="search-test-form">
				<div style="display:flex;gap:12px;max-width:700px;">
					<input type="text" id="search-test-query" class="regular-text" style="flex:1;padding:8px 12px;font-size:15px;" placeholder="e.g., Railway ki latest vacancy, SSC result, 10th pass job..." value="Railway vacancy">
					<button type="button" id="btn-run-search-test" class="button button-primary">
						<span class="dashicons dashicons-search"></span> Test Retrieval
					</button>
				</div>

				<div class="quick-sample-queries" style="margin-top:12px;">
					<span style="font-size:12px;color:#64748b;">Try sample questions:</span>
					<button type="button" class="btn-sample-q">Railway ki vacancy aayi hai?</button>
					<button type="button" class="btn-sample-q">SSC CGL 2026 result</button>
					<button type="button" class="btn-sample-q">10th pass government job</button>
					<button type="button" class="btn-sample-q">Latest admit card</button>
				</div>
			</div>

			<!-- Search Results Output -->
			<div id="search-test-results-container" style="margin-top:30px;display:none;">
				<div class="sarkari-ai-intent-box" id="search-intent-box"></div>

				<h3 style="margin-top:20px;">Ranked Retrieval Results:</h3>
				<table class="wp-list-table widefat fixed striped" id="table-search-results">
					<thead>
						<tr>
							<th style="width:70px;">Post ID</th>
							<th>Post Title & Permalink</th>
							<th style="width:130px;">Category</th>
							<th style="width:110px;">Semantic Score</th>
							<th style="width:110px;">Keyword Score</th>
							<th style="width:110px;">Final Score</th>
						</tr>
					</thead>
					<tbody id="search-results-tbody">
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
