# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Updates and Schema Versioning Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| SyRS Reference | N/A - no standalone system-level updates package |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data updates subsystem must do to detect plugin-version changes, track per-connection schema versions, and execute schema create/upgrade workflows safely.

### 1.2 System Scope

The updates subsystem includes:
- plugin version checks through activation and admin-init upgrade gating,
- per-connection schema version storage and migration fallback,
- schema create/status/upgrade orchestration paths,
- provider-aware schema handling for direct PostgreSQL and PostgREST flows,
- REST control-plane routes for schema lifecycle operations,
- transaction-safe upgrade behavior and operator-facing responses.

Software identifier: gregius-data-updates

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

This subsystem bridges WordPress plugin lifecycle events and connection-scoped PostgreSQL schema lifecycle operations. It coordinates upgrade checks at plugin level and schema readiness/upgrade execution at connection level.

#### 1.3.2 System Functions Summary

- Compare installed plugin version option against code version and run upgrade dispatcher when needed.
- Persist plugin version in WordPress options.
- Retrieve and persist schema version per connection in categorized settings.
- Migrate legacy schema-version options into the settings table format.
- Expose schema status/create/upgrade/verify/sql routes under `gregius-data/v1/schema`.
- Run connection-scoped schema upgrades with transactional rollback on failures.
- Signal update availability when schema version lags the required schema version (`GREGIUS_DATA_SCHEMA_VERSION`).

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | Dashboard update prompts and schema REST endpoints |
| Plugin Developer | Technical | Activator and schema manager upgrade contracts |
| Operations | Technical | Version diagnostics and schema upgrade workflows |

## 2. References

- `docs/updates/architecture.md`
- `docs/updates/developer-documentation.md`
- `docs/decisions/0001-schema-versioning-and-provider-parity.md`
- `gregius-data.php`
- `includes/class-gregius-data-activator.php`
- `includes/class-gregius-data-schema-manager.php`
- `includes/api/class-gregius-data-rest-schema-controller.php`
- `includes/class-gregius-data-settings-manager.php`
- `includes/providers/class-gregius-data-postgrest-provider.php`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| UPD-FR-01 | The software MUST register lifecycle hooks that trigger activation, deactivation, and version-check behaviors for updates control flow. | Must |
| UPD-FR-02 | The software MUST compare installed plugin version (`gregius_data_db_version`) against runtime version and execute upgrade dispatcher logic when the installed version is older. | Must |
| UPD-FR-03 | The software MUST persist plugin version state in WordPress options after upgrade dispatcher completion. | Must |
| UPD-FR-04 | The software MUST provide per-connection schema-version retrieval and update APIs. | Must |
| UPD-FR-05 | The software MUST support legacy schema-version fallback (`gregius_data_schema_version_<connection>`) and migrate it into current settings storage when encountered. | Must |
| UPD-FR-06 | The software MUST expose schema status/create/upgrade REST routes under `gregius-data/v1/schema`. | Must |
| UPD-FR-07 | The software MUST expose Supabase/PostgREST-specific verify and SQL download routes under `gregius-data/v1/schema`. | Must |
| UPD-FR-08 | The software MUST report schema update availability when schema version is lower than the required schema version (`GREGIUS_DATA_SCHEMA_VERSION`) for a connection. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| UPD-DR-01 | Plugin version state MUST be stored in `gregius_data_db_version`. | Must |
| UPD-DR-02 | Schema version state MUST be stored in settings category `schema` with key `version` per connection name. | Must |
| UPD-DR-03 | Schema version updates MUST also persist an `updated_at` timestamp in settings category `schema` for the same connection. | Must |
| UPD-DR-04 | Schema status responses MUST include compatibility fields for frontend consumption including `schema_version`, `required_schema_version`, `schema_status`, `plugin_version`, and update flags. | Must |
| UPD-DR-05 | Schema create and upgrade REST responses MUST include `success`, user-facing `message`, and operation data or error payloads. | Must |
| UPD-DR-06 | Supabase verify responses MUST indicate schema state (`ready`, `not_created`, `outdated`, or `newer`) and current version data. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| UPD-OR-01 | Schema create and schema upgrade operations on direct PostgreSQL paths MUST execute within transactions and roll back on errors. | Must |
| UPD-OR-02 | Schema upgrade routines MUST be idempotent and safe to re-run when the schema is already current. | Must |
| UPD-OR-03 | Schema upgrade routines MUST return an explicit non-upgraded success state when current schema version is already at or above target version. | Must |
| UPD-OR-04 | Schema management REST routes MUST require administrative permission checks. | Must |
| UPD-OR-05 | Supabase/PostgREST schema setup MUST avoid arbitrary SQL execution through REST and instead return manual SQL instructions and verification paths. | Must |
| UPD-OR-06 | Upgrade and schema-version operations MUST emit logging events on success and error paths. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| UPD-QR-01 | Version-check and schema-status contracts MUST remain deterministic across plugin restarts and admin page loads. | Behavioral inspection |
| UPD-QR-02 | Upgrade failures MUST fail safely with rollback and explicit error messages suitable for troubleshooting. | Failure-path inspection |
| UPD-QR-03 | Connection-scoped schema state MUST support multiple configured connections without cross-connection overwrite. | Multi-connection state inspection |
| UPD-QR-04 | Provider-specific setup behavior MUST preserve a consistent status contract for frontend clients. | Cross-provider response inspection |
| UPD-QR-05 | Backward compatibility MUST be preserved for legacy schema-version option values via migration fallback. | Legacy fallback test |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Lifecycle + route execution checks | Hook and REST invocation checks |
| Data/contract requirements | Option/settings/response inspection | Options table + settings API + payload inspection |
| Operations requirements | Upgrade/create/verify control checks | Direct and provider-specific flow inspection |
| Quality requirements | Error path and compatibility checks | Rollback simulation + response contract review |

