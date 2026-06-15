# Developer Documentation: Sync Orchestration Subsystem

Standard: ISO/IEC/IEEE 26514:2022  
Component: Sync Orchestration Subsystem  
Version: 1.0.0  
Date: 2026-04-04  
Upstream Architecture: [architecture.md](architecture.md)

---

## 1. Component Overview

### Lifecycle Hooks (`GG_Data_Lifecycle_Hooks`)

Registers WordPress action listeners for post/status/term/meta changes. Filters by:
- Synced post types (from wp_options: `gg_synced_post_types`)
- Enabled statuses (from wp_options: `gg_enabled_statuses`)
- Real-time sync enabled (from wp_options: `gg_realtime_sync_enabled`)

Example:
```php
add_action('save_post', [$lifecycle_hooks, 'on_post_saved']);
```

All errors logged via `error_log()`; never raised to WordPress.

### Batch Processors (`GG_Data_Post_Sync`, `GG_Data_Taxonomy_Sync`)

Implements offset paginated loops for batch operations. Signature:
```php
public function sync_batch(int $batch_size = 100, int $offset = 0): array {
  return [
    'processed' => 42,
    'failed' => 0,
    'has_more' => true,
    'total' => 1250,
  ];
}
```

Retrieve `has_more` to determine whether to paginate; increment offset and re-call.

### Content Cleaner (`GG_Data_Content_Cleaner`)

Accepts post content; returns cleaned text and MD5 hash. Changes detected by hash comparison:
```php
$cleaner = new GG_Data_Content_Cleaner();
$result = $cleaner->clean($post_id);
// $result = ['text' => '...', 'hash' => 'abc123...', 'changed' => true]
```

Invokes chunker internally after cleaning.

Behavior contract:
- Clean-content persistence and chunk persistence are treated as one success unit.
- If chunk generation or chunk write fails, the clean operation returns failure and sync surfaces that failure.
- Hash-match skip paths still support rechunking when chunk drift is detected.

### Sync Service (`GG_Data_Sync_Service`)

Public orchestration facade:
```php
public function upsert(int $post_id, int $site_id): bool { }
public function delete(int $post_id, int $site_id): bool { }
```

Invokes cleaner → chunker → db provider. Returns true if all internal steps succeed.

### Batch Delete Flow (`GG_Data_REST_Sync_Controller::batch_delete_sync`)

Deletes synchronized content from PostgreSQL in a destructive batch loop. This is a maintenance/internal endpoint intended for controlled operator or automation usage, not routine dashboard operations.

The controller intentionally owns the cursor so callers do not have to manage shrinking offsets.

Delete order:
1. `wp_posts_chunks` using `chunk_id`
2. `wp_posts_clean` using `post_id`
3. `wp_posts` using `id`

Behavior:
- Always queries from offset `0` inside each batch.
- Returns `has_more` so API clients can poll until deletion is complete.
- Stops if a batch deletes `0` rows while rows still remain, which prevents infinite polling.
- Requires deliberate invocation because the operation is irreversible for the targeted synchronized rows.

Batch-size extension hooks:
```php
apply_filters( 'gg_data_sync_delete_batch_size', 500, $connection_name );
apply_filters( 'gg_data_sync_delete_batch_size_wp_posts_chunks', 500, $connection_name );
apply_filters( 'gg_data_sync_delete_batch_size_wp_posts_clean', 500, $connection_name );
apply_filters( 'gg_data_sync_delete_batch_size_wp_posts', 500, $connection_name );
```

Use the global filter for a default sync-wide limit and the table-specific filters when one table needs a different batch size.

### Metadata Manager (`GG_Data_Sync_Metadata_Manager`)

Tracks sync state in `wp_gg_sync_metadata` table:
```php
$mgr = new GG_Data_Sync_Metadata_Manager();
$mgr->mark_synced($post_id, $site_id, $chunk_count);
$mgr->get_site_stats($site_id); // [wp_count => 42, pg_count => 41, drift => 1]
```

Used by dashboard and validation components. Atomic with sync operations.

### Validators (`GG_Data_Sync_Validator`)

Fast validation (metadata-based, <500ms):
```php
$validator = new GG_Data_Sync_Validator();
$validator->validate_metadata($site_id);
// Returns: ['missing' => [...], 'orphaned' => [...], 'drift' => 3]
```

Full validation (table scan, 3–13s):
```php
$validator->validate_full($site_id);
```

---

## 2. REST API Routes

### Batch Sync

**POST** `/wp-json/gg-data/v1/sync/post-type/{type}`

Parameters:
- `type` (path): post, page, etc.
- `batch_size` (query, default 100)
- `offset` (query, default 0)

Request body: (none)

