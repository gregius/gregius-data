# Vectors and Embeddings Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to use, operate, and extend the Gregius Data vectors and embeddings subsystem.

Audience:
- Core contributors maintaining vector generation, strategy routing, and vocabulary operations
- Integrators working with model configuration and connection-level embedding behavior
- Engineers validating vector contracts consumed by Search and RAG

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Vector orchestration and strategy contracts
- Vocabulary lifecycle endpoints and behaviors
- Vector queue, generation, status, and clear endpoints
- Connection-model association endpoints
- Row-per-embedding storage contract and integration boundaries

Not covered:
- Search ranking internals and query orchestration
- RAG answer synthesis behavior
- Full schema lifecycle and non-vector table migration policy

## 3. Key Components

### 3.1 Vector Generator (`GG_Data_Vector_Generator`)

Source: [../../includes/vectors/class-gg-data-vector-generator.php](../../includes/vectors/class-gg-data-vector-generator.php)

Core responsibilities:
- Resolve model configuration from registry by connection + model key.
- Select generation strategy by `supports_model()` contract.
- Execute generation and return normalized result payloads.
- Log orchestration lifecycle and outcomes.

Notable behavior:
- Built-in strategies are registered at construction time.
- Model lookup supports fallback to global `gregius-data` scope when needed.

### 3.2 Vector Strategy Interface (`GG_Data_Vector_Strategy_Interface`)

Source: [../../includes/vectors/interface-gg-data-vector-strategy.php](../../includes/vectors/interface-gg-data-vector-strategy.php)

Core contract:
- `generate( array $model, int $batch_size, string $connection_name ): array`
- `get_id(): string`
- `get_name(): string`
- `supports_model( array $model ): bool`

Required generation response keys:
- `success`
- `message`
- `processed`
- `failed`
- `total_tokens`

### 3.3 Vocabulary Manager (`GG_Data_Vocabulary_Manager`)

Source: [../../includes/vectors/class-gg-data-vocabulary-manager.php](../../includes/vectors/class-gg-data-vocabulary-manager.php)

Core responsibilities:
- Build and cache vocabulary from `wp_posts_clean` corpus.
- Route execution by connection provider type (PDO vs PostgREST/Supabase compatible).
- Validate vocabulary readiness and drift state.
- Clear cached vocabulary artifacts.

### 3.4 Vector Queue REST Controller (`GG_Data_REST_Vector_Queue_Controller`)

Source: [../../includes/api/class-gg-data-rest-vector-queue-controller.php](../../includes/api/class-gg-data-rest-vector-queue-controller.php)

Core responsibilities:
- Expose queue inspection and vector generation controls.
- Expose status and posts-list diagnostics.
- Expose vector clear operation.

### 3.5 Vocabulary REST Controller (`GG_Data_REST_Vocabulary_Controller`)

Source: [../../includes/api/class-gg-data-rest-vocabulary-controller.php](../../includes/api/class-gg-data-rest-vocabulary-controller.php)

Core responsibilities:
- Expose vocabulary prepare/status/cache-clear operations.
- Return connection-scoped status metadata for operators.

### 3.6 Connection Models REST Controller (`GG_Data_REST_Connection_Models_Controller`)

Source: [../../includes/api/class-gg-data-rest-connection-models-controller.php](../../includes/api/class-gg-data-rest-connection-models-controller.php)

Core responsibilities:
- List active models per connection.
- Add/remove model associations per connection.
- Mask API key material in response payloads.

### 3.7 HashingTF Generator (`GG_Data_HashingTF_Embeddings`) and Strategy (`GG_Data_HashingTF_Strategy`)

Sources:
- [../../includes/vectors/class-gg-data-hashingtf-embeddings.php](../../includes/vectors/class-gg-data-hashingtf-embeddings.php)
- [../../includes/vectors/strategies/class-gg-data-hashingtf-strategy.php](../../includes/vectors/strategies/class-gg-data-hashingtf-strategy.php)

