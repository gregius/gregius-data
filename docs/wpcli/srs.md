# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | WP-CLI Interface |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| SyRS Reference | N/A - no standalone system-level CLI package |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data WP-CLI interface must do to expose automation and operator workflows for sync, vectors, answer, listing, and log operations.

### 1.2 System Scope

The WP-CLI subsystem includes:
- Command namespace bootstrap and command registration for `wp gg-data`
- Sync command family for posts, terms, and full sync workflows
- Vector command family for generate and rebuild workflows
- RAG answer command and optional prompt override behavior
- Listing commands for configured connections and available models
- Logs command family for list, export, purge, and stats workflows
- Output formatting contracts for table/json/csv and deterministic automation usage

Software identifier: gregius-data-wpcli

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The CLI layer is an operator-facing interface that delegates business logic to existing service managers (`GG_Data_Post_Sync`, `GG_Data_Taxonomy_Sync`, `GG_Data_Vector_Generator`, `GG_Data_Abilities_Manager`, `GG_Data_Logger`) while standardizing validation, command routing, and output behavior.

#### 1.3.2 System Functions Summary

- Register command namespace and six command families under `gg-data`.
- Validate required/optional options and enforce safe defaults.
- Process batch workloads for sync and vectors with progress and memory cleanup.
- Execute RAG answers through Abilities manager with optional prompt overrides.
- Expose inventory commands for connections and models via abilities contracts.
- Expose logs operations for list/export/purge/stats via logger contracts.
- Emit machine-consumable output for automation through `--format` options.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | WP-CLI commands for maintenance and diagnostics |
| DevOps / Automation | Technical | Scheduled jobs and scripted operations using JSON/CSV output |
| Plugin Developer | Technical | Command handlers and delegated service manager contracts |

## 2. References

- `docs/wpcli/architecture.md`
- `docs/wpcli/developer-documentation.md`
- `includes/cli/class-gg-data-cli.php`
- `includes/cli/class-gg-data-cli-sync.php`
- `includes/cli/class-gg-data-cli-vectors.php`
- `includes/cli/class-gg-data-cli-answer.php`
- `includes/cli/class-gg-data-cli-list-connections.php`
- `includes/cli/class-gg-data-cli-list-models.php`
- `includes/cli/class-gg-data-cli-logs.php`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| CLI-FR-01 | The software MUST register the `gg-data` namespace and the command families `sync`, `vectors`, `answer`, `list-connections`, `list-models`, and `logs`. | Must |
| CLI-FR-02 | The software MUST provide sync subcommands `posts`, `terms`, and `all`. | Must |
| CLI-FR-03 | The software MUST provide vectors subcommands `generate` and `rebuild`. | Must |
| CLI-FR-04 | The software MUST provide an `answer` command with a required `<query>` positional argument. | Must |
| CLI-FR-05 | The software MUST provide list commands for connections and models. | Must |
| CLI-FR-06 | The software MUST provide logs subcommands `list`, `export`, `purge`, and `stats`. | Must |
| CLI-FR-07 | The software MUST support connection selection through `--connection` with a default value of `gregius-data` across applicable commands. | Must |
| CLI-FR-08 | The software MUST support command output formats through `--format` according to each command contract (`table`, `json`, `csv` as implemented). | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| CLI-DR-01 | Sync commands MUST accept `--post-type`, `--batch-size`, and `--dry-run` contracts where implemented and expose per-type summary output. | Must |
| CLI-DR-02 | Vectors commands MUST accept `--embedding-model`, `--post-type`, `--batch-size`, and output summary metrics including processed, failed, and duration values. | Must |
| CLI-DR-03 | Answer command MUST support optional model overrides (`--embedding-model`, `--agentic-model`, `--rerank-model`, `--answer-model`) and optional prompt overrides (`--prompt-id` or `--prompt`). | Must |
| CLI-DR-04 | List commands MUST return connection and model inventories using abilities-manager response payloads. | Must |
| CLI-DR-07 | `list-connections` SHOULD support optional embedding-context expansion flags (`--with-embedding-models`, `--with-model-details`) while preserving default output contracts when omitted. | Should |
| CLI-DR-05 | Logs list and export commands MUST validate level/component values against logger-provided enumerations before execution. | Must |
| CLI-DR-06 | Logs purge command MUST enforce `days >= 1` and support interactive confirmation bypass with `--yes`. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| CLI-OR-01 | Commands that accept `--connection` MUST validate that the connection exists in configured settings before delegated execution. | Must |
| CLI-OR-02 | Sync and vectors commands MUST validate `--batch-size` and clamp invalid values to safe defaults or bounded maxima. | Must |
| CLI-OR-03 | Sync and vectors commands SHOULD perform memory cleanup between batches. | Should |
| CLI-OR-04 | Batch-oriented commands SHOULD report progress using WP-CLI progress bars or equivalent progress output. | Should |
| CLI-OR-05 | `answer --no-track` MUST suppress interaction tracking through the tracking filter. | Must |
| CLI-OR-06 | Commands MUST terminate with explicit CLI errors on invalid critical inputs (for example invalid connection, invalid prompt flags, invalid log filters). | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| CLI-QR-01 | Command output contracts MUST remain deterministic enough for automation when `--format=json` or `--format=csv` is used. | CLI output contract inspection |
| CLI-QR-02 | The CLI layer MUST keep command handlers thin and delegate domain logic to existing service managers. | Code-structure inspection |
| CLI-QR-03 | Long-running operations SHOULD expose bounded batch controls and status summaries to reduce operational ambiguity. | Command behavior inspection |
| CLI-QR-04 | Prompt override handling MUST detect conflicting prompt options and ambiguous title matches with explicit error messages. | Answer command validation test |
| CLI-QR-05 | Command registration MUST no-op when `WP_CLI` is unavailable. | Bootstrap behavior inspection |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Command registration and command invocation checks | CLI command execution + code-path inspection |
| Data/contract requirements | Option/argument and output schema checks | CLI contract review + command outputs |
| Operations requirements | Validation and error-path checks | Invalid-input command runs + code inspection |
| Quality requirements | Delegation and deterministic output checks | Source review + automation output checks |

