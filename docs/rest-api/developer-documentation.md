# REST API Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to use and extend the Gregius Data REST API subsystem.

Audience:
- Core contributors maintaining REST controllers
- Integrators building dashboard or external clients

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Route groups under `gg-data/v1`
- Permission models and endpoint access expectations
- Request and response conventions
- Extension hooks relevant to REST behavior
- Troubleshooting guidance for common API failures

Not covered:
- End-user feature documentation
- Internal algorithm details of sync/search/RAG subsystems

## 3. API Fundamentals

### 3.1 Namespace and Route Style

- Base namespace: `/wp-json/gg-data/v1`
- Routes are grouped by controller domain.
- Most endpoints are JSON request/response contracts.

### 3.2 Permission Patterns

The subsystem uses three permission patterns:

1. **Admin-gated controllers**
   - Most operational controllers require privileged capability checks.
2. **Filter-based runtime policy**
   - RAG endpoints use `gg_data_rag_endpoint_permission` and related security hooks.
3. **Ownership-aware access**
  - Interactions endpoints allow logged-in users to access their own records (read/update/delete), restrict direct create to admins, and allow admins full governance.

### 3.3 Request and Response Conventions

- Provide `Content-Type: application/json` for JSON bodies.
- Authenticated admin requests typically include a REST nonce in WordPress dashboard contexts.
- Successful responses are structured JSON payloads.
- Failures return `WP_Error` style responses with explicit status codes.

## 4. Route Catalog (gg-data/v1)

### 4.1 Settings Controller

Source: [../../includes/api/class-gg-data-rest-settings-controller.php](../../includes/api/class-gg-data-rest-settings-controller.php)

Key routes:
- `GET /settings`
- `POST /settings/create-tables`
- `GET /settings/{key}`
- `POST /settings/{key}`
- `PUT/PATCH /settings/{key}`
- `DELETE /settings/{key}`
- `POST /settings/bulk`
- `GET /settings/database/{db_key}`
- `POST /settings/database/{db_key}`
- `GET /settings/{category}/{key}`
- `POST /settings/{category}/{key}`

### 4.2 Connections Controller

Source: [../../includes/api/class-gg-data-rest-connections-controller.php](../../includes/api/class-gg-data-rest-connections-controller.php)

Key routes:
- `GET /connections`
- `POST /connections`
- `GET /connections/{name}`
- `PUT/PATCH /connections/{name}`
- `DELETE /connections/{name}`
- `POST /connections/{name}/test`
- `GET /connections/all-health`
- `GET /connections/{name}/health`
- `GET /connections/stats`

Contract notes:
- `all-health` and `stats` are reserved static routes and are not matched by `{name}` route parameters.
- `GET /connections/stats` reports provider-cache/configuration state (configured connections, cached provider presence, and current connected state), not raw pgsql extension resource counters.

### 4.3 Connection Health Controller

Source: [../../includes/api/class-gg-data-rest-connection-health-controller.php](../../includes/api/class-gg-data-rest-connection-health-controller.php)

Key routes:
- `GET /sync/connection-health`
- `POST /sync/connection-health/check`
- `POST /sync/connection-health/reset`

### 4.4 Connection Models Controller

Source: [../../includes/api/class-gg-data-rest-connection-models-controller.php](../../includes/api/class-gg-data-rest-connection-models-controller.php)

Key routes:
- `GET /connections/{connection}/vectors/models`
- `POST /connections/{connection}/vectors/models`
- `DELETE /connections/{connection}/vectors/models/{modelKey}`

### 4.5 Schema Controller

Source: [../../includes/api/class-gg-data-rest-schema-controller.php](../../includes/api/class-gg-data-rest-schema-controller.php)

Key routes:
- `GET /schema/status`
- `POST /schema/create`
- `POST /schema/upgrade`
- `POST /schema/verify`
- `GET /schema/sql`

### 4.6 Sync Controller

Source: [../../includes/api/class-gg-data-rest-sync-controller.php](../../includes/api/class-gg-data-rest-sync-controller.php)

