# Search Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to use, operate, and extend the Gregius Data Search subsystem.

Audience:
- Core contributors maintaining search integration and SQL orchestration
- Integrators working with search behavior, settings, and operational controls
- Engineers validating provider-path parity and fallback behavior

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- WordPress search interception and ordering behavior
- PDO and PostgREST execution paths
- Search SQL functions and orchestration entrypoints
- Health fallback and search management REST endpoints
- Extension points and troubleshooting guidance

Not covered:
- RAG answer synthesis behavior
- Vector generation internals beyond search consumption contracts
- End-user search UX copy and presentation guidance

## 3. Key Components

### 3.1 Search Integration (`GG_Data_Search_Integration`)

Source: [../../includes/search/class-gg-data-search-integration.php](../../includes/search/class-gg-data-search-integration.php)

Core responsibilities:
- Intercept WordPress search via `posts_search`.
- Preserve relevance ordering via `posts_orderby`.
- Execute PostgreSQL search for synced post types.
- Apply configured retrieval mode (`hybrid_default` or `postgresql_only`).
- Keep MySQL fallback internal-only when PostgreSQL execution fails.

Notable behavior:
- Main-query gating avoids rewriting non-main search queries.
- Connection is resolved from search settings.
- Runtime calls `search_native_orchestrate` directly. No compatibility wrapper toggle exists.

### 3.2 Search Schema (`GG_Data_Search_Schema`)

Source: [../../includes/search/class-gg-data-search-schema.php](../../includes/search/class-gg-data-search-schema.php)

Core responsibilities:
- Provision SQL functions from `includes/search/sql/create-search-function.sql`.
- Validate function existence.
- Report readiness status per connection.

### 3.3 Search Fallback (`GG_Data_Search_Fallback`)

Source: [../../includes/search/class-gg-data-search-fallback.php](../../includes/search/class-gg-data-search-fallback.php)

Core responsibilities:
- Wrap PostgreSQL search execution with fallback-safe behavior.
- Track success/failure/latency telemetry.
- Expose degradation state for operational use.

### 3.4 Search REST Controller (`GG_Data_REST_Search_Controller`)

Source: [../../includes/api/class-gg-data-rest-search-controller.php](../../includes/api/class-gg-data-rest-search-controller.php)

Core responsibilities:
- Expose search status and controls under `gg-data/v1/search/*`.
- Support schema creation, language checks/updates, and typo-tolerance operations.
- Enforce administrative permission callbacks for management operations.

## 4. SQL Function Contract

Source: [../../includes/search/sql/create-search-function.sql](../../includes/search/sql/create-search-function.sql)

Primary entrypoints:
- `search_native_orchestrate(...)` (native search with parallel RRF)
- `search_rag_orchestrate(...)` (RAG-oriented orchestration path with supplementary metadata filter)

### `search_native_orchestrate`

```sql
search_native_orchestrate(
    search_text text,
    post_types text[] DEFAULT ARRAY['post', 'page'],
    limit_count integer DEFAULT 50,
    search_language text DEFAULT 'english',
    enable_trigram boolean DEFAULT false,
    similarity_threshold real DEFAULT 0.3,
    enable_vector boolean DEFAULT false,
    vector_table text DEFAULT 'wp_posts_tfidf_300',
    vector_column text DEFAULT 'embedding',
    rrf_k integer DEFAULT 60,
    precomputed_query_vector text DEFAULT NULL
)
```

### `search_rag_orchestrate`

```sql
search_rag_orchestrate(
    search_text text,
    post_types text[] DEFAULT ARRAY['post', 'page'],
    limit_count integer DEFAULT 50,
    search_language text DEFAULT 'english',
    enable_trigram boolean DEFAULT true,
    similarity_threshold real DEFAULT 0.3,
    enable_vector boolean DEFAULT true,
    vector_table text DEFAULT 'wp_posts_tfidf_300',
    vector_column text DEFAULT 'embedding',
    metadata_filter jsonb DEFAULT '{}'::jsonb,
    rrf_k integer DEFAULT 60
)
```

### Trigram Implementation

