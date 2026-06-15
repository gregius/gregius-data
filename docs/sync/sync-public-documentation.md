# Sync — WordPress Content Synchronization

[Editorial: Feature: Sync | Pages: 1 | Total words: 880 | Sections: 8 (H2: 8, H3: 10, H4: 0) | Screenshots needed: 2 | Doc category: /docs/category/core-features/ | Audience: Administrator]

## Overview

The sync subsystem keeps your [PostgreSQL](https://www.postgresql.org/) database in sync with WordPress content — posts types, metadata, and taxonomies — in two complementary modes:

- **Real-time sync** — triggered automatically by WordPress lifecycle hooks (`save_post`, `transition_post_status`, etc.). Runs asynchronously and never blocks your WordPress admin.
- **Batch sync** — REST-initiated bulk operations with offset pagination for initial imports, recovery after downtime, or large-scale content changes.

Both modes route through the same content pipeline: HTML cleaning, MD5 hash change detection, chunking, and provider dispatch (PDO or PostgREST).

[SRS: SYNC-FR-01, SYNC-FR-03]

### Prerequisites

- Gregius Data plugin installed and activated
- At least one database connection configured ([PostgreSQL](https://www.postgresql.org/) via PDO or [PostgREST](https://postgrest.org/) via [Supabase](https://supabase.com/))
- Synced content types and statuses configured in Gregius Data settings

[SRS: SYNC-FR-08]

---

## In this page

- [Overview](#overview)
  - [Prerequisites](#prerequisites)
- [How Sync Works](#how-sync-works)
  - [What Gets Synced](#what-gets-synced)
- [Real-Time Sync](#real-time-sync)
- [Batch Sync](#batch-sync)
- [Content Preparation](#content-preparation)
  - [HTML Cleaning](#html-cleaning)
  - [Chunking](#chunking)
- [Sync Status Monitoring](#sync-status-monitoring)
- [How to: Run a Batch Sync](#how-to-run-a-batch-sync)
- [How to: Check Sync Status](#how-to-check-sync-status)
- [Permissions](#permissions)
- [Next Steps](#next-steps)

---

## How Sync Works

A sync operation flows through a predictable pipeline regardless of whether it was triggered in real-time or via batch:

1. **Trigger** — WordPress action or REST API call identifies content to sync.
2. **Content Cleaner** — Strips HTML, calculates an MD5 content hash, and detects whether content changed since the last sync. Unchanged content is skipped.
3. **Chunker** — Segments cleaned content into chunks per connection-specific strategy (fixed-size or semantic).
4. **Provider Dispatch** — Routes cleaned content and chunks to the configured provider (PDO or PostgREST), which writes to PostgreSQL mirror tables (`wp_posts`, `wp_posts_clean`, `wp_posts_chunks`).
5. **Metadata Update** — Records sync state in `wp_gg_sync_metadata` for fast validation without table scans.

[SRS: SYNC-FR-04, SYNC-DR-04, SYNC-DR-05]

### What Gets Synced

| Entity | Details |
|---|---|
| **Posts types** | Title, content, status, dates, excerpt |
| **Post metadata** | Custom fields associated with synced posts |
| **Taxonomies and terms** | Categories, tags, and custom taxonomy assignments |

You can control which post types and statuses are synced per connection through the Gregius Data settings.

[SRS: SYNC-FR-08]

---

## Real-Time Sync

Real-time sync activates automatically when WordPress content changes. It is designed to be **non-blocking** — all errors are caught and logged without interrupting your WordPress save operation.

**What triggers it:**
- Publishing, updating, or trashing a post or page
- Changing post status (draft → publish, publish → draft)
- Updating post metadata or taxonomy relationships

**Configuration options:**
- Enable or disable real-time sync per connection
- Choose which post types to sync (post, page, custom post types)
- Choose which statuses trigger sync (publish, draft, etc.)

Real-time sync is ideal for keeping PostgreSQL up to date during normal editorial workflows. For bulk operations, use batch sync.

[SRS: SYNC-FR-01, SYNC-FR-02, SYNC-OR-01]

---

## Batch Sync

Batch sync lets you synchronize large volumes of content on demand via REST API. It uses offset-based pagination and returns a `has_more` flag so you can iterate until all content is processed.

**When to use batch sync:**
- Initial content import after setting up a new connection
- Recovery after network outages or provider errors
- Large-scale content migrations or re-syncs
- Periodic full synchronization for data assurance

**REST endpoint:** `POST /wp-json/gg-data/v1/sync/post-type/{type}`

**Response format:**
```json
{
  "processed": 42,
  "failed": 0,
  "has_more": true,
  "total": 1250
}
```

Continue calling with incremented `offset` until `has_more` is `false`. The operation is resumable — you can stop and restart without data loss.

**Provider behavior:**
- [PostgREST/Supabase](https://supabase.com/): Supports bulk upsert — up to 1,000 posts per request
- [PostgreSQL PDO](https://www.postgresql.org/): Implements bulk provider methods; lower batch sizes (50–75) recommended for performance

[SRS: SYNC-FR-03, SYNC-FR-05, SYNC-FR-09, SYNC-OR-05]

---

## Content Preparation

Before content reaches PostgreSQL, it goes through automatic preparation to make it suitable for search and vector workflows.

### HTML Cleaning

The content cleaner strips HTML tags, calculates an MD5 hash of the cleaned content, and compares it with the previously stored hash. If nothing changed, the sync skips the content entirely — avoiding redundant writes.

**Why this matters:** Clean content improves search relevance and vector embedding quality. The hash-based skip prevents unnecessary database work for unchanged content.

[SRS: SYNC-OR-04, SYNC-DR-04]

### Chunking

After cleaning, the chunker segments content into smaller pieces based on the connection's chunking strategy:

| Strategy | Behavior |
|---|---|
| **Fixed-size** | Splits content into chunks of equal token or character count |
| **Semantic** | Splits at natural boundaries (paragraphs, sentences) |

Chunking strategy is configurable per connection and per post type. If you change the strategy, existing content needs to be re-chunked — you can trigger this via a manual batch sync.

Chunks are stored in `wp_posts_chunks` and consumed downstream by the Search and Vector subsystems.

[SRS: SYNC-FR-04, SYNC-FR-11, SYNC-DR-05]

---

## Sync Status Monitoring

The sync dashboard displays drift automatically — no manual validation step required. When you navigate to **Gregius Data > Sync**, the dashboard loads drift data for every entity type and post type in parallel.

**What the dashboard shows:**

| Entity | Data displayed |
|---|---|
| **Posts** (per post type) | WordPress count, PostgreSQL count, drift percentage, health status badge |
| **Terms** | Total term count comparison, drift percentage, health status badge |
| **Term taxonomies** | Count comparison, drift percentage, health status badge |
| **Term relationships** | Count comparison, drift percentage, health status badge |
| **Post metadata** | Count comparison, drift percentage, health status badge |

Each entity type receives a **health status badge**:

| Badge | Meaning |
|---|---|
| **Healthy** | WordPress and PostgreSQL counts match (drift = 0%) |
| **Warning** | Small drift detected (less than 5%) — review if persistent |
| **Critical** | Significant drift detected (5% or more) — run a batch sync |

The overall connection status reflects the worst status across all entities. Drift data refreshes automatically when you toggle post types, change sync configuration, or switch connections.

[SRS: SYNC-FR-06, SYNC-DR-03]

---

## How to: Run a Batch Sync

1. Navigate to **Gregius Data > Sync** in the WordPress admin.
2. Select the connection you want to sync.
3. Choose the post type (e.g., Posts, Pages) or select all.
4. Click **Start Batch Sync**.
5. Monitor progress — the dashboard displays processed, failed, and remaining counts.
6. Once complete, confirm drift has resolved by reviewing the health status badges on the dashboard.

> **Tip:** For very large sites (100K+ posts), batch sync handles pagination automatically. You can pause and resume without data loss.

[SRS: SYNC-FR-09, SYNC-QR-02]

---

## How to: Check Sync Status

1. Go to **Gregius Data > Sync** in the WordPress admin.
2. The dashboard automatically loads drift data for all entity types (posts, terms, postmeta, taxonomies, relationships).
3. Review the **health status badge** and **drift percentage** for each post type and entity.
4. Switch connections using the connection selector to inspect each one.
5. If drift is detected, run a batch sync for the affected post types by clicking **Sync**.

The dashboard also highlights connections with stale or missing sync data with a warning badge so you can act quickly.

[SRS: SYNC-FR-06]

---

## Permissions

| Function | Who can use it |
|---|---|
| View sync status and metadata | Administrators only |
| Run batch sync operations | Administrators only |
| Access validation data (auto-loaded) | Administrators only |
| Change sync configuration (post types, statuses) | Administrators only |
| Delete synced content from PostgreSQL | Administrators only |

All sync operations require the `manage_options` capability.

[SRS: SYNC-OR-02]

---

## Next Steps

**Local:**
- [Real-Time Sync](#real-time-sync)
- [Batch Sync](#batch-sync)
- [Content Preparation](#content-preparation)

**Global:**
- [Gregius Data: Vectors](/docs/vectors/)
- [Gregius Data: Search](/docs/search/)
- [Gregius Data: Providers](/docs/providers/)