Key routes:
- `GET /sync/post-types`
- `GET /sync/configuration`
- `POST /sync/configuration`
- `GET /sync/status`
- `POST /sync/taxonomy/bulk`
- `POST /sync/taxonomy/terms`
- `POST /sync/taxonomy/taxonomies`
- `POST /sync/taxonomy/relationships`
- `GET /sync/taxonomy/validation`
- `POST /sync/post-type/{type}`
- `POST /sync/post-type/{type}/clean`
- `DELETE /sync/post-type/{type}/orphans`
- `DELETE /sync/postmeta/orphans`
- `DELETE /sync/term-relationships/orphans`
- `DELETE /sync/term-taxonomy/orphans`
- `DELETE /sync/terms/orphans`
- `POST /sync/batch-sync-postmeta`
- `POST /sync/postmeta`
- `POST /sync/batch-sync-post-type/{type}`
- `POST /sync/batch-sync-terms`
- `POST /sync/batch-sync-term-taxonomies`
- `POST /sync/batch-sync-term-relationships`
- `POST /sync/batch-delete`

Contract notes:
- `POST /sync/batch-delete` is a maintenance/internal destructive endpoint that removes synchronized content in this sequence: `wp_posts_chunks` -> `wp_posts_clean` -> `wp_posts`.
- Request args: `connection_name` (required), optional `batch_size`, `offset`, and `limit`.
- The endpoint always reads from offset `0` inside each batch to avoid skipping rows as tables shrink.
- The response includes `deleted`, `total_deleted`, `has_more`, `next_offset`, `duration_ms`, `errors`, `limit`, `table`, and `remaining`.
- Batch-size customization is filter-driven through `gg_data_sync_delete_batch_size` and the table-specific filters documented in the sync guide.
- The handler is provider-aware and supports both direct PostgreSQL/PDO and PostgREST/Supabase connections.
- This endpoint is not intended for routine dashboard operation; use it only in controlled operator or automation workflows.

Orphan cleanup notes:
- The orphan cleanup endpoints accept an optional `batch_size` request argument.
- The effective orphan cleanup batch size can also be overridden at runtime through `gg_data_orphan_delete_batch_size`.
- `gg_data_sync_delete_batch_size` and its table-specific variants apply to `POST /sync/batch-delete`, not to the orphan cleanup endpoints.

### 4.7 Sync Validator Controller

Source: [../../includes/api/class-gg-data-rest-sync-validator-controller.php](../../includes/api/class-gg-data-rest-sync-validator-controller.php)

Key routes:
- `GET /sync/validation`
- `POST /sync/validation/run`
- `POST /sync/validation/reset`
- `GET /sync/validation/fast`

### 4.8 Search Controller

Source: [../../includes/api/class-gg-data-rest-search-controller.php](../../includes/api/class-gg-data-rest-search-controller.php)

Key routes:
- `GET /search/health`
- `POST /search/health/check`
- `POST /search/health/reset`
- `GET /search/status`
- `POST /search/schema/create`
- `GET /search/language-status`
- `POST /search/update-language`
- `POST /search/fix-settings`
- `GET /search/typo-tolerance-status`
- `GET /search/typo-tolerance`
- `POST /search/typo-tolerance`

Contract notes:
- `POST /search/typo-tolerance` accepts `connection` (string, required), `typo_tolerance` (boolean, optional), `similarity_threshold` (number 0.2–0.5, optional), and `retrieval_mode` (string, optional).
- `GET /search/typo-tolerance` returns `typo_tolerance`, `similarity_threshold`, and `retrieval_mode`.

### 4.9 Vector Queue Controller

Source: [../../includes/api/class-gg-data-rest-vector-queue-controller.php](../../includes/api/class-gg-data-rest-vector-queue-controller.php)

Key routes:
- `GET /vector-queue`
- `POST /vector-queue/generate/{id}`
- `POST /vectors/batch-generate`
- `POST /vectors/batch-delete`
- `GET /vectors/status`
- `DELETE /vectors`
- `GET /vectors/posts`

Contract notes:
- `POST /vectors/batch-delete` supports both PDO and PostgREST/Supabase providers.
- Request args: `model_key`, `connection_name`, optional `batch_size`, `offset`, `limit`.
- Response includes batch progress fields: `deleted`, `total_deleted`, `has_more`, `next_offset`, `duration_ms`, and `errors`.
- For destructive deletes, backend selects from the current remaining set each batch to avoid offset drift.

### 4.10 Vocabulary Controller

Source: [../../includes/api/class-gg-data-rest-vocabulary-controller.php](../../includes/api/class-gg-data-rest-vocabulary-controller.php)

