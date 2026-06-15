# Architecture Description: Logs Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Logging  
**Version:** 1.0.0  
**Date:** 2026-04-04  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data centralizes operational logging with a database-backed write path and multi-interface management surface (REST, CLI, dashboard).

**Scope:**
- Log write pipeline and guardrails
- Logs storage schema and indexing model
- REST and WP-CLI management interfaces
- Dashboard data-store integration for logs consumption
- Sensitive context masking strategy

**Explicitly Excluded:**
- Feature-level business telemetry semantics per subsystem
- External log shipping services and third-party observability backends
- User-facing support runbooks

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site Administrators | Operational visibility and safe maintenance controls | High |
| Developers | Stable logging API and predictable context masking | High |
| DevOps/Automation | Scriptable log retrieval/export/purge contracts | High |
| Security Reviewers | Credential-safe context persistence | High |

---

## 2. Context View (AV-01)

### 2.1 Context

```
Subsystem Emitters (RAG/Search/Sync/Vectors/Connection/Model/Cron/System)
                                  |
                                  v
                         GG_Data_Logger (write pipeline)
                                  |
                                  v
                     WordPress DB table: {prefix}gg_data_logs
                                  |
              +-------------------+-------------------+
              |                   |                   |
              v                   v                   v
  REST Logs Controller    WP-CLI Logs Commands    Dashboard Logs Store/UI
  (/gg-data/v1/logs/*)      (wp gg-data logs *)     (LogsPage + store)
```

### 2.2 Context Rationale

A shared logger service prevents subsystem-specific divergence in log structure and security handling. Read/manage interfaces are separated from write concerns to support administrative operations without changing emitters.

**Mapping to Requirements:**
- AV-01 -> LOG-FR-01..09
- AV-01 -> LOG-OR-01..05

---

## 3. Component View (AV-02)

### 3.1 Major Components

1. **Logger Core (`GG_Data_Logger`)**
- Validates level/component values
- Applies write guardrails (enabled/debug/threshold)
- Masks sensitive context recursively
- Persists records and supports filtered reads, stats, export, purge
- Manages schema creation for single-site and multisite contexts

2. **REST Logs Controller (`GG_Data_REST_Logs_Controller`)**
- Registers logs routes and request arguments
- Enforces capability checks (`manage_options`)
- Returns standardized success/error envelopes

3. **CLI Logs Commands (`GG_Data_CLI_Logs`)**
- Exposes list/export/purge/stats operations
- Validates level/component values and formats output for automation

4. **Dashboard Logs Store/UI**
- Fetches logs/stats/settings via REST
- Supports filters, pagination, auto-refresh, and export actions
- Presents context and severity-aware display states

### 3.2 Responsibility Mapping

| Component | Primary Responsibilities | Requirement Links |
|---|---|---|
| Logger Core | Write validation, masking, persistence, query/export/purge, schema lifecycle | LOG-FR-01..03, LOG-FR-06..08, LOG-DR-01..04, LOG-OR-01..03, LOG-QR-03..04 |
| REST Controller | Route contracts, permissions, settings operations | LOG-FR-04, LOG-FR-09, LOG-DR-05..07, LOG-OR-04 |
| CLI Commands | Operator automation for list/export/purge/stats | LOG-FR-05, LOG-QR-05 |
| Dashboard Store/UI | Admin-facing log retrieval and filtering workflows | LOG-FR-06, LOG-FR-09 |

---

## 4. Runtime View (AV-03)

### 4.1 Write Path

```
Emitter calls logger->log(message, level, component, connection, context)
    |
    +--> check logging enabled
    +--> validate level/component
    +--> debug-mode and threshold gating
    +--> mask sensitive context recursively
    +--> insert into {prefix}gg_data_logs
    +--> return insert id (or false on safe-fail)
```

### 4.2 Read Path (REST/CLI/UI)

```
Consumer request with filters
    |
    +--> normalize pagination and filters
    +--> validate level/component values
    +--> build WHERE clauses + sort
    +--> query logs table
    +--> decode context JSON
    +--> return structured result set and pagination metadata
```

### 4.3 Maintenance Path (Purge/Settings/Export)

```
Purge request -> normalize days -> delete rows older than threshold
Settings request -> read/update options (enabled, level, retention_days)
Export request -> query filtered logs -> format CSV or JSON output

### 4.4 Scheduled Retention Path

```
Activation (single-site or per-site multisite context)
  |
  +--> schedule gg_data_daily_log_retention_purge (once per site)

Cron event callback
  |
  +--> read gg_data_log_retention_days (site option)
  +--> apply gg_data_log_retention_days filter
  +--> emit gg_data_before_purge_logs
  +--> purge rows from current site's {prefix}gg_data_logs only
  +--> emit gg_data_after_purge_logs
  +--> write observability log context
```
```

**Mapping to Requirements:**
- AV-03 -> LOG-FR-06..09
- AV-03 -> LOG-OR-01..04
- AV-03 -> LOG-QR-01..03

---

## 5. Architectural Decisions

### AD-01: Database-Backed Logs with Site-Scoped Prefix Tables

**Decision:** Persist logs in WordPress database tables (`{prefix}gg_data_logs`) rather than files.

**Alternatives Considered:**
- File-based logs per environment
- External logging service as primary store