Acceptance baseline:
- Plugin version checks and schema update checks operate as documented.
- Per-connection schema versions are persisted and read correctly.
- REST control-plane operations enforce permissions and return stable payload contracts.
- Upgrade operations preserve transactional safety and idempotent behavior.

## 5. Traceability

| SRS Requirement ID | Source Reference (file:line) | Notes |
|---|---|---|
| UPD-FR-01 | `gregius-data.php:199-203` | Activation/deactivation hooks and admin-init version-check callback |
| UPD-FR-02 | `includes/class-gregius-data-activator.php:939-954` | Installed-vs-runtime version comparison and upgrade dispatcher |
| UPD-FR-03 | `includes/class-gregius-data-activator.php:975-979` | Plugin version option persistence |
| UPD-DR-01 | `includes/class-gregius-data-activator.php:23` | `gregius_data_db_version` option |
| UPD-FR-04 | `includes/class-gregius-data-schema-manager.php:78-119` | Per-connection schema version get/set APIs |
| UPD-FR-05 | `includes/class-gregius-data-schema-manager.php:86-94` | Legacy option fallback migration |
| UPD-DR-02 | `includes/class-gregius-data-schema-manager.php:80,108` | Settings `schema`/`version` storage |
| UPD-DR-03 | `includes/class-gregius-data-schema-manager.php:111-113` | `updated_at` timestamp persistence |
| UPD-QR-05 | `includes/class-gregius-data-schema-manager.php:86-94` | Legacy fallback migration path |
| UPD-FR-06 | `includes/api/class-gregius-data-rest-schema-controller.php:67-121` | status/create/upgrade route registration |
| UPD-FR-07 | `includes/api/class-gregius-data-rest-schema-controller.php:123-157` | verify/sql route registration |
| UPD-DR-04 | `includes/class-gregius-data-schema-manager.php:697-715` | Status response fields |
| UPD-DR-05 | `includes/api/class-gregius-data-rest-schema-controller.php:225-244,271-289` | Create/upgrade response contracts |
| UPD-DR-06 | `includes/api/class-gregius-data-rest-schema-controller.php:350-419` | Verify status/version responses |
| UPD-OR-01 | `includes/class-gregius-data-schema-manager.php:233,299-303,549,579-583` | Transaction boundary and rollback |
| UPD-OR-02 | `includes/class-gregius-data-schema-manager.php:538-598` | Idempotent migration path |
| UPD-OR-03 | `includes/class-gregius-data-schema-manager.php:538-546` | Non-upgraded success state (`upgraded => false`) |
| UPD-OR-04 | `includes/api/class-gregius-data-rest-schema-controller.php:541-543` | `manage_options` permission gate |
| UPD-OR-05 | `includes/class-gregius-data-schema-manager.php:331-377`, `includes/api/class-gregius-data-rest-schema-controller.php:437-525` | Manual SQL + verify handoff |
| UPD-OR-06 | `includes/class-gregius-data-schema-manager.php:115,310,590` | Logging on success/error paths |
| UPD-FR-08 | `includes/class-gregius-data-schema-manager.php:693-695` | Update availability flags against schema version |
| UPD-QR-01 | `includes/class-gregius-data-schema-manager.php:735-746` | Deterministic status enum |
| UPD-QR-02 | `includes/class-gregius-data-schema-manager.php:579-597` | Failure-path rollback and error payload |
| UPD-QR-03 | `includes/class-gregius-data-schema-manager.php:78-119` | Connection-scoped version state |
| UPD-QR-04 | `includes/class-gregius-data-schema-manager.php:386-510,606-724` | Cross-provider status contract |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Dashboard update banners and actions depend on schema status/upgrade route contracts.
- Lifecycle initialization and schema update orchestration are related but documented under distinct subsystem ownership.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| UPD-TBD-01 | 3.3 | Versioned incremental migration ladders beyond idempotent v1.0 routines are not yet implemented. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Split updates docs into canonical SRS/architecture/developer package | Align with plugin-wide ISO canonical structure and retire monolithic architecture docs | Documentation migration initiative |
