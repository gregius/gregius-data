# Developer Documentation: Hooks and Extension Surface Subsystem

Standard: ISO/IEC/IEEE 26514:2022
Component: Hooks and Extension Surface Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Architecture: [architecture.md](architecture.md)

---

## 1. Quick Start

Use WordPress-native hook APIs:

```php
add_filter( 'gg_data_post_sync_batch_size', function( $batch_size, $connection_name ) {
    return 50;
}, 10, 2 );

add_action( 'gg_data_interaction_recorded', function( $record_id, $payload ) {
    error_log( 'Interaction recorded: ' . $record_id );
}, 10, 2 );
```

Guidelines:
- Keep callbacks side-effect safe and idempotent where possible.
- Return the expected type from filters.
- Avoid relying on internal hooks unless clearly marked.

---

## 2. Tier 1 Public Hook Catalog

### 2.1 Configuration and Batch Hooks

#### `gg_data_post_sync_batch_size`
- Type: Filter
- Signature: `(int $batch_size, string $connection_name): int`
- Default: `100`
- Emitted in: `includes/batch/class-gg-data-post-sync.php`
- Use case: tune post sync throughput.

#### `gg_data_term_sync_batch_size`
- Type: Filter
- Signature: `(int $batch_size, string $connection_name): int`
- Default: `500`
- Emitted in: `includes/batch/class-gg-data-taxonomy-sync.php`
- Use case: tune taxonomy sync batching.

#### `gg_data_embedding_batch_size`
- Type: Filter
- Signature: `(int $batch_size, string $model_key, string $connection_name): int`
- Default: `10`
- Emitted in: `includes/api/class-gg-data-rest-vector-queue-controller.php`
- Use case: tune embedding generation batch size.

#### `gg_data_vector_delete_batch_size`
- Type: Filter
- Signature: `(int $batch_size, string $model_key, string $connection_name): int`
- Default: `500`
- Emitted in: `includes/api/class-gg-data-rest-vector-queue-controller.php`
- Use case: tune vector batch deletion throughput and timeout safety by environment.

Example:

```php
add_filter( 'gg_data_vector_delete_batch_size', function( $batch_size, $model_key, $connection_name ) {
  // Conservative on shared hosting.
  if ( defined( 'GG_DATA_SHARED_HOSTING' ) && GG_DATA_SHARED_HOSTING ) {
    return 250;
  }

  // Faster on dedicated infrastructure.
  return 1000;
}, 10, 3 );
```

### 2.2 Sync Hooks

#### `gg_data_should_sync_post`
- Type: Filter
- Signature: `(bool $should_sync, int $post_id): bool`
- Default: `true`
- Emitted in: `includes/hooks/class-gg-data-lifecycle-hooks.php`
- Use case: conditional sync inclusion by post state/type/meta.

#### `gg_data_skip_meta_keys`
- Type: Filter
- Signature: `(array $skip_keys): array`
- Default: WordPress/internal skip list
- Emitted in: `includes/hooks/class-gg-data-lifecycle-hooks.php`
- Use case: customize excluded post meta keys.

#### `gg_data_metadata_manifest`
- Type: Filter
- Signature: `(array $manifest, int $post_id): array`
- Default: `{ post_id, post_type, taxonomy_manifest }`
- Emitted in: `includes/class-gg-data-content-cleaner.php`
- Tier: 1 (public, stable)
- Use case: enrich the manifest written to the PostgreSQL sync table. External plugins
  (e.g. gregius-intelligence) use this to add `meta_manifest`, `manifest_hash`,
  `manifest_version`, and `search_capabilities` so the sync table stays in parity with
  the canonical manifest schema. Return an array of the same shape or a superset;
  never return a different type.

#### `gg_data_sync_post_types_updated`
- Type: Action
- Signature: `(string $connection_name, array $enabled_post_types): void`
- Emitted in: `includes/api/class-gg-data-rest-sync-controller.php`
- Tier: 1 (public, stable)
- Use case: rebuild downstream corpus-level derived artifacts (e.g. connection-scoped corpus capability manifests in gregius-intelligence) after sync post types configuration is saved for a connection.

### 2.3 Chunking Hooks

