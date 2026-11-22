# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Schema Management Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data schema subsystem must do to create, verify, version, and upgrade data-layer contracts used by sync, search, and vector features.

### 1.2 System Scope

The subsystem includes provider-aware schema lifecycle orchestration, per-connection schema version tracking, required extension management, schema status diagnostics, and administrative schema REST endpoints.

Software identifier: gregius-data-schema

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The schema subsystem sits between connection/provider configuration and feature subsystems that require table/function contracts. It coordinates both WordPress-side tables and PostgreSQL-side structures, with distinct behavior for direct PostgreSQL and PostgREST connections.

#### 1.3.2 System Functions Summary

- Create required schema structures for a selected connection.
- Maintain per-connection schema version state.
- Provide provider-aware schema status and readiness signals.
- Upgrade legacy schema versions to the required schema version where supported.
- Install required PostgreSQL extensions on direct PostgreSQL connections.
- Provide Supabase/PostgREST manual SQL handoff and verification workflow.
- Expose admin-only schema management endpoints.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | Dashboard schema setup and `/wp-json/gregius-data/v1/schema/*` endpoints |
| Plugin Developer or Integrator | Technical | Schema manager APIs, provider contracts, and migration behavior |
| Operations | Technical/Operational | Schema status diagnostics and upgrade workflows |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Schema manager | Service that creates, upgrades, and validates schema state per connection |
| Schema version | Version value persisted per connection for migration and compatibility checks |
| Provider-aware routing | Runtime branch that chooses direct PostgreSQL or PostgREST-compatible schema path |
| Supabase manual SQL flow | Setup path where SQL must be applied manually via SQL editor and then verified |
| Settings category schema | `wp_gregius_data_settings` category used to store version metadata |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| REST | Representational State Transfer |
| PDO | PHP Data Objects |
| RLS | Row Level Security |
| SQL | Structured Query Language |

## 2. References