Response:
```json
{
  "processed": 42,
  "failed": 0,
  "has_more": true,
  "total": 1250
}
```

Required capability: `manage_options`

### Batch Delete Synced Content (Maintenance/Internal)

**POST** `/wp-json/gg-data/v1/sync/batch-delete`

Use this endpoint only for controlled maintenance/reset workflows. It is intentionally destructive and is not part of the routine sync dashboard flow.

Parameters:
- `connection_name` (required): target connection name
- `batch_size` (optional, default 500): rows requested per batch
- `offset` (optional, default 0): retained for response parity
- `limit` (optional, default 0): optional client-side total tracker

Response:
```json
{
  "success": true,
  "deleted": 1,
  "total_deleted": 1,
  "has_more": true,
  "next_offset": 1,
  "duration_ms": 42,
  "errors": [],
  "limit": 0,
  "table": "wp_posts_chunks",
  "remaining": 12
}
```

Required capability: `manage_options`

### List Configuration

**GET** `/wp-json/gg-data/v1/sync/configuration?connection=default`

Response:
```json
{
  "realtime_enabled": true,
  "synced_post_types": ["post", "page"],
  "enabled_statuses": ["publish", "draft"]
}
```

### Clean Content

**POST** `/wp-json/gg-data/v1/sync/post-type/{type}/clean`

Parameters:
- `type` (path): post type slug (post, page, etc.)
- `connection` (query, default `default`): target connection name

Response:
```json
{
  "text": "Clean post content without HTML",
  "hash": "abc123def456...",
  "chunks": 3
}
```

### Fast Validation (Auto-Loaded by Dashboard)

**GET** `/wp-json/gg-data/v1/sync/validation/fast?connection=default`

This endpoint is called automatically by the sync dashboard on page load. Returns per-post-type and per-entity drift data with health status badges.

Response:
```json
{
  "connection": "default",
  "overall_status": "healthy",
  "validation_time": "45ms",
  "post": {
    "post": {
      "wordpress_count": 42,
      "postgresql_count": 42,
      "drift": 0,
      "drift_percentage": 0,
      "status": "healthy"
    }
  },
  "terms": {
    "wordpress_count": 5,
    "postgresql_count": 5,
    "drift": 0,
    "status": "healthy"
  },
  "term_taxonomy": { "wordpress_count": 3, "postgresql_count": 3, "drift": 0, "status": "healthy" },
  "term_relationships": { "wordpress_count": 10, "postgresql_count": 10, "drift": 0, "status": "healthy" },
  "postmeta": { "wordpress_count": 50, "postgresql_count": 50, "drift": 0, "status": "healthy" }
}
```

### Manual Validation (Operator/Internal)

**POST** `/wp-json/gg-data/v1/sync/validation/run?connection=default`

Warning: This endpoint performs a full table-scan and is not called by the dashboard UI. Intended for operator use or troubleshooting.

Response:
```json
{
  "success": true,
  "mode": "full",
  "missing": [12, 45],
  "orphaned": [67, 89],
  "drift": 2,
  "elapsed_ms": 6200
}
```

---

## 3. Data Contracts

### POST Upsert Payload (Internal)

```json
{
  "post_id": 123,
  "site_id": 1,
  "title": "Post Title",
  "content": "Clean content (HTML stripped)",
  "content_hash": "abc123...",
  "status": "publish",
  "post_type": "post",
  "post_date": "2026-04-04T10:30:00Z",
  "chunks": [
    { "chunk_index": 0, "text": "... chunk 1 ...", "word_count": 150 },
    { "chunk_index": 1, "text": "... chunk 2 ...", "word_count": 142 }
  ]
}
```

### Sync Metadata Row

```
wp_gg_sync_metadata
├─ post_id: integer
├─ site_id: integer
├─ last_synced: timestamp
├─ chunk_count: integer
├─ content_hash: varchar(255)
└─ wp_record_count: integer (for site aggregates)
```

---

## 4. Provider-Specific Behavior

### Supabase/PostgREST (`GG_Data_PostgREST_Provider`)

- **Supports** `bulk_upsert`: Single HTTP request commits up to 1,000 posts in one round-trip.
- **Row-Level Security (RLS)**: Enabled on all tables. Service role (anon key) has unrestricted access if properly configured.
- **Latency**: ~50ms per 100 records (batch); ~5ms per record (real-time).
- **Batch size limit**: 1,000 rows per request. Pagination required for larger datasets.

Example bulk request:
```json
POST /rest/v1/wp_posts_clean?upsert=true
[
  { "post_id": 1, "content": "...", "content_hash": "..." },
  { "post_id": 2, "content": "...", "content_hash": "..." }
]
```

### PostgreSQL/PDO (`GG_Data_PostgreSQL_Provider`)

