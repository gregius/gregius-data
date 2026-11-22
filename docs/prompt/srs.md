# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Prompt Subsystem |
| Version | 1.0 |
| Date | 2026-04-18 |
| Author | Gregius |
| StRS Reference | N/A - direct architecture and implementation derivation |
| SyRS Reference | N/A - bounded plugin subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Prompt subsystem must do to manage, resolve, and supply effective system prompts for the Gregius Data RAG execution path.

### 1.2 System Scope

The Prompt subsystem includes prompt storage in `gregius_prompt` posts with type taxonomy, metadata contracts, type-scoped prompt resolution fallback behavior, token expansion, deterministic hashing, REST prompt management endpoints with type support, security gatekeeper LLM-based pre-retrieval query classification, and lifecycle seeding/migration support.

Software identifier: gregius-data-prompt-subsystem

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The Prompt subsystem is consumed by RAG generation, WP-CLI answer flows, and Abilities API execution. It is an internal platform service responsible for selecting and preparing the final system prompt used by answer generation.

#### 1.3.2 System Functions Summary

- Register and persist prompt definitions as `gregius_prompt` posts with type classification via `gregius_prompt_type` taxonomy.
- Store prompt control metadata (`status`, `version`, `hash`, `selected`, `factory`).
- Resolve effective prompt content by deterministic fallback order, type-scoped to ('system', 'security', or 'evaluation').
- Expand date/time tokens at runtime.
- Classify user queries via LLM-based security gatekeeper before retrieval (pre-RAG check).
- Block queries classified as unsafe; allow safe queries to proceed to answer generation.
- Expose prompt CRUD and activation operations via REST with type-aware support.
- Seed factory prompts (system, security, and evaluation) and run placeholder migration idempotently.
- Store security check results in interaction metadata for audit and analysis.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | WordPress admin prompt UI + REST; views prompt types (system/security/evaluation) and activation status. |
| Content Moderator / Security Lead | Technical | REST API to create/update security prompts; views security check audit logs in interactions. |
| Plugin Developer | Technical | Prompt resolver API with type parameter; security gatekeeper integration points. |
| Integrator | Technical | CLI and Abilities prompt override contracts with prompt type support; security check result inspection. |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Prompt | A `gregius_prompt` post assigned to a prompt type (system, security, or evaluation) |
| Prompt type | Classification of a prompt into ('system' for answer generation, 'security' for query classification, or 'evaluation' for retrieval-sufficiency evaluation) via `gregius_prompt_type` taxonomy |
| Effective prompt | The final prompt content returned by resolver after type filtering, fallback, and token expansion |
| Explicit prompt | Prompt chosen by a provided prompt ID override (must match requested type) |
| Selected prompt | Prompt marked by `_gregius_prompt_selected=1` and `_gregius_prompt_status=published` within a type |
| Factory prompt | Activation-seeded prompt marked by `_gregius_prompt_is_factory=1` (one per type: System Default for system, Security Default for security, Evaluation Default for evaluation) |
| Prompt hash | SHA256 hash of normalized prompt content |
| Security gatekeeper | LLM-based pre-retrieval query classifier using 'security' type prompt; classifies query as safe or unsafe |
| Security check | The execution of the security gatekeeper LLM call; returns status (safe/unsafe), reason, and LLM usage metadata |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| REST | Representational State Transfer |
| CPT | Custom Post Type |

## 2. References

