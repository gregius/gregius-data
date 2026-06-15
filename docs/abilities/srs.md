# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Abilities API Integration |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| SyRS Reference | N/A - no standalone system-level abilities package |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data Abilities subsystem must do to register plugin capabilities with the WordPress Abilities API and expose them as machine-discoverable tool contracts.

### 1.2 System Scope

The subsystem includes:
- Ability category registration
- Ability registration with input/output schema contracts
- Ability execution callbacks
- Permission enforcement per ability
- REST and MCP-facing metadata exposure via Abilities API

Software identifier: gregius-data-abilities

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The subsystem bridges Gregius plugin runtime services with WordPress Abilities API primitives so external clients can discover and execute supported capabilities through standardized contracts.

#### 1.3.2 System Functions Summary

- Register categories (`ai`, `gregius-data`) via Abilities categories hook.
- Register three abilities: `gregius-data/answer`, `gregius-data/list-connections`, `gregius-data/list-models`.
- Expose JSON input and output schemas for each ability.
- Enforce per-ability permissions (`read` for answer; `manage_options` for connection/model listing).
- Execute abilities by delegating to existing plugin services (RAG service, settings manager, model registry).
- Publish metadata for REST and MCP-style discovery (`show_in_rest`, annotations, mcp public/tool metadata).
- Keep prompt selection and security prompt selection internal to runtime resolver logic for the basic MCP-facing answer contract.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| AI Agent / Tooling Client | Technical | Abilities REST contracts and MCP adapters |
| Site Administrator | Technical/Operational | Abilities management and connection/model discovery |
| Authenticated Site User | Mixed | `answer` execution (permission `read`) |
| Plugin Developer | Technical | Ability registration and callback extension paths |

## 2. References

- `docs/abilities/architecture.md`
- `docs/abilities/developer-documentation.md`
- `includes/class-gg-data-abilities-manager.php`
- `includes/rag/class-gg-data-rag-service.php`
- `includes/class-gg-data-settings-manager.php`
- `includes/class-gg-data-model-registry.php`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| ABIL-FR-01 | The software MUST register abilities categories on `wp_abilities_api_categories_init` when `wp_register_ability_category` is available. | Must |
| ABIL-FR-02 | The software MUST register abilities on `wp_abilities_api_init` when `wp_register_ability` is available. | Must |
| ABIL-FR-03 | The software MUST register category `ai` for AI/ML capability grouping. | Must |
| ABIL-FR-04 | The software MUST register category `gregius-data` for plugin operational utility grouping. | Must |
| ABIL-FR-05 | The software MUST register ability `gregius-data/answer` with execute callback, permission callback, and schema contracts. | Must |
| ABIL-FR-06 | The software MUST register ability `gregius-data/list-connections` with execute callback, permission callback, and schema contracts. | Must |
| ABIL-FR-07 | The software MUST register ability `gregius-data/list-models` with execute callback, permission callback, and schema contracts. | Must |
| ABIL-FR-08 | The `answer` ability MUST delegate answer generation to the RAG service with validated inputs and options mapping. | Must |
| ABIL-FR-09 | The `list-connections` ability MUST return available configured connections with normalized output fields and MAY include optional per-connection embedding model context when explicitly requested. | Must |
| ABIL-FR-10 | The `list-models` ability MUST return model inventory with optional type filtering and normalized output fields. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| ABIL-DR-01 | All registered abilities MUST declare input schemas in object form suitable for Abilities API discovery. | Must |
| ABIL-DR-02 | All registered abilities MUST declare output schemas in object form suitable for Abilities API discovery. | Must |
| ABIL-DR-03 | Ability metadata MUST include `show_in_rest=true`, `annotations`, and MCP metadata (`public`, `type=tool`). | Must |
| ABIL-DR-04 | The `answer` ability contract MUST require `query`, `connection_name`, `embedding_model`, and `answer_model` for basic execution. | Must |
| ABIL-DR-05 | The `list-models` contract MUST support optional `type` filtering across embeddings, llm, and rerank model classes, and MUST NOT require connection input. | Must |
| ABIL-DR-06 | Errors for invalid input combinations (e.g., conflicting prompt arguments) SHOULD return structured `WP_Error` responses. | Should |
| ABIL-DR-07 | Basic MCP-facing answer contract SHOULD keep prompt selection internal and not require prompt identifiers in public payloads. | Should |
| ABIL-DR-08 | The `list-connections` contract SHOULD preserve default response shape for backward compatibility when optional enrichment flags are omitted. | Should |
| ABIL-DR-09 | When `include_embedding_models` is true, the `list-connections` contract MUST include deterministic per-connection embedding context fields (`active_keys`, `active_count`), and MAY include safe model metadata when `include_model_details` is true. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| ABIL-OR-01 | The subsystem MUST degrade gracefully when Abilities API functions are unavailable by skipping registration without fatal errors. | Must |
| ABIL-OR-02 | The `answer` ability permission callback MUST require at least `read` capability. | Must |
| ABIL-OR-03 | The `list-connections` and `list-models` permission callbacks MUST require `manage_options` capability. | Must |
| ABIL-OR-04 | The subsystem SHOULD preserve stable ability IDs and category names for external client compatibility. | Should |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| ABIL-QR-01 | Ability registration MUST be deterministic across requests when API availability conditions are unchanged. | Registration inspection |
| ABIL-QR-02 | Contracts SHOULD remain backward-compatible for existing ability consumers. | Contract diff review |
| ABIL-QR-03 | Ability output normalization MUST provide consistent field names for automation clients. | Output inspection |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Hook registration and callback execution checks | Code-path and runtime request checks |
| Data/contract requirements | Schema and metadata inspection | Registered ability payload review |
| Operations requirements | Capability and availability checks | Permission tests + API-absence simulation |
| Quality requirements | Contract and output consistency checks | Multi-call contract inspection |

Acceptance baseline:
- Categories and three abilities are discoverable when Abilities API is active.
- Permission callbacks enforce expected capability boundaries.
- Input/output schemas and metadata are present and consistent.
- Execution callbacks delegate correctly to plugin services.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| ABIL-FR-01..04, ABIL-OR-01 | `includes/class-gg-data-abilities-manager.php` | Registration hooks, category setup, API-availability guards |
| ABIL-FR-05..07, ABIL-DR-01..03, ABIL-OR-02..03 | `includes/class-gg-data-abilities-manager.php` | Ability definitions, permission callbacks, schema/meta contracts |
| ABIL-FR-08, ABIL-DR-04, ABIL-DR-06 | `includes/class-gg-data-abilities-manager.php`, `includes/rag/class-gg-data-rag-service.php` | Answer callback flow, options normalization, validation errors |
| ABIL-FR-09, ABIL-DR-08, ABIL-DR-09 | `includes/class-gg-data-abilities-manager.php`, `includes/class-gg-data-settings-manager.php`, `includes/class-gg-data-connection-model-manager.php` | Connection listing normalization and optional embedding model context |
| ABIL-FR-10, ABIL-DR-05, ABIL-QR-03 | `includes/class-gg-data-abilities-manager.php`, `includes/class-gg-data-model-registry.php` | Model listing/type filtering over global provider registry |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Abilities functionality depends on WordPress Abilities API availability.
- MCP exposure is mediated by Abilities metadata and external adapter/runtime support.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| ABIL-TBD-01 | 3.2 | Output-schema field depth for `answer` metadata is intentionally partial and may need stricter contract codification if external consumers rely on full metadata shape. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Split abilities docs into canonical SRS/architecture/developer package | Align with plugin-wide ISO documentation structure and retire monolithic architecture docs | Documentation migration initiative |
