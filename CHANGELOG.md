# Changelog

All notable changes to Gregius Data will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
