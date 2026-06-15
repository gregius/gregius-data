# Updates Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document describes how to use and maintain the Gregius Data updates and schema-versioning subsystem.

Audience:
- Plugin developers maintaining upgrade behavior
- Administrators managing connection-specific schema upgrades
- Integrators relying on schema status and update route contracts

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Plugin version-check behavior and option contracts
- Connection-scoped schema-version APIs
- Schema REST control-plane contracts
- Upgrade execution, rollback, and provider-aware behavior
- Maintenance recipes for adding migration steps safely

Not covered:
- Full lifecycle cleanup flows (see lifecycle package)
- Full schema object definitions (see schema package)

## 3. Quick Start

### 3.1 Validate Plugin and Schema Version State

```php
$installed_version = get_option( GG_Data_Activator::VERSION_OPTION, '0.0.0' );
$runtime_version   = GG_Data_Activator::VERSION;

$schema_manager = new GG_Data_Schema_Manager();
$schema_version = $schema_manager->get_schema_version( 'default' );
```

[SRS: UPD-FR-02, UPD-FR-04, UPD-DR-01, UPD-DR-02]

### 3.2 Trigger Schema Upgrade through REST

```bash
curl -X POST "https://example.com/wp-json/gg-data/v1/schema/upgrade?connection=default" \
  -H "Authorization: Bearer <token>"
```

[SRS: UPD-FR-06, UPD-OR-04]

### 3.3 Check Update Availability

```bash
curl -X GET "https://example.com/wp-json/gg-data/v1/schema/status?connection=default" \
  -H "Authorization: Bearer <token>"
```

Look for `requires_update` / `update_available` in the response payload.

[SRS: UPD-FR-08, UPD-DR-04]

## 4. Core API Reference

### 4.1 `GG_Data_Activator`

Source: `includes/class-gg-data-activator.php`

Constants:
- `VERSION`
- `VERSION_OPTION` (`gg_data_db_version`)

Key methods:
- `activate()`
- `check_version()`
- `run_upgrades()`

Behavior notes:
- `check_version()` compares stored plugin version with `VERSION` and runs upgrades when outdated.
- `run_upgrades()` updates the plugin version option and emits admin success notice when version changes.

[SRS: UPD-FR-02, UPD-FR-03, UPD-DR-01]

### 4.2 `GG_Data_Schema_Manager`

Source: `includes/class-gg-data-schema-manager.php`

Key methods:
- `get_schema_version( $connection_name )`
- `set_schema_version( $connection_name, $version )`
- `get_schema_status( $connection_name = 'default' )`
- `create_all_tables( $connection_name = 'default' )`
- `upgrade_schema_to_latest( $connection_name = 'default' )`

Upgrade flow internals:
- Reads `current_version` and compares to `GG_DATA_VERSION`.
- Returns early with `upgraded => false` when already current.
- Runs transaction-wrapped migration path:
  - `migrate_postmeta_table()`
  - `migrate_posts_table_for_sync()`
  - `migrate_taxonomy_tables()`
  - `install_pgvector_extension()`
- Commits and updates schema version on success.
- Rolls back and returns error payload on exceptions.

Legacy fallback:
- If `schema/version` is absent in settings, checks legacy option key `gg_data_schema_version_<connection>`.
- Migrates legacy value into settings and deletes legacy option.

[SRS: UPD-FR-04, UPD-FR-05, UPD-OR-01..03, UPD-QR-05]

## 5. REST API Contracts

Controller: `GG_Data_REST_Schema_Controller`  
Namespace: `gg-data/v1`  
Base route: `/schema`

Permission gate:
- `get_items_permissions_check()` returns `current_user_can( 'manage_options' )`.

[SRS: UPD-OR-04]

### 5.1 `GET /schema/status`

Purpose:
- Return provider-aware schema status, version values, and update flags.

Args:
- `connection` (optional, default `default`)

Key response fields:
- `connected`
- `connection_name`
- `schema_version`
- `plugin_version`
- `requires_update`
- `update_available`

[SRS: UPD-FR-06, UPD-FR-08, UPD-DR-04]

### 5.2 `POST /schema/create`

