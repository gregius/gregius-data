# RAG Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to use, extend, and troubleshoot the RAG subsystem in Gregius Data.

It is written for two audiences:
- Core contributors maintaining RAG internals
- Third-party developers integrating or extending the RAG subsystem

This document complements:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)
- Prompt subsystem (canonical prompt contracts): [../prompt/developer-documentation.md](../prompt/developer-documentation.md)

## 2. Scope

Covered:
- Primary RAG entry points
- REST endpoints and request contracts
- RAG-specific extension hooks and custom tool integration
- Access-control customization
- Practical examples and troubleshooting

Not covered:
- End-user chat UX documentation
- Benchmark-runner workflows
- Provider subsystem internals beyond their use by RAG

## 3. Quick Start

### 3.1 Generate an Answer in PHP

Use the RAG service directly when you are inside plugin code and already know the connection and model IDs. This path implements the main subsystem flow described in the SRS. [SRS: RAG-FR-01, RAG-FR-05, RAG-FR-14, RAG-FR-17]

```php
$rag = new GG_Data_RAG_Service( 'default', 'tfidf-300' );

$result = $rag->generate_answer(
	'How do I install WordPress?',
	'gpt-4o-mini',
	array(
		'num_chunks'      => 5,
		'temperature'     => 0.7,
		'rewrite_model'   => '',
		'rerank_model_id' => '',
		'messages'        => array(),
	)
);

if ( is_wp_error( $result ) ) {
	// Handle retrieval or generation failure.
} else {
	$answer  = $result['answer'] ?? '';
	$sources = $result['sources'] ?? array();
	$meta    = $result['metadata'] ?? array();
}
```

### 3.2 Call the REST Chat Endpoint

Use the REST endpoint when the caller is outside PHP plugin internals, such as a decoupled frontend or integration service. [SRS: RAG-FR-18, RAG-DR-01, RAG-DR-03]

```bash
curl -X POST https://example.com/wp-json/gg-data/v1/rag/chat \
	-H 'Content-Type: application/json' \
	-d '{
	  "query": "How do I install WordPress?",
	  "connection_name": "default",
	  "embedding_model_key": "tfidf-300",
	  "llm_model_id": "gpt-4o-mini",
	  "messages": []
	}'
```

### 3.3 Tighten Endpoint Access

RAG access is configurable and defaults to logged-in in current security hooks with fail-closed permission evaluation. Use access-level filters for stricter or intentionally broader deployment policy. [SRS: RAG-OR-01, RAG-OR-02, RAG-QR-08]

```php
add_filter( 'gg_data_rag_access_level', function() {
	return GG_Data_RAG_Security_Hooks::ACCESS_CAPABILITY;
} );

add_filter( 'gg_data_rag_required_capability', function() {
	return 'edit_posts';
} );
```

### 3.4 Deterministic Tool Overrides with Manifest

Use `forced_tool` plus `manifest` when a caller needs deterministic tool execution without relying on agentic tool selection.

Canonical contract reference:
- [RAG Manifest Contract](manifest-contract.md)

```php
$rag = new GG_Data_RAG_Service( 'default', 'tfidf-300' );

$result = $rag->generate_answer(
	'Summarize this post',
	'gpt-4o-mini',
	array(
		'conversation_id' => '2f4e0f0c-0d52-4886-a919-4d7774f5ecb0',
		'forced_tool'     => 'summarize_current_entity',
		'source'          => array(
			'type'    => 'frontend',
			'post_id' => 377,
		),
		'manifest'        => array(
			'schema_version' => '1.0',
			'entity'         => array(
				'post_id' => 377,
			),
		),
	)
);
```

## 4. Core Entry Points

### 4.1 `GG_Data_RAG_Service`

Source: [includes/rag/class-gg-data-rag-service.php](../../includes/rag/class-gg-data-rag-service.php)

Public constructor:
- `new GG_Data_RAG_Service( $connection_name, $embedding_model_key )`

Primary public methods:
- `retrieve_chunks( $query, $options = array() )`
- `generate_answer( $query, $llm_model_id, $options = array() )`

Behavior notes:
- `retrieve_chunks()` resolves backend-specific retrieval and falls back when needed.
- `generate_answer()` orchestrates tool routing, retrieval, governance, and answer generation.
- Both methods depend on configured connection and model registry data.

Use this class when you need plugin-internal access to the full RAG pipeline without going through REST. [SRS: RAG-FR-05 to RAG-FR-17]

