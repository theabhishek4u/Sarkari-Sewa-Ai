=== Sarkari Sewa AI ===
Contributors: sarkarisevateam
Tags: ai, search, chatbot, jobs, sarkari result, admit card, rag
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Production-ready source-grounded AI search assistant and chatbot answering strictly from published content on SarkariSewa.com.

== Description ==

Sarkari Sewa AI is a custom WordPress plugin engineered specifically for SarkariSewa.com. It connects visitors to government jobs, results, admit cards, answer keys, syllabus, and admissions through an intelligent, conversational interface that is strictly grounded in published WordPress posts.

Key Features:
* **Source-Grounded Answers**: Zero hallucination guarantee. Answers exclusively from indexed WordPress content.
* **Hybrid Search Engine**: Combines full-text keyword matching with semantic vector search (Supabase pgvector / OpenAI / Gemini embeddings).
* **Multi-language Support**: Natural understanding of Hindi, English, and Hinglish queries (e.g., "Railway ki vacancy aayi hai?").
* **Automated Content Sync**: Automatically indexes newly published posts, updates indexes when posts change, and cleans up trashed posts.
* **Floating Chatbot UI**: High-performance, accessible Vanilla JS interface styled with Sarkari Sewa's Maroon & Navy branding.
* **Mobile Optimized**: Full-screen modal drawer on small screens with keyboard accessibility and safe area support.
* **Admin Dashboard & Test Suite**: Real-time retrieval testing, batch indexing, connection diagnostics, and structured logs.

== Installation ==

1. Upload the `sarkari-sewa-ai` folder to your `/wp-content/plugins/` directory, or upload `sarkari-sewa-ai.zip` via Plugins > Add New > Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Sarkari Sewa AI > Settings** in the admin sidebar.
4. Enter your AI Provider API key (OpenAI or Google Gemini) and Vector DB credentials (Supabase).
5. Run the Connection Test to verify connectivity.
6. Navigate to **Content Index** and click "Start Indexing" to build the initial search vectors.

== Frequently Asked Questions ==

= Does the AI invent jobs or dates if no posts are found? =
No. The plugin includes a hard-coded anti-hallucination gate. If zero relevant posts are retrieved above the similarity threshold, it returns a polite fallback message and never invokes the AI model.

= Are API keys exposed in the frontend? =
Never. All AI and vector API requests are processed strictly on the WordPress server via secure REST API endpoints.

== Changelog ==

= 1.0.0 =
* Initial production release.
