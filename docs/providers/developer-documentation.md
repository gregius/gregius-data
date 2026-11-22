# Provider Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to use, extend, and contribute to the provider subsystem in Gregius Data.

It is written for two audiences:
- Core contributors maintaining provider internals
- Third-party developers integrating custom providers into the plugin

This document complements:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Database provider contract and lifecycle
- AI provider contract and lifecycle
- Tool-calling strategy contract and factory behavior
- Extension points (WordPress filters for AI provider registration)
- Integration recipes and troubleshooting

Not covered:
- End-user dashboard workflows
- Product feature strategy
- Full REST API reference for non-provider modules

## 3. Quick Start

### 3.1 Add a Custom Database Provider

1. Implement the full `Gregius_Data_DB_Provider` interface (16 methods).
2. Return standardized response arrays with at least `success` and `message`.
3. Add the provider class name to the factory's built-in type map.

```php
class My_Custom_DB_Provider implements Gregius_Data_DB_Provider {
	public function connect( $connection_config ) { return array( 'success' => true, 'message' => 'Connected' ); }
	public function disconnect() { return array( 'success' => true, 'message' => 'Disconnected' ); }
	public function test_connection( $connection_config ) { return array( 'success' => true, 'message' => 'OK' ); }
	public function sync_post( $post_id, $post_data ) { return array( 'success' => true, 'message' => 'Synced' ); }
	public function delete_post( $post_id ) { return array( 'success' => true, 'message' => 'Deleted' ); }
	public function get_ids( $table, $limit = 100, $offset = 0, $conditions = array(), $select = 'id' ) { return array(); }
	public function delete_ids( $table, $ids, $id_column = 'id' ) { return array(); }
	public function delete_term_relationship( $object_id, $term_taxonomy_id ) { return array(); }
	public function get_schema_version() { return array( 'success' => true, 'message' => 'Schema version fetched', 'version' => '1.0.0' ); }
	public function create_schema( $schema_config = array() ) { return array( 'success' => true, 'message' => 'Schema created' ); }
	public function count_records( $table, $where = array() ) { return array( 'success' => true, 'count' => 0 ); }
	public function select( $table, $conditions = array(), $order = null, $select = '*', $limit = null ) { return array(); }
	public function insert( $table, $data ) { return true; }
	public function delete( $table, $conditions = array() ) { return true; }
	public function bulk_insert( $table, $rows ) { return 0; }
	public function upsert( $table, $data, $conflict_columns = array() ) { return true; }
}
```

> **Note:** `Gregius_Data_Provider_Factory` does not expose a runtime `register_provider()` method in 1.0.3. The type map is a static private array (canonical `postgresql`, `postgrest`; legacy aliases `pdo`, `supabase`). A third-party DB provider therefore requires a core change to the factory's type map rather than a filter or hook registration.

### 3.2 Register a Custom AI Provider

1. Implement `Gregius_Data_AI_Provider_Interface`.
2. Register your provider through the `gregius_data_llm_providers` filter.

```php
class My_Custom_AI_Provider implements Gregius_Data_AI_Provider_Interface {
	public function get_id(): string { return 'myai'; }
	public function get_name(): string { return 'My AI'; }
	public function get_capabilities(): array { return array( 'llm' ); }

	public function generate_text( string $prompt, array $options = array() ): array|WP_Error {
		return array(
			'text'        => 'Response from custom provider.',
			'tokens_used' => 42,
			'model'       => $options['model'] ?? 'default-model',
		);
	}

	public function get_llm_models(): array { return array( 'default-model' => 'Default Model' ); }
	public function get_vision_models(): array { return array(); }
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error { return new WP_Error( 'unsupported', 'Embeddings not supported.' ); }
	public function get_embedding_models(): array { return array(); }
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error { return new WP_Error( 'unsupported', 'Rerank not supported.' ); }
	public function get_rerank_models(): array { return array(); }
}

add_filter( 'gregius_data_llm_providers', function( $providers ) {
	$providers['myai'] = new My_Custom_AI_Provider();
	return $providers;
} );
```

## 4. Core Contributor Reference

### 4.1 Database Provider Contract

Source: [includes/providers/interface-gregius-data-db-provider.php](../../includes/providers/interface-gregius-data-db-provider.php)

