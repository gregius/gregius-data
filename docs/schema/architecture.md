# Architecture Description: Schema Management Subsystem

Standard: ISO/IEC/IEEE 42010:2022  
Component: Schema Management Subsystem  
Version: 1.0.0  
Date: 2026-04-04  
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data creates, verifies, versions, and upgrades schema contracts required by sync, search, vectors, and related operational workflows.

Scope includes:
- Provider-aware schema lifecycle routing
- Schema creation and upgrade orchestration
- Extension installation policy for direct PostgreSQL
- Version and status contracts per connection
- Supabase/PostgREST manual SQL setup and verification flow
- Schema REST management endpoints

Explicitly excluded:
- Full content sync orchestration behavior
- Search ranking and retrieval algorithm internals
- Vector generation strategy internals outside schema contracts

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrators | Predictable schema setup and upgrade flows with clear failure messages | High |
| Plugin developers | Stable schema manager and route contracts across provider types | High |
| Operations | Fast, reliable schema status visibility and version diagnostics | High |
| Integrators | Safe behavior differences for direct PostgreSQL vs PostgREST connections | High |

---

## 2. Context View (AV-01)

```
┌───────────────────────────────────────────────────────────────────┐
│                         WordPress Runtime                         │
│                                                                   │
│  Dashboard / REST Clients                                          │
│          │                                                        │
│          ▼                                                        │
│  Schema REST Layer                                                 │
│  - /schema/status                                                  │
│  - /schema/create                                                  │
│  - /schema/upgrade                                                 │
│  - /schema/verify                                                  │
│  - /schema/sql                                                     │
│          │                                                        │
│          ▼                                                        │
│  Schema Management Layer                                           │
│  - GG_Data_Schema_Manager                                          │
│  - GG_Data_Settings_Manager                                        │
│  - GG_Data_Connection_Manager                                      │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
           │                                   │
           ▼                                   ▼
   Direct PostgreSQL Path                 PostgREST/Supabase Path
   (PDO, SQL execution)                   (manual SQL + verification)
```

Architecture intent:
- Keep one schema management contract while allowing provider-specific setup behavior.
- Preserve compatibility fields expected by current dashboard clients.
- Gate schema mutations behind administrative authorization.

Mapping:
- AV-01 -> SCH-FR-01 to SCH-FR-10
- AV-01 -> SCH-OR-01 to SCH-OR-06

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. Schema Manager Component (`GG_Data_Schema_Manager`)
- Orchestrates create/upgrade/status logic per connection.
- Routes by provider type (`postgresql` vs `postgrest`).
- Persists schema version metadata through settings manager.
- Handles legacy fallback migration for schema version values.

2. Schema REST Controller Component (`GG_Data_REST_Schema_Controller`)
- Registers `/gg-data/v1/schema/*` route family.
- Validates connection targeting and connection type constraints.
- Returns normalized success/error payloads for UI clients.
- Enforces permission checks via `manage_options`.

3. Settings and Connection Components
- `GG_Data_Settings_Manager`: source of connection and schema version state.
- `GG_Data_Connection_Manager`: provider retrieval for non-PDO status checks.
- `GG_Data_DB`: direct PostgreSQL connection path used by schema manager.

4. Provider Components
- `GG_Data_PostgreSQL_Provider`: direct DB runtime operations.
- `GG_Data_PostgREST_Provider`: REST-based status/verification path and schema version read.

5. SQL Artifact Component
- `includes/sql/postgrest-schema.sql`: canonical manual setup SQL for PostgREST/Supabase flow.

### 3.2 Contract Boundaries

- Upstream dependencies: connection config and credentials in settings.
- Downstream dependencies: sync/search/vector subsystems requiring schema readiness.
- Security boundary: schema route mutations restricted to administrators.

Mapping:
- AV-02 -> SCH-FR-01 to SCH-FR-08
- AV-02 -> SCH-DR-01 to SCH-DR-08

---

## 4. Runtime View (AV-03)

### 4.1 Direct PostgreSQL Schema Create Flow

```
POST /gg-data/v1/schema/create
    │
    ▼
Permission check (manage_options)
    │
    ▼
Resolve connection config and detect provider type
    │
    ▼
Provider type = postgresql (PDO path)
    │
    ▼
Create WordPress-side tables (settings + sync metadata)
    │
    ▼
Begin transaction
    │
    ├─ create schema/table structures in dependency order
    ├─ install pgvector and pg_trgm extensions
    ├─ create search function and related tables
    └─ create vector and vocabulary support tables
    │
    ▼
Commit transaction
    │
    ▼
Persist schema version in settings
```

### 4.2 PostgREST/Supabase Schema Create and Verify Flow

```
POST /gg-data/v1/schema/create
    │
    ▼
Permission check (manage_options)
    │
    ▼
Provider type = postgrest
    │
    ├─ create WordPress-side tables only (settings + sync metadata)
    └─ return manual SQL instructions

GET /gg-data/v1/schema/sql
    │
    ▼
Return postgrest-schema.sql content + operator instructions

POST /gg-data/v1/schema/verify
    │
    ▼
Provider reads gg_schema_meta via PostgREST
    │
    ├─ schema found -> status=ready and metadata update
    └─ schema absent -> status=not_created
```

### 4.3 Upgrade and Status Flow

