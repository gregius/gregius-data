# Schema Management Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document explains how to operate and extend the Gregius Data schema subsystem.

Audience:
- Core contributors maintaining schema lifecycle orchestration
- Integrators configuring provider-specific setup flows
- Engineers diagnosing schema readiness and upgrade behavior

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Schema manager responsibilities and lifecycle methods
- Schema REST route catalog and payload behavior
- Provider-specific setup paths (PostgreSQL vs PostgREST)
- Version tracking and migration behavior
- Error handling and troubleshooting for schema operations

Not covered:
- Full sync pipeline behavior and content movement semantics
- Search or vector algorithm internals
- Non-schema dashboard UX design details

## 3. Key Components

### 3.1 Schema Manager (`GG_Data_Schema_Manager`)

Source: [../../includes/class-gg-data-schema-manager.php](../../includes/class-gg-data-schema-manager.php)

Core responsibilities:
- Create all required schema structures for a target connection.
- Route schema creation/status by provider type.
- Track and update per-connection schema version.
- Execute schema upgrades and migration steps.
- Install required PostgreSQL extensions for direct connections.

Notable behavior:
- Reads provider type from connection config via settings manager.
- Migrates legacy version key from `wp_options` to `wp_gg_data_settings`.
- Uses transaction boundaries for direct PostgreSQL create and upgrade operations.

### 3.2 Schema REST Controller (`GG_Data_REST_Schema_Controller`)

Source: [../../includes/api/class-gg-data-rest-schema-controller.php](../../includes/api/class-gg-data-rest-schema-controller.php)

Core responsibilities:
- Register `/schema/status`, `/schema/create`, `/schema/upgrade`, `/schema/verify`, and `/schema/sql`.
- Validate connection existence and type before provider-specific operations.
- Return machine-actionable payloads for dashboard flows.
- Enforce admin permissions for all schema routes.

### 3.3 Connection and Settings Services

Sources:
- [../../includes/class-gg-data-connection-manager.php](../../includes/class-gg-data-connection-manager.php)
- [../../includes/class-gg-data-settings-manager.php](../../includes/class-gg-data-settings-manager.php)

Core responsibilities:
- Store/retrieve connection config and schema metadata.
- Provide provider instances used by schema status checks.
- Persist metadata updates on successful verification.

### 3.4 Provider Interfaces and Implementations

Sources:
- [../../includes/providers/interface-gg-db-provider.php](../../includes/providers/interface-gg-db-provider.php)
- [../../includes/providers/class-gg-postgresql-provider.php](../../includes/providers/class-gg-postgresql-provider.php)
- [../../includes/providers/class-gg-postgrest-provider.php](../../includes/providers/class-gg-postgrest-provider.php)

Schema-related behavior:
- Direct PostgreSQL path uses PDO for schema SQL and extension setup.
- PostgREST path uses REST reads for schema verification and status.

### 3.5 PostgREST Manual SQL Artifact

Source: [../../includes/sql/postgrest-schema.sql](../../includes/sql/postgrest-schema.sql)

Role:
- Canonical SQL applied manually via Supabase/PostgREST SQL editor for schema setup.

## 4. REST Route Catalog (Schema)

Base namespace: `/wp-json/gg-data/v1`

Routes:
- `GET /schema/status`
- `POST /schema/create`
- `POST /schema/upgrade`
- `POST /schema/verify`
- `GET /schema/sql`

Common request argument:
- `connection` (string)
- Default: `default` for status/create/upgrade
- Required for verify/sql

Permissions:
- All routes require admin authorization (`manage_options`).

## 5. Provider-Specific Lifecycle Behavior

### 5.1 Direct PostgreSQL (`type=postgresql`)

Create flow highlights:
- Creates WordPress-side settings and sync metadata tables.
- Starts transaction for PostgreSQL-side schema creation.
- Attempts to install `vector` and `pg_trgm` extensions via `CREATE EXTENSION IF NOT EXISTS ...`.
- Creates core mirror/search/vector-support tables in dependency order.
- Ensures `wp_posts_clean.metadata_manifest` JSONB column exists with object default (`'{}'::jsonb`).
- Ensures GIN index exists for deterministic metadata containment filters.
- Commits transaction and stores connection schema version.

Important prerequisite:
- The direct PostgreSQL host or container must already include extension binaries/control files (for example `vector.control` for pgvector).
- The schema create route can enable available extensions, but it cannot install missing OS-level/database-server packages on the PostgreSQL host.

Upgrade flow highlights:
- Compares stored schema version with plugin version.
- Runs migration methods inside transaction.
- Ensures required extension availability.
- Commits and updates version state.

Status flow highlights:
- Checks table existence and extension presence.
- Returns completeness and upgrade flags.

### 5.2 PostgREST/Supabase (`type=postgrest`)

Create flow highlights:
- Creates WordPress-side settings and sync metadata tables only.
- Returns instruction payload indicating manual SQL execution requirement.

