# RAG Evaluation Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Overview

This guide documents developer-facing usage and extension details for the RAG evaluation subsystem.

Primary references:
- SRS: [SRS reference](srs.md)
- Architecture: [Architecture reference](architecture.md)
- Command family: `wp gg-data evaluation`

Scope:
1. Runner execution contract
2. Configuration contract
3. Prompt corpus schema
4. CLI flag contract
5. Artifact layout
6. Framework adapter usage
7. Adding a new framework adapter
8. Troubleshooting

## 2. Quick Start

1. Copy config template:
   `cp wp-content/plugins/gregius-data/includes/cli/resources/evaluation/rag-evaluation-config.example.json wp-content/uploads/gregius-data/config/rag-evaluation-config.json`

2. Populate required config values:
   - `connection`
   - `framework`
   - `prompts[]` with at least one entry containing `id` and `user_input`

3. Run evaluation (full corpus):
   `wp gg-data evaluation run`

4. Run single prompt:
   `wp gg-data evaluation run --only=P1`

5. Override output directory:
   `wp gg-data evaluation run --output-dir=/tmp/rag-eval`

6. Show usage:
   `wp help gg-data evaluation`

7. Validate config:
   `wp gg-data evaluation validate-config`

8. List supported adapters:
   `wp gg-data evaluation adapters`

9. List configured prompts:
   `wp gg-data evaluation list-prompts`

[SRS: RE-FR-01, RE-FR-04, RE-FR-05, RE-FR-10]

## 3. Configuration Contract

### 3.1 Required top-level fields

| Field | Type | Description |
|---|---|---|
| `connection` | string | Named RAG connection to evaluate. |
| `framework` | string | Target evaluation framework. Accepted: `ragas`. |
| `prompts` | array | Non-empty array of prompt objects. |

### 3.2 Optional top-level fields

| Field | Type | Default | Description |
|---|---|---|---|---|
| `url` | string | (empty) | Site URL for provenance and multisite traceability. |
| `artifact_root` | string | `wp-content/uploads/gregius-data/artifacts/evaluation` | Root path for timestamped run directories. |
| `sample_type` | string | `single-turn` | Sample type. Currently only `single-turn` is supported. |

### 3.3 Prompt corpus schema

Each entry in `prompts` represents one evaluation sample that will be executed through the live RAG pipeline.

Required fields per prompt:

| Field | Type | Description |
|---|---|---|
| `id` | string | Stable unique prompt identifier (e.g. `P1`). |
| `user_input` | string | The user query submitted to the RAG pipeline. |
| `response` | string | Generated at runtime and written to artifacts, not configured ahead of time. |
| `retrieved_contexts` | array of strings | Generated at runtime and written to artifacts, not configured ahead of time. |

Optional fields per prompt:

| Field | Type | Description |
|---|---|---|
| `reference` | string | Ground-truth answer for reference-based metrics. |
| `reference_contexts` | array of strings | Ideal contexts for context precision/recall metrics. |
| `rubric` | string | Evaluation-specific scoring guidance for this prompt. |
| `prompt_id` | integer | System prompt post ID to use for this prompt. Omit or set to `0` to use the default system prompt. |

Behavioral notes:
1. Prompt IDs must be unique within a config. Duplicate IDs cause non-zero exit before any artifact is written.
2. Prompts without `reference` can still be evaluated with metrics that do not require a reference answer.
3. `reference` is strongly recommended for meaningful faithfulness and factual correctness metrics.
4. The shipped template lives inside the plugin, but the writable local config should live under `wp-content/uploads/gregius-data/config/` so it survives plugin updates.

[SRS: RE-DR-01, RE-DR-02, RE-DR-03, RE-DR-04, RE-DR-05, RE-DR-06, RE-FR-03]

## 4. CLI Flag Contract

| Flag | Value | Default | Description |
|---|---|---|---|---|
| `--only <ID>` | Sample ID string | (empty) | Process a single prompt by ID. |
| `--framework <name>` | `ragas` | (from config) | Override the target evaluation framework. |
| `--output-dir <path>` | Absolute path | (timestamped in artifact root) | Write artifacts to a timestamped subdirectory under the specified path. |
| `--skip-connection-check` | (none) | (unset) | Skip database connection validation. Only applies to `validate-config` and `run` subcommands. |
| `--help` / `-h` | (none) | | Print usage and exit. |

Framework flag behavior:
1. If `--framework` is provided, it overrides the `framework` field in config.
2. Unsupported framework values are rejected at parse time with exit code 1 and a message listing supported frameworks.
3. Currently supported frameworks: `ragas`.
4. Future frameworks (`deepeval`, `promptfoo`) will be supported by extending the enum and adding an adapter. Providing them now produces a clear unsupported error.

[SRS: RE-FR-05, RE-FR-07, RE-FR-08, RE-FR-10, RE-OR-05]

## 5. Runner Lifecycle

1. Parse args and options.
2. Load and validate config.
3. Validate `--framework` value (or config framework value).
4. Resolve output directory and initialize subdirectories.
5. Resolve execution prompt set (full corpus or `--only` prompt).
6. Execute each selected prompt through the live pipeline and write raw plus normalized JSON artifacts.
7. Write `prompts.jsonl` from canonical sample records built from live execution output.
8. Dispatch framework adapter.
9. Ragas adapter writes `frameworks/ragas/dataset.jsonl`.
10. Write `manifest.json`.
11. Print artifact summary.

[SRS: RE-FR-01, RE-FR-02, RE-FR-03, RE-FR-04, RE-FR-05, RE-FR-06, RE-FR-07, RE-FR-09, RE-FR-11]

## 6. Artifact Layout

