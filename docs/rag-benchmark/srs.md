# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | RAG Benchmark |
| Version | 1.0 |
| Date | 2026-03-29 |
| Author | Gregius |
| StRS Reference | N/A - direct architecture and roadmap derivation |
| SyRS Reference | N/A - SRS produced directly from planning and architecture artifacts |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the RAG Benchmark software component must do to provide repeatable, evidence-backed validation of RAG behavior for release readiness and regression detection.

### 1.2 System Scope

The software component includes benchmark configuration loading, prompt execution through command-line interfaces, artifact generation, and scorecard output for human review.

Software identifier: gregius-data-rag-benchmark

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The benchmark is a plugin-adjacent quality validation component that executes prompt suites against WordPress RAG responses and records durable evidence artifacts.

#### 1.3.2 System Functions Summary

- Load benchmark configuration and prompt matrix.
- Execute full or targeted benchmark prompts.
- Produce raw output, extracted JSON artifacts, and scorecard output.
- Produce scorecard with blank Verdict and Notes columns for manual human review.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Plugin Developer | Technical | CLI benchmark runner |
| QA Reviewer | Technical | Scorecard and artifact review |
| Product Owner | Technical/Operational | MVP benchmark evidence snapshot |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Benchmark run | One execution of the benchmark suite producing timestamped artifacts |
| Prompt matrix | Canonical set of prompt definitions with IDs, slugs, prompt text, and expected behavior |
| Scorecard | Markdown summary table linking prompt outcomes and artifacts |
| Artifact set | Raw output files, extracted JSON files, and scorecard for one run |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| MVP | Minimum Viable Product |
| CLI | Command-Line Interface |
| RAG | Retrieval-Augmented Generation |

## 2. References

- docs/rag-benchmark/architecture.md
- wp gg-data benchmark
- includes/cli/resources/benchmark/rag-benchmark-quality-config.example.json

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| RB-FR-01 | The software MUST load benchmark runtime configuration from a JSON configuration file before prompt execution starts. | Must |
| RB-FR-02 | The software MUST validate required runtime fields and terminate with a non-zero exit result when required fields are missing. | Must |
| RB-FR-03 | The software MUST execute the canonical prompt matrix by default when no prompt filter is provided. | Must |
| RB-FR-04 | The software MUST support execution of a single prompt by prompt ID when an explicit prompt filter is provided. | Must |
| RB-FR-05 | The software MUST execute prompts through the WordPress command interface and pass configured model selections for embedding, agentic, rerank, and answer processing. | Must |
| RB-FR-06 | The software MUST capture complete raw command output for each prompt in a prompt-specific raw artifact file. | Must |
| RB-FR-07 | The software MUST extract a JSON payload from mixed command output and store the extracted payload in a prompt-specific JSON artifact file. | Must |
| RB-FR-08 | The software MUST generate a Markdown scorecard with links to raw and JSON artifacts. By default, scorecard rows MUST include all configured prompts; when an explicit scorecard scope selects executed prompts, scorecard rows MUST include only prompts in the selected execution set. | Must |
| RB-FR-09 | The software MUST write artifacts to a timestamped output directory and MUST support explicit output directory override by user option. | Must |
| RB-FR-10 | The software MUST emit progress information and artifact locations to support operator verification and troubleshooting. | Must |
| RB-FR-11 | The software MUST provide a scorecard subcommand that generates a blank scorecard for review purposes. When a `--from-dir` option is provided pointing to a previous run's artifact directory, the software MUST read the `<slug>.json` artifacts from that directory and populate the Actual Behavior column from each artifact's `answer` field, writing the regenerated scorecard in-place. | Must |
| RB-FR-12 | The software MUST use the shared GG_Data_Format_Json_Output trait for consistent JSON artifact schema across benchmark and evaluation subsystems. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| RB-DR-01 | The prompt matrix contract MUST include stable prompt IDs, slugs, prompt text, and expected behavior text for each prompt. | Must |
| RB-DR-02 | The canonical release benchmark baseline MUST preserve prompt IDs without semantic reassignment. Current baseline covers 3 prompts (P1-P3). | Must |
| RB-DR-03 | The scorecard table schema MUST include columns for ID, Prompt, Expected Outcome, Raw Output, JSON Artifact, Actual Behavior, Verdict, and Notes. | Must |
| RB-DR-05 | JSON artifacts capture RAG response metadata (policy decision, evidence sources) as-is from the output for human reviewer inspection. | Must |
| RB-DR-07 | Artifact and scorecard links generated by the software are portable across supported execution transports (relative paths). | Must |
| RB-DR-08 | The scorecard header records run timestamp and site URL. Model set is recorded in individual per-prompt JSON artifacts. Verdict and Notes columns remain blank for manual human review. | Must |
| RB-DR-09 | The prompt matrix contract MAY include an optional prompt_id field referencing a custom system prompt post ID. | May |
| RB-DR-10 | The list-prompts subcommand MUST support --format=<table|json|csv> to control output formatting. | Must |
| RB-DR-11 | The validate-config subcommand MUST support --skip-connection-check to skip the live connection test in CI or offline environments. | Must |

### 3.3 Software Operations