### 4.2 Coverage Gate

Source: [includes/rag/class-gg-data-coverage-gate.php](../../includes/rag/class-gg-data-coverage-gate.php)

Purpose:
- Evaluate evidence coverage for supported intents.
- Return `full`, `partial`, or `abstain` decisions before final synthesis.

This class is an internal governance component rather than the primary integration surface, but its decision model is important when debugging partial or abstain outcomes. [SRS: RAG-FR-15, RAG-FR-16, RAG-QR-02]

### 4.3 Tool Handlers

Source: [includes/rag/class-gg-data-rag-service.php](../../includes/rag/class-gg-data-rag-service.php)

`generate_answer()` dispatches to one of three tool handlers based on tool selection or deterministic rules:

**`handle_search_content()`**
- Return contract: `{ answer, sources, chunks, metadata }`
- Standard single-entity retrieval and answer generation.
- `chunks` contains the final selected chunk set used for context assembly.

**`handle_compare_content()`**
- Return contract: `{ answer, sources, chunks, metadata }`
- Per-entity retrieval with merge/dedup/rerank, coverage gate evaluation, and comparison synthesis.
- `chunks` is populated in all three return paths:
  - **Abstain** (no coverage): uses `$all_chunks` — the full merged candidate set.
  - **LLM failure fallback**: uses `$synthesis_chunks` — the balanced synthesis chunk set.
  - **Success**: uses `$synthesis_chunks` — the balanced synthesis chunk set.
- The `chunks` key feeds `format_json_output()` (via the `GG_Data_Format_Json_Output` trait), which extracts `retrieved_contexts` for evaluation and benchmark dataset capture. Without it, `compare_content` tool results would record empty retrieved contexts.

**`handle_assistant_actions()`**
- Return contract varies by action type (callback confirmation, metadata, etc.).
- Not relevant for evaluation or benchmark capture.

[SRS: RAG-FR-05 to RAG-FR-17]

## 5. REST API Entry Points

### 5.1 `POST /wp-json/gg-data/v1/rag/chat`

Source: [includes/api/class-gg-data-rest-rag-controller.php](../../includes/api/class-gg-data-rest-rag-controller.php)

Required request fields:
- `query`
- `connection_name`
- `embedding_model_key`
- `llm_model_id`

Optional request fields:
- `num_chunks`
- `post_types`
- `temperature`
- `rewrite_model`
- `rerank_model_id`
- `conversation_id`
- `prompt_id`
- `messages`
- `metadata_filter`
- `metadata_manifest`
- `manifest`
- `forced_tool`

Key behavior:
- Missing required fields return a structured `WP_Error` response.
- `post_types` are only passed through when explicitly present in the JSON body.
- `messages` are sanitized to allow only supported roles and sanitized content.
- `metadata_filter` and `metadata_manifest` are accepted as structured arrays and normalized to array defaults when absent or invalid.
- `manifest` is accepted as a structured payload and normalized by the canonical validator before tool handlers consume it.
- `forced_tool` executes deterministically when it matches an allowed tool (`summarize_current_entity`, `recommend_related_content`); unsupported values fall back to standard routing.
- Retrieval providers must preserve empty metadata filter object semantics (`{}`) at SQL transport boundaries for JSONB containment parity.
- Fixed-window route throttling is applied before generation and returns HTTP 429 (`gg_data_rate_limited`) when exceeded.
- Successful responses return `success: true` and a `data` payload.

Implements the main external contract for RAG chat. [SRS: RAG-FR-01 to RAG-FR-04, RAG-FR-18, RAG-DR-01 to RAG-DR-11]

### 5.2 `POST /wp-json/gg-data/v1/rag/action`

Purpose:
- Execute a user-triggerable RAG tool directly without requiring LLM tool selection.

Required request fields:
- `action`
- `connection_name`
- `llm_model_id`

Optional request fields:
- `embedding_model_key`
- `params`
- `conversation_id`
- `messages`

Key behavior:
- Uses the same tool-definition registry as agentic routing.
- Rejects unknown actions.
- Rejects tools that are not explicitly marked with `user_action` metadata.
- Invokes the matching dynamic handler filter `gg_data_rag_tool_{action}`.
- Fixed-window route throttling is applied before action execution and returns HTTP 429 (`gg_data_rate_limited`) when exceeded.

