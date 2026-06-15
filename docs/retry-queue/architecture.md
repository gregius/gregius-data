# Architecture Description: Retry Queue Subsystem

Standard: ISO/IEC/IEEE 42010:2022
Component: Retry Queue Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how the Gregius Data retry queue subsystem intercepts sync database failures, classifies errors, schedules automatic retries with exponential backoff, and provides operator-facing dead letter management.

Scope includes:
- Error classification routing (transient / permanent / unknown)
- Retry queue item lifecycle (queueing → WP-Cron processing → success or dead letter)
- Dead letter queue management and operator recovery workflows
- REST endpoints for monitoring and manual intervention
- WP-Cron background scheduling and cleanup

Explicitly excluded:
- Sync orchestration logic (upstream dependency; see [sync/architecture.md](../sync/architecture.md))
- Schema management and table creation
- WordPress front-end or admin UI beyond the React dashboard integration boundary

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrators | Visibility into pending and permanently failed items via dashboard | High |
| Operations | Actionable REST endpoints for manual retry and dead letter clearing | High |
| Plugin developers | Stable error handler and queue manager APIs for extension | High |
| Product | Reliable recovery behavior that doesn't degrade WordPress content editing | High |

---

## 2. Context View (AV-01)

```
WordPress Content Editing
        │
        ▼
Sync Operation (GG_Data_DB)
        │
        ├─ Success → Update sync metadata
        │
        └─ Failure:
               │
               ▼
       GG_Data_Error_Handler
         classify_error()
               │
               ├─ Permanent → Log error, return false (no queue)
               │
               ├─ Transient/Unknown →
               │         ▼
               │   GG_Data_Retry_Queue
               │    queue_for_retry()
               │     Save to wp_options
               │         ▼
               │   WP-Cron (every 60s)
               │    process_queue()
               │         │
               │         ├─ Retry success → Remove from queue
               │         │                  Update sync metadata
               │         │
               │         └─ Retry fail (×3) → Dead letter queue
               │                              (wp_options, max 100 items)
               │
               └─ REST API (manage_options)
                    GET  /sync/retry-queue          (status)
                    POST /sync/retry-queue/retry/{n} (manual retry)
                    DELETE /sync/retry-queue/clear   (bulk clear)
```

Mapping:
- AV-01 → RQ-FR-01 to RQ-FR-16
- AV-01 → RQ-OR-01 to RQ-OR-06

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. **Error Handler Component** (`GG_Data_Error_Handler`)
   - Stateless classifier; receives error message string, returns structured classification result
   - Maintains two internal pattern lists: 18 transient patterns, 15 permanent patterns
   - Unknown errors (no match) default to retry-safe to prevent silent data loss

2. **Retry Queue Manager Component** (`GG_Data_Retry_Queue`)
   - Manages queue item serialization into wp_options
   - Registers and processes WP-Cron event `gg_data_process_retry_queue`
   - Implements retry dispatch router: switches on operation_type to reconstruct and re-execute operation
   - Implements dead letter promotion and rolling-window eviction (max 100 items)
   - Exposes `manual_retry($index)` and `clear_dead_letter_queue()` for REST controller delegation

3. **DB Integration Layer** (`GG_Data_DB`)
   - 8 instrumented call sites that invoke `queue_for_retry()` on failure
   - Call sites span: `upsert_post` (×3), `delete_post` (×1), `upsert_post_meta` (×3), `delete_post_meta` (×1)
   - Provides `operation_data` payload carrying context needed for retry reconstruction

4. **REST Controller Component** (`GG_Data_REST_Retry_Queue_Controller`)
   - Registers 3 REST routes under `gg-data/v1/sync/retry-queue`
   - Delegates all queue state management to `GG_Data_Retry_Queue`
   - Enforces `manage_options` on all routes

5. **React Dashboard Component** (`RetryQueueCard.js`)
   - Displays queue metrics, pending items table, dead letter items
   - Provides manual retry (per item) and clear actions
   - Auto-refreshes every 30 seconds
   - Scope boundary: UI consumer; no architectural decisions depend on its internal implementation

6. **Deactivation/Uninstall Handlers** (`GG_Data_Deactivator`, `uninstall.php`)
   - Deactivation: unschedules `gg_data_process_retry_queue` WP-Cron event; preserves queue data
   - Uninstall: deletes `gg_data_retry_queue` and `gg_data_dead_letter_queue` from wp_options

### 3.2 Contract Boundaries

- Upstream: `GG_Data_DB` failure paths provide operation_type, entity_id, error_message, and operation_data
- Downstream: Successful retry calls back into `GG_Data_DB` upsert/delete methods using stored operation_data
- Sync metadata: Updated only on retry success (never on queue entry or on failure)

Mapping:
- AV-02 → RQ-FR-01 to RQ-FR-16
- AV-02 → RQ-DR-01 to RQ-DR-07

---

## 4. Runtime View (AV-03)

