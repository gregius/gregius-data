# Architecture Description: Sync Orchestration Subsystem

Standard: ISO/IEC/IEEE 42010:2022  
Component: Sync Orchestration Subsystem  
Version: 1.0.0  
Date: 2026-04-04  
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data synchronizes WordPress content to PostgreSQL through real-time lifecycle hooks and batch processors, with automatic content preparation and provider-aware execution routing.

Scope includes:
- Real-time lifecycle sync orchestration for posts, meta, and terms
- Batch processors for offset-based pagination and bulk operations
- Content cleaning, hashing, and chunking pipelines
- Sync metadata tracking and drift validation
- Deterministic provider contract execution (bulk-capable providers)
- REST management endpoints for configuration and operations
- Multisite configuration isolation for WordPress-side settings and sync state
- Canonical PostgreSQL mirror naming across providers; mirror tables do not vary by site prefix

Explicitly excluded:
- Search ranking and retrieval algorithms
- Vector generation strategies (downstream consumer)
- WordPress hook internals or admin UX design

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrators | Predictable sync behavior and operational dashboards with clear status | High |
| Plugin developers | Stable lifecycle hooks and batch processor APIs for extensibility | High |
| Operations | Resumable batch operations and actionable validation reports | High |
| Integrators | Consistent provider-path behavior for Supabase and self-hosted PostgreSQL | High |

---

## 2. Context View (AV-01)

```
┌───────────────────────────────────────────────────────────────┐
│                         WordPress                             │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │  Post/Status/Term/Meta Changes                          │  │
│  │  (save_post, transition_post_status, etc.)             │  │
│  └─────────────────────────────────────────────────────────┘  │
│          │                                    │                │
│          ▼ (Real-time, non-blocking)         │ (Batch: REST)  │
├──────────────────────────────────────────────┼────────────────┤
│ Lifecycle Hooks                              REST Batch API   │
│ ↓                                            ↓                 │
│ Content Cleaner (HTML strip, hash)          Batch Processors  │
│ ↓                                            ↓                 │
│ Chunker (content segmentation)               Sync Service     │
│ ↓                                            ↓                 │
│ Sync Service Router                          Metadata Mgr     │
│ ↓                                            ↓                 │
│ DB Operations (provider contract)            Provider Router   │
└──────────────────────────────────────────────┴────────────────┘
           │                                    │
           └────────────────┬─────────────────┘
                            ▼
                  PostgreSQL Providers
                  ├─ PDO:      Bulk-capable provider contract, canonical mirror naming
                  └─ PostgREST: Bulk-capable provider contract, canonical mirror naming
                            ▼
                  PostgreSQL (wp_posts, wp_posts_clean,
                             wp_posts_chunks, sync_metadata)
```

Architecture intent:
- Keep real-time and batch operations unified through shared content cleaning and metadata tracking.
- Make provider routing transparent to callers; consistent result contracts.
- Preserve non-blocking semantics for real-time sync to avoid WordPress UX delays.

Mapping:
- AV-01 -> SYNC-FR-01 to SYNC-FR-09
- AV-01 -> SYNC-OR-01 to SYNC-OR-06

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. **Lifecycle Hooks Component** (`GG_Data_Lifecycle_Hooks`)
   - Registers WP action listeners for post/status/meta/term changes
   - Checks sync configuration (enabled types, statuses, real-time flag)
   - Routes to sync service; logs errors without raising exceptions

2. **Batch Processor Components** (`GG_Data_Post_Sync`, `GG_Data_Taxonomy_Sync`, `GG_Data_Postmeta_Sync`)
   - Implements offset-based pagination loops
   - Queries WordPress in batches; delegates to sync service per item
   - Aggregates counts and status; handles resumable operations

3. **Sync Service Component** (`GG_Data_Sync_Service`)
   - Facade and router; orchestrates upsert/delete for applicable entity
   - Dispatches to content cleaner and chunker
   - Calls metadata manager to record sync state

4. **Content Cleaner Component** (`GG_Data_Content_Cleaner`)
   - Strips HTML, calculates hash for change detection
   - Populates wp_posts_clean table
   - Invokes chunker for further segmentation

5. **Chunker Component** (`GG_Data_Chunker`)
   - Selects strategy per connection and post type
   - Generates normalized chunk rows in wp_posts_chunks
   - Returns chunk count for metadata aggregation

6. **Sync Metadata Manager** (`GG_Data_Sync_Metadata_Manager`)
   - Reads/updates wp_gg_sync_metadata table
   - Tracks WordPress and PostgreSQL record counts
   - Calculates drift; supports fast UI queries

