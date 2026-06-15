# Prompt Subsystem Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Overview

This guide documents developer-facing behavior of the Prompt subsystem used by Gregius Data answer-generation flows.

Primary references:
- SRS: [SRS reference](srs.md)
- Architecture: [Architecture reference](architecture.md)

Scope:
1. Prompt data model (post type, metadata, taxonomy)
2. Prompt type taxonomy and type-scoped resolution
3. Type-scoped prompt resolver contract
4. Prompt REST management contract with type support
5. Security gatekeeper LLM-based query classification
6. Lifecycle seed/migration behavior
7. Integration with RAG, CLI, and Abilities
8. Extension and troubleshooting guidance

## 2. Prompt Data Model

### 2.1 Prompt post type

Prompt records are stored as WordPress posts of type `gg_prompt`.

Source:
- `includes/class-gg-data-prompt.php`

[SRS: PROMPT-FR-01, PROMPT-DR-01]

### 2.2 Prompt metadata keys

Prompt metadata is namespaced under `_gg_prompt_*` keys.

Core keys used by subsystem behavior:
- `_gg_prompt_version`
- `_gg_prompt_status`
- `_gg_prompt_hash`
- `_gg_prompt_notes`
- `_gg_prompt_selected`
- `_gg_prompt_is_factory`

Source:
- `includes/class-gg-data-prompt.php`
- `includes/class-gg-data-prompt-resolver.php`
- `includes/api/class-gg-data-rest-prompts-controller.php`

[SRS: PROMPT-FR-02, PROMPT-DR-02]

### 2.3 Prompt type taxonomy

Prompt types are classified using the `gg_prompt_type` hierarchical taxonomy.

Terms (fixed):
- `system` — Prompts used for answer generation in RAG pipeline
- `security` — Prompts used for pre-retrieval security gatekeeper classification

Every prompt post MUST be assigned exactly one type term via `wp_set_object_terms()`.

Usage:
```php
// Get current prompt type
$terms = wp_get_object_terms( $prompt_id, 'gg_prompt_type', array( 'fields' => 'slugs' ) );

// Assign prompt type
wp_set_object_terms( $prompt_id, array( 'system' ), 'gg_prompt_type', false );
```

Source:
- `includes/class-gg-data-prompt.php` (registration via `register_prompt_type_taxonomy()`)
- `includes/class-gg-data-activator.php` (seeding via `ensure_prompt_type_terms()`)

[SRS: PROMPT-FR-03, PROMPT-DR-03]

## 3. Type-Scoped Resolver Contract

Source:
- `includes/class-gg-data-prompt-resolver.php`

Primary API:
- `resolve_prompt( $prompt_id = 0, $prompt_type = 'system' )`

Parameters:
- `$prompt_id` (int, optional): Explicit prompt ID to attempt first. If provided, must be of requested type or resolution fails.
- `$prompt_type` (string): 'system' or 'security'. Filters candidates by type term assignment.

Return contract:
- success: array with
  - `content` (effective prompt text)
  - `metadata.id`
  - `metadata.version`
  - `metadata.hash`
  - `metadata.source` (`explicit` | `selected` | `factory`)
  - `metadata.prompt_type` ('system' or 'security')
- failure: `WP_Error( 'gg_data_prompt_not_found', ... )` or type mismatch error

**Important**: Each type maintains independent selected/factory state. Resolving 'system' will never return a 'security' prompt, and vice versa.

[SRS: PROMPT-FR-04, PROMPT-FR-05, PROMPT-DR-04, PROMPT-DR-07, PROMPT-OR-02]

### 3.1 Resolution order (type-scoped)

Fallback order is deterministic within the requested type:
1. Explicit prompt ID (if provided; must match requested type)
2. Selected prompt (`_gg_prompt_selected=1`, published, and of requested type)
3. Factory prompt (`_gg_prompt_is_factory=1`, published, and of requested type)

Resolver skips invalid candidates:
- wrong post type (`gg_prompt`)
- empty post content
- draft `_gg_prompt_status`
- **missing requested type term assignment**

[SRS: PROMPT-FR-04, PROMPT-FR-05, PROMPT-QR-01]

### 3.2 Token expansion

Resolver expands tokens in returned content:
- `{{date}}`
- `{{time}}`
- `{{datetime}}`

Expansion uses WordPress runtime date functions.

[SRS: PROMPT-FR-05, PROMPT-QR-04]

### 3.3 Hashing behavior