| ID | Requirement | Priority |
|---|---|---|
| RB-OR-01 | The software MUST use PHP as the benchmark orchestration runtime. | Must |
| RB-OR-02 | The software MUST remain transport-agnostic and MUST NOT require a specific execution transport such as Docker, VM, host shell, or CI runner. | Must |
| RB-OR-03 | The software MUST default runtime artifacts to a location outside documentation directories. | Must |
| RB-OR-04 | The software SHOULD support targeted and full benchmark execution patterns without requiring code changes, including explicit scorecard-scope selection for targeted prompt reruns. | Should |
| RB-OR-05 | The software MUST fail fast on unknown command options and report usage guidance for correction. (WP-CLI handles unknown options automatically.) | Must |
| RB-OR-07 | The software SHOULD allow environment-specific binary and working-directory configuration without modifying source code. | Should |
| RB-OR-08 | The software MUST separate transient run artifacts from durable planning documentation snapshots. | Must |
| RB-OR-09 | The software MUST validate that --config=<path> resolves to a path within the permitted config directory using realpath() and prefix checking. | Must |
| RB-OR-10 | The software MUST detect and report duplicate prompt IDs in the configuration before prompt execution begins. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| RB-QR-01 | Benchmark artifacts SHOULD enable human reviewers to verify grounded single-entity answer behavior for supported prompts. | Scorecard review + JSON/source inspection |
| RB-QR-02 | Benchmark artifacts SHOULD enable human reviewers to verify compare behavior including evidence-aware handling and partial-answer behavior when only one entity is supported. | Scorecard review + JSON/source inspection |
| RB-QR-03 | Benchmark artifacts SHOULD enable human reviewers to verify truthful abstain behavior for unsupported prompts and reject fabricated evidence claims. | Scorecard review + metadata inspection |
| RB-QR-04 | Benchmark artifacts SHOULD enable human reviewers to verify deterministic ambiguity handling. | Scorecard review |
| RB-QR-05 | Benchmark artifacts MUST include metadata contract fields across compare and non-compare responses. | JSON contract inspection |
| RB-QR-06 | Benchmark artifacts MUST preserve raw output so human reviewers can detect fatal, blank, or contradictory fallback content. | Raw output review |
| RB-QR-07 | The benchmark process SHOULD preserve stable scoring semantics across repeated runs so trend comparisons remain meaningful. | Multi-run comparison |
| RB-QR-08 | The benchmark process SHOULD preserve portability of command and artifact contracts across execution transports. | Cross-transport run check |
| RB-QR-09 | MVP benchmark evidence MUST be suitable for human-reviewed release decisions with explicit PASS/PARTIAL/FAIL distribution. | Planning evidence review |
| RB-QR-10 | The benchmark process MUST support reproducible reruns using a stable prompt matrix and explicit model configuration. | Rerun consistency check |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Execution and artifact inspection | Benchmark runner output + artifact files |
| Data and contract requirements | Schema and content inspection | Scorecard + JSON artifact review |
| Operations requirements | Configuration and portability checks | Multi-environment run inspection |
| Quality requirements | Human-reviewed benchmark assessment | Scorecard verdicts + roadmap evidence snapshot |

Acceptance baseline for current MVP benchmark evidence:
- Release-go decision remains governed by roadmap benchmark policy.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| RB-FR-01 to RB-FR-12 | docs/rag-benchmark/architecture.md (Execution Model, CLI workflow), includes/class-gg-data-benchmark-service.php, includes/trait-gg-data-format-json-output.php | Command behavior, artifact outputs, scorecard regeneration, shared JSON formatting |
| RB-DR-01 to RB-DR-11 | docs/rag-benchmark/architecture.md (Stable comparison contract), config example JSON, includes/cli/class-gg-data-cli-benchmark.php | Prompt contract, metadata contract, evidence recording, CLI formatting options |
| RB-OR-01 to RB-OR-10 | docs/rag-benchmark/architecture.md (PHP-first, transport-agnostic, artifact model), includes/cli/class-gg-data-cli-benchmark.php | Operational constraints, transport policy, config validation, duplicate detection |
| RB-QR-01 to RB-QR-10 | docs/rag-benchmark/architecture.md (Goals, Validation Strategy) | Quality acceptance and scoring semantics |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first mode without separate BRS, StRS, or SyRS artifacts.
- Scorecard Verdict and Notes columns are filled manually by human reviewers. No automated scoring is implemented.
- Existing benchmark prompt matrix and IDs are treated as canonical for MVP evidence continuity.
- Runtime model outputs may vary in wording; policy and metadata contracts remain the primary deterministic scoring anchors where defined.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| RB-TBD-01 | 3.2, 3.4 | Formalized machine-checkable PASS/PARTIAL/FAIL criteria for all prompt classes remain incomplete. | Product and QA | TBD |
| RB-TBD-02 | 4 | Explicit release threshold policy for PARTIAL outcomes is defined in roadmap governance but not yet encoded in automated checks. | Product | TBD |
| RB-TBD-03 | 3.3 | Artifact retention policy lifecycle is implied but not yet codified as a fixed retention period. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-03-29 | Use SRS-only package for benchmark requirements | Fastest path to traceable implementation and QA handoff | Product and engineering direction |
| 2026-03-29 | Preserve RAG metadata (policy + retrieval keys) in JSON artifacts for human reviewer interpretation | Enables post-run review without mandating automated scoring; keeps artifacts review-agnostic | Benchmark policy update |
