# Search - Enhance WordPress Site Search

[Editorial: Feature: Search | Pages: 1 | Total words: 870 | Sections: 7 (H2: 7, H3: 12, H4: 0) | Screenshots needed: 4 | Doc category: /docs/category/ai-features/ | Audience: Administrator]

## Overview

This feature enables **site administrators** to enhance WordPress search with PostgreSQL-powered retrieval, combining full-text search, typo-tolerant trigram matching, and optional vector-based semantic search through a unified interface that integrates directly with the standard WordPress search experience.

### Prerequisites

- Gregius Data plugin installed and activated
- PostgreSQL connection configured and active
- SQL search functions provisioned (via schema creation)
- Optional: `pg_trgm` extension for typo tolerance, `vector` extension for semantic search

---

## In this page

- [Overview](#overview)
  - [Prerequisites](#prerequisites)
- [How to: Enable Enhanced Search](#how-to-enable-enhanced-search)
  - [Enable Enhanced Search](#enable-enhanced-search)
  - [Choose a Retrieval Mode](#choose-a-retrieval-mode)
  - [Verify Search is Working](#verify-search-is-working)
- [How to: Manage Search Operations](#how-to-manage-search-operations)
  - [Check Health Status](#check-health-status)
  - [Create Search Schema](#create-search-schema)
  - [Adjust Typo Tolerance](#adjust-typo-tolerance)
  - [Manage Search Language](#manage-search-language)
- [How to: Troubleshoot Search Issues](#how-to-troubleshoot-search-issues)
  - [No PostgreSQL Search Results](#no-postgresql-search-results)
  - [Typo Tolerance Not Working](#typo-tolerance-not-working)
  - [Vector Search Not Active](#vector-search-not-active)
  - [Health Status Remains Degraded](#health-status-remains-degraded)
- [Permissions](#permissions)
- [Next Steps](#next-steps)

---

## How to: Enable Enhanced Search

Enhanced search replaces the default WordPress search with PostgreSQL-powered retrieval. Once enabled, all site search queries route through the Gregius Data search engine for improved relevance.

### Enable Enhanced Search

1. Navigate to **Gregius Data > Search** in the WordPress admin.
2. Toggle **Enhanced Search** to enabled.
3. Select the PostgreSQL connection to use for search retrieval.
4. Optionally, select an embedding model if vector search is needed.
5. Save your settings.

[SRS: SEARCH-FR-01, SEARCH-FR-02]

### Choose a Retrieval Mode

Two modes control how search results are composed:

- **Hybrid Default (recommended)** - Combines PostgreSQL results with standard WordPress MySQL search for broader coverage. MySQL fill-in applies automatically when PostgreSQL has gaps in post-type coverage.
- **PostgreSQL Only** - Returns PostgreSQL-native results without MySQL merge. Useful when all searchable content lives in PostgreSQL.

The system always falls back to MySQL internally if PostgreSQL execution fails, regardless of the selected mode.

[SRS: SEARCH-FR-14, SEARCH-FR-15]

### Verify Search is Working

<!-- IMAGE: settings page showing enhanced search toggle and connection selector with saved state -->

1. Perform a test search on your site's frontend.
2. Results should reflect improved relevance ordering.
3. Check search health status at **Gregius Data > Search > Health** to confirm the system is operational.

---

## How to: Manage Search Operations

The search subsystem exposes REST management endpoints for operational control. All management routes require administrator access.

### Check Health Status

The health endpoint at `/wp-json/gg-data/v1/search/health` returns:

- Total search count since tracking began
- Success and failure counters
- Last error message and timestamp
- Latency information
- Degradation state (active or inactive)

Use `POST /wp-json/gg-data/v1/search/health/check` to trigger a fresh health probe and `POST /wp-json/gg-data/v1/search/health/reset` to clear degradation state after the root cause is resolved.

[SRS: SEARCH-FR-09, SEARCH-OR-03]

<!-- IMAGE: health endpoint response in a REST client showing telemetry fields -->

### Create Search Schema

Before PostgreSQL search can execute, the required SQL search functions must be provisioned on the database:

1. Run `POST /wp-json/gg-data/v1/search/schema/create` to create the functions.
2. Check readiness via `GET /wp-json/gg-data/v1/search/status`.

[SRS: SEARCH-FR-08]

### Adjust Typo Tolerance

Typo tolerance uses trigram similarity matching (`pg_trgm`) to find results even when search terms are misspelled.

- Check extension availability: `GET /wp-json/gg-data/v1/search/typo-tolerance-status`
- View current threshold: `GET /wp-json/gg-data/v1/search/typo-tolerance`
- Update threshold: `POST /wp-json/gg-data/v1/search/typo-tolerance`

Short queries where every word is under 4 characters skip typo tolerance automatically for performance and precision.

### Manage Search Language

Search language alignment with the WordPress site locale:

- Check current language: `GET /wp-json/gg-data/v1/search/language-status`
- Update to match site locale: `POST /wp-json/gg-data/v1/search/update-language`

[SRS: SEARCH-FR-10]

---

## How to: Troubleshoot Search Issues

### No PostgreSQL Search Results

- Confirm **Enhanced Search** is enabled in settings.
- Verify the PostgreSQL connection is active and reachable.
- Check schema status via the search status endpoint.
- Validate that synced post types contain searchable data.

### Typo Tolerance Not Working

- Verify `pg_trgm` extension is installed on the PostgreSQL server.
- Check typo tolerance settings and threshold values.
- Ensure the search query is not triggering short-word suppression.

### Vector Search Not Active

- Verify the `vector` extension is present on the PostgreSQL server.
- Confirm the configured embedding model maps to a valid vector table.
- Check that vectors exist in the configured table.
- Review connection capability status for vector readiness.

<!-- IMAGE: troubleshooting checklist in the admin showing status indicators for each capability -->

### Health Status Remains Degraded

1. Run `POST /wp-json/gg-data/v1/search/health/check` and inspect error details.
2. Validate provider-specific credentials and connectivity.
3. Review recent error telemetry in the health payload and plugin logs.
4. Use `POST /wp-json/gg-data/v1/search/health/reset` only after the root cause is addressed.

[SRS: SEARCH-OR-02]

---

## Permissions

| Function | Who can use it |
|---|---|
| Frontend search (enhanced) | All site visitors |
| Search management (health, schema, language, typo tolerance) | Administrators only |

[SRS: SEARCH-OR-01]

---

## Next Steps

**Local:**
- [Enable Enhanced Search](#how-to-enable-enhanced-search)
- [Manage Search Operations](#how-to-manage-search-operations)
- [Troubleshoot Search Issues](#how-to-troubleshoot-search-issues)

**Global:**
- [Gregius Data: Abilities API](/docs/abilities/)
- [Gregius Data: Prompts](/docs/prompt/)