Purpose:
- Run schema create setup for selected connection.

Args:
- `connection` (optional, default `default`)

Behavior:
- Direct PostgreSQL path: runs manager create flow.
- PostgREST path: creates WordPress-side tables and returns manual SQL guidance.

[SRS: UPD-FR-06, UPD-OR-05]

### 5.3 `POST /schema/upgrade`

Purpose:
- Upgrade selected connection schema to latest target version.

Args:
- `connection` (optional, default `default`)

Behavior:
- Delegates to `upgrade_schema_to_latest()` and returns success or WP_Error payload.

[SRS: UPD-FR-06, UPD-OR-01..03]

### 5.4 `POST /schema/verify` (Supabase/PostgREST)

Purpose:
- Verify schema existence and version via provider path.

Args:
- `connection` (required)

Behavior:
- Only accepts connection types `postgrest` or `supabase`.
- Returns `status: ready` when schema version is found.
- Returns `status: not_created` when schema is missing.

[SRS: UPD-FR-07, UPD-DR-06]

### 5.5 `GET /schema/sql` (Supabase/PostgREST)

Purpose:
- Return SQL file content (`postgrest-schema.sql`) and guided instructions for manual execution.

Args:
- `connection` (required)

Behavior:
- Validates connection and type.
- Returns SQL content, filename, and dashboard URL instructions when available.

[SRS: UPD-FR-07, UPD-OR-05]

## 6. Data and Option Contracts

### 6.1 WordPress Options

- `gg_data_db_version` - plugin-version state used by activator upgrade checks.

Related guarded migration/seed options used in activator upgrade-adjacent flow:
- `gg_data_default_prompt_seeded`
- `gg_data_factory_prompt_placeholders_migrated`

### 6.2 Settings Category Contracts

Per connection in settings category `schema`:
- `version`
- `updated_at`

[SRS: UPD-DR-01..03]

## 7. Provider-Specific Behavior

### 7.1 Direct PostgreSQL

- Uses `GG_Data_DB` connection and transactional create/upgrade operations.
- Supports automatic extension checks/installation in manager flow.

### 7.2 PostgREST/Supabase

- Avoids arbitrary SQL execution via REST create path.
- Uses SQL handoff through `/schema/sql` and validation via `/schema/verify`.
- Maintains status compatibility fields for frontend consumers.

[SRS: UPD-OR-05, UPD-QR-04]

## 8. Maintenance Recipes

### 8.1 Adding a New Migration Step

1. Add idempotent migration method in `GG_Data_Schema_Manager`.
2. Insert method call into `upgrade_schema_to_latest()` transaction path.
3. Ensure failures throw exceptions and preserve rollback semantics.
4. Update schema status/docs when new contract fields are introduced.

### 8.2 Versioning Rules

1. Keep plugin runtime version constant aligned with release process.
2. Do not overwrite connection-scoped schema versions globally.
3. Preserve legacy fallback migration path until deprecated in a planned release.

## 9. Troubleshooting

### Schema status says connection not found

Cause:
- Connection key is missing from configured connections.

Resolution:
- Validate connection exists in settings and retry request with correct key.

### Upgrade route returns failed transaction

Cause:
- Migration step or extension install failed in transaction.

Resolution:
- Inspect logs, correct DB permissions/state, rerun upgrade.

### Supabase schema remains not_created after create

Cause:
- Manual SQL step has not been executed in Supabase SQL Editor.

Resolution:
- Use `/schema/sql` payload instructions, execute SQL in Supabase dashboard, then rerun `/schema/verify`.

### Legacy schema version appears stale

Cause:
- Legacy option value not yet migrated to settings category.

Resolution:
- Invoke status/version path for that connection; manager read path migrates legacy value automatically.

## 10. Parity Coverage Snapshot

Documented and code-backed:
- Hook registration and plugin-version check contracts
- Connection-scoped schema version retrieval and persistence
- Legacy option fallback migration
- Schema status/create/upgrade/verify/sql route behavior
- Transactional upgrade and rollback semantics
- Provider-specific update path differences (direct PostgreSQL vs postgrest/supabase)