Required methods:
- `connect( $connection_config )`
- `disconnect()`
- `test_connection( $connection_config )`
- `sync_post( $post_id, $post_data )`
- `delete_post( $post_id )`
- `get_ids( $table, $limit = 100, $offset = 0, $conditions = array(), $select = 'id' )`
- `delete_ids( $table, $ids, $id_column = 'id' )`
- `delete_term_relationship( $object_id, $term_taxonomy_id )`
- `get_schema_version()`
- `create_schema( $schema_config = array() )`
- `count_records( $table, $where = array() )`
- `select( $table, $conditions = array(), $order = null, $select = '*', $limit = null )`
- `insert( $table, $data )`
- `delete( $table, $conditions = array() )`
- `bulk_insert( $table, $rows )`
- `upsert( $table, $data, $conflict_columns = array() )`

Response contract:
- Always return arrays with at least:
- `success` (bool)
- `message` (string)
- Include operation-specific payload fields on success.

Traceability:
- PA-FR-01 to PA-FR-10
- PA-DR-01 to PA-DR-03

### 4.2 Database Provider Factory

Source: [includes/providers/class-gregius-data-provider-factory.php](../../includes/providers/class-gregius-data-provider-factory.php)

Public API:
- `Gregius_Data_Provider_Factory::create_provider( $database_type, $connection_config = array(), $connection_name = null )`

Built-in type map (static, no runtime registration):
- Canonical: `postgresql` → `Gregius_Data_PostgreSQL_Provider`, `postgrest` → `Gregius_Data_PostgREST_Provider`
- Backward compatible aliases: `pdo` → `Gregius_Data_PostgreSQL_Provider`, `supabase` → `Gregius_Data_PostgREST_Provider`

Validation behavior:
- The type is lowercased/trimmed and checked against the built-in map; an unknown type throws an `Exception` listing the supported types.
- The mapped class is verified to exist and to implement `Gregius_Data_DB_Provider` before instantiation.

Traceability:
- PA-FR-06, PA-FR-08
- PA-DR-06

### 4.3 AI Provider Contract

Source: [includes/interfaces/interface-gregius-data-ai-provider.php](../../includes/interfaces/interface-gregius-data-ai-provider.php)

Required methods:
- `get_id(): string`
- `get_name(): string`
- `get_capabilities(): array`
- `generate_text( string $prompt, array $options = array() ): array|WP_Error`
- `get_llm_models(): array`
- `get_vision_models(): array`
- `generate_embedding( string $text, array $options = array() ): array|WP_Error`
- `get_embedding_models(): array`
- `rerank( string $query, array $documents, array $options = array() ): array|WP_Error`
- `get_rerank_models(): array`

Optional capability method:
- Streaming is optional and checked via `method_exists( $provider, 'generate_text_stream' )`.
- `evaluate( string $state, array $questions, array $options = array() ): array|WP_Error` and `get_evaluator_models(): array` are optional, checked via `method_exists()` — only the TypeSafe provider implements them. A provider advertises the capability via `get_capabilities()` returning `'evaluator'`.

TypeSafe (Jev):
- `includes/ai/providers/class-gregius-data-typesafe-provider.php` — decision model (TypeSafe System One) that returns typed judgments, not prose.
- `evaluate()` sends `POST https://api.typesafe.ai/v1/systemone` with `state` + `questions` (Noul/Choice/Score) and returns `answers` keyed by question id. The RAG service thresholds the `noul` probability into `correct | insufficient`. The provider is primitive-agnostic — it forwards `questions` verbatim — but the RAG evaluator currently issues only a `noul` question; `choice` and `score` are provider-supported yet not wired to the evaluator prompt (roadmap: let the evaluation prompt select the primitive and supply its criteria/options/levels).
- Jev is English-primary, text-only; the versioned model `jev-1.13.0` is pinned by default.

Traceability:
- PA-FR-11 to PA-FR-17
- PA-DR-04

### 4.4 AI Registry and Client Flow

Sources:
- [includes/ai/class-gregius-data-llm-registry.php](../../includes/ai/class-gregius-data-llm-registry.php)
- [includes/ai/class-gregius-data-ai-client.php](../../includes/ai/class-gregius-data-ai-client.php)
- [includes/ai/class-gregius-data-ai-request.php](../../includes/ai/class-gregius-data-ai-request.php)

