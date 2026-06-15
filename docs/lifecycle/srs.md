# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

Related Prompt Requirements: [../prompt/srs.md](../prompt/srs.md)

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Plugin Lifecycle Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data lifecycle subsystem must do to bootstrap plugin state on activation, preserve state on deactivation, detect version changes during active upgrades, and remove plugin-owned data on uninstall.

### 1.2 System Scope

The subsystem includes activation and deactivation hook behavior, admin-init version checks, default sync settings initialization, cron schedule registration and cleanup, capability setup and removal behavior, multisite uninstall cleanup, and the deferred localStorage cleanup bridge.

Software identifier: gregius-data-lifecycle

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The lifecycle subsystem sits at the plugin boundary. It is responsible for establishing required plugin-owned tables, options, scheduled events, and administrative capabilities, and for cleaning up or preserving those resources according to the current lifecycle phase.

#### 1.3.2 System Functions Summary

- Register and execute activation and deactivation hooks from the plugin bootstrap.
- Run upgrade/version checks during admin requests while the plugin remains active.
- Initialize empty default sync settings that require explicit user opt-in.
- Create required settings, sync metadata, and log tables during activation.
- Schedule and clear plugin-owned WP-Cron events as lifecycle state changes.
- Preserve user data on deactivation.
- Remove plugin-owned options, transients, scheduled actions, and custom tables on uninstall.
- Trigger deferred browser-side localStorage cleanup after deactivation.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Operational | Plugin activation, deactivation, uninstall, admin dashboard |
| Plugin Developer | Technical | Bootstrap hooks, activator/deactivator APIs, uninstall contracts |
| Operations | Technical/Operational | Upgrade behavior, cron cleanup, multisite uninstall workflows |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Activation | The WordPress phase in which the plugin establishes required initial state |
| Deactivation | The WordPress phase in which the plugin pauses runtime behavior while preserving user data |
| Uninstall | The WordPress phase in which the plugin permanently removes plugin-owned data |
| Version check | The admin-side check that compares the installed plugin version option to the current code version |
| Deferred localStorage cleanup | A browser-side cleanup action triggered by a transient set during deactivation and consumed on the next admin page load |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| WP | WordPress |
| REST | Representational State Transfer |

## 2. References

- docs/lifecycle/architecture.md
- gregius-data.php
- includes/class-gg-data-activator.php
- includes/class-gg-data-deactivator.php
- includes/class-gg-data-cron-manager.php
- assets/assets.php
- uninstall.php

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| LIFE-FR-01 | The software MUST register a WordPress activation hook that invokes the lifecycle activation handler. | Must |
| LIFE-FR-02 | The software MUST register a WordPress deactivation hook that invokes the lifecycle deactivation handler. | Must |
| LIFE-FR-03 | The software MUST run a version check during admin requests and trigger upgrade routines when the installed version is older than the code version. | Must |
| LIFE-FR-04 | Activation MUST create or initialize plugin-owned settings, sync metadata, and logs tables for all sites where the implementation supports all-site creation. | Must |
| LIFE-FR-05 | Activation MUST initialize default sync settings only when those option values do not already exist. | Must |
| LIFE-FR-06 | Default sync settings MUST start with empty post types, empty statuses, disabled real-time sync, enabled meta sync, and enabled term sync. | Must |
| LIFE-FR-07 | Activation MUST schedule connection health and daily validation cron events when they are not already scheduled. | Must |
| LIFE-FR-08 | Activation MUST add the plugin administrative capability to the administrator role. | Must |
| LIFE-FR-09 | Activation and version-check flows MUST ensure the factory default prompt seed and prompt placeholder migration routines remain idempotent. | Must |
| LIFE-FR-10 | Deactivation MUST clear active and deprecated plugin-owned scheduled cron hooks without deleting plugin data. | Must |
| LIFE-FR-11 | Deactivation MUST set a transient that triggers browser-side localStorage cleanup on the next admin page load. | Must |
| LIFE-FR-12 | Uninstall MUST unschedule plugin-owned background jobs using Action Scheduler when available and WordPress cron fallback otherwise. | Must |
| LIFE-FR-13 | Uninstall MUST delete plugin-owned options, transients, and custom tables. | Must |
| LIFE-FR-14 | Uninstall MUST clean up multisite blog data for each site and remove network-level plugin metadata when multisite is active. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| LIFE-DR-01 | The lifecycle version contract MUST use the option key `gg_data_db_version` to track installed lifecycle version state. | Must |
| LIFE-DR-02 | Default sync settings contracts MUST use option keys prefixed with `gg_data_sync_`. | Must |
| LIFE-DR-03 | The deferred localStorage cleanup contract MUST use the transient key `gg_data_clear_localstorage`. | Must |
| LIFE-DR-04 | Uninstall cleanup contracts MUST remove option keys matching both `gg_data_*` and `gregius_data_*`. | Must |
| LIFE-DR-05 | Cron schedule contracts MUST define `gg_data_every_minute` as 60 seconds and `gg_data_every_five_minutes` as 300 seconds. | Must |
| LIFE-DR-06 | The lifecycle subsystem MUST preserve clear ownership boundaries for plugin-managed settings, sync metadata, and logs tables. | Must |

