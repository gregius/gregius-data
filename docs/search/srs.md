# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Search Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data Search subsystem must do to provide WordPress-integrated retrieval behavior across lexical, typo-tolerant, and semantic matching while preserving operational safety and fallback behavior.

### 1.2 System Scope

The subsystem includes WordPress search interception, PostgreSQL or PostgREST execution paths, orchestrator-capable SQL search functions, search health fallback handling, and search-related REST management endpoints.

Software identifier: gregius-data-search

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The Search subsystem bridges WordPress query execution and database retrieval services, returning ranked post IDs to WordPress query pipelines and surfacing operational controls through REST endpoints.

#### 1.3.2 System Functions Summary

- Intercept WordPress main search queries when enhanced search is enabled.
- Resolve synced and unsynced post-type coverage with PostgreSQL-first and MySQL fallback composition.
- Support controlled retrieval modes for merge behavior (`hybrid_default` and `postgresql_only`).
- Execute database retrieval through SQL functions supporting lexical, trigram, and vector strategies.
- Support both PDO PostgreSQL and PostgREST provider execution paths.
- Maintain health telemetry and fallback behavior for degraded search states.
- Expose REST routes for health, schema, language, and typo-tolerance management.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Visitor | Non-technical | Frontend WordPress search experience |
| Site Administrator | Technical/Operational | Search settings and REST management endpoints |
| Plugin Developer or Integrator | Technical | Filters, hooks, SQL orchestration, provider integration |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Enhanced search | Plugin search mode that intercepts WordPress search and routes through PostgreSQL-capable retrieval |
| Orchestrator mode | SQL execution mode using `search_native_orchestrate` or `search_rag_orchestrate` instead of legacy direct function behavior |
| Typo tolerance | Trigram-based similarity matching using `pg_trgm` support |
| Vector search | Semantic candidate retrieval using model-specific embedding tables and cosine-distance ranking |
| Fallback manager | Runtime component that tracks search health and degrades to MySQL-safe behavior when PostgreSQL execution fails |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| FTS | Full-Text Search |
| RRF | Reciprocal Rank Fusion |
| PDO | PHP Data Objects |
| API | Application Programming Interface |

## 2. References