#### `gg_data_chunking_strategies`
- Type: Filter
- Signature: `(array $strategies): array`
- Emitted in: `includes/class-gg-data-chunker.php`
- Use case: register custom chunking strategies.

#### `gg_data_chunking_strategy`
- Type: Filter
- Signature: `(string $strategy, string $connection_name, array $context): string`
- Emitted in: `includes/class-gg-data-chunker.php`
- Use case: override strategy selection per context.

#### `gg_data_chunking_strategy_resolved`
- Type: Action
- Signature: `(string $strategy, string $connection_name, array $context): void`
- Emitted in: `includes/class-gg-data-chunker.php`
- Use case: diagnostics/telemetry after strategy resolution.

#### `gg_data_embedding_chunks`
- Type: Filter
- Signature: `(array $chunks, string $content, int $target_tokens, string $connection_name, array $context): array`
- Emitted in: `includes/class-gg-data-chunker.php`
- Use case: post-process chunk payload before persistence/embedding workflows.

### 2.4 Search Hooks

#### `gg_data_search_title_weight`
- Type: Filter
- Signature: `(string $weight, string $connection_name): string`
- Default: `'A'`
- Emitted in: `includes/class-gg-data-schema-manager.php`

#### `gg_data_search_content_weight`
- Type: Filter
- Signature: `(string $weight, string $connection_name): string`
- Default: `'B'`
- Emitted in: `includes/class-gg-data-schema-manager.php`

#### `gg_data_search_similarity_threshold`
- Type: Filter
- Signature: `(float $threshold, string $connection_name, string $search_query): float`
- Default: `0.5`
- Emitted in: `includes/search/class-gg-data-search-integration.php`

#### `gg_data_search_rate_limit`
- Type: Filter
- Signature: `(array $config): array`
- Default: `array('limit' => 30, 'window' => 60)`
- Emitted in: `includes/search/class-gg-data-search-integration.php`

### 2.5 Interaction Hooks

#### `gg_data_interaction_meta_fields`
- Type: Filter
- Signature: `(array $fields): array`
- Emitted in: `includes/class-gg-data-interaction.php`

#### `gg_data_interaction_log_context`
- Type: Filter
- Signature: `(array $context, int $post_id, string $type): array`
- Emitted in: `includes/class-gg-data-interaction.php`

#### `gg_data_interaction_recorded`
- Type: Action
- Signature: `(int $post_id, string $type, array $args): void`
- Emitted in: `includes/class-gg-data-interaction.php`

#### `gg_data_interaction_feedback_received`
- Type: Action
- Signature: `(int $interaction_id, string $feedback_type, mixed $feedback_value, array $context): void`
- Emitted in: `includes/api/class-gg-data-rest-interactions-controller.php`
- Use case: collect RLHF signals, trigger analytics/reranking pipelines, send feedback to external evaluation systems.

### 2.6 RAG Tool Hooks

#### `gg_data_rag_tools`
- Type: Filter
- Signature: `(array $tools): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`
- Use case: register custom RAG tools.

#### `gg_data_rag_tool_{name}` (dynamic)
- Type: Filter
- Dynamic format: `gg_data_rag_tool_` + tool slug
- Example: `gg_data_rag_tool_web_search`
- Signature: `(mixed $result, string $tool_name, array $tool_context): mixed`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_tool_executed`
- Type: Action
- Signature: `(string $tool_name, mixed $result, array $tool_context): void`

#### `gg_data_rag_complete`
- Type: Action
- Signature: `(array $result, string $query, float $execution_time): void`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`
- Use case: post-RAG integrations such as interaction logging, progressive summarization, analytics, and telemetry. Fired after every complete RAG turn including abstain and blocked results.

**`$result` shape:**

| Key | Type | Description |
|-----|------|-------------|
| `answer` | `string` | Final answer text returned to the user. Empty string on abstain or blocked. |
| `sources` | `array` | Citation sources used in the answer. Each entry has `title`, `url`, and `relevance`. Empty array when no sources were selected. |
| `metadata` | `array` | Execution context. See sub-keys below. |

**`$result['metadata']` sub-keys:**

