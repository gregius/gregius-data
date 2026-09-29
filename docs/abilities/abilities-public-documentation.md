# Abilities API — Exposing Engine Capabilities to AI Agents

[Editorial: Feature: Abilities API | Pages: 1 | Total words: 1150 | Sections: 9 (H2: 8, H3: 11) | Screenshots: 1 | Doc category: /docs/category/ai-features/ | Audience: Administrator]

## Overview

AI agents and automation tools need a contract — not a conversation. The WordPress Abilities API (WP 6.9+) provides exactly that: a central registry where functional capabilities are defined, permissioned, and discoverable through a single REST endpoint.

Gregius Data registers its full engine surface — RAG pipeline, data connections, and model inventory — as three discrete abilities. Each ability maps to a machine-readable tool contract that AI agents can discover via `GET /wp-json/wp-abilities/v1/abilities` and invoke with well-defined inputs. Site administrators control which engine capabilities are exposed and who can call them.

By the end of this page, you will know what abilities Gregius Data exposes, how to configure data connections, browse available models, and run AI-powered site searches through a single standardized interface.

[SRS: ABIL-FR-03, ABIL-FR-04, ABIL-FR-05]

### Prerequisites

- WordPress 6.9+ with Gregius Data plugin installed and activated
- At least one AI model registered and active on the site
- At least one data connection configured and active

---

## Understanding Abilities

Each ability is a discrete unit of functionality — namespace, name, JSON Schema inputs and outputs, a permission callback, and a category assignment — all discoverable through a single REST endpoint.

Gregius Data registers three abilities that expose its engine capabilities:

| Ability | Description | Category | Permission |
|---|---|---|---|
| `gregius-data/answer` | Searches site content via the RAG pipeline and returns an answer with sources | ai | `read` |
| `gregius-data/list-connections` | Returns configured data connections with optional embedding model context | ai | `manage_options` |
| `gregius-data/list-models` | Returns registered AI models with optional type filtering | ai | `manage_options` |

WordPress Core ships additional abilities in WP 6.9 — `core/get-site-info`, `core/get-user-info`, and `core/get-environment-info` — following the same contract pattern and exposed through the same REST endpoint.

[SRS: ABIL-FR-01, ABIL-FR-02]

---

## How to: Configure Data Connections

Data connections tell the AI which data sources it can search. Each connection links to a database or API. Think of connections as instrument sections in an orchestration: individually tuned, collectively ready for performance. They surface through the `gregius-data/list-connections` ability.

<!-- IMAGE: admin settings page showing the connections list with name, type, description, and active/inactive status badges -->

### View connections

Listing your connections returns four fields per entry:

- **Name** — A label identifying the connection
- **Type** — The data source type (PostgreSQL database or Supabase-style REST API)
- **Description** — What data this connection provides
- **Active status** — Whether the connection is currently enabled

### Get optional details

Request additional detail per connection to surface the models powering it:

- **Embedding model overview** — Which embedding model keys are active and how many
- **Full model details** — Model ID, type, provider, label, active status, and optional dimensions or description

[SRS: ABIL-FR-09, ABIL-DR-08, ABIL-DR-09]

### Tips

- Use connection names found here as valid inputs for AI Site Search
- Connections can be database-backed (PostgreSQL) or API-backed (Supabase-style REST)

---

## How to: Browse Available AI Models

AI models power search, answers, and relevance ranking. Different model types handle different tasks. The `gregius-data/list-models` ability surfaces your full model inventory with type filtering — use it before running searches to confirm which models are active and available.

<!-- IMAGE: models list page showing type filter dropdown and model cards with ID, type, provider, status -->

### View all models

Each model displays:

- **ID** — Unique identifier
- **Type** — What it is used for (embeddings, LLM, rerank)
- **Provider** — Where the model comes from
- **Label** — Display name
- **Active status** — Whether the model is enabled
- **Description** — What it does (if available)
- **Dimensions** — Technical reference (if applicable)

### Filter by type

Narrow the list by model type:

- **embeddings** — Convert text into searchable vectors
- **llm** — Language models that generate answers
- **rerank** — Improve result relevance

[SRS: ABIL-FR-10, ABIL-DR-05]

### Tips

- Use valid model IDs found here when configuring AI Site Search
- Models are listed from your global registry, not per-connection storage

---

## How to: Use AI Site Search

Ask a question about your site's content through the `gregius-data/answer` ability. It orchestrates the full RAG pipeline — search, retrieval, reranking, and answer generation — returning a response with supporting source references. A well-tuned search is the difference between a generic reply and a precise, sourced answer.

<!-- IMAGE: AI Site Search input form showing required fields: Query, Connection name, Embedding model, Answer model -->

### What you need

Before asking a question:

- A **data connection** configured and active
- An **embedding model** for search
- An **answer model** for generating responses
- Optionally, a **rerank model** for improved relevance

### Required inputs

| Input | What it is | Example |
|---|---|---|
| Query | Your question | "What features does the Pro plan include?" |
| Connection name | The data source to search | "docs-database" |
| Embedding model | Model for searching | "text-embedding-3-small" |
| Answer model | Model for generating the response | "gpt-4o-mini" |

### What you get back

- **Answer** — A generated response to your question
- **Sources** — References showing where the information came from
- **Metadata** — Information about the search and generation process

[SRS: ABIL-FR-05, ABIL-FR-08, ABIL-DR-04]

### Troubleshooting

**Problem:** "Missing query" error
**Fix:** Include a question in your request

**Problem:** Answer does not seem accurate
**Fix:** Verify your data connection includes the expected content. Try adding a rerank model.

**Problem:** Answer not appearing at all
**Fix:** Check that the Abilities API is active on your WordPress installation. Verify Gregius Data is installed and activated.

[SRS: ABIL-OR-01]

---

## Permissions

| Ability | ID | Who can use it |
|---|---|---|
| AI Site Search | `gregius-data/answer` | Any logged-in user with read access |
| Data Connections | `gregius-data/list-connections` | Administrators only |
| AI Models | `gregius-data/list-models` | Administrators only |

[SRS: ABIL-OR-02, ABIL-OR-03]

---

## Frequently Asked Questions

**What is the WordPress Abilities API?**

A WordPress Core API (WP 6.9+) that registers functional capabilities as machine-discoverable contracts with defined inputs, outputs, and permissions.

**Who can use Gregius Data abilities?**

AI Site Search is available to any logged-in user with read access. Data connections and model listing require administrator privileges.

**Which connection name should I use for AI Site Search?**

Use the `gregius-data/list-connections` ability first to discover valid connection names, then pass the desired name to the `gregius-data/answer` ability.

**Why is my answer not appearing?**

Confirm the Abilities API is active on your WordPress installation, Gregius Data is installed and activated, and at least one data connection and AI model are configured.

**Can I add custom data sources?**

Yes. Gregius Data supports PostgreSQL database connections and Supabase-style REST API connections, configurable through the Data Connections admin panel.

---

## Final Thoughts

Each ability is a discrete contract: discoverable, invocable, and permissioned. When you need to extend what AI agents can do with your content, the Abilities API is where the orchestration begins.

---

## Next Steps

**Local:**
- [Configure Data Connections](#how-to-configure-data-connections)
- [Browse Available AI Models](#how-to-browse-available-ai-models)

**Global:**
- [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/)
- [Gregius Data: Prompts](/docs/prompt/)
- [Gregius Data: Search](/docs/search/)

[SRS: ABIL-FR-01..10] [SRS: ABIL-DR-01..09] [SRS: ABIL-OR-01..04]
