# Developer Documentation: Retry Queue Subsystem

Standard: ISO/IEC/IEEE 26514:2022
Component: Retry Queue Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Architecture: [architecture.md](architecture.md)

---

## 1. Component Overview

### Error Handler (`GG_Data_Error_Handler`)

Stateless classifier. Call `classify_error()` with a raw error string to get a structured result:

```php
$handler = new GG_Data_Error_Handler();
$result  = $handler->classify_error($error_message);
// $result = [
//   'type'       => 'transient' | 'permanent' | 'unknown',
//   'retry_safe' => true | false,
//   'message'    => 'Descriptive label',
// ]
```

Calculate retry delay for a given attempt number:

```php
$delay = $handler->calculate_retry_delay($attempt_number);
// Attempt 1 → 5 seconds
// Attempt 2 → 30 seconds
// Attempt 3 → 300 seconds
```

### Retry Queue Manager (`GG_Data_Retry_Queue`)

Core queue manager. Instantiate once per operation context:

```php
$queue = new GG_Data_Retry_Queue();

// Queue a failed operation for retry
$queued = $queue->queue_for_retry(
    'sync_post',   // operation_type: sync_post | sync_meta | delete_post | delete_meta
    $post_id,      // entity_id (int)
    $error_message, // raw error string from PDO or Exception
    $operation_data // array: full context needed to reconstruct the operation
);
// Returns true if queued (transient/unknown error), false if permanent (not queued)

// Force-process the queue (bypasses WP-Cron timing; useful for debugging)
$queue->process_queue();

// Get status snapshot for dashboard or monitoring
$status = $queue->get_queue_status();
// Returns: [
//   'pending_retries'   => int,
//   'failed_permanently'=> int,
//   'items'             => array,
//   'dead_letter_items' => array,
// ]

// Manual retry of a dead letter item by index
$success = $queue->manual_retry($index); // Returns bool

// Clear all dead letter items
$cleared = $queue->clear_dead_letter_queue(); // Returns int (count cleared)
```

**Constants:**

| Constant | Value | Description |
|---|---|---|
| `GG_Data_Retry_Queue::MAX_RETRY_ATTEMPTS` | 3 | Max retries before dead letter promotion |
| `GG_Data_Retry_Queue::OPTION_KEY` | `gg_data_retry_queue` | wp_options key for pending queue |
| `GG_Data_Retry_Queue::DEAD_LETTER_KEY` | `gg_data_dead_letter_queue` | wp_options key for dead letter queue |

---

## 2. REST API Routes

All routes require `manage_options` capability. Use `X-WP-Nonce` header with a valid nonce.

### GET `/wp-json/gg-data/v1/sync/retry-queue`

Retrieve current queue status.

Response:
```json
{
  "success": true,
  "data": {
    "pending_retries": 2,
    "failed_permanently": 1,
    "items": [
      {
        "operation_type": "sync_post",
        "entity_id": 123,
        "attempt_count": 2,
        "error_type": "transient",
        "next_retry": "2026-04-04 11:30:00",
        "last_error": "connection timeout"
      }
    ],
    "dead_letter_items": [
      {
        "operation_type": "sync_meta",
        "entity_id": 456,
        "attempt_count": 3,
        "moved_to_dead_letter": "2026-04-04 11:00:00",
        "last_error": "max retries exceeded"
      }
    ]
  }
}
```

### POST `/wp-json/gg-data/v1/sync/retry-queue/retry/{index}`

Move a dead letter item back to the pending queue. `{index}` is the array index of the dead letter item.

```bash
curl -X POST \
  -H "X-WP-Nonce: [nonce]" \
  "https://example.com/wp-json/gg-data/v1/sync/retry-queue/retry/0"
```

Response:
```json
{
  "success": true,
  "message": "Item moved back to retry queue"
}
```

### DELETE `/wp-json/gg-data/v1/sync/retry-queue/clear`

Clear all items from the dead letter queue.

```bash
curl -X DELETE \
  -H "X-WP-Nonce: [nonce]" \
  "https://example.com/wp-json/gg-data/v1/sync/retry-queue/clear"
```

Response:
```json
{
  "success": true,
  "message": "Cleared 5 items from dead letter queue",
  "count": 5
}
```

---

## 3. Data Structures

### Queue Item

```php
[
  'operation_type' => 'sync_post',           // sync_post | sync_meta | delete_post | delete_meta
  'entity_id'      => 123,                   // Post ID or meta ID (int)
  'error_message'  => 'PDO Error: ...',      // Original error string
  'error_type'     => 'transient',           // transient | permanent | unknown
  'operation_data' => [...],                 // Full context array for retry reconstruction
  'attempt_count'  => 1,                     // Current attempt number (1–3)
  'first_attempt'  => '2026-04-04 10:00:00', // Timestamp of initial failure
  'next_retry'     => '2026-04-04 10:00:05', // Scheduled retry time
  'last_error'     => 'PDO Error: ...',      // Most recent error message
]
```

### Dead Letter Item

All queue item fields, plus:

```php
[
  // All queue item fields above
  'moved_to_dead_letter' => '2026-04-04 10:06:00', // Timestamp when promoted to dead letter
]
```

---

## 4. Error Classification Reference

### Transient Errors (retry-safe)

| Category | Example Patterns |
|---|---|
| Connection failures | `connection refused`, `server closed the connection`, `lost connection` |
| Timeouts | `connection timed out`, `statement timeout`, `query execution timeout` |
| Resource contention | `deadlock detected`, `too many connections`, `lock wait timeout` |
| Temporary failures | `server has gone away`, `could not connect`, `temporary failure` |

