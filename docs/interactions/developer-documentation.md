# Interaction Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to use and extend the Gregius Data interaction subsystem.

Audience:
- Plugin contributors maintaining interaction capture and REST behavior
- Integrators consuming interaction records through REST
- Developers extending interaction payloads/log context via hooks

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Interaction post type and REST surface
- Event-driven capture flows from RAG and Search
- Meta and JSON payload contracts
- Extension hooks and integration recipes
- Access-control and troubleshooting guidance

Not covered:
- End-user UI workflows
- Logging subsystem internals

## 3. Quick Start

### 3.1 Record a Search Interaction in PHP

Use this pattern when writing internal plugin flows that need explicit search interaction recording. [SRS: INT-FR-03, INT-FR-04]

```php
$post_id = GG_Data_Interaction::record_search(
	array(
		'connection'   => 'default',
		'query'        => 'dementia care',
		'zero_results' => false,
		'source'       => array( 'type' => 'frontend' ),
		'data'         => array(
			'query'  => array( 'original' => 'dementia care' ),
			'search' => array( 'results_count' => 12 ),
		),
	)
);
```

### 3.2 Record or Append a RAG Conversation Turn

`record_rag()` validates the UUID, acquires a short lock, and appends to an existing conversation or creates a new one. [SRS: INT-FR-05, INT-FR-06, INT-OR-02]

```php
$post_id = GG_Data_Interaction::record_rag(
	'2f4e0f0c-0d52-4886-a919-4d7774f5ecb0',
	array(
		'connection' => 'default',
		'query'      => 'What is dementia care?',
		'response'   => 'Dementia care includes...',
		'source'     => array( 'type' => 'rest' ),
		'sources'    => array( 101, 204 ),
	)
);
```

## 4. Core Classes

### 4.1 `GG_Data_Interaction`

Source: `includes/class-gg-data-interaction.php`

Responsibilities:
- Register interaction post type and REST-exposed meta fields
- Listen to `gg_data_rag_complete` and `gg_data_search_completed`
- Persist interaction records (`record_search`, `record_rag`)
- Append RAG turns to existing conversations
- Emit extensibility hooks and log context
- Exclude interactions from real-time sync decisions

Important methods:
- `init()`
- `register_post_type()`
- `register_meta_fields()`
- `on_rag_complete( $result, $query, $execution_time )`
- `on_search_completed( $results )`
- `record_search( array $args )`
- `record_rag( $conversation_id, array $args )`
- `validate_conversation_id( $conversation_id )`

### 4.2 `GG_Data_REST_Interactions_Controller`

Source: `includes/api/class-gg-data-rest-interactions-controller.php`

Responsibilities:
- Expose interaction endpoints under `gg-data/v1/interactions`
- Enforce authentication for all reads/writes
- Scope non-admin users to authored records for read/update/delete
- Restrict non-admin direct creation (interaction creation is chat-lifecycle driven)
- Allow admin full governance, including multisite network governance via explicit site context

Overridden permission checks:
- `get_items_permissions_check()`
- `get_item_permissions_check()`
- `create_item_permissions_check()`
- `update_item_permissions_check()`
- `delete_item_permissions_check()`

## 5. Data Contracts

### 5.1 Post Type Contract

- Post type: `gg_interaction`
- Public visibility: `false`
- REST exposure: `true`
- REST namespace/base: `gg-data/v1/interactions`
- Supports: `title`, `editor`, `custom-fields`

### 5.2 Interaction Meta Fields

| Meta Key | Type | Description | REST Exposed |
|---|---|---|---|
| `_gg_interaction_type` | string | Interaction type (`search` or `rag`) | Yes |
| `_gg_interaction_source` | string | Source type (`frontend`, `rest`, `wpcli`, `mcp`) | Yes |
| `_gg_interaction_connection` | string | Connection slug used for request | Yes |
| `_gg_interaction_conversation_id` | string | RAG conversation UUID | Yes |
| `_gg_interaction_zero_results` | boolean | Whether a search returned no results | Yes |
| `_gg_interaction_data` | string | Canonical JSON payload with full interaction details | Yes |

### 5.3 JSON Payload Patterns

#### Search payload (stored in `_gg_interaction_data`)

```json
{
  "query": {
    "original": "dementia care"
  },
  "search": {
    "post_types": ["post", "page"],
    "metadata_filter": {},
    "results_count": 12
  }
}
```

#### RAG payload (stored in `_gg_interaction_data`)

```json
{
  "source": { "type": "frontend" },
  "models": {
    "agentic": "gpt-4o-mini",
    "embedding": "tfidf-300",
    "rerank": "rerank-2.5",
    "answer": "deepseek-reasoner"
  },
  "manifest": {
    "schema_version": "1.0",
    "entity": {
      "post_id": 377
    }
  },
  "manifest_hash": "6f2d7c2d3f6a",
  "manifest_size_bytes": 248,
  "manifest_version": "1.0",
  "turns": [
    {
      "timestamp": "2026-04-04T12:30:00+00:00",
      "query": {
        "original": "What is dementia care?",
        "rewritten": "dementia care"
      },
      "response": "Dementia care includes...",
      "sources": [101, 204],
      "search": {
        "post_types": ["post", "page"],
        "metadata_filter": {}
      },
      "latency": { "total": 2100 }
    }
  ],
  "totals": {
    "turns": 1,
    "latency": 2100,
    "sources_used": [101, 204]
  }
}
```

Manifest observability notes:
- `manifest` is persisted when request context includes canonical manifest data.
- `manifest_hash`, `manifest_size_bytes`, and `manifest_version` provide lightweight diagnostics for replay and drift detection.

