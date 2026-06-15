# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Logging Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| SyRS Reference | N/A - no standalone system-level logging package |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data logging subsystem must do to store, query, export, and manage operational logs generated across plugin components.

### 1.2 System Scope

The logging subsystem includes:
- Database-backed log storage in WordPress-managed tables
- Structured logging API with levels, components, and JSON context
- Sensitive context masking before persistence
- REST API for listing, stats, export, purge, and settings
- WP-CLI commands for listing, exporting, purging, and stats
- Dashboard data-store integration for logs viewing and filtering

Software identifier: gregius-data-logs

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The subsystem is a shared operational service used by RAG, Search, Sync, Vectors, Connection, Model, Cron, and System components. It provides a common write contract and read-management interfaces through REST, WP-CLI, and dashboard UI.

#### 1.3.2 System Functions Summary

- Persist logs with timestamp, severity, component, connection, message, and optional context.
- Enforce log-level threshold and debug-mode behavior during writes.
- Mask sensitive keys in context payloads while preserving token usage metrics.
- Query logs with pagination, sorting, date range, level/component filters, and search.
- Provide stats grouped by level and component.
- Export filtered logs as CSV or JSON.
- Purge logs older than a provided day threshold.
- Expose and update runtime logging settings via REST.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | Dashboard logs UI, REST logs routes, WP-CLI logs commands |
| DevOps / Automation | Technical | WP-CLI logs workflows and REST integration |
| Plugin Developer | Technical | `GG_Data_Logger` API and component logging calls |

## 2. References

- `docs/logs/architecture.md`
- `docs/logs/developer-documentation.md`
- `includes/class-gg-data-logger.php`
- `includes/api/class-gg-data-rest-logs-controller.php`
- `includes/cli/class-gg-data-cli-logs.php`
- `assets/src/scripts/dashboard/pages/LogsPage.js`
- `assets/src/scripts/dashboard/stores/logs/actions.js`
- `assets/src/scripts/dashboard/stores/logs/selectors.js`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| LOG-FR-01 | The software MUST store logs in a WordPress database table named `{$wpdb->prefix}gg_data_logs` for each site context. | Must |
| LOG-FR-02 | The software MUST support the log levels `debug`, `info`, `warning`, `error`, and `critical`. | Must |
| LOG-FR-03 | The software MUST support component classification values `rag`, `search`, `sync`, `vectors`, `connection`, `model`, `cron`, `system`, and `legacy`. | Must |
| LOG-FR-04 | The software MUST expose logs REST routes for list, stats, export, purge, and settings operations under `gg-data/v1/logs`. | Must |
| LOG-FR-05 | The software MUST expose WP-CLI logs commands for list, export, purge, and stats operations. | Must |
| LOG-FR-06 | The software MUST support retrieving logs with pagination and filter criteria (level, component, connection, date range, search, sort). | Must |
| LOG-FR-07 | The software MUST support exporting logs as CSV and JSON with applied filter criteria. | Must |
| LOG-FR-08 | The software MUST support deleting old logs by day-threshold input. | Must |
| LOG-FR-09 | The software MUST expose runtime logging settings for logging enablement, minimum level, and retention-day value through REST settings routes. | Must |
| LOG-FR-10 | The software MUST execute a daily scheduled retention purge using cron event `gg_data_daily_log_retention_purge` in the current site context only. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| LOG-DR-01 | Each log record MUST include `id`, `logged_at`, `level`, `component`, `message` and MAY include `connection_id` and `context`. | Must |
| LOG-DR-02 | The logging context MUST be accepted as structured key/value data and persisted as JSON-encoded text when present. | Must |
| LOG-DR-03 | Sensitive context keys containing `api_key`, `password`, `secret`, `token`, or `authorization` MUST be masked before persistence. | Must |
| LOG-DR-04 | Usage metric keys `prompt_tokens`, `completion_tokens`, `total_tokens`, and `tokens_used` MUST remain unmasked. | Must |
| LOG-DR-05 | List and stats REST responses MUST return `{ success: true, data: ... }` envelopes on success. | Must |
| LOG-DR-06 | Purge REST responses MUST return deleted-row counts in the response payload. | Must |
| LOG-DR-07 | Settings REST responses MUST include both active settings and available levels/components metadata. | Must |
| LOG-DR-08 | Retention purge runs MUST expose extension hooks for effective days, pre-purge threshold, and post-purge deleted count, each with `site_id` context. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| LOG-OR-01 | The subsystem MUST skip writes when logging is disabled. | Must |
| LOG-OR-02 | The subsystem MUST skip `debug` writes unless debug mode is active. | Must |
| LOG-OR-03 | The subsystem MUST skip writes below the configured minimum log level threshold. | Must |
| LOG-OR-04 | Logs REST operations MUST require `manage_options` capability. | Must |
| LOG-OR-05 | The subsystem MUST support multisite table creation through site-context iteration at activation time. | Must |
| LOG-OR-06 | The subsystem SHOULD fail safely when insert operations fail (no fatal runtime disruption). | Should |
| LOG-OR-07 | The subsystem MUST guarantee retention purges are constrained to `gg_data_logs` and do not auto-delete `gg_interaction` records or meta. | Must |
| LOG-OR-08 | The subsystem MUST schedule and clear retention cron events per site context in multisite environments. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| LOG-QR-01 | Log query operations MUST enforce bounded pagination with maximum `per_page` limits. | API parameter validation test |
| LOG-QR-02 | Component and level filters MUST validate against known allowed values before query execution. | Input-validation test |
| LOG-QR-03 | Sensitive-data masking MUST recurse through nested context arrays. | Context masking test |
| LOG-QR-04 | Log table schema MUST include indexes for timestamp, level, component, and connection identifiers. | Schema inspection |
| LOG-QR-05 | CLI and REST management operations SHOULD provide deterministic structured outputs suitable for automation. | Contract inspection |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Route/command execution + logger API checks | REST/CLI checks + code-path inspection |
| Data/contract requirements | Schema and payload inspection | DB schema + response payload review |
| Operations requirements | Permission/threshold behavior checks | capability and settings variation tests |
| Quality requirements | Query bound checks and masking checks | API tests + nested context tests |

