# Developer Documentation: Sync Orchestration Subsystem

Standard: ISO/IEC/IEEE 26514:2022  
Component: Sync Orchestration Subsystem  
Version: 1.0.3  
Date: 2026-04-04  
Upstream Architecture: [architecture.md](architecture.md)

---

## 1. Component Overview

### Lifecycle Hooks (`Gregius_Data_Lifecycle_Hooks`)

Source: `includes/hooks/class-gregius-data-lifecycle-hooks.php`

Registers WordPress action listeners for real-time post/meta/term synchronization. Constructor:

```php
public function __construct( $connection_manager, $settings_manager ) { }
```

Registered WordPress hooks:

| WordPress hook | Registered as | Handler |
|---|---|---|
| `save_post` | action | `handle_post_save( $post_id, $post, $update )` |
| `transition_post_status` | action | `handle_post_status_transition( $new_status, $old_status, $post )` |
| `before_delete_post` | action | `handle_post_deletion( $post_id, $post )` |
| `added_post_meta` / `updated_post_meta` | action | `handle_post_meta_added` / `handle_post_meta_updated` |
| `deleted_post_meta` | action | `handle_post_meta_deleted` |
| `created_term` / `edited_term` / `delete_term` | action | `handle_term_created` / `handle_term_edited` / `handle_term_deleted` |
| `wp_after_insert_post` | action | `handle_post_complete` |
| `add_attachment` / `attachment_updated` | action | `handle_attachment_added` / `handle_attachment_updated` |
| `wp_generate_attachment_metadata` | filter | `handle_attachment_metadata_generated` |
| `gregius_data_sync_post_types_changed` | action | `schedule_attachment_reconcile( $connection_name, $added_types, $removed_types )` |
| `gregius_data_reconcile_attachment_children` | action | `reconcile_attachment_children( $connection_name, $added_types, $removed_types )` |
| `gregius_data_orphan_cleanup` | action | `cleanup_orphaned_records` |
| `shutdown` | action | `flush_pending_stats_refresh` |

Configuration is read per connection from the sync settings category:

- Enabled post types — setting key `sync_enabled_post_types` (via `Gregius_Data_Settings_Manager::get_with_category( 'sync', $connection_name, 'sync_enabled_post_types', … )`).
- Enabled statuses — setting key `sync_enabled_statuses`.
- Real-time toggle — setting key `sync_real_time_sync`, falling back to the `gregius_data_sync_real_time_sync` option (default `true`).

All errors are caught and logged via `Gregius_Data_Logger`; they are never raised to WordPress.

### Legacy Sync Hooks (`Gregius_Data_Sync_Hooks`)

Source: `includes/sync/class-gregius-data-sync-hooks.php`

A legacy, logging-only class. It registers only `before_delete_post` and `wp_trash_post` and records debug/info log entries (via `on_delete_post` / `on_trash_post`); it does not perform synchronization. It also exposes `get_processing_status()`, which reports publish-count progress against the vector table. Real-time synchronization is performed by `Gregius_Data_Lifecycle_Hooks` above.

### Sync Service (`Gregius_Data_Sync_Service`)

Source: `includes/class-gregius-data-sync-service.php`

A facade that owns the `connection_name` and dispatches batch operations to the batch workers:

```php
public function __construct( $connection_name ) { }

public function batch_sync_post_type( $post_type, $batch_size, $offset ) { }
public function batch_sync_postmeta( $batch_size, $offset ) { }
public function batch_sync_terms( $batch_size, $offset ) { }
public function batch_sync_term_taxonomies( $batch_size, $offset ) { }
public function batch_sync_term_relationships( $batch_size, $offset ) { }

public function sync_taxonomy_full() { }
public function sync_taxonomy_terms_only() { }
public function sync_taxonomy_taxonomies_only() { }
public function sync_taxonomy_relationships_only() { }

public function sync_postmeta_bulk( $batch_size ) { }        // Legacy
public function clean_post_type( $post_type, $batch_size, $offset = 0 ) { }
public function delete_orphans( $type, $batch_size, $offset = 0, $args = array() ) { }
public function validate_sync() { }
```

