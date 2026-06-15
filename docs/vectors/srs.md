# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Vectors and Embeddings Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data vectors and embeddings subsystem must do to generate, store, and expose semantic embeddings used by search and retrieval workflows.

### 1.2 System Scope

The subsystem includes vector generation orchestration, strategy selection (internal TF-IDF and API embeddings), vocabulary lifecycle management, embedding storage contracts, and vector-related REST management endpoints.

Software identifier: gregius-data-vectors

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The vectors subsystem consumes cleaned and chunked content from sync contracts and produces embedding rows in PostgreSQL tables that are consumed by search and RAG retrieval flows.

#### 1.3.2 System Functions Summary

- Generate vectors through a strategy-based orchestration layer.
- Support internal TF-IDF and provider-backed API embedding models.
- Manage vocabulary preparation, status, and cache clearing for TF-IDF.
- Expose vector queue, batch generation, status, and deletion endpoints.
- Expose per-connection model association and vector model controls.
- Preserve consistent embedding row contracts for title, excerpt, and chunk vectors.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | Dashboard vectors pages and REST management endpoints |
| Plugin Developer or Integrator | Technical | Strategy interfaces, model contracts, and extension points |
| Operations | Technical/Operational | Status endpoints, logs, and regeneration workflows |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Vector strategy | Implementation that generates embeddings for a model contract (for example TF-IDF or API embeddings) |
| Vocabulary cache | Stored TF-IDF term/IDF corpus and metadata required for deterministic TF-IDF generation |
| Row-per-embedding schema | Storage pattern where title, excerpt, and each chunk embedding is stored as an individual row |
| Model registry | Settings-backed model catalog providing model_key, provider, dimensions, and table contracts |
| Connection-model association | Mapping of active models to a specific PostgreSQL connection |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| TF-IDF | Term Frequency-Inverse Document Frequency |
| API | Application Programming Interface |
| REST | Representational State Transfer |
| PDO | PHP Data Objects |

## 2. References

