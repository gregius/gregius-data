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
- Extension points (factory registration and WordPress hooks/filters)
- Integration recipes and troubleshooting

Not covered:
- End-user dashboard workflows
- Product feature strategy
- Full REST API reference for non-provider modules

## 3. Quick Start

### 3.1 Register a Custom Database Provider

1. Implement the full `Gregius_Data_DB_Provider` interface.
2. Return standardized response arrays with at least `success` and `message`.
3. Register the provider class with the factory.

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
}

add_action( 'plugins_loaded', function() {
	Gregius_Data_Provider_Factory::register_provider( 'mydb', 'My_Custom_DB_Provider' );
} );
```

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
- `Gregius_Data_Provider_Factory::get_supported_types()`
- `Gregius_Data_Provider_Factory::is_supported( $database_type )`
- `Gregius_Data_Provider_Factory::get_provider_class( $database_type )`
- `Gregius_Data_Provider_Factory::register_provider( $database_type, $provider_class )`

Built-in type map includes:
- Canonical: `postgresql`, `postgrest`
- Backward compatible aliases: `pdo`, `supabase`

Registration constraints:
- Type must be lowercase alphanumeric/underscore
- Class must exist
- Class must implement `Gregius_Data_DB_Provider`

Traceability:
- PA-FR-06 to PA-FR-08
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
- Native providers: `openai`, `deepseek`, `anthropic`, `gemini`
- Fallback for unsupported providers/models: `Gregius_Data_Prompt_Tool_Strategy`

Traceability:
- PA-FR-18 to PA-FR-23

### 4.6 Connection Lifecycle

Source: [includes/class-gregius-data-connection-manager.php](../../includes/class-gregius-data-connection-manager.php)

Public methods:
- `init_connections()`
- `register_connection( $name, $config )`
- `get_provider( $connection_name = 'default' )`
- `get_connection( $connection_name = 'default', $force_reconnect = false )` (legacy)
- `test_connection( $connection_name )`
- `cleanup_connections()`

Lifecycle behavior:
- Active configs are loaded on `wp_loaded`.
- Provider instances are cached per named connection.
- Cleanup is executed on `shutdown`.

Traceability:
- PA-FR-24 to PA-FR-25
- PA-OR-04

### 4.7 PostgREST/Supabase Key Normalization Contract

Runtime contract:
- PostgREST provider runtime resolution uses canonical keys only: `project_url`, `publishable_key`, `secret_key`.
- Runtime operations do not depend on legacy Supabase aliases.

Backward compatibility boundary:
- Legacy aliases `api_key` and `service_role_key` are accepted only when ingesting/saving connection config.
- Ingestion must normalize to canonical keys before provider runtime calls.

Implementation note:
- Shared Supabase header generation is centralized in `Gregius_Data_PostgREST_Provider` and should be reused by REST/controller callers instead of duplicating inline header assembly.

Traceability:
- PA-DR-09 to PA-DR-10

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

Recipe: Intercept tool selection for observability

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

Issue: Factory registration fails
- Symptom: exception during `register_provider()`
- Check: class exists and autoload/includes executed before registration
- Check: class implements `Gregius_Data_DB_Provider`
- Check: type string format (`^[a-z0-9_]+$`)

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
- Add registration path (factory or filter)
- Add/update tests for success and failure paths
- Add/update docs in this file, [srs.md](srs.md), and [architecture.md](architecture.md) if behavior changes

## 8. API Quick Lookup

DB extension APIs:
- `Gregius_Data_Provider_Factory::register_provider( $database_type, $provider_class )`
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
