# Architecture Description: Updates and Schema Versioning Subsystem

Standard: ISO/IEC/IEEE 42010:2022
Component: Updates and Schema Versioning Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data detects plugin version drift and orchestrates per-connection schema lifecycle operations.

Scope includes:
- plugin activation and admin-init version-check orchestration,
- plugin version persistence,
- per-connection schema version tracking,
- provider-aware schema status/create/upgrade flows,
- schema REST control-plane operations,
- transactional safety and rollback for upgrade execution.

Explicitly excluded:
- full lifecycle deactivation/uninstall cleanup behavior,
- sync/vector/search runtime execution internals,
- non-schema feature migration logic.

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrators | Predictable upgrade prompts and successful schema updates | High |
| Developers | Stable versioning and migration orchestration contracts | High |
| Operations | Safe rollback behavior and actionable failure responses | High |
| Integrators | Consistent status contracts across connection provider types | High |

---

## 2. Context View (AV-01)

```
WordPress Plugin Lifecycle
        |
        +--> Activation/admin_init version checks
        |         |
        |         v
        |    GG_Data_Activator
        |         |
        |         v
        |    Plugin version option state (gg_data_db_version)
        |
Dashboard / REST Client
        |
        v
GG_Data_REST_Schema_Controller (gg-data/v1/schema/*)
        |
        v
GG_Data_Schema_Manager
        |
   +----+---------------------+
   |                          |
   v                          v
Direct PostgreSQL path      PostgREST/Supabase path
(transactional create/upgrade) (manual SQL + verify)
```

Rationale:
- Separate plugin-level version checks from connection-level schema version checks while keeping shared status/update semantics.

Mapping:
- AV-01 -> UPD-FR-01..08
- AV-01 -> UPD-OR-01..06

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. **Plugin Bootstrap (`gregius-data.php`)**
- Registers activation/deactivation hooks.
- Registers admin-init version check callback.
- Provides `GG_DATA_VERSION` constant consumed by upgrade/status comparisons.

2. **Activator (`GG_Data_Activator`)**
- Runs upgrade dispatcher during activation and version mismatch checks.
- Persists plugin version option (`gg_data_db_version`).
- Emits admin success notices after version updates.
- Executes guarded prompt-seeding/migration helper routines on admin-init.

3. **Schema Manager (`GG_Data_Schema_Manager`)**
- Retrieves and sets per-connection schema versions.
- Migrates legacy option-backed schema versions to settings-backed storage.
- Executes create and upgrade operations.
- Calculates schema status contracts and update flags.
- Applies provider-aware routing (direct PostgreSQL vs postgrest).

4. **Schema REST Controller (`GG_Data_REST_Schema_Controller`)**
- Registers `/schema/status`, `/schema/create`, `/schema/upgrade`, `/schema/verify`, `/schema/sql`.
- Enforces administrative permissions (`manage_options`).
- Translates manager outcomes into REST response/error contracts.

5. **Settings + Provider Dependencies**
- `GG_Data_Settings_Manager`: connection inventory and schema category persistence.
- `GG_Data_DB` and `GG_Data_Connection_Manager`: connection resolution.
- `GG_Data_PostgREST_Provider`: schema version retrieval for Supabase/PostgREST flow.

### 3.2 Responsibility Mapping

| Component | Primary Responsibilities | Requirement Links |
|---|---|---|
| Bootstrap | Hook registration and version-constant context | UPD-FR-01, UPD-FR-02 |
| Activator | Plugin version checks, upgrade dispatcher, option updates | UPD-FR-02, UPD-FR-03, UPD-DR-01 |
| Schema Manager | Per-connection versioning, upgrade orchestration, status model | UPD-FR-04, UPD-FR-05, UPD-FR-08, UPD-OR-01..03 |
| REST Schema Controller | Schema route contracts and permission enforcement | UPD-FR-06, UPD-FR-07, UPD-OR-04, UPD-OR-05 |
| Settings/Providers | Backing persistence and provider-specific status pathways | UPD-DR-02..06, UPD-QR-03..05 |

---

## 4. Runtime View (AV-03)

### 4.1 Plugin Update Detection Flow

```
Activation or admin_init
   -> GG_Data_Activator::check_version()
   -> read installed version (gg_data_db_version)
   -> compare against GG_Data_Activator::VERSION
   -> run_upgrades() when installed < runtime version
   -> update plugin version option
   -> emit success notice on version change
```

### 4.2 Direct PostgreSQL Schema Upgrade Flow

```
POST /gg-data/v1/schema/upgrade
   -> permission check (manage_options)
   -> Schema_Manager::upgrade_schema_to_latest(connection)
   -> read current schema version
   -> if current >= target: return upgraded=false
   -> begin transaction
   -> run idempotent migration methods
   -> ensure pgvector extension installed
   -> commit
   -> set per-connection schema version to target
   -> return success payload
```

### 4.3 Supabase/PostgREST Schema Flow