- docs/vectors/architecture.md
- includes/vectors/class-gg-data-vector-generator.php
- includes/vectors/interface-gg-data-vector-strategy.php
- includes/vectors/strategies/class-gg-data-tfidf-strategy.php
- includes/vectors/strategies/class-gg-data-api-embeddings-strategy.php
- includes/vectors/class-gg-data-vocabulary-manager.php
- includes/class-gg-data-tfidf-300-embeddings.php
- includes/class-gg-data-model-registry.php
- includes/class-gg-data-connection-model-manager.php
- includes/api/class-gg-data-rest-vector-queue-controller.php
- includes/api/class-gg-data-rest-vocabulary-controller.php
- includes/api/class-gg-data-rest-connection-models-controller.php
- includes/class-gg-data-chunker.php
- includes/search/sql/create-search-function.sql

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| VEC-FR-01 | The software MUST provide a vector generation orchestrator that selects an appropriate generation strategy from model configuration. | Must |
| VEC-FR-02 | The software MUST support at least one internal TF-IDF strategy, one internal stateless hashing strategy, and one API embeddings strategy. | Must |
| VEC-FR-03 | The software MUST support batch vector generation by model key and connection name. | Must |
| VEC-FR-04 | The software MUST provide vocabulary preparation for TF-IDF generation before TF-IDF batches can run. | Must |
| VEC-FR-05 | The software MUST provide vocabulary status reporting including drift-oriented readiness indicators. | Must |
| VEC-FR-06 | The software MUST support vocabulary cache clearing for controlled regeneration workflows. | Must |
| VEC-FR-07 | The software MUST expose vector queue and vector status endpoints for operators. | Must |
| VEC-FR-08 | The software MUST support clearing vectors for a connection and optional model context. | Must |
| VEC-FR-09 | The software MUST support per-connection model association retrieval, addition, and removal. | Must |
| VEC-FR-10 | The software MUST support row-per-embedding generation for title, excerpt, and chunk payloads. | Must |
| VEC-FR-11 | The software MUST support both direct PostgreSQL and PostgREST-compatible vocabulary workflows based on connection type. | Must |
| VEC-FR-12 | The software MUST support model lookup fallback from connection-specific to global model registry scope when configured. | Must |
| VEC-FR-13 | The software MUST preserve compatibility with downstream retrieval consumers that expect vector table and embedding contract stability. | Must |
| VEC-FR-14 | The software SHOULD support incremental regeneration controls (for example regenerate_since windows) for operational batch workflows. | Should |
| VEC-FR-15 | The software MUST support at least one stateless internal embedding strategy that requires no vocabulary preparation before generation. | Must |
| VEC-FR-16 | The software MUST expose a batch vector deletion endpoint for large vector tables and support both direct PostgreSQL and PostgREST/Supabase execution paths. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| VEC-DR-01 | Vector strategy contracts MUST accept model metadata including provider, dimensions, vector table name, and provider model identifier. | Must |
| VEC-DR-02 | Vector strategy results MUST return success status, message, processed count, failed count, and total tokens. | Must |
| VEC-DR-03 | Embedding storage contracts MUST preserve a unique row identity across post_id, field_type, and chunk_index. | Must |
| VEC-DR-04 | Field-type contracts MUST distinguish title, excerpt, and chunk rows in storage and retrieval operations. | Must |
| VEC-DR-05 | Vector and vocabulary REST endpoints MUST return structured success/error payloads with operator-actionable messages. | Must |
| VEC-DR-06 | Connection-model endpoint contracts MUST mask sensitive API key material in response payloads. | Must |
| VEC-DR-07 | Vocabulary metadata contracts MUST include version, post-count context, and generation timestamp indicators. | Must |
| VEC-DR-08 | Model contracts MUST identify target vector table names deterministically for each active embedding model. | Must |
| VEC-DR-09 | Chunk payload contracts consumed by vector strategies MUST be model-agnostic and connection-aware. | Must |
| VEC-DR-10 | Error payload contracts MUST preserve machine-actionable error codes for route clients. | Must |
| VEC-DR-11 | Batch delete responses MUST include progress fields sufficient for iterative clients: deleted, total_deleted, has_more, next_offset, duration_ms, and errors. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| VEC-OR-01 | Vector and vocabulary management endpoints MUST enforce administrative authorization checks. | Must |
| VEC-OR-02 | The subsystem MUST provide graceful error responses when database connections cannot be established. | Must |
| VEC-OR-03 | The subsystem SHOULD cap queue/list request limits to protect runtime stability. | Should |
| VEC-OR-04 | The subsystem MUST log strategy registration and generation outcomes with operational context. | Must |
| VEC-OR-05 | The subsystem MUST allow connection-specific model activation and deactivation without requiring plugin code changes. | Must |
| VEC-OR-06 | TF-IDF generation MUST refuse to run when prerequisite vocabulary state is unavailable or invalid. | Must |
| VEC-OR-07 | The subsystem SHOULD support safe vector regeneration workflows without requiring full schema recreation. | Should |
| VEC-OR-08 | The subsystem MUST preserve compatibility with multisite-aware content contracts where applicable. | Must |
| VEC-OR-09 | Batch delete behavior MUST avoid destructive offset drift and MUST terminate safely if no rows are deleted while rows remain. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| VEC-QR-01 | Vector generation pathways SHOULD preserve deterministic model-to-strategy routing for equivalent model metadata. | Routing behavior tests |
| VEC-QR-02 | The subsystem MUST preserve row-level deduplication guarantees through storage uniqueness constraints. | Schema and integration checks |
| VEC-QR-03 | Vocabulary caching SHOULD reduce repeated TF-IDF generation overhead versus vocabulary-per-batch behavior. | Runtime profiling |
| VEC-QR-04 | API embeddings pathways SHOULD preserve token observability for operator reporting. | Payload and stats checks |
| VEC-QR-05 | Provider-path behavior for vocabulary and generation SHOULD remain parity-aligned between direct and HTTP-backed connections. | Cross-provider parity checks |
| VEC-QR-06 | Vector status and posts-list endpoints SHOULD remain responsive under normal operational batch sizes. | Endpoint runtime measurement |
| VEC-QR-07 | The subsystem MUST avoid hard failures in Search/RAG consumers when a specific model table is unavailable and fallback conditions are met upstream. | Integration tests with degraded model availability |
| VEC-QR-08 | Extension points for adding future vector strategies SHOULD remain stable and discoverable through interface-driven contracts. | Interface contract review |
| VEC-QR-09 | Batch deletion SHOULD remain parity-aligned across PDO and PostgREST/Supabase providers for equivalent datasets and batch sizes. | Cross-provider batch delete verification |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Route and strategy behavior validation | REST route tests + orchestration behavior checks |
| Data and contract requirements | Payload and storage-contract inspection | API payload review + DB schema checks |
| Operations/security requirements | Permission and failure-mode testing | Auth tests + connection-failure simulations |
| Quality requirements | Performance and parity verification | Profiling + provider-path comparison |