Each batch method delegates to the corresponding worker (see below) and returns that worker's result array. The `*_only` / `sync_taxonomy_full` / `sync_postmeta_bulk` methods are legacy/debug entry points that wrap the taxonomy/postmeta workers.

### Batch Processors

- `Gregius_Data_Post_Sync` — `includes/batch/class-gregius-data-post-sync.php`

  ```php
  public function batch_sync_post_type( $post_type, $batch_size = 100, $offset = 0 ) { }
  ```

  Returns `array{ success, processed, skipped, failed, has_more, total }`. Validates the provider contract (`bulk_upsert_posts`, `bulk_upsert_postmeta`, `bulk_upsert_term_relationships`), then bulk-upserts posts, postmeta, and term relationships, cleans content, and marks each post synced.

- `Gregius_Data_Postmeta_Sync` — `includes/batch/class-gregius-data-postmeta-sync.php`

  ```php
  public function batch_sync_postmeta( $batch_size = 5000, $offset = 0 ) { }
  ```

  Syncs postmeta with smart scoping (only for posts that exist in PostgreSQL and match enabled post types/statuses).

- `Gregius_Data_Taxonomy_Sync` — `includes/batch/class-gregius-data-taxonomy-sync.php`

  ```php
  public function batch_sync_terms( $batch_size = 500, $offset = 0 ) { }
  public function batch_sync_term_taxonomies( $batch_size = 500, $offset = 0 ) { }
  public function batch_sync_term_relationships( $batch_size = 500, $offset = 0 ) { }
  public function full_sync() { }
  public function sync_terms() { }
  public function sync_term_taxonomies() { }
  public function sync_term_relationships() { }
  public function sync_single_term( $term_id, $taxonomy ) { }
  public function sync_post_term_relationships( $post_id, $taxonomy ) { }
  ```

  Synchronizes terms, term taxonomies, and term relationships (in that order) and validates the provider contract (`bulk_upsert_terms`, `bulk_upsert_term_taxonomies`, `bulk_upsert_term_relationships`).

- `Gregius_Data_Clean_Batch` — `includes/batch/class-gregius-data-clean-batch.php`

  ```php
  public function batch_clean_post_type( $post_type, $batch_size = 100, $offset = 0 ) { }
  ```

  Cleans content for posts of a post type that have no `wp_posts_clean` row yet.

### Content Cleaner (`Gregius_Data_Content_Cleaner`)

Source: `includes/class-gregius-data-content-cleaner.php`

```php
public function clean_post( $post_id, $title, $content, $excerpt, $connection_name = 'default', $force = false ) { }
```

Returns `bool`. Resolves the content source (attachments extract text from the file), strips HTML via `Gregius_Data_Content_Processor`, computes an MD5 `content_hash`, and stores the clean row in `wp_posts_clean`. It then delegates chunking to `Gregius_Data_Chunker::process_post()`.

Change detection: `needs_cleaning()` compares `content_hash` and `cleaning_version` (`Gregius_Data_Content_Cleaner::CLEANING_VERSION`, currently `1.0`) against the stored row. When the hash matches but chunks have drifted, `process_post()` regenerates chunks (see `needs_rechunking()`).

Behavior contract:
- Clean-content persistence and chunk persistence are treated as one success unit; if chunk generation or chunk write fails, `clean_post()` returns `false` and the caller surfaces that failure.
- Hash-match skip paths still support rechunking when chunk drift is detected.

### Chunker (`Gregius_Data_Chunker`)

Source: `includes/class-gregius-data-chunker.php`

```php
public function chunk_content( $content, $target_tokens = 512, $connection_name = 'default', $context = array() ) { }
public function process_post( $post_id, $content, $content_hash, $connection_name = 'default', $force = false ) { }
public function estimate_tokens( $text ) { }
```

The only registered strategy is `default` (`DEFAULT_CHUNK_SIZE` = 512 tokens, `MIN_CHUNK_SIZE` = 100 tokens, `CHARS_PER_TOKEN` = 4.0). It splits paragraph-first, then sentence-aware, then fixed-size on word boundaries. Additional strategies can be registered via the `gregius_data_chunking_strategies` filter; the active strategy is selected via the `gregius_data_chunking_strategy` filter. Chunks are written to `wp_posts_chunks`.