Both functions use **GiST-optimized trigram matching** via the `<<->` distance operator with separate ORDER BY + LIMIT pushdown on `post_title_clean` and `post_content_clean`. This replaces the earlier GIN-based `word_similarity()` + `%` WHERE approach, avoiding planner conflicts between GIN and GiST index types and enabling index-accelerated distance sorting.

**Short-word suppression:** Both orchestrators disable trigram when every word in the query is under 4 characters (e.g. "wp", "ai"). Short queries have poor trigram selectivity and degrade result quality, so they fall back to FTS-only or vector-only matching. This is checked in the SQL function body, not in PHP.

Supporting helpers:
- `search_core_fuse_rrf(integer, integer)` — reciprocal rank fusion scoring
- `search_core_vector_candidates(...)` — retrieves vector candidates from the configured embedding table
- `gg_generate_search_vector(text, text, text, text, text, integer)` — generates a tsvector from concatenated title + content fields with configurable weights per search configuration

**PostgREST-only functions** (in `includes/sql/postgrest-schema.sql`, not auto-deployed):
- `get_schema_status()` — returns extension availability and schema table presence for `pg_trgm`, `vector`, and vector table. Used by the integration class to populate per-connection capability state without auto-deploy.
- `get_posts_needing_vectors(integer, text, text)` — identifies posts whose clean-content rows lack vector embeddings, returning post IDs and their clean content for upstream vector generation.
- `search_rag_get_context(text, text[], integer, text, boolean, real, boolean, text, text, jsonb, integer)` — PostgREST-specific RPC variant of `search_rag_orchestrate` with identical parameter semantics.
- `wp_posts_clean_search_vector_update()` — trigger function that recomputes the `search_vector` column on `wp_posts_clean` rows when title or content changes.

Expected output shape from all orchestrators:
- `post_id`
- `relevance_score`
- `post_title`
- `post_excerpt`
- `post_type`
- `post_status`
- `match_type` (one of `blended_rrf`, `vector`, `trigram`, `fts`)

## 5. REST Route Catalog (Search)

Base namespace: `/wp-json/gg-data/v1/search`

Health and status:
- `GET /health`
- `POST /health/check`
- `POST /health/reset`
- `GET /status`

Schema and language:
- `POST /schema/create`
- `GET /language-status`
- `POST /update-language`
- `POST /fix-settings`

Typo tolerance:
- `GET /typo-tolerance-status`
- `GET /typo-tolerance`
- `POST /typo-tolerance`

All search management routes use admin-style permission checks in controller callbacks.

## 6. Runtime Settings and Controls

Global search settings source:
- [../../includes/search/search-settings.php](../../includes/search/search-settings.php)

Core keys (default values in parentheses):

| Key | Default | Description |
|---|---|---|
| `search.enabled` | `false` | Opt-in toggle. |
| `search.connection` | `''` | Database connection for search queries. |
| `search.embedding_model` | `tfidf-300` | Resolves to a vector table name for semantic search. |
| `search.retrieval_mode` | `hybrid_default` | PostgreSQL + MySQL merge or PostgreSQL-only. |
| `search.language` | `english` | PostgreSQL text search configuration. |
| `search.similarity_threshold` | `0.5` (PHP runtime) / `0.3` (SQL default) | Trigram matching strictness. The SQL function default is `0.3`, but the PHP runtime overrides to `0.5` on every call. |
| `search.observability_enabled` | `false` | Opt-in expensive probe toggle. |
| `search.typo_tolerance` | (capability-gated) | Not a persisted default; runtime eligibility is derived from `pg_trgm` extension presence or persisted `trigram_supported` state. |

Behavior notes:
- Search enablement is opt-in.
- Connection selection drives provider path.
- Embedding model selection influences vector table configuration.
- `search.retrieval_mode` accepted values:
    - `hybrid_default`: merge PostgreSQL results with MySQL matches for broader coverage.
    - `postgresql_only`: skip merge path and return PostgreSQL-native results.