Flow:
1. `Gregius_Data_Ai_Client::prompt( $prompt )` creates `Gregius_Data_Ai_Request`.
2. Chain request options (`setSystemMessage`, `usingProvider`, `usingModel`, `usingConnection`, `withMaxTokens`, `withMessages`).
3. `generateText()` or `generateTextWithMetadata()` resolves provider via registry.
4. Provider executes `generate_text()` and returns normalized output.

Notable behavior:
- Default provider ID is `openai`.
- Default connection name is `default`.
- `generateText()` returns plain text or `WP_Error`.
- `generateTextWithMetadata()` returns structured payload (`text`, `reasoning_content`, `usage`, `model`, `provider`, `raw_response`) or `WP_Error`.

Traceability:
- PA-FR-13 to PA-FR-16

### 4.5 Tool Strategy Contract and Selection

Sources:
- [includes/ai/strategies/interface-gregius-data-tool-calling-strategy.php](../../includes/ai/strategies/interface-gregius-data-tool-calling-strategy.php)
- [includes/ai/strategies/class-gregius-data-tool-strategy-factory.php](../../includes/ai/strategies/class-gregius-data-tool-strategy-factory.php)

Strategy interface methods:
- `select_tool( string $query, array $messages, array $tools, string $model_id ): array|WP_Error`
- `format_tools( array $tools ): array`
- `parse_response( $response ): array`
- `get_id(): string`

Factory behavior:
- `create( string $provider, ?bool $supports_tools = null )`
- `create_for_model( string $model_id )`
- `provider_supports_tools( string $provider ): bool`
- `get_available_strategies(): array`
- Native providers: `openai`, `deepseek`, `anthropic`, `gemini`
- Fallback for unsupported providers/models: `Gregius_Data_Prompt_Tool_Strategy`

Traceability:
- PA-FR-18 to PA-FR-23

### 4.6 Connection Lifecycle

Source: [includes/class-gregius-data-connection-manager.php](../../includes/class-gregius-data-connection-manager.php)

Public methods:
- `get_provider( $connection_name = 'default' )`
- `test_connection( $connection_name )`
- `get_connection_health( $connection_name, $force_check = false )`
- `get_all_connection_health( $force_check = false )`
- `cleanup_connections()`
- `get_connection_stats()`

Lifecycle behavior:
- Provider instances are created lazily via the factory and cached per named connection in `get_provider()`.
- A cached provider is reconnected when `is_connected()` reports false.
- Cleanup (disconnect + cache clear) is registered on the `shutdown` hook in the constructor.
- Connection health is cached for 5 minutes; `get_all_connection_health()` iterates every configured connection.

Traceability:
- PA-FR-24 to PA-FR-25
- PA-OR-04

### 4.7 PostgREST/Supabase Key Normalization Contract

Runtime contract:
- PostgREST provider runtime resolution uses canonical keys only: `project_url`, `publishable_key`, `secret_key`.
- The constructor and `connect()` read `project_url`, `publishable_key`, and `secret_key` directly.

Canonical helpers (static):
- `get_runtime_supabase_config( $connection_config )` — returns canonical keys or a `WP_Error` when `project_url` or `publishable_key` is missing.
- `build_supabase_headers( $publishable_key, $secret_key = '', $use_secret = false )` — builds `apikey` / `Authorization` / `Content-Type` headers. The key is sent as a `Bearer` token only when it contains a `.` (non-JWT Supabase keys are not sent as Bearer).

Implementation note:
- Shared Supabase header generation is centralized in `Gregius_Data_PostgREST_Provider::build_supabase_headers()` and should be reused by REST/controller callers instead of duplicating inline header assembly.
- `normalize_connection_config()` only re-assigns the canonical `publishable_key` / `secret_key` fields; it does not map legacy `api_key` / `service_role_key` aliases.

Traceability:
- PA-DR-09

### 4.8 Model Catalog

Source: [includes/class-gregius-data-model-catalog.php](../../includes/class-gregius-data-model-catalog.php)

`Gregius_Data_Model_Catalog` is the curated, per-provider source of truth for model capability metadata (vision support, embedding dimension, rerank context). Model lifecycle (active/retired) is derived live by the discovery service, not stored here.