Prompt hash is deterministic SHA256 over normalized content:
- CRLF normalized to LF
- trimmed before hashing

If hash metadata is missing, resolver computes and persists it.

[SRS: PROMPT-DR-04, PROMPT-QR-02]

## 4. REST Management Contract

Source:
- `includes/api/class-gg-data-rest-prompts-controller.php`

Routes are registered under `/wp-json/gg-data/v1/prompts` with support for list/get/create/update/delete plus activation operations.

Permission model:
- Administrative capability checks are required for prompt management operations.

### 4.1 Type-aware parameters and operations

Create/Update endpoints (`POST /prompts`, `PUT /prompts/<id>`):
- Accept `prompt_type` parameter ('system' or 'security')
- Validate `prompt_type` to enum; reject invalid values
- Store type via `wp_set_object_terms()` under `gg_prompt_type` taxonomy
- Return `prompt_type` in response payload

Activation endpoint (`POST /prompts/<id>/activate`):
- Retrieves current prompt type
- Passes type to `deselect_other_prompts()` for type-scoped deactivation
- Only deselects OTHER prompts of SAME type; leaves opposite type unaffected
- Example: Activating a 'system' prompt will deactivate other 'system' prompts but leave 'security' selection unchanged

Payload example:
```json
{
  "title": "My Custom System Prompt",
  "prompt_type": "system",
  "status": "published",
  "content": "You are a helpful assistant...",
  "notes": "Internal notes"
}
```

Response example:
```json
{
  "id": 123,
  "title": "My Custom System Prompt",
  "prompt_type": "system",
  "status": "published",
  "version": 1,
  "selected": true,
  "is_factory": false,
  "modified": "2026-04-19T12:34:56Z",
  "content": "You are a helpful assistant...",
  "notes": "Internal notes"
}
```

Operational notes:
- Prompt resource responses include prompt metadata fields used by admin UI and integration code paths.
- Activation endpoints normalize selected/factory state updates through controller logic.

[SRS: PROMPT-FR-07, PROMPT-FR-16, PROMPT-FR-06, PROMPT-OR-01, PROMPT-OR-07, PROMPT-DR-07]

## 5. Security Gatekeeper Contract

Source:
- `includes/rag/class-gg-data-rag-service.php`

The security gatekeeper is an optional pre-retrieval LLM-based query classifier that prevents unsafe/policy-violating queries from reaching the RAG retrieval and generation phases.

### 5.1 API

Primary method:
- `run_security_gatekeeper_check( $query, $llm_model_id, $options = array() )`

Parameters:
- `$query` (string): User query to classify
- `$llm_model_id` (string): Model to use for classification
- `$options` (array): Optional overrides (e.g., `['prompt_type' => 'security']`)

Return:
- success: array with
  - `status` ('SAFE' or 'UNSAFE')
  - `reason` (string: explanation)
  - `prompt` (resolved security prompt metadata)
  - `usage` (LLM token usage data)
- failure: `WP_Error` on resolution or LLM failure; treated as UNSAFE (fail-safe)

### 5.2 Security check behavior

Gatekeeper workflow:
1. Resolves active 'security' type prompt via `resolve_prompt( 0, 'security' )`
2. Invokes LLM with:
   - System message: resolved security prompt content
   - User message: user query
3. Parses LLM response as JSON: `{"status":"SAFE|UNSAFE","reason":"..."}`
4. On JSON parse failure, checks response for literal 'unsafe' substring (fallback)
5. Returns normalized result with status, reason, prompt metadata, and usage info

### 5.3 RAG integration

RAG service invokes gatekeeper pre-retrieval in `generate_answer()` flow:
1. Before tool selection
2. Before any tool execution or document retrieval

If check returns UNSAFE status:
- Build short-circuit response: 'I can\'t help with that request'
- Include full security_check metadata in interaction log
- Fire `gg_data_rag_error` action
- Return early without executing retrieval or generation

If check returns SAFE status or fails with error:
- Proceed to normal retrieval and generation
- Still log security_check metadata for audit

[SRS: PROMPT-FR-13, PROMPT-FR-14, PROMPT-FR-15, PROMPT-OR-05, PROMPT-OR-06]

## 6. Lifecycle Seed and Migration

Source:
- `includes/class-gg-data-activator.php`

### 6.1 Prompt type term seeding

Activation creates taxonomy terms via `ensure_prompt_type_terms()`:
- Creates 'system' term if not present
- Creates 'security' term if not present
- Idempotent via `gg_data_prompt_type_terms_seeded` option guard