- docs/schema/architecture.md
- includes/class-gregius-data-schema-manager.php
- includes/api/class-gregius-data-rest-schema-controller.php
- includes/class-gregius-data-connection-manager.php
- includes/class-gregius-data-settings-manager.php
- includes/providers/interface-gregius-data-db-provider.php
- includes/providers/class-gregius-data-postgresql-provider.php
- includes/providers/class-gregius-data-postgrest-provider.php
- includes/sql/postgrest-schema.sql

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| SCH-FR-01 | The software MUST provide schema create, status, and upgrade orchestration per named connection. | Must |
| SCH-FR-02 | The software MUST branch schema lifecycle behavior by connection provider type. | Must |
| SCH-FR-03 | For direct PostgreSQL connections, the software MUST create required WordPress mirror and subsystem tables in dependency-safe order. | Must |
| SCH-FR-04 | For PostgREST/Supabase connections, the software MUST provide a manual SQL setup flow and verification endpoint. | Must |
| SCH-FR-05 | The software MUST persist schema version metadata per connection in settings storage. | Must |
| SCH-FR-06 | The software MUST support fallback migration of legacy schema version values from `wp_options` to settings storage. | Must |
| SCH-FR-07 | The software MUST provide schema status payloads indicating existence, completeness, and update requirement. | Must |
| SCH-FR-08 | The software MUST expose SQL download content for manual PostgREST schema setup when connection type supports it. | Must |
| SCH-FR-09 | The software SHOULD provide idempotent create and upgrade behavior that does not fail when schema is already current. | Should |
| SCH-FR-10 | The software MUST provide structured error payloads for connection-not-found, invalid-connection-type, and runtime failures. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| SCH-DR-01 | Schema status responses MUST include `connected`, `connection_name`, `schema_version`, and `plugin_version` fields. | Must |
| SCH-DR-02 | Schema status responses MUST include compatibility aliases (`version`, `vector_extension`) required by current clients. | Must |
| SCH-DR-03 | Schema version contract MUST default to `0.0.0` when no schema metadata exists. | Must |
| SCH-DR-04 | Per-connection schema version state MUST be stored under settings category `schema` with key `version`. | Must |
| SCH-DR-05 | Supabase verification responses MUST include machine-actionable status values (`ready`, `not_created`). | Must |
| SCH-DR-06 | SQL download responses MUST include SQL payload, filename, and operator instructions for manual execution. | Must |
| SCH-DR-07 | Schema route request contract MUST accept optional `connection` with default `default`, except operations that require explicit targeting. | Must |
| SCH-DR-08 | Upgrade responses MUST report whether a migration was applied and the before/after version values. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| SCH-OR-01 | Schema management REST endpoints MUST enforce administrative authorization checks (`manage_options`). | Must |
| SCH-OR-02 | Direct PostgreSQL schema creation MUST execute core table and extension setup atomically via transaction where supported. | Must |
| SCH-OR-03 | Upgrade execution MUST rollback transaction state on migration errors where supported. | Must |
| SCH-OR-04 | Extension installation failures MUST return operator-actionable error messages for permission and availability cases. | Must |
| SCH-OR-05 | The subsystem SHOULD log schema lifecycle milestones and failures with connection context for diagnosis. | Should |
| SCH-OR-06 | The subsystem MUST avoid exposing schema create SQL execution over PostgREST runtime routes for security parity with provider constraints. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| SCH-QR-01 | Schema status behavior SHOULD remain parity-aligned across direct PostgreSQL and PostgREST paths where fields overlap. | Cross-provider contract checks |
| SCH-QR-02 | Schema lifecycle operations MUST remain deterministic for identical connection state and plugin version. | Integration scenario replay |
| SCH-QR-03 | Administrative route responses SHOULD be suitable for dashboard automation with stable field names. | Frontend contract validation |
| SCH-QR-04 | Manual SQL handoff flow SHOULD minimize operator ambiguity by returning explicit step-by-step instructions. | UX and payload review |
| SCH-QR-05 | Version tracking and compatibility checks MUST correctly identify when schema upgrades are required. | Version comparison tests |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Route and service behavior validation | REST tests + schema manager integration checks |
| Data and contract requirements | Payload and settings state inspection | API payload review + settings table checks |
| Operations/security requirements | Permission and failure-mode testing | Auth checks + connection/migration failure simulations |
| Quality requirements | Parity and versioning validation | Cross-provider comparisons + migration scenario runs |

Acceptance baseline for migration phase:
- Canonical schema package requirements reflect current schema manager and REST controller behavior.
- Provider-path differences are explicit without masking implementation constraints.
- Legacy monolithic schema narrative is superseded by this package.

## 5. Traceability

