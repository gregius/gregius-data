# Architecture Description: RAG Benchmark

Canonical path: docs/rag-benchmark/architecture.md

## 1. Purpose and Scope

Purpose:
Describe the architecture of the RAG benchmark subsystem used to execute repeatable benchmark runs, produce evidence artifacts for manual human review.

In scope:
- Benchmark runner architecture and execution boundary.
- Prompt matrix and configuration loading contract.
- Artifact generation and scorecard architecture.
- Scorecard-based human review boundary (manual offline process, not automated).
- Requirement-linked architecture decisions.

Out of scope:
- Class-level or function-level implementation design.
- Sprint planning and task decomposition.
- Provider-specific model orchestration internals.
- End-user UX behavior outside benchmark review context.

## 2. Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Product owner | Benchmark evidence is trustworthy for MVP release decisions. | High |
| Engineering lead | Execution is reproducible, transport-agnostic, and stable across changes. | High |
| QA reviewer | Scoring signals and artifacts are reviewable and traceable to requirements. | High |
| Operations | Benchmark runs are portable across host, VM, container, and CI environments. | Medium |

## 3. Architecture Context (AV-01)

System context summary:
The RAG benchmark subsystem sits adjacent to plugin runtime code and validates response behavior by driving command-line interactions through the WordPress command interface. It outputs durable artifacts into a benchmark artifact root and feeds reviewed evidence summaries into planning documents.

Interfaces at context level:
- CLI execution interface via `wp gg-data benchmark` commands.
- WordPress command interface (WP-CLI) for operator invocation and prompt execution.
- Local JSON configuration file for runtime parameters and prompt matrix, with a shipped template inside the plugin and writable site-local config under uploads.
- Artifact filesystem boundary for raw output, extracted JSON, and scorecard output.


## 4. Architecture Views

### 4.1 Container or Component View (AV-02)

Major elements:
- Benchmark command family.
- Configuration loader and validator.
- Prompt execution orchestrator.
- JSON extraction and artifact writer.
- Scorecard generator (`run` flow) and scorecard-only generator (`scorecard` flow).
- Shared JSON formatting trait (`GG_Data_Format_Json_Output`).
- Human review workflow and planning evidence capture (manual process, not a software component).

Element responsibilities:

| Element | Responsibility |
|---|---|
| Benchmark command family | Parse options, coordinate benchmark lifecycle, and control run flow. |
| Config loader | Load and validate runtime configuration and prompt definitions. |
| Prompt executor | Execute each prompt against the live RAG pipeline with configured models. |
| Artifact pipeline | Persist raw output, derive JSON payload, and preserve failure evidence. |
| Scorecard generator (run flow) | Produce stable review table and artifact links during benchmark execution. |
| Scorecard-only generator (scorecard flow) | Generate a blank scorecard template, or when `--from-dir` is provided, read existing JSON artifacts from the specified run directory and regenerate with the Actual Behavior column populated. |
| Shared JSON formatting trait | Provide consistent JSON artifact schema across benchmark and evaluation subsystems (`format_json_output`). |
| Human review workflow (manual) | Reviewer applies PASS, PARTIAL, FAIL verdicts using scorecard plus JSON and raw evidence (manual process, not software-automated). |

### 4.2 Runtime Interaction View (AV-03)

High-level runtime interaction flow:
1. Operator invokes `wp gg-data benchmark run`.
2. Command loads and validates benchmark config and prompt matrix.
3. Command initializes timestamped output directory under the site-local artifact root.
4. Command resolves execution scope using either the full configured prompt matrix or an explicit single-prompt filter.
5. Command resolves scorecard scope: all configured prompts by default, or selected execution prompts when explicitly requested.
6. For each selected execution prompt, service executes the live pipeline, stores raw output, writes normalized JSON, and preserves failure evidence.
7. Command generates scorecard containing prompt expectations, evidence links, and review columns for the resolved scorecard scope.
8. Operator can run `wp gg-data benchmark scorecard --from-dir=<path>` to regenerate the scorecard from artifacts in a specific previous run directory without re-executing prompts, or `wp gg-data benchmark scorecard` to generate a blank scorecard template for planning purposes.
9. Reviewer applies PASS, PARTIAL, FAIL verdicts using scorecard plus JSON and raw evidence. **Manual process — not software-automated.**
10. Reviewed benchmark summary is published into planning evidence snapshot for release decision context. **Manual process — no software publishes scorecards to planning docs.**

### 4.3 Deployment and Environment View (AV-04)

Deployment summary:
- Execution transport is intentionally environment-agnostic.
- Supported operation includes host shell, VM, containerized local environment, and CI runner.
- Runtime artifacts are stored under the site uploads tree by default to keep transient evidence separated from durable planning docs and to survive plugin updates.

Environment constraints:
- WordPress and WP-CLI runtime available in the selected environment.
- Filesystem write access to artifact output root.

## 5. Architectural Decisions

