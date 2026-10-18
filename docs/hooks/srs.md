# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Hooks and Extension Surface Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data hook subsystem must do to provide a stable, discoverable, and governable extension surface for plugin developers.

### 1.2 System Scope

The subsystem includes custom action/filter naming policy, hook signature contracts, stability tiering (public, semi-public, internal), backward-compatibility expectations, and documentation obligations for emitted hook surfaces.

Software identifier: gregius-data-hooks

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

Hooks connect plugin subsystems (sync, chunking, search, RAG, providers, interaction tracking) with extension code in themes, plugins, and custom integrations.

#### 1.3.2 System Functions Summary

- Emit and consume custom hooks under a controlled namespace.
- Provide stable signatures for Tier 1 public hooks.
- Distinguish public, semi-public, and internal hook surfaces.
- Support dynamic hook variants where required by feature design.
- Maintain documentation parity between emitted hooks and canonical docs.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Plugin Integrator | Advanced | add_filter/add_action in custom plugins |
| Site Developer | Intermediate | Child theme and mu-plugin hook usage |
| Maintainer | Advanced | Hook emission sites in PHP source |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Tier 1 Hook | Public, stable extension contract intended for third-party use |
| Tier 2 Hook | Semi-public hook for advanced extensions with caveats |
| Tier 3 Hook | Internal hook not guaranteed as stable public API |
| Emission Site | Source location where `apply_filters` or `do_action` is called |
| Dynamic Hook | Hook name that includes runtime segment (for example tool/provider suffix) |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| RAG | Retrieval-Augmented Generation |
| REST | Representational State Transfer |

## 2. References

- docs/hooks/architecture.md
- includes/hooks/class-gregius-data-lifecycle-hooks.php
- includes/class-gregius-data-chunker.php
- includes/search/class-gregius-data-search-integration.php
- includes/rag/class-gregius-data-rag-service.php
- includes/ai/class-gregius-data-llm-registry.php
- includes/class-gregius-data-interaction.php

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| HOOKS-FR-01 | The software MUST emit custom hooks using the `gregius_data_` prefix for plugin-owned extension points, except for intentionally documented dynamic variants. | Must |
| HOOKS-FR-02 | The software MUST expose Tier 1 public hooks for core extension categories: sync control, chunking strategy/output, search tuning, interaction tracking, and RAG tool registration. | Must |
| HOOKS-FR-03 | The software MUST preserve expected execution order semantics between hook emissions and core processing for documented Tier 1 hooks. | Must |
| HOOKS-FR-04 | The software MUST allow filters to override default values where a default value is provided at emission. | Must |
| HOOKS-FR-05 | The software MUST support action hooks for post-event notifications where no return value is expected. | Must |
| HOOKS-FR-06 | The software SHOULD expose dynamic hook variants only where static alternatives are insufficient (for example per-tool execution hooks). | Should |
| HOOKS-FR-07 | The software MUST classify hooks into stability tiers and document those tiers in canonical hook docs. | Must |
| HOOKS-FR-08 | The software MUST keep Tier 1 hook names and parameter contracts backward compatible within minor releases. | Must |
| HOOKS-FR-09 | The software SHOULD document Tier 2 hooks with explicit caveats for compatibility expectations. | Should |
| HOOKS-FR-10 | The software MAY expose Tier 3 internal hooks but MUST identify them as internal/non-contract surfaces in developer docs. | May |
| HOOKS-FR-11 | The software MUST expose a retention-days filter hook for logs governance with site context. | Must |
| HOOKS-FR-12 | The software MUST expose before/after retention purge action hooks for observability and extension points with site context. | Must |
| HOOKS-FR-13 | The software MUST expose a pre-retrieval filter hook to allow exclusion of document types, date ranges, and taxonomy constraints before candidate search. | Must |
| HOOKS-FR-14 | The software MUST expose an LLM response filter hook to allow post-processing of raw answer text before citation normalization. | Must |
| HOOKS-FR-15 | The software MUST expose a feedback submission action hook to fire when user feedback is persisted on an interaction for RLHF signal collection. | Must |
| HOOKS-FR-21 | The software MUST expose a `gregius_data_metadata_manifest` Tier 1 filter hook at the return of `Gregius_Data_Content_Cleaner::build_metadata_manifest()` so external plugins can enrich the manifest written to the PostgreSQL sync table. Signature: `(array $manifest, int $post_id): array`. | Must |
| HOOKS-FR-22 | The software MUST expose a `gregius_data_sync_post_types_updated` Tier 1 action hook after sync post type configuration is persisted for a connection, so downstream plugins can rebuild connection-scoped corpus artifacts. Signature: `(string $connection_name, array $enabled_post_types): void`. | Must |
| HOOKS-FR-24 | The software MUST expose a `gregius_data_vector_delete_batch_size` Tier 1 filter hook for operational tuning of vector batch deletion by model and connection. Signature: `(int $batch_size, string $model_key, string $connection_name): int`. | Must |
| HOOKS-FR-25 | The software MUST expose a `gregius_data_rag_tool_selection_system_prompt` Tier 1 filter hook in every tool-selection strategy so downstream plugins can inject context into the routing model's system prompt (e.g. entity grounding). Signature: `(string $system_prompt): string`. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| HOOKS-DR-01 | Tier 1 hook documentation MUST include hook type (filter/action), parameters, defaults, and emission site path. | Must |
| HOOKS-DR-02 | Filter hooks MUST return values of the same expected semantic type as their documented defaults. | Must |
| HOOKS-DR-03 | Action hooks MUST document side-effect intent and parameter payload contracts. | Must |
| HOOKS-DR-04 | Dynamic hooks MUST document the dynamic segment format and an example concrete hook name. | Must |
| HOOKS-DR-05 | Hook categories in documentation MUST map to implementation subsystems (sync, chunking, search, interaction, RAG, provider). | Must |
| HOOKS-DR-06 | Hooks documented as deferred or unknown status MUST NOT be represented as active Tier 1 contracts. | Must |
| HOOKS-DR-07 | Retention hook documentation MUST preserve argument order as `(days, threshold/deleted, site_id)` for action hooks and `(days, site_id)` for filter hooks. | Must |