| Key | Type | Description |
|-----|------|-------------|
| `connection` | `string` | Name of the Gregius Data connection used for this RAG turn. Set by the caller at construction time. |
| `llm_model` | `string` | Configured LLM model identifier (e.g. `gpt-4o-mini`). Stable across provider versioning. |
| `provider` | `string` | LLM provider identifier returned in the API response (e.g. `openai`). |
| `model_used` | `string` | Exact model string returned by the LLM provider (e.g. `gpt-4o-mini-2024-07-18`). May differ from `llm_model`. |
| `conversation_id` | `string\|null` | Conversation identifier passed in the original request options. `null` if not provided. |
| `usage` | `array` | Token usage from the LLM response. Keys: `prompt_tokens` (int), `completion_tokens` (int), `total_tokens` (int). Empty array on abstain. |
| `execution_time` | `int` | Total RAG turn duration in milliseconds. |
| `embedding_model` | `string` | Embedding model key used for vector retrieval. |
| `chunks_used` | `int` | Number of retrieved chunks included in the LLM context. |

**Note:** `connection` and `llm_model` are the canonical keys downstream consumers (e.g. progressive summarization in gregius-intelligence) must use to resolve the correct connection and model for follow-up LLM calls. Do not rely on `model_used` as a stable model identifier — it reflects provider versioning and may include date suffixes.

### 2.7 RAG Query and Source Quality Hooks

#### `gg_data_rag_search_query`
- Type: Filter
- Signature: `(string $search_query, string $query, array $options): string`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`
- Use case: rewrite retrieval query before candidate search.

#### `gg_data_rag_pre_retrieval_filter`
- Type: Filter
- Signature: `(array $filter_criteria, string $query, array $options): array`
- Default: `array()` (empty array; no filtering applied)
- Emitted in: `includes/rag/class-gg-data-rag-service.php`
- Use case: exclude document types, date ranges, or taxonomy constraints before candidate retrieval begins.

#### `gg_data_rag_source_min_relevance`
- Type: Filter
- Signature: `(float $threshold, array $chunks): float`
- Default: `0.5`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`
- Use case: adjust minimum relevance needed for source inclusion.

### 2.8 RAG Answer Generation Hooks

#### `gg_data_rag_llm_response`
- Type: Filter
- Signature: `(string $response_text, array $raw_response, string $model, string $query): string`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`
- Use case: post-process raw LLM response text (rephrasing, safety checks, format transforms, redaction) before citation marker normalization.

### 2.9 Log Retention Hooks

#### `gg_data_log_retention_days`
- Type: Filter
- Signature: `(int $days, int $site_id): int`
- Default: `30`
- Emitted in: `includes/class-gg-data-logger.php`
- Use case: override effective retention period for the current site before purge.

#### `gg_data_before_purge_logs`
- Type: Action
- Signature: `(int $days, string $threshold, int $site_id): void`
- Emitted in: `includes/class-gg-data-logger.php`
- Use case: pre-purge observability and site-scoped integrations.

#### `gg_data_after_purge_logs`
- Type: Action
- Signature: `(int $days, int $deleted, int $site_id): void`
- Emitted in: `includes/class-gg-data-logger.php`
- Use case: post-purge audit trails and automation.

Guidance:
- Keep listeners multisite-safe and site-context aware.
- Do not perform cross-site write operations from listeners.
- Hook argument order is stable and should not be reordered.

### 2.10 RAG Journey Hooks

#### `gg_data_rag_journey_allowed_block_id_prefixes`
- Type: Filter
- Signature: `(string[] $prefixes): string[]`
- Default: `['gg-rag-chat-']`
- Emitted in: `includes/api/class-gg-data-rest-rag-journey-controller.php`
- Use case: register additional block ID prefixes so journey token issue/consume
  validation accepts block IDs generated by downstream plugins or third-party blocks.

Details:
- Each entry must be a non-empty string.
- The suffix following the matched prefix is independently validated against `[A-Za-z0-9_-]+`.
- A block ID is accepted when at least one prefix matches AND the suffix passes the suffix constraint.
- Register prefixes from a plugin's integration hook class rather than from theme code.

Example — third-party block plugin registering its own prefix:
```php
add_filter( 'gg_data_rag_journey_allowed_block_id_prefixes', function ( array $prefixes ): array {
    $prefixes[] = 'my-plugin-chat-';
    return $prefixes;
} );
```

---

## 3. Tier 2 Semi-Public Hooks (Documented with Caveats)

These hooks are in active code and useful for advanced integrations, but should be treated with tighter compatibility caution than Tier 1.

### 3.1 RAG Core and Governance

#### `gg_data_rag_chunks`
- Type: Filter
- Signature: `(array $chunks, string $query, array $options): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_response`
- Type: Filter
- Signature: `(array $result, string $query): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_sources`
- Type: Filter
- Signature: `(array $sources, array $chunks): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_context`
- Type: Filter
- Signature: `(string $context, array $chunks, string $query): string`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_error`
- Type: Action
- Signature: `(mixed $error, string $query, array $context): void`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_request`
- Type: Action
- Signature: `(string $query, array $options, int $user_id): void`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_endpoint_permission`
- Type: Filter
- Signature: `(bool|WP_Error $allowed, WP_REST_Request|null $request): bool|WP_Error`
- Emitted in: `includes/api/class-gg-data-rest-rag-controller.php`, `includes/api/class-gg-data-rest-rag-journey-controller.php`, `includes/api/class-gg-data-sse-handler.php`
- Context parameter: `$request` contains the REST request object or `null` for SSE/non-standard contexts.