### Permanent Errors (not retry-safe)

| Category | Example Patterns |
|---|---|
| Schema errors | `column does not exist`, `relation does not exist`, `table does not exist` |
| Constraint violations | `unique constraint`, `foreign key constraint`, `not null constraint`, `duplicate key` |
| Data errors | `invalid input syntax`, `value too long`, `out of range` |
| Permission errors | `permission denied`, `authentication failed`, `insufficient privilege` |

### Unknown Errors

Unclassified errors (no pattern match) default to `retry_safe = true` to prevent silent data loss.

---

## 5. DB Integration Points

The following call sites in `GG_Data_DB` invoke `queue_for_retry()` on failure:

| Method | Trigger | Operation Type |
|---|---|---|
| `upsert_post()` | PDO error result | `sync_post` |
| `upsert_post()` | Exception during binding | `sync_post` |
| `upsert_post()` | PDOException outer catch | `sync_post` |
| `delete_post()` | PDOException | `delete_post` |
| `upsert_post_meta()` | PDO error result | `sync_meta` |
| `upsert_post_meta()` | PDOException | `sync_meta` |
| `upsert_post_meta()` | General exception | `sync_meta` |
| `delete_post_meta()` | PDOException | `delete_meta` |

Operation data passed includes the full payload array used by the originating DB method, allowing complete operation reconstruction on retry.

---

## 6. WP-Cron Reference

| Property | Value |
|---|---|
| Hook name | `gg_data_process_retry_queue` |
| Schedule key | `gg_data_every_minute` |
| Interval | 60 seconds |
| Callback | `GG_Data_Retry_Queue::process_queue()` |
| Registered in | `GG_Data_Retry_Queue` constructor |
| Unscheduled on | Plugin deactivation (`GG_Data_Deactivator::deactivate()`) |
| Cleaned up on | Plugin uninstall (`uninstall.php`) |

---

## 7. Manual Operations

### Inspect queue via PHP (debugging)

```php
// Pending retry queue
$pending = get_option('gg_data_retry_queue', []);
print_r($pending);

// Dead letter queue
$dead = get_option('gg_data_dead_letter_queue', []);
print_r($dead);

// Force a processing cycle regardless of WP-Cron timing
$queue = new GG_Data_Retry_Queue();
$queue->process_queue();
```

### Bypass via WP-CLI

```bash
# Trigger WP-Cron processing manually on sites with external cron
wp cron event run gg_data_process_retry_queue
```

---

## 8. Troubleshooting

### Symptom: High pending retry count (> 20 items)

**Root cause:** Sustained PostgreSQL connection instability or network disruption.

**Diagnosis:**
1. `GET /wp-json/gg-data/v1/sync/retry-queue` → review last_error patterns
2. Review error logs: `grep "Queued sync" <logfile>`
3. Check PostgreSQL connection health via Settings dashboard

**Resolution:**
1. Resolve underlying connectivity issue.
2. Pending items will automatically clear as WP-Cron retries them.
3. If items have already reached dead letter, use manual retry after fixing the root cause.

### Symptom: Dead letter items accumulating

**Root cause:** Schema mismatch, permission error, or data validation failure.

**Diagnosis:**
1. Review dead_letter_items[].last_error — permanent errors indicate structural issue.
2. Check error_type field — if `permanent`, the item never should have been queued; check error handler patterns.
3. If `transient`/`unknown`, it reached 3 attempts before the underlying issue was resolved.

**Resolution:**
1. Fix the underlying issue (run schema validation, fix permissions).
2. Use `POST /retry-queue/retry/{index}` to re-queue per item, or `DELETE /retry-queue/clear` if all are stale.

### Symptom: Retry queue not processing

**Root cause:** WP-Cron is disabled or misconfigured; no HTTP traffic to trigger WP-Cron.

**Diagnosis:**
1. Check if `DISABLE_WP_CRON` is set in `wp-config.php`.
2. On low-traffic sites, WP-Cron may never fire.

**Resolution:**
1. Configure system cron on the server to invoke `wp-cron.php` every minute:
   ```
   * * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null
   ```
2. Alternatively: `wp cron event run gg_data_process_retry_queue` for on-demand triggering.

### Symptom: Same entity failing repeatedly

**Root cause:** Corrupt post data, invalid meta value, or persistent schema mismatch for that entity.

**Diagnosis:**
1. `grep "entity_id: {id}" <logfile>` to see full failure history.
2. Inspect the post in WordPress admin for data anomalies.

**Resolution:**
1. Fix or remove the problematic data.
2. Clear the dead letter item for that entity.
3. Trigger a fresh sync via batch sync endpoint.

---

## 9. Integration Guide

### Monitor dead letter from external system

Poll the status endpoint for alerting:

```javascript
const res = await fetch('/wp-json/gg-data/v1/sync/retry-queue', {
  headers: { 'X-WP-Nonce': ggDataSettings.nonce }
});
const { data } = await res.json();

if (data.failed_permanently > 10) {
  alert('Dead letter queue threshold exceeded — investigation required');
}
```

### Trigger manual re-sync after resolving dead letter cause

After resolving the underlying issue, clear dead letter and re-run batch sync:

```bash
# Clear dead letter
curl -X DELETE -H "X-WP-Nonce: [nonce]" \
  "https://example.com/wp-json/gg-data/v1/sync/retry-queue/clear"

# Re-run batch sync for affected post type
curl -X POST -H "X-WP-Nonce: [nonce]" \
  "https://example.com/wp-json/gg-data/v1/sync/post-type/post?batch_size=100&offset=0"
```