### Metadata Manager (`Gregius_Data_Sync_Metadata_Manager`)

Source: `includes/class-gregius-data-sync-metadata-manager.php`

```php
public function __construct( $connection_name = 'default' ) { }

public function track_synced( $entity_type, $entity_id, $extra_data = array() ) { }
public function mark_synced_in_postgresql( $entity_type, $entity_id, $extra_data = array() ) { }
public function remove_metadata( $entity_type, $entity_id ) { }
public function is_entity_synced_in_postgresql( $entity_type, $entity_id ) { }
public function get_entity_metadata( $entity_type, $entity_id ) { }
public function needs_resync( $entity_type, $entity_id, $source_modified_at = null ) { }
public function update_sync_stats( $entity_type, $post_type = null ) { }
public function get_validation_summary() { }
public function get_aggregate_stats() { }
public function get_orphaned_post_types_from_metadata( $enabled_post_types, $connection_name ) { }
public function delete_connection_metadata( $connection_name ) { }

public static function get_skipped_meta_keys() { }
public static function get_allowed_underscore_meta_keys() { }
```

Maintains two tables (see [Data Contracts](#3-data-contracts)): the MySQL aggregate table `wp_gregius_data_sync_metadata` (what *should* sync) and the PostgreSQL entity table `wp_gregius_data_sync_metadata_entities` (what *has* synced). `get_skipped_meta_keys()` is filterable via `gregius_data_skip_meta_keys`.

### Validator (`Gregius_Data_Sync_Validator`)

Source: `includes/class-gregius-data-sync-validator.php`

```php
public function run_validation( $connection_name = null ) { }
public function get_validation_results( $connection_name = null ) { }
public function reset_validation( $connection_name = null ) { }
```

`run_validation()` performs a full row-count comparison for posts (per post type), postmeta, terms, term taxonomies, and term relationships, and computes an `overall_status` of `healthy`, `warning`, `degraded`, `critical`, or `error`. Results are stored in the `gregius_data_validation_results_{connection}` option. A `critical` result emails a drift alert and an `error` result emails a health alert (each throttled to once per 24 hours per connection).

Fast validation is provided by the REST controller (`Gregius_Data_REST_Sync_Validator_Controller::get_fast_validation()`), which reads the precomputed metadata aggregate rather than scanning tables.

### Batch Delete Flow (`Gregius_Data_REST_Sync_Controller::batch_delete_sync`)

Deletes synchronized content from PostgreSQL in a destructive batch loop. This is a maintenance/internal endpoint intended for controlled operator or automation usage, not routine dashboard operations.

The controller owns the cursor so callers do not manage shrinking offsets. Delete order:

1. `wp_posts_chunks` using `chunk_id`
2. `wp_posts_clean` using `post_id`
3. `wp_posts` using `id`

Behavior:
- Always queries from offset `0` inside each batch.
- Returns `has_more` so API clients can poll until deletion is complete.
- Stops if a batch deletes `0` rows while rows still remain, preventing infinite polling.
- Requires deliberate invocation because the operation is irreversible for the targeted synchronized rows.

Batch-size extension hooks: `gregius_data_sync_delete_batch_size` (global) plus per-table variants `gregius_data_sync_delete_batch_size_wp_posts_chunks`, `..._wp_posts_clean`, and `..._wp_posts`. Use the global filter for a default sync-wide limit and the table-specific filters when one table needs a different batch size.

---

## 2. REST API Routes

Namespace: `gregius-data/v1`. All routes require the `manage_options` capability.

### Sync management (`Gregius_Data_REST_Sync_Controller` — base `sync`)

| Method | Route | Purpose |
|---|---|---|
| GET | `/sync/post-types` | List available post types and statuses |
| GET | `/sync/configuration` | Read sync configuration (`?connection=` default `default`) |
| POST | `/sync/configuration` | Save sync configuration (`enabled_post_types`, `enabled_statuses`, `real_time_sync`, `sync_meta`, `sync_terms`, `document_model`) |
| GET | `/sync/status` | Read sync status/progress (`?connection=`) |
| POST | `/sync/taxonomy/bulk` | Full taxonomy sync |
| POST | `/sync/taxonomy/terms` | Terms-only sync (debug) |
| POST | `/sync/taxonomy/taxonomies` | Term-taxonomies-only sync (debug) |
| POST | `/sync/taxonomy/relationships` | Term-relationships-only sync (debug) |
| GET | `/sync/taxonomy/validation` | Taxonomy validation status |
| POST | `/sync/post-type/{type}/clean` | Clean content for a post type |
| DELETE | `/sync/post-type/{type}/orphans` | Delete orphan posts (`preview` flag supported) |
| DELETE | `/sync/postmeta/orphans` | Delete orphan postmeta |
| DELETE | `/sync/term-relationships/orphans` | Delete orphan term relationships |
| DELETE | `/sync/term-taxonomy/orphans` | Delete orphan term taxonomies |
| DELETE | `/sync/terms/orphans` | Delete orphan terms |
| POST | `/sync/batch-sync-postmeta` | Batch postmeta sync (`batch_size` default 5000) |
| POST | `/sync/postmeta` | Legacy single-request postmeta sync |
| POST | `/sync/batch-sync-post-type/{type}` | Batch post sync (`batch_size` default 100) |
| POST | `/sync/batch-sync-terms` | Batch terms sync (`batch_size` default 500) |
| POST | `/sync/batch-sync-term-taxonomies` | Batch term-taxonomies sync |
| POST | `/sync/batch-sync-term-relationships` | Batch term-relationships sync |
| POST | `/sync/batch-delete` | Destructive batch delete (`connection_name` required; `batch_size` default 500) |

Batch sync response shape (post/terms/taxonomies/relationships/postmeta):

```json
{
  "success": true,
  "message": "Processed 42 posts",
  "batch": {
    "success": true,
    "processed": 42,
    "skipped": 0,
    "failed": 0,
    "has_more": true,
    "total": 1250
  }
}
```

Batch delete response shape:

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

List configuration response shape:

```json
{
  "success": true,
  "connection": "default",
  "configuration": {
    "enabled_post_types": ["post", "page"],
    "enabled_statuses": ["publish", "draft"],
    "real_time_sync": true,
    "sync_meta": true,
    "sync_terms": true,
    "last_updated": "2026-04-04 10:30:00",
    "document_model": null,
    "document_model_available": false
  }
}
```

### Sync validation (`Gregius_Data_REST_Sync_Validator_Controller` — base `sync/validation`)

| Method | Route | Purpose |
|---|---|---|
| GET | `/sync/validation` | Read stored validation results |
| POST | `/sync/validation/run` | Run a manual full validation |
| POST | `/sync/validation/reset` | Reset stored validation data |
| GET | `/sync/validation/fast` | Fast metadata-based validation (`?connection=` default `default`) |

Fast validation returns per-entity and per-post-type `wordpress_count`, `postgresql_count`, `drift`, `drift_percentage`, and `status`, plus an `overall_status` of `healthy`, `warning`, or `critical`.

---

## 3. Data Contracts

### PostgreSQL mirror tables (canonical prefix `wp_`)

- `wp_posts` — WordPress post mirror (full post row; upserted via `provider->sync_post()` or `bulk_upsert_posts()`).
- `wp_posts_clean` — cleaned content. Columns: `post_id`, `post_title_clean`, `post_content_clean`, `post_excerpt_clean`, `metadata_manifest`, `cleaned_at`, `content_hash`, `cleaning_version`, `word_count`, `reading_time_minutes`.
- `wp_posts_chunks` — chunks. Columns: `post_id`, `chunk_index`, `chunk_text`, `chunk_hash`, `source_hash`, `token_count`.
- `wp_postmeta`, `wp_terms`, `wp_term_taxonomy`, `wp_term_relationships` — WordPress term/meta mirrors.

Mirror table names are canonical (do not vary by site prefix); `Gregius_Data_Table_Prefix_Resolver::runtime_prefix()` returns `wp_`.

### Sync metadata — MySQL aggregate (`wp_gregius_data_sync_metadata`)

Tracks what *should* be synced, keyed by `connection_name` + `entity_type` (+ `post_type` for posts):

```
wp_gregius_data_sync_metadata
├─ id: integer
├─ connection_name: varchar
├─ entity_type: varchar  (post | postmeta | term | term_taxonomy | term_relationships)
├─ post_type: varchar (nullable)
├─ wp_count: integer
├─ pg_count: integer
├─ drift: integer
├─ last_sync_at: datetime
├─ sync_status: varchar (e.g. 'completed')
└─ enabled: boolean
```

### Sync metadata — PostgreSQL entity table (`wp_gregius_data_sync_metadata_entities`)

Tracks individual entities that *have* been synced:

```
wp_gregius_data_sync_metadata_entities
├─ id: bigserial (primary key)
├─ connection_name: varchar
├─ entity_type: varchar
├─ entity_id: varchar
├─ source_id: bigint (nullable)
├─ post_type: varchar (nullable)
├─ source_modified_at: timestamp (nullable)
├─ synced_at: timestamp
├─ created_at: timestamp
└─ updated_at: timestamp
```

---

## 4. Provider-Specific Behavior

### Supabase/PostgREST (`Gregius_Data_PostgREST_Provider`)

- **Bulk upsert**: `bulk_upsert_posts()` issues a single `POST /rest/v1/wp_posts` with `Prefer: resolution=merge-duplicates`. Batch size is caller-controlled (post batch default 100, terms default 500, postmeta default 5000); the plugin does not hardcode a 1,000-row ceiling.
- **Row-Level Security (RLS)**: writes and deletes use the service-role key to bypass RLS (see `get_headers( true )`).
- Implements the same `bulk_upsert_*` and `sync_post()` contract methods as the PDO provider.

### PostgreSQL/PDO (`Gregius_Data_PostgreSQL_Provider`)

- Implements the same `bulk_upsert_posts()`, `bulk_upsert_postmeta()`, `bulk_upsert_terms()`, `bulk_upsert_term_taxonomies()`, `bulk_upsert_term_relationships()`, and `sync_post()` contract methods directly against PDO.
- Batch sync relies on strict provider method parity across PDO and PostgREST; missing contract methods fail fast.

---

## 5. Extension Points

### Filters: batch size

```php
apply_filters( 'gregius_data_post_sync_batch_size', 100, $connection_name )
apply_filters( 'gregius_data_term_sync_batch_size', 500, $connection_name )
apply_filters( 'gregius_data_clean_batch_size', 100, $post_type, $connection_name )
```

Allows custom batch sizing per connection. Example:

```php
add_filter( 'gregius_data_post_sync_batch_size', function( $size, $connection_name ) {
  return 'default' === $connection_name ? 75 : $size;
}, 10, 2 );
```

### Filter: batch delete size

`gregius_data_sync_delete_batch_size` (global) plus per-table variants `gregius_data_sync_delete_batch_size_wp_posts_chunks`, `..._wp_posts_clean`, and `..._wp_posts`. These let operators tune destructive batch deletes without changing cursor handling.

### Filter: chunking strategies

```php
apply_filters( 'gregius_data_chunking_strategies', $strategies, $connection_name )
```

Register additional chunking strategies keyed by strategy key.

### Filter: active chunking strategy

```php
apply_filters( 'gregius_data_chunking_strategy', 'default', $connection_name, $context )
```

Selects the active strategy key. The built-in value is `default` (paragraph-first, sentence-aware, fixed-size fallback).

### Action: chunking strategy resolved

```php
do_action( 'gregius_data_chunking_strategy_resolved', $strategy_key, $connection_name, $context )
```

Fired after the chunker resolves the strategy for a connection.

### Filter: skip meta keys

```php
apply_filters( 'gregius_data_skip_meta_keys', $skip_keys )
```

Filters the postmeta keys excluded from sync and aggregate counts.

### Filter: should sync post

```php
apply_filters( 'gregius_data_should_sync_post', true, $post_id )
```

Excludes specific posts from real-time sync.

### Action: sync post types updated

```php
do_action( 'gregius_data_sync_post_types_updated', $connection_name, $enabled_post_types )
```

Fired when sync configuration is saved.

### Action: sync post types changed

```php
do_action( 'gregius_data_sync_post_types_changed', $connection_name, $added_types, $removed_types )
```

Fired when the enabled post types actually change, so the attachment reconciler can react.

---

## 6. WP-CLI (`Gregius_Data_CLI_Sync`)

Source: `includes/cli/class-gregius-data-cli-sync.php`

```bash
wp gregius-data sync posts   [--connection=<name>] [--post-type=<type>] [--batch-size=<number>] [--dry-run] [--format=<table|json|csv>]
wp gregius-data sync terms   [--connection=<name>] [--batch-size=<number>] [--dry-run] [--format=<table|json|csv>]
wp gregius-data sync all     [--connection=<name>] [--batch-size=<number>] [--dry-run] [--format=<table|json|csv>]
```

- `--connection` defaults to `gregius-data`.
- `--batch-size` defaults to `100` and is capped by the `gregius_data_cli_max_batch_size` filter (default 1000).
- `posts` syncs posts (and their postmeta/relationships) for all public post types, or a single `--post-type`.
- `terms` syncs terms, term taxonomies, and term relationships.
- `all` runs `posts` then `terms`.

---

## 7. Troubleshooting

### Symptom: PostgreSQL has fewer records than WordPress

**Root cause:** Real-time sync errors OR a batch operation incomplete.

**Diagnosis:**
1. Run fast validation: `GET /wp-json/gregius-data/v1/sync/validation/fast?connection=default`
2. Inspect drift values; check the corresponding WordPress posts exist.
3. Review error logs for sync failures.

**Resolution:**
1. Review the error log for exceptions.
2. Run batch sync for the affected post type: `POST /wp-json/gregius-data/v1/sync/batch-sync-post-type/post`
3. Repeat fast validation and confirm drift resolved.

### Symptom: Batch operation times out

**Root cause:** High batch size on the PDO provider or a slow network.

**Diagnosis:**
1. Check the provider: PDO or PostgREST?
2. Reduce batch size (e.g. `POST .../batch-sync-post-type/post` with `batch_size=50&offset=0`).

**Resolution:**
1. For PDO: reduce batch size to 50–75.
2. For PostgREST: increase batch size; network latency is usually the bottleneck.

### Symptom: Batch delete repeats the same row or stalls

**Root cause:** A destructive delete was paginated from a shrinking client-side offset instead of letting the controller manage the cursor.

**Diagnosis:**
1. Confirm the request goes to `/wp-json/gregius-data/v1/sync/batch-delete`.
2. Check the response for `has_more`, `deleted`, and `remaining`.
3. Inspect any custom batch-size filters that may force a zero or tiny batch.

**Resolution:**
1. Keep the client loop simple and trust the controller's `has_more` value.
2. Adjust the batch-size filters only for throughput tuning.
3. If the controller returns zero deletions while rows remain, verify provider permissions and row-level filters.

### Symptom: drift for one connection differs from another

**Root cause:** A connection name mismatch in metadata OR sync ran with the wrong connection context.

**Diagnosis:**
1. Confirm `wp_gregius_data_sync_metadata.connection_name` matches the requested connection.
2. Check settings per connection (each connection has its own `sync_enabled_post_types` / `sync_enabled_statuses`).

**Resolution:**
1. Ensure sync endpoints are called with the correct `connection` / `connection_name` parameter.
2. Run per-connection full validation to refresh metadata.

---

## 8. Integration Guide

### Consume synced content (search indexing)

The sync subsystem does not fire a per-post sync-complete action. To react to synced content, use the `gregius_data_sync_post_types_updated` action (fires when sync configuration changes) or poll sync metadata for changes.

### Consume synced chunks (vector embedding)

Query `wp_posts_chunks` via the provider or `Gregius_Data_Chunker::get_chunks( $post_id, $connection_name )`. Chunks are keyed by `post_id` and `chunk_index`.