Acceptance baseline:
- All registered command families are available and route to correct handlers.
- Input validation and defaults behave as specified.
- Output formats are stable for human and automation usage.
- Delegated service-manager boundaries remain intact.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| CLI-FR-01, CLI-QR-05 | `includes/cli/class-gg-data-cli.php` | Namespace and command registration contracts |
| CLI-FR-02, CLI-DR-01, CLI-OR-01..04 | `includes/cli/class-gg-data-cli-sync.php` | Sync subcommands, validation, batching, progress, memory cleanup |
| CLI-FR-03, CLI-DR-02, CLI-OR-01..04 | `includes/cli/class-gg-data-cli-vectors.php` | Vector commands, batch bounds, vocabulary step, summary output |
| CLI-FR-04, CLI-DR-03, CLI-OR-01, CLI-OR-05, CLI-OR-06, CLI-QR-04 | `includes/cli/class-gg-data-cli-answer.php` | Query contract, prompt override handling, tracking control, errors |
| CLI-FR-05, CLI-DR-04, CLI-DR-07 | `includes/cli/class-gg-data-cli-list-connections.php`, `includes/cli/class-gg-data-cli-list-models.php` | Connections/models inventory command contracts and optional list-connections enrichment flags |
| CLI-FR-06, CLI-DR-05, CLI-DR-06, CLI-OR-06 | `includes/cli/class-gg-data-cli-logs.php` | Logs management command contracts and validation |
| CLI-FR-07, CLI-FR-08, CLI-QR-01, CLI-QR-02 | `includes/cli/class-gg-data-cli-*.php` | Shared defaults, output formats, and delegation pattern |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- WP-CLI runtime and WordPress bootstrapping are environmental prerequisites for command execution.
- Command handlers intentionally delegate domain operations to existing plugin services.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| CLI-TBD-01 | 3.2 | Some output payload fields remain manager-defined and are not globally normalized across all commands. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Split WPCLI docs into canonical SRS/architecture/developer package | Align subsystem documentation with plugin-wide canonical ISO structure and retire monolithic docs | Documentation migration initiative |