### 3.3 Operations and Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| LIFE-OR-01 | Deactivation MUST preserve plugin-owned options, tables, and other user data so that reactivation can resume from existing state. | Must |
| LIFE-OR-02 | Uninstall MUST check for `WP_UNINSTALL_PLUGIN` before executing destructive cleanup. | Must |
| LIFE-OR-03 | The lifecycle subsystem SHOULD remain idempotent when activation or version-check routines are invoked multiple times. | Should |
| LIFE-OR-04 | Multisite uninstall cleanup MUST switch site context per blog before deleting site-scoped data and restore context afterward. | Must |
| LIFE-OR-05 | The lifecycle subsystem MUST flush object cache during uninstall cleanup after destructive operations are executed. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| LIFE-QR-01 | Activation and version-check routines SHOULD be safe to run repeatedly without duplicating durable state. | Repeated activation and admin-init verification |
| LIFE-QR-02 | Deactivation behavior MUST align with WordPress expectation that data is preserved unless the plugin is uninstalled. | Manual deactivation/reactivation tests |
| LIFE-QR-03 | Uninstall cleanup SHOULD leave no plugin-owned options or custom tables behind for either single-site or multisite installs. | Database inspection after uninstall |
| LIFE-QR-04 | Deferred localStorage cleanup SHOULD execute on the next admin request without requiring direct user scripting actions. | Browser-based admin verification |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Hook and lifecycle flow validation | Activation/deactivation/uninstall behavior review + admin-init checks |
| Data and contract requirements | Option, transient, and schedule inspection | wp_options inspection + code review |
| Operations/security requirements | Preservation and cleanup testing | Reactivation testing + uninstall path validation |
| Quality requirements | Repeatability and cleanup verification | Re-run lifecycle scenarios + multisite cleanup review |

Acceptance baseline:
- Canonical lifecycle package requirements reflect current activation, deactivation, version-check, and uninstall behavior.
- Preservation-on-deactivate and cleanup-on-uninstall semantics are explicit and documented.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| LIFE-FR-01 to LIFE-FR-03 | gregius-data.php, includes/class-gg-data-activator.php | Bootstrap hook registration and admin-init version check |
| LIFE-FR-04 to LIFE-FR-09, LIFE-DR-01 to LIFE-DR-02, LIFE-OR-03, LIFE-QR-01 | includes/class-gg-data-activator.php | Activation setup, defaults, versioning, prompt seeding, idempotent guards |
| LIFE-FR-10 to LIFE-FR-11, LIFE-DR-03, LIFE-OR-01, LIFE-QR-02, LIFE-QR-04 | includes/class-gg-data-deactivator.php, assets/assets.php | Deactivation preservation and deferred localStorage cleanup |
| LIFE-FR-12 to LIFE-FR-14, LIFE-DR-04, LIFE-OR-02, LIFE-OR-04, LIFE-OR-05, LIFE-QR-03 | uninstall.php | Uninstall cleanup, Action Scheduler fallback, multisite cleanup, cache flush |
| LIFE-DR-05 | includes/class-gg-data-cron-manager.php | Custom schedule intervals and cron ownership |
| LIFE-DR-06 | includes/class-gg-data-activator.php, uninstall.php | Table ownership boundaries |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Cron schedule registration must occur before any lifecycle path attempts to schedule those events.
- The browser-side localStorage cleanup mechanism depends on a subsequent admin request after deactivation.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| LIFE-TBD-01 | 3.1 | Capability add/remove behavior is inconsistent in the current implementation and should be tracked separately from documentation migration. | Engineering | TBD |
| LIFE-TBD-02 | 3.1 | Prompt seeding and placeholder migration run on every admin version-check path even though they are guarded; operational cost has not been benchmarked. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Create a dedicated lifecycle package for plugin startup, pause, upgrade, and removal behavior | Keeps lifecycle contracts isolated from sync and logging subsystem narratives | Documentation migration decision |
| 2026-04-04 | Document implementation drift instead of fixing it during the migration | Maintains parity-first migration scope and separates documentation from corrective code changes | Documentation migration decision |