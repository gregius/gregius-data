# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Interaction Tracking |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| SyRS Reference | N/A - no separate system-level interaction requirements package |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data interaction subsystem must do to capture and expose search and RAG interaction events as persistent records for operational analysis, debugging, and downstream data workflows.

### 1.2 System Scope

The interaction subsystem includes:
- Interaction capture for RAG completions and search completions
- Persistent storage in `gg_interaction` custom posts
- Structured metadata and JSON payload contracts for each interaction
- Author-aware REST access to interaction records
- Operational logging integration for quick inspection
- Sync control behavior for interaction records in the broader sync architecture

Software identifier: gregius-data-interactions

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The subsystem listens to runtime events emitted by the RAG and search subsystems, records normalized interaction data as `gg_interaction` posts, and exposes those records through REST with permission-aware access control.

#### 1.3.2 System Functions Summary

- Capture successful RAG and search lifecycle events into interaction records.
- Persist interaction fields in both typed post meta and canonical JSON interaction payloads.
- Group RAG turns by conversation UUID and append subsequent turns to existing conversations.
- Expose interaction records through `gg-data/v1/interactions` endpoints.
- Enforce author-aware access: admins can access all records; logged-in users are scoped to their own records.
- Exclude interaction records from immediate post-save sync behavior.
- Emit extension hooks for metadata registration, log-context shaping, and post-record notifications.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Visitor (Indirect) | Non-technical | Frontend search/RAG experiences that emit interaction events |
| Logged-in Site User | Mixed | REST interaction endpoints scoped to owned interactions |
| Site Administrator | Technical/Operational | REST endpoints and logs with full interaction visibility |
| Plugin Developer/Integrator | Technical | Hooks and extensibility contracts |

## 2. References

- `docs/interactions/architecture.md`
- `docs/interactions/developer-documentation.md`
- `docs/rag/architecture.md`
- `docs/search/architecture.md`
- `includes/class-gg-data-interaction.php`
- `includes/api/class-gg-data-rest-interactions-controller.php`
- `includes/rag/class-gg-data-rag-service.php`
- `includes/search/class-gg-data-search-integration.php`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| INT-FR-01 | The software MUST register and maintain a dedicated interaction post type named `gg_interaction`. | Must |
| INT-FR-02 | The software MUST record RAG interaction outcomes when `gg_data_rag_complete` is emitted and a valid conversation identifier is present. | Must |
| INT-FR-03 | The software MUST record search interaction outcomes when `gg_data_search_completed` is emitted and a valid connection identifier is present. | Must |
| INT-FR-04 | The software MUST store interaction type, source, connection, and canonical JSON interaction data for each record. | Must |
| INT-FR-05 | The software MUST support RAG multi-turn conversations by appending new turns to an existing conversation identified by UUID. | Must |
| INT-FR-06 | The software MUST create a new RAG conversation record when no matching conversation UUID exists. | Must |
| INT-FR-07 | The software MUST expose REST endpoints for listing, reading, creating, updating, and deleting interaction records under `gg-data/v1/interactions`, with actor-specific governance rules. | Must |
| INT-FR-08 | The software MUST restrict direct `POST /interactions` creation to administrators; non-admin interaction creation MUST occur through chat lifecycle flows (RAG/SSE). | Must |
| INT-FR-09 | The software MUST allow administrators to list and access all interaction records. | Must |
| INT-FR-10 | The software MUST restrict non-admin REST reads/updates/deletes to interaction records authored by the current user. | Must |
| INT-FR-11 | The software MUST expose extension hooks for interaction meta-field registration, log-context shaping, and post-record notifications. | Must |
| INT-FR-12 | The software MUST prevent interaction records from participating in real-time sync decisions by default. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| INT-DR-01 | The interaction subsystem MUST register the following REST-exposed meta keys: `_gg_interaction_type`, `_gg_interaction_source`, `_gg_interaction_connection`, `_gg_interaction_conversation_id`, `_gg_interaction_zero_results`, `_gg_interaction_data`. | Must |
| INT-DR-02 | For search interactions, the software MUST persist exactly one interaction record per completed search event payload. | Must |
| INT-DR-03 | For RAG interactions, the software MUST maintain a conversation turn history in both markdown post content and structured JSON metadata. | Must |
| INT-DR-04 | Conversation identifiers used for RAG recording MUST be validated as UUIDs before persistence. | Must |
| INT-DR-05 | The subsystem MUST include source context in recorded payloads so origin (`frontend`, `rest`, `wpcli`, or `mcp`) remains queryable. | Must |
| INT-DR-06 | Post-record logging context SHOULD include enough metadata to inspect query, source, model, and retrieval/policy details for RAG interactions. | Should |
| INT-DR-07 | REST access-denial responses MUST return authorization errors when users attempt to access records they do not own. | Must |
| INT-DR-08 | For manifest-aware RAG turns, interaction payloads and log context MUST preserve manifest observability fields (`manifest`, `manifest_hash`, `manifest_size_bytes`, `manifest_version`). | Must |
| INT-DR-09 | In multisite, explicit cross-site interaction governance context MUST be accepted only from super admins with valid target site IDs. | Must |
| INT-DR-10 | In multisite admin editor flows, interaction routing MUST remain site-local by default and MUST NOT use implicit network-wide post-ID lookup across sites. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| INT-OR-01 | The subsystem MUST initialize interaction post-type registration, meta registration, and event listeners during plugin bootstrap. | Must |
| INT-OR-02 | The subsystem MUST support concurrent RAG turn recording safely by applying a short-lived conversation lock before append/create operations. | Must |
| INT-OR-03 | The subsystem MUST release conversation locks after processing, including failure paths. | Must |
| INT-OR-04 | The subsystem SHOULD continue operation when interaction logging fails, without blocking interaction record persistence. | Should |
| INT-OR-05 | The subsystem MUST remain compatible with existing RAG and search emissions without requiring changes in callers. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| INT-QR-01 | Interaction recording MUST be additive and non-destructive to existing conversation history. | Turn append inspection |
| INT-QR-02 | REST access-control behavior MUST enforce least privilege for non-admin users by default, including denied direct create and denied cross-site context. | Permission test |
| INT-QR-03 | Interaction payload structures SHOULD remain stable for downstream analytics and integration consumers. | Contract inspection |
| INT-QR-04 | Interaction recording MUST fail safely when conversation IDs are invalid by returning a structured error instead of corrupting records. | Validation test |
| INT-QR-05 | Interaction capture SHOULD preserve source and model metadata required for cross-interface parity analysis. | Metadata review |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Hook-driven runtime validation + REST endpoint checks | Runtime inspection + REST tests |
| Data/contract requirements | Meta key and JSON payload inspection | Post/meta inspection + API response review |
| Operations requirements | Locking and listener behavior checks | Code-path inspection + controlled concurrent requests |
| Quality requirements | Contract stability and access-control checks | Metadata review + permission tests |

