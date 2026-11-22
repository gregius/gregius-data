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

The retry queue subsystem sits between the sync database operations layer and PostgreSQL. When sync database operations fail at one of 5 instrumented call sites in `Gregius_Data_DB`, the retry queue classifies the error and either queues it for automatic retry or discards it as a permanent failure.

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
- includes/class-gregius-data-error-handler.php
- includes/class-gregius-data-retry-queue.php
- includes/api/class-gregius-data-rest-retry-queue-controller.php
- includes/class-gregius-data-db.php
- includes/class-gregius-data-deactivator.php
- uninstall.php
- includes/class-gregius-data-uninstaller.php
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
| RQ-FR-12 | The software MUST support retry dispatch for the queued operation types: sync_meta, delete_post, and delete_meta. | Must |
| RQ-FR-14 | The software MUST unschedule the WP-Cron retry event on plugin deactivation. | Must |
| RQ-FR-15 | The software MUST delete queue data from wp_options on plugin uninstall when data removal is enabled via the `gregius_data_remove_data_on_uninstall` option; when removal is not enabled, WordPress-side plugin data (including the retry queue) is preserved. | Must |
| RQ-FR-16 | The software MUST log all queueing, successful retry, max-retry, and manual operation events with operational context. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| RQ-DR-01 | Queue items MUST carry: operation_type, entity_id, error_message, error_type, operation_data, attempt_count, first_attempt, next_retry, and last_error. | Must |
| RQ-DR-02 | Dead letter items MUST carry all queue item fields plus a moved_to_dead_letter timestamp. | Must |
| RQ-DR-03 | Queue items MUST be stored under the wp_options key gregius_data_retry_queue as a serialized PHP array. | Must |
| RQ-DR-04 | Dead letter items MUST be stored under the wp_options key gregius_data_dead_letter_queue as a serialized PHP array. | Must |
| RQ-DR-05 | Error classification results MUST return type (transient, permanent, or unknown), retry_safe (bool), and message string. | Must |
| RQ-DR-06 | REST queue status responses MUST include pending_retries count, failed_permanently count, items array, and dead_letter_items array. | Must |
| RQ-DR-07 | REST responses MUST use structured success/error payloads with operator-actionable messages. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| RQ-OR-01 | All retry queue REST endpoints MUST enforce the manage_options capability. | Must |
| RQ-OR-02 | Queue processing MUST NOT block the WordPress request cycle; all processing MUST occur via WP-Cron callback. | Must |
| RQ-OR-03 | The subsystem MUST preserve queue data in wp_options on plugin deactivation to allow recovery on reactivation. | Must |
| RQ-OR-04 | The subsystem MUST clear retry-queue WP-Cron events on plugin uninstall unconditionally, and MUST delete retry-queue wp_options data when data removal is enabled via the `gregius_data_remove_data_on_uninstall` option. | Must |
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