Acceptance baseline for migration phase:
- Canonical vectors package requirements reflect current vector, vocabulary, and model-association implementation behavior.
- Contracts needed by Search and RAG consumers are explicit in subsystem requirements.
- Legacy vectors/embeddings monolithic narratives are superseded by this package.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| VEC-FR-01 to VEC-FR-03, VEC-FR-12 | includes/vectors/class-gg-data-vector-generator.php, includes/vectors/interface-gg-data-vector-strategy.php | Strategy orchestration and model routing behavior |
| VEC-FR-04 to VEC-FR-06, VEC-FR-11 | includes/vectors/class-gg-data-vocabulary-manager.php, includes/api/class-gg-data-rest-vocabulary-controller.php | Vocabulary lifecycle and connection-path behavior |
| VEC-FR-07 to VEC-FR-10, VEC-FR-14, VEC-FR-16 | includes/api/class-gg-data-rest-vector-queue-controller.php, includes/class-gg-data-tfidf-300-embeddings.php | Queue/generation/status/delete behavior and row-per-embedding model |
| VEC-FR-15 | includes/vectors/class-gg-data-hashingtf-embeddings.php, includes/vectors/strategies/class-gg-data-hashingtf-strategy.php | Stateless internal model — no vocabulary preparation required |
| VEC-FR-09, VEC-DR-06 | includes/api/class-gg-data-rest-connection-models-controller.php, includes/class-gg-data-connection-model-manager.php | Connection-model management and response masking |
| VEC-DR-01 to VEC-DR-11 | includes/vectors/interface-gg-data-vector-strategy.php, REST controllers, model registry and embeddings contracts | Data and payload obligations |
| VEC-OR-01 to VEC-OR-09 | REST controllers, vector generator, vocabulary manager | Permissions, operations, and lifecycle constraints |
| VEC-QR-01 to VEC-QR-09 | docs/vectors/architecture.md, implementation files listed in References | Quality, parity, observability, and extension expectations |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Sync-clean-chunk contracts are treated as external subsystem dependencies consumed by vectors.
- Search and RAG vector-consumption behavior is external; this SRS focuses on production of vector artifacts and management controls.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| VEC-TBD-01 | 3.4 | Batch-throughput SLO thresholds are not yet codified as release-level limits. | Product and engineering | TBD |
| VEC-TBD-02 | 3.2 | Standardized request/response fixtures for all vector routes should be consolidated in developer documentation examples. | Engineering | TBD |
| VEC-TBD-03 | 3.3 | Provider-path parity evidence for large datasets should be added to recurring benchmark checks. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Create a joint vectors package for vectors and embeddings documentation | Keeps one canonical contract boundary for Search and RAG dependencies | Product and engineering direction |
| 2026-04-04 | Use SRS-first migration flow for vectors package | Aligns with existing rag/providers/rest-api/search documentation workflow | Documentation migration decision |