```
POST /gg-data/v1/schema/upgrade
    │
    ▼
Permission check + resolve connection
    │
    ▼
Compare stored schema version with plugin version
    │
    ├─ up to date -> upgraded=false
    └─ needs upgrade
         ├─ begin transaction
         ├─ run migration steps
         ├─ ensure pgvector extension
         ├─ commit
         └─ persist new schema version

GET /gg-data/v1/schema/status
    │
    ▼
Provider-aware status checks
    │
    ▼
Return compatibility payload fields for dashboard clients
```

Mapping:
- AV-03 -> SCH-FR-02 to SCH-FR-10
- AV-03 -> SCH-OR-01 to SCH-OR-06
- AV-03 -> SCH-QR-01 to SCH-QR-05

---

## 5. Architectural Decisions (ADRs)

### AD-01: Provider-Aware Schema Lifecycle Routing

Decision:
- Use a single schema manager contract with runtime routing per provider type.

Rationale:
- Preserves one operational API while honoring provider capability differences.
- Avoids duplicating schema lifecycle logic across separate subsystems.

Consequences:
- Route and status contracts must remain consistent despite divergent internals.
- Provider branching becomes a primary maintenance concern.

Linked requirements:
- AD-01 -> SCH-FR-01, SCH-FR-02, SCH-QR-01

### AD-02: Manual SQL Execution for PostgREST/Supabase Setup

Decision:
- Do not execute arbitrary schema SQL over PostgREST routes; provide SQL handoff and verification instead.

Rationale:
- Aligns with provider security constraints and least-privilege operational model.
- Keeps setup explicit for operators with database admin access.

Consequences:
- Setup requires manual step and clear operator guidance.
- Verification endpoint becomes mandatory for readiness confirmation.

Linked requirements:
- AD-02 -> SCH-FR-04, SCH-FR-08, SCH-OR-06, SCH-QR-04

### AD-03: Per-Connection Version Tracking in Settings Table

Decision:
- Persist schema version per connection in `wp_gg_data_settings` category `schema`.

Rationale:
- Supports multi-connection operation and upgrade targeting.
- Decouples version state from deprecated single-option storage.

Consequences:
- Migration fallback from legacy option keys is needed.
- Version state consistency is critical for upgrade decision accuracy.

Linked requirements:
- AD-03 -> SCH-FR-05, SCH-FR-06, SCH-DR-03, SCH-DR-04, SCH-QR-05

### AD-04: Transactional Create/Upgrade on Direct PostgreSQL

Decision:
- Execute create/upgrade operations inside database transactions on PDO path.

Rationale:
- Reduces partial-schema failure modes.
- Improves operational reliability for multi-step migrations.

Consequences:
- Failures require explicit rollback and actionable operator feedback.
- Transaction support assumptions apply to direct PostgreSQL path only.

Linked requirements:
- AD-04 -> SCH-OR-02, SCH-OR-03, SCH-FR-09

### AD-05: Compatibility-First Status Payloads

Decision:
- Preserve legacy-compatible status field names alongside canonical schema fields.

Rationale:
- Prevents dashboard regressions during documentation and architecture transitions.
- Supports incremental client modernization.

Consequences:
- Payload schema remains broader than strict minimum.
- Contract evolution must account for backward compatibility.

Linked requirements:
- AD-05 -> SCH-DR-01, SCH-DR-02, SCH-QR-03

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Impact |
|---|---|---|
| C-01 | PostgREST setup cannot rely on runtime arbitrary SQL execution through plugin routes. | Requires manual SQL workflow and operator guidance. |
| C-02 | Direct PostgreSQL extension enablement requires both privileges and server-side extension availability (for example pgvector binaries/control files). | Setup may fail until DBA grants required permissions and the PostgreSQL host/container image includes required extension packages. |
| C-03 | Schema status fields are consumed by existing dashboard clients. | Contract-breaking payload changes can regress UI workflows. |
| C-04 | Multi-connection deployments rely on accurate per-connection version metadata. | Version drift can trigger false upgrade prompts or skipped migrations. |

### 6.2 Risks

| Risk ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Provider-path behavior diverges in status semantics over time | Medium | High | Keep unified status contract and parity regression checks |
| R-02 | Manual SQL step is skipped or partially applied in Supabase setup | Medium | High | Enforce verify workflow and explicit setup instructions |
| R-03 | Extension install fails in managed PostgreSQL environments | Medium | Medium | Return actionable errors and document DBA escalation path |
| R-04 | Legacy version key migration is incomplete for existing installations | Low | Medium | Keep fallback read and migrate-on-read behavior |
| R-05 | Route authorization bypass attempts on schema endpoints | Low | High | Keep strict `manage_options` permission callback on all schema routes |

---

## 7. Coverage Mapping

| Architecture Item | SRS IDs Covered |
|---|---|
| AV-01 (Context View) | SCH-FR-01 to SCH-FR-10, SCH-OR-01 to SCH-OR-06 |
| AV-02 (Component View) | SCH-FR-01 to SCH-FR-08, SCH-DR-01 to SCH-DR-08 |
| AV-03 (Runtime View) | SCH-FR-02 to SCH-FR-10, SCH-OR-01 to SCH-OR-06, SCH-QR-01 to SCH-QR-05 |
| AD-01 | SCH-FR-01, SCH-FR-02, SCH-QR-01 |
| AD-02 | SCH-FR-04, SCH-FR-08, SCH-OR-06, SCH-QR-04 |
| AD-03 | SCH-FR-05, SCH-FR-06, SCH-DR-03, SCH-DR-04, SCH-QR-05 |
| AD-04 | SCH-OR-02, SCH-OR-03, SCH-FR-09 |
| AD-05 | SCH-DR-01, SCH-DR-02, SCH-QR-03 |
