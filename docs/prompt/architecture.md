# Architecture Description: Prompt Subsystem

Canonical path: `docs/prompt/architecture.md`

Standard: ISO/IEC/IEEE 42010:2022

## 1. Purpose and Scope

Purpose:
Describe the architecture of the Prompt subsystem that provides resolved system prompts and security gatekeeper prompts to Gregius Data RAG execution paths.

In scope:
- Prompt storage and metadata model with type taxonomy.
- Prompt resolver fallback and token expansion behavior, type-scoped.
- Security gatekeeper LLM-based query classification mechanism.
- Prompt REST management interface and permissions.
- Lifecycle seed and migration behavior for factory system and security prompts (System Default and Security Default).
- Integration contracts with RAG, CLI, and Abilities flows.

Out of scope:
- End-user chat UX.
- LLM provider-specific request semantics beyond gatekeeper classification.
- Benchmark/evaluation subsystem execution logic.

## 2. Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrator | Prompt management is safe, manageable, and deterministic; security gatekeeper protects against harmful queries. | High |
| Content moderator / Security lead | Security gatekeeper accurately classifies unsafe queries; decisions are auditable and tunable. | High |
| Plugin maintainer | Prompt logic remains centralized and traceable; security gatekeeper integrates predictably. | High |
| Integrator | Prompt overrides and resolver behavior are predictable across interfaces; security checks are transparent. | High |
| Operations | Activation/migration routines are idempotent and non-destructive; security checks do not add excessive latency. | Medium |

## 3. Architecture Context (AV-01)

System context summary:
The Prompt subsystem is an internal service consumed by RAG generation and upstream command/ability entrypoints. It stores prompt records as WordPress posts organized by type (system vs. security), resolves effective prompt content with controlled fallback and runtime expansion, and enforces security gatekeeper LLM-based classification before retrieval.

Interfaces at context level:
- WordPress post/meta storage for `gg_prompt` data with `gg_prompt_type` taxonomy.
- Prompt REST API (`/gg-data/v1/prompts*`) for admin management of typed prompts.
- RAG service system prompt resolution call path (type-scoped to 'system').
- RAG service security gatekeeper pre-retrieval check (type-scoped to 'security').
- CLI and Abilities prompt override argument contracts.
- Interaction metadata logging for security check results.

## 4. Architecture Views

### 4.1 Component View (AV-02)

Major elements:
- Prompt CPT + Taxonomy Registrar (`GG_Data_Prompt`)
- Prompt Resolver (`GG_Data_Prompt_Resolver`)
- Prompt REST Controller (`GG_Data_REST_Prompts_Controller`)
- Security Gatekeeper Check (`GG_Data_RAG_Service::run_security_gatekeeper_check()`)
- Lifecycle Seed/Migration (`GG_Data_Activator` helpers)
- Prompt Consumers (RAG Service, CLI Answer, Abilities Manager)

Element responsibilities:

| Element | Responsibility |
|---|---|
| Prompt CPT + Taxonomy Registrar | Registers `gg_prompt` post type, metadata schema, and `gg_prompt_type` hierarchical taxonomy with 'system' and 'security' terms. |
| Prompt Resolver | Resolves effective prompt by fallback order, validity rules, token expansion, hash maintenance, and type-scoping (e.g., resolve('system') or resolve('security')). |
| Prompt REST Controller | Exposes prompt CRUD + activate routes with capability checks, prompt_type parameter handling, type-scoped activation (deselects only prompts of same type). |
| Security Gatekeeper Check | Pre-retrieval LLM-based query classifier; resolves 'security' prompt, invokes LLM with security prompt as system message, parses classify response (SAFE/UNSAFE), blocks unsafe queries. |
| Lifecycle Seed/Migration | Seeds System Default (system, active) and Security Default (security, active) prompts idempotently; migrates legacy prompts to 'system' type; migrates baked date/time text to placeholders. |
| Prompt Consumers | Supply optional prompt override context, request type-scoped resolution, consume resolved prompt output, integrate security check results into interaction logs. |

### 4.2 Runtime Interaction View (AV-03)

Type-scoped prompt resolution flow (used for system or security prompts):
1. Consumer calls resolver with prompt type ('system' or 'security') and optional explicit prompt ID.
2. Resolver filters candidates by type via taxonomy query.
3. Resolver attempts explicit prompt (validated against requested type).
4. If explicit prompt is unavailable/invalid for type, resolver attempts selected prompt marker (filtered by type).
5. If selected prompt is unavailable/invalid for type, resolver attempts factory prompt marker (filtered by type).
6. Resolver validates prompt post type, non-empty content, non-draft status, and type term assignment.
7. Resolver expands `{{date}}`, `{{time}}`, `{{datetime}}` tokens.
8. Resolver returns content plus prompt metadata (`id`, `version`, `hash`, `source`, `prompt_type`) or structured error.