- docs/search/architecture.md
- includes/search/class-gg-data-search-integration.php
- includes/search/class-gg-data-search-schema.php
- includes/search/class-gg-data-search-fallback.php
- includes/search/class-gg-data-search-language.php
- includes/search/search-settings.php
- includes/search/sql/create-search-function.sql
- includes/api/class-gg-data-rest-search-controller.php
- includes/providers/class-gg-postgresql-provider.php
- includes/rag/class-gg-data-rag-service.php

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| SEARCH-FR-01 | The software MUST support enhanced search as an opt-in site-wide capability controlled by settings. | Must |
| SEARCH-FR-02 | When enhanced search is enabled, the software MUST intercept WordPress main search queries and route retrieval through subsystem search orchestration. | Must |
| SEARCH-FR-03 | The software MUST determine retrieval coverage by combining synced post types for PostgreSQL search with MySQL coverage for unsynced or broader public post types. | Must |
| SEARCH-FR-04 | The software MUST execute PostgreSQL-backed retrieval through SQL function calls with configurable search language, trigram threshold, and vector-search capability flags. | Must |
| SEARCH-FR-05 | The software MUST support both PDO PostgreSQL and PostgREST provider execution paths for search requests. | Must |
| SEARCH-FR-06 | The software MUST support orchestrator-mode SQL execution and fallback to legacy SQL entrypoints when orchestrators are disabled. | Must |
| SEARCH-FR-07 | The software MUST maintain relevance ordering across WordPress result rendering when PostgreSQL results are returned. | Must |
| SEARCH-FR-08 | The software MUST expose a search schema creation flow that provisions the required SQL search functions. | Must |
| SEARCH-FR-09 | The software MUST expose health monitoring and health-reset controls for search fallback behavior. | Must |
| SEARCH-FR-10 | The software MUST expose language-status and language-update controls for search-language alignment with WordPress locale behavior. | Must |
| SEARCH-FR-11 | The software MUST expose typo-tolerance capability and settings controls, including extension availability and threshold tuning. | Must |
| SEARCH-FR-12 | The software MUST emit search completion events that include post IDs, counts, and context metadata for downstream observers. | Must |
| SEARCH-FR-13 | The software MUST support deterministic JSONB metadata filtering in orchestrator search execution so lexical, trigram, and vector branches evaluate the same metadata filter contract. | Must |
| SEARCH-FR-14 | The software MUST support exactly two user-facing native retrieval modes: `hybrid_default` (PostgreSQL+MySQL merge) and `postgresql_only` (PostgreSQL-native only). | Must |
| SEARCH-FR-15 | The software MUST keep MySQL fallback on PostgreSQL execution failure as an internal safety behavior regardless of selected retrieval mode. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| SEARCH-DR-01 | Search execution responses consumed by subsystem internals MUST include post identifiers and relevance information sufficient to preserve rank ordering. | Must |
| SEARCH-DR-02 | SQL search function contracts MUST return match-type metadata that identifies retrieval source behavior. | Must |
| SEARCH-DR-03 | REST search management endpoints MUST return structured success/error payloads with explicit status messaging. | Must |
| SEARCH-DR-04 | Search health telemetry MUST include total-search counts, success/failure counters, and last error/latency fields. | Must |
| SEARCH-DR-05 | Typo-tolerance settings contracts MUST validate threshold values and reject out-of-range updates with client errors. | Must |
| SEARCH-DR-06 | Language-status contracts MUST return stored and current language values plus mismatch indicators. | Must |
| SEARCH-DR-07 | Search schema status contracts MUST return function readiness and connection state indicators. | Must |
| SEARCH-DR-08 | Provider-path contracts for PostgREST execution MUST pass equivalent search parameters to RPC calls as PDO path execution. | Must |
| SEARCH-DR-09 | The metadata-filter contract MUST treat an empty filter as a JSON object (`{}`), not an array (`[]`), so JSONB containment bypass logic remains correct. | Must |
| SEARCH-DR-10 | Search-orchestrator contracts MUST evaluate metadata filters against per-post `metadata_manifest` JSONB and preserve branch parity across FTS, trigram, and vector candidate generation. | Must |
| SEARCH-DR-11 | Search completion event metadata MUST include contextual source attribution and selected retrieval mode for downstream logging consumers. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| SEARCH-OR-01 | Search management REST routes MUST require administrative authorization checks before state-changing operations execute. | Must |
| SEARCH-OR-02 | The subsystem MUST preserve site availability when PostgreSQL search fails by executing fallback-safe behavior and avoiding fatal query interruption. | Must |
| SEARCH-OR-03 | Health tracking MUST capture consecutive failure conditions and support alert-oriented operational status transitions. | Must |
| SEARCH-OR-04 | The subsystem SHOULD support connection-specific search execution for multi-connection deployments. | Should |
| SEARCH-OR-05 | The subsystem MUST allow runtime tuning of trigram similarity thresholds through extension hooks. | Must |
| SEARCH-OR-06 | The subsystem MUST disable vector-search execution when required capabilities (extension/table/data availability) are not present. | Must |
| SEARCH-OR-07 | The subsystem MUST avoid executing typo-tolerance for all-short-word queries where trigram behavior is intentionally suppressed for performance and precision. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| SEARCH-QR-01 | The subsystem SHOULD preserve fast-path lexical performance when fallback tiers are unnecessary. | Runtime measurement + query profiling |
| SEARCH-QR-02 | The subsystem MUST preserve deterministic result ordering from retrieval output through WordPress query rendering. | Result-order validation |
| SEARCH-QR-03 | The subsystem MUST avoid duplicate post IDs when composing PostgreSQL and MySQL result sets. | Contract inspection + integration tests |
| SEARCH-QR-04 | The subsystem SHOULD provide retrieval observability sufficient to diagnose source behavior and fallback status. | Log and status inspection |
| SEARCH-QR-05 | Provider-path behavior (PDO vs PostgREST) SHOULD remain parity-aligned for equivalent search inputs. | Cross-provider parity tests |
| SEARCH-QR-06 | The subsystem MUST avoid hard failure when vector or trigram capabilities are unavailable and continue with supported retrieval behavior. | Capability-variation testing |
| SEARCH-QR-07 | SQL orchestration contracts SHOULD remain backward compatible with legacy wrapper entrypoints during migration windows. | SQL function compatibility checks |
| SEARCH-QR-08 | Search-management endpoints SHOULD return operator-actionable messages for troubleshooting. | Response contract review |
| SEARCH-QR-09 | The subsystem MUST preserve empty-filter serialization parity across provider paths so equivalent no-filter requests do not regress to zero-result behavior. | Cross-provider parity tests |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Search execution and endpoint behavior inspection | Runtime query checks + REST route tests |
| Data and contract requirements | Payload and SQL contract inspection | API payload review + function output review |
| Operations/security requirements | Permission and fallback behavior checks | Authorization tests + failure-mode tests |
| Quality requirements | Performance, ordering, and parity validation | Profiling + integration comparison |

