=== Gregius Data ===
Contributors: hectorjarquin, gregiusteam
Tags: ai, rag, semantic search, postgresql, embeddings
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Give WordPress developers a foundation to build AI-powered features.

== Description ==

Gregius Data is an open-source orchestration layer for AI workflows in WordPress.

It connects WordPress content with PostgreSQL-backed retrieval and AI provider workflows using familiar WordPress patterns.

WordPress remains the source of truth. PostgreSQL runs alongside as a secondary execution layer for retrieval and vector operations.

**Key Features:**

* **RAG Workflow** - Retrieval, governance, answer generation, and source attribution
* **Provider Architecture** - Database backends and AI providers behind stable contracts
* **Selective Sync** - Choose which post types are synchronized to PostgreSQL
* **Interaction and Logs** - Operational visibility for request flows and outcomes
* **Orchestration Controls** - Retrieval, model selection, and policy controls in one workflow
* **Integration Surface** - REST API, WP-CLI, and hooks for extension and automation

= How It Works =

1. Syncs your WordPress content to PostgreSQL (you choose what syncs)
2. Runs retrieval operations from PostgreSQL-backed data
3. Executes RAG generation with your configured AI provider (optional)
4. Returns answer data with source metadata and governance context
5. Captures interaction/log details for monitoring and review

**Your WordPress stays on MySQL.** PostgreSQL runs alongside as a secondary execution layer.

= Use Cases =

* **Grounded site assistants** based on your published WordPress content
* **RAG-driven Q&A experiences** where source attribution is required
* **Recommendation engines** that suggest relevant content using semantic similarity
* **Developer-oriented AI integrations** using REST API, hooks, and WP-CLI

== Installation ==

= Requirements =

* WordPress 6.9 or higher (required for AI Abilities API)
* PHP 8.2 or higher
* Connection mode (choose one): Supabase (managed, recommended) or Direct PostgreSQL 16+ with pgvector (self-hosted/direct path; requires PDO + pdo_pgsql)
* Optional AI provider credentials for generation workflows

= Database Setup =

**Managed (Recommended):**
* [Supabase](https://supabase.com)

**Direct PostgreSQL (Alternative):**
* Install PostgreSQL 16+ with pgvector extension
* Run: `CREATE EXTENSION IF NOT EXISTS vector;`

= Plugin Installation =

### 2. Install Plugin

1. Navigate to Plugins
2. Install and activate
3. Navigate to Data

= WP-CLI Commands =

Core command examples:

* `wp gg-data list-connections`
* `wp gg-data answer "What is dementia care?" --format=json`
* `wp gg-data benchmark validate-config`
* `wp gg-data benchmark run --only=P1`
* `wp gg-data evaluation validate-config`
* `wp gg-data evaluation run --framework=ragas`

Benchmark and evaluation config templates are shipped inside the plugin under:

* `wp-content/plugins/gregius-data/includes/cli/resources/benchmark/`
* `wp-content/plugins/gregius-data/includes/cli/resources/evaluation/`

Recommended writable local config location:

* `wp-content/uploads/gregius-data/config/`

Benchmark and evaluation artifacts are written outside the plugin directory by default so they are not lost on plugin updates:

* `wp-content/uploads/gregius-data/artifacts/`

== Frequently Asked Questions ==

= Does this replace my MySQL database? =

No. WordPress stays on MySQL. PostgreSQL runs alongside as a secondary layer for retrieval operations. Your primary WordPress database is not replaced.

= What happens if PostgreSQL is unavailable? =

The WordPress site itself remains available, but retrieval operations that depend on PostgreSQL are affected until the connection is restored.

= Do I need to know SQL? =

No. The dashboard handles everything through a visual interface. You only need basic connection details (host, database name, credentials).

= What data is synced to PostgreSQL? =

Only content from the post types you choose for synchronization.

= Which AI providers are supported? =

Supported providers include OpenAI, Anthropic, Google Gemini, DeepSeek, Cohere, and Voyage AI when configured.

= Do I need AI API keys to use the plugin? =

No. PostgreSQL synchronization and retrieval infrastructure can be configured without AI API keys. API keys are only required when generation workflows are enabled.

= Does this work with page builders? =

Yes. The plugin syncs final post content regardless of how it's created (Gutenberg, Classic Editor, Page Builders, etc.).

= How much does managed PostgreSQL hosting cost? =

Supabase provides a free tier for evaluation. Paid costs depend on your usage and provider plan.

== Contributors & Developers ==

Gregius Data is open source software. The following people have contributed to this plugin.

* **Hector Jarquin** — Lead developer and maintainer
* **Gregius** — Product owner and sponsor

Visit the contributor profiles on WordPress.org:
* https://profiles.wordpress.org/hectorjarquin/
* https://profiles.wordpress.org/gregiusteam/

== Screenshots ==

1. Models tab for provider and model configuration
2. Connections tab with PostgreSQL health checks
3. Sync tab with selective post type synchronization controls
4. Vectors tab for embeddings and vocabulary workflows
5. Prompts tab for prompt templates and orchestration settings
6. Search tab for retrieval and generation execution
7. Logs tab for interaction and operational trace visibility

== Changelog ==

= 1.0.0 =
* Initial release
* Selective WordPress to PostgreSQL synchronization for chosen post types
* Retrieval and semantic search workflows over PostgreSQL-backed data
* Vector and embeddings management workflows
* RAG orchestration with source-aware response metadata
* Multi-provider AI support (OpenAI, Anthropic, Google Gemini, DeepSeek, Cohere, Voyage AI)
* Connection health checks and schema setup tooling
* Retry queue for failed synchronization operations
* Admin dashboard modules: Models, Connections, Sync, Vectors, Prompts, Search, and Logs
* REST API, WP-CLI commands, and hooks for developer integrations

== Repository ==

Source code and build instructions:
https://github.com/gregius/gregius-data

This plugin uses composer for PHP dependency management and @wordpress/scripts for asset compilation. Source JavaScript lives in `assets/src/` and compiles to `assets/build/`. All PHP source is human-readable.

== Upgrade Notice ==

= 1.0.0 =
Initial public release. After activation, configure your PostgreSQL connection, run schema setup, and choose synchronized post types before enabling retrieval and generation workflows.

== Privacy Policy ==

This plugin syncs publicly visible WordPress content (posts, pages, custom post types you select) to an external PostgreSQL database you control. No user data, passwords, or sensitive information is synced. Connection details are stored using standard WordPress options API.

== External Services ==

This plugin connects to services you configure:

1. PostgreSQL database provider (required):
- You provide and control this database connection (self-hosted or managed provider).

2. AI provider APIs (optional):
- OpenAI, Anthropic, Google Gemini, DeepSeek, Cohere, and Voyage AI are used only if you configure them.
- Requests to generation/embedding/rerank workflows send request data to the provider you selected.
- OpenAI Terms: https://openai.com/policies/terms-of-use/ | Privacy: https://openai.com/policies/privacy-policy/
- Anthropic Terms: https://www.anthropic.com/legal/consumer-terms | Privacy: https://www.anthropic.com/privacy
- Google Gemini Terms: https://policies.google.com/terms | Privacy: https://policies.google.com/privacy
- DeepSeek Terms: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html | Privacy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html
- Cohere Terms: https://cohere.com/terms-of-use | Privacy: https://cohere.com/privacy
- Voyage AI Terms: https://www.voyageai.com/terms-of-service | Privacy: https://www.voyageai.com/privacy

You are responsible for each configured provider's terms and privacy policy.