Security gatekeeper query classification flow (pre-retrieval check in RAG):
1. RAG service receives user query after tool selection.
2. RAG service calls `run_security_gatekeeper_check($query, $llm_model_id, $options)`.
3. Gatekeeper resolves 'security' type prompt via type-scoped resolver.
4. Gatekeeper invokes LLM with security prompt as system message, query as user message.
5. Gatekeeper parses LLM response as JSON: `{"status":"SAFE|UNSAFE","reason":"..."}`.
6. If status is UNSAFE, RAG returns short-circuit response: "I can't help with that request" + metadata.
7. If status is SAFE, RAG proceeds to retrieval and normal answer generation.
8. Security check result (status, reason, prompt, usage) stored in interaction metadata.

Lifecycle flow:
1. Activation/version-check paths execute seed and migration routines.
2. Prompt type terms ('system', 'security') are created once via `ensure_prompt_type_terms()`.
3. Factory prompts are created once: System Default (system, active) and Security Default (security, active).
4. Legacy prompts without type assignment are migrated to 'system' type via `migrate_prompt_types()`.
5. Placeholder migration rewrites legacy baked date/time fragments into token placeholders once.
6. Subsequent runs no-op via guard options.

### 4.3 Deployment and Environment View (AV-04)

Environment assumptions:
- WordPress runtime with post/meta APIs available.
- Plugin activation hooks execute in admin lifecycle.
- REST routes are available under plugin namespace.

Persistence dependencies:
- `wp_posts` for `gg_prompt` records.
- `wp_postmeta` for `_gg_prompt_*` contracts.
- `wp_term_taxonomy` and `wp_terms` for `gg_prompt_type` taxonomy terms ('system', 'security').
- `wp_term_relationships` for post-to-type term associations.
- `wp_options` for seed/migration idempotency flags.
- Interaction metadata storage for security check results.

## 5. Architectural Decisions

### Decision AD-01: Single canonical resolver for effective prompt selection

- Status: Accepted
- Decision: Use `GG_Data_Prompt_Resolver` as the canonical prompt resolution component for RAG generation and prompt metadata output.
- Rationale: Centralizes fallback, validation, token expansion, and hashing behavior to avoid drift across consuming layers.
- Alternatives considered:
  - Inline prompt resolution in each consumer.
  - REST-only prompt resolution service.
- Consequences:
  - Positive: One deterministic behavior contract across integrations.
  - Tradeoff: Resolver changes can impact all prompt consumers.
- Traceability: PROMPT-FR-03, PROMPT-FR-04, PROMPT-FR-05, PROMPT-DR-03.

### Decision AD-02: Metadata-driven fallback chain

- Status: Accepted
- Decision: Resolve prompts in strict order explicit -> selected -> factory using `_gg_prompt_selected` and `_gg_prompt_is_factory` markers.
- Rationale: Supports deterministic overrides while preserving safe default behavior.
- Alternatives considered:
  - Selected-only resolution.
  - Title/slug heuristics as default fallback.
- Consequences:
  - Positive: Predictable behavior and robust fallback.
  - Tradeoff: Metadata integrity is a dependency.
- Traceability: PROMPT-FR-03, PROMPT-DR-05, PROMPT-QR-01.

### Decision AD-03: Runtime token expansion

- Status: Accepted
- Decision: Expand date/time placeholders at prompt-resolution time, not as persisted static values.
- Rationale: Keeps prompt records stable while ensuring current runtime values in effective content.
- Alternatives considered:
  - Persist baked date/time values.
  - Expand tokens in every consumer instead of resolver.
- Consequences:
  - Positive: Centralized and locale-aware dynamic values.
  - Tradeoff: Token support set is explicit and finite.
- Traceability: PROMPT-FR-05, PROMPT-QR-04.

### Decision AD-04: Idempotent factory seed and migration

- Status: Accepted
- Decision: Seed factory prompt and placeholder migration routines are guarded by options to prevent repeated mutation.
- Rationale: Activation/version-check paths can run multiple times and must remain safe.
- Alternatives considered:
  - Seed on every activation unconditionally.
  - One-time manual migration via CLI only.