Acceptance baseline for migration phase:
- Search package requirements reflect current orchestrator-capable implementation.
- Search operational controls (health/schema/language/typo-tolerance) are represented in canonical subsystem documentation.
- Provider-path parity assumptions and fallback contracts are explicit.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| SEARCH-FR-01 to SEARCH-FR-07, SEARCH-FR-13 to SEARCH-FR-15 | includes/search/class-gg-data-search-integration.php, includes/search/sql/create-search-function.sql, includes/sql/postgrest-schema.sql | WordPress interception, SQL execution, retrieval-mode behavior, ranking behavior, and metadata-filter parity |
| SEARCH-FR-08 to SEARCH-FR-11 | includes/search/class-gg-data-search-schema.php, includes/api/class-gg-data-rest-search-controller.php, includes/search/class-gg-data-search-language.php | Schema provisioning and search-management endpoint behavior |
| SEARCH-FR-12, SEARCH-DR-01 to SEARCH-DR-11 | includes/search/class-gg-data-search-integration.php, includes/search/class-gg-data-search-fallback.php, includes/providers/class-gg-postgresql-provider.php, includes/class-gg-data-schema-manager.php, includes/class-gg-data-content-cleaner.php | Event emission, response contracts, contextual source metadata, health telemetry, provider path parity, and metadata-manifest contracts |
| SEARCH-OR-01 to SEARCH-OR-07 | includes/api/class-gg-data-rest-search-controller.php, includes/search/class-gg-data-search-fallback.php, includes/search/class-gg-data-search-integration.php | Permissions, fallback operations, and runtime controls |
| SEARCH-QR-01 to SEARCH-QR-09 | docs/search/architecture.md, includes/search/sql/create-search-function.sql, includes/sql/postgrest-schema.sql, implementation files listed in References | Performance, compatibility, observability, and parity expectations |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Search behavior is implementation-led and reverse-mapped to requirement statements from current runtime code.
- PostgreSQL extension availability (`pg_trgm`, `vector`) is deployment-dependent and treated as capability-gated, not universally guaranteed.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| SEARCH-TBD-01 | 3.4 | Query-performance SLO targets are described narratively but not codified as formal release thresholds in this package. | Product and engineering | TBD |
| SEARCH-TBD-02 | 3.2 | Endpoint payload examples for all search management routes should be consolidated in canonical developer docs with tested request/response fixtures. | Engineering | TBD |
| SEARCH-TBD-03 | 3.1, 3.4 | Cross-provider parity evidence (PDO vs PostgREST) should be continuously tracked in benchmark-style regression checks. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Start search subsystem split package with SRS-first artifact | Aligns search documentation workflow with existing rag/providers/rest-api package structure | Product and engineering direction |
| 2026-04-04 | Establish split package docs as canonical search documentation set | Consolidates ongoing maintenance into subsystem-specific SRS, architecture, and developer documentation artifacts | Documentation migration decision |
