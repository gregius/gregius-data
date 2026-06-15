# Abilities Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how Gregius Data registers and executes WordPress Abilities API contracts.

Audience:
- Plugin developers maintaining abilities registration/callback logic
- Integrators consuming abilities via Abilities REST or MCP adapters
- Administrators validating ability availability and permissions

Companion docs:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)
- Prompt subsystem (identifier resolution and prompt contracts): [../prompt/developer-documentation.md](../prompt/developer-documentation.md)

## 2. Scope

Covered:
- `GG_Data_Abilities_Manager` lifecycle and callbacks
- Registered categories and abilities
- Input/output schemas and metadata behavior
- Permission boundaries
- Integration examples and troubleshooting

Not covered:
- Deep internals of RAG, settings, and model services
- MCP adapter implementation details outside plugin contracts

## 3. Quick Start

### 3.1 Bootstrapping abilities manager

```php
$abilities = new GG_Data_Abilities_Manager();
$abilities->init();
```

This registers category and ability callbacks on Abilities API lifecycle hooks.

### 3.2 Validate discovery endpoints

```bash
curl -X GET https://example.com/wp-json/wp-abilities/v1/abilities
curl -X GET https://example.com/wp-json/wp-abilities/v1/gregius-data/answer
```

### 3.3 Execute an ability

```bash
curl -X POST https://example.com/wp-json/wp-abilities/v1/gregius-data/answer/run \
  -H 'Content-Type: application/json' \
  -d '{
    "input": {
      "query": "What is WordPress?",
      "connection_name": "<your-connection>",
      "embedding_model": "<your-embedding-model>",
      "rerank_model": "<your-rerank-model>",
      "answer_model": "<your-llm-model>"
    }
  }'
```

## 4. Core Class Reference

### 4.1 `GG_Data_Abilities_Manager`

Source: `includes/class-gg-data-abilities-manager.php`

Lifecycle methods:
- `init()`
- `register_categories()`
- `register_abilities()`

Execution callbacks:
- `execute_rag_answer( $args )`
- `execute_list_connections( $args )`
- `execute_list_models( $args )`

Internal helpers:
- `resolve_prompt_identifier( $identifier )`
- `validate_prompt_post_id( $prompt_id )`

## 5. Registered Categories

- `ai`
  - Label: AI & Machine Learning
- `gregius-data`
  - Label: Gregius Data

Categories are registered only when `wp_register_ability_category` exists.

## 6. Registered Abilities

### 6.1 `gregius-data/answer`

Label: `Answer Question`

Category: `ai`  
Permission: `current_user_can( 'read' )`

Description:
- searches site content semantically and answers a question using retrieved context
- requires `connection_name`, `embedding_model`, and `answer_model`
- optional relevance reranking via `rerank_model`
- optional agentic orchestration via `agentic_model`
- use `list-connections` and `list-models` to discover valid values

Execution summary:
- normalizes input fields for basic and optional orchestration modes
- validates required query and model arguments
- delegates to `GG_Data_RAG_Service::generate_answer()`
- normalizes interaction tracking context for persistence (server-generated `conversation_id` when absent/invalid; default `source.type = mcp` for ability-origin calls)

Basic contract policy:
- Prompt selection is handled internally by the prompt resolver (selected/factory fallback by prompt type).
- Security gatekeeper prompt selection is internal and not part of the public abilities payload.
- Prompt override fields are intentionally excluded from the basic MCP-facing contract.

Input highlights:
- Required: `query`, `connection_name`, `embedding_model`, `answer_model`
- Optional: `agentic_model`, `rerank_model`, `messages`
- Internal/runtime only in basic mode: prompt resolution and security prompt selection
- Internal/runtime only in basic mode: interaction tracking normalization (`conversation_id`, `source`)

Output highlights:
- `answer`
- `sources`
- `metadata`

### 6.2 `gregius-data/list-connections`

Label: `List Data Connections`

Category: `gregius-data`  
Permission: `current_user_can( 'manage_options' )`

Description:
- returns configured vector data connections and status
- use this output to get valid `connection_name` values for `answer`
- supports PostgreSQL/PDO and PostgREST (Supabase-style REST)
- optional embedding model context can be included per connection

Input highlights:
- Optional: `include_embedding_models` (boolean, default `false`)
- Optional: `include_model_details` (boolean, default `false`; requires `include_embedding_models = true`)