Key routes:
- `POST /vocabulary/prepare`
- `GET /vocabulary/status`
- `DELETE /vocabulary/cache`

### 4.11 Retry Queue Controller

Source: [../../includes/api/class-gg-data-rest-retry-queue-controller.php](../../includes/api/class-gg-data-rest-retry-queue-controller.php)

Key routes:
- `GET /sync/retry-queue`
- `POST /sync/retry-queue/retry/{index}`
- `DELETE /sync/retry-queue/clear`

### 4.12 Models Controller

Source: [../../includes/api/class-gg-data-rest-models-controller.php](../../includes/api/class-gg-data-rest-models-controller.php)

Key routes:
- `GET /models`
- `POST /models`
- `GET /models/providers`
- `POST /models/test`
- `POST /models/{id}/reset-usage`
- `GET /models/{id}`
- `PUT/PATCH /models/{id}`
- `DELETE /models/{id}`

### 4.13 Prompts Controller

Source: [../../includes/api/class-gg-data-rest-prompts-controller.php](../../includes/api/class-gg-data-rest-prompts-controller.php)

Key routes:
- `GET /prompts`
- `POST /prompts`
- `PUT/PATCH /prompts/{id}`
- `DELETE /prompts/{id}`
- `POST /prompts/{id}/activate`

### 4.14 Logs Controller

Source: [../../includes/api/class-gg-data-rest-logs-controller.php](../../includes/api/class-gg-data-rest-logs-controller.php)

Key routes:
- `GET /logs`
- `GET /logs/stats`
- `GET /logs/export`
- `DELETE /logs/purge`
- `GET /logs/settings`
- `POST /logs/settings`

Contract notes:
- `GET /logs/settings` exposes `retention_days` from site option `gg_data_log_retention_days`.
- `POST /logs/settings` persists `retention_days` (bounded `1..365`) at site scope.
- `DELETE /logs/purge` accepts `days` and returns deleted row count in `data.deleted`.
- Purge operations are constrained to the current site's `{prefix}gg_data_logs` table.
- Purge operations do not target `gg_interaction` records.

### 4.15 RAG Controller

Source: [../../includes/api/class-gg-data-rest-rag-controller.php](../../includes/api/class-gg-data-rest-rag-controller.php)

Key routes:
- `POST /rag/chat`
- `POST /rag/action`
- `GET /rag/actions`

Key notes:
- Permission is filter-based (`gg_data_rag_endpoint_permission`) with fail-closed evaluation and logged-in default posture in current security hooks.
- Route-scoped fixed-window throttling is enforced for `POST /rag/chat` and `POST /rag/action`.
- Exceeded limits return `gg_data_rate_limited` with HTTP 429 and metadata (`retry_after`, `scope`, `limit`, `window`).
- Request payloads include required and optional model and context fields.
- `POST /rag/chat` accepts optional structured `metadata_filter` and `metadata_manifest` fields for scoped retrieval context.
- `POST /rag/chat` accepts optional `manifest` (object) and `forced_tool` (string) to enable deterministic tool execution and manifest-aware context handling.
- Supported deterministic overrides currently include `summarize_current_entity` and `recommend_related_content`; unsupported values fall back to normal routing.

### 4.16 RAG Journey Controller

Source: [../../includes/api/class-gg-data-rest-rag-journey-controller.php](../../includes/api/class-gg-data-rest-rag-journey-controller.php)

Key routes:
- `POST /rag/journey/issue`
- `POST /rag/journey/consume`
- `GET /rag/journey/history`

Key notes:
- Permission is filter-based (`gg_data_rag_endpoint_permission`) with fail-open evaluation (default allow) for journey endpoints.
- Caller authentication: guest users identified by session-hash credentials derived from WordPress authentication salts.
- Session ownership is enforced on consume and history routes: caller's current session hash must match the stored journey owner hash.
- First-claim binding: legacy journey records without session hashes can be claimed on first interaction by any guest session.
- `POST /rag/journey/issue` creates or retrieves a journey and returns a unique `interaction_id` and `session_hash`.
- `POST /rag/journey/consume` appends a RAG turn (query + answer + metadata) and validates session ownership.
- `GET /rag/journey/history` retrieves all turns in a journey, paginated and filtered by session ownership.
- No fixed-window throttling on journey endpoints; per-scope default rate-limit filter applies.