Use this endpoint for direct user-triggered workflows such as custom action buttons in a chat UI. [SRS: RAG-FR-19, RAG-FR-21, RAG-DR-09, RAG-DR-10]

### 5.3 `GET /wp-json/gg-data/v1/rag/actions`

Purpose:
- Discover tools that are safe and intended for direct user triggering.

Required query field:
- `connection_name`

Response behavior:
- Returns only tools that include `user_action` metadata.
- Includes action name, description, label, icon, and parameter schema.

Use this endpoint to drive client-side menus or action buttons without hardcoding available actions. [SRS: RAG-FR-20, RAG-DR-09]

## 5.4 Journey Continuity Endpoints (`POST`, `GET /wp-json/gg-data/v1/rag/journey/...`)

Source: [includes/api/class-gg-data-rest-rag-journey-controller.php](../../includes/api/class-gg-data-rest-rag-journey-controller.php)

**Purpose:**
Guest users without WordPress accounts can maintain a persistent conversation history within their browser session. Journey endpoints implement session-bound interaction persistence that prevents cross-session access while supporting first-time and returning visitor workflows.

**Guest Session Ownership:**
- Guests are identified by a hashed session credential derived from WordPress authentication salts.
- Guest session hashes are stored in a secure, httponly cookie.
- All journey operations validate that the caller's current session hash matches the stored ownership hash.
- First-claim binding: legacy conversations without session-hash credentials can be claimed by first interaction.

### 5.4.1 `POST /wp-json/gg-data/v1/rag/journey/issue`

**Purpose:**
Create a new journey record or retrieve an existing guest session's journey for first-time or returning visits.

**Required request fields:**
- `reference` (string): A unique identifier for the conversation thread (e.g., post slug, custom ID).

**Optional request fields:**
- `block_id` (string): Gutenberg block ID for the initiating context.

**Request behavior:**
- If the caller's session hash matches an existing journey for the reference, the record is returned.
- If the caller has no session hash yet, a new journey record is created and a session hash is issued.
- First-claim binding: if the reference matches a legacy record without a session hash, it is claimed by the current guest on first interaction.

**Response:**
```json
{
  "success": true,
  "data": {
    "interaction_id": "uuid",
    "reference": "post-slug",
    "session_hash": "sha256-hash",
    "created_at": "2026-03-30T10:00:00Z",
    "state": "active"
  }
}
```

**Errors:**
- `gg_data_journey_blocked`: Guest journey access is denied by policy.
- `gg_data_journey_invalid_reference`: Reference is malformed.

**Implements:** [SRS: RAG-FR-30, RAG-DR-14, RAG-DR-15, RAG-OR-09]

### 5.4.2 `POST /wp-json/gg-data/v1/rag/journey/consume`

**Purpose:**
Append a new RAG interaction (question + answer) to a session-bound journey.

**Required request fields:**
- `interaction_id` (string): The journey record UUID from `issue` response.
- `query` (string): The user query.
- `answer` (string): The generated answer or response text.

**Optional request fields:**
- `sources` (array): Citation/reference data for the answer.
- `metadata` (object): Retrieval and governance metadata.

**Request behavior:**
- Session hash validation: caller's current session hash must match the stored journey owner hash.
- First-claim fallback: if the stored journey has no session hash (legacy record), the caller claims ownership and sets the session hash on first append.
- Subsequent appends must validate session hash.

**Response:**
```json
{
  "success": true,
  "data": {
    "turn_id": "uuid",
    "interaction_id": "uuid",
    "query": "...",
    "answer": "...",
    "created_at": "2026-03-30T10:00:01Z"
  }
}
```

**Errors:**
- `gg_data_journey_not_found`: Journey record does not exist.
- `gg_data_journey_session_mismatch`: Caller session hash does not match journey owner hash.
- `gg_data_journey_ownership_denied`: Cannot claim ownership (journey already claimed or invalid state).

**Implements:** [SRS: RAG-FR-31, RAG-DR-14, RAG-DR-15, RAG-OR-09]

### 5.4.3 `GET /wp-json/gg-data/v1/rag/journey/history`

**Purpose:**
Retrieve the complete interaction history for a session-bound journey.

**Required query fields:**
- `interaction_id` (string): The journey record UUID.

**Optional query fields:**
- `page` (integer): Pagination page number (default: 1).
- `per_page` (integer): Items per page (default: 20).

