# Providers - Databases and AI Models Service Abstraction

[Editorial: Feature: Providers | Pages: 1 | Total words: 920 | Sections: 7 (H2: 7, H3: 10, H4: 0) | Screenshots needed: 3 | Doc category: /docs/category/ai-features/ | Audience: Administrator]

## Overview

This feature enables **site administrators** to configure and manage database backends and AI services through a unified provider abstraction layer. The provider subsystem handles all communication with PostgreSQL databases and external AI APIs, so you can choose the setup that fits your hosting environment and workflow.

[SRS: PA-FR-01, PA-FR-11]

### Prerequisites

- Gregius Data plugin installed and activated
- At least one database connection configured (PostgreSQL or PostgREST/Supabase)
- API keys for any AI providers you plan to use

---

## In this page

- [Overview](#overview)
  - [Prerequisites](#prerequisites)
- [Database Providers](#database-providers)
  - [PostgreSQL (PDO)](#postgresql-pdo)
  - [PostgREST (Supabase)](#postgrest-supabase)
- [AI Providers](#ai-providers)
  - [LLM Providers](#llm-providers)
  - [Embedding and Reranking Providers](#embedding-and-reranking-providers)
  - [Internal Embedding Models](#internal-embedding-models)
- [How to: Configure a Connection](#how-to-configure-a-connection)
  - [Add a Connection](#add-a-connection)
  - [Test a Connection](#test-a-connection)
  - [Choose a Provider](#choose-a-provider)
- [Permissions](#permissions)
- [Next Steps](#next-steps)

---

## Database Providers

The database provider subsystem gives you two ways to connect Gregius Data to a PostgreSQL database. Both implement the same interface, so upstream features like sync, search, and the RAG pipeline work identically regardless of which provider you choose.

### PostgreSQL (PDO)

Connects directly to [PostgreSQL](https://www.postgresql.org/) using the `pdo_pgsql` PHP extension. Native [pgvector](https://github.com/pgvector/pgvector) support for semantic search.

Key facts:
- Requires the **`pdo_pgsql`** PHP extension on your server
- Native pgvector support for semantic search
- Ideal for VPS, dedicated, and cloud hosting with database TCP access
- Ask your hosting provider to enable the extension if not already available

[SRS: PA-FR-09]

### PostgREST (Supabase)

Connects to [PostgreSQL](https://www.postgresql.org/) through the [Supabase](https://supabase.com/) REST API ([PostgREST](https://postgrest.org/)). Uses the WordPress HTTP API for all communication.

Key facts:
- **No PHP database extensions required**
- Works on any WordPress hosting environment, including shared hosting
- Uses `wp_remote_post()` for all outbound requests
- RPC functions handle bulk operations and complex queries efficiently
- Supports `postgresql` and `supabase` as alias type names (both resolve to the same provider)

<!-- IMAGE: database provider selection screen showing PostgreSQL and PostgREST options -->

[SRS: PA-FR-10]

---

## AI Providers

Gregius Data ships with seven built-in AI providers covering text generation, embedding, and reranking capabilities. All providers are registered with the AI provider registry and are available for use by the RAG pipeline and other plugin features.

### LLM Providers

These providers handle text generation and conversation:

| Provider | Models | Capabilities |
|---|---|---|
| [OpenAI](https://openai.com) | GPT-4o, GPT-4o-mini, o-series | LLM, tool calling |
| [Anthropic](https://www.anthropic.com) | Claude 3/4 series | LLM, tool calling |
| [Google Gemini](https://deepmind.google/technologies/gemini/) | Gemini 1.5/2.0 series | LLM, embeddings, tool calling |
| [DeepSeek](https://deepseek.com) | DeepSeek V2/V3 | LLM, tool calling |

### Embedding and Reranking Providers

These providers generate vector embeddings and rerank search results:

| Provider | Purpose |
|---|---|
| [Voyage AI](https://www.voyageai.com) | Embeddings and reranking |
| [Cohere](https://cohere.com) | Embeddings and reranking |

<!-- IMAGE: AI providers configuration page showing API key fields for each provider -->

### Internal Embedding Models

Gregius Data ships two free local embedding models that require no API keys or external service accounts.

**TF-IDF 300D** (`tfidf-300`) — 300-dimensional vectors using [term frequency-inverse document frequency](https://en.wikipedia.org/wiki/Tf%E2%80%93idf). Requires a corpus vocabulary to be generated before use, which provides accurate IDF weighting. Ideal for sites that have enough content to build a meaningful vocabulary.

**HashingTF Murmur3 1024D** (`hashingtf-murmur3-1024`) — 1024-dimensional vectors using PHP-native [MurmurHash3](https://en.wikipedia.org/wiki/MurmurHash) feature hashing. Stateless — no vocabulary needed, usable immediately after schema creation. Uses signed hash bucket assignment and field-type weighting (title 1.5×, excerpt 1.2×, chunk 1.0×) with L2 normalization.

[SRS: PA-FR-17]

---

## How to: Configure a Connection

### Add a Connection

1. Navigate to **Gregius Data > Connections** in the WordPress admin.
2. Click **Add New Connection**.
3. Enter a name for the connection (for example, `default` or `production-db`).
4. Select the provider type:
   - **PostgreSQL** for direct PDO access
   - **PostgREST** for Supabase HTTP access
5. Fill in the connection details (host, credentials, project URL).
6. Save the connection.

### Test a Connection

After saving, use the **Test Connection** button to verify the provider can reach the backend. The test runs a simple connectivity check and returns a success or error message.

- For PostgreSQL providers, this attempts a PDO connection and runs a `SELECT 1` query.
- For PostgREST providers, this calls the health endpoint via HTTP.

If the test fails, check credentials, network access, and PHP extension availability.

[SRS: PA-FR-02]

### Choose a Provider

| If you need... | Use |
|---|---|
| Direct [PostgreSQL](https://www.postgresql.org/) access | **PostgreSQL (PDO)** - requires the `pdo_pgsql` PHP extension. Ask your host to enable it if not already available. |
| HTTP-based access that works on any hosting | **PostgREST ([Supabase](https://supabase.com/))** - no PHP extensions required. Works on shared hosting, managed WordPress, and environments without database TCP access. |
| External AI text generation (LLM) | **[OpenAI](https://openai.com), [Anthropic](https://www.anthropic.com), [Gemini](https://deepmind.google/technologies/gemini/), or [DeepSeek](https://deepseek.com)** |
| External embeddings or reranking | **[Voyage AI](https://www.voyageai.com)** (embeddings + reranking) or **[Cohere](https://cohere.com)** (embeddings + reranking) |
| Local embeddings, accurate IDF weighting | **[TF-IDF 300D](https://en.wikipedia.org/wiki/Tf%E2%80%93idf)** (`tfidf-300`) - requires corpus vocabulary |
| Local embeddings, zero setup | **[HashingTF Murmur3 1024D](https://en.wikipedia.org/wiki/MurmurHash)** (`hashingtf-murmur3-1024`) - stateless, no vocabulary needed |

---

## Permissions

| Function | Who can use it |
|---|---|
| View provider configurations | Administrators only |
| Add, edit, or delete connections | Administrators only |
| Test a connection | Administrators only |
| Use AI providers via the RAG pipeline | Depends on ability permissions |

[SRS: PA-OR-01]

---

## Next Steps

**Local:**
- [Database Providers](#database-providers)
- [AI Providers](#ai-providers)
- [Configure a Connection](#how-to-configure-a-connection)

**Global:**
- [Gregius Data: Search](/docs/search/)
- [Gregius Data: Abilities API](/docs/abilities/)
