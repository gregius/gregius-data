# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Retry Queue Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data retry queue subsystem must do to provide automatic recovery from transient database sync failures through error classification, exponential backoff scheduling, dead letter management, and background processing.

### 1.2 System Scope

The subsystem includes error classification routing, retry queue item lifecycle management, exponential backoff scheduling, dead letter queue behavior, WP-Cron background processing, REST management endpoints, and cleanup on plugin deactivation and uninstall.

Software identifier: gregius-data-retry-queue

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The retry queue subsystem sits between the sync database operations layer and PostgreSQL. When sync database operations fail at one of 8 instrumented call sites in `GG_Data_DB`, the retry queue classifies the error and either queues it for automatic retry or discards it as a permanent failure.

#### 1.3.2 System Functions Summary

- Classify sync errors as transient, permanent, or unknown using deterministic pattern matching.
- Queue transient failures with exponential backoff scheduling for automatic retry.
- Process queued items in background via WP-Cron every 60 seconds.
- Move items that exceed the maximum retry attempt count to a dead letter queue.
- Expose REST endpoints for queue status monitoring, manual dead letter retry, and bulk clear.
- Log all queueing, retry, and dead letter events with operational context.
- Clean up WP-Cron events on plugin deactivation; clean up queue data on uninstall.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Operational | React dashboard Retry Queue card and REST endpoints |
| Plugin Developer | Technical | Error handler API and queue integration points |
| Operations | Technical/Operational | REST management endpoints, logs, manual retry workflows |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Transient error | A database failure expected to be temporary and safe to retry (for example: connection timeout, deadlock) |
| Permanent error | A database failure that will not resolve with retries (for example: schema mismatch, permission denied) |
| Dead letter queue | A holding store for items that have exceeded the maximum retry attempt count, available for operator review and manual retry |
| Exponential backoff | A retry delay strategy that progressively increases wait time between attempts |
| Queue item | A serialized record of a failed database operation including context data needed for resubmission |
| WP-Cron | WordPress's built-in background task scheduling system |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| REST | Representational State Transfer |
| WP | WordPress |
| PDO | PHP Data Objects |

## 2. References

- docs/retry-queue/architecture.md
- includes/class-gg-data-error-handler.php
- includes/class-gg-data-retry-queue.php
- includes/api/class-gg-data-rest-retry-queue-controller.php
- includes/class-gg-data-db.php
- includes/class-gg-data-deactivator.php
- uninstall.php
- assets/src/scripts/dashboard/components/sync/RetryQueueCard.js

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| RQ-FR-01 | The software MUST classify sync error messages as transient, permanent, or unknown using deterministic pattern matching against known error strings. | Must |
| RQ-FR-02 | The software MUST queue transient and unknown errors for automatic retry; permanent errors MUST NOT be queued. | Must |
| RQ-FR-03 | The software MUST assign queue items an initial retry delay of 5 seconds, a second retry delay of 30 seconds, and a third retry delay of 300 seconds. | Must |
| RQ-FR-04 | The software MUST limit retry attempts to a maximum of 3 before moving items to the dead letter queue. | Must |
| RQ-FR-05 | The software MUST process the retry queue in background via WP-Cron on a 60-second interval. | Must |
| RQ-FR-06 | The software MUST skip queue items whose scheduled retry time has not yet elapsed during each processing cycle. | Must |
| RQ-FR-07 | The software MUST move items that fail at maximum attempt count to the dead letter queue with a moved_to_dead_letter timestamp. | Must |
| RQ-FR-08 | The software MUST limit the dead letter queue to 100 items using a rolling window; oldest items MUST be discarded when the limit is exceeded. | Must |
| RQ-FR-09 | The software MUST expose a REST endpoint to retrieve current queue status including pending and dead letter items. | Must |
| RQ-FR-10 | The software MUST expose a REST endpoint to manually move a dead letter item back to the retry queue by index. | Must |
| RQ-FR-11 | The software MUST expose a REST endpoint to clear the entire dead letter queue. | Must |
| RQ-FR-12 | The software MUST support retry dispatch for all four operation types: sync_post, sync_meta, delete_post, and delete_meta. | Must |
| RQ-FR-13 | The software MUST treat a deleted WordPress post as a successful resolution when retried (operation no longer needed). | Must |
| RQ-FR-14 | The software MUST unschedule the WP-Cron retry event on plugin deactivation. | Must |
| RQ-FR-15 | The software MUST delete queue data from wp_options on plugin uninstall. | Must |
| RQ-FR-16 | The software MUST log all queueing, successful retry, max-retry, and manual operation events with operational context. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| RQ-DR-01 | Queue items MUST carry: operation_type, entity_id, error_message, error_type, operation_data, attempt_count, first_attempt, next_retry, and last_error. | Must |
| RQ-DR-02 | Dead letter items MUST carry all queue item fields plus a moved_to_dead_letter timestamp. | Must |
| RQ-DR-03 | Queue items MUST be stored under the wp_options key gg_data_retry_queue as a serialized PHP array. | Must |
| RQ-DR-04 | Dead letter items MUST be stored under the wp_options key gg_data_dead_letter_queue as a serialized PHP array. | Must |
| RQ-DR-05 | Error classification results MUST return type (transient, permanent, or unknown), retry_safe (bool), and message string. | Must |
| RQ-DR-06 | REST queue status responses MUST include pending_retries count, failed_permanently count, items array, and dead_letter_items array. | Must |
| RQ-DR-07 | REST responses MUST use structured success/error payloads with operator-actionable messages. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| RQ-OR-01 | All retry queue REST endpoints MUST enforce the manage_options capability. | Must |
| RQ-OR-02 | Queue processing MUST NOT block the WordPress request cycle; all processing MUST occur via WP-Cron callback. | Must |
| RQ-OR-03 | The subsystem MUST preserve queue data in wp_options on plugin deactivation to allow recovery on reactivation. | Must |
| RQ-OR-04 | The subsystem MUST clean up all retry queue wp_options data and WP-Cron events on plugin uninstall. | Must |
| RQ-OR-05 | Sync metadata MUST NOT be updated as synced until a retry operation succeeds. | Must |
| RQ-OR-06 | The subsystem MUST log all retry queue activity with enough context for operator diagnosis. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| RQ-QR-01 | Error classification MUST be deterministic: identical error strings MUST always produce identical classification results. | Unit tests for all named patterns |
| RQ-QR-02 | The pending queue has no hard size limit; it grows dynamically with failure rate and is drained as retries succeed or age out. | Queue lifecycle integration tests |
| RQ-QR-03 | The dead letter queue rolling window MUST cap at 100 items without manual intervention. | Overflow boundary tests |
| RQ-QR-04 | WP-Cron queue processing MUST complete within acceptable time when queue is empty or contains a small number of ready items. | Cron execution profiling |
| RQ-QR-05 | Manual retry and clear operations via REST MUST return a response within normal REST API latency expectations. | Endpoint response time checks |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Lifecycle and dispatch behavior validation | Queue lifecycle tests + retry dispatch checks per op type |
| Data and contract requirements | Queue item structure and REST payload inspection | Serialized payload review + API response checks |
| Operations/security requirements | Permission, deactivation, and cleanup testing | Auth tests + deactivation/uninstall flow verification |
| Quality requirements | Classification determinism and queue size verification | Unit tests + boundary size checks |