**Request behavior:**
- Session hash validation: caller's current session hash must match the stored journey owner hash.
- Returns only interactions owned by the validated session.
- Pagination and ordering follow WordPress REST conventions.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "turn_id": "uuid",
      "query": "...",
      "answer": "...",
      "sources": [...],
      "created_at": "2026-03-30T10:00:01Z"
    },
    ...
  ]
}
```

**Errors:**
- `gg_data_journey_not_found`: Journey record does not exist.
- `gg_data_journey_session_mismatch`: Caller session hash does not match journey owner hash.

**Implements:** [SRS: RAG-FR-32, RAG-DR-14, RAG-OR-09]

## 6. Extension Points

### 6.1 Request Lifecycle Hooks

Verified hooks:
- `gg_data_rag_request`
- `gg_data_rag_complete`
- `gg_data_rag_error`

Use these to observe request start, success, and failure paths for analytics or logging.

### 6.2 Retrieval and Response Hooks

Verified hooks and filters:
- `gg_data_rag_search_query`
- `gg_data_rag_chunks`
- `gg_data_rag_context`
- `gg_data_rag_sources`
- `gg_data_rag_response`

These are the main extension points for query rewriting, evidence shaping, prompt context customization, source filtering, and final response mutation. [SRS: RAG-FR-21]

Example: override the search query before retrieval.

```php
add_filter( 'gg_data_rag_search_query', function( $search_query, $query, $options ) {
	if ( false !== stripos( $query, 'wp cli' ) ) {
		return 'WP-CLI command line';
	}

	return $search_query;
}, 10, 3 );
```

### 6.3 Governance and Policy Hooks

Verified hooks and filters:
- `gg_data_rag_threshold_profile`
- `gg_data_rag_policy_query_class`
- `gg_data_rag_policy_retrieval_mode`
- `gg_data_rag_retrieval_policy`
- `gg_data_rag_rerank_policy`
- `gg_data_rag_abstain_message`
- `gg_data_rag_governance_decision`

These control or observe retrieval-mode resolution, acceptance thresholds, rerank policy, abstain messaging, and final governance outcomes. [SRS: RAG-FR-14, RAG-FR-15, RAG-FR-16, RAG-QR-04, RAG-QR-05]

Example: force conceptual queries to semantic mode.

```php
add_filter( 'gg_data_rag_policy_retrieval_mode', function( $mode, $connection_name, $intent, $options, $query, $query_class ) {
	if ( 'conceptual' === $query_class ) {
		return 'semantic';
	}

	return $mode;
}, 10, 6 );
```

### 6.4 Tool Registration and Custom Action Hooks

Verified hooks and filters:
- `gg_data_rag_tools`
- `gg_data_rag_tool_{tool_name}`
- `gg_data_rag_tool_executed`

Use these when you need to add a new tool that can be selected by the agentic model or triggered directly by users.

Example: register a user-triggerable custom tool and its handler.

```php
add_filter( 'gg_data_rag_tools', function( $tools ) {
	$tools['summarize_source'] = array(
		'name'        => 'summarize_source',
		'description' => 'Summarize a specific source post.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to summarize.',
				),
			),
			'required'   => array( 'post_id' ),
		),
		'user_action' => array(
			'label' => 'Summarize Source',
			'icon'  => 'dashicons-media-text',
		),
	);

	return $tools;
} );

