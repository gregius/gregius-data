# Architecture Description: WP-CLI Interface

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** WP-CLI  
**Version:** 1.0.0  
**Date:** 2026-04-04  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data exposes operator workflows through WP-CLI while delegating domain behavior to existing managers.

**Scope:**
- CLI bootstrap and command registration
- Command-handler decomposition by command family
- Validation, batching, and output orchestration patterns
- Runtime flows for sync, vectors, answer, benchmark, evaluation, listing, and logs operations
- Shipped benchmark and evaluation command families under `wp gg-data` with site-local config and uploads-based artifact storage

**Explicitly Excluded:**
- Service-manager internal implementations
- Admin dashboard orchestration details
- REST route behavior (covered in REST subsystem docs)

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site Administrators | Reliable maintenance and diagnostics commands | High |
| DevOps/Automation | Deterministic, scriptable output and safe defaults | High |
| Developers | Thin CLI layer with stable delegation boundaries | High |
| Support/Ops | Clear error messages for invalid runtime configuration | Medium |

---

## 2. Context View (AV-01)

### 2.1 Context

```
Operator / Automation Script
            |
            v
      wp gg-data <command>
            |
            v
   GG_Data_CLI bootstrap + command classes
            |
   +--------+----------+----------+----------+
   |        |          |          |          |
   v        v          v          v          v
 Sync    Vectors     Answer      List       Logs
 handlers handlers   handler     handlers   handlers
   |        |          |          |          |
   +--------+----------+----------+----------+
            |
            v
   Delegated service managers and contracts
 (Post/Tax Sync, Vector Generator, Abilities, Logger)
```

### 2.2 Context Rationale

CLI acts as an orchestration shell that validates inputs, invokes manager APIs, and formats outputs. This separation reduces duplication and keeps runtime business logic centralized.

**Mapping to Requirements:**
- AV-01 -> CLI-FR-01..08
- AV-01 -> CLI-QR-01..03

---

## 3. Component View (AV-02)

### 3.1 Major Components

1. **CLI Bootstrap (`GG_Data_CLI`)**
- Loads command classes
- Registers `gg-data` namespace and subcommands
- Guards registration when `WP_CLI` runtime is not active

2. **Sync Commands (`GG_Data_CLI_Sync`)**
- Routes `posts`, `terms`, `all`
- Validates connection and batch controls
- Coordinates batched post/taxonomy sync and progress output

3. **Vectors Commands (`GG_Data_CLI_Vectors`)**
- Routes `generate`, `rebuild`
- Applies vector-specific batch bounds
- Coordinates vocabulary build and batch generation loops

4. **Answer Command (`GG_Data_CLI_Answer`)**
- Handles required `<query>` and model options
- Resolves prompt overrides and conflict errors
- Delegates answer execution to abilities manager

5. **List Commands (`GG_Data_CLI_List_Connections`, `GG_Data_CLI_List_Models`)**
- Delegates inventory retrieval to abilities manager
- Applies output formatting and optional model-type filtering
- Supports optional list-connections enrichment flags for per-connection embedding model context

6. **Logs Commands (`GG_Data_CLI_Logs`)**
- Routes `list`, `export`, `purge`, `stats`
- Validates level/component filters
- Delegates to logger APIs for management operations

### 3.2 Responsibility Mapping

| Component | Primary Responsibilities | Requirement Links |
|---|---|---|
| CLI Bootstrap | Register command surface and runtime guard | CLI-FR-01, CLI-QR-05 |
| Sync Commands | Sync command contracts, validation, batch orchestration | CLI-FR-02, CLI-DR-01, CLI-OR-01..04 |
| Vectors Commands | Vector command contracts and generation orchestration | CLI-FR-03, CLI-DR-02, CLI-OR-01..04 |
| Answer Command | Query contract, prompt resolution, tracking control | CLI-FR-04, CLI-DR-03, CLI-OR-05, CLI-QR-04 |
| List Commands | Connections/models inventory contracts | CLI-FR-05, CLI-DR-04 |
| Logs Commands | Logs operation contracts and validation | CLI-FR-06, CLI-DR-05, CLI-DR-06, CLI-OR-06 |

---

## 4. Runtime View (AV-03)

### 4.1 Registration Flow

```
Plugin bootstrap
   -> check WP_CLI constant/runtime
   -> require command class files
   -> register namespace and commands via WP_CLI::add_command()
```

### 4.2 Sync and Vectors Execution Flow

```
Command invoked
   -> parse options + defaults
   -> validate connection and batch size
   -> compute workload size
   -> iterate batches with progress updates
   -> call manager batch APIs
   -> cleanup cache/memory between iterations
   -> emit summary output by selected format
```

### 4.3 Answer Execution Flow

```
answer <query>
   -> validate required query
   -> resolve optional prompt override (--prompt-id or --prompt)
   -> validate connection
   -> optional no-track filter override
   -> execute_rag_answer() via abilities manager
   -> emit answer/sources/metadata output
```

### 4.4 List and Logs Execution Flow