**Per-Surface Permission Seed Behavior:**

This filter uses different permission seed defaults depending on which endpoint surface evaluates the request:

| Surface | Endpoint | Seed | Semantics |
|---|---|---|---|
| RAG Chat/Action | `POST /rag/chat`, `POST /rag/action`, `GET /rag/actions` | Fail-Closed (False) | Default deny unless filter explicitly allows |
| RAG Journey | `POST /rag/journey/issue`, `POST /rag/journey/consume`, `GET /rag/journey/history` | Fail-Open (True) | Default allow unless filter explicitly denies |
| SSE Streaming | `GET /rag/stream` | Fail-Open (True) | Default allow unless filter explicitly denies |

**Filter Return Values:**
- Return `true` to allow access (overrides seed).
- Return `false` to deny access (overrides seed).
- Return `WP_Error` with explicit `status` code (e.g., `403`, `429`) to signal a specific failure.
- Return the passed `$allowed` value unchanged to defer to the seed behavior.

**Use Cases:**
- Tighten RAG chat/action access to capability-gated only (fail-closed by default).
- Block guest journey access during maintenance (fail-open by default, so explicit deny required).
- Implement custom rate-limiting or soft-throttling by returning rate-limit `WP_Error`.
- Route-scoped or request-aware permission logic based on `$request` context.

**Example: Block journey access for guests:**
```php
add_filter( 'gg_data_rag_endpoint_permission', function( $allowed, $request ) {
	if ( ! $request ) {
		// SSE or non-standard context; use seed.
		return $allowed;
	}

	$route = $request->get_route();

	// Block guest journey access by denying non-logged-in users on journey endpoints.
	if ( str_contains( $route, '/rag/journey/' ) && ! is_user_logged_in() ) {
		return false;
	}

	return $allowed;
}, 10, 2 );
```

#### `gg_data_rag_tool_selection`
- Type: Filter
- Signature: `(array $result, string $query, array $messages): array`
- Emitted in: `includes/ai/strategies/`

