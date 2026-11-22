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

### 3.1 Schema Manager (`Gregius_Data_Schema_Manager`)

Source: [../../includes/class-gregius-data-schema-manager.php](../../includes/class-gregius-data-schema-manager.php)

Core responsibilities:
- Create all required schema structures for a target connection.
- Route schema creation/status by provider type.
- Track and update per-connection schema version.
- Execute schema upgrades and migration steps.
- Install required PostgreSQL extensions for direct connections.

Notable behavior:
- Reads provider type from connection config via settings manager.
- Migrates legacy version key from `wp_options` to `wp_gregius_data_settings`.
- Uses transaction boundaries for direct PostgreSQL create and upgrade operations.

### 3.2 Schema REST Controller (`Gregius_Data_REST_Schema_Controller`)

Source: [../../includes/api/class-gregius-data-rest-schema-controller.php](../../includes/api/class-gregius-data-rest-schema-controller.php)

Core responsibilities:
- Register `/schema/status`, `/schema/create`, `/schema/upgrade`, `/schema/verify`, and `/schema/sql`.
- Validate connection existence and type before provider-specific operations.
- Return machine-actionable payloads for dashboard flows.
- Enforce admin permissions for all schema routes.

### 3.3 Connection and Settings Services

Sources:
- [../../includes/class-gregius-data-connection-manager.php](../../includes/class-gregius-data-connection-manager.php)
- [../../includes/class-gregius-data-settings-manager.php](../../includes/class-gregius-data-settings-manager.php)

Core responsibilities:
- Store/retrieve connection config and schema metadata.
- Provide provider instances used by schema status checks.
- Persist metadata updates on successful verification.

### 3.4 Provider Interfaces and Implementations

Sources:
- [../../includes/providers/interface-gregius-data-db-provider.php](../../includes/providers/interface-gregius-data-db-provider.php)
- [../../includes/providers/class-gregius-data-postgresql-provider.php](../../includes/providers/class-gregius-data-postgresql-provider.php)
- [../../includes/providers/class-gregius-data-postgrest-provider.php](../../includes/providers/class-gregius-data-postgrest-provider.php)

Schema-related behavior:
- Direct PostgreSQL path uses PDO for schema SQL and extension setup.
- PostgREST path uses REST reads for schema verification and status.

### 3.5 PostgREST Manual SQL Artifact

Source: [../../includes/sql/postgrest-schema.sql](../../includes/sql/postgrest-schema.sql)

Role:
- Canonical SQL applied manually via Supabase/PostgREST SQL editor for schema setup.

### 3.6 Schema Manager Method Reference

Source: [../../includes/class-gregius-data-schema-manager.php](../../includes/class-gregius-data-schema-manager.php)

Lifecycle orchestration (public):

| Method | Signature | Purpose |
|---|---|---|
| `create_all_tables` | `create_all_tables( $connection_name = 'default' )` | Resolve provider type and create all schema structures for the connection. |
| `upgrade_schema_to_latest` | `upgrade_schema_to_latest( $connection_name = 'default' )` | Compare the stored schema version to `GREGIUS_DATA_SCHEMA_VERSION` and run migrations if older. |
| `get_schema_status` | `get_schema_status( $connection_name = 'default' )` | Return a provider-aware schema status payload (existence, completeness, version, extensions). |

Version management (public):

| Method | Signature | Purpose |
|---|---|---|
| `get_schema_version` | `get_schema_version( $connection_name )` | Read per-connection schema version from settings, migrating the legacy `wp_options` value on read. |
| `set_schema_version` | `set_schema_version( $connection_name, $version )` | Persist per-connection schema version (and `updated_at`) in settings category `schema`. |

PostgreSQL schema DDL (protected):

