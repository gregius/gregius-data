# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | REST API Subsystem |
| Version | 1.0 |
| Date | 2026-03-30 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data REST API subsystem must do to expose administrative and runtime operations for settings, connections, schema, sync, vectors, search, RAG, models, prompts, logs, and interactions.

### 1.2 System Scope

The subsystem includes route registration, request validation, permission enforcement, endpoint execution, and response contracts under the `gg-data/v1` namespace.

Software identifier: gregius-data-rest-api

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The REST API is the primary integration boundary between plugin internals and external callers such as WordPress admin dashboards, frontend interactions, and automation clients.

#### 1.3.2 System Functions Summary

- Register all REST routes under one versioned namespace.
- Enforce endpoint-specific permission policies.
- Validate and sanitize endpoint input.
- Expose CRUD and operational endpoints for core plugin domains.
- Return consistent success and error envelopes.
- Support extension through filter and action hooks where exposed.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | Dashboard and authenticated API clients |
| Developer or Integrator | Technical | Direct REST integrations and custom clients |
| Logged-in End User | Non-technical/Technical | Interaction endpoints and gated frontend features |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Controller | Class that registers and handles one endpoint group |
| Endpoint group | Related routes under a shared rest base |
| Permission callback | Authorization check for an endpoint before execution |
| Route contract | HTTP method, path pattern, request args, and response shape |
| Admin endpoint | Endpoint requiring `manage_options` capability or equivalent admin check |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| REST | Representational State Transfer |
| CPT | Custom Post Type |
| SSE | Server-Sent Events |
| API | Application Programming Interface |

## 2. References