- **Implements** required bulk provider methods used by sync batch orchestration.
- **Latency**: Depends on deployment topology and database load.
- **Suitable for** self-hosted deployments with collocated database.
- **Contract**: Batch sync relies on strict provider method parity across PDO and PostgREST.

---

## 5. Extension Points

### Filters: Batch Size

```php
apply_filters('gg_data_post_sync_batch_size', 100, $connection_name)
apply_filters('gg_data_term_sync_batch_size', 500, $connection_name)
```

Allows custom batch sizing for post and taxonomy sync flows per connection. Example:
```php
add_filter('gg_data_post_sync_batch_size', function($size, $connection_name) {
  return 'default' === $connection_name ? 75 : $size;
}, 10, 2);

add_filter('gg_data_term_sync_batch_size', function($size, $connection_name) {
  return 'default' === $connection_name ? 300 : $size;
}, 10, 2);
```

### Filter: Batch Delete Size

```php
apply_filters('gg_data_sync_delete_batch_size', 500, $connection_name)
apply_filters('gg_data_sync_delete_batch_size_wp_posts_chunks', 500, $connection_name)
apply_filters('gg_data_sync_delete_batch_size_wp_posts_clean', 500, $connection_name)
apply_filters('gg_data_sync_delete_batch_size_wp_posts', 500, $connection_name)
```

These filters let operators tune destructive batch deletes for direct API or automation workflows without changing cursor handling.

### Filter: Chunking Strategy

```php
apply_filters('gg_data_chunking_strategy', 'default', $connection_name, $context)
```

Supported values: `fixed_size`, `semantic`. Operators can configure via wp-admin settings.

### Action: Chunking Strategy Resolved

```php
do_action('gg_data_chunking_strategy_resolved', $strategy_key, $connection_name, $context)
```

Fired after the chunker resolves the chunking strategy for a connection. Useful for logging or monitoring which strategy was selected.

### Action: Sync Post Types Updated

```php
do_action('gg_data_sync_post_types_updated', $connection_name, $enabled_post_types)
```

Fired when sync configuration is saved. Allows downstream systems to react to changes in synced post types.

---

## 6. Troubleshooting

### Symptom: PostgreSQL has fewer records than WordPress

**Root cause:** Real-time sync errors OR batch operation incomplete.

**Diagnosis:**
1. Run fast validation: `GET /wp-json/gg-data/v1/sync/validation/fast?connection=default`
2. Inspect `missing` IDs; check WordPress posts exist.
3. Review error logs for sync failures.

**Resolution:**
1. Review error log for exceptions.
2. Run batch sync for affected post type: `POST /wp-json/gg-data/v1/sync/post-type/post`
3. Repeat fast validation; confirm drift resolved.

### Symptom: Batch operation times out

**Root cause:** High batch size on PDO provider or slow network.

**Diagnosis:**
1. Check provider: Is it PDO or PostgREST?
2. Reduce batch size: `POST ... ?batch_size=50&offset=0`

**Resolution:**
1. For PDO: Reduce batch size to 50–75.
2. For PostgREST: Increase batch size; network likely the bottleneck.

### Symptom: Batch delete repeats the same row or stalls

**Root cause:** A destructive delete was paginated from a shrinking client-side offset instead of letting the controller manage the cursor.

**Diagnosis:**
1. Confirm the direct API or automation request is going to `/wp-json/gg-data/v1/sync/batch-delete`.
2. Check the response for `has_more`, `deleted`, and `remaining`.
3. Inspect any custom batch-size filters that may be forcing a zero or tiny batch.

**Resolution:**
1. Keep the client loop simple and trust the controller's `has_more` value.
2. Adjust the batch-size filters only for throughput tuning.
3. If the controller returns zero deletions while rows remain, verify provider permissions and row-level filters.

### Symptom: Multisite shows drift per-site

**Root cause:** Site ID mismatch in metadata OR sync ran with wrong site context.

**Diagnosis:**
1. Confirm `wp_gg_sync_metadata.site_id` matches request site_id.
2. Check wp-cli: `wp option get gg_enabled_sites` across all sites.

**Resolution:**
1. Ensure sync endpoints are called with correct `site_id` query parameter.
2. Run per-site full validation to reset metadata.

---

## 7. Integration Guide

### Consume Synced Content (Search Indexing)

The sync subsystem does not currently fire a per-post sync-complete action. To react to synced content, use the `gg_data_sync_post_types_updated` action which fires when sync configuration changes, or poll sync metadata for changes.

### Consume Synced Chunks (Vector Embedding)

Query wp_posts_chunks directly:
```php
$chunks = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT chunk_index, text FROM wp_posts_chunks WHERE post_id = %d",
    $post_id
  )
);
```

Always filter by site_id in multisite contexts.