Core responsibilities:
- Generate 1024-dimensional vectors via PHP-native MurmurHash3 feature hashing.
- Require no vocabulary preparation — generation can run immediately after schema creation.
- Use signed hashing (sign bit from hash byte) to reduce collision accumulation bias.
- Apply field-type weighting (title 1.5×, excerpt 1.2×, chunk 1.0×) and L2 normalization.
- Implement row-per-embedding storage under `wp_posts_hashingtf_murmur3_1024`.
- Target PDO (direct PostgreSQL) and Supabase/PostgREST provider paths.

Model key: `hashingtf-murmur3-1024`
Dimensions: 1024
Provider: `internal`
Vector table: `wp_posts_hashingtf_murmur3_1024`

## 4. REST Route Catalog (Vectors)

Base namespace: `/wp-json/gg-data/v1`

Vector queue and generation:
- `GET /vector-queue`
- `POST /vector-queue/generate/{id}`
- `POST /vectors/batch-generate`
- `POST /vectors/batch-delete`
- `GET /vectors/status`
- `DELETE /vectors`
- `GET /vectors/posts`

Batch delete contract notes:
- Designed for large vector tables where single-request deletion can hit host/proxy timeouts.
- Works for both PDO and PostgREST/Supabase connection types.
- Uses destructive pagination semantics: each request selects from the current head of remaining rows.
- Returns progress fields: `deleted`, `total_deleted`, `has_more`, `next_offset`, `duration_ms`, `errors`.
- Includes an anti-loop safety guard if zero rows are deleted while rows still remain.

Vocabulary management:
- `POST /vocabulary/prepare`
- `GET /vocabulary/status`
- `DELETE /vocabulary/cache`

Connection-model associations:
- `GET /connections/{connection}/vectors/models`
- `POST /connections/{connection}/vectors/models`
- `DELETE /connections/{connection}/vectors/models/{modelKey}`

Permissions:
- All routes above require admin authorization (`manage_options`) via controller permission callbacks.

## 5. Runtime Settings and Model Contracts

Primary model and association sources:
- [../../includes/class-gg-data-model-registry.php](../../includes/class-gg-data-model-registry.php)
- [../../includes/class-gg-data-connection-model-manager.php](../../includes/class-gg-data-connection-model-manager.php)

Model contract keys expected by vector strategies:
- `model_key`
- `provider`
- `provider_model_id`
- `model_type`
- `dimensions`
- `vector_table_name`
- `status`
- `config`

Behavior notes:
- `vector_table_name` determines target embedding table per model.
- `provider` and `model_type` drive strategy selection.
- Connection-model association controls which models are active for a connection.
- Internal models that use `tokenizer_version` (e.g. HashingTF) trigger regeneration by bumping the version constant, not by vocabulary invalidation.

## 6. Storage Contract

Vectors use a row-per-embedding schema pattern.

Expected per-row shape:
- `post_id`
- `field_type` (`title`, `excerpt`, `chunk`)
- `chunk_index` (nullable for title/excerpt)
- `embedding`
- `content_hash`
- `token_count`
- `status`
- `generated_at`
- Optional model-specific metadata (for example `model_used`, `vocabulary_version`)

Contract invariants:
- Uniqueness across `post_id + field_type + chunk_index`.
- Field-type semantics must remain stable for consumer weighting and deduplication flows.
- Model-specific metadata columns are independent per model family: `vocabulary_version` for TF-IDF; `model_used` for API-provider models; `tokenizer_version` for HashingTF. No column uniformity is required or enforced across model families.

## 7. Extension Points

### 7.1 Add a New Vector Strategy

Implement [../../includes/vectors/interface-gg-data-vector-strategy.php](../../includes/vectors/interface-gg-data-vector-strategy.php) and register strategy with generator startup wiring.

Practical guidance:
- Keep `supports_model()` deterministic and narrowly scoped.
- Return normalized result keys regardless of strategy internals.
- Preserve connection-aware behavior and error payload consistency.
- For stateless internal strategies (e.g. hashing), omit vocabulary readiness checks — generation must be immediately runnable without any preparation step.