```
POST /gg-data/v1/schema/create
   -> postgrest path does not execute arbitrary schema SQL
   -> return manual SQL guidance and keep WordPress-side setup

GET /gg-data/v1/schema/sql
   -> return postgrest-schema.sql content + operator instructions

POST /gg-data/v1/schema/verify
   -> query provider schema version
   -> return ready/not_created status and version
```

### 4.4 Schema Status and Update Banner Flow

```
GET /gg-data/v1/schema/status
   -> resolve provider path
   -> compute schema completeness and schema_version
   -> compare with plugin_version
   -> return requires_update / update_available flags
```

Mapping:
- AV-03 -> UPD-FR-02..08
- AV-03 -> UPD-DR-04..06
- AV-03 -> UPD-OR-01..06
- AV-03 -> UPD-QR-01..04

---

## 5. Architectural Decisions (ADRs)

### AD-01: Dual-Layer Version Model

Decision:
- Maintain plugin version state separately from per-connection schema version state.

Alternatives considered:
- Single global version state for plugin and schema.

Rationale:
- Different connections can be upgraded at different times.
- Plugin updates should not force immediate schema mutation on all connections.

Consequences:
- UI and status endpoints must compare plugin and schema versions explicitly.

Linked requirements:
- AD-01 -> UPD-FR-02, UPD-FR-04, UPD-FR-08, UPD-DR-01, UPD-DR-02

### AD-02: Idempotent-First Migration Strategy

Decision:
- Use idempotent migration methods and safe re-execution patterns in upgrade flow.

Alternatives considered:
- Strict version-step ladders with irreversible scripts for each minor version.

Rationale:
- Reduces risk for skipped-version updates and repeated execution attempts.

Consequences:
- Advanced future migration semantics remain a separate enhancement path.

Linked requirements:
- AD-02 -> UPD-OR-02, UPD-OR-03, UPD-QR-01

### AD-03: Transactional Upgrade Boundary for Direct PostgreSQL

Decision:
- Wrap direct PostgreSQL upgrade operations in transactions with rollback on failure.

Alternatives considered:
- Non-transactional sequential DDL updates.

Rationale:
- Limits partial-upgrade states and improves failure recovery.

Consequences:
- Errors return explicit failure payloads and require operator remediation.

Linked requirements:
- AD-03 -> UPD-OR-01, UPD-QR-02

### AD-04: Provider-Aware Control Plane

Decision:
- Route behavior by provider type: execute SQL upgrades for direct PostgreSQL, provide manual SQL + verify flow for postgrest/supabase.

Alternatives considered:
- Uniform automatic SQL execution across all providers.

Rationale:
- PostgREST path does not support arbitrary SQL execution with equivalent operational guarantees.

Consequences:
- Operator workflow includes manual SQL execution for supabase/postgrest setup.

Linked requirements:
- AD-04 -> UPD-FR-07, UPD-OR-05, UPD-QR-04

### AD-05: Admin-Scoped Schema Mutation Surface

Decision:
- Gate schema control-plane routes with `manage_options` permission checks.

Alternatives considered:
- Lower capabilities or mixed route-level capability matrix.

Rationale:
- Schema operations can materially affect production data and connectivity.

Consequences:
- Non-admin operators require delegated administrative access.

Linked requirements:
- AD-05 -> UPD-OR-04

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Rationale |
|---|---|---|
| C-01 | Plugin update checks depend on WordPress activation/admin-init lifecycle callbacks. | Version checks are lifecycle-driven, not daemon-driven. |
| C-02 | Schema versions are connection-scoped and rely on settings manager persistence contracts. | Multi-connection safety depends on correct connection keys. |
| C-03 | Supabase/PostgREST path requires manual SQL execution. | Arbitrary SQL execution is intentionally not automated via REST. |

### 6.2 Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | Failed migrations can leave schema behind plugin version. | High | Transaction rollback, explicit error payloads, and retryable upgrade action. |
| R-02 | Misconfigured or missing connection entries block update operations. | Medium | Early connection validation and clear 404/400 response contracts. |
| R-03 | Legacy schema-version fallback may hide stale state if not migrated consistently. | Medium | Automatic migration to settings-backed storage during version read. |

---

## 7. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | UPD-FR-01..08, UPD-OR-01..06 |
| AV-02 | UPD-FR-02..08, UPD-DR-01..06, UPD-OR-04..06 |
| AV-03 | UPD-FR-02..08, UPD-DR-04..06, UPD-OR-01..06, UPD-QR-01..04 |
| AD-01 | UPD-FR-02, UPD-FR-04, UPD-FR-08, UPD-DR-01, UPD-DR-02 |
| AD-02 | UPD-OR-02, UPD-OR-03, UPD-QR-01 |
| AD-03 | UPD-OR-01, UPD-QR-02 |
| AD-04 | UPD-FR-07, UPD-OR-05, UPD-QR-04 |
| AD-05 | UPD-OR-04 |

---

## 8. Readiness Checklist

- Scope and exclusions are explicit.
- Update lifecycle and schema upgrade flows are represented.
- Provider-specific behaviors and control-plane routes are modeled.
- Decisions include rationale and consequences.
- Requirement-linked coverage mapping is present.
- Companion docs are available:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