```
list-connections/list-models
   -> delegate to abilities manager
   -> format response

logs list/export/purge/stats
   -> validate filters or days input
   -> delegate to logger methods
   -> print structured output or summaries
```

**Mapping to Requirements:**
- AV-03 -> CLI-DR-01..06
- AV-03 -> CLI-OR-01..06
- AV-03 -> CLI-QR-01, CLI-QR-03, CLI-QR-04

---

## 5. Architectural Decisions

### AD-01: Thin Command Handlers with Service-Manager Delegation

**Decision:** Keep command classes focused on parsing/validation/output and delegate domain behavior to existing managers.

**Alternatives Considered:**
- Implement full domain logic inside CLI classes
- Duplicate service flows specifically for CLI

**Rationale:**
- Preserves single source of domain behavior.
- Reduces drift between CLI, REST, and internal integrations.

**Consequences:**
- CLI behavior depends on downstream manager contracts.
- Contract changes require coordinated documentation updates.

**Requirement Links:** CLI-QR-02, CLI-FR-02..06

### AD-02: Bounded Batch Processing for Long Operations

**Decision:** Use explicit batch-size validation with configurable maxima and looped batch execution for sync/vectors.

**Alternatives Considered:**
- Single-pass processing without batching
- Unbounded user-defined batch sizes

**Rationale:**
- Reduces memory/time risk for large sites.
- Supports operational tuning through filter-based limits.

**Consequences:**
- Very small batches can increase run time.
- Requires progress/status messaging to preserve operator confidence.

**Requirement Links:** CLI-OR-02, CLI-OR-03, CLI-OR-04, CLI-QR-03

### AD-03: Multi-Format Output Contracts

**Decision:** Support human-readable `table` output and machine-readable `json`/`csv` where implemented.

**Alternatives Considered:**
- Table-only output
- JSON-only output

**Rationale:**
- Balances interactive operations and automation scripting.
- Aligns with common WP-CLI command ergonomics.

**Consequences:**
- Format-specific output shapes must remain stable.
- Additional testing is needed when output fields evolve.

**Requirement Links:** CLI-FR-08, CLI-QR-01

### AD-04: Shell-Authorized Operational Model

**Decision:** Rely on WP-CLI execution context (shell access + WP bootstrap) rather than HTTP capability/nonces for command entry.

**Alternatives Considered:**
- Replicate HTTP-style capability checks and nonce semantics

**Rationale:**
- Matches WP-CLI operational model and avoids redundant access mechanisms.

**Consequences:**
- Environment hardening and shell-access policy remain external controls.
- Documentation must clearly indicate operational trust boundaries.

**Requirement Links:** CLI-OR-06, CLI-QR-05

### AD-05: Explicit Error Surfaces for Contract Violations

**Decision:** Fail fast with explicit `WP_CLI::error()` messaging for invalid critical inputs.

**Alternatives Considered:**
- Silent fallback for invalid options
- Deferred errors inside manager calls

**Rationale:**
- Prevents ambiguous execution results in automation.
- Improves operator troubleshooting speed.

**Consequences:**
- Invalid inputs terminate current command invocation.

**Requirement Links:** CLI-OR-01, CLI-OR-02, CLI-OR-06, CLI-QR-04

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Rationale |
|---|---|---|
| C-01 | WP-CLI runtime is required for command registration and execution. | Command layer is not available in non-WP-CLI runtime contexts. |
| C-02 | Connection-dependent commands require configured settings entries. | Invalid connection names must fail before manager execution. |
| C-03 | Batch maxima for sync/vectors are bounded by filter-backed limits. | Prevents unsafe resource usage from unbounded user input. |

### 6.2 Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | Large workloads can produce long-running CLI jobs. | Medium | Tune batch size and use progress/summaries for observability. |
| R-02 | Downstream manager contract changes can break CLI output assumptions. | Medium | Maintain parity checks and update developer docs with command changes. |
| R-03 | Automation scripts may rely on unstable free-text output. | Medium | Prefer `--format=json`/`--format=csv` for automation use cases. |

---

## 7. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | CLI-FR-01..08, CLI-QR-01..03 |
| AV-02 | CLI-FR-01..06, CLI-DR-01..06, CLI-OR-01..06 |
| AV-03 | CLI-DR-01..06, CLI-OR-01..06, CLI-QR-01, CLI-QR-03, CLI-QR-04 |
| AD-01 | CLI-QR-02, CLI-FR-02..06 |
| AD-02 | CLI-OR-02, CLI-OR-03, CLI-OR-04, CLI-QR-03 |
| AD-03 | CLI-FR-08, CLI-QR-01 |
| AD-04 | CLI-OR-06, CLI-QR-05 |
| AD-05 | CLI-OR-01, CLI-OR-02, CLI-OR-06, CLI-QR-04 |

---

## 8. Readiness Checklist

- Scope and exclusions are explicit.
- Command bootstrap and all command families are represented.
- Runtime views cover registration and command execution paths.
- Architectural decisions include alternatives and consequences.
- Requirement-linked coverage mapping is present.
- Companion docs are available:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
