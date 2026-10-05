# Developer Documentation: Plugin Lifecycle Subsystem

Standard: ISO/IEC/IEEE 26514:2022
Component: Plugin Lifecycle Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Architecture: [architecture.md](architecture.md)

---

## 1. Lifecycle Entry Points

### Bootstrap Registration

The plugin bootstrap wires lifecycle entry points in `gregius-data.php`:

```php
register_activation_hook( __FILE__, array( 'GG_Data_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GG_Data_Deactivator', 'deactivate' ) );
add_action( 'admin_init', array( 'GG_Data_Activator', 'check_version' ) );

GG_Data_Cron_Manager::register_schedules();
```

This ordering matters: custom cron schedules must be registered before activation logic attempts to schedule events.

## 2. Public APIs and Constants

### `GG_Data_Activator`

Relevant public API:

```php
const VERSION = '1.0.0';
const VERSION_OPTION = 'gg_data_db_version';

public static function activate();
public static function check_version();
```

Internal helper methods used by those public entry points:

```php
private static function run_upgrades();
private static function seed_default_prompt();
private static function migrate_factory_prompt_to_placeholders();
private static function init_default_sync_settings();
```

Activation behavior summary:
- calls `run_upgrades()` and updates `gg_data_db_version`,
- creates plugin-owned settings, sync metadata, and logs tables through collaborating managers,
- initializes search settings,
- writes default `gg_data_sync_*` options only when absent,
- schedules connection health and daily validation cron jobs,
- adds the administrator capability `manage_gg_pg`,
- seeds the factory prompt when missing.

Version-check behavior summary:
- compares `gg_data_db_version` with `GG_Data_Activator::VERSION`,
- runs upgrades when the installed version is older,
- invokes prompt seeding and placeholder migration on every admin-init path, guarded by options.

### `GG_Data_Deactivator`

```php
public static function deactivate();
```

Deactivation behavior summary:
- clears active cron hooks,
- clears deprecated cron hooks retained for backward cleanup,
- sets `gg_data_clear_localstorage` transient for one hour,
- preserves plugin options and tables.

Capability lifecycle note:
- activation adds `manage_gg_pg`, and deactivation removes `manage_gg_pg`.
- runtime admin and REST authorization remains based on `manage_options`.

### `GG_Data_Cron_Manager`

```php
public static function register_schedules();
public static function add_custom_schedules( $schedules );
public static function get_schedules();
```

Registered schedules:

| Key | Interval | Used By | Hook |
|---|---|---|---|
| `gg_data_every_minute` | 60 seconds | Retry queue processing | `gg_data_process_retry_queue` |
| `gg_data_every_five_minutes` | 300 seconds | Connection health checks | `gg_data_check_connection_health` |

### `GG_Data_Uninstaller`

```php
const RETENTION_OPTION = 'gg_data_remove_data_on_uninstall';

public static function get_retention_flag();
public static function set_retention_flag( $value );
public static function run();
```

Retention flag storage: a site option on multisite (`get_site_option` / `update_site_option`) and a blog option on single-site (`get_option` / `update_option`).

Uninstall behavior summary:
- `run()` preserves all plugin data by default (flag unset),
- always clears cron (Action Scheduler + WP-Cron), removes `manage_gg_pg`, and deletes the `gg_data_clear_localstorage` transient,
- when the flag is set: deletes `gg_data_*`/`gregius_data_*` options and transients, drops `gg_settings`, `gg_sync_metadata`, and `gg_data_logs`, deletes `gg_prompt` posts/terms/meta, and (multisite) `sitemeta` keys,
- never touches the external PostgreSQL/Supabase mirror.

### `GG_Data_REST_Data_Retention_Controller`

```php
protected $namespace = 'gg-data/v1';
protected $rest_base  = 'data-retention';
```

- `POST /gg-data/v1/data-retention` with body `{ remove_data: boolean }`.
- Permission: `manage_network_options` on multisite, `manage_options` otherwise.

---

## 3. Default Option Contracts

`init_default_sync_settings()` initializes the following option values only when they do not already exist:

| Option Key | Default Value |
|---|---|
| `gg_data_sync_enabled_post_types` | `array()` |
| `gg_data_sync_enabled_statuses` | `array()` |
| `gg_data_sync_real_time_sync` | `false` |
| `gg_data_sync_sync_meta` | `true` |
| `gg_data_sync_sync_terms` | `true` |

Design intent:
- post types and statuses start empty so administrators explicitly opt in,
- real-time sync starts disabled,
- meta and term sync support is enabled for later use once core sync is configured.

---

## 4. Scheduled Hook Ownership

### Scheduled on Activation

| Hook | Schedule | Purpose |
|---|---|---|
| `gg_data_check_connection_health` | `gg_data_every_five_minutes` | Connection health monitoring |
| `gg_data_daily_validation` | `daily` | Sync validation |

### Cleared on Deactivation

Active hooks:
- `gg_data_check_vector_indexes`
- `gg_data_process_retry_queue`
- `gg_data_check_connection_health`
- `gg_data_daily_validation`

Deprecated hooks kept for cleanup:
- `gg_data_cleanup_logs`
- `gg_data_process_simple_vectors`
- `gg_data_process_batch`
- `gg_data_retry_failed`

### Cleared on Uninstall