- PostgreSQL execution failure always allows internal MySQL fallback regardless of retrieval mode.
- `search.observability_enabled` defaults to `false` and is the single runtime control for observability probes.
- `search.observability_enabled` controls probe mode only; it does not directly toggle trigram/vector flags.
- When `search.observability_enabled` is `false`, PDO and PostgREST runtime paths perform no live extension checks and no expensive readiness probes.
- When `search.observability_enabled` is `false`, trigram/vector eligibility is determined only from persisted per-connection capability state.
- When `search.observability_enabled` is `false`, both `latency_pg_extension_checks_ms` and `latency_pg_vector_readiness_ms` remain `0`.
- When `search.observability_enabled` is `true`, PDO and PostgREST paths run the full observability probe set.
- `search.typo_tolerance` is settable via the management API (`POST /search/typo-tolerance`) and persisted in settings. Runtime trigram eligibility is determined by two capability paths: live `pg_trgm` extension checks (when observability is enabled) or persisted per-connection `trigram_supported` state (baseline mode).

### 6.1 Observability Field Interpretation

The `search_strategy` field classifies the active retrieval strategy based on signal counts and merge state. All 9 possible values:

| Strategy | When returned |
|---|---|
| `mysql_only` | MySQL fallback contributed and no PostgreSQL signals were present (degraded) |
| `hybrid_with_mysql` | MySQL contributed alongside PostgreSQL signals |
| `fts_only` | Only FTS signal present |
| `trigram_only` | Only trigram signal present |
| `vector_only` | Only vector signal present |
| `fts_plus_trigram` | FTS + trigram (vector absent or disabled) |
| `fts_plus_vector` | FTS + vector (trigram absent) |
| `fused` | All three signals present (or multiple with no exact pair match) |
| `none` | No signals and no MySQL merge |

Key telemetry interpretations:
- `search_strategy = mysql_only` with `degraded = true` means PostgreSQL execution path failed and internal MySQL fallback was applied.
- `fallback_reason = postgresql_failed_internal_fallback` indicates fallback was intentional and availability-preserving.
- `mysql_merge_applied = true` and `mysql_merge_contributed = true` confirm MySQL supplied final visible results.
- `postgresql_count = 0` with non-zero `mysql_count` indicates PostgreSQL contribution was unavailable in that request.
- When MySQL and PostgreSQL results are merged, dedup uses `array_diff` to remove duplicate post IDs. **PostgreSQL entries take precedence** — MySQL duplicates are discarded (`dedup_removed_count` reports how many).

Vector-readiness interpretation:
- `signal_counts.vector = 0` is expected when vectors are not generated or not eligible for the active request.
- `latency_pg_vector_readiness_ms = 0` and `latency_pg_extension_checks_ms = 0` with `observability_mode = baseline` indicate expensive probes are disabled, not necessarily that capability is absent.
- In baseline mode (`search.observability_enabled = false`), capability checks come from persisted settings state and probe latency fields remain zero.

Model observability:
- `embedding_model` — the model key resolved from the model registry for the active search (e.g. `hashingtf-murmur3-1024`). Confirms which embedding model the vector path queried.
- `vector_table` — the database table name corresponding to the resolved model (e.g. `wp_posts_hashingtf_murmur3_1024`). Useful for diagnosing table-level configuration mismatches.

## 7. Metadata Filter Contract

When metadata filtering is used from RAG-oriented retrieval, preserve these invariants:
- Empty filter must be object semantics (`{}`), not array semantics (`[]`).
- `metadata_filter` is evaluated against per-post `metadata_manifest` JSONB.
- Provider paths (PDO and PostgREST) must pass equivalent JSONB payloads for parity.

Implementation surfaces:
- SQL signatures in [../../includes/search/sql/create-search-function.sql](../../includes/search/sql/create-search-function.sql)
- Supabase/PostgREST schema in [../../includes/sql/postgrest-schema.sql](../../includes/sql/postgrest-schema.sql)
- Content-cleaner manifest population in [../../includes/class-gg-data-content-cleaner.php](../../includes/class-gg-data-content-cleaner.php)
- Schema manager column/index provisioning in [../../includes/class-gg-data-schema-manager.php](../../includes/class-gg-data-schema-manager.php)

### 7.1 filter_operator: AND / OR Mode

The `metadata_filter` JSONB parameter supports two evaluation modes controlled by the `filter_operator` key.

**AND mode (default)**

When `filter_operator` is absent or `'AND'`, standard JSONB containment is applied:

```sql
COALESCE(pc.metadata_manifest, '{}'::jsonb) @> (metadata_filter - 'filter_operator')
```

The `filter_operator` key is stripped from `metadata_filter` before containment evaluation so it does not interfere with the JSONB match.