| Method | Signature | Purpose |
|---|---|---|
| `create_schema_meta_table` | `create_schema_meta_table( $conn )` | Create `gregius_data_schema_meta`. |
| `set_schema_version_in_db` | `set_schema_version_in_db( $conn, $version )` | Stamp schema version into `gregius_data_schema_meta`. |
| `get_schema_version_from_db` | `get_schema_version_from_db( $conn )` | Read schema version stamp from `gregius_data_schema_meta`. |
| `create_posts_table` | `create_posts_table( $conn )` | Create `wp_posts` mirror table and indexes. |
| `create_posts_clean_table` | `create_posts_clean_table( $conn, $connection_name = 'default' )` | Create `wp_posts_clean` and its indexes. |
| `create_postmeta_table` | `create_postmeta_table( $conn )` | Create `wp_postmeta` (JSONB `meta_value`). |
| `create_terms_table` | `create_terms_table( $conn )` | Create `wp_terms`. |
| `create_term_taxonomy_table` | `create_term_taxonomy_table( $conn )` | Create `wp_term_taxonomy`. |
| `create_term_relationships_table` | `create_term_relationships_table( $conn )` | Create `wp_term_relationships`. |
| `create_comments_table` | `create_comments_table( $conn )` | Create `wp_comments`. |
| `create_commentmeta_table` | `create_commentmeta_table( $conn )` | Create `wp_commentmeta`. |
| `create_users_table` | `create_users_table( $conn )` | Create `wp_users`. |
| `create_usermeta_table` | `create_usermeta_table( $conn )` | Create `wp_usermeta`. |
| `create_options_table` | `create_options_table( $conn )` | Create `wp_options`. |
| `create_chunks_table` | `create_chunks_table( $conn )` | Create `wp_posts_chunks`. |
| `create_openai_embedding_tables` | `create_openai_embedding_tables( $conn )` | Create the OpenAI/Gemini/Cohere embedding tables and the `connection_embedding_models` association table. |
| `create_hashingtf_embedding_table` | `create_hashingtf_embedding_table( $conn )` | Create `wp_posts_hashingtf_murmur3_1024`. |
| `create_connection_embedding_models_table` | `create_connection_embedding_models_table( $conn )` | Create `connection_embedding_models`. |

Extensions (protected):

| Method | Signature | Purpose |
|---|---|---|
| `install_pgvector_extension` | `install_pgvector_extension( $conn )` | Install and verify the `vector` extension; throws an actionable error on failure. |
| `install_pg_trgm_extension` | `install_pg_trgm_extension( $conn )` | Install and verify the `pg_trgm` extension; throws an actionable error on failure. |

Migrations (protected):

| Method | Signature | Purpose |
|---|---|---|
| `migrate_postmeta_table` | `migrate_postmeta_table( $conn )` | Convert `wp_postmeta.meta_value` to JSONB and drop the legacy unique constraint. |
| `migrate_posts_table_for_sync` | `migrate_posts_table_for_sync( $conn )` | Add `synced_at` / `modified_at` to `wp_posts` if missing. |

Sync metadata (public + protected):

| Method | Signature | Purpose |
|---|---|---|
| `create_sync_metadata_tables_for_all_sites` | `create_sync_metadata_tables_for_all_sites()` | Create the MySQL sync metadata table for each site (multisite-aware). |
| `create_sync_metadata_mysql_table` | `create_sync_metadata_mysql_table()` | Create the aggregate MySQL `{prefix}gregius_data_sync_metadata` table. |
| `create_sync_metadata_table` | `create_sync_metadata_table( $conn )` | Create both MySQL and PostgreSQL sync metadata tables. |
| `create_sync_metadata_postgresql_table` | `create_sync_metadata_postgresql_table( $conn )` | Create `wp_gregius_data_sync_metadata_entities`. |

Search (public + protected):

| Method | Signature | Purpose |
|---|---|---|
| `rebuild_search_vector` | `rebuild_search_vector( $conn, $connection_name, $language )` | Recreate the search-vector trigger and backfill `search_vector_weighted`. |
| `create_search_function` | `create_search_function( $conn, $connection_name = 'default' )` | Delegate full-text search function creation to `Gregius_Data_Search_Schema`. |

Provider routing and status helpers (protected):

| Method | Signature | Purpose |
|---|---|---|
| `create_supabase_schema` | `create_supabase_schema( $connection_name )` | Create WordPress-side tables for PostgREST and return manual SQL instructions. |
| `get_supabase_schema_status` | `get_supabase_schema_status( $connection_name )` | Query `gregius_data_schema_meta` over PostgREST for status. |
| `get_schema_status_enum` | `get_schema_status_enum( $schema_exists, $schema_version )` | Map existence/version to `missing` / `current` / `update_available` / `schema_newer`. |
| `create_schema` | `create_schema( $conn )` | No-op; tables live in the default `public` schema. |
| `get_table_prefix` | `get_table_prefix()` | Resolve the canonical mirror table prefix (`wp_`). |

