# Architecture Description: RAG Evaluation

Canonical path: docs/rag-evaluation/architecture.md

## 1. Purpose and Scope

Purpose:
Describe the architecture of the RAG evaluation subsystem used to execute configured prompts through the live RAG pipeline and produce framework-ready evaluation datasets.

In scope:
- Evaluation runner architecture and execution boundary.
- Prompt corpus and configuration loading contract.
- Canonical artifact generation and framework adapter architecture.
- Run manifest design and provenance model.
- Requirement-linked architecture decisions.

Out of scope:
- Benchmark runner architecture (fully independent component).
- Class-level or function-level implementation design.
- Sprint planning and task decomposition.
- Provider-specific model orchestration internals.

## 2. Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Product owner | Evaluation evidence supports pipeline quality decisions. | High |
| Engineering lead | Runner is reproducible, transport-agnostic, and framework-extensible. | High |
| ML engineer | Framework adapter output is schema-compatible and directly consumable. | High |
| QA engineer | Canonical artifacts are stable, inspectable, and traceable. | High |
| Operations | Evaluation runs are portable across host, VM, container, and CI environments. | Medium |

## 3. Architecture Context (AV-01)

System context summary:
The RAG evaluation subsystem operates adjacent to the RAG benchmark subsystem but is fully independent. It executes configured prompts through shipped `wp gg-data evaluation` commands, produces canonical JSONL and framework-specific adapter output, and writes a run manifest for provenance tracking.

Interfaces at context level:
- CLI execution interface via `wp gg-data evaluation` commands.
- WordPress command interface (WP-CLI) for operator invocation and live prompt execution.
- Local JSON configuration file for runtime parameters and prompt corpus, with a shipped template inside the plugin and a writable site-local config under uploads.
- Canonical artifact filesystem boundary for JSONL and manifest output.
- Framework adapter output boundary for framework-specific JSONL or dataset files.
- No interface with the RAG benchmark runner.

## 4. Architecture Views

### 4.1 Container or Component View (AV-02)

Major elements:
- Evaluation runner entrypoint.
- Configuration loader and validator.
- Prompt validator.
- Canonical artifact writer.
- Framework dispatcher.
- Ragas adapter.
- Manifest writer.

Element responsibilities:

| Element | Responsibility |
|---|---|
| Runner entrypoint | Parse options, coordinate evaluation lifecycle, and control run flow. |
| Config loader | Load and validate runtime configuration and prompt corpus definitions. |
| Prompt validator | Check required fields, field types, and ID uniqueness across the corpus. |
| Canonical writer | Produce framework-agnostic JSONL from validated, processed prompts. |
| Framework dispatcher | Select and invoke the correct framework adapter based on the configured framework value. |
| Ragas adapter | Transform canonical prompt data into Ragas `SingleTurnSample`-compatible JSONL. |
| Manifest writer | Write a JSON run manifest recording provenance, counts, framework, and artifact locations. |

### 4.2 Runtime Interaction View (AV-03)

High-level runtime interaction flow:
1. Operator invokes `wp gg-data evaluation run` with configuration path and options.
2. Runner loads and validates evaluation config and prompt corpus.
3. Runner resolves execution scope using the full configured prompt corpus or an explicit single-prompt filter.
4. Runner initializes timestamped output directory with raw and framework subdirectories.
5. Runner executes each selected prompt through the abilities manager's RAG answer pipeline in-process and writes raw plus extracted JSON artifacts.
6. Canonical writer produces JSONL from pipeline results to `prompts.jsonl` at the run root.
7. Framework dispatcher invokes the configured framework adapter.
8. Ragas adapter writes `frameworks/ragas/dataset.jsonl` from canonical prompt data.
9. Manifest writer writes `manifest.json` recording run identity, counts, framework, artifact paths, and timestamp.
10. Runner emits artifact summary to STDOUT for operator verification.

### 4.3 Deployment and Environment View (AV-04)

Deployment summary:
- Execution transport is intentionally environment-agnostic.
- Supported operation includes host shell, VM, containerized local environment, and CI runner.
- Runtime artifacts are stored under the site uploads tree by default to keep transient evaluation data separated from durable planning docs and to survive plugin updates.

Environment constraints:
- WordPress and WP-CLI runtime available in the selected environment.
- Filesystem write access to artifact output root.

Artifact directory structure per run:
```
wp-content/uploads/gregius-data/artifacts/evaluation/YYYYMMDD-HHMMSS/
  prompts.jsonl
  errors.jsonl                  (only present when prompts fail)
  raw/
    01.json
    01.raw.txt
    02.json
    02.raw.txt
  frameworks/
    ragas/
      dataset.jsonl
  manifest.json
```

## 5. Architectural Decisions

### Decision AD-01: WordPress-native WP-CLI evaluation orchestration

- Status: Accepted
- Decision: Use shipped `wp gg-data evaluation` commands as the evaluation orchestration entrypoint.
- Rationale: Aligns evaluation execution with the plugin's native command surface and makes the workflow available in packaged installs.
- Alternatives considered:
  - Python runner (closer to Ragas native environment).
  - Shell-only orchestration script.
  - Node-based runner.
- Consequences:
  - Positive: Language alignment, simpler dependency model, consistent operator experience.
  - Tradeoff: Framework-native Python APIs are not callable directly; adapter output files bridge the gap.