### Decision AD-01: WordPress-native WP-CLI benchmark orchestration

- Status: Accepted
- Decision: Use shipped `wp gg-data benchmark` commands as the benchmark orchestration entrypoint.
- Rationale: Aligns benchmark execution with the plugin's native command surface and makes the workflow available in packaged installs.
- Alternatives considered:
  - Shell-only orchestration script.
  - Node-based runner.
  - Python-based runner.
- Consequences:
  - Positive: Language alignment, simpler dependency model, easier maintenance in plugin repo.
  - Tradeoff: CLI-level process control and parsing complexity remain in PHP.
- Traceability: RB-FR-01, RB-FR-02, RB-OR-01, RB-OR-05.

### Decision AD-02: Command-surface execution without transport coupling

- Status: Accepted
- Decision: Execute prompts through WordPress command interface while keeping transport as an operator choice.
- Rationale: Preserves plugin contract portability and avoids coupling benchmark behavior to one infrastructure style. "Transport-agnostic" means no Docker, VM, or CI runner is required — the benchmark works wherever WP-CLI is available.
- Alternatives considered:
  - Docker-only benchmark execution contract.
  - Host-only execution contract.
  - CI-only execution contract.
- Consequences:
  - Positive: Works across local and CI contexts with shared benchmark semantics.
  - Tradeoff: Environment setup differences must be handled by operators.
- Traceability: RB-FR-05, RB-OR-02, RB-OR-07, RB-QR-08.

### Decision AD-03: Artifact-backed review model

- Status: Accepted
- Decision: Persist raw output, extracted JSON, and scorecard for each run as primary evidence surface.
- Rationale: Durable artifacts enable post-run diagnosis, stable review, and reproducible audits.
- Alternatives considered:
  - Terminal-output-only review.
  - JSON-only artifact output.
  - Scorecard-only output without per-prompt artifacts.
- Consequences:
  - Positive: Improves traceability and debugging confidence.
  - Tradeoff: Increases local artifact storage volume.
- Traceability: RB-FR-06, RB-FR-07, RB-FR-08, RB-OR-06, RB-OR-08.

### Decision AD-04: Metadata-first review signals

- Status: Accepted
- Decision: Preserve RAG metadata (policy decision + retrieval keys) in JSON artifacts for human reviewer interpretation, rather than treating metadata as an automated scoring signal.
- Rationale: Keeps artifacts review-agnostic; avoids embedding scoring policy that would require governance updates.
- Alternatives considered:
  - Wording-only review signals.
  - Automated abstain scoring from metadata.
  - No metadata preservation.
- Consequences:
  - Positive: Artifacts remain useful regardless of scoring policy changes.
  - Tradeoff: Reviewers must understand metadata semantics to use them.
- Traceability: RB-DR-05, RB-QR-03, RB-QR-05.

### Decision AD-05: Stable prompt matrix and scorecard schema

- Status: Accepted
- Decision: Preserve canonical prompt IDs and stable scorecard columns for cross-run comparability.
- Rationale: Benchmark trend analysis depends on fixed identifiers and stable contract structure.
- Alternatives considered:
  - Flexible prompt set with ad hoc IDs.
  - Dynamic scorecard schemas per run.
  - Prompt groups without fixed IDs.
- Consequences:
  - Positive: Supports consistent trend, regression, and release evidence comparisons.
  - Tradeoff: Prompt set changes require governed migration decisions.
- Traceability: RB-DR-01, RB-DR-02, RB-DR-03, RB-QR-07, RB-QR-10.

### Decision AD-06: Scorecard-based manual review for MVP baseline

- Status: Accepted
- Decision: Scorecard includes blank Verdict and Notes columns. Verdict application (PASS/PARTIAL/FAIL) is an offline manual process by human reviewers.
- Rationale: Separates data capture from assessment; no automated scoring is implemented.
- Alternatives considered:
  - Fully automated verdicting.
  - Binary pass/fail only with no partial outcomes.
  - Narrative-only review without explicit verdict classes.
- Consequences:
  - Positive: Data capture and assessment are decoupled; artifacts remain valid regardless of scoring rubric changes.
  - Tradeoff: Reviewer consistency depends on rubric governance (manual).
- Traceability: RB-DR-08, RB-QR-09.

### Decision AD-07: Explicit scorecard scope with backward-compatible default

- Status: Accepted
- Decision: Preserve all-configured scorecard rows as the default behavior and add explicit selected-scope scorecard rows for targeted remediation runs.
- Rationale: Keeps baseline comparability stable while supporting focused post-tuning reruns without unnecessary scorecard noise.
- Alternatives considered:
  - Always all-configured rows regardless of execution scope.
  - Always selected rows matching execution scope.
  - Separate scorecard generators for baseline versus remediation workflows.
- Consequences:
  - Positive: Backward compatibility for baseline evidence and improved ergonomics for targeted validation.
  - Tradeoff: Additional CLI option semantics and reviewer awareness of run scope.