### 3.3 Operations and Governance Requirements

| ID | Requirement | Priority |
|---|---|---|
| HOOKS-OR-01 | The codebase is the source of truth for hooks; `docs/hooks/developer-documentation.md` MUST be the canonical, code-derived catalog for all extension contracts. | Must |
| HOOKS-OR-02 | Before retiring monolithic hook docs, all inbound references MUST be rewired to canonical hooks package docs. | Must |
| HOOKS-OR-03 | Hook parity audits SHOULD verify documented Tier 1 hooks against live emission sites prior to release. | Should |
| HOOKS-OR-04 | Tier 1 hook removals or incompatible parameter changes MUST require explicit deprecation and migration notes. | Must |
| HOOKS-OR-05 | Tier 2/Tier 3 hook promotion to Tier 1 MUST require documentation hardening and stability declaration. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| HOOKS-QR-01 | Hook documentation SHOULD remain parity-aligned with implementation for Tier 1 hooks. | Hook catalog parity scan |
| HOOKS-QR-02 | Tier 1 examples SHOULD be concise, executable WordPress snippets using `add_filter`/`add_action`. | Documentation review |
| HOOKS-QR-03 | Hook docs SHOULD distinguish public vs internal hooks clearly enough to avoid unintended coupling. | Documentation review |
| HOOKS-QR-04 | Reference links in docs SHOULD remain valid after monolith retirement. | Link/reference verification |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Hook emission and category coverage checks | Code scan of apply_filters/do_action sites |
| Data/contract requirements | Signature and default-value documentation checks | Developer doc audit |
| Governance requirements | Reference rewiring and deprecation policy checks | Repo-wide grep + doc review |
| Quality requirements | Clarity and parity checks | Canonical docs review |

Acceptance baseline:
- Canonical hooks package requirements reflect active implementation for Tier 1 extension surface.
- Internal/deferred hook surfaces are clearly distinguished from stable contracts.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| HOOKS-FR-01 to HOOKS-FR-05 | includes/hooks/class-gregius-data-lifecycle-hooks.php, includes/class-gregius-data-chunker.php, includes/search/class-gregius-data-search-integration.php, includes/class-gregius-data-interaction.php | Core sync/chunk/search/interaction hook emissions |
| HOOKS-FR-06 to HOOKS-FR-10 | includes/rag/class-gregius-data-rag-service.php, includes/ai/class-gregius-data-llm-registry.php | Dynamic and tiered hook surface |
| HOOKS-FR-11, HOOKS-FR-12 | includes/class-gregius-data-logger.php | Logs retention governance hooks |
| HOOKS-FR-13 | includes/rag/class-gregius-data-rag-service.php | Pre-retrieval filtering hook (new Tier 1) |
| HOOKS-FR-14 | includes/rag/class-gregius-data-rag-service.php | LLM response post-processing hook (new Tier 1) |
| HOOKS-FR-15 | includes/api/class-gregius-data-rest-interactions-controller.php | Feedback submission action hook (new Tier 1) |
| HOOKS-FR-21 | includes/class-gregius-data-content-cleaner.php | Metadata manifest sync enrichment hook (Tier 1) |
| HOOKS-FR-22 | includes/api/class-gregius-data-rest-sync-controller.php | Sync post types updated action hook (Tier 1) |
| HOOKS-FR-24 | includes/api/class-gregius-data-rest-vector-queue-controller.php | Vector batch delete batch-size filter hook (Tier 1) |
| HOOKS-DR-01 to HOOKS-DR-07 | docs/hooks/developer-documentation.md + emission source files | Hook signature, ordering, tier contract obligations |
| HOOKS-OR-01 to HOOKS-OR-05 | docs/hooks package + docs/README rewiring | Governance and retirement policy obligations |
| HOOKS-QR-01 to HOOKS-QR-04 | Canonical docs and repo reference checks | Parity and integrity quality obligations |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Hook stability assumptions apply to Tier 1 hooks only unless explicitly stated.
- Dynamic hook families require concrete examples to avoid extension ambiguity.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| HOOKS-TBD-01 | 3.1 | Dynamic tool-hook families should include additional concrete examples beyond `gregius_data_rag_tool_search_entity_content` to reduce ambiguity for integrators. | Engineering | TBD |
| HOOKS-TBD-02 | 3.4 | Tier 2/Tier 3 boundaries for RAG planning internals should be revisited in future governance pass. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Split hooks monolith into canonical package | Improve contract clarity and parity governance for extension surface | Documentation migration decision |
| 2026-04-04 | Use tier model (Tier 1/2/3) in canonical docs | Avoid overpromising internal hooks as stable API | Documentation migration decision |
| 2026-04-04 | Promote `gregius_data_rag_search_query` and `gregius_data_rag_source_min_relevance` to Tier 1 | Both hooks are live, externally useful, and signature-stable for extension use | Hooks tier parity audit |