- Consequences:
  - Positive: Stable lifecycle behavior and low operational risk.
  - Tradeoff: Requires option-flag hygiene during debugging.
- Traceability: PROMPT-FR-08, PROMPT-FR-09, PROMPT-OR-04.

### Decision AD-05: Type-scoped prompt resolution and activation

- Status: Accepted
- Decision: Resolve and activate prompts independently per type via `gg_prompt_type` taxonomy; resolver accepts `$prompt_type` parameter; REST activation deselects only prompts of same type.
- Rationale: Allows independent selection of system and security prompts without conflict; prevents accidental hijacking of one prompt type by activating another.
- Alternatives considered:
  - Single merged system/security prompt.
  - Global activation affecting all types.
  - Separate database tables per type.
- Consequences:
  - Positive: Independent lifecycle for system and security concerns; clean separation of concerns; admin dashboard can manage both without coupling.
  - Tradeoff: Taxonomy queries add slight performance overhead; developers must remember to pass `$prompt_type` parameter.
- Traceability: PROMPT-FR-12, PROMPT-FR-13, PROMPT-DR-07.

### Decision AD-06: Pre-retrieval LLM-based security gatekeeper

- Status: Accepted
- Decision: Invoke a separate LLM call with security prompt before retrieval to classify user query as SAFE or UNSAFE; block unsafe queries with policy response.
- Rationale: Prevents policy-violating queries from reaching retrieval and generation phases; uses LLM classification (not keyword filtering) for nuance and context awareness; separate call allows security check to use specialized security prompt without affecting answer generation prompt.
- Alternatives considered:
  - Keyword-based filtering (brittle, high false positive rate).
  - Security check after retrieval (wastes compute on blocked queries).
  - Combined security+answer LLM call (couples security and content concerns, harder to tune independently).
  - No security check (exposes RAG to harmful requests).
- Consequences:
  - Positive: Accurate context-aware classification; early termination saves compute; tunable security prompt; audit trail via interaction logs.
  - Tradeoff: Adds latency (~1 LLM call per query); duplicates some LLM context/tokens; security prompt must be kept current.
- Traceability: PROMPT-FR-14, PROMPT-FR-15, PROMPT-OR-05, PROMPT-OR-06.

## 6. Constraints and Risks

Constraints:
- Prompt eligibility depends on both post state, `_gg_prompt_status` metadata, and type term assignment.
- Type-scoped resolution requires taxonomy to exist; resolver gracefully degrades if taxonomy not registered.
- REST management is administrator-only by design.
- Prompt consumer integrations depend on resolver output shape and type parameter.
- Security gatekeeper LLM call must complete successfully; timeout/error falls through to unsafe decision (fail-safe).

Risks:
- Direct manual edits to prompt metadata or term assignments may produce unexpected selected/factory state or type misalignment.
- Security gatekeeper LLM latency adds to query RTT; poor model selection or prompt size could impact user experience.
- Future token additions without documentation updates could create contract drift.
- Duplicate prompt titles can create ambiguity in non-ID override pathways.
- Stale security prompt may produce inaccurate classifications (e.g., new attack vectors not in training).

## 7. Architecture Coverage Mapping

| Architecture Item | SRS Coverage |
|---|---|
| AV-01 Context Interfaces | PROMPT-FR-06, PROMPT-FR-10, PROMPT-FR-11, PROMPT-FR-14, PROMPT-FR-15 |
| AV-02 Component Responsibilities | PROMPT-FR-01 to PROMPT-FR-15, PROMPT-DR-01 to PROMPT-DR-09 |
| AV-03 Resolution + Lifecycle Flows | PROMPT-FR-03, PROMPT-FR-04, PROMPT-FR-05, PROMPT-FR-08, PROMPT-FR-09, PROMPT-FR-12, PROMPT-FR-13, PROMPT-FR-14, PROMPT-FR-15 |
| AD-01 | PROMPT-FR-03, PROMPT-FR-04, PROMPT-DR-03 |
| AD-02 | PROMPT-FR-03, PROMPT-QR-01 |
| AD-03 | PROMPT-FR-05, PROMPT-QR-04 |
| AD-04 | PROMPT-FR-08, PROMPT-FR-09, PROMPT-OR-04 |
| AD-05 | PROMPT-FR-12, PROMPT-FR-13, PROMPT-DR-07, PROMPT-OR-07 |
| AD-06 | PROMPT-FR-14, PROMPT-FR-15, PROMPT-OR-05, PROMPT-OR-06 |
