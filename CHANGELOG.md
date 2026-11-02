# Changelog

All notable changes to Gregius Data will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-11-01

### Added
- RAG progress feedback: a "Preparing follow-up questions..." stage when generating follow-up questions, and a "Retrieving relevant content..." stage during passage retrieval.

### Fixed
- Entity-grounded answers on PostgREST (Supabase) connections now apply the same content cleaning (tag and shortcode stripping, whitespace collapse, 10-character minimum, cleaned title) as direct PostgreSQL connections.

## [1.0.1] - 2026-10-26

### Added
- Dedicated schema versioning (`GREGIUS_DATA_SCHEMA_VERSION`) with a Supabase schema-update flow (copy/verify).
- Two Supabase count functions: `gregius_data_count_postmeta` and `gregius_data_count_cleaning_needed`.
- A five-state validation status (`healthy` / `warning` / `critical` / `degraded` / `error`) and a separate health-check email for connection failures.
- Database provider `upsert()` (PostgREST via merge-duplicates, PostgreSQL via `ON CONFLICT`).
- Simultaneous synchronization to every active connection (direct PostgreSQL and Supabase together) for posts, terms, taxonomies, relationships, meta, and attachments.

### Changed
- Lifecycle metadata tracking, row-count validation, and drift alerts for Supabase (PostgREST) connections operate through the provider layer; drift reporting uses provider-based counts.
- The schema update banner compares against a dedicated schema version rather than the plugin version.
- Postmeta drift counts mirror the WordPress-side meta_key filtering on both direct PostgreSQL and Supabase.
- The real-time sync toggle governs post-save sync.
- PDO schema version stamping records the schema version and reads a single source of truth (`gregius_data_schema_meta`).
- Direct PostgreSQL "Update Schema" completes the postmeta migration against the intended table schema.
- Attachments sync their `post_mime_type` and `post_parent` columns on both backends; detached or deleted attachments are removed from every active connection.
- Dashboard validation reads a pre-aggregated read model (recomputed on write) for load-time drift status.
- Real-time sync is enabled by default.

## [1.0.0] - 2026-10-24

### Added
- Initial public release
- Selective WordPress-to-PostgreSQL synchronization for chosen post types
- Vector embeddings: API providers plus a local HashingTF fallback (Murmur3, 1024D)
- Semantic search with fuzzy typo tolerance
- RAG orchestration with source-aware response metadata
- RAG Assistant block with per-role model assignment and streaming answers
- Multi-provider AI support (OpenAI, Anthropic, Google Gemini, DeepSeek, Cohere, TypeSafe)
- PDF attachment extraction via vision-capable models
- Factory prompts (System, Security, Evaluation) seeded on activation
- RAG evaluation via the ragas framework (WP-CLI)
- WordPress Abilities API integration (WP 6.9+)
- Connection health monitoring, retry queue, and schema setup tooling
- Multisite support (per-site settings, cron, and sync defaults)
- React admin dashboard: Models, Connections, Sync, Vectors, Prompts, Search, and Logs
- REST API, WP-CLI commands, and hooks for developer integrations
- Translation-ready (text domain); no bundled translations
