# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Sync Orchestration Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data sync subsystem must do to orchestrate WordPress content synchronization to PostgreSQL, supporting real-time lifecycle hooks, batch processors, content preparation, and multisite deployments.

### 1.2 System Scope

The subsystem includes real-time lifecycle sync orchestration, batch processors for posts/taxonomies/metadata, content cleaning and chunking, sync metadata tracking, validation, REST management endpoints, and strict provider-contract execution routing.

Software identifier: gregius-data-sync

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The sync subsystem consumes WordPress content changes (post/meta/term/status) and routes them to PostgreSQL via direct PDO or PostgREST providers, automatically cleaning and chunking content for downstream search/vectors/RAG workflows.

#### 1.3.2 System Functions Summary

- Intercept WordPress post/meta/term lifecycle actions and sync changes non-blockingly to PostgreSQL.
- Support batch processors for pagination-based bulk synchronization across post types and taxonomies.
- Automatically prepare content (cleaning, hashing, chunking) during real-time and batch sync.
- Track sync metadata (WordPress vs PostgreSQL counts, drift, last-sync timestamps) for fast UI validation.
- Expose REST endpoints for configuration, batch operations, and validation.
- Route execution to provider implementations using a deterministic bulk-capable contract across connection types.
- Keep PostgreSQL mirror table naming canonical across providers and sites; multisite affects WordPress-side configuration only.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | Dashboard sync controls and validation endpoints |
| Plugin Developer or Integrator | Technical | Real-time hooks, batch processor APIs, filter hooks |
| Operations | Technical/Operational | Status endpoints, batch progress, validation reports |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Real-time sync | Asynchronous sync triggered by WordPress lifecycle actions (save_post, transition_post_status, etc.); non-blocking (errors do not fail WordPress operations) |
| Batch sync | Synchronous bulk sync operation with pagination and offset-based loops; user-initiated via REST API |
| Content cleaning | Automatic HTML stripping, hash-based change detection, and preparation for search/vectors workflows |
| Chunking | Content segmentation into fixed-size or semantic chunks per connection-specific strategy |
| Sync metadata | Aggregate tracking table (wp_gg_sync_metadata) recording WordPress ↔ PostgreSQL record counts and last-sync timestamps |
| Provider routing | Runtime dispatch to provider implementations through a strict bulk-capable contract |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| REST | Representational State Transfer |
| PDO | PHP Data Objects |
| RLS | Row Level Security |

## 2. References