**OR mode**

When `filter_operator = 'OR'`, each term in `metadata_filter.taxonomy_manifest` is evaluated independently via `EXISTS`:

```sql
EXISTS (
    SELECT 1
    FROM jsonb_array_elements(
        COALESCE(metadata_filter->'taxonomy_manifest', '[]'::jsonb)
    ) AS f(term)
    WHERE COALESCE(pc.metadata_manifest, '{}'::jsonb)
          @> jsonb_build_object('taxonomy_manifest', jsonb_build_array(f.term))
)
```

A post qualifies if its `metadata_manifest.taxonomy_manifest` array contains **any** of the filter terms. This enables broad NBA result pools that span multiple taxonomy categories.

**Applied in both SQL files, all three CTE branches:**

| CTE | PDO File | PostgREST File |
|---|---|---|
| `fts_results` | `create-search-function.sql` ~line 435 | `postgrest-schema.sql` |
| `trigram_results` | same | same |
| `vector_results` | same | same |

PostgREST file path: [`includes/sql/postgrest-schema.sql`](../../includes/sql/postgrest-schema.sql) — must be manually copied to Supabase SQL editor; no auto-deploy.

**Wire format — PHP (`GG_Intelligence_RAG_Tools::resolve_nba_filter`):**

```php
[
    'filter_operator'   => 'OR',
    'taxonomy_manifest' => [
        [ 'taxonomy' => 'category', 'term_id' => 12, 'term_slug' => 'certification' ],
        [ 'taxonomy' => 'category', 'term_id' => 7,  'term_slug' => 'compliance' ],
    ],
]
```

**Wire format — JavaScript (`buildMetadataFilterFromPill` in `rag-intelligence.js`):**

```js
// NBA pill click builds:
{
    taxonomy_manifest: [
        { taxonomy: 'category', term_id: 12, term_slug: 'certification' }
    ],
    filter_operator: 'OR'
}
```

Legacy pills using `pill.anchor` (single `taxonomy:slug:id` string) are handled by a separate branch that produces a single-element `taxonomy_manifest` array without `filter_operator` (AND default).

**Backward compatibility:** Existing queries without `filter_operator` are unaffected. AND containment remains the implicit default. Empty filter (`{}`) always passes both branches via the `metadata_filter = '{}'::jsonb` short-circuit.

**Provider path parity:** Both PDO and PostgREST SQL files carry identical OR/AND logic. Any future changes to this filter contract must be applied to both files.

---

## 8. Extension Points

### 8.1 Filters

- `gg_data_search_similarity_threshold`
  - Adjust trigram similarity threshold dynamically by query and connection context.

- `gg_data_search_rate_limit`
  - Adjust search rate limiting. Receives `['limit' => int, 'window' => int]`.
  - Default: `['limit' => 30, 'window' => 60]` (30 requests per 60-second window).
  - Applied as a per-IP transient in `filter_posts_search()` before PostgreSQL execution.
  - Return empty array to disable rate limiting.
  - When exceeded, the hook causes a soft fallback to default MySQL `WP_Query` search (no error response).
  - Admin users (`manage_options` capability) bypass rate limiting automatically.

- `gg_data_search_title_weight` / `gg_data_search_content_weight`
  - Applied in schema manager (lines 934-935) when creating tsvector indexes.
  - Defaults: `'A'` (title weight) and `'B'` (content weight).
  - Override to adjust ranking contribution of title vs body text in FTS scoring.

- `gg_data_rag_pre_retrieval_filter`
  - Applied in RAG service `generate_answer()` (line 1544) before chunk retrieval.
  - Receives empty array, original query, and options; returns filter criteria array.
  - Returned array is merged into RAG options as `$options['retrieval_filters']`, which flows through to `search_rag_orchestrate`'s `metadata_filter` parameter.
  - Use to inject date ranges, taxonomy filters, or document-class constraints into RAG-oriented retrieval.

- `gg_data_rag_chunks`
  - Applied in RAG service `retrieve_chunks_pdo()` (line 697) and `retrieve_chunks()` (lines 449, 559).
  - Receives the resolved chunk array, query, and RAG options.
  - Use to re-rank, filter, or annotate retrieved chunks before context assembly.

