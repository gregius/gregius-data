# Architecture Description: Plugin Lifecycle Subsystem

Standard: ISO/IEC/IEEE 42010:2022
Component: Plugin Lifecycle Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Specification: [srs.md](srs.md)
Related Prompt Architecture: [../prompt/architecture.md](../prompt/architecture.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data establishes, preserves, upgrades, and removes plugin-owned state through WordPress lifecycle phases.

Scope includes:
- activation and deactivation hook registration,
- admin-init version checking and upgrade gating,
- default sync settings initialization,
- cron schedule registration and scheduled event cleanup,
- uninstall cleanup for single-site and multisite,
- transient-driven localStorage cleanup after deactivation.

Explicitly excluded:
- sync execution logic,
- retry queue runtime behavior beyond lifecycle-owned hook cleanup,
- dashboard behavior outside the deferred localStorage cleanup bridge.

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrators | Predictable behavior on activate, deactivate, reactivate, and uninstall | High |
| Plugin developers | Stable lifecycle boundaries and hook registration points | High |
| Operations | Safe cleanup behavior in multisite and upgrade scenarios | High |
| Product | Empty defaults and non-destructive deactivation semantics | Medium |

---

## 2. Context View (AV-01)

```
WordPress Plugin Lifecycle
        │
        ├─ Activation
        │    ▼
        │  gregius-data.php
        │    register_activation_hook()
        │    ▼
        │  GG_Data_Activator::activate()
        │    ├─ run_upgrades()
        │    ├─ create settings / sync metadata / logs tables
        │    ├─ initialize default sync settings
        │    ├─ schedule cron events
        │    ├─ add administrator capability
        │    └─ seed factory prompt
        │
        ├─ Active Admin Request
        │    ▼
        │  admin_init
        │    ▼
        │  GG_Data_Activator::check_version()
        │    ├─ compare gg_data_db_version with code version
        │    ├─ run_upgrades() when outdated
        │    ├─ seed factory prompt (guarded)
        │    └─ migrate factory prompt placeholders (guarded)
        │
        ├─ Deactivation
        │    ▼
        │  register_deactivation_hook()
        │    ▼
        │  GG_Data_Deactivator::deactivate()
        │    ├─ clear active + deprecated cron hooks
        │    ├─ set gg_data_clear_localstorage transient
        │    └─ preserve plugin data
        │
        └─ Uninstall
             ▼
           uninstall.php
             ├─ verify WP_UNINSTALL_PLUGIN
             ├─ unschedule Action Scheduler or cron hooks
             ├─ delete options and transients
             ├─ drop custom tables
             ├─ flush cache
             └─ repeat per site in multisite + clean sitemeta
```

Mapping:
- AV-01 -> LIFE-FR-01 to LIFE-FR-14
- AV-01 -> LIFE-OR-01 to LIFE-OR-05

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. **Bootstrap Component** (`gregius-data.php`)
   - Registers activation and deactivation hooks.
   - Registers the admin-init version-check callback.
   - Registers cron schedules before event scheduling occurs.

2. **Activator Component** (`GG_Data_Activator`)
   - Performs upgrades and version tracking.
   - Creates settings, sync metadata, and logs tables via collaborating managers.
   - Initializes default sync options under `gg_data_sync_*` keys.
   - Schedules connection-health and daily-validation cron events.
   - Adds administrator capability.
   - Seeds and migrates factory prompt content through guarded helper methods.

3. **Deactivator Component** (`GG_Data_Deactivator`)
   - Clears active and deprecated cron hooks.
   - Removes an administrator capability according to current implementation.
   - Sets the transient consumed by the admin-side localStorage cleanup bridge.
   - Preserves plugin state by design.

4. **Cron Manager Component** (`GG_Data_Cron_Manager`)
   - Registers custom cron schedule definitions.
   - Provides schedule metadata for runtime use and debugging.

5. **Assets Cleanup Bridge** (`gg_data_clear_localstorage_script()`)
   - Runs on `admin_head`.
   - Detects `gg_data_clear_localstorage` transient.
   - Injects JavaScript to remove `gg_data_*` localStorage keys.

6. **Uninstall Handler** (`uninstall.php`)
   - Executes destructive cleanup only when called through WordPress uninstall flow.
   - Iterates per site in multisite.
   - Removes Action Scheduler jobs or cron fallbacks, deletes plugin-owned options/transients, drops tables, and flushes cache.

### 3.2 Contract Boundaries

- Upstream dependency: WordPress plugin lifecycle and hook APIs.
- Collaborators: settings manager, schema manager, and logger participate in activation setup.
- Browser boundary: localStorage cleanup is deferred to the next admin request rather than executed during deactivation itself.

Mapping:
- AV-02 -> LIFE-FR-01 to LIFE-FR-14
- AV-02 -> LIFE-DR-01 to LIFE-DR-06

---

## 4. Runtime View (AV-03)

### 4.1 Activation Flow

```
Plugin activated
    │
    ▼
register_activation_hook → GG_Data_Activator::activate()
    │
    ├─ run_upgrades()
    │   └─ update_option('gg_data_db_version', VERSION)
    │
    ├─ create settings tables for all sites
    ├─ create sync metadata tables for all sites
    ├─ create logs tables for all sites + migrate legacy log options
    ├─ initialize search settings
    ├─ init_default_sync_settings()
    │   └─ only update missing gg_data_sync_* options
    ├─ schedule gg_data_check_connection_health when missing
    ├─ schedule gg_data_daily_validation when missing
    ├─ add_cap('manage_gg_pg') to administrator
    └─ seed_default_prompt() (guarded by option)
```

### 4.2 Version-Check Flow

```
admin_init
    │
    ▼
GG_Data_Activator::check_version()
    │
    ├─ installed_version = get_option('gg_data_db_version', '0.0.0')
    ├─ version_compare(installed_version, VERSION, '<')?
    │   ├─ YES → run_upgrades()
    │   └─ NO  → continue
    ├─ seed_default_prompt() (guarded)
    └─ migrate_factory_prompt_to_placeholders() (guarded)
```

### 4.3 Deactivation and Deferred Browser Cleanup Flow

```
Plugin deactivated
    │
    ▼
GG_Data_Deactivator::deactivate()
    │
    ├─ clear active cron hooks
    ├─ clear deprecated cron hooks
    ├─ remove administrator capability (`manage_gg_pg`)
    ├─ set_transient('gg_data_clear_localstorage', true, 1 hour)
    └─ preserve plugin options, tables, and user data

Next admin request
    │
    ▼
admin_head → gg_data_clear_localstorage_script()
    │
    ├─ transient exists?
    │   ├─ NO → no-op
    │   └─ YES:
    │       ├─ delete transient
    │       └─ inject JS to remove localStorage keys with prefix gg_data_
```

### 4.4 Uninstall Flow

```
Plugin uninstalled via WordPress
    │
    ▼
uninstall.php
    │
    ├─ verify WP_UNINSTALL_PLUGIN
    ├─ determine blog_ids (single-site => [1], multisite => all blogs)
    ├─ foreach blog_id:
    │   ├─ switch_to_blog() when multisite
    │   ├─ unschedule Action Scheduler jobs when available
    │   ├─ else clear cron hooks directly
    │   ├─ delete gg_data_* and gregius_data_* options
    │   ├─ delete transient and site transient keys
    │   ├─ drop gg_data_settings table
    │   ├─ drop gg_data_sync_metadata table
    │   ├─ wp_cache_flush()
    │   └─ restore_current_blog() when multisite
    └─ delete network-level sitemeta keys for gg_data_* and gregius_data_*
```

Mapping:
- AV-03 -> LIFE-FR-03 to LIFE-FR-14
- AV-03 -> LIFE-OR-01 to LIFE-OR-05
- AV-03 -> LIFE-QR-01 to LIFE-QR-04

---

## 5. Architectural Decisions (ADRs)

### AD-01: Preserve Data on Deactivation

Decision:
- Deactivation clears scheduled runtime work and sets cleanup transients but does not delete plugin-owned data.

Rationale:
- WordPress deactivation is a pause state, not a destructive state.
- Reactivation should restore prior operational context without forcing full setup.

Consequences:
- Uninstall becomes the only destructive lifecycle phase.
- Reactivation can rely on existing settings and tables.

Linked requirements:
- AD-01 -> LIFE-FR-10, LIFE-OR-01, LIFE-QR-02

### AD-02: Empty Sync Defaults Require Explicit Opt-In

Decision:
- Activation initializes empty post-type and status defaults and keeps real-time sync disabled by default.

Rationale:
- Prevents accidental mass sync immediately after install.
- Forces administrators to make explicit scope decisions.

Consequences:
- Fresh installations need initial configuration before sync features are active.

Linked requirements:
- AD-02 -> LIFE-FR-05, LIFE-FR-06

### AD-03: Multisite Uninstall Cleans Per Site and Network Scope

Decision:
- Uninstall iterates through each site for site-scoped cleanup and separately removes network metadata.

Rationale:
- Plugin-owned state exists in both site-scoped and network-scoped storage.
- Partial cleanup would leave stale data behind in multisite deployments.

Consequences:
- Uninstall has wider operational blast radius and must be clearly documented.

Linked requirements:
- AD-03 -> LIFE-FR-13, LIFE-FR-14, LIFE-OR-04, LIFE-QR-03

### AD-04: Deferred localStorage Cleanup via Transient

Decision:
- Browser cleanup is deferred to the next admin request through a transient rather than being attempted during deactivation.

Rationale:
- Deactivation runs on the server and cannot directly mutate browser localStorage.
- The transient bridge provides a reliable handoff to the browser environment.

Consequences:
- Cleanup only occurs after a subsequent admin page load.
- Low or absent admin traffic can delay browser cleanup.

Linked requirements:
- AD-04 -> LIFE-FR-11, LIFE-DR-03, LIFE-QR-04

### AD-05: Action Scheduler Cleanup with Cron Fallback on Uninstall

Decision:
- Uninstall prefers `as_unschedule_all_actions()` when available and falls back to `wp_clear_scheduled_hook()` otherwise.

Rationale:
- The plugin may own work scheduled through more than one scheduler mechanism.
- Fallback keeps uninstall resilient when Action Scheduler is unavailable.

Consequences:
- Uninstall behavior is more robust than deactivation behavior, which only clears cron hooks.

Linked requirements:
- AD-05 -> LIFE-FR-12, LIFE-OR-02

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Impact |
|---|---|---|
| C-01 | Lifecycle scheduling depends on custom cron schedules being registered before `wp_schedule_event()` calls. | Bootstrap order must remain stable. |
| C-02 | Deactivation cannot directly mutate browser storage. | localStorage cleanup requires a deferred bridge via transient and admin page load. |
| C-03 | Uninstall cleanup is intentionally destructive across all sites in multisite. | Operators need clear awareness of uninstall blast radius. |
| C-04 | Version-check logic runs on admin-init while the plugin is active. | Guarded routines must remain idempotent to avoid repeated side effects. |

### 6.2 Risks

| Risk ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Capability add/remove names diverge in current implementation. | Medium | Medium | Document as implementation caveat and track separately for code fix. |
| R-02 | Guarded prompt routines still execute on every admin-init path. | Medium | Low | Keep guards intact and document the repeated invocation pattern. |
| R-03 | Sites with disabled or low-traffic admin activity may delay deferred browser cleanup. | Low | Low | Document operational expectation and rely on subsequent admin requests. |
| R-04 | Multisite uninstall can remove plugin state across all blogs unintentionally if invoked without full awareness. | Low | High | Document uninstall behavior clearly and keep destructive work scoped to uninstall only. |

---

## 7. Coverage Mapping

| Architecture Item | SRS IDs Covered |
|---|---|
| AV-01 (Context View) | LIFE-FR-01 to LIFE-FR-14, LIFE-OR-01 to LIFE-OR-05 |
| AV-02 (Component View) | LIFE-FR-01 to LIFE-FR-14, LIFE-DR-01 to LIFE-DR-06 |
| AV-03 (Runtime View) | LIFE-FR-03 to LIFE-FR-14, LIFE-OR-01 to LIFE-OR-05, LIFE-QR-01 to LIFE-QR-04 |
| AD-01 | LIFE-FR-10, LIFE-OR-01, LIFE-QR-02 |
| AD-02 | LIFE-FR-05, LIFE-FR-06 |
| AD-03 | LIFE-FR-13, LIFE-FR-14, LIFE-OR-04, LIFE-QR-03 |
| AD-04 | LIFE-FR-11, LIFE-DR-03, LIFE-QR-04 |
| AD-05 | LIFE-FR-12, LIFE-OR-02 |