**Rationale:**
- Works across single-site and multisite contexts.
- Enables SQL filtering, pagination, stats, and export.
- Keeps operations within WordPress-native deployment assumptions.

**Consequences:**
- Table growth requires operational housekeeping.
- Query/index discipline is required for performance.

**Requirement Links:** LOG-FR-01, LOG-QR-04, LOG-OR-05

### AD-02: Centralized Guardrail Pipeline for Writes

**Decision:** Apply enablement, debug gating, and minimum-level threshold checks in logger core before writes.

**Alternatives Considered:**
- Emitter-side gating per subsystem
- Always-write then filter at read time

**Rationale:**
- Keeps write behavior consistent across all emitters.
- Reduces unnecessary storage volume at source.

**Consequences:**
- Misconfigured thresholds can hide useful diagnostics.
- Requires clear settings visibility for operators.

**Requirement Links:** LOG-OR-01, LOG-OR-02, LOG-OR-03

### AD-03: Recursive Sensitive-Data Masking with Usage-Metric Allowlist

**Decision:** Mask sensitive keys recursively while preserving token usage counters.

**Alternatives Considered:**
- Flat-only masking
- Full context redaction

**Rationale:**
- Maintains security posture without losing useful operational telemetry.
- Handles nested context structures from complex emitters.

**Consequences:**
- Key-name heuristics may require maintenance as payloads evolve.
- Component-level schema discipline still matters.

**Requirement Links:** LOG-DR-03, LOG-DR-04, LOG-QR-03

### AD-04: Admin-Gated Management Interfaces

**Decision:** Restrict REST logs management operations to `manage_options`.

**Alternatives Considered:**
- Authenticated-user access
- Mixed capability model by endpoint

**Rationale:**
- Logs may include sensitive operational details.
- Keeps management and purge functions under admin control.

**Consequences:**
- Non-admin troubleshooting must occur through other channels.

**Requirement Links:** LOG-OR-04, LOG-FR-04, LOG-FR-09

### AD-05: Interface Parity Across REST and WP-CLI

**Decision:** Provide equivalent operational capabilities (query/export/purge/stats/settings) across REST and CLI where relevant.

**Alternatives Considered:**
- REST-only management
- CLI-only maintenance

**Rationale:**
- Supports both dashboard workflows and automation pipelines.
- Reduces operational bottlenecks for larger environments.

**Consequences:**
- Contract changes require synchronized docs and validation in multiple interfaces.

**Requirement Links:** LOG-FR-05, LOG-FR-06, LOG-FR-07, LOG-FR-08, LOG-QR-05

### AD-06: Retention Governance Boundary and Site-Scoped Scheduling

**Decision:** Run retention as a dedicated daily cron event (`gg_data_daily_log_retention_purge`) per site context, constrained to `{prefix}gg_data_logs` only.

**Alternatives Considered:**
- Network-wide fanout in one callback
- Retention coupled to interaction data lifecycle

**Rationale:**
- Preserves multisite tenant boundaries and local retention policy.
- Prevents accidental cross-domain deletion behavior.
- Keeps `gg_interaction` as immutable system-of-record for per-turn history.

**Consequences:**
- Activation and deactivation must manage retention schedule per site in multisite.
- Hook listeners must remain site-context aware and side-effect safe.

**Requirement Links:** LOG-FR-10, LOG-OR-07, LOG-OR-08, LOG-DR-08

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Rationale |
|---|---|---|
| C-01 | Logs API permissions are admin-scoped by capability checks. | Prevents broad exposure of operational data. |
| C-02 | Level/component filters must use whitelisted values. | Guards query integrity and consistent categorization. |
| C-03 | Context schema is component-defined and not globally enforced. | Current implementation accepts flexible payload shapes. |

### 6.2 Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | High-volume logging can increase table size and query costs. | Medium | Use filtering, bounded pagination, and periodic purge operations. |
| R-02 | New sensitive keys might bypass masking heuristics. | Medium | Maintain key list and review payload contracts during feature changes. |
| R-03 | Divergent emitter context shapes can reduce cross-component comparability. | Medium | Maintain component-level examples and contract guidance in developer docs. |

---

## 7. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | LOG-FR-01..09, LOG-OR-01..05 |
| AV-02 | LOG-FR-01..09, LOG-DR-01..07, LOG-OR-01..05 |
| AV-03 | LOG-FR-06..09, LOG-OR-01..04, LOG-QR-01..03 |
| AD-01 | LOG-FR-01, LOG-OR-05, LOG-QR-04 |
| AD-02 | LOG-OR-01, LOG-OR-02, LOG-OR-03 |
| AD-03 | LOG-DR-03, LOG-DR-04, LOG-QR-03 |
| AD-04 | LOG-OR-04, LOG-FR-04, LOG-FR-09 |
| AD-05 | LOG-FR-05, LOG-FR-06, LOG-FR-07, LOG-FR-08, LOG-QR-05 |
| AD-06 | LOG-FR-10, LOG-OR-07, LOG-OR-08, LOG-DR-08 |

---

## 8. Readiness Checklist

- Scope and exclusions are explicit.
- Logger, REST, CLI, and dashboard components are represented.
- Runtime views cover write, read, and maintenance flows.
- Architecture decisions include alternatives and consequences.
- Requirement-linked coverage mapping is present.
- Companion docs are available:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