Note:
- There is no direct hook to force `enable_trigram` or `enable_vector`; these are derived from capability state for the active runtime branch.

### 8.2 Actions

- `gg_data_search_completed`
    - Emitted after search composition with post IDs, count, and context metadata.
    - Includes `source_type` (for example `frontend`, `admin`, `rest`) and `retrieval_mode`.
        - Includes strategy attribution fields:
            - `search_strategy`
            - `signal_counts`
            - `dominant_signal`
            - `mysql_merge_applied`
            - `mysql_merge_contributed`
    - Includes diagnostic telemetry fields for logging/observability:
      - `latency_total_ms`
      - `latency_postgresql_ms`
      - `latency_mysql_ms`
            - `latency_pg_extension_checks_ms`
            - `latency_pg_vector_readiness_ms`
            - `latency_pg_execute_ms`
            - `latency_pg_fetch_ms`
            - `latency_pg_total_ms`
            - `observability_expensive_probes_enabled`
            - `observability_expensive_probe_executed`
      - `dedup_removed_count`
      - `degraded`
      - `fallback_reason`
      - `is_slow`
    - Includes model resolution fields:
      - `embedding_model` — resolved model key used for vector search
      - `vector_table` — database table queried for vector candidates

### 8.3 PostgreSQL Timing Field Semantics

- `latency_pg_extension_checks_ms`
    - Time spent checking PostgreSQL extension availability needed by the search runtime (for example `pg_trgm`, `vector`).

- `latency_pg_vector_readiness_ms`
    - Time spent preparing vector-search readiness state (model table resolution, vector presence checks, related setup probes).

- `latency_pg_execute_ms`
    - Time spent in PostgreSQL statement prepare+execute for the primary search function call.

- `latency_pg_fetch_ms`
    - Time spent fetching result rows after SQL execution.

- `latency_pg_total_ms`
    - End-to-end PostgreSQL search-path runtime from method entry to result return/throw handling.

Notes:
- Values are milliseconds with numeric defaults (`0.0`) when a phase is not executed.
- `latency_postgresql_ms` remains available at the orchestration layer and may differ from `latency_pg_total_ms` because it measures a broader wrapper path.
- This plugin is currently development-only and has no external legacy consumers; telemetry schema is additive and may evolve without legacy compatibility shims.

## 9. Logging Model

Search logging is intentionally split into two layers:

- Canonical interaction log (`info`):
  - emitted from interaction recording flow
  - one primary user-facing search log event per request
  - includes query/source/outcome and diagnostic timing context

- Execution diagnostic log (`debug`/`warning`):
  - emitted from search integration runtime
  - `warning` when search is degraded (`degraded=true`) or exceeds slow threshold
  - `debug` otherwise

Slow threshold behavior:
- default threshold: `400` milliseconds
- override via filter: `gg_data_search_slow_log_ms`

Example filter:

```php
add_filter( 'gg_data_search_slow_log_ms', function( $threshold_ms, $search_term, $connection_name, $retrieval_mode ) {
    unset( $search_term, $connection_name, $retrieval_mode );
    return 500;
}, 10, 4 );
```

## 10. Integration Notes

### 10.1 Provider Path Selection

- PDO path uses prepared SQL execution directly.
- PostgREST path uses RPC endpoint invocation.
- Keep payload parity when introducing new SQL parameters.

### 10.2 Search + RAG Intersections

Search SQL functions are shared across native (frontend search) and RAG-oriented retrieval contexts. Changes to search function signatures must account for all call sites.

**RAG service call sites calling `search_rag_orchestrate`:**

| Method | Provider | Params | Notes |
|---|---|---|---|
| `retrieve_chunks()` / `retrieve_chunks_pdo()` (lines 405, 595) | PDO | 10 params (no `rrf_k` — uses SQL default 60) | Main RAG retrieval path; supports `$options['disable_vector']` to skip vector search |
| `retrieve_chunks_fallback()` (line 729) | WordPress native | N/A | Used when PDO/PostgREST are unavailable; no PostgreSQL function called |
| PostgREST provider RPC call (line ~476) | PostgREST | 11 params | Supabase RPC invocation of `search_rag_orchestrate` |

**Search-adjacent RAG hooks:**