Per-run directory:
```
wp-content/uploads/gregius-data/artifacts/evaluation/YYYYMMDD-HHMMSS/
  prompts.jsonl               Framework-agnostic JSONL; one JSON object per line per prompt.
  errors.jsonl                Failed-prompt errors (only present when prompts fail).
  raw/
    01.json
    01.raw.txt
    02.json
    02.raw.txt
  frameworks/
    ragas/
      dataset.jsonl         Ragas SingleTurnSample-compatible JSONL.
  manifest.json             Run provenance record.
```

`prompts.jsonl` fields per line:

| Field | Always present | Notes |
|---|---|---|
| `id` | Yes | Stable prompt ID from config. |
| `user_input` | Yes | |
| `response` | Yes | |
| `retrieved_contexts` | Yes | Array of strings. |
| `reference` | When present in config | |
| `reference_contexts` | When present in config | Array of strings. |
| `rubric` | When present in config | |

`manifest.json` fields:

| Field | Description |
|---|---|
| `run_id` | Timestamp string matching the run directory name. |
| `created_at` | ISO 8601 UTC datetime. |
| `schema_version` | Manifest schema version. Current: `1`. |
| `framework` | Configured framework for this run. |
| `sample_type` | Sample type for this run. Current: `single-turn`. |
| `sample_count` | Total prompts in corpus. |
| `scope` | Execution scope: `all` or `selected`. |
| `executed_count` | Number of prompts included in this run. |
| `failed_count` | Number of prompts that failed during execution. |
| `config` | Object with `path` (config file path) and `url` (site URL for provenance). |
| `prompts` | Object with `path` and `sha256` fields pointing to `prompts.jsonl` and its SHA-256 hash. |
| `errors` | Object with `path` and `sha256` fields pointing to `errors.jsonl` and its SHA-256 hash. Empty when no prompts failed (`path` is `""`). |
| `scope_detail` | Object with `total`, `executed`, `failed`, and `failure_breakdown` (grouped error messages with counts). |
| `adapters` | Object keyed by framework name; each value has `path` and `sha256`. |

[SRS: RE-DR-07, RE-DR-08, RE-DR-09, RE-DR-10, RE-FR-06, RE-FR-09]

## 7. Framework Adapter: Ragas

### 7.1 Output location

`frameworks/ragas/dataset.jsonl`

### 7.2 Field mapping

Ragas `SingleTurnSample` fields are mapped directly from canonical prompt data:

| Ragas field | Source |
|---|---|
| `user_input` | `user_input` |
| `response` | `response` |
| `retrieved_contexts` | `retrieved_contexts` |
| `reference` | `reference` (omitted when absent) |
| `reference_contexts` | `reference_contexts` (omitted when absent) |
| `rubric` | `rubric` (omitted when absent) |

### 7.3 Consuming adapter output in Python

```python
from ragas import EvaluationDataset

dataset = EvaluationDataset.from_jsonl("frameworks/ragas/dataset.jsonl")
```

[SRS: RE-DR-07, RE-DR-08, RE-QR-02]

## 8. Adding a New Framework Adapter

When a new evaluation framework is ready to be supported:

1. Add the framework name to the supported values in `includes/class-gg-data-evaluation-service.php`.
2. Add the adapter writer branch via the `gg_data_rag_evaluation_framework_adapter` filter (see `dispatch_framework()` in the service class).
3. Update `docs/rag-evaluation/developer-documentation.md` sections 4, 6, and 7.
4. Extend the SRS `RE-DR` requirements if the framework introduces new schema requirements.

The prompts dataset writer and manifest writer require no changes.

[SRS: RE-OR-08, RE-QR-06]

## 9. Troubleshooting

### Symptom: runner exits before any artifacts are written

Likely causes:
- Configuration file not found.
- Required config fields missing or empty.
- Unsupported framework value.
- Prompt validation failure (missing field or duplicate ID).

Resolution:
1. Check for config file at `wp-content/uploads/gregius-data/config/rag-evaluation-config.json`.
2. Review STDERR output for field names.
3. Validate prompt IDs for uniqueness.
4. Confirm framework value matches a supported entry.

### Symptom: `prompts.jsonl` is empty or has fewer lines than expected

Likely cause:
- `--only` flag filtered corpus to a single prompt.
- Prompt corpus in config is smaller than expected.

Resolution:
1. Run without `--only` for full corpus output.
2. Inspect `manifest.json` for `executed_count` and `sample_count`.

### Symptom: Ragas cannot load adapter dataset

Likely cause:
- Invalid JSONL (malformed JSON on one or more lines).
- Missing required Ragas fields.

Resolution:
1. Validate `frameworks/ragas/dataset.jsonl` with a JSONL parser.
2. Confirm `user_input`, `response`, and `retrieved_contexts` are present on every line.
3. Regenerate artifacts with current runner version.

### Symptom: manifest.json is missing or incomplete

Likely cause:
- Runner exited before reaching manifest write step (e.g. adapter write failure).

Resolution:
1. Inspect STDERR for runtime errors.
2. Check filesystem write permissions on artifact directory.

### Symptom: unsupported framework error for a listed framework

Likely cause:
- Framework is listed in this documentation as a future target but is not yet implemented.

Resolution:
1. Use `--framework ragas` (currently the only supported value).
2. Track implementation of additional adapters in the project roadmap.

## 10. Known Limitations

1. Only `single-turn` sample type is supported in v1.
2. Only the Ragas framework adapter is implemented in v1.
3. `reference` omission reduces metric coverage to reference-free metrics only.
4. Retention policy for long-term artifact storage is not yet codified.

## 11. Handoff Notes

When extending evaluation functionality:
1. Keep transport-agnostic execution policy intact.
2. Keep canonical-first artifact model intact.
3. Preserve manifest schema backward compatibility or increment `schema_version`.
4. Do not introduce programmatic coupling to the benchmark runner.