- Traceability: RB-FR-04, RB-FR-08, RB-OR-04, RB-DR-03, RB-QR-10.

## 6. Constraints and Assumptions

Constraints:
- Benchmark subsystem must preserve transport-agnostic execution policy.
- Canonical prompt baseline covers 3 prompts (P1-P3).
- Runtime artifact default location remains outside docs and outside the plugin directory.
- Planning evidence snapshots must remain durable even when artifact directory is git-ignored.

Assumptions:
- SRS-first mode is authoritative for this architecture package, with no separate SyRS or StRS artifact.
- WordPress command interface behavior remains available and consistent in supported environments.
- Human review remains authoritative until machine-checkable scoring policy is approved.

## 7. Risks and Mitigations

| Risk ID | Description | Impact | Mitigation |
|---|---|---|---|
| R-01 | Reviewer drift in PASS, PARTIAL, FAIL interpretation across runs. | High | Maintain explicit verdict rubric and require review notes on changed classifications. |
| R-02 | Metadata contract drift breaks scoring assumptions for abstain and routing checks. | High | Treat metadata contract as versioned benchmark contract and include contract checks in every run review. |
| R-03 | Cross-transport differences reduce reproducibility confidence. | Medium | Run periodic cross-transport validation slices and compare contract-level outcomes. |
| R-04 | Prompt matrix modifications invalidate trend comparisons. | Medium | Govern prompt updates through versioned baseline changes and migration notes. |
| R-05 | Artifact growth affects local or CI storage over time. | Low | Apply retention practices and document archival policy in operations guidance. |

## 8. Architecture Coverage Mapping

| Architecture Item | Requirement IDs | Coverage Note |
|---|---|---|
| AV-01 | RB-OR-02, RB-OR-08, RB-QR-09 | Defines subsystem boundary and release evidence context. |
| AV-02 | RB-FR-01 to RB-FR-12, RB-DR-01 to RB-DR-11 | Maps major elements to execution, artifact contract, and shared formatting behavior. |
| AV-03 | RB-FR-03, RB-FR-04, RB-FR-06 to RB-FR-11, RB-OR-04, RB-QR-01 to RB-QR-06 | Captures lifecycle flow from run initiation through scorecard regeneration and manual review. |
| AV-04 | RB-OR-01, RB-OR-02, RB-OR-03, RB-OR-07, RB-QR-08 | Documents transport and deployment constraints with environment policy. |
| AD-01 | RB-FR-01, RB-FR-02, RB-OR-01, RB-OR-05 | Establishes runner orchestration architecture. |
| AD-02 | RB-FR-05, RB-OR-02, RB-OR-07, RB-QR-08 | Establishes command execution policy without hardcoding transport dependencies. |
| AD-03 | RB-FR-06, RB-FR-07, RB-FR-08, RB-OR-08 | Establishes artifact-backed review architecture. |
| AD-04 | RB-DR-05, RB-QR-03, RB-QR-05 | Documents metadata preservation strategy for reviewer interpretation. |
| AD-05 | RB-DR-01, RB-DR-02, RB-DR-03, RB-QR-07, RB-QR-10 | Preserves stable benchmark comparability architecture. |
| AD-06 | RB-DR-08, RB-QR-09 | Captures scorecard-based manual review architecture. |
| AD-07 | RB-FR-04, RB-FR-08, RB-OR-04, RB-DR-03, RB-QR-10 | Adds explicit scorecard-scope policy for targeted reruns while preserving baseline compatibility. |

Coverage gaps:
- None identified for major architecture decisions against current SRS requirement groups.

## 9. Architecture Readiness Summary

- [x] Architecture scope and boundary are explicit.
- [x] Stakeholders and concerns are documented.
- [x] Architecture context and major views are defined.
- [x] Major decisions are documented and requirement-linked.
- [x] Constraints, assumptions, and risks are documented.
- [x] Mandatory sections contain no unresolved placeholders.

Readiness outcome:
- Result: READY
- Reviewer: Gregius team
- Date: 2026-03-29
- Open issues:
  - Formal machine-checkable verdict rubric remains an SRS open issue.
  - Automated release threshold evaluation for PARTIAL outcomes remains pending.

## 10. Handoff

Next implementation skills:
- wp-plugin-development
- wp-qa-testing

Architecture handoff notes:
- Preserve transport-agnostic execution semantics and do not hardcode deployment transport assumptions.
- Preserve stable prompt IDs and scorecard schema unless a baseline migration is explicitly approved.
- Preserve RAG metadata fields in JSON artifacts for human reviewer interpretation; do not embed scoring policy in the artifact schema.
- Keep transient artifact storage outside docs and maintain durable planning snapshots for release governance.

## 11. Document Control

- Version: 1.0
- Status: Draft
- Author: Gregius team
- Last Updated: 2026-03-29