- `gg_data_rag_pre_retrieval_filter` — applied in `generate_answer()` (line 1544). Receives the original query and options; returns an array of filter criteria (`metadata_filter`) that flows into `search_rag_orchestrate`. Use this hook to inject date ranges, taxonomy filters, or document-class constraints into RAG retrieval.

- `gg_data_rag_chunks` — applied in `retrieve_chunks_pdo()` (line 697), and twice in `retrieve_chunks()` (lines 449, 559). Receives the resolved chunk array, query, and options. Use this hook to re-rank, filter, or annotate retrieved chunks before context assembly.

**Search settings governing RAG retrieval:**

- `search.language` — the PostgreSQL text search configuration (e.g. `english`). Used by `retrieve_chunks_pdo()` via `$options['language']` (default: `english`).
- `search.similarity_threshold` — default `0.5` in PHP runtime (SQL default is `0.3`). Controls trigram matching strictness in both PDO and PostgREST paths.
- `search.embedding_model` — default `tfidf-300`. Resolves to a vector table name for semantic search candidates. Must map to a valid model in the model registry.

**Metadata-filter changes** must be mirrored in both SQL artifacts and provider payload builders.

## 11. Troubleshooting

### 11.1 No PostgreSQL Search Results

Checklist:
- Confirm `search.enabled` is true.
- Confirm active connection is configured and reachable.
- Check schema status (`GET /search/status`) and function provisioning.
- Validate synced post types and data availability.

### 11.2 Typo Tolerance Not Working

Checklist:
- Verify `pg_trgm` extension presence (`GET /search/typo-tolerance-status`).
- Confirm typo tolerance setting/threshold values.
- Confirm query does not hit short-word suppression behavior.

### 11.3 Vector Search Not Active

Checklist:
- Verify `vector` extension presence.
- Verify configured embedding model maps to a valid vector table.

### 11.4 Duplicate Search Logs in UI

Expected behavior:
- one canonical `info` interaction log per search
- one execution log entry that is:
    - `warning` when degraded/slow
    - `debug` otherwise

If debug logging is enabled, both entries can be visible; this is normal and provides layered observability.

### 11.5 Health Status Remains Degraded

Checklist:
- Run `POST /search/health/check` and inspect error details.
- Validate provider-specific credentials/connectivity.
- Review recent error telemetry in health payload and plugin logs.
- Use `POST /search/health/reset` only after root cause is addressed.

### 11.6 Metadata-Scoped Search Returns No Results Unexpectedly

Checklist:
- Confirm empty metadata filter is serialized as `{}` rather than `[]`.
- Confirm `metadata_manifest` exists on `wp_posts_clean` rows used by retrieval.
- Confirm containment logic is present in all orchestrator candidate branches.
- Confirm PDO and PostgREST payloads carry equivalent `metadata_filter` JSON.

### 11.7 Missing Search Functions Auto-Repair

The integration class (`GG_Data_Search_Integration`) includes automatic detection and repair of missing SQL functions:
- `is_missing_function_error()` (line 839) — checks if a PDO exception indicates a missing `search_native_orchestrate` function.
- `repair_missing_search_functions()` (line 859) — recreates the missing function by re-running the SQL definition from `create-search-function.sql`; called automatically when a missing-function error is detected during a search request.

This means a single failed request due to a dropped function (e.g. after a DB restore) can self-heal on the next search attempt. If auto-repair fails, the search falls back to MySQL.

## 12. Traceability (SRS Mapping)

- Search interception and execution behavior: [SRS: SEARCH-FR-02, SEARCH-FR-04, SEARCH-FR-07]
- Provider-path and orchestrator behavior: [SRS: SEARCH-FR-05, SEARCH-FR-06, SEARCH-FR-13, SEARCH-DR-08, SEARCH-DR-09, SEARCH-DR-10]
- Operational controls and fallback: [SRS: SEARCH-FR-09, SEARCH-OR-02, SEARCH-OR-03]
- Typo/language management: [SRS: SEARCH-FR-10, SEARCH-FR-11, SEARCH-DR-05, SEARCH-DR-06]
- Quality/parity expectations: [SRS: SEARCH-QR-02, SEARCH-QR-05, SEARCH-QR-06, SEARCH-QR-08, SEARCH-QR-09]