Static methods:
- `all(): array` — full catalog keyed by provider id, then model id.
- `models( string $provider ): array` — all entries for a provider.
- `llm_models( string $provider ): array` — model-id => display-name map.
- `vision_models( string $provider ): array` — vision-capable LLM model ids.
- `embedding_models( string $provider ): array` — model-id => config map.
- `rerank_models( string $provider ): array` — model-id => config map.
- `evaluator_models( string $provider ): array` — model-id => config map.
- `context_limits(): array` — model-id => context-window token limit.
- `max_tokens_map(): array` — model-id => max output tokens.
- `embedding_dimensions(): array` — flat model-id => dimensions map.

### 4.9 Model Registry

Source: [includes/class-gregius-data-model-registry.php](../../includes/class-gregius-data-model-registry.php)

`Gregius_Data_Model_Registry` stores model configurations in the settings table using categories `llm_model`, `embeddings_model`, `rerank_model`, and `evaluator_model`, with a global scope key.

Public methods:
- `add_model( $connection_name, array $model_config )`
- `get_model( $connection_name, $model_key )`
- `get_models( $connection_name, $model_type = null )`
- `update_usage( $connection_name, $model_key, $tokens )`
- `get_usage( $connection_name, $model_key )`
- `delete_model( $connection_name, $model_key )`
- `model_exists( $connection_name, $model_key )`
- `update_model( $connection_name, $model_key, array $updates )`
- `set_model_lifecycle( $model_key, $status )`
- `get_models_by_provider( $connection_name, $provider, $model_type = null )`
- `get_active_models( $connection_name, $model_type = null )`
- `get_active_vision_models( $connection_name )`
- `get_document_model( $connection_name )`

### 4.10 Model Discovery

Source: [includes/class-gregius-data-model-discovery.php](../../includes/class-gregius-data-model-discovery.php)

`Gregius_Data_Model_Discovery` resolves live model availability by calling a provider's optional `list_remote_models()` method (the provider's `GET /models` endpoint), cached via a 300-second transient.

Public methods:
- `__construct( ?callable $provider_resolver = null )`
- `fetch_live_models( string $provider_id, string $api_key, bool $force = false ): array|WP_Error`
- `resolve_lifecycle( string $provider_id, string $api_key, string $model_id ): string` — `'active'` | `'retired'` | `'unknown'`
- `flush( string $provider_id, string $api_key ): void`

### 4.11 Connection Model Manager

Source: [includes/class-gregius-data-connection-model-manager.php](../../includes/class-gregius-data-connection-model-manager.php)

`Gregius_Data_Connection_Model_Manager` manages per-connection active embedding model associations (stored under the `vectors` settings category).

Public methods:
- `get_connection_models( string $connection_name ): array`
- `add_model_to_connection( string $connection_name, string $model_key )`
- `remove_model_from_connection( string $connection_name, string $model_key )`

### 4.12 Connection Health

Source: [includes/class-gregius-data-connection-health.php](../../includes/class-gregius-data-connection-health.php)

`Gregius_Data_Connection_Health` tracks per-connection health status and sends email alerts after 3 consecutive failures (recovery email on restore).

Public methods:
- `record_success( $connection_name = null )`
- `record_failure( $error_message, $connection_name = null )`
- `get_health_status( $connection_name = null )`
- `perform_health_check( $connection_name = null )`
- `reset_health_status( $connection_name = null )`

## 5. Third-Party Integration Guide

### 5.1 Extension Points (Hooks and Filters)

Verified hook/filter surface:
- `gregius_data_llm_providers` (filter): register AI provider instances
- `gregius_data_openai_stream_request` (filter): mutate OpenAI stream payload
- `gregius_data_openai_api_base_url` (filter): override OpenAI base URL
- `gregius_data_anthropic_request` (filter): mutate Anthropic request payload
- `gregius_data_deepseek_request` (filter): mutate DeepSeek request payload
- `gregius_data_deepseek_stream_request` (filter): mutate DeepSeek stream payload
- `gregius_data_gemini_request` (filter): mutate Gemini request payload
- `gregius_data_rag_tool_selection` (filter): intercept normalized tool selection
- `gregius_data_anthropic_call` (action): observe Anthropic call telemetry
- `gregius_data_deepseek_call` (action): observe DeepSeek call telemetry
- `gregius_data_gemini_call` (action): observe Gemini call telemetry

### 5.2 AI Client Usage Pattern

```php
$result = Gregius_Data_Ai_Client::prompt( 'Summarize this post.' )
	->setSystemMessage( 'You are a precise WordPress assistant.' )
	->usingProvider( 'openai' )
	->usingModel( 'gpt-4o-mini' )
	->usingConnection( 'editorial_openai' )
	->withMaxTokens( 300 )
	->generateTextWithMetadata();

if ( is_wp_error( $result ) ) {
	// Handle provider or transport error.
} else {
	$text  = $result['text'];
	$usage = $result['usage'];
}
```

