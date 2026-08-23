/**
 * Sarkari Sewa AI Admin JavaScript.
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Settings Tab Switching
		$('.sarkari-ai-tabs-nav .tab-btn').on('click', function(e) {
			e.preventDefault();
			var targetTab = $(this).data('tab');

			$('.sarkari-ai-tabs-nav .tab-btn').removeClass('active');
			$(this).addClass('active');

			$('.sarkari-ai-tab-pane').removeClass('active');
			$('#' + targetTab).addClass('active');
		});

		// 2. AI Provider Toggle in Settings
		$('#ai_provider').on('change', function() {
			var selected = $(this).val();
			if (selected === 'gemini') {
				$('.provider-openai').hide();
				$('.provider-gemini').show();
			} else {
				$('.provider-gemini').hide();
				$('.provider-openai').show();
			}
		}).trigger('change');

		// 3. Health Check / Test Connection Button
		$('#sarkari-btn-test-conn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var originalText = $btn.html();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt spin"></span> Testing...');
			$('#status-ai-provider, #status-vector-provider').html('<span class="dashicons dashicons-update-alt spin"></span> Testing...');

			$.ajax({
				url: sarkariAiAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sarkari_ai_test_connection',
					nonce: sarkariAiAdmin.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).html(originalText);
					if (response.success && response.data) {
						var ai = response.data.ai;
						var vec = response.data.vector;

						// AI Status
						if (ai && ai.success) {
							$('#status-ai-provider').attr('class', 'health-status status-ok').html('<span class="dashicons dashicons-yes-alt"></span> ' + ai.message);
						} else {
							var aiMsg = (ai && ai.message) ? ai.message : 'API Key missing or invalid';
							$('#status-ai-provider').attr('class', 'health-status status-error').html('<span class="dashicons dashicons-dismiss"></span> ' + aiMsg);
						}

						// Vector Status
						if (vec && vec.success) {
							$('#status-vector-provider').attr('class', 'health-status status-ok').html('<span class="dashicons dashicons-yes-alt"></span> ' + vec.message);
						} else {
							var vecMsg = (vec && vec.message) ? vec.message : 'Supabase URL/Key missing';
							$('#status-vector-provider').attr('class', 'health-status status-error').html('<span class="dashicons dashicons-dismiss"></span> ' + vecMsg);
						}
					} else {
						var msg = (response.data && response.data.message) ? response.data.message : 'Please check Settings.';
						$('#status-ai-provider, #status-vector-provider').attr('class', 'health-status status-error').text(msg);
					}
				},
				error: function(xhr, status, error) {
					$btn.prop('disabled', false).html(originalText);
					var errMsg = 'Connection failed. Please configure keys in Settings.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errMsg = xhr.responseJSON.data.message;
					}
					$('#status-ai-provider, #status-vector-provider').attr('class', 'health-status status-error').text(errMsg);
				}
			});
		});

		// 4. Batch Indexer Runner
		var isIndexingPaused = false;
		var currentIndexOffset = 0;

		$('#btn-start-index').on('click', function(e) {
			e.preventDefault();
			if (!confirm('Rebuilding the index will extract, chunk, and sync all published posts. Proceed?')) {
				return;
			}

			isIndexingPaused = false;
			currentIndexOffset = 0;
			$('#indexer-progress-box').slideDown();
			$('#indexer-console').slideDown();
			$('#console-logs').empty();

			$(this).hide();
			$('#btn-pause-index').show();

			appendLog('Starting bulk indexing...');
			runIndexBatch(currentIndexOffset);
		});

		$('#btn-pause-index').on('click', function() {
			isIndexingPaused = true;
			$(this).hide();
			$('#btn-resume-index').show();
			appendLog('Indexing paused by user.');
		});

		$('#btn-resume-index').on('click', function() {
			isIndexingPaused = false;
			$(this).hide();
			$('#btn-pause-index').show();
			appendLog('Resuming indexing...');
			runIndexBatch(currentIndexOffset);
		});

		function runIndexBatch(offset) {
			if (isIndexingPaused) {
				return;
			}

			$('#indexer-progress-status').text('Indexing batch starting at offset ' + offset + '...');

			$.ajax({
				url: sarkariAiAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sarkari_ai_reindex_batch',
					offset: offset,
					nonce: sarkariAiAdmin.nonce
				},
				success: function(response) {
					if (!response.success || !response.data) {
						appendLog('Batch error: ' + (response.data ? response.data.error : 'Unknown error'));
						$('#btn-pause-index').hide();
						$('#btn-start-index').show();
						return;
					}

					var data = response.data;
					var percent = data.percentage || 0;
					currentIndexOffset = data.next_offset;

					$('#indexer-progress-fill').css('width', percent + '%');
					$('#indexer-progress-percent').text(percent + '%');
					$('#indexer-progress-counts').text(currentIndexOffset + ' / ' + data.total_posts + ' posts processed');

					appendLog('Batch completed: processed ' + data.processed_count + ' posts (Offset ' + offset + ' -> ' + currentIndexOffset + ')');

					if (data.completed || currentIndexOffset >= data.total_posts) {
						$('#indexer-progress-status').text('Indexing Completed Successfully!');
						$('#btn-pause-index').hide();
						$('#btn-start-index').show().html('<span class="dashicons dashicons-yes-alt"></span> Indexing Finished');
						appendLog('✓ All published content indexed into Sarkari Sewa AI successfully.');
					} else {
						// Continue next batch
						runIndexBatch(currentIndexOffset);
					}
				},
				error: function(xhr, status, error) {
					appendLog('AJAX error during indexing: ' + error);
					$('#btn-pause-index').hide();
					$('#btn-start-index').show();
				}
			});
		}

		function appendLog(msg) {
			var now = new Date().toLocaleTimeString();
			var logLine = '[' + now + '] ' + msg + '\n';
			$('#console-logs').append(logLine);
			var box = document.getElementById('console-logs');
			if (box) {
				box.scrollTop = box.scrollHeight;
			}
		}

		// 5. Search Test Sandbox
		$('#btn-run-search-test').on('click', function(e) {
			e.preventDefault();
			var query = $('#search-test-query').val().trim();
			if (!query) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Searching...');

			$.ajax({
				url: sarkariAiAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sarkari_ai_search_test',
					query: query,
					nonce: sarkariAiAdmin.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Test Retrieval');
					$('#search-test-results-container').show();

					if (response.success && response.data) {
						var data = response.data;
						var intentText = 'Detected Intent: <strong>' + (data.intent ? data.intent.type : 'general') + '</strong>';
						if (data.intent && data.intent.category_match) {
							intentText += ' | Target Category: <strong>' + data.intent.category_match + '</strong>';
						}
						intentText += ' | Has Results Gate: <strong>' + (data.has_results ? '<span style="color:#059669">PASS</span>' : '<span style="color:#DC2626">NO RESULTS FALLBACK</span>') + '</strong>';

						$('#search-intent-box').html(intentText);

						var $tbody = $('#search-results-tbody').empty();
						if (!data.results || data.results.length === 0) {
							$tbody.append('<tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px;">No matching posts found.</td></tr>');
						} else {
							$.each(data.results, function(i, item) {
								var semScore = (item.semantic_score || 0).toFixed(3);
								var kwScore = (item.keyword_score || 0).toFixed(3);
								var finScore = (item.final_score || 0).toFixed(3);

								var row = '<tr>' +
									'<td>' + item.post_id + '</td>' +
									'<td><strong><a href="' + item.url + '" target="_blank">' + item.title + '</a></strong></td>' +
									'<td><span class="badge">' + (item.category || 'General') + '</span></td>' +
									'<td>' + semScore + '</td>' +
									'<td>' + kwScore + '</td>' +
									'<td><strong>' + finScore + '</strong></td>' +
								'</tr>';
								$tbody.append(row);
							});
						}
					}
				},
				error: function(xhr, status, error) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Test Retrieval');
					alert('Search test failed: ' + error);
				}
			});
		});

		$('.btn-sample-q').on('click', function() {
			$('#search-test-query').val($(this).text());
			$('#btn-run-search-test').trigger('click');
		});

		// 6. Clear Logs
		$('#btn-clear-logs').on('click', function(e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to clear all logs?')) {
				return;
			}

			$.ajax({
				url: sarkariAiAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sarkari_ai_clear_logs',
					nonce: sarkariAiAdmin.nonce
				},
				success: function(response) {
					location.reload();
				}
			});
		});

	});

})(jQuery);