### 7.2 Extend Provider/Model Coverage

Use provider and model registry contracts to add new embedding-capable models without changing vector orchestration API calls.

Practical guidance:
- Keep `vector_table_name` deterministic and migration-safe.
- Keep model metadata complete for strategy selection and reporting.

## 8. Integration Notes

### 8.1 Upstream Dependencies

- Cleaned content contracts from sync pipeline (`wp_posts_clean`).
- Chunk contracts from chunking flow (`wp_posts_chunks`).

### 8.2 Downstream Consumers

- Search SQL contracts consume vector tables and field-type conventions.
- RAG retrieval service consumes vector outputs through configured model/search pathways.

### 8.3 Provider-Path Behavior

- Vocabulary manager detects connection type and uses matching execution path.
- Maintain parity for vocabulary and generation outcomes across connection types.
- Batch deletion follows provider parity as well:
	- PDO: SQL `SELECT id`, `DELETE ... IN (...)`, `COUNT(*)`.
	- PostgREST/Supabase: provider `get_ids`, `delete_ids`, `count_records`.

## 9. Troubleshooting

### 9.1 Batch Generation Fails with Model Not Found

Checklist:
- Verify model exists in registry for connection.
- Verify expected fallback/global model scope when connection-local model is missing.
- Verify model key supplied to route matches registry entry exactly.

### 9.2 TF-IDF Generation Fails Due to Vocabulary State

Checklist:
- Run `POST /vocabulary/prepare` for target connection.
- Check `GET /vocabulary/status` for readiness and drift indicators.
- Re-run generation after cache refresh if status indicates regeneration need.

### 9.3 Connection-Based Endpoint Errors

Checklist:
- Verify connection exists and credentials are valid.
- Verify connection type has required transport credentials (PDO or API settings).
- Check plugin logs for provider-specific connection failures.

### 9.4 Vector Coverage Appears Incomplete

Checklist:
- Inspect `GET /vectors/status` and `GET /vectors/posts` for pending/failure indicators.
- Verify cleaned/chunked content exists for target posts.
- Validate model table name and table readiness for active model.

### 9.5 Batch Delete Stops Before Completion

Checklist:
- Use `POST /vectors/batch-delete` for large datasets instead of `DELETE /vectors`.
- Confirm target model table comes from registry `vector_table_name`.
- Tune `gg_data_vector_delete_batch_size` for environment timeout profile.
- If a response includes zero deleted rows with remaining rows, inspect `errors` field; backend now aborts to prevent infinite polling.
- Re-run delete to continue from remaining rows.

### 9.6 HashingTF Vectors Missing After Tokenizer Version Bump

Checklist:
- HashingTF does not use vocabulary state. If `TOKENIZER_VERSION` is incremented in `GG_Data_HashingTF_Embeddings`, existing rows are NOT automatically invalidated.
- Run `DELETE /vectors?connection_name=<name>&model_key=hashingtf-murmur3-1024` to clear stale vectors.
- Re-run batch generation to regenerate with the new tokenizer version.
- Confirm `tokenizer_version` column in `wp_posts_hashingtf_murmur3_1024` reflects the new constant value after completion.

## 10. Traceability (SRS Mapping)

- Orchestration and strategy behavior: [SRS: VEC-FR-01, VEC-FR-02, VEC-FR-03, VEC-DR-01, VEC-DR-02]
- Vocabulary lifecycle: [SRS: VEC-FR-04, VEC-FR-05, VEC-FR-06, VEC-OR-06]
- Route and permissions controls: [SRS: VEC-FR-07, VEC-FR-08, VEC-FR-09, VEC-OR-01]
- Storage and contract invariants: [SRS: VEC-FR-10, VEC-DR-03, VEC-DR-04, VEC-QR-02]
- Quality/parity expectations: [SRS: VEC-QR-03, VEC-QR-04, VEC-QR-05, VEC-QR-08]