Acceptance baseline:
- Core logging write/read/export/purge/settings contracts are available and consistent.
- Permissions and thresholds behave as specified.
- Sensitive context values are masked while usage counters remain visible.
- Dashboard and automation consumers can use stable logs contracts.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| LOG-FR-01, LOG-FR-02, LOG-FR-03, LOG-DR-01, LOG-DR-02, LOG-QR-04 | `includes/class-gg-data-logger.php` | Table schema, levels/components, base log data model |
| LOG-FR-04, LOG-FR-06, LOG-FR-07, LOG-FR-08, LOG-FR-09, LOG-DR-05..07, LOG-OR-04 | `includes/api/class-gg-data-rest-logs-controller.php` | Route registration, params, responses, permissions |
| LOG-FR-05, LOG-QR-05 | `includes/cli/class-gg-data-cli-logs.php` | CLI list/export/purge/stats automation contracts |
| LOG-DR-03, LOG-DR-04, LOG-DR-08, LOG-QR-03 | `includes/class-gg-data-logger.php` | Sensitive-key masking, usage-key allowlist, retention hook contract |
| LOG-OR-01, LOG-OR-02, LOG-OR-03, LOG-OR-06, LOG-OR-07 | `includes/class-gg-data-logger.php` | Logging enablement, debug gating, level threshold, safe-fail behavior, retention boundary |
| LOG-FR-10, LOG-OR-08 | `includes/class-gg-data-log-retention.php`, `includes/class-gg-data-activator.php`, `includes/class-gg-data-deactivator.php`, `gregius-data.php` | Scheduled retention runtime and per-site lifecycle management |
| LOG-OR-05 | `includes/class-gg-data-logger.php`, `includes/class-gg-data-activator.php` | Multisite table creation during activation |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Dashboard logs views depend on the logs store and logs REST routes.
- Logging is an operational subsystem and is not part of vector/search sync payload contracts.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| LOG-TBD-01 | 3.2 | Component-specific context schema conventions are implementation-driven and not yet normalized into per-component contract schemas. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Split logging docs into canonical SRS/architecture/developer package | Align with plugin-wide ISO documentation structure and retire monolithic architecture docs | Documentation migration initiative |
