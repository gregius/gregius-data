# Abilities API - Expose Engine Capabilities

[Editorial: Feature: Abilities API | Pages: 1 | Total words: 991 | Sections: 8 (H2: 7, H3: 11, H4: 0) | Screenshots needed: 3 | Doc category: /docs/category/ai-features/ | Audience: Administrator]

## Overview

This feature enables **site administrators** to expose Gregius Data engine capabilities as registered Abilities through the WordPress Abilities API (WP 6.9+). Gregius Data registers its RAG pipeline, data connections, and model inventory as machine-discoverable tool contracts that AI agents, automation tools, and authenticated users can discover and execute through a standardized interface.

[SRS: ABIL-FR-03, ABIL-FR-04, ABIL-FR-05]

### Prerequisites

- WordPress 6.9+ with Gregius Data plugin installed and activated
- At least one AI model registered and active on the site
- At least one data connection configured and active

---

## In this page

- [Overview](#overview)
  - [Prerequisites](#prerequisites)
- [Understanding Abilities](#understanding-abilities)
- [How to: Configure Data Connections](#how-to-configure-data-connections)
  - [View connections](#view-connections)
  - [Get optional details](#get-optional-details)
  - [Tips](#tips-connections)
- [How to: Browse Available AI Models](#how-to-browse-available-ai-models)
  - [View all models](#view-all-models)
  - [Filter by type](#filter-by-type)
  - [Tips](#tips-models)
- [How to: Use AI Site Search](#how-to-use-ai-site-search)
  - [What you need](#what-you-need)
  - [Required inputs](#required-inputs)
  - [What you get back](#what-you-get-back)
  - [Troubleshooting](#troubleshooting)
- [Permissions](#permissions)
- [Next Steps](#next-steps)

---

## Understanding Abilities

The WordPress Abilities API (WP 6.9+) is a WordPress Core API that provides a central registry for functional capabilities. Each Ability is a discrete unit of functionality with a unique namespace/name, defined inputs and outputs (JSON Schema), a permission callback, and a category assignment. All registered Abilities are discoverable via `GET /wp-json/wp-abilities/v1/abilities`.

Gregius Data registers three abilities that expose its engine capabilities:

| Ability | Description | Category | Permission |
|---|---|---|---|
| `gregius-data/answer` | Searches site content via the RAG pipeline and returns an answer with sources | ai | `read` |
| `gregius-data/list-connections` | Returns configured data connections with optional embedding model context | ai | `manage_options` |
| `gregius-data/list-models` | Returns registered AI models with optional type filtering | ai | `manage_options` |

WordPress Core ships additional abilities in WP 6.9 such as `core/get-site-info`, `core/get-user-info`, and `core/get-environment-info`. All abilities follow the same contract pattern and are discoverable through the same REST endpoint.

[SRS: ABIL-FR-01, ABIL-FR-02]

---

## How to: Configure Data Connections

Data Connections tell the AI which data sources it can search. Each connection links to a database or API. This corresponds to the `gregius-data/list-connections` ability.

<!-- IMAGE: admin settings page showing the connections list with name, type, description, and active/inactive status badges -->

### View connections

When you list your connections, each one shows:
- **Name** - A label identifying the connection
- **Type** - The data source type (PostgreSQL database or Supabase-style REST API)
- **Description** - What data this connection provides
- **Active status** - Whether the connection is currently enabled

### Get optional details

You can request additional information per connection:
- **Embedding model overview** - Shows which embedding model keys are active and how many
- **Full model details** - Shows model ID, type, provider, label, active status, and optional dimensions or description

[SRS: ABIL-FR-09, ABIL-DR-08, ABIL-DR-09]

### Tips

- Use this section to find valid connection names before using AI Site Search
- Connections can be database-backed (PostgreSQL) or API-backed (Supabase-style REST)

---

## How to: Browse Available AI Models

AI Models are the engines that power search, answers, and relevance ranking. Different model types handle different tasks. This corresponds to the `gregius-data/list-models` ability.

<!-- IMAGE: models list page showing type filter dropdown and model cards with ID, type, provider, status -->

### View all models

Each model displays:
- **ID** - Unique identifier
- **Type** - What it's used for (embeddings, LLM, rerank)
- **Provider** - Where the model comes from
- **Label** - Display name
- **Active status** - Whether the model is enabled
- **Description** - What it does (if available)
- **Dimensions** - Technical reference (if applicable)

### Filter by type

You can narrow the list:
- **embeddings** - Convert text into searchable vectors
- **llm** - Language models that generate answers
- **rerank** - Improve result relevance

[SRS: ABIL-FR-10, ABIL-DR-05]

### Tips

- Use this section to find valid model values before using AI Site Search
- Models are listed from your global registry, not per-connection storage

---

## How to: Use AI Site Search

Ask a question about your site's content through the `gregius-data/answer` ability, which delegates to the Gregius Data RAG pipeline. The AI searches your connected data sources and returns an answer with supporting references.

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

- **Answer** - A generated response to your question
- **Sources** - References showing where the information came from
- **Metadata** - Information about the search and generation process

[SRS: ABIL-FR-05, ABIL-FR-08, ABIL-DR-04]

### Troubleshooting

**Problem:** "Missing query" error
- **Fix:** Include a question in your request

**Problem:** Answer doesn't seem accurate
- **Fix:** Verify your data connection includes the expected content. Try adding a rerank model.

**Problem:** Answer not appearing at all
- **Fix:** Check that the Abilities API is active on your WordPress installation. Verify Gregius Data is installed and activated.

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

## Next Steps

**Local:**
- [Configure Data Connections](#how-to-configure-data-connections)
- [Browse Available AI Models](#how-to-browse-available-ai-models)

**Global:**
- [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/)
- [Gregius Data: Prompts](/docs/prompt/)
- [Gregius Data: Search](/docs/search/)

[SRS: ABIL-FR-01..10] [SRS: ABIL-DR-01..09] [SRS: ABIL-OR-01..04]
