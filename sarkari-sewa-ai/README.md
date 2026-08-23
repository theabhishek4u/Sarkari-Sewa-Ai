# Sarkari Sewa AI — WordPress Search & Chatbot Plugin

**Sarkari Sewa AI** is a production-ready, source-grounded AI search assistant and floating chatbot plugin built specifically for [SarkariSewa.com](https://sarkariseva.com).

It empowers candidates and students to ask questions about government vacancies, results, admit cards, answer keys, syllabus, and admissions in Hindi, English, or Hinglish (e.g. *"Railway ki vacancy aayi hai?"*, *"SSC CGL ka result aaya kya?"*), and answers **ONLY** from published WordPress content on SarkariSewa.com.

---

## Key Highlights & Guarantees

* 🛡️ **Zero-Hallucination Anti-AI Gate**: If search returns 0 relevant posts above the similarity threshold, the plugin returns a polite fallback message **without calling the AI model**.
* 🔍 **Hybrid Retrieval Engine**: Fuses keyword full-text matching (`WP_Query` & local chunk search) with semantic vector search (Supabase `pgvector` / OpenAI / Gemini embeddings).
* 🔗 **100% Trusted URLs**: URLs are never hallucinated by LLMs. Real `get_permalink()` URLs are attached as structured source cards.
* ⚡ **Performance & Caching**: Response caching with automatic invalidation upon post updates and IP-based sliding rate-limiting.
* 📱 **Responsive Vanilla Frontend**: Custom Maroon & Navy branded floating drawer on desktop; full-screen responsive drawer on mobile devices.
* 🎛️ **Full Admin Suite**: Includes bulk batch indexer, search sandbox debugger, health diagnostics, and structured event logs.

---

## Directory Structure

```
sarkari-sewa-ai/
├── sarkari-sewa-ai.php             # Main bootstrap & hooks
├── uninstall.php                    # Safe uninstall with data toggle
├── readme.txt                       # WordPress.org standard readme
├── README.md                        # Documentation
├── INSTALLATION.md                  # Installation & Supabase SQL Setup
├── API_DOCS.md                      # REST API Endpoints Guide
├── includes/
│   ├── class-plugin.php             # Plugin coordinator
│   ├── class-activator.php          # Activation handler & DB setup
│   ├── class-deactivator.php        # Deactivation handler
│   ├── class-database.php           # Local tables (index, chunks, logs)
│   ├── class-rest-api.php           # REST API route registration
│   ├── class-chat-controller.php    # Chat processing pipeline
│   ├── class-search-engine.php      # Hybrid search & ranking engine
│   ├── class-keyword-search.php     # Full-text keyword search
│   ├── class-semantic-search.php    # Vector similarity search
│   ├── class-content-indexer.php    # Post lifecycle hooks & batching
│   ├── class-content-parser.php     # Clean text & metadata extractor
│   ├── class-chunk-manager.php      # Semantic text chunking
│   ├── class-embedding-provider.php # Embedding generation interface
│   ├── class-ai-provider.php        # AI provider interface
│   ├── class-openai-provider.php    # OpenAI GPT-4o-mini & embeddings
│   ├── class-gemini-provider.php    # Google Gemini 1.5 Flash & embeddings
│   ├── class-vector-provider.php    # Vector provider interface
│   ├── class-supabase-provider.php  # Supabase pgvector REST client
│   ├── class-prompt-manager.php     # Strict grounding system prompts
│   ├── class-cache.php              # Transient cache manager
│   ├── class-rate-limiter.php       # IP rate limiter
│   ├── class-security.php           # Input sanitization & security
│   ├── class-logger.php             # Event & error logger
│   └── class-admin.php              # Admin settings & AJAX handlers
├── admin/
│   ├── views/                       # Dashboard, Settings, Indexing, Search Test, Logs
│   ├── css/admin.css                # Admin styles
│   └── js/admin.js                  # Admin interactive script
├── public/
│   ├── css/chatbot.css              # Frontend widget stylesheet
│   └── js/chatbot.js                # Frontend Vanilla JS widget
└── templates/
    ├── chatbot.php                  # Floating chat drawer markup
    ├── message.php                  # Message bubble helper
    └── source-card.php              # Source card helper
```

---

## Quick Start

1. Download or generate `sarkari-sewa-ai.zip`.
2. In WordPress Admin, navigate to **Plugins > Add New > Upload Plugin** and upload `sarkari-sewa-ai.zip`.
3. Activate the plugin.
4. Go to **Sarkari Sewa AI > Settings** to configure your OpenAI/Gemini API key and Supabase pgvector credentials.
5. Head to **Sarkari Sewa AI > Content Index** and click **Start Bulk Indexing** to sync your published posts.