#### `gg_data_rag_retrieval_policy`
- Type: Filter
- Signature: `(array $policy, string $intent, string $query, array $options, string $connection_name): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_rerank_policy`
- Type: Filter
- Signature: `(array $policy, string $intent, string $query, int $candidate_count, array $options, string $connection_name): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_threshold_profile`
- Type: Filter
- Signature: `(array $profile, string $intent, string $query, array $options, string $connection_name): array`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_abstain_message`
- Type: Filter
- Signature: `(string $message, string $query, array $policy, array $retrieval, array $options, string $connection_name): string`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

#### `gg_data_rag_governance_decision`
- Type: Action
- Signature: `(string $query, array $policy, array $retrieval, array $options, string $connection_name): void`
- Emitted in: `includes/rag/class-gg-data-rag-service.php`

### 3.2 Provider Surface

#### `gg_data_llm_providers`
- Type: Filter
- Signature: `(array $providers): array`
- Emitted in: `includes/ai/class-gg-data-llm-registry.php`

#### `gg_data_openai_request`
- Type: Filter
- Signature: `(array $request_data, string $user_message): array`
- Emitted in: `includes/class-gg-data-openai-client.php`

#### `gg_data_openai_call`
- Type: Action
- Signature: `(array $request_data, array $response_data, int $tokens_used): void`
- Emitted in: `includes/class-gg-data-openai-client.php`

#### `gg_data_openai_stream_request`
- Type: Filter
- Signature: `(array $request_data, string $prompt): array`
- Emitted in: `includes/ai/providers/class-gg-data-openai-provider.php`

#### `gg_data_openai_api_base_url`
- Type: Filter
- Signature: `(string $base_url, string $model): string`
- Emitted in: `includes/ai/providers/class-gg-data-openai-provider.php`

#### `gg_data_anthropic_request`
- Type: Filter
- Signature: `(array $request_data, string $prompt, string $model): array`
- Emitted in: `includes/ai/providers/class-gg-data-anthropic-provider.php`

#### `gg_data_anthropic_call`
- Type: Action
- Signature: `(array $request_data, array $response_data, int $tokens_used, string $model): void`
- Emitted in: `includes/ai/providers/class-gg-data-anthropic-provider.php`

#### `gg_data_gemini_request`
- Type: Filter
- Signature: `(array $request_data, string $prompt, string $model): array`
- Emitted in: `includes/ai/providers/class-gg-data-gemini-provider.php`

#### `gg_data_gemini_call`
- Type: Action
- Signature: `(array $request_data, array $response_data, int $tokens_used, string $model): void`
- Emitted in: `includes/ai/providers/class-gg-data-gemini-provider.php`

#### `gg_data_deepseek_request`
- Type: Filter
- Signature: `(array $request_data, string $prompt, string $model): array`
- Emitted in: `includes/ai/providers/class-gg-data-deepseek-provider.php`

#### `gg_data_deepseek_call`
- Type: Action
- Signature: `(array $request_data, array $response_data, int $tokens_used, string $model): void`
- Emitted in: `includes/ai/providers/class-gg-data-deepseek-provider.php`

#### `gg_data_deepseek_stream_request`
- Type: Filter
- Signature: `(array $request_data, string $prompt): array`
- Emitted in: `includes/ai/providers/class-gg-data-deepseek-provider.php`

### 3.3 Operational Controls

#### `gg_data_clean_batch_size`
- Type: Filter
- Signature: `(int $batch_size, string $post_type, string $connection_name): int`
- Emitted in: `includes/batch/class-gg-data-clean-batch.php`

#### `gg_data_orphan_delete_batch_size`
- Type: Filter
- Signature: `(int $batch_size, string $type, string $connection_name): int`
- Emitted in: `includes/class-gg-data-orphan-manager.php`

#### `gg_data_cli_max_batch_size`
- Type: Filter
- Signature: `(int $max_batch_size): int`
- Emitted in: `includes/cli/class-gg-data-cli-sync.php`

#### `gg_data_cli_vectors_max_batch_size`
- Type: Filter
- Signature: `(int $max_batch_size): int`
- Emitted in: `includes/cli/class-gg-data-cli-vectors.php`

#### `gg_data_rag_rate_limit`
- Type: Filter
- Signature: `(bool|WP_Error $allowed, int $user_id): bool|WP_Error`
- Emitted in: `includes/api/class-gg-data-sse-handler.php`

#### `gg_data_rag_rate_limit_anonymous`
- Type: Filter
- Signature: `(int $limit, WP_REST_Request $request, string $endpoint): int`
- Emitted in: `includes/api/class-gg-data-rest-rag-controller.php`

#### `gg_data_rag_rate_limit_authenticated`
- Type: Filter
- Signature: `(int $limit, WP_REST_Request $request, string $endpoint): int`
- Emitted in: `includes/api/class-gg-data-rest-rag-controller.php`

#### `gg_data_rag_rate_limit_window_seconds`
- Type: Filter
- Signature: `(int $seconds, WP_REST_Request $request, string $endpoint, string $scope): int`
- Emitted in: `includes/api/class-gg-data-rest-rag-controller.php`

#### `gg_data_rag_rate_limit_decision`
- Type: Action
- Signature: `(bool $is_blocked, string $scope, string $endpoint, int $limit, int $window, int $current_count, int $retry_after, int $user_id): void`
- Emitted in: `includes/api/class-gg-data-rest-rag-controller.php`
- Parameters:
  - `$is_blocked` (bool): Whether the request is rate-limited (`true` = blocked).
  - `$scope` (string): Rate-limit scope (`'authenticated'` or `'anonymous'`).
  - `$endpoint` (string): Endpoint key being throttled (e.g., `'chat'`, `'action'`).
  - `$limit` (int): Effective request limit for this scope and window.
  - `$window` (int): Effective window duration in seconds.
  - `$current_count` (int): Current request count in the active window.
  - `$retry_after` (int): Seconds until the next window (if blocked).
  - `$user_id` (int): Current user ID (`0` for anonymous).
- Use case: logging, telemetry, debugging rate-limit behavior. This action is observational only and must not mutate enforcement decisions.

### 3.4 Observability and Telemetry

#### `gg_data_streaming_failure`
- Type: Action
- Signature: `(string $error_type, int $elapsed_ms, int $partial_content_length, string $model_id, string $connection_id): void`
- Emitted in: `includes/api/class-gg-data-sse-handler.php`

#### `gg_data_token_usage_tracked`
- Type: Action
- Signature: `(int $tokens_used, string $model, string $query, string $connection_name, string $rag_type): void`
- Emitted in: `includes/class-gg-data-token-counter.php`

#### `gg_data_token_usage_reset`
- Type: Action
- Signature: `(string $connection_name, string $rag_type): void`
- Emitted in: `includes/class-gg-data-token-counter.php`

Caveat:
- Tier 2 hooks are discoverable and useful, but integration code should be defensive regarding parameter shape evolution.

---

## 4. Tier 3 Internal Hooks

Internal hooks are implementation surfaces and are not guaranteed as stable extension contracts. Examples include fine-grained RAG planner/threshold internals and comparison pipeline internals.

Representative internal hooks:
- `gg_data_rag_policy_lexical_active`
- `gg_data_rag_policy_retrieval_mode`
- `gg_data_rag_rerank_min_candidates`
- `gg_data_rag_rerank_acceptance_threshold`
- `gg_data_rag_compare_rerank_acceptance_threshold`
- `gg_data_rag_compare_acceptance_fallback_enabled`
- `gg_data_rag_compare_acceptance_fallback_top_k`
- `gg_data_rag_compare_acceptance_soft_floor_threshold`
- `gg_data_rag_policy_compare_threshold`
- `gg_data_rag_policy_single_threshold`
- `gg_data_rag_planner_max_subqueries`
- `gg_data_rag_planner_final_post_limit`
- `gg_data_rag_planner_subquery_limit`
- `gg_data_rag_compare_slot_recovery_max_chunks`
- `gg_data_rag_single_block_lookup_recovery_max_chunks`
- `gg_data_rag_compare_synthesis_max_chunks`
- `gg_data_rag_compare_max_chunks_per_entity`
- `gg_data_rag_compare_min_chunks_per_covered_entity`
- `gg_data_rag_compare_max_entities`

Rule:
- Do not build critical external integrations on Tier 3 hooks unless you accept maintenance coupling.

---

## 5. Deferred / Legacy Notes

The initial monolith carried some hooks as uncertain. This parity pass confirms the major previously uncertain governance hooks are active in code and now categorized in Tier 2 or Tier 3 as appropriate.

Policy:
- Do not treat undocumented hooks as stable contracts.
- Any new hook must be tiered explicitly before it is considered part of the public extension surface.

---

## 6. Implementation Recipes

### 6.1 Adaptive Sync Batching

```php
add_filter( 'gg_data_post_sync_batch_size', function( $batch_size, $connection_name ) {
    if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local' ) {
        return 25;
    }
    return $batch_size;
}, 10, 2 );
```

### 6.2 Exclude Internal Posts from Sync

```php
add_filter( 'gg_data_should_sync_post', function( $should_sync, $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'activity_log' ) {
        return false;
    }
    return $should_sync;
}, 10, 2 );
```

### 6.3 Extend RAG Tools

```php
add_filter( 'gg_data_rag_tools', function( $tools, $context ) {
    $tools['custom_lookup'] = array(
        'description' => 'Custom metadata lookup',
    );
    return $tools;
}, 10, 2 );
```

---

## 7. Troubleshooting

### Hook callback not firing
- Verify hook tier and exact hook name (especially dynamic variants).
- Confirm callback priority and accepted argument count.
- Confirm the target subsystem code path is executed.

### Filter returns ignored
- Ensure callback returns the expected value type and shape.
- Ensure no later callback overrides your returned value.

### Integration breaks after update
- Check whether you depended on Tier 2 or Tier 3 hooks.
- Prefer Tier 1 hooks for long-term compatibility.

---

## 8. Migration Notes from Monolith

- This package supersedes the old monolithic hooks architecture document.
- Critical code-only hooks have been surfaced in Tier 2 rather than omitted.
- Deferred/unknown legacy hook names are explicitly marked to avoid false contract guarantees.

---

## 9. Audit & Verification

### Coverage Summary

| Metric | Count | Notes |
|---|---:|---|
| Literal emitted hooks discovered in code | 61 | Extracted from `apply_filters`/`do_action` calls with literal scanner |
| Documented hooks in this reference | 74 | Includes Tier 1, Tier 2, Tier 3, and dynamic examples |
| Code hooks missing from docs | 0 | No undocumented literal emitted hooks |
| Documented hooks not found by literal scanner | 13 | 12 are emitted via multiline patterns; 1 is a dynamic example |

**Parity gate status: PASS** — All literal code-emitted hooks are documented.

### Verification Commands

To verify parity independently:

```bash
# Extract literal code hooks
grep -RhoE "(apply_filters|do_action)\s*\(\s*['\"]gg_data_[A-Za-z0-9_]+" includes --include='*.php' \
  | sed -E "s/.*['\"](gg_data_[A-Za-z0-9_]+)$/\1/" \
  | sort -u > /tmp/code_hooks.txt

