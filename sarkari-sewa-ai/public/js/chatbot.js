/**
 * Sarkari Sewa AI — Frontend Vanilla JavaScript Chatbot
 */

(function() {
	'use strict';

	// Wait for DOM to load
	document.addEventListener('DOMContentLoaded', function() {
		var config = window.sarkariSewaAiConfig || {};
		var widget = document.getElementById('sarkari-sewa-ai-widget');
		if (!widget) return;

		var triggerBtn   = document.getElementById('sarkari-ai-trigger');
		var panel        = document.getElementById('sarkari-ai-panel');
		var closeBtn     = document.getElementById('sarkari-ai-close-panel');
		var clearBtn     = document.getElementById('sarkari-ai-clear-chat');
		var messagesBox  = document.getElementById('sarkari-ai-messages');
		var suggestions  = document.getElementById('sarkari-ai-suggestions');
		var form         = document.getElementById('sarkari-ai-form');
		var input        = document.getElementById('sarkari-ai-input');
		var sendBtn      = document.getElementById('sarkari-ai-send-btn');

		var isOpen = false;
		var isProcessing = false;
		var sessionKey = 'sse_ai_chat_history_v1';

		// Apply Brand Colors from Config if custom
		if (config.primaryColor) {
			document.documentElement.style.setProperty('--sse-primary', config.primaryColor);
		}
		if (config.secondaryColor) {
			document.documentElement.style.setProperty('--sse-navy', config.secondaryColor);
		}

		var remainingToday = typeof config.remainingToday !== 'undefined' ? parseInt(config.remainingToday, 10) : -1;
		var dailyLimit = typeof config.dailyLimit !== 'undefined' ? parseInt(config.dailyLimit, 10) : 10;

		// Initialize Chat State
		initSuggestions();
		loadSessionHistory();
		checkLimitState();

		// Event Listeners
		triggerBtn.addEventListener('click', toggleChat);
		closeBtn.addEventListener('click', closeChat);
		clearBtn.addEventListener('click', clearChat);

		function checkLimitState() {
			if (dailyLimit > 0 && remainingToday === 0) {
				input.disabled = true;
				input.placeholder = 'Daily quota reached. Please come back tomorrow!';
				input.classList.add('input-limit-exhausted');
				sendBtn.disabled = true;
			}
		}

		// Auto resize textarea & enable/disable send button
		input.addEventListener('input', function() {
			if (input.disabled) return;
			this.style.height = 'auto';
			this.style.height = (this.scrollHeight) + 'px';
			sendBtn.disabled = this.value.trim().length === 0 || isProcessing;
		});

		// Keyboard submission handling
		input.addEventListener('keydown', function(e) {
			if (input.disabled) return;
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				if (!sendBtn.disabled) {
					form.dispatchEvent(new Event('submit'));
				}
			}
		});

		// Close on Escape key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && isOpen) {
				closeChat();
			}
		});

		// Handle Form Submission
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			if (input.disabled) return;
			var query = input.value.trim();
			if (!query || isProcessing) return;

			// Add User Message
			appendMessage('user', escapeHtml(query));
			input.value = '';
			input.style.height = 'auto';
			sendBtn.disabled = true;

			// Send to REST API
			sendChatQuery(query);
		});

		function toggleChat() {
			isOpen ? closeChat() : openChat();
		}

		function openChat() {
			isOpen = true;
			widget.classList.add('widget-open');
			panel.style.display = 'flex';
			triggerBtn.querySelector('.trigger-icon-chat').style.display = 'none';
			triggerBtn.querySelector('.trigger-icon-close').style.display = 'flex';
			triggerBtn.setAttribute('aria-expanded', 'true');
			scrollToBottom();
			setTimeout(function() { if (!input.disabled) input.focus(); }, 150);
		}

		function closeChat() {
			isOpen = false;
			widget.classList.remove('widget-open');
			panel.style.display = 'none';
			triggerBtn.querySelector('.trigger-icon-chat').style.display = 'flex';
			triggerBtn.querySelector('.trigger-icon-close').style.display = 'none';
			triggerBtn.setAttribute('aria-expanded', 'false');
		}

		function initSuggestions() {
			suggestions.innerHTML = '';
			var items = config.suggestedQuestions || [];
			items.forEach(function(q) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'suggestion-chip';
				btn.textContent = q;
				btn.addEventListener('click', function() {
					if (isProcessing || input.disabled) return;
					input.value = q;
					sendBtn.disabled = false;
					form.dispatchEvent(new Event('submit'));
				});
				suggestions.appendChild(btn);
			});
		}

		function loadSessionHistory() {
			var saved = sessionStorage.getItem(sessionKey);
			if (saved) {
				try {
					var history = JSON.parse(saved);
					messagesBox.innerHTML = '';
					history.forEach(function(item) {
						appendMessage(item.sender, item.text, item.sources, false);
					});
					return;
				} catch(e) {}
			}

			// First-time default greeting
			var greeting = config.greeting || "Namaste! 👋\n\nI can help you find jobs, results & admit cards published on SarkariSewa.com.";
			appendMessage('bot', formatMarkdown(greeting), [], true);
		}

		function sendChatQuery(message) {
			isProcessing = true;
			showTypingIndicator();

			var endpoint = config.restUrl || '/wp-json/sarkari-sewa-ai/v1/chat';

			fetch(endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce || ''
				},
				body: JSON.stringify({ message: message })
			})
			.then(function(res) {
				return res.json().then(function(data) {
					return { status: res.status, data: data };
				});
			})
			.then(function(result) {
				removeTypingIndicator();
				isProcessing = false;

				if (result.data && typeof result.data.data !== 'undefined' && typeof result.data.data.remaining_today !== 'undefined') {
					remainingToday = parseInt(result.data.data.remaining_today, 10);
					checkLimitState();
				}

				if (result.status === 429) {
					var limitErr = (result.data && result.data.error)
						? result.data.error
						: '⚠️ Too many requests. Please wait before asking again.';
					appendMessage('bot', formatMarkdown(limitErr));
					if (result.data && result.data.code === 'daily_limit_reached') {
						remainingToday = 0;
						checkLimitState();
					}
					return;
				}

				if (!result.data || !result.data.success) {
					var err = (result.data && result.data.error)
						? result.data.error
						: ((result.data && result.data.message) ? result.data.message : 'No matching information found on SarkariSewa.com. Please try searching for a different job or result.');
					appendMessage('bot', formatMarkdown(err));
					sendBtn.disabled = input.value.trim().length === 0 || input.disabled;
					return;
				}

				var payload = result.data.data || {};
				var answer = payload.answer || '';
				var sources = payload.results || [];

				appendMessage('bot', formatMarkdown(answer), sources, true);
				sendBtn.disabled = input.value.trim().length === 0 || input.disabled;
			})
			.catch(function(err) {
				removeTypingIndicator();
				isProcessing = false;
				sendBtn.disabled = input.value.trim().length === 0 || input.disabled;
				appendMessage('bot', '⚠️ Network error. Please check your connection and try again.');
			});
		}

		function appendMessage(sender, htmlContent, sources, saveToSession) {
			var msgRow = document.createElement('div');
			msgRow.className = 'sarkari-msg ' + (sender === 'user' ? 'msg-user' : 'msg-bot');

			if (sender === 'bot') {
				var avatar = document.createElement('div');
				avatar.className = 'msg-avatar';
				avatar.textContent = 'SS';
				msgRow.appendChild(avatar);
			}

			var bubble = document.createElement('div');
			bubble.className = 'msg-bubble';

			var textDiv = document.createElement('div');
			textDiv.className = 'msg-text';
			textDiv.innerHTML = htmlContent;
			bubble.appendChild(textDiv);

			// Append Source Cards if present
			if (sources && sources.length > 0) {
				var sourcesContainer = document.createElement('div');
				sourcesContainer.className = 'msg-sources';

				var titleSpan = document.createElement('div');
				titleSpan.className = 'sources-title';
				titleSpan.textContent = 'Published on Sarkari Sewa:';
				sourcesContainer.appendChild(titleSpan);

				var listDiv = document.createElement('div');
				listDiv.className = 'sources-list';

				sources.forEach(function(src) {
					var cardLink = document.createElement('a');
					cardLink.href = src.url;
					cardLink.className = 'source-card-link';
					cardLink.target = '_blank';
					cardLink.rel = 'noopener noreferrer';

					var card = document.createElement('div');
					card.className = 'source-card';

					var metaRow = document.createElement('div');
					metaRow.className = 'source-meta-row';

					var cat = document.createElement('span');
					cat.className = 'source-category-badge';
					cat.textContent = src.category || 'Latest Job';
					metaRow.appendChild(cat);

					if (src.last_date) {
						var dateSpan = document.createElement('span');
						dateSpan.className = 'source-deadline';
						dateSpan.textContent = '📅 Last Date: ' + src.last_date;
						metaRow.appendChild(dateSpan);
					}

					var title = document.createElement('div');
					title.className = 'source-title';
					title.textContent = src.title;

					var action = document.createElement('span');
					action.className = 'source-action-btn';
					action.innerHTML = 'View Details & Apply <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';

					card.appendChild(metaRow);
					card.appendChild(title);
					card.appendChild(action);
					cardLink.appendChild(card);
					listDiv.appendChild(cardLink);
				});

				sourcesContainer.appendChild(listDiv);
				bubble.appendChild(sourcesContainer);
			}

			msgRow.appendChild(bubble);
			messagesBox.appendChild(msgRow);
			scrollToBottom();

			if (saveToSession !== false) {
				saveHistoryItem(sender, htmlContent, sources);
			}
		}

		function saveHistoryItem(sender, text, sources) {
			try {
				var history = JSON.parse(sessionStorage.getItem(sessionKey) || '[]');
				history.push({ sender: sender, text: text, sources: sources || [] });
				// Keep max 25 messages in session
				if (history.length > 25) history.shift();
				sessionStorage.setItem(sessionKey, JSON.stringify(history));
			} catch(e) {}
		}

		function clearChat() {
			sessionStorage.removeItem(sessionKey);
			messagesBox.innerHTML = '';
			var greeting = config.greeting || "Namaste! 👋\n\nI can help you find jobs, results & admit cards published on SarkariSewa.com.";
			appendMessage('bot', formatMarkdown(greeting), [], true);
		}

		function showTypingIndicator() {
			var row = document.createElement('div');
			row.id = 'sse-typing-indicator';
			row.className = 'sarkari-msg msg-bot';
			row.innerHTML = '<div class="msg-avatar">SS</div><div class="msg-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
			messagesBox.appendChild(row);
			scrollToBottom();
		}

		function removeTypingIndicator() {
			var ind = document.getElementById('sse-typing-indicator');
			if (ind) ind.remove();
		}

		function scrollToBottom() {
			messagesBox.scrollTop = messagesBox.scrollHeight;
		}

		function escapeHtml(str) {
			return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		}

		function formatMarkdown(text) {
			if (!text) return '';
			var safe = escapeHtml(text);

			// Bold **text**
			safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
			// Italic *text*
			safe = safe.replace(/\*(.*?)\*/g, '<em>$1</em>');
			// Convert linebreaks to paragraphs/br
			safe = safe.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
			return '<p>' + safe + '</p>';
		}

	});
})();