SQL download flow highlights:
- Returns SQL file content from `includes/sql/postgrest-schema.sql`.
- Returns step-by-step operator instructions and dashboard SQL URL when possible.
- Includes `metadata_manifest` JSONB schema and `search_rag_orchestrate(..., metadata_filter jsonb DEFAULT '{}'::jsonb)` contract updates.

Verify flow highlights:
- Reads schema version from `gg_schema_meta` via PostgREST provider.
- On success updates connection metadata with `schema_version` and `schema_status=ready`.
- On missing schema returns status `not_created`.

## 6. Schema Version and Status Contracts

Schema version storage:
- Category: `schema`
- Keys used by manager: `version`, `updated_at`
- Legacy fallback key: `gg_data_schema_version_{connection}` in `wp_options`

Status response fields commonly consumed by clients:
- `connected`
- `connection_name`
- `complete`
- `schema_exists`
- `tables.complete`
- `tables.missing`
- `vector_extension`
- `pg_trgm_extension`
- `schema_version`
- `version` (compatibility alias)
- `plugin_version`
- `requires_update`
- `update_available`

Compatibility note:
- Keep both `schema_version` and `version` to avoid breaking existing dashboard consumers.
- Preserve metadata-filter object-default semantics in SQL function signatures to prevent no-filter containment regressions.

## 7. Extension Points and Safe Customization

### 7.1 Add Provider-Aware Schema Behavior

Recommended approach:
- Extend provider implementations while preserving schema manager contract shape.
- Keep route payloads and field names stable for frontend compatibility.
- Preserve create/status/upgrade semantics for connection-scoped operations.

### 7.2 Add New Migration Steps

Recommended approach:
- Add migration calls in `upgrade_schema_to_latest()` in deterministic order.
- Keep transaction boundaries intact for atomic migration.
- Update version state only after successful commit.

### 7.3 Evolve PostgREST SQL Artifact

Recommended approach:
- Update `includes/sql/postgrest-schema.sql` when schema contracts change.
- Ensure `/schema/sql` payload and instructions remain accurate.
- Keep `/schema/verify` logic aligned with expected metadata table state.

## 8. Integration Notes

### 8.1 Upstream Dependencies

- Connection configuration stored in settings manager.
- Provider factory behavior used by connection manager.
- WordPress capability model (`manage_options`) for route authorization.

### 8.2 Downstream Dependencies

- Sync subsystem expects mirror/sync tables and versioned readiness.
- Search subsystem expects extensions and function/table contracts.
- Vector subsystem expects vector-support tables and compatible schema state.

### 8.3 Operational Logging

- Schema manager logs lifecycle milestones and errors with connection context.
- Use plugin logs to diagnose extension-permission and connection failures.

## 9. Troubleshooting

### 9.1 Connection Not Found Errors

Checklist:
- Verify connection exists in settings storage.
- Verify request `connection` argument matches stored connection key.
- Confirm route caller is targeting the intended environment connection.

### 9.2 pgvector or pg_trgm Installation Fails

Checklist:
- Confirm DB user has extension install privileges.
- Confirm extension is available on the PostgreSQL host.
- Confirm the PostgreSQL server image/package includes extension control files (for example `vector.control` for pgvector).
- If `CREATE EXTENSION` fails with `could not open extension control file`, install pgvector on the DB host/container image first, then re-run schema create.
- Escalate to DBA for managed-host environments with restricted extension policy.

### 9.3 Schema Status Shows Not Complete

Checklist:
- Re-run create flow for direct PostgreSQL connections.
- Confirm required tables exist under expected prefix/schema.
- Verify schema version was persisted after successful create/upgrade.

### 9.4 Supabase Verify Reports Not Created

Checklist:
- Retrieve SQL via `GET /schema/sql`.
- Execute the returned SQL in Supabase SQL editor.
- Re-run `POST /schema/verify` for the same connection.

### 9.5 Upgrade Does Not Run

Checklist:
- Compare `schema_version` against `plugin_version` in status payload.
- Confirm route caller has `manage_options` capability.
- Check logs for migration exceptions that triggered rollback.

## 10. Traceability (SRS Mapping)

- Lifecycle orchestration and provider routing: [SRS: SCH-FR-01, SCH-FR-02, SCH-FR-03, SCH-FR-04]
- Versioning and status contracts: [SRS: SCH-FR-05, SCH-FR-06, SCH-FR-07, SCH-DR-01, SCH-DR-04]
- Route contracts and manual SQL workflow: [SRS: SCH-FR-08, SCH-DR-05, SCH-DR-06, SCH-DR-07]
- Security and operations behavior: [SRS: SCH-OR-01, SCH-OR-02, SCH-OR-03, SCH-OR-04, SCH-OR-06]
- Quality and compatibility guarantees: [SRS: SCH-QR-01, SCH-QR-03, SCH-QR-05]