7. **Sync Validator Component** (`GG_Data_Sync_Validator`)
   - Metadata-based validation (fast, ~100ms, auto-loaded by dashboard UI)
   - Full table-scan validation (comprehensive, 3–13s, backend/internal only — not exposed in dashboard)
   - Reports orphaned and missing records

8. **REST Controller Components** (`GG_Data_REST_Sync_Controller`, `GG_Data_REST_Sync_Validator_Controller`)
   - Registers routes for configuration, batch ops, content cleaning, validation
   - Permission checks and request validation

9. **DB Operations & Providers** (`GG_Data_DB`, `GG_Data_PostgreSQL_Provider`, `GG_Data_PostgREST_Provider`)
   - DB wrapper delegates to deterministic provider contract execution
   - Providers implement equivalent bulk-capable sync operations

### 3.2 Contract Boundaries

- Upstream dependencies: WordPress lifecycle actions and settings storage
- Downstream dependencies: Search and Vectors subsystems consuming wp_posts_clean and wp_posts_chunks
- Provider boundary: equivalent contract semantics across providers
- Mirror naming boundary: PostgreSQL table names are canonical and do not vary by multisite prefix

Mapping:
- AV-02 -> SYNC-FR-01 to SYNC-FR-09
- AV-02 -> SYNC-DR-01 to SYNC-DR-07

---

## 4. Runtime View (AV-03)

### 4.1 Real-Time Sync Flow

```
WordPress Action Triggered
    │
    ▼
Lifecycle Hook Handler
    │
    ├─ Check: is_synced_type? + is_enabled_status?
    ├─ If not valid → exit (no-op)
    └─ If valid:
        ├─ try {
      │   ├─ Sync Service.upsert ( post_id )
        │   │   ├─ Content Cleaner ( HTML strip, hash )
        │   │   ├─ Chunker ( content segments )
        │   │   ├─ DB Operations ( insert/update )
        │   │   └─ Metadata Manager ( mark_synced )
        │   │
        │   ├─ Sync postmeta (if enabled)
        │   ├─ Sync term relationships (if enabled)
        │   │
        │   └─ Return success
        │
        └─ } catch (Exception $e) {
            ├─ Log error + context
            └─ Return silently (NO exception to WordPress)
          }
```

Key property: **Non-blocking**. All errors logged but not raised.

### 4.2 Batch Sync Flow

```
POST /gg-data/v1/sync/post-type/{type}
    │
    ▼
Permission check (manage_options)
    │
    ▼
Get enabled_statuses from settings
    │
    ▼
Query WordPress (batch_size=100, offset=0)
    │
    ├─ For each post in batch:
    │   ├─ Call Sync Service (same as real-time)
    │   │   ├─ Content Cleaner
    │   │   ├─ Chunker
    │   │   └─ DB Operations
    │   └─ Aggregate counts
    │
   ├─ Provider contract execution
   │   └─ bulk_upsert_posts() on active provider
    │
    ├─ Update Metadata aggregates
    └─ Return: { processed, failed, has_more, total }
```

Key property: **Pagination-friendly**. Loop controlled by caller.

### 4.3 Content Cleaning & Chunking Flow

```
Sync Service Invokes Content Cleaner
    │
    ├─ Query original post content from WordPress (wp_posts)
    ├─ Strip HTML, calculate MD5 hash
    ├─ Check: has content changed? (compare with stored hash)
    │   ├─ NO → skip update
    │   └─ YES:
    │       ├─ Insert/update wp_posts_clean row
    │       └─ Invoke Chunker
    │
    └─ Chunker invokes per-connection strategy
       ├─ Chunking strategy A (fixed-size)
       ├─ Chunking strategy B (semantic)
       └─ Generate wp_posts_chunks rows
```

Mapping:
- AV-03 -> SYNC-FR-01 to SYNC-FR-10
- AV-03 -> SYNC-OR-01, SYNC-OR-04, SYNC-OR-05
- AV-03 -> SYNC-QR-01, SYNC-QR-02

---

## 5. Architectural Decisions (ADRs)

### AD-01: Non-Blocking Real-Time Sync

Decision:
- Real-time sync errors are caught and logged but NOT raised to WordPress save operations.

Rationale:
- WordPress admin UX must remain responsive; database sync failures should not block content saves.
- Allows retry via batch operations or manual validation.

Consequences:
- Operators must monitor logs for sync failures.
- Short-term drift between WordPress and PostgreSQL is acceptable.

Linked requirements:
- AD-01 -> SYNC-FR-02, SYNC-OR-01, SYNC-QR-01

### AD-02: Separate Batch Processors (Posts/Terms/Meta)

Decision:
- Implement independent batch processor classes for posts, taxonomies, and postmeta.

Rationale:
- Different pagination semantics and dependency ordering.
- Allows independent operation and orchestration based on operator needs.
- Independent result aggregation and retry logic.