Acceptance baseline:
- RAG and search events are captured in interaction records.
- Author-aware REST access behaves as specified.
- Interaction payload and meta fields are complete and queryable.
- Extension hooks are available and callable by integrators.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| INT-FR-01, INT-OR-01 | `includes/class-gg-data-interaction.php` | Post type and init-time registration responsibilities |
| INT-FR-02, INT-FR-05, INT-FR-06, INT-DR-03, INT-DR-04, INT-OR-02, INT-OR-03 | `includes/class-gg-data-interaction.php` | RAG event listener, UUID validation, lock handling, append/create behavior |
| INT-FR-03, INT-DR-02 | `includes/class-gg-data-interaction.php`, `includes/search/class-gg-data-search-integration.php` | Search completion emission and record creation path |
| INT-FR-04, INT-DR-01, INT-DR-05, INT-QR-05 | `includes/class-gg-data-interaction.php` | Meta-field registration and payload persistence |
| INT-DR-08 | `includes/class-gg-data-interaction.php`, `includes/rag/class-gg-data-rag-service.php` | Manifest metadata persistence for interaction telemetry |
| INT-FR-07 to INT-FR-10, INT-DR-07, INT-DR-09, INT-QR-02 | `includes/api/class-gg-data-rest-interactions-controller.php` | Endpoint namespace/base and permission model |
| INT-DR-10 | `includes/class-gg-data-interaction.php` | Multisite interaction editor routing is explicit-only and site-local by default |
| INT-FR-11 | `includes/class-gg-data-interaction.php` | Hook extension points (`meta_fields`, `log_context`, `recorded`) |
| INT-FR-12 | `includes/class-gg-data-interaction.php` | `gg_data_should_sync_post` filter behavior |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- RAG and Search subsystems are treated as upstream event emitters for this component.
- Logging internals are documented in their own subsystem and are referenced here only as an integration dependency.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| INTERACTION-TBD-01 | 3.2, 3.4 | Search-side duplicate detection/normalization policy is not formalized as a contract-level rule for repeated identical queries. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Use canonical split package (`srs.md`, `architecture.md`, `developer-documentation.md`) for interactions | Align with plugin-wide ISO documentation pattern and retire monolithic architecture docs | Documentation migration initiative |