### 4.17 Interactions Controller

Source: [../../includes/api/class-gg-data-rest-interactions-controller.php](../../includes/api/class-gg-data-rest-interactions-controller.php)

Notes:
- This is a `WP_REST_Posts_Controller` implementation for interactions.
- Access policy is ownership-aware:
  - logged-in users can access their own interactions (read/update/delete)
  - direct `POST /interactions` is admin-only (non-admin create is chat-flow driven)
  - admins can access all interactions
  - anonymous users are denied
  - in multisite, explicit `site_id` context is super-admin-only and must reference a valid site

## 5. Extension Points

### 5.1 REST Access and Security Hooks

- `gg_data_rag_endpoint_permission`
- `gg_data_rag_access_level`
- `gg_data_rag_required_capability`

Use these to tighten or customize runtime access policy for RAG endpoints. Note that RAG chat and action endpoints use fail-closed permission semantics (default deny), while journey and SSE endpoints use fail-open semantics (default allow).

### 5.2 Streaming and Rate-Limit Extension Hook

Source: [../../includes/api/class-gg-data-sse-handler.php](../../includes/api/class-gg-data-sse-handler.php)

- `gg_data_rag_rate_limit`

Use this hook to implement custom throttling for streaming requests. This does not imply generic controller-wide rate limiting for all routes.

### 5.3 Route-Scoped RAG Rate-Limit Hooks

Source: [../../includes/api/class-gg-data-rest-rag-controller.php](../../includes/api/class-gg-data-rest-rag-controller.php)

- `gg_data_rag_rate_limit_anonymous`
- `gg_data_rag_rate_limit_authenticated`
- `gg_data_rag_rate_limit_window_seconds`

Use these hooks to tune route-scoped fixed-window limits for RAG chat/action endpoints.

For observability and telemetry, see the `gg_data_rag_rate_limit_decision` action hook documented in [../../docs/hooks/developer-documentation.md](../../docs/hooks/developer-documentation.md#gg_data_rag_rate_limit_decision). This action fires for all rate-limit decisions and is suitable for logging, metrics, and debugging.

## 6. Usage Examples

### 6.1 Settings Retrieval (Admin)

```bash
curl -X GET https://example.com/wp-json/gg-data/v1/settings \
  -H "X-WP-Nonce: YOUR_NONCE"
```

### 6.2 RAG Chat Request

```bash
curl -X POST https://example.com/wp-json/gg-data/v1/rag/chat \
  -H "Content-Type: application/json" \
  -d '{
    "query": "How do I install WordPress?",
    "connection_name": "default",
    "embedding_model_key": "tfidf-300",
    "llm_model_id": "gpt-4o-mini",
    "metadata_filter": {},
    "metadata_manifest": []
  }'
```

### 6.3 Sync Status Check

```bash
curl -X GET "https://example.com/wp-json/gg-data/v1/sync/status?connection=default" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

## 7. Troubleshooting

### 7.1 Permission Denied (`rest_forbidden`)

Check:
- caller authentication status
- required capability for admin endpoints
- RAG filter-based permissions
- interaction ownership rules

### 7.2 Validation Errors

Check route-specific required args and types:
- connection keys and model keys
- typed route params (`id`, `name`, `type`)
- payload field names expected by route args

### 7.3 Route Not Found

Check:
- namespace path (`/wp-json/gg-data/v1/...`)
- method mismatch (GET vs POST vs DELETE)
- route pattern parameters (`{name}`, `{id}`, `{type}`)

### 7.4 CORS and Throttling Expectations

Current guidance:
- Do not assume generic controller-level CORS headers are automatically added.
- Route-scoped throttling is built in for `POST /rag/chat` and `POST /rag/action`; do not assume global middleware for all REST routes.
- Treat CORS and non-RAG throttling as deployment or extension concerns unless explicit runtime implementation is verified.

## 8. Traceability Summary

- Route and permission coverage: [SRS: REST-FR-01 to REST-FR-17]
- Contract and error behavior: [SRS: REST-DR-01 to REST-DR-12]
- Access and operational constraints: [SRS: REST-OR-01 to REST-OR-07]
- Parity quality expectations: [SRS: REST-QR-01 to REST-QR-08]