| SRS Requirement ID | Source (file:line) | Notes |
|---|---|---|
| SCH-FR-01 | includes/class-gregius-data-schema-manager.php:186, :518, :606 | Create, upgrade, and status orchestration per connection |
| SCH-FR-02 | includes/class-gregius-data-schema-manager.php:200-205, :621-626 | Provider-type routing for create and status |
| SCH-FR-03 | includes/class-gregius-data-schema-manager.php:233-278 | Direct PostgreSQL table creation in dependency order |
| SCH-FR-04 | includes/class-gregius-data-schema-manager.php:331, includes/api/class-gregius-data-rest-schema-controller.php:308 | PostgREST manual SQL flow and verify endpoint |
| SCH-FR-05 | includes/class-gregius-data-schema-manager.php:78, :106 | Per-connection schema version read/persist |
| SCH-FR-06 | includes/class-gregius-data-schema-manager.php:86-94 | Legacy `wp_options` fallback migration on read |
| SCH-FR-07 | includes/class-gregius-data-schema-manager.php:386, :606 | Status payloads (existence, completeness, update) |
| SCH-FR-08 | includes/api/class-gregius-data-rest-schema-controller.php:437 | SQL download endpoint for manual PostgREST setup |
| SCH-FR-09 | includes/class-gregius-data-schema-manager.php:538 | Idempotent upgrade (already-current short-circuit) |
| SCH-FR-10 | includes/api/class-gregius-data-rest-schema-controller.php:166-205, :214-252, :330, :459 | Structured error payloads |
| SCH-DR-01 | includes/class-gregius-data-schema-manager.php:697-715 | `connected` / `connection_name` / `schema_version` / `plugin_version` |
| SCH-DR-02 | includes/class-gregius-data-schema-manager.php:706, :709 | Compatibility aliases `version`, `vector_extension` |
| SCH-DR-03 | includes/class-gregius-data-schema-manager.php:96, :173, :176 | Default `0.0.0` when no metadata |
| SCH-DR-04 | includes/class-gregius-data-schema-manager.php:80, :108 | Category `schema`, key `version` |
| SCH-DR-05 | includes/api/class-gregius-data-rest-schema-controller.php:351-417 | `ready` / `not_created` status values |
| SCH-DR-06 | includes/api/class-gregius-data-rest-schema-controller.php:508-524 | SQL payload, filename, instructions |
| SCH-DR-07 | includes/api/class-gregius-data-rest-schema-controller.php:74-80 | Optional `connection` default `default` |
| SCH-DR-08 | includes/class-gregius-data-schema-manager.php:566-578 | `upgraded` + before/after version values |
| SCH-OR-01 | includes/api/class-gregius-data-rest-schema-controller.php:541-543 | `manage_options` permission callback |
| SCH-OR-02 | includes/class-gregius-data-schema-manager.php:233, :285 | Transactional create |
| SCH-OR-03 | includes/class-gregius-data-schema-manager.php:579-583 | Rollback on migration error |
| SCH-OR-04 | includes/class-gregius-data-schema-manager.php:792-804, :838-850 | Actionable extension error messages |
| SCH-OR-05 | includes/class-gregius-data-schema-manager.php:225, :286 | Lifecycle logging with connection context |
| SCH-OR-06 | includes/class-gregius-data-schema-manager.php:348-355 | No arbitrary SQL over PostgREST routes |
| SCH-QR-01 | docs/schema/architecture.md (AD-01, AD-05), includes/class-gregius-data-schema-manager.php:386, :606 | Provider parity |
| SCH-QR-02 | docs/schema/architecture.md (AD-04), includes/class-gregius-data-schema-manager.php:518 | Deterministic lifecycle |
| SCH-QR-03 | docs/schema/architecture.md (AD-05), includes/api/class-gregius-data-rest-schema-controller.php:64-158 | Stable dashboard payload fields |
| SCH-QR-04 | docs/schema/architecture.md (AD-02), includes/api/class-gregius-data-rest-schema-controller.php:508-524 | Explicit operator instructions |
| SCH-QR-05 | includes/class-gregius-data-schema-manager.php:735-746 | Version-comparison upgrade detection |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Schema operations depend on valid connection configuration and provider factory behavior.
- Direct PostgreSQL extension installation requires sufficient DB permissions and server-side extension availability (for example pgvector control files on the PostgreSQL host/container).
- PostgREST/Supabase setups require operator access to SQL editor for manual schema application.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| SCH-TBD-01 | 3.4 | Explicit SLO targets for schema status and migration runtime are not yet codified. | Product and engineering | TBD |
| SCH-TBD-02 | 3.2 | Formal response fixtures for all schema routes should be consolidated in developer docs tests. | Engineering | TBD |
| SCH-TBD-03 | 3.1 | PostgREST schema upgrade automation strategy remains intentionally deferred to manual SQL workflow. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Keep provider-aware schema lifecycle in one subsystem package | Reflects current code and avoids split-contract drift | Product and engineering direction |
| 2026-04-04 | Preserve manual PostgREST SQL setup flow as explicit requirement | Current provider constraints prohibit arbitrary SQL execution through REST runtime | Documentation migration decision |