### 4.1 Queueing Flow (DB Failure Path)

```
GG_Data_DB method (e.g., upsert_post) fails
    │
    ├─ Extract error string from PDO errorInfo or Exception message
    ├─ Instantiate GG_Data_Error_Handler
    ├─ $handler->classify_error($error_string)
    │   ├─ Returns: ['type' => 'transient', 'retry_safe' => true, 'message' => '...']
    │   └─ Returns: ['type' => 'permanent', 'retry_safe' => false, 'message' => '...']
    │
    ├─ retry_safe = false → log [error], return false (caller gets false)
    │
    └─ retry_safe = true:
        ├─ Instantiate GG_Data_Retry_Queue
        ├─ $queue->queue_for_retry(
        │       'sync_post',         // operation_type
        │       $post_arr['ID'],     // entity_id
        │       $error_message,      // error_message
        │       $post_arr            // operation_data (full context)
        │   )
        ├─ Build queue item: attempt_count=1, next_retry=now+5s, first_attempt=now
        ├─ Serialize + store in wp_options['gg_data_retry_queue']
        ├─ Log [warning]: "Queued sync_post #{id} for retry - next retry in 5s"
        └─ Return true (queued); DB method returns false to original caller
```

### 4.2 WP-Cron Processing Flow

```
WP-Cron fires: gg_data_process_retry_queue (every 60s)
    │
    ▼
process_queue()
    │
    ├─ Load gg_data_retry_queue from wp_options
    │
    └─ For each queue item:
        │
        ├─ Is next_retry <= now?
        │   └─ NO → skip (not ready)
        │
        └─ YES → retry_operation($item)
                  │
                  ├─ Switch on operation_type:
                  │   ├─ sync_post:    get_post() + GG_Data_DB->upsert_post()
                  │   │                (if post deleted → return true, it's resolved)
                  │   ├─ sync_meta:    GG_Data_DB->upsert_post_meta()
                  │   ├─ delete_post:  GG_Data_DB->delete_post()
                  │   └─ delete_meta:  GG_Data_DB->delete_post_meta()
                  │
                  ├─ SUCCESS:
                  │   ├─ Remove from queue
                  │   └─ Log [info]: "Retry succeeded after N attempts"
                  │
                  └─ FAIL:
                      ├─ Increment attempt_count
                      ├─ Update last_error
                      │
                      └─ attempt_count >= MAX_RETRY_ATTEMPTS (3)?
                          ├─ YES → Add moved_to_dead_letter timestamp
                          │        Append to gg_data_dead_letter_queue
                          │        Enforce 100-item rolling window (drop oldest)
                          │        Remove from pending queue
                          │        Log [error]: "Moved to dead letter queue"
                          │
                          └─ NO → Calculate next_retry:
                                   attempt 2 → now + 30s
                                   attempt 3 → now + 300s
                                   Update item in queue
    │
    └─ Save updated queue to wp_options
```

### 4.3 Manual Retry Flow

```
POST /gg-data/v1/sync/retry-queue/retry/{index}
    │
    ├─ Permission: manage_options
    ├─ Load dead_letter_queue[index]
    ├─ Reset item: attempt_count=1, next_retry=now+5s
    ├─ Append back to pending queue
    ├─ Remove from dead letter queue
    └─ Log [info]: "Manually retrying from dead letter"
    └─ Return: {"success": true, "message": "Item moved back to retry queue"}
```

Mapping:
- AV-03 → RQ-FR-02 to RQ-FR-16
- AV-03 → RQ-OR-01, RQ-OR-02, RQ-OR-05, RQ-OR-06
- AV-03 → RQ-QR-01 to RQ-QR-04

---

## 5. Architectural Decisions (ADRs)

### AD-01: wp_options Storage (Not a Dedicated DB Table)

Decision:
- Retry queue and dead letter queue are stored as serialized PHP arrays in wp_options.

Rationale:
- Retry queue is a transient operational state; it does not require the full overhead of a dedicated table with migrations.
- Queue sizes are bounded (dead letter max 100 items; pending queue naturally drains).
- wp_options is always available without schema setup; reduces activation dependencies.

Consequences:
- Autoloaded options add small overhead on every WordPress request; acceptable given bounded payload size (~5–15 KB).
- Queue is not queryable via SQL; not suitable for analytics or high-volume sites.

Linked requirements:
- AD-01 → RQ-DR-03, RQ-DR-04, RQ-QR-02

### AD-02: Exponential Backoff (5s / 30s / 300s) with 3-Attempt Limit

Decision:
- Three retry attempts with delays of 5s, 30s, and 300s before dead letter promotion.

Rationale:
- 5 seconds covers brief network hiccups.
- 30 seconds allows connection pool resets.
- 5 minutes handles server restarts or maintenance windows.
- 3 attempts balances recovery depth against queue accumulation risk.