[SRS: PROMPT-FR-03]

### 6.2 Factory prompt seeding

Activation seeds TWO factory prompts via `seed_default_prompts()`:

**System Default** (system type):
- Title: 'System Default'
- Type: 'system'
- Status: 'published'
- Selected: true (active by default)
- Content: Standard helpful assistant prompt with date/time placeholders
- Guard option: `gg_data_default_prompt_seeded`

**Security Default** (security type):
- Title: 'Security Default'
- Type: 'security'
- Status: 'published'
- Selected: true (active by default)
- Content: Prompt to classify queries as SAFE/UNSAFE
- Guard option: `gg_data_security_prompt_seeded`

Both are marked as factory prompts (`_gg_prompt_is_factory=1`) for identification.

### 6.3 Legacy migration

`migrate_prompt_types()` assigns 'system' type to any prompt without type:
- Guards with `gg_data_prompt_type_migrated` option
- Preserves backward compatibility

### 6.4 Placeholder migration

Lifecycle migration rewrites legacy baked date/time text in factory prompt content into:
- `Current date: {{date}}.`
- `Current time: {{time}}.`

Both routines are idempotent through option guards.

### 7.1 RAG service integration

Source:
- `includes/rag/class-gg-data-rag-service.php`

System prompt resolution:
- Resolves system prompt via `GG_Data_Prompt_Resolver::resolve_prompt( 0, 'system' )` before invoking answer model
- Exposed to filter `gg_data_rag_system_prompt`

Security gatekeeper check:
- Invokes `run_security_gatekeeper_check()` before tool selection and retrieval
- Classifies query as SAFE/UNSAFE
- Blocks unsafe queries with policy response
- Logs security check results in interaction metadata

[SRS: PROMPT-FR-12, PROMPT-FR-13, PROMPT-FR-14, PROMPT-FR-15]

### 7.2 WP-CLI answer integration

Source:
- `includes/cli/class-gg-data-cli-answer.php`

CLI supports prompt overrides:
- `--prompt-id=<id>`
- `--prompt=<identifier>`

Validation behavior:
- rejects conflicting override arguments
- validates numeric IDs and identifier resolution
- respects type-scoped resolution for explicit prompts

[SRS: PROMPT-FR-17, PROMPT-OR-03]

### 7.3 Abilities integration

Source:
- `includes/class-gg-data-abilities-manager.php`

Abilities answer path supports:
- `prompt_id`
- `prompt`

Validation behavior:
- rejects invalid combinations
- resolves prompt identifiers through manager helper methods
- respects type-scoped resolution for explicit prompts

[SRS: PROMPT-FR-17, PROMPT-OR-03]

## 8. Extension Points

Prompt subsystem behavior can be influenced by integration-facing hooks and filters in consuming subsystems, especially RAG service layers.

Primary integration hooks:
- `gg_data_rag_system_prompt` — Filter system prompt before model invocation
- `gg_data_rag_error` — Action fired when security gatekeeper blocks query

Guidance:
- Prefer prompt-content mutation through the documented filter in consumer context.
- Avoid direct mutation of resolver internals unless changing subsystem contract intentionally.
- For security gatekeeper customization, update the active 'security' type prompt in admin UI.

## 9. Troubleshooting

### Prompt not resolving

Check:
1. Prompt exists and is `gg_prompt` post type.
2. Prompt has non-empty content.
3. `_gg_prompt_status` is not `draft`.
4. Selected/factory marker metadata is set correctly when relying on fallback behavior.

### Ambiguous or invalid prompt override

Check:
1. Do not provide both prompt override inputs together.
2. Prefer ID-based override for deterministic resolution.
3. Validate target prompt post exists and is valid.

### Date/time values appear static

Check:
1. Prompt content still contains placeholder tokens, not baked timestamps.
2. Placeholder migration has executed for legacy factory prompt content.

### Hash missing or inconsistent

Check:
1. Resolver path has run at least once to backfill missing hash.
2. Content normalization assumptions match subsystem hash contract.

## 9. Parity Coverage Snapshot

Documented and code-backed:
- Prompt CPT + metadata contract
- Deterministic prompt resolver behavior and fallback order
- Runtime token expansion and deterministic hashing
- REST prompt management with admin permission boundaries
- Activation seed and placeholder migration idempotency
- RAG, CLI, and Abilities integration contracts