### 5.3 DB Provider Method Pattern

```php
$manager  = new Gregius_Data_Connection_Manager();
$provider = $manager->get_provider( 'default' );

if ( ! $provider ) {
	// Handle missing connection/provider.
	return;
}

$result = $provider->get_schema_version();

if ( empty( $result['success'] ) ) {
	// Handle provider-level error.
}
```

Note: semantic search and vector generation are deliberately excluded from the DB provider interface (see PA-FR-04); retrieval is provided by the search service, not the provider.

### 5.4 Recipes

Recipe: Override OpenAI API base URL

```php
add_filter( 'gregius_data_openai_api_base_url', function( $base_url, $model ) {
	if ( str_starts_with( $model, 'gpt-' ) ) {
		return 'https://proxy.example.com/openai/v1';
	}
	return $base_url;
}, 10, 2 );
```

Recipe: Add custom logging around Gemini calls

```php
add_action( 'gregius_data_gemini_call', function( $request_data, $response_data, $tokens_used, $model ) {
	error_log( sprintf( 'Gemini call model=%s tokens=%d', $model, (int) $tokens_used ) );
}, 10, 4 );
```

Recipe: Intercept tool selection for diagnostics

```php
add_filter( 'gregius_data_rag_tool_selection', function( $result, $query, $messages ) {
	// Add diagnostics without changing behavior.
	$result['diagnostic'] = array(
		'query_length' => strlen( $query ),
		'message_count' => count( $messages ),
	);
	return $result;
}, 10, 3 );
```

## 6. Troubleshooting

Issue: AI provider not found
- Symptom: `gregius_data_provider_not_found` WP_Error
- Check: ensure your provider is added through `gregius_data_llm_providers`
- Check: provider key used in `usingProvider()` matches registry key exactly

Issue: Factory creation fails
- Symptom: `Exception` from `create_provider()` for an unsupported type or a missing/invalid provider class
- Check: the type string matches a built-in map entry (`postgresql`, `postgrest`, `pdo`, `supabase`)
- Check: the mapped class exists and implements `Gregius_Data_DB_Provider`

Issue: Tool strategy unexpectedly falls back to prompt mode
- Check: provider is one of `openai`, `deepseek`, `anthropic`, `gemini`
- Check: model configuration `supports_tools` was not set to false
- Check: model exists in `Gregius_Data_Model_Registry`

Issue: Connection-specific AI credentials are not applied
- Check: request uses `usingConnection( 'name' )`
- Check: named connection exists in plugin settings
- Check: provider supports the capability being used

Issue: HTTP provider performance is poor
- Check: use RPC consolidation paths where available
- Check: avoid repeated single-row HTTP operations in loops
- Check: confirm network latency and timeout values

## 7. Contributor Checklist

When adding a new provider:
- Implement required interface methods fully
- Return standardized success/error structures
- Avoid logging credentials
- Add a registration path (AI providers: `gregius_data_llm_providers` filter; DB providers: extend the factory's built-in type map)
- Add/update tests for success and failure paths
- Add/update docs in this file, [srs.md](srs.md), and [architecture.md](architecture.md) if behavior changes

## 8. API Quick Lookup

DB extension APIs:
- `Gregius_Data_Provider_Factory::create_provider( $database_type, $connection_config = array(), $connection_name = null )`

AI extension APIs:
- `add_filter( 'gregius_data_llm_providers', ... )`
- `Gregius_Data_LLM_Registry::get_provider( $provider_id )`
- `Gregius_Data_Ai_Client::prompt( string $prompt )`

Tool strategy APIs:
- `Gregius_Data_Tool_Strategy_Factory::create_for_model( string $model_id )`
- `Gregius_Data_Tool_Strategy_Factory::create( string $provider, ?bool $supports_tools = null )`

## 9. Traceability Summary

This document supports implementation and integration understanding for these SRS groups:
- Functional requirements: PA-FR-01 to PA-FR-25
- Data/contract requirements: PA-DR-01 to PA-DR-10
- Operational requirements: PA-OR-01 to PA-OR-07
- Quality requirements: PA-QR-01 to PA-QR-05