Execution summary:
- reads connections via `GG_Data_Settings_Manager`
- returns normalized array: `name`, `type`, `description`, `is_active`
- `type` identifies backend provider mode (for example `postgresql` direct/PDO or `postgrest` Supabase-style REST)
- when requested, adds `embedding_models` per connection with `active_keys` and `active_count`
- when detail expansion is requested, includes safe per-model metadata in `embedding_models.active`

Output highlights:
- Default mode (no flags):
  - `name`, `type`, `description`, `is_active`
- Enriched mode (`include_embedding_models = true`):
  - `embedding_models.active_keys` (string array)
  - `embedding_models.active_count` (integer)
- Detailed mode (`include_model_details = true`):
  - `embedding_models.active[]` safe model summary (`id`, `type`, `provider`, `label`, `is_active`, optional `dimensions`, optional `description`, optional `provider_model_id`)

### 6.3 `gregius-data/list-models`

Label: `List AI Models`

Category: `ai`  
Permission: `current_user_can( 'manage_options' )`

Description:
- returns registered AI models by type: `embeddings`, `llm`, `rerank`
- use this output to get valid `embedding_model`, `rerank_model`, `agentic_model`, and `answer_model` values for `answer`

Execution summary:
- reads models via `GG_Data_Model_Registry`
- input contract accepts only optional `type` filter
- optional `type` filtering: `embeddings`, `llm`, `rerank`
- model definitions are read from a global/provider registry scope (not per-connection model storage)
- returns normalized model objects including `id`, `type`, `provider`, `label`, `is_active`, optional `description`, optional `dimensions`
- does not persist `gg_interaction` records (utility/discovery ability only)

## 7. Metadata Contract

All three abilities include:

- `meta.show_in_rest = true`
- `meta.annotations` (by ability)
  - `gregius-data/answer`: `readonly = false`, `destructive = false`, `idempotent = false`
  - `gregius-data/list-connections`: `readonly = true`, `destructive = false`, `idempotent = true`
  - `gregius-data/list-models`: `readonly = true`, `destructive = false`, `idempotent = true`
- `meta.mcp`
  - `public = true`
  - `type = tool`

This enables REST discovery and MCP-adapter tool translation.

## 8. Permission Matrix

| Ability | Capability |
|---|---|
| `gregius-data/answer` | `read` |
| `gregius-data/list-connections` | `manage_options` |
| `gregius-data/list-models` | `manage_options` |

## 9. Error Behavior

Known error returns in `execute_rag_answer()`:
- missing query -> `WP_Error( 'missing_query', ... )`
- missing required connection/model arguments should fail contract validation at the Abilities API boundary

Graceful behavior:
- category/ability registration no-ops when Abilities API functions are unavailable.

## 10. Integration Notes

### 10.1 REST discovery and execution

Primary Abilities endpoints (provided by Abilities API runtime):
- `GET /wp-abilities/v1/abilities`
- `GET /wp-abilities/v1/{namespace}/{ability}`
- `POST /wp-abilities/v1/{namespace}/{ability}/run` (pass ability arguments under `input` in the JSON body)

### 10.2 MCP adapter alignment

The plugin does not implement MCP transport directly.
It exposes MCP-friendly metadata so external adapter layers can present abilities as tools.

## 11. Troubleshooting

### Ability not appearing in discovery

Check:
1. `wp_register_ability` function exists in runtime.
2. `GG_Data_Abilities_Manager::init()` is executed.
3. Hooks are attached to `wp_abilities_api_init` and `wp_abilities_api_categories_init`.

### Ability exists but cannot execute

Check capability requirements:
- `answer`: requires logged-in user with `read`.
- `list-connections` / `list-models`: require `manage_options`.

### `answer` did not create an interaction record

Check runtime metadata in the response:
- `metadata.conversation_id` should be present (auto-generated when omitted).
- `metadata.source.type` should be set (defaults to `mcp` in ability-origin calls).

Scope note:
- Only `gregius-data/answer` is interaction-persistent.
- `gregius-data/list-models` and `gregius-data/list-connections` do not create `gg_interaction` rows.

### `answer` ability returns prompt errors

Basic contract note:
- Prompt fields are not part of the MCP-facing basic payload.
- Prompt and security prompt selection are handled internally by the prompt resolver and security gatekeeper flow.

## 12. Parity Coverage Snapshot

Documented and code-backed:
- categories: `ai`, `gregius-data`
- abilities: `answer`, `list-connections`, `list-models`
- permission callbacks and capability boundaries
- schema/meta registration contracts
- callback delegation to RAG/settings/model services