- docs/rest-api/architecture.md
- docs/rag/architecture.md
- docs/rag/srs.md
- includes/api/class-gg-data-rest-api.php
- includes/api/class-gg-data-rest-settings-controller.php
- includes/api/class-gg-data-rest-connections-controller.php
- includes/api/class-gg-data-rest-connection-health-controller.php
- includes/api/class-gg-data-rest-connection-models-controller.php
- includes/api/class-gg-data-rest-schema-controller.php
- includes/api/class-gg-data-rest-sync-controller.php
- includes/api/class-gg-data-rest-sync-validator-controller.php
- includes/api/class-gg-data-rest-search-controller.php
- includes/api/class-gg-data-rest-vector-queue-controller.php
- includes/api/class-gg-data-rest-vocabulary-controller.php
- includes/api/class-gg-data-rest-retry-queue-controller.php
- includes/api/class-gg-data-rest-rag-controller.php
- includes/api/class-gg-data-rest-prompts-controller.php
- includes/api/class-gg-data-rest-models-controller.php
- includes/api/class-gg-data-rest-logs-controller.php
- includes/api/class-gg-data-rest-interactions-controller.php
- includes/api/class-gg-data-sse-handler.php

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REST-FR-01 | The software MUST register all Gregius REST routes under namespace `gg-data/v1`. | Must |
| REST-FR-02 | The software MUST expose endpoint groups for settings, connections, connection health, connection models, schema, sync, sync validation, search, vectors, vocabulary, retry queue, RAG, prompts, models, logs, and interactions. | Must |
| REST-FR-03 | The software MUST provide route contracts for listing, retrieval, creation, update, and deletion where supported by each endpoint group. | Must |
| REST-FR-04 | The software MUST enforce a permission callback for each registered route before endpoint execution. | Must |
| REST-FR-05 | The software MUST support admin-gated endpoint groups that require privileged access for configuration and operational actions. | Must |
| REST-FR-06 | The software MUST support RAG endpoint permissions through a dedicated filter-based callback that can be tightened at runtime. | Must |
| REST-FR-07 | The software MUST support interaction endpoint access rules where authenticated users can access their own interaction records (read/update/delete), direct `POST /interactions` create is restricted to administrators, and administrators can access all records. | Must |
| REST-FR-08 | The software MUST validate and sanitize route arguments according to each route definition and reject invalid requests with structured errors. | Must |
| REST-FR-09 | The software MUST expose sync operation endpoints for status, configuration, taxonomy sync, post-type sync, and orphan cleanup workflows. | Must |
| REST-FR-10 | The software MUST expose vector lifecycle endpoints for queue inspection, generation, batch generation, status, cleanup, and post-level vector views. | Must |
| REST-FR-11 | The software MUST expose search operational endpoints for health checks, status, language settings, schema creation, and typo-tolerance management. | Must |
| REST-FR-12 | The software MUST expose schema-management endpoints for schema status, creation, upgrade, verification, and SQL retrieval. | Must |
| REST-FR-13 | The software MUST expose model-management endpoints for model CRUD, provider listing, model testing, and usage reset. | Must |
| REST-FR-14 | The software MUST expose prompt-management endpoints for prompt CRUD and activation workflows. | Must |
| REST-FR-15 | The software MUST expose logs endpoints for listing, stats, export, purge, and settings. | Must |
| REST-FR-16 | The software MUST expose RAG endpoints for chat generation, action execution, and action discovery. | Must |
| REST-FR-17 | The software SHOULD preserve versioned route compatibility within `gg-data/v1` and avoid breaking route semantics without explicit migration handling. | Should |
| REST-FR-18 | The software MUST preserve parity for manifest-aware and deterministic-tool RAG request fields (`manifest`, `forced_tool`) across REST and SSE entry paths where RAG chat is supported. | Must |
| REST-FR-19 | The software MUST expose `POST /vectors/batch-delete` for timeout-safe iterative vector deletion and support both PDO and PostgREST/Supabase provider paths. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| REST-DR-01 | The software MUST return structured JSON responses for successful operations. | Must |
| REST-DR-02 | Endpoints that mask sensitive connection configuration MUST avoid exposing plaintext credentials in response payloads. | Must |
| REST-DR-03 | The software MUST return structured `WP_Error` responses with explicit HTTP status codes for invalid input, authorization failures, missing resources, and execution failures. | Must |
| REST-DR-04 | Route definitions MUST declare required and optional parameters for endpoint operations that accept request payloads or query arguments. | Must |
| REST-DR-05 | Routes handling typed values MUST sanitize or validate them before use in underlying services. | Must |
| REST-DR-06 | RAG chat responses MUST return a success wrapper and a data payload that includes answer data and metadata when successful. | Must |
| REST-DR-07 | Action-discovery responses MUST return only user-triggerable actions with route-consumable metadata. | Must |
| REST-DR-08 | Model and settings endpoints MUST support key-based and category-based addressing where declared in route contracts. | Must |
| REST-DR-09 | Search and health endpoints MUST return enough status information for operational diagnosis and dashboard consumption. | Must |
| REST-DR-10 | Sync and vector operation endpoints MUST return status and progress structures suitable for operational monitoring and retry workflows. | Must |
| REST-DR-11 | Logs purge endpoints MUST operate on logs data only and MUST NOT imply deletion of `gg_interaction` records. | Must |
| REST-DR-12 | RAG request contracts MUST accept optional structured metadata fields (`metadata_filter`, `metadata_manifest`) and preserve deterministic no-filter behavior when those fields are omitted. | Must |
| REST-DR-13 | RAG request contracts MUST accept optional `manifest` (object) and `forced_tool` (string) fields and forward sanitized values to RAG orchestration without breaking existing request semantics. | Must |
| REST-DR-14 | Vector batch delete responses MUST expose progress metadata suitable for polling clients: deleted, total_deleted, has_more, next_offset, duration_ms, and errors. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| REST-OR-01 | The software MUST require `manage_options` or an equivalent privileged check for administrative endpoint groups, unless explicitly documented otherwise. | Must |
| REST-OR-02 | The software MUST support endpoint-level permission variation where subsystem design requires different access behavior (for example, RAG filter-based policy and interaction ownership/direct-create governance policy). | Must |
| REST-OR-03 | The software MUST enforce authentication for interaction endpoints and deny anonymous access, and in multisite MUST allow explicit cross-site interaction context only for super admins with valid site IDs. | Must |
| REST-OR-04 | The software MUST support runtime tightening of RAG access level and required capability via extension hooks/settings. | Must |
| REST-OR-05 | The software SHOULD provide endpoint-level observability through status and stats routes for operational domains (connections, search, logs, vectors, sync validation). | Should |
| REST-OR-06 | The REST controller layer MUST NOT claim built-in cross-origin policy configuration unless response-header behavior is actually implemented in code paths handling REST responses. | Must |
| REST-OR-07 | The REST controller layer MUST document route-scoped built-in throttling where implemented (currently RAG `POST /rag/chat` and `POST /rag/action`) and MUST NOT present it as global middleware for all endpoints. | Must |
| REST-OR-08 | Destructive batch-delete endpoints MUST avoid offset drift by using backend-owned selection semantics and MUST terminate safely when zero rows are deleted while rows remain. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| REST-QR-01 | The API subsystem MUST preserve consistent route namespace and base patterns across controllers. | Route inventory inspection |
| REST-QR-02 | The API subsystem MUST preserve deterministic permission behavior per route and avoid hidden bypass paths. | Permission callback inspection |
| REST-QR-03 | Endpoint argument handling MUST prevent unsafe direct use of unsanitized request inputs. | Route args and handler inspection |
| REST-QR-04 | The API subsystem SHOULD maintain stable response semantics for dashboard and integration clients within a major API version. | Client contract review |
| REST-QR-05 | The API subsystem SHOULD provide operationally useful health/status endpoints for key subsystems. | Endpoint behavior verification |
| REST-QR-06 | Error responses SHOULD remain actionable enough for troubleshooting by developers and operators. | Error response inspection |
| REST-QR-07 | The API subsystem MUST avoid stale capability claims in architecture and developer docs that are not present in runtime code. | Documentation parity review |
| REST-QR-08 | Route contracts for high-impact operational endpoints SHOULD remain discoverable and grouped by endpoint domain in developer docs. | Documentation coverage check |
| REST-QR-09 | The API subsystem SHOULD preserve provider-parity behavior for vector batch deletion across PDO and PostgREST/Supabase transports. | Cross-provider endpoint verification |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Controller route inspection and endpoint execution checks | Route registration review + endpoint tests |
| Data and contract requirements | Request/response schema and error inspection | Controller args + response payload review |
| Operations/security requirements | Permission and access behavior checks | Permission callback review + runtime checks |
| Quality requirements | Parity and consistency checks | Docs-vs-code comparison |