- `includes/class-gregius-data-prompt.php`
- `includes/class-gregius-data-prompt-resolver.php`
- `includes/api/class-gregius-data-rest-prompts-controller.php`
- `includes/class-gregius-data-activator.php`
- `includes/rag/class-gregius-data-rag-service.php`
- `includes/cli/class-gregius-data-cli-answer.php`
- `includes/class-gregius-data-abilities-manager.php`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| PROMPT-FR-01 | The software MUST register and persist prompts as WordPress posts of type `gregius_prompt`. | Must |
| PROMPT-FR-02 | The software MUST register prompt metadata fields for version, status, hash, notes, selected marker, and factory marker. | Must |
| PROMPT-FR-03 | The software MUST register a `gregius_prompt_type` taxonomy with 'system', 'security', and 'evaluation' terms to classify prompts by purpose. | Must |
| PROMPT-FR-04 | The software MUST resolve an effective prompt using type-scoped fallback order: explicit prompt (of requested type), then selected prompt (of requested type), then factory prompt (of requested type). | Must |
| PROMPT-FR-05 | The software MUST reject prompt candidates that are missing, empty, wrong post type, marked draft status, or whose assigned type term does not match the requested type; a prompt with no type term MUST be treated as type 'system'. | Must |
| PROMPT-FR-06 | The software MUST expand `{{date}}`, `{{time}}`, and `{{datetime}}` tokens in resolved prompt content at runtime. | Must |
| PROMPT-FR-07 | The software MUST expose REST routes for prompt listing, creation, update, deletion, and activation; routes MUST accept and validate `prompt_type` parameter. | Must |
| PROMPT-FR-08 | The software MUST enforce administrative permission checks on prompt REST operations. | Must |
| PROMPT-FR-09 | Activation flows MUST seed exactly three factory prompts when not already seeded: 'System Default' (type='system', selected=true), 'Security Default' (type='security', selected=true), and 'Evaluation Default' (type='evaluation', selected=true). | Must |
| PROMPT-FR-10 | Activation/version-check flows MUST migrate legacy prompts without type assignment to type='system' idempotently. | Must |
| PROMPT-FR-11 | Activation/version-check flows MUST migrate legacy baked date/time values in factory prompts to placeholder tokens idempotently. | Must |
| PROMPT-FR-12 | RAG generation MUST resolve system prompt content through the type-scoped prompt resolver with prompt_type='system' before model invocation. | Must |
| PROMPT-FR-13 | Pre-retrieval, RAG generation MUST execute security gatekeeper LLM check using the active 'security' type prompt to classify user query as safe or unsafe. | Must |
| PROMPT-FR-14 | If security gatekeeper classifies query as unsafe, RAG MUST return short-circuit response 'I can\'t help with that request.' without proceeding to retrieval or answer generation. | Must |
| PROMPT-FR-15 | Security check results (status, reason, prompt, usage) MUST be stored in interaction metadata for audit and analysis. | Must |
| PROMPT-FR-16 | REST prompt activation MUST deselect only prompts of the same type, allowing independent type selections to coexist. | Must |
| PROMPT-FR-17 | CLI and ability-based answer flows MUST support optional prompt override semantics with type support and reject conflicting override inputs. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| PROMPT-DR-01 | Prompt post type contract MUST use `gregius_prompt`. | Must |
| PROMPT-DR-02 | Prompt metadata contract MUST use `_gregius_prompt_*` prefixed keys. | Must |
| PROMPT-DR-03 | Prompt type taxonomy MUST be registered as `gregius_prompt_type` with terms 'system', 'security', and 'evaluation'. | Must |
| PROMPT-DR-04 | Effective prompt result MUST return content and metadata including prompt ID, version, hash, resolution source, and prompt type. | Must |
| PROMPT-DR-05 | Prompt hashing MUST normalize line endings and trim content before SHA256 computation. | Must |
| PROMPT-DR-06 | Resolution source metadata SHOULD indicate one of `explicit`, `selected`, or `factory`. | Should |
| PROMPT-DR-07 | REST payload contracts for prompt resources MUST include sufficient fields for title, content, status, version, selection, factory markers, and prompt type. | Must |
| PROMPT-DR-08 | Security check result MUST contain status ('safe' or 'unsafe'), reason (string), prompt metadata, and LLM usage metadata. | Must |
| PROMPT-DR-09 | Interaction metadata MUST include complete security_check object (status, reason, prompt, usage) for auditing and analysis. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| PROMPT-OR-01 | Prompt REST management operations MUST require administrative capability checks. | Must |
| PROMPT-OR-02 | Prompt resolution failures MUST return structured errors usable by upstream consumers. | Must |
| PROMPT-OR-03 | Prompt override handling SHOULD fail fast on ambiguous or conflicting inputs. | Should |
| PROMPT-OR-04 | Prompt lifecycle operations MUST remain idempotent across repeated activation/version-check invocations. | Must |
| PROMPT-OR-05 | Security gatekeeper LLM invocation MUST use dedicated security prompt (type='security'); MUST not use answer generation prompt. | Must |
| PROMPT-OR-06 | Security gatekeeper response parsing MUST handle JSON parse failures gracefully; parsing defaults to 'safe' (fail-open) unless the response contains a literal 'unsafe' indicator. | Must |
| PROMPT-OR-07 | Prompt activation via REST MUST only deselect prompts of the same type, allowing system, security, and evaluation selections to coexist independently. | Must |

### 3.4 Quality Requirements

| ID | Requirement | Priority |
|---|---|---|
| PROMPT-QR-01 | Prompt resolution behavior MUST be deterministic for identical prompt storage state and override input. | Must |
| PROMPT-QR-02 | Prompt hash outputs MUST be stable for semantically identical normalized content. | Must |
| PROMPT-QR-03 | Prompt subsystem contracts SHOULD be traceable from SRS requirements to implementation anchors. | Should |
| PROMPT-QR-04 | Prompt token expansion SHOULD use WordPress runtime date/time functions for locale-aware output. | Should |

## 4. Traceability

| SRS Requirement ID | Source (file:line) | Notes |
|---|---|---|
| PROMPT-FR-01 | includes/class-gregius-data-prompt.php:27, :73-122 | `gregius_prompt` post type registration |
| PROMPT-FR-02 | includes/class-gregius-data-prompt.php:168-218 | Meta field registration |
| PROMPT-FR-03 | includes/class-gregius-data-prompt.php:129-161, includes/class-gregius-data-activator.php:99-139 | Taxonomy registration + term seeding |
| PROMPT-FR-04 | includes/class-gregius-data-prompt-resolver.php:33-71 | Type-scoped fallback (explicit → selected → factory) |
| PROMPT-FR-05 | includes/class-gregius-data-prompt-resolver.php:82-120, :172-184 | Candidate validation; termless → 'system' |
| PROMPT-FR-06 | includes/class-gregius-data-prompt-resolver.php:193-201 | `{{date}}`/`{{time}}`/`{{datetime}}` expansion |
| PROMPT-FR-07 | includes/api/class-gregius-data-rest-prompts-controller.php:39-87, :357-386 | Routes + `prompt_type` enum |
| PROMPT-FR-08 | includes/api/class-gregius-data-rest-prompts-controller.php:95-105 | `manage_options` permission check |
| PROMPT-FR-09 | includes/class-gregius-data-activator.php:146-183, :195-239 | Three factory prompt seeds |
| PROMPT-FR-10 | includes/class-gregius-data-activator.php:246-279 | Legacy type migration |
| PROMPT-FR-11 | includes/class-gregius-data-activator.php:287-333 | Placeholder migration |
| PROMPT-FR-12 | includes/rag/class-gregius-data-rag-service.php:3264, :1898-1905 | System prompt resolution + filter |
| PROMPT-FR-13 | includes/rag/class-gregius-data-rag-service.php:1269-1274, :3294-3357 | Pre-retrieval gatekeeper |
| PROMPT-FR-14 | includes/rag/class-gregius-data-rag-service.php:1276-1298 | Unsafe short-circuit |
| PROMPT-FR-15 | includes/rag/class-gregius-data-rag-service.php:1300-1301, :3346-3356, includes/class-gregius-data-interaction.php:351-423 | security_check metadata |
| PROMPT-FR-16 | includes/api/class-gregius-data-rest-prompts-controller.php:317-350, :451-482 | Same-type activation deselect |
| PROMPT-FR-17 | includes/cli/class-gregius-data-cli-answer.php:239-318, includes/class-gregius-data-abilities-manager.php:405-416 | Prompt override + conflict rejection |
| PROMPT-DR-01 | includes/class-gregius-data-prompt.php:27 | `POST_TYPE` constant |
| PROMPT-DR-02 | includes/class-gregius-data-prompt.php:41, :168-218 | `_gregius_prompt_*` prefix + keys |
| PROMPT-DR-03 | includes/class-gregius-data-prompt.php:34, :129-161 | `TAXONOMY` constant + terms |
| PROMPT-DR-04 | includes/class-gregius-data-prompt-resolver.php:110-119 | content + metadata result |
| PROMPT-DR-05 | includes/class-gregius-data-prompt-resolver.php:210-213 | SHA-256 normalize/trim |
| PROMPT-DR-06 | includes/class-gregius-data-prompt-resolver.php:49-65, :116 | `explicit`/`selected`/`factory` source |
| PROMPT-DR-07 | includes/api/class-gregius-data-rest-prompts-controller.php:394-409 | Response payload fields |
| PROMPT-DR-08 | includes/rag/class-gregius-data-rag-service.php:3346-3356 | security_check result fields |
| PROMPT-DR-09 | includes/class-gregius-data-interaction.php:224, :404, :423 | interaction security_check object |
| PROMPT-OR-01 | includes/api/class-gregius-data-rest-prompts-controller.php:95-105 | `manage_options` capability |
| PROMPT-OR-02 | includes/class-gregius-data-prompt-resolver.php:67-70 | Structured `WP_Error` |
| PROMPT-OR-03 | includes/cli/class-gregius-data-cli-answer.php:243-247, includes/class-gregius-data-abilities-manager.php:405-407 | Conflicting-input rejection |
| PROMPT-OR-04 | includes/class-gregius-data-activator.php:100-102, :196-198, :247-249, :288-290 | Idempotency option guards |
| PROMPT-OR-05 | includes/rag/class-gregius-data-rag-service.php:3295-3299, :3330-3331 | Dedicated 'security' prompt |
| PROMPT-OR-06 | includes/rag/class-gregius-data-rag-service.php:3366-3392 | Graceful parse, fail-open |
| PROMPT-OR-07 | includes/api/class-gregius-data-rest-prompts-controller.php:451-482 | Same-type deselect |
| PROMPT-QR-01 | includes/class-gregius-data-prompt-resolver.php:33-71 | Deterministic resolution |
| PROMPT-QR-02 | includes/class-gregius-data-prompt-resolver.php:210-213 | Stable hash |
| PROMPT-QR-03 | docs/prompt/srs.md (§4) | Traceability mapping |
| PROMPT-QR-04 | includes/class-gregius-data-prompt-resolver.php:193-201 | `wp_date` locale-aware expansion |