Consequences:
- Requires coordination for full-sync workflows.
- Operators must call each batch type explicitly or via orchestration script.

Linked requirements:
- AD-02 -> SYNC-FR-03, SYNC-OR-05

### AD-03: Sync Metadata as Operational Aggregate

Decision:
- Maintain wp_gg_sync_metadata table with WordPress/PostgreSQL counts and drift for fast UI validation.

Rationale:
- Metadata-based validation executes in <500ms vs 3–13s for full scans.
- Dashboard can provide live status without table scans.

Consequences:
- Metadata can drift if manual SQL changes happen; requires periodic full validation.
- Metadata update must remain atomic with sync operations.

Linked requirements:
- AD-03 -> SYNC-FR-06, SYNC-DR-03, SYNC-QR-05

### AD-04: Strict Provider Contract Routing

Decision:
- Route batch operations through a strict bulk-capable provider contract for all providers.

Rationale:
- Shared provider contract eliminates behavior drift between provider paths.
- Bulk-capable operations reduce query overhead and keep contracts explicit.

Consequences:
- Providers must implement required bulk methods as a hard contract.
- Missing contract methods fail fast instead of silently degrading behavior.

Linked requirements:
- AD-04 -> SYNC-FR-05, SYNC-QR-03

### AD-05: Automatic Content Cleaning During Sync

Decision:
- Content cleaning (HTML stripping, hashing, chunking) is mandatory during both real-time and batch sync.

Rationale:
- Search and Vector subsystems require clean content.
- Hash-based change detection avoids redundant processing.
- Single invocation point reduces code duplication.

Consequences:
- Sync latency includes cleaning overhead.
- Chunking strategy changes require re-cleaning via manual bulk operation.

Linked requirements:
- AD-05 -> SYNC-FR-04, SYNC-DR-04, SYNC-OR-04

### AD-06: Per-Connection Chunking Strategy

Decision:
- Allow per-connection and per-post-type chunking strategy selection without plugin code changes.

Rationale:
- Different vector models may benefit from different chunk sizes.
- Settings management allows operator control.

Consequences:
- Chunking must be deterministic for identical content/strategy.
- Strategy changes require re-chunking of existing content.

Linked requirements:
- AD-06 -> SYNC-FR-11, SYNC-QR-04

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Impact |
|---|---|---|
| C-01 | Real-time sync MUST not block WordPress operations; errors are logged but not raised. | Transient drift between WordPress and PostgreSQL; requires monitoring. |
| C-02 | Content changing is detected via MD5 hash; collision is theoretically possible but practically low-risk. | Should formalize collision detection strategy. |
| C-03 | Batch operations are resumable via offset pagination; however, pagination offset can shift if new posts inserted mid-batch. | Operators must be aware of potential record skips in concurrent batches. |
| C-04 | Multisite deployments require per-site WordPress configuration isolation; PostgreSQL mirror table names remain canonical across sites. | Site-specific settings stay isolated while provider tables stay stable. |

### 6.2 Risks

| Risk ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Provider-path behavior divergence over time (bulk differs from sequential) | Medium | High | Keep unified result contract; parity regression tests across both providers |
| R-02 | Metadata tracking diverges from actual WordPress/PostgreSQL state | Medium | High | Periodic full validation runs; explicit cache invalidation on manual SQL changes |
| R-03 | Chunking strategy changes not automatically propagated to existing content | Low | Medium | Document requirement for manual re-chunk operation; include in operational runbooks |
| R-04 | Real-time sync failures accumulate without operator awareness | Medium | Medium | Ensure logs are monitored; dashboard alerts for drift threshold |

---

## 7. Coverage Mapping

| Architecture Item | SRS IDs Covered |
|---|---|
| AV-01 (Context View) | SYNC-FR-01 to SYNC-FR-09, SYNC-OR-01 to SYNC-OR-06 |
| AV-02 (Component View) | SYNC-FR-01 to SYNC-FR-09, SYNC-DR-01 to SYNC-DR-07 |
| AV-03 (Runtime View) | SYNC-FR-01 to SYNC-FR-10, SYNC-OR-01, SYNC-OR-04, SYNC-OR-05, SYNC-QR-01, SYNC-QR-02 |
| AD-01 | SYNC-FR-02, SYNC-OR-01, SYNC-QR-01 |
| AD-02 | SYNC-FR-03, SYNC-OR-05 |
| AD-03 | SYNC-FR-06, SYNC-DR-03, SYNC-QR-05 |
| AD-04 | SYNC-FR-05, SYNC-QR-03 |
| AD-05 | SYNC-FR-04, SYNC-DR-04, SYNC-OR-04 |
| AD-06 | SYNC-FR-11, SYNC-QR-04 |