| SRS Requirement ID | Source (file:line) | Notes |
|---|---|---|
| RQ-FR-01 | includes/class-gregius-data-error-handler.php:96-127 | classify_error() transient/permanent/unknown routing |
| RQ-FR-02 | includes/class-gregius-data-retry-queue.php:88-137 | queue_for_retry(); permanent errors not queued (:92-104) |
| RQ-FR-03 | includes/class-gregius-data-error-handler.php:140-146 | calculate_retry_delay() 5s/30s/300s |
| RQ-FR-04 | includes/class-gregius-data-retry-queue.php:29, :182-195 | MAX_RETRY_ATTEMPTS=3; dead-letter promotion gate |
| RQ-FR-05 | includes/class-gregius-data-retry-queue.php:73-77, includes/class-gregius-data-cron-manager.php:46-49 | every-minute WP-Cron schedule |
| RQ-FR-06 | includes/class-gregius-data-retry-queue.php:156-160 | skip items whose next_retry has not elapsed |
| RQ-FR-07 | includes/class-gregius-data-retry-queue.php:182-195, :295-307 | dead-letter promotion with moved_to_dead_letter timestamp |
| RQ-FR-08 | includes/class-gregius-data-retry-queue.php:301-304 | 100-item rolling window |
| RQ-FR-09 | includes/api/class-gregius-data-rest-retry-queue-controller.php:44-54, :96-114 | GET queue status endpoint |
| RQ-FR-10 | includes/api/class-gregius-data-rest-retry-queue-controller.php:57-74, :122-150 | POST manual retry endpoint |
| RQ-FR-11 | includes/api/class-gregius-data-rest-retry-queue-controller.php:77-87, :158-181 | DELETE clear dead letter endpoint |
| RQ-FR-12 | includes/class-gregius-data-retry-queue.php:243-288 | retry_operation() dispatch (sync_meta/delete_post/delete_meta) |
| RQ-FR-14 | includes/class-gregius-data-deactivator.php:29 | wp_clear_scheduled_hook on deactivation |
| RQ-FR-15 | includes/class-gregius-data-uninstaller.php:78-110, :169-181 | queue data removed only when retention flag set |
| RQ-FR-16 | includes/class-gregius-data-retry-queue.php:94-102, :125-134, :172-180, :188-195, :206-216, :363-370, :386-389 | queueing/retry/dead-letter/manual logging |
| RQ-DR-01 | includes/class-gregius-data-retry-queue.php:108-118 | queue item field construction |
| RQ-DR-02 | includes/class-gregius-data-retry-queue.php:298 | moved_to_dead_letter appended to item |
| RQ-DR-03 | includes/class-gregius-data-retry-queue.php:36, :106, :123 | OPTION_KEY gregius_data_retry_queue |
| RQ-DR-04 | includes/class-gregius-data-retry-queue.php:43, :296, :306 | DEAD_LETTER_KEY gregius_data_dead_letter_queue |
| RQ-DR-05 | includes/class-gregius-data-error-handler.php:96-127 | type/retry_safe/message return contract |
| RQ-DR-06 | includes/class-gregius-data-retry-queue.php:321-331 | get_queue_status() field contract |
| RQ-DR-07 | includes/api/class-gregius-data-rest-retry-queue-controller.php:100-113, :128-149, :162-181 | structured success/error payloads |
| RQ-OR-01 | includes/api/class-gregius-data-rest-retry-queue-controller.php:189-211 | manage_options permission callbacks |
| RQ-OR-02 | includes/class-gregius-data-retry-queue.php:62-66, :144-235 | WP-Cron-only background processing |
| RQ-OR-03 | includes/class-gregius-data-deactivator.php:25-43 | deactivation clears cron hooks + transient only; queue options preserved |
| RQ-OR-04 | includes/class-gregius-data-uninstaller.php:87, :90-92, :134-162, :169-181 | unconditional cron clear; conditional data removal |
| RQ-OR-05 | includes/class-gregius-data-db.php:356, :479, :488, :529, includes/class-gregius-data-retry-queue.php:167-181 | sync metadata deferred until retry success |
| RQ-OR-06 | includes/class-gregius-data-retry-queue.php:94-102, :125-134, :172-180, :188-195, :206-216, :363-370, :386-389 | operational log context |
| RQ-QR-01 | includes/class-gregius-data-error-handler.php:96-127 | deterministic classification |
| RQ-QR-02 | includes/class-gregius-data-retry-queue.php:106, :123, :221 | pending queue has no hard size cap |
| RQ-QR-03 | includes/class-gregius-data-retry-queue.php:301-304 | dead-letter rolling window cap of 100 |
| RQ-QR-04 | includes/class-gregius-data-retry-queue.php:147-149 | early return when queue empty |
| RQ-QR-05 | includes/api/class-gregius-data-rest-retry-queue-controller.php:96-181 | synchronous REST response for manual operations |

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