Acceptance baseline for migration phase:
- All `gg-data/v1` endpoint groups are represented in the new REST API package.
- No unverified claim of built-in CORS or endpoint-wide global rate limiting remains in canonical REST package docs.
- RAG route throttling behavior and extension hooks are documented as route-scoped runtime behavior.
- RAG and interaction permission exceptions are explicitly documented as intentional architectural behavior.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| REST-FR-01 to REST-FR-03 | includes/api/class-gg-data-rest-api.php, includes/api/class-gg-data-rest-*-controller.php | Namespace and endpoint-group coverage |
| REST-FR-04 to REST-FR-08 | includes/api/class-gg-data-rest-*-controller.php, includes/api/class-gg-data-rest-rag-controller.php, includes/api/class-gg-data-rest-interactions-controller.php | Permission and contract behavior |
| REST-FR-09 to REST-FR-19 | includes/api/class-gg-data-rest-sync-controller.php, includes/api/class-gg-data-rest-vector-queue-controller.php, includes/api/class-gg-data-rest-search-controller.php, includes/api/class-gg-data-rest-schema-controller.php, includes/api/class-gg-data-rest-models-controller.php, includes/api/class-gg-data-rest-prompts-controller.php, includes/api/class-gg-data-rest-logs-controller.php, includes/api/class-gg-data-rest-rag-controller.php | Domain-specific endpoint behavior |
| REST-FR-18 | includes/api/class-gg-data-rest-rag-controller.php, includes/api/class-gg-data-sse-handler.php | Manifest and deterministic-tool parity across transport surfaces |
| REST-DR-01 to REST-DR-14 | includes/api/class-gg-data-rest-connections-controller.php, includes/api/class-gg-data-rest-settings-controller.php, includes/api/class-gg-data-rest-rag-controller.php, includes/api/class-gg-data-sse-handler.php, includes/api/class-gg-data-rest-search-controller.php, includes/api/class-gg-data-rest-sync-controller.php, includes/api/class-gg-data-rest-logs-controller.php, includes/api/class-gg-data-rest-vector-queue-controller.php | Request/response/error contracts |
| REST-OR-01 to REST-OR-08 | includes/api/class-gg-data-rest-*-controller.php, includes/hooks/class-gg-data-rag-security-hooks.php, includes/api/class-gg-data-sse-handler.php, docs/rest-api/architecture.md | Access and operational constraints |
| REST-QR-01 to REST-QR-09 | docs/rest-api/architecture.md, controller implementations listed in References | Parity and quality alignment |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Controller code is treated as canonical when legacy docs and code diverge.
- Rate limiting in this subsystem is route-scoped for RAG (`POST /rag/chat`, `POST /rag/action`) and configurable via `gg_data_rag_rate_limit_anonymous`, `gg_data_rag_rate_limit_authenticated`, and `gg_data_rag_rate_limit_window_seconds`.
- SSE throttling remains hook-driven via `gg_data_rag_rate_limit`.
- CORS response-header behavior is treated as deployment-level unless explicit runtime implementation is confirmed.
- Canonical REST architecture documentation is maintained in `docs/rest-api/architecture.md`.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| REST-TBD-01 | 3.3 | Formalized global rate-limiting policy for REST endpoints outside RAG chat/action is not currently encoded in controller runtime. | Product and engineering | TBD |
| REST-TBD-02 | 3.3 | CORS policy ownership between plugin runtime and deployment stack needs explicit release policy documentation. | Engineering and ops | TBD |
| REST-TBD-03 | 3.2 | Error code catalog in historical REST documentation requires canonical consolidation in new developer docs. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-03-30 | Use SRS-first migration for REST API docs | Keeps requirements separated from implementation/reference detail and aligns with existing RAG/provider package pattern | Product and engineering direction |
| 2026-03-30 | Treat unverified rate-limiting and CORS claims as constraints, not implemented features | Prevents stale capability claims in canonical docs | Documentation parity decision |