## 6. REST API Reference

Base namespace: `gg-data/v1`

### 6.1 List interactions

- Method: `GET`
- Route: `/interactions`
- Auth: required
- Behavior:
  - Admins: all interactions
  - Non-admin users: current user interactions only

### 6.2 Get interaction by ID

- Method: `GET`
- Route: `/interactions/{id}`
- Auth: required
- Behavior:
  - Admins: any record
  - Non-admin users: owned record only

### 6.3 Create interaction

- Method: `POST`
- Route: `/interactions`
- Auth: required
- Behavior:
  - Admins: allowed
  - Non-admin users: denied (use RAG/SSE chat flow to create interaction records)

### 6.4 Multisite governance context

- Optional request parameter: `site_id`
- Behavior:
  - Single-site: ignored
  - Multisite + super admin: allows explicit governance in target site context when valid
  - Multisite + non-super-admin: denied when `site_id` is provided
- Admin editor requests are site-local by default. Implicit cross-site lookup by numeric post ID is not used.
- Cross-site interaction editor redirects require explicit `site_id` and super-admin authorization.

### 6.5 Update interaction

- Method: `PUT` / `PATCH`
- Route: `/interactions/{id}`
- Auth: required
- Behavior:
  - Admins: any record
  - Non-admin users: owned record only

### 6.6 Delete interaction

- Method: `DELETE`
- Route: `/interactions/{id}`
- Auth: required
- Behavior:
  - Admins: any record
  - Non-admin users: owned record only
  - Default delete semantics are soft-delete unless `force=true` is provided by an authorized caller

## 7. Hooks and Extension Points

### 7.1 `gg_data_interaction_meta_fields`

- Type: Filter
- Signature: `(array $meta_fields): array`
- Purpose: Add custom interaction meta fields before `register_post_meta()`

Example:

```php
add_filter( 'gg_data_interaction_meta_fields', function( $fields ) {
	$fields['_gg_interaction_channel'] = array(
		'type'        => 'string',
		'description' => 'Channel label for interaction source grouping',
	);

	return $fields;
} );
```

### 7.2 `gg_data_interaction_log_context`

- Type: Filter
- Signature: `(array $log_context, int $post_id, string $type): array`
- Purpose: Shape context written into logs for recorded interactions

### 7.3 `gg_data_interaction_recorded`

- Type: Action
- Signature: `(int $post_id, string $type, array $args): void`
- Purpose: React to newly recorded interactions (analytics, notifications, external sync)

### 7.4 `gg_data_should_sync_post` (integration boundary)

- Type: Filter
- Signature: `(bool $should_sync, int $post_id): bool`
- Interaction behavior: returns `false` for `gg_interaction` post type

## 8. Integration Recipes

### 8.1 Subscribe to New Interaction Events

```php
add_action( 'gg_data_interaction_recorded', function( $post_id, $type, $args ) {
	if ( 'rag' === $type ) {
		error_log( 'New RAG interaction #' . $post_id );
	}
}, 10, 3 );
```

### 8.2 Add Per-Record Context for Logs

```php
add_filter( 'gg_data_interaction_log_context', function( $context, $post_id, $type ) {
	$context['integration_tag'] = 'my-plugin';
	$context['post_id']         = $post_id;
	$context['kind']            = $type;
	return $context;
}, 10, 3 );
```

### 8.3 Query Current User Interactions via REST

```bash
curl -X GET https://example.com/wp-json/gg-data/v1/interactions \
  -H "Authorization: Bearer <token>"
```

## 9. Parity Coverage Snapshot

Coverage verified from code:
- Event ingress hooks documented: 2 (`gg_data_rag_complete`, `gg_data_search_completed`)
- Interaction extension hooks documented: 3 (`gg_data_interaction_meta_fields`, `gg_data_interaction_log_context`, `gg_data_interaction_recorded`)
- Integration filter documented: 1 (`gg_data_should_sync_post`)
- Interaction meta fields documented: 6
- REST endpoint family documented: list/get/create/update/delete (+ governance constraints)

## 10. Troubleshooting

### RAG interactions are not being recorded

Check:
1. `conversation_id` exists in RAG metadata.
2. UUID is valid (`wp_is_uuid()` must pass).
3. No lock timeout occurred during append/create path.
4. `search.metadata_filter` is present in metadata when troubleshooting scoped retrieval behavior.

### Non-admin cannot access interaction record

Expected behavior for non-owned records.
- Admin users (`manage_options`) can access all.
- Non-admin users are author-scoped.

### Logged-in non-admin cannot create interaction directly

Expected behavior.
- Direct `POST /interactions` is restricted to administrators.
- Non-admin interaction creation is handled by chat lifecycle endpoints (`/rag/chat` or SSE flow).

### Multisite site context is denied

Expected behavior when a non-super-admin sends `site_id`.
- Only super admins can use explicit cross-site governance context.
- Verify target site ID is valid in network before retrying as super admin.

### Post editor unexpectedly changes site in multisite

This should not happen under the current contract.
- Editor routing does not perform implicit cross-site ID discovery.
- If cross-site editor routing is needed for an interaction record, include explicit `site_id` and use a super-admin account.

### Search interactions missing

Check:
1. `gg_data_search_completed` is emitted by search integration.
2. `connection` is present in search results payload.

## 11. Limitations

- Search duplicate normalization is not currently a formalized interaction contract rule.
- This document references logging integration points but does not define logging subsystem internals.