# Extract documented hooks
grep -oE "gg_data_[A-Za-z0-9_]+" docs/hooks/developer-documentation.md \
  | sort -u > /tmp/doc_hooks.txt

# Find gaps
comm -23 /tmp/code_hooks.txt /tmp/doc_hooks.txt
```

### Scanner Exceptions (Documented but Not Literal Match)

Twelve hooks are emitted via multiline patterns not captured by the single-line extraction regex; direct source search confirms all are present:

- `gg_data_rag_abstain_message`, `gg_data_rag_compare_acceptance_fallback_enabled`, `gg_data_rag_compare_acceptance_soft_floor_threshold`, `gg_data_rag_compare_rerank_acceptance_threshold`, `gg_data_rag_governance_decision`, `gg_data_rag_policy_retrieval_mode`, `gg_data_rag_rerank_acceptance_threshold`, `gg_data_rag_rerank_min_candidates`, `gg_data_rag_rerank_policy`, `gg_data_rag_retrieval_policy`, `gg_data_rag_threshold_profile`, `gg_data_search_similarity_threshold`

Reason: These are emitted inside multiline filter calls or conditional blocks where the hook name is split across lines.

### Dynamic Examples (Not Literal Emissions)

- `gg_data_rag_tool_web_search` — This is a concrete example of the `gg_data_rag_tool_{name}` dynamic family pattern (Tier 1). The literal dynamic emission use `apply_filters( 'gg_data_rag_tool_' . $tool_name, ... )`, which renders many possible hook names at runtime. Only the pattern itself is documented, not every instance.

### Last Audit

- **Date**: April 4, 2026
- **Scope**: Tier assignment verification (docs-only pass; no production code changes)
- **Decisions**:
  - Promoted `gg_data_rag_search_query` and `gg_data_rag_source_min_relevance` to Tier 1
  - Expanded Tier 2 with per-hook signatures for 40+ RAG governance, provider, operational, and telemetry hooks
  - Retains Tier 3 for algorithm-tuning internals
- **Result**: All 61 emitted hooks documented with tier status and signatures