### 3.7 Database Schema Reference

Canonical PostgreSQL schema is defined by [../../includes/sql/postgrest-schema.sql](../../includes/sql/postgrest-schema.sql) (PostgREST/Supabase path) and generated by the schema manager (PDO path). Unless noted, columns are identical across both paths. Columns marked "PDO only" exist only in the schema-manager-generated (direct PostgreSQL) path; they are not present in the canonical PostgREST SQL file.

Core mirror tables:

| Table | Provider(s) | Columns |
|---|---|---|
| `gregius_data_schema_meta` | PDO, PostgREST | `key` (PK), `value`, `updated_at` |
| `wp_gregius_data_sync_metadata_entities` | PDO, PostgREST | `id`, `connection_name`, `entity_type`, `entity_id`, `source_id`, `post_type`, `source_modified_at`, `synced_at`, `created_at`, `updated_at` |
| `wp_posts` | PDO, PostgREST | `id` (PK), `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`, `synced_at`, `modified_at` (PDO only) |
| `wp_posts_clean` | PDO, PostgREST | `post_id` (PK, FK → `wp_posts.id`), `post_title_clean`, `post_content_clean`, `post_excerpt_clean`, `metadata_manifest` (JSONB), `cleaned_at`, `content_hash`, `cleaning_version`, `word_count`, `reading_time_minutes`, `search_vector_weighted` (tsvector), `visibility` |
| `wp_postmeta` | PDO, PostgREST | `meta_id` (PK), `post_id` (FK → `wp_posts.id`), `meta_key`, `meta_value` (JSONB on PDO; TEXT + `synced_at` on PostgREST) |
| `wp_terms` | PDO, PostgREST | `term_id` (PK), `name`, `slug`, `term_group`, `synced_at` (PostgREST only) |
| `wp_term_taxonomy` | PDO, PostgREST | `term_taxonomy_id` (PK), `term_id` (FK → `wp_terms.term_id`), `taxonomy`, `description`, `parent`, `count`, `synced_at` (PostgREST only) |
| `wp_term_relationships` | PDO, PostgREST | `object_id` (FK → `wp_posts.id`), `term_taxonomy_id` (FK → `wp_term_taxonomy.term_taxonomy_id`), `term_order`, `synced_at` (PostgREST only); PK `(object_id, term_taxonomy_id)` |
| `wp_comments` | PDO only | `comment_ID` (PK), `comment_post_ID`, `comment_author`, `comment_author_email`, `comment_author_url`, `comment_author_IP`, `comment_date`, `comment_date_gmt`, `comment_content`, `comment_karma`, `comment_approved`, `comment_agent`, `comment_type`, `comment_parent`, `user_id` |
| `wp_commentmeta` | PDO only | `meta_id` (PK), `comment_id` (FK → `wp_comments.comment_ID`), `meta_key`, `meta_value` |
| `wp_users` | PDO only | `ID` (PK), `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name` |
| `wp_usermeta` | PDO only | `umeta_id` (PK), `user_id` (FK → `wp_users.ID`), `meta_key`, `meta_value` |
| `wp_options` | PDO only | `option_id` (PK), `option_name`, `option_value`, `autoload` |

Chunk and embedding tables:

