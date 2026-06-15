# Gregius Data

**Give WordPress developers a foundation to build AI-powered features.**

Gregius Data is an open-source orchestration layer for AI workflows in WordPress.

It connects WordPress content with PostgreSQL-backed retrieval and AI provider workflows using familiar WordPress patterns.

WordPress remains your source of truth. PostgreSQL runs alongside as a secondary execution layer for retrieval and vector operations.

## At a Glance

- Sync selected content from WordPress to PostgreSQL
- Retrieve relevant context semantically
- Generate grounded responses with your configured AI provider
- Return response, source attribution, and governance metadata

No database migration required. No lock-in to a single AI provider. No need to rebuild your retrieval stack.

## What You Can Build

- **Grounded site assistants** that can answer from your published content
- **RAG (Retrieval Augmented Generation) content experiences** with source attribution
- **Recomendation Engines** that can suggest relevant content based on semantic similarity
- **Custom AI integrations** via REST API, hooks, and WP-CLI

## Capabilities

- **RAG workflow** with retrieval, governance, answer synthesis, and source attribution
- **Provider-based architecture** for database backends and AI services
- **Selective sync** from WordPress to PostgreSQL for chosen post types
- **Interaction tracking and logging** for operational visibility
- **Retry queue and health checks** for resilience
- **Orchestration-first design**: retrieval, model selection, and policy controls in one workflow
- **Multi-provider AI support**: OpenAI, Anthropic, Google Gemini, DeepSeek, Cohere, and Voyage AI (when configured)
- **Grounded retrieval layer**: semantic retrieval designed for RAG quality and source attribution
- **Developer integration surface**: REST API, WP-CLI, and hooks for customization
- **Data ownership stays with you**: your primary content remains in WordPress

## Requirements

- **WordPress**: 6.9 or higher
- **PHP**: 8.2 or higher
- **Connection mode (choose one):**
	- **Supabase** (managed, recommended)
	- **Direct PostgreSQL** 16.0+ with pgvector (self-hosted/direct path; requires PDO + pdo_pgsql)

## Quick Start

### 1. Choose your PostgreSQL connection mode

**Managed (Recommended):**
- [Supabase](https://supabase.com)

**Direct PostgreSQL (Alternative):**
```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### 2. Install Plugin

1. Naviagate to Plugins
2. Install and activate
3. Navigate to Data

## How It Works

1. Content is synchronized from WordPress to PostgreSQL based on post types selection.
2. Retrieval operations run against PostgreSQL data.
3. RAG requests combine retrieval evidence with the configured AI provider.
4. Responses return answer text plus source metadata and governance context.
5. Interaction and log subsystems capture execution details for monitoring and review.

## Documentation

- **Full Documentation**: [View docs folder](./docs/)
- **Documentation Index**: [docs/README.md](./docs/README.md)
- **Architecture**: See subsystem architecture docs in `/docs/`
- **RAG**: [docs/rag/architecture.md](./docs/rag/architecture.md)
- **Providers**: [docs/providers/architecture.md](./docs/providers/architecture.md)
- **REST API**: [rest-api/architecture.md](./docs/rest-api/architecture.md)
- **Sync Process**: [sync/architecture.md](./docs/sync/architecture.md)
- **Schema Management**: [schema/architecture.md](./docs/schema/architecture.md)
- **Plugin Lifecycle**: [lifecycle/architecture.md](./docs/lifecycle/architecture.md)
- **Retry Queue**: [retry-queue/architecture.md](./docs/retry-queue/architecture.md)
- **Interactions**: [interactions/architecture.md](./docs/interactions/architecture.md)
- **WP-CLI**: [wpcli/architecture.md](./docs/wpcli/architecture.md)
- **Vectors**: [vectors/architecture.md](./docs/vectors/architecture.md)

## External Services

- **PostgreSQL service**: required (self-hosted or managed provider)
- **AI providers**: optional, only when configured by the site owner

## Support

- **Community Support**: [WordPress.org Forum](https://wordpress.org/support/plugin/gregius-data/)
- **Issues**: [GitHub Issues](https://github.com/gregius/gregius-data/issues)
- **Documentation**: Full documentation in `/docs/` folder

## License

GPL v2 or later. See [LICENSE](./LICENSE) for details.

---

**Website**: [gregius.com](https://gregius.com) · **GitHub**: [gregius/gregius-data](https://github.com/gregius/gregius-data)