When Action Scheduler is available:
- `gg_data_process_retry_queue`
- `gg_data_process_batch_sync`
- `gg_data_process_batch_embeddings`
- `gg_data_sync_post`
- `gg_data_delete_post`

Fallback when Action Scheduler is unavailable:
- corresponding `wp_clear_scheduled_hook()` calls for the same hook names.

---

## 5. Uninstall Cleanup Sequence

`uninstall.php` delegates to `GG_Data_Uninstaller::run()`, which performs destructive cleanup only when the `gg_data_remove_data_on_uninstall` flag is enabled.

Always (flag on or off):
1. Clear cron (Action Scheduler actions, or WP-Cron fallbacks).
2. Remove the `manage_gg_pg` capability from the administrator role.
3. Delete the `gg_data_clear_localstorage` transient.

When the flag is enabled (opt-in removal):
1. Delete `gg_data_*` and `gregius_data_*` options.
2. Delete matching transients and transient timeouts.
3. Drop `{prefix}gg_settings`, `{prefix}gg_sync_metadata`, and `{prefix}gg_data_logs`.
4. Delete prompt data: `gg_prompt` posts, `_gg_prompt_*` meta, and `gg_prompt_type` terms.
5. Flush object cache.

Multisite additions:
1. Query all blog IDs.
2. `switch_to_blog()` for each site before site-scoped cleanup; `restore_current_blog()` after.
3. The retention flag is a single network-wide site option.
4. Delete matching network-level `sitemeta` keys after per-site cleanup (flag enabled).

The external PostgreSQL/Supabase mirror is never removed by uninstall; it is managed through the plugin's delete-synced-data action while active.

---

## 6. Deferred localStorage Cleanup

### Server-Side Trigger

Deactivation sets:

```php
set_transient( 'gg_data_clear_localstorage', true, HOUR_IN_SECONDS );
```

### Browser-Side Bridge

`assets/assets.php` attaches to `admin_head` and checks the transient:

```php
function gg_data_clear_localstorage_script() {
    if ( get_transient( 'gg_data_clear_localstorage' ) ) {
        delete_transient( 'gg_data_clear_localstorage' );
        // output script that removes localStorage keys prefixed with gg_data_
    }
}
add_action( 'admin_head', 'gg_data_clear_localstorage_script' );
```

The injected script removes all browser localStorage keys whose names start with `gg_data_`.

Operational note:
- no cleanup occurs until the next admin request after deactivation.

---

## 7. Manual Verification Checklist

### Fresh Activation

1. Activate the plugin.
2. Confirm `gg_data_db_version` exists and matches the current plugin version.
3. Confirm `gg_data_sync_*` options exist with the documented defaults.
4. Confirm `gg_data_check_connection_health` and `gg_data_daily_validation` are scheduled.
5. Confirm required custom tables exist.

### Deactivation and Reactivation

1. Deactivate the plugin.
2. Confirm scheduled hooks are cleared.
3. Confirm options and custom tables remain present.
4. Load an admin page and verify the localStorage cleanup script has consumed the transient.
5. Reactivate and confirm previous state is preserved.

### Upgrade / Version Check

1. Set `gg_data_db_version` to an older value in a test environment.
2. Load an admin page.
3. Confirm `run_upgrades()` updates the version and triggers the upgrade notice.

### Uninstall

1. Deactivate with "Keep" (default) and delete the plugin: confirm options, tables, and prompt data remain; only cron, the capability, and the transient are removed.
2. Deactivate with "Remove" and delete the plugin: confirm `gg_data_*`/`gregius_data_*` options, the three tables (`gg_settings`, `gg_sync_metadata`, `gg_data_logs`), prompt data, and (multisite) `sitemeta` keys are removed.
3. In both cases, confirm the external PostgreSQL/Supabase mirror remains intact.

---

## 8. Troubleshooting

### Symptom: Plugin reactivates with prior state still present

Expected behavior. Deactivation intentionally preserves plugin data. Use uninstall, not deactivation, for destructive cleanup.

### Symptom: Browser localStorage was not cleared immediately after deactivation

Root cause:
- localStorage cleanup is deferred until the next admin page load.

Resolution:
1. Visit a WordPress admin page after deactivation.
2. Confirm the transient has been consumed.

### Symptom: Capability appears to remain after deactivation

Likely cause:
- administrator role state was not refreshed after deactivation/reactivation.

Resolution:
1. Reactivate the plugin to re-apply lifecycle capability handling.
2. Verify access behavior using `manage_options`-protected admin and REST routes.

### Symptom: Uninstall left data behind in multisite

Root cause:
- cleanup must run through the WordPress uninstall path so site iteration and `sitemeta` cleanup execute.

Resolution:
1. Re-run uninstall through WordPress-managed flow.
2. Inspect remaining `gg_data_*` and `gregius_data_*` keys manually if needed.

### Symptom: Delete dialog says "will also delete its data" but data remains

Root cause:
- WordPress core shows that wording for any plugin with an uninstall script and cannot be customized.
- Gregius Data preserves data by default; it removes data only when the "Remove" choice was selected at deactivation.

Resolution:
1. Expected behavior — no action needed.
2. To actually remove data, deactivate again and choose "Remove all data when the plugin is deleted," then delete the plugin.