| Table | Provider(s) | Columns |
|---|---|---|
| `wp_posts_chunks` | PDO, PostgREST | `chunk_id` (PK), `post_id` (FK → `wp_posts.id`), `chunk_index`, `chunk_text`, `chunk_hash`, `source_hash`, `token_count`, `created_at` |
| `wp_posts_openai_text_embedding_3_small_1536` | PDO, PostgREST | `id`, `post_id`, `post_type`, `field_type`, `chunk_index`, `embedding` vector(1536), `content_hash`, `token_count`, `cost`, `status`, `generated_at`, `model_used`, `error_message` |
| `wp_posts_openai_text_embedding_3_large_3072` | PDO, PostgREST | `id`, `post_id`, `post_type`, `field_type`, `chunk_index`, `embedding` halfvec(3072), `content_hash`, `token_count`, `cost`, `status`, `generated_at`, `model_used`, `error_message` |
| `wp_posts_gemini_gemini_embedding_001_3072` | PDO, PostgREST | `id`, `post_id`, `post_type`, `field_type`, `chunk_index`, `embedding` halfvec(3072), `content_hash`, `token_count`, `cost`, `status`, `generated_at`, `model_used`, `error_message` |
| `wp_posts_cohere_embed_v50_pro_2048` | PDO, PostgREST | `id`, `post_id`, `post_type`, `field_type`, `chunk_index`, `embedding` halfvec(2048), `content_hash`, `token_count`, `cost`, `status`, `generated_at`, `model_used`, `error_message` |
| `wp_posts_cohere_embed_v50_fast_2048` | PDO, PostgREST | `id`, `post_id`, `post_type`, `field_type`, `chunk_index`, `embedding` halfvec(2048), `content_hash`, `token_count`, `cost`, `status`, `generated_at`, `model_used`, `error_message` |
| `wp_posts_hashingtf_murmur3_1024` | PDO, PostgREST | `id`, `post_id`, `post_type`, `field_type`, `chunk_index`, `embedding` vector(1024), `content_hash`, `token_count`, `status`, `generated_at`, `tokenizer_version`, `error_message` |
| `connection_embedding_models` | PDO only | `model_key` (PK), `added_at`, `is_active` |

WordPress-side (MySQL) tables:

| Table | Provider(s) | Columns |
|---|---|---|
| `wp_gregius_data_settings` | PDO, PostgREST | `id` (PK), `connection_name`, `category`, `setting_key`, `setting_value`, `data_type`, `is_encrypted`, `is_active`, `environment`, `provider`, `created_at`, `updated_at`; UNIQUE `(connection_name, category, setting_key)` |
| `{prefix}gregius_data_sync_metadata` | PDO, PostgREST | `id`, `connection_name`, `entity_type`, `post_type`, `wp_count`, `pg_count`, `drift`, `last_sync_at`, `sync_status`, `enabled`, `created_at`, `updated_at` |

The row-per-embedding tables share a common shape: `field_type` discriminates `title` / `excerpt` / `chunk`, with `chunk_index` NULL for title/excerpt and 0-N for chunks, and a `UNIQUE (post_id, field_type, chunk_index)` constraint plus a `CHECK` enforcing the NULL/`chunk_index` relationship.

## 4. REST Route Catalog (Schema)

Base namespace: `/wp-json/gregius-data/v1`

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
- Compares stored schema version with the required schema version (`GREGIUS_DATA_SCHEMA_VERSION`).
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
- Reads schema version from `gregius_data_schema_meta` via PostgREST provider.
- On success updates connection metadata with `schema_version` and `schema_status=ready`.
- On missing schema returns status `not_created`.

## 6. Schema Version and Status Contracts

Schema version storage:
- Category: `schema`
- Keys used by manager: `version`, `updated_at`
- Legacy fallback key: `gregius_data_schema_version_{connection}` in `wp_options`

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
- `required_schema_version`
- `schema_status`
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
- Compare `schema_version` against `required_schema_version` in status payload.
- Confirm route caller has `manage_options` capability.
- Check logs for migration exceptions that triggered rollback.

## 10. Traceability (SRS Mapping)

- Lifecycle orchestration and provider routing: [SRS: SCH-FR-01, SCH-FR-02, SCH-FR-03, SCH-FR-04]
- Versioning and status contracts: [SRS: SCH-FR-05, SCH-FR-06, SCH-FR-07, SCH-DR-01, SCH-DR-04]
- Route contracts and manual SQL workflow: [SRS: SCH-FR-08, SCH-DR-05, SCH-DR-06, SCH-DR-07]
- Security and operations behavior: [SRS: SCH-OR-01, SCH-OR-02, SCH-OR-03, SCH-OR-04, SCH-OR-06]
- Quality and compatibility guarantees: [SRS: SCH-QR-01, SCH-QR-03, SCH-QR-05]