- Traceability: RE-FR-01, RE-OR-01.

### Decision AD-02: Independent config schema, no benchmark coupling

- Status: Accepted
- Decision: The evaluation runner uses its own standalone configuration file and prompt corpus schema. No programmatic coupling to benchmark runner config or benchmark artifact output is permitted.
- Rationale: Benchmark and evaluation are independent concerns with different scale, purpose, and dataset design requirements. Coupling creates implicit dependencies and design compromises on both sides.
- Alternatives considered:
  - Shared config schema between benchmark and evaluation.
  - Evaluation runner reads from prior benchmark artifact directories.
  - Shared prompt/sample matrix with evaluation-specific extension fields.
- Consequences:
  - Positive: Both tools remain clean, focused, and independently maintainable.
  - Tradeoff: Prompt authors must maintain separate config files; no automatic reuse pathway.
- Traceability: RE-FR-01, RE-DR-01, RE-DR-11.

### Decision AD-03: Canonical-first artifact model

- Status: Accepted
- Decision: Always produce a framework-agnostic canonical JSONL artifact first, then produce framework adapter output by transforming canonical data. No framework adapter writes directly from raw config.
- Rationale: Canonical artifacts are stable, inspectable, and reusable across frameworks. They decouple the evaluation dataset from any one framework's schema evolution. They also provide a stable audit trail independent of adapter changes.
- Alternatives considered:
  - Framework-only output without canonical layer.
  - Parallel independent writers (config → framework N directly).
  - Single canonical that doubles as Ragas input without a separate adapter step.
- Consequences:
  - Positive: Framework adapters become thin and replaceable. Canonical artifacts survive framework deprecations.
  - Tradeoff: Minor duplication between `prompts.jsonl` and adapter output in early stages when Ragas fields closely match canonical fields.
- Traceability: RE-FR-06, RE-FR-07, RE-DR-07, RE-DR-08, RE-QR-06.

### Decision AD-04: Framework adapter pattern with future-proof enum validation

- Status: Accepted
- Decision: Use a `--framework` CLI flag with explicit enum validation. Accepted values are defined at parse time. Unrecognized values are rejected immediately with a list of supported frameworks. New frameworks are added by extending the enum list and adding an adapter.
- Rationale: Provides a clear extension contract. Prevents silent fallbacks. Makes the supported framework set self-documenting at every invocation. Operators get accurate error messages before any processing begins.
- Alternatives considered:
  - Plugin-based dynamic framework loading.
  - Flag-free implicit framework detection from config.
  - Framework flag optional, defaulting to none.
- Consequences:
  - Positive: Clear extension seams. No silent degradation. Accurate operator feedback.
  - Tradeoff: Adding a framework requires editing the enum validation list in addition to the adapter class/function.
- Traceability: RE-FR-07, RE-FR-08, RE-OR-05, RE-OR-08.

### Decision AD-05: Run manifest as provenance record

- Status: Accepted
- Decision: Write a `manifest.json` at the end of every run recording run identity, schema version, framework, sample type, sample count, scope, execution counts, failed prompt count, config path, artifact paths (with SHA-256 integrity hashes), error artifact, scope detail with failure breakdown, and adapter paths (with SHA-256 hashes).
- Rationale: Provides a stable provenance record for reproducibility audits, cross-run comparisons, and downstream pipeline consumption. Enables tooling to locate artifacts without directory scanning.
- Alternatives considered:
  - No manifest; artifact layout is self-describing.
  - Manifest embedded in canonical JSONL header line.
  - Manifest only when explicitly requested via flag.
- Consequences:
  - Positive: Enables automation, audit, and cross-run analysis.
  - Tradeoff: Manifest must be kept consistent with artifact state; partial runs produce incomplete manifests.
- Traceability: RE-FR-09, RE-DR-09, RE-QR-03.

### Decision AD-06: Ragas SingleTurnSample as initial adapter target

- Status: Accepted
- Decision: The first and currently only framework adapter produces Ragas `SingleTurnSample`-compatible JSONL. Field mapping follows the Ragas schema directly.
- Rationale: Ragas is the selected initial evaluation framework. The SingleTurnSample schema covers the primary evaluation scenario of single-query, single-response interactions with retrieved context.
- Alternatives considered:
  - Ragas multi-turn support in v1.
  - DeepEval as the initial adapter.
  - Generic adapter for all frameworks.
- Consequences:
  - Positive: Focused, correct v1 adapter with clear field mapping.
  - Tradeoff: Multi-turn support and additional framework adapters require future development.
- Traceability: RE-FR-07, RE-DR-07, RE-DR-08, RE-QR-02.

## 6. Constraints and Assumptions

Constraints:
- Evaluation subsystem must preserve transport-agnostic execution policy.
- No programmatic coupling to benchmark runner or benchmark artifact directories is permitted.
- Canonical artifact format must remain stable for a given schema version.
- Runtime artifact default location remains outside docs.

Assumptions:
- Prompt corpus is authored by the operator before command execution and the live response/context fields are generated during the run.
- Ragas consumption of adapter output occurs outside this runner in a Python environment.
- Additional framework adapters will be added in future iterations as evaluation tooling matures.
- Prompt IDs are treated as stable identifiers and governed by the same stability norms as benchmark prompt IDs.