Acceptance baseline:
- Canonical retry queue package requirements reflect current error classification, queue lifecycle, and management endpoint behavior.
- Dead letter queue semantics and retention policy are explicit and documented.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| RQ-FR-01, RQ-DR-05 | includes/class-gg-data-error-handler.php | Error classification patterns and classify_error() return contract |
| RQ-FR-02 to RQ-FR-08, RQ-DR-01 to RQ-DR-04 | includes/class-gg-data-retry-queue.php | Queue item lifecycle, retry scheduling, dead letter management |
| RQ-FR-09 to RQ-FR-11, RQ-DR-06 to RQ-DR-07 | includes/api/class-gg-data-rest-retry-queue-controller.php | REST endpoint registration and response contracts |
| RQ-FR-12 to RQ-FR-13 | includes/class-gg-data-retry-queue.php (retry_operation method) | Op type dispatch and deleted-post resolution |
| RQ-FR-14 | includes/class-gg-data-deactivator.php | WP-Cron event unscheduling on deactivation |
| RQ-FR-15, RQ-OR-04 | uninstall.php | wp_options cleanup on uninstall |
| RQ-FR-16, RQ-OR-06 | includes/class-gg-data-retry-queue.php (logging calls) | Operational log coverage |
| RQ-FR-05, RQ-OR-02 | includes/class-gg-data-retry-queue.php (constructor, process_queue) | WP-Cron scheduling and background-only processing |
| RQ-OR-01 | includes/api/class-gg-data-rest-retry-queue-controller.php (permission_callback) | manage_options enforcement |
| RQ-OR-05 | includes/class-gg-data-retry-queue.php, includes/class-gg-data-db.php | Sync metadata update deferred until retry success |
| RQ-QR-01 to RQ-QR-05 | docs/retry-queue/architecture.md, implementation files listed in References | Quality, determinism, and boundary expectations |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Sync contracts are treated as upstream dependencies; this subsystem handles their failure paths only.
- WordPress WP-Cron availability is a prerequisite for background retry processing; sites with WP-Cron disabled require external cron invocation.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| RQ-TBD-01 | 3.4 | Queue size SLO thresholds for high-volume sites are not codified as release-level limits. | Product and engineering | TBD |
| RQ-TBD-02 | 3.1 | Dead letter notification mechanism (email/webhook alerts) has not been implemented; documented as future enhancement. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Create a dedicated retry-queue package for retry resiliency documentation | Provides canonical single-responsibility documentation for the error recovery subsystem separate from sync orchestration | Documentation migration decision |
| 2026-04-04 | Use SRS-first migration flow | Aligns with existing rag/providers/rest-api/search/sync documentation workflow | Documentation migration decision |