add_filter( 'gg_data_rag_tool_summarize_source', function( $result, $tool_name, $tool_context ) {
	$post_id = absint( $tool_context['tool_selection']['post_id'] ?? 0 );
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post ) {
		return new WP_Error( 'gg_data_missing_post', 'Post not found.' );
	}

	return array(
		'answer'  => wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
		'sources' => array(
			array(
				'post_id' => $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
			),
		),
		'metadata' => array(
			'tool'    => $tool_name,
			'trigger' => $tool_context['trigger'] ?? 'unknown',
		),
	);
}, 10, 3 );
```

### 6.5 Access-Control Hooks

Verified hooks and filters:
- `gg_data_rag_endpoint_permission`
- `gg_data_rag_access_level`
- `gg_data_rag_required_capability`

Use `gg_data_rag_endpoint_permission` for request-aware allow or deny logic, and use the other two filters to customize the default security policy. [SRS: RAG-OR-01, RAG-OR-02, RAG-QR-08]

### 6.6 Route Throttling Hooks

Verified filters:
- `gg_data_rag_rate_limit_anonymous`
- `gg_data_rag_rate_limit_authenticated`
- `gg_data_rag_rate_limit_window_seconds`

Use these filters to tune fixed-window limits used by `POST /rag/chat` and `POST /rag/action`.

Verified action:
- `gg_data_rag_rate_limit_decision`

Fires after every rate-limit evaluation — for both allowed and blocked requests. Observational only; must not mutate enforcement decisions.

Parameters (in order): `$is_blocked (bool)`, `$scope (string)`, `$endpoint (string)`, `$limit (int)`, `$window (int)`, `$current_count (int)`, `$retry_after (int)`, `$user_id (int)`.

Use this action to implement custom telemetry, alerting, or audit logging without modifying the enforcement path. See also [hooks/developer-documentation.md](../hooks/developer-documentation.md) for the full hook reference.

## 7. Troubleshooting

### 7.1 `gg_data_missing_query` or other missing parameter errors

Cause:
- A required REST field was omitted.

Check:
- `query`
- `connection_name`
- `embedding_model_key`
- `llm_model_id`

### 7.2 `gg_data_action_not_allowed`

Cause:
- The requested action exists but does not include `user_action` metadata.

Fix:
- Add the `user_action` configuration when registering the tool.

### 7.3 `gg_data_action_no_handler`

Cause:
- A user-triggerable tool was registered, but no `gg_data_rag_tool_{tool_name}` handler returned a result.

Fix:
- Register a matching filter that returns either a result array or `WP_Error`.

### 7.4 Unexpected abstain responses

Common causes:
- No accepted evidence remained after acceptance filtering.
- Retrieval mode selection is too strict for the query class.
- Optional rerank behavior reduced candidates below the accepted threshold.

Check:
- `metadata.retrieval`
- `metadata.policy`
- Any custom governance filters altering thresholds or modes

### 7.5 Unexpected public endpoint exposure in production

Cause:
- Access-level settings or filters were changed to public.

Fix:
- Confirm and enforce `logged_in` or capability-gated access through settings or filters.

### 7.6 `gg_data_rate_limited` (HTTP 429)

Cause:
- Route-scoped fixed-window limit exceeded for anonymous or authenticated scope.

Fix:
- Respect `retry_after` before retrying.
- Tune limits through `gg_data_rag_rate_limit_anonymous`, `gg_data_rag_rate_limit_authenticated`, and `gg_data_rag_rate_limit_window_seconds` where needed.

### 7.7 Metadata-Scoped Retrieval Returns Unexpected Zero Results

Cause:
- Empty metadata filter was serialized as a JSON array (`[]`) instead of JSON object (`{}`) before SQL containment evaluation.

Fix:
- Confirm request payload shape for `metadata_filter` is object-compatible.
- Confirm provider transport keeps empty-filter object semantics for JSONB containment bypass.
- Confirm response metadata and interaction payload include `search.metadata_filter` for diagnostics.

### 7.8 `gg_data_journey_session_mismatch` or ownership errors on journey endpoints

Cause:
- The guest caller's session hash does not match the stored journey owner hash, or the journey is already claimed by another session.

Investigation:
- Verify that the guest is using the same browser/tab as the original journey creation request.
- Check that session cookies are preserved across requests (browser should not clear cookies mid-session).
- Inspect browser developer tools for `gg_data_guest_session` or similar session-hash cookies.

Fix:
- For new visitors, call `POST /rag/journey/issue` to create a new journey with the current session hash.
- For returning visitors, ensure the same session hash is present when calling `/consume` or `/history`.
- If the session has been cleared, the guest can create a new journey with a fresh session hash, but previous interactions will be unavailable (privacy by design).

**First-Claim Binding:** If the journey is a legacy record without a stored session hash, the first caller to append via `/consume` automatically claims ownership. Subsequent access will require session-hash validation.

### 7.9 `gg_data_journey_blocked` on issue or consume requests

Cause:
- Guest journey access is denied by policy filter.

Check:
- Verify the `gg_data_rag_endpoint_permission` filter for journey endpoints; journey surfaces default to fail-open (allow unless explicitly denied).
- Confirm no custom filter is overriding the permission on journey routes.

Fix:
- Review and adjust access control policy if guest journey access should be permitted in your deployment.
- If blocking is intentional, provide clear user messaging to explain why guest conversations are not available.

## 8. RAG Assistant Block (`gregius-data/rag-assistant`)

Source: [`blocks/rag-assistant/`](../../blocks/rag-assistant/)

The RAG Assistant is the foundation Gutenberg block that exposes the RAG endpoint directly as a chat UI. It is the base block; the premium `gregius-intelligence/rag-intelligence` block extends this pattern with NBA, journey continuity, and tool routing.

### 8.1 Block Attributes

| Attribute | Type | Default | Description |
|---|---|---|---|
| `blockId` | string | `""` | Unique block instance ID, set from `clientId` on mount |
| `connectionId` | string | `""` | PostgreSQL connection name |
| `embeddingModelKey` | string | `"tfidf-300"` | Embedding model key for retrieval |
| `llmModelId` | string | `""` | LLM model ID for answer generation |
| `rewriteModelId` | string | `""` | Agentic/rewrite model ID (optional) |
| `rerankModelId` | string | `""` | Rerank model ID (optional) |
| `promptId` | number | `0` | System prompt post ID (0 = active/default) |
| `securityPromptId` | number | `0` | Security prompt post ID (0 = active/default) |
| `placeholder` | string | `"Ask a question..."` | Input placeholder text |
| `enableStreaming` | boolean | `true` | Stream responses via SSE |
| `requireLogin` | boolean | `true` | Gate block render to logged-in users only |

### 8.2 `requireLogin` — Block-Level Access Control

When `requireLogin` is `true`, `render.php` checks `is_user_logged_in()` before rendering the block or any markup. Guests receive a login link with a redirect back to the current page:

```php
if ( $require_login && ! is_user_logged_in() ) {
    $login_url = wp_login_url( get_permalink() );
    echo '<div class="gg-rag-login-required">...<a href="...">Sign in</a>...</div>';
    return;
}
```

**Important:** Render behavior also honors the effective API access policy. Guests are gated when either `requireLogin=true` **or** the global RAG access level is not `public`.

Access-level resolution is site-aware: render logic reads `rag_access_level` through `GG_Data_Settings_Manager` (stored in the current site's `*_gg_settings` table) and then applies the `gg_data_rag_access_level` filter. This works correctly for both single-site and multisite without hardcoding a site ID or options table.

- `requireLogin=true` + any non-public API access level → guest sign-in prompt at render time ✅
- `requireLogin=false` + API default `ACCESS_LOGGED_IN` → guest sign-in prompt at render time (prevents submit-then-401 flow)
- `requireLogin=false` + API set to `public` (via `gg_data_rag_access_level` filter) → fully public block and API ✅

To make a block public end-to-end, both the block attribute and the API access level must be aligned:

```php
// Make API public for a public-facing block:
add_filter( 'gg_data_rag_access_level', fn() => 'public' );
```

**Invalidation:** Changing `requireLogin` in the block editor takes effect immediately on the next page render. No cache flush required.

### 8.3 Build

The block uses `@wordpress/scripts`. Run from the block root:

```bash
cd blocks/rag-assistant
npm run build
```

`build/render.php` is **not** auto-generated — it must be kept in sync with `src/render.php` manually after render logic changes.

---

## 9. Traceability Summary

- Core service usage implements [SRS: RAG-FR-05 to RAG-FR-17]
- REST chat endpoint implements [SRS: RAG-FR-01 to RAG-FR-04, RAG-FR-18, RAG-DR-01 to RAG-DR-11]
- Metadata-bearing request and observability contracts implement [SRS: RAG-FR-23, RAG-DR-12]
- Custom tool registration and action execution implement [SRS: RAG-FR-19 to RAG-FR-21, RAG-DR-09, RAG-DR-10]
- Access-control customization implements [SRS: RAG-OR-01, RAG-OR-02, RAG-QR-08]

## 9. Maintenance Notes

When updating the RAG subsystem, recheck these files first:
- [includes/rag/class-gg-data-rag-service.php](../../includes/rag/class-gg-data-rag-service.php)
- [includes/api/class-gg-data-rest-rag-controller.php](../../includes/api/class-gg-data-rest-rag-controller.php)
- [includes/rag/class-gg-data-coverage-gate.php](../../includes/rag/class-gg-data-coverage-gate.php)
- [includes/hooks/class-gg-data-rag-security-hooks.php](../../includes/hooks/class-gg-data-rag-security-hooks.php)
- [docs/rag/srs.md](srs.md)
- [docs/rag/architecture.md](architecture.md)