Consequences:
- Total window before dead letter: ~5m 35s from first failure.
- Sites with maintenance windows longer than 5 minutes will need manual retry from dead letter after recovery.

Linked requirements:
- AD-02 → RQ-FR-03, RQ-FR-04, RQ-QR-04

### AD-03: Error-Classification-First Routing

Decision:
- Error message classification via `GG_Data_Error_Handler` happens before any queue decision; permanent errors are never queued.

Rationale:
- Prevents infinite retry cycles on non-recoverable errors (schema mismatch, permission denied).
- Keeps dead letter queue reserved for items that reached max retries, not structural failures.
- Unknown errors default to retry-safe to avoid silent data loss on unrecognized error formats.

Consequences:
- Error pattern lists must be maintained when new error types are introduced by PostgreSQL or PDO upgrades.

Linked requirements:
- AD-03 → RQ-FR-01, RQ-FR-02, RQ-QR-01

### AD-04: Dead Letter Queue as Operator Holding State (Not Permanent Discard)

Decision:
- Items that exceed max retry attempts are moved to the dead letter queue, not discarded. Operators can inspect, manually retry, or clear.

Rationale:
- Enables operator-driven recovery after fixing the underlying issue (for example: schema sync after column-missing error resolved).
- 100-item rolling window prevents unbounded growth while retaining recent failure context.

Consequences:
- Dead letter items contain full operation context; operators can reason about root cause.
- Manual retry resets attempt count to 1, giving the item a full new retry cycle.

Linked requirements:
- AD-04 → RQ-FR-07, RQ-FR-08, RQ-FR-10, RQ-FR-11

### AD-05: WP-Cron for Background Processing

Decision:
- Queue processing runs exclusively via WP-Cron on a 60-second interval; no synchronous processing during WordPress requests.

Rationale:
- Keeps real-time sync non-blocking (aligned with Sync subsystem AD-01).
- WP-Cron is available without external infrastructure dependencies.
- Acceptable latency for retry processing (up to 60 seconds before a ready item is processed).

Consequences:
- Sites with WP-Cron disabled require external cron invocation for retries to process.
- There is a latency window of up to 60 seconds before a ready retry item is re-attempted.

Linked requirements:
- AD-05 → RQ-FR-05, RQ-FR-06, RQ-OR-02

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Impact |
|---|---|---|
| C-01 | Queue processing MUST be non-blocking; all processing occurs in WP-Cron callback, never in sync request path. | Retry latency limited by WP-Cron interval (60s minimum). |
| C-02 | wp_options storage is autoloaded; queue serialization size must remain within practical limits (~15 KB). | At high failure rates, pending queue could grow large; monitor and tune batch processing rate. |
| C-03 | Error pattern matching is string-based; new PostgreSQL error message formats may not be classified correctly. | Unknown classification defaults to retry-safe, but may cause unnecessary retries for new permanent errors. |
| C-04 | WP-Cron reliability depends on WordPress receiving HTTP traffic; low-traffic sites may have delayed queue processing. | Operators on low-traffic sites should configure system cron to trigger wp-cron.php directly. |

### 6.2 Risks

| Risk ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Sustained DB connectivity loss causes pending queue to grow unboundedly | Medium | Medium | Monitor pending_retries count via dashboard; alert when > 20 items; address underlying connection issue |
| R-02 | Error pattern lists become stale as PostgreSQL or PDO error format evolves | Low | Medium | Treat error-handler pattern lists as a versioned maintenance artifact; review on PostgreSQL major version upgrades |
| R-03 | Dead letter queue loses items silently when rolling window evicts older items at high failure rates | Low | Medium | Dead letter cap of 100 is generous for normal operation; high-rate failures indicate systemic issues requiring operator action |
| R-04 | Manual retry from dead letter re-queues item but underlying cause not yet fixed | Medium | Low | Operators should diagnose dead letter cause before retrying; log entries provide context |

---

## 7. Coverage Mapping

| Architecture Item | SRS IDs Covered |
|---|---|
| AV-01 (Context View) | RQ-FR-01 to RQ-FR-16, RQ-OR-01 to RQ-OR-06 |
| AV-02 (Component View) | RQ-FR-01 to RQ-FR-16, RQ-DR-01 to RQ-DR-07 |
| AV-03 (Runtime View) | RQ-FR-02 to RQ-FR-16, RQ-OR-01, RQ-OR-02, RQ-OR-05, RQ-OR-06, RQ-QR-01 to RQ-QR-04 |
| AD-01 | RQ-DR-03, RQ-DR-04, RQ-QR-02 |
| AD-02 | RQ-FR-03, RQ-FR-04, RQ-QR-04 |
| AD-03 | RQ-FR-01, RQ-FR-02, RQ-QR-01 |
| AD-04 | RQ-FR-07, RQ-FR-08, RQ-FR-10, RQ-FR-11 |
| AD-05 | RQ-FR-05, RQ-FR-06, RQ-OR-02 |
