# ADR 0001: Schema Versioning and Provider Parity (1.0.1)

- Status: Accepted
- Date: 2026-10-25

## Context

Version 1.0.0 shipped with three defects that broke Supabase (PostgREST) deployments
while direct PostgreSQL (PDO) deployments worked:

1. **Lifecycle metadata tracking was a no-op for Supabase.** The metadata manager and
   validator bypassed the provider layer and called `Gregius_Data_DB::get_connection()`
   directly, which returns `false` by design for PostgREST. Result: the sync metadata
   table was never populated and drift validation reported `pg_count = 0`.
2. **The validator computed a false 100% "critical" drift for Supabase**, sending a
   daily false-alarm email.
3. **There was no real schema versioning.** The "schema update available" banner
   compared the stored schema version against the *plugin* version, so it fired on
   every plugin release, and the Supabase "Update Schema" button could never succeed.

## Decisions

### 1. Schema version is decoupled from the plugin version

- Introduce `GREGIUS_DATA_SCHEMA_VERSION = '1.0.1'`, independent of
  `GREGIUS_DATA_VERSION`.
- Invariant: **schema X.Y.Z requires plugin >= X.Y.Z**. Bump the schema constant only
  when `includes/sql/postgrest-schema.sql` changes, and never above the plugin version.
- The version stamp is written **at the end** of `postgrest-schema.sql`, so a failed
  run never leaves a stale "success" version.
- 1.0.1 is **additive** (`CREATE TABLE IF NOT EXISTS` / `CREATE OR REPLACE FUNCTION`),
  so the upgrade step is **re-run the schema SQL**, never delete-and-recreate (which
  would wipe embeddings and force re-embedding).

### 2. Supabase upsert uses `Prefer: resolution=merge-duplicates`

- `merge-duplicates` cannot express `CURRENT_TIMESTAMP`, so the payload sends
  `synced_at` + `updated_at` (naive GMT, matching PDO) and omits `id` + `created_at`.
- HTTP 409 under merge-duplicates means "ambiguous multiple matching rows", not
  "missing constraint", so `upsert()` owns a targeted fallback (PATCH, else insert).

### 3. Validator status is five-state, and `degraded` is never emailed

- Statuses: `healthy` / `warning` / `critical` / `degraded` / `error`. Overall is
  worst-of; drift % is computed over countable types only.
- `degraded` (schema stale / count RPC missing) is dashboard-only — **no email**.
  `error` (connection/auth/network failure) sends its own health email with a
  separate 24h throttle. `schema_newer` (stored > plugin) is informational only.

### 4. Postmeta drift is filtered on both provider paths

- The WordPress-side postmeta count applies a `meta_key` blacklist plus post
  type/status filters. The Supabase RPC (`gregius_data_count_postmeta`) and the PDO
  raw SQL implement the **same** filter so drift is meaningful and parity holds.
  (A raw `COUNT(*)` would permanently overcount and reproduce a false drift.)

### 5. `real_time_sync` default flips to enabled

- The toggle was a no-op in 1.0.0 (post-save sync always ran). It is now wired into
  `handle_post_save()`, the default is `true`, and a one-time migration flips the
  stored `false` default to `true` so the setting reflects actual behavior.

## Consequences

- Supabase and PDO now share one drift/validation path; both are correct.
- Schema updates remain **manual copy/paste** for Supabase (no DDL-executing RPC is
  ever exposed).
- The two count RPCs are `SECURITY DEFINER` + `SET search_path`, matching the existing
  `get_schema_status()` pattern, and are called with the service-role key.
- Future schema bumps must use `ALTER TABLE … ADD COLUMN IF NOT EXISTS` (not
  `CREATE TABLE IF NOT EXISTS`) for new columns, and `DROP FUNCTION IF EXISTS f(args)`
  before changing a function's argument list or return type.