- docs/sync/architecture.md
- includes/hooks/class-gg-data-lifecycle-hooks.php
- includes/batch/class-gg-data-post-sync.php
- includes/batch/class-gg-data-taxonomy-sync.php
- includes/batch/class-gg-data-postmeta-sync.php
- includes/class-gg-data-sync-service.php
- includes/class-gg-data-sync-metadata-manager.php
- includes/class-gg-data-sync-validator.php
- includes/class-gg-data-db.php
- includes/providers/interface-gg-db-provider.php
- includes/providers/class-gg-postgresql-provider.php
- includes/providers/class-gg-postgrest-provider.php
- includes/api/class-gg-data-rest-sync-controller.php
- includes/api/class-gg-data-rest-sync-validator-controller.php
- includes/class-gg-data-content-cleaner.php
- includes/class-gg-data-chunker.php

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| SYNC-FR-01 | The software MUST intercept WordPress post/meta/term lifecycle actions and dispatch sync changes to PostgreSQL asynchronously. | Must |
| SYNC-FR-02 | The software MUST guarantee that real-time sync errors do NOT block or fail WordPress save operations. | Must |
| SYNC-FR-03 | The software MUST support batch synchronization for posts, taxonomies, and postmeta with offset-based pagination. | Must |
| SYNC-FR-04 | The software MUST automatically clean (strip HTML) and chunk content during both real-time and batch sync. | Must |
| SYNC-FR-05 | The software MUST enforce required bulk provider methods for batch operations across all providers and fail fast on contract violations. | Must |
| SYNC-FR-06 | The software MUST track sync metadata (WordPress count, PostgreSQL count, last-sync timestamp) per entity type and connection. | Must |
| SYNC-FR-07 | The software MUST provide validation endpoints for fast metadata-based drift detection (auto-loaded by the dashboard UI) and full table-scan drift detection (operator/internal endpoint). | Must |
| SYNC-FR-08 | The software MUST support per-site configuration (enabled post types, statuses, real-time toggle) with WordPress-side multisite isolation. | Must |
| SYNC-FR-09 | The software MUST expose REST endpoints for batch operations, configuration, content cleaning, and status reporting. | Must |
| SYNC-FR-10 | The software SHOULD support incremental/resume-friendly batch operations using offset pagination. | Should |
| SYNC-FR-11 | The software SHOULD allow custom chunking strategies per connection and post type without plugin code changes. | Should |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| SYNC-DR-01 | Real-time sync MUST preserve WordPress post/meta/term data exactly as stored in source database. | Must |
| SYNC-DR-02 | Batch sync responses MUST include processed count, failed count, skipped count, and has-more pagination flag. | Must |
| SYNC-DR-03 | Sync metadata contracts MUST track WordPress and PostgreSQL counts with drift and last-sync timestamp. | Must |
| SYNC-DR-04 | Content cleaning contracts MUST include original content, cleaned content, content hash, and cleaning version. | Must |
| SYNC-DR-05 | Chunk contracts MUST include post_id, chunk_index, content, and result of chunking strategy. | Must |
| SYNC-DR-06 | Configuration payload contracts MUST expose enabled post types, statuses, real-time toggle, and metadata toggle per connection. | Must |
| SYNC-DR-07 | Validation payloads MUST include WordPress total, PostgreSQL total, drift, orphan detection, and missing entity counts. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| SYNC-OR-01 | Real-time sync MUST catch and log all errors without raising exceptions to WordPress operations. | Must |
| SYNC-OR-02 | Batch sync operations MUST enforce administrative authorization checks via REST middleware. | Must |
| SYNC-OR-03 | The subsystem MUST respect per-site WordPress configuration in multisite installations without requiring code changes. | Must |
| SYNC-OR-04 | Content cleaning MUST detect changes via content hash to avoid redundant processing. | Must |
| SYNC-OR-05 | The subsystem MUST support resumable batch operations via offset + batch_size parameters. | Must |
| SYNC-OR-06 | Validation operations MUST provide actionable error reporting for orphaned and missing records. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| SYNC-QR-01 | Real-time sync MUST complete within 100ms latency to avoid WordPress UI lag perception. | Endpoint timing measurement |
| SYNC-QR-02 | Batch operations SHOULD scale to 100K+ posts without exceeding memory or timeout limits. | Load testing with pagination handling |
| SYNC-QR-03 | Provider-path behavior SHOULD remain transparent to callers with equivalent bulk-result contracts across providers. | Route behavior and result payload tests |
| SYNC-QR-04 | Content cleaning and chunking SHOULD be deterministic for identical input across sync runs. | Hash validation and chunk boundary tests |
| SYNC-QR-05 | Metadata-based validation (fast check) SHOULD execute in <500ms for operational dashboards. | Metadata query timing |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Hook behavior and batch operation validation | Integration tests + REST route tests |
| Data and contract requirements | Payload schema inspection and data flow tests | API contract validation + database checks |
| Operations/security requirements | Permission and error handling tests | Auth checks + exception handling verification |
| Quality requirements | Performance and parity testing | Benchmarks + provider-path behavior tests |

Acceptance baseline for migration phase:
- Canonical sync package requirements reflect current real-time and batch sync implementation behavior.
- Provider-contract parity is explicit and documented.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| SYNC-FR-01, SYNC-FR-02 | includes/hooks/class-gg-data-lifecycle-hooks.php | Real-time orchestration and non-blocking error handling |
| SYNC-FR-03 to SYNC-FR-05 | includes/batch/class-gg-data-post-sync.php, includes/batch/class-gg-data-taxonomy-sync.php | Batch processors and provider routing |
| SYNC-FR-04, SYNC-DR-04, SYNC-DR-05 | includes/class-gg-data-content-cleaner.php, includes/class-gg-data-chunker.php | Content cleaning and chunking |
| SYNC-FR-06, SYNC-DR-03 | includes/class-gg-data-sync-metadata-manager.php | Metadata tracking and drift detection |
| SYNC-FR-07 | includes/class-gg-data-sync-validator.php, includes/api/class-gg-data-rest-sync-validator-controller.php | Validation logic and endpoints |
| SYNC-FR-08, SYNC-OR-03 | includes/class-gg-data-settings-manager.php (multisite support) | Per-site configuration isolation |
| SYNC-FR-09 | includes/api/class-gg-data-rest-sync-controller.php | REST endpoint registration and request validation |
| SYNC-OR-01, SYNC-OR-04 | includes/hooks/class-gg-data-lifecycle-hooks.php, includes/class-gg-data-content-cleaner.php | Error handling and change detection |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Sync operations depend on valid connection configuration and established provider instances.
- Real-time sync runs asynchronously; errors must not block WordPress operations.
- Content cleaning and chunking are mandatory for search/vector workflows; not optional operations.
- Multisite deployments use WordPress-side configuration isolation; PostgreSQL mirror tables use canonical names and do not vary by site prefix.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| SYNC-TBD-01 | 3.4 | Batch operation timeout SLOs and max size limits not yet codified as release-level guarantees. | Product and engineering | TBD |
| SYNC-TBD-02 | 3.2 | Content hash collision detection strategy should be formalized (currently MD5). | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Keep real-time and batch in unified sync package | Both share metadata, cleaning, chunking; provider routing is internal implementation detail | Product and engineering direction |
| 2026-04-04 | Use SRS-first migration flow for sync package | Aligns with existing search/vectors/schema documentation workflow | Documentation migration decision |
