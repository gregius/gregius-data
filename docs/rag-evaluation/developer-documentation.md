# RAG Evaluation Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Overview

This guide documents developer-facing usage and extension details for the RAG evaluation subsystem.

Primary references:
- SRS: [SRS reference](srs.md)
- Architecture: [Architecture reference](architecture.md)
- Command family: `wp gregius-data evaluation`

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
   - `models` with `embedding` and `answer` (optional: `agentic`, `rerank`, `evaluator`)
   - `prompts[]` with at least one entry containing `id` and `user_input`

3. Run evaluation (full corpus):
   `wp gregius-data evaluation run`

4. Run single prompt:
   `wp gregius-data evaluation run --only=P1`

5. Override output directory:
   `wp gregius-data evaluation run --output-dir=/tmp/rag-eval`

6. Show usage:
   `wp help gregius-data evaluation`

7. Validate config:
   `wp gregius-data evaluation validate-config`

8. List supported adapters:
   `wp gregius-data evaluation adapters`

9. List configured prompts:
   `wp gregius-data evaluation list-prompts`

[SRS: RE-FR-01, RE-FR-04, RE-FR-05, RE-FR-10]

## 3. Configuration Contract

### 3.1 Required top-level fields

| Field | Type | Description |
|---|---|---|
| `connection` | string | Named RAG connection to evaluate. |
| `framework` | string | Target evaluation framework. Accepted: `ragas`. |
| `models` | object | Model selection object (sub-fields below). |
| `prompts` | array | Non-empty array of prompt objects. |

`models` sub-fields:

| Sub-field | Required | Description |
|---|---|---|
| `embedding` | Yes | Embedding model key used for retrieval. |
| `answer` | Yes | LLM model ID for answer generation. |
| `agentic` | No | Agentic model ID for query routing and tool selection. |
| `rerank` | No | Rerank model ID. |
| `evaluator` | No | Evaluator model ID for retrieval-sufficiency grading. |

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

| Flag | Subcommand(s) | Value | Default | Description |
|---|---|---|---|---|
| `--config <path>` | run, list-prompts, validate-config | Absolute path | (default local config) | Path to the evaluation config file. |
| `--only <ID>` | run | Sample ID string | (empty) | Process a single prompt by ID. |
| `--framework <name>` | run | `ragas` | (from config) | Override the target evaluation framework. |
| `--output-dir <path>` | run | Absolute path | (timestamped in artifact root) | Write artifacts to a timestamped subdirectory under the specified path. |
| `--format <format>` | list-prompts | `table`, `json`, `csv` | `table` | Output format for the prompt list. |
| `--skip-connection-check` | validate-config | (none) | (unset) | Skip database connection validation. |
| `--help` / `-h` | all | (none) | | Print usage and exit. |

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

1. Add the framework name to the supported values in `includes/class-gregius-data-evaluation-service.php`.
2. Add the adapter writer branch via the `gregius_data_rag_evaluation_framework_adapter` filter (see `dispatch_framework()` in the service class).
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
4. Avoid introducing programmatic coupling to other plugin subsystems.

## 12. Public API Reference

This section records the evaluation subsystem's public symbols with their
current signatures. Code is the source of truth; line numbers are provided for
traceability.

### 12.1 Evaluation orchestration service

`includes/class-gregius-data-evaluation-service.php` — class
`Gregius_Data_Evaluation_Service` (uses trait `Gregius_Data_Format_Json_Output`).

| Method | Signature | Returns |
|---|---|---|
| `get_supported_frameworks` | `public function get_supported_frameworks()` | `array` — `array( 'ragas' )` |
| `get_template_path` | `public function get_template_path()` | `string` — shipped template path |
| `get_default_config_path` | `public function get_default_config_path()` | `string` |
| `get_default_artifact_root` | `public function get_default_artifact_root()` | `string` |
| `load_config` | `public function load_config( $config_path = '' )` | `array` — normalized config |
| `assert_connection_exists` | `public function assert_connection_exists( $connection )` | `void` — throws `RuntimeException` |
| `list_prompts` | `public function list_prompts( $config_path = '' )` | `array` |
| `get_config_summary` | `public function get_config_summary( $config_path = '', $skip_connection_check = false )` | `array` |
| `run` | `public function run( $assoc_args )` | `array` |

### 12.2 WP-CLI evaluation command

`includes/cli/class-gregius-data-cli-evaluation.php` — class
`Gregius_Data_CLI_Evaluation`, registered as `wp gregius-data evaluation`
(see `includes/cli/class-gregius-data-cli.php`).

| Subcommand | Method | Signature |
|---|---|---|
| `run` | `run` | `public function run( $args, $assoc_args )` |
| `list-prompts` | `list_prompts` | `public function list_prompts( $args, $assoc_args )` |
| `validate-config` | `validate_config` | `public function validate_config( $args, $assoc_args )` |
| `adapters` | `adapters` | `public function adapters( $args, $assoc_args )` |

### 12.3 TypeSafe evaluator provider

`includes/ai/providers/class-gregius-data-typesafe-provider.php` — class
`Gregius_Data_Typesafe_Provider` implements
`Gregius_Data_AI_Provider_Interface`. It is the only shipped provider exposing
the `evaluator` capability. The unsupported capability methods
`generate_text()`, `generate_embedding()`, and `rerank()` return `WP_Error`;
the model-list methods `get_llm_models()`, `get_vision_models()`,
`get_embedding_models()`, `get_rerank_models()`, and `get_evaluator_models()`
return arrays (empty for typesafe except the evaluator catalog).

| Symbol | Signature / value |
|---|---|
| `BASE_URL` | `const` = `https://api.typesafe.ai/v1` |
| `DEFAULT_TIMEOUT` | `const` = `30` |
| `DEFAULT_MODEL` | `const` = `jev-1.13.0` |
| `MAX_STATE_CHARS` | `const` = `120000` |
| `__construct` | `public function __construct( string $api_key = '' )` |
| `get_id` | `public function get_id(): string` → `typesafe` |
| `get_name` | `public function get_name(): string` → `TypeSafe` |
| `get_capabilities` | `public function get_capabilities(): array` → `array( 'evaluator' )` |
| `generate_text` | `public function generate_text( string $prompt, array $options = array() ): array|WP_Error` |
| `get_llm_models` | `public function get_llm_models(): array` |
| `get_vision_models` | `public function get_vision_models(): array` |
| `generate_embedding` | `public function generate_embedding( string $text, array $options = array() ): array|WP_Error` |
| `get_embedding_models` | `public function get_embedding_models(): array` |
| `rerank` | `public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error` |
| `get_rerank_models` | `public function get_rerank_models(): array` |
| `get_evaluator_models` | `public function get_evaluator_models(): array` |
| `evaluate` | `public function evaluate( string $state, array $questions, array $options = array() ): array|WP_Error` |
| `validate_api_key` | `public function validate_api_key( string $api_key ): bool|WP_Error` |

`evaluate()` sends `POST /systemone` against `BASE_URL`. On success it returns
`array{ answers, model, usage }`, where `answers` is a map of
`question-id => answer object`; the caller interprets the answers (e.g.
thresholds a Noul probability into a verdict).

### 12.4 Typed-question primitive

`evaluate()` accepts a `$questions` map of `question-id => question object`,
each carrying a `type`, an `instructions` string, and an optional `criteria`
map. The shipped integration emits only the **Noul** question type:

```json
{ "type": "noul", "instructions": "…", "criteria": { "true": "…", "false": "…" } }
```

A Noul answer carries its probability under the `noul` key (a float in
`[0, 1]`). No other question type (`choice`, `score`) is emitted by shipped
code.

### 12.5 Retrieval-sufficiency evaluation (RAG service)

`includes/rag/class-gregius-data-rag-service.php` — private methods that grade
whether retrieved context is sufficient to answer a query, and run up to one
corrective query reformulation. These run inside `generate_answer()` at answer
time and are internal to the RAG pipeline, distinct from the dataset runner.

| Method | Signature |
|---|---|
| `resolve_evaluator` | `private function resolve_evaluator( $options )` |
| `resolve_evaluation_instructions` | `private function resolve_evaluation_instructions( $options )` |
| `evaluate_retrieval_sufficiency` | `private function evaluate_retrieval_sufficiency( $query, $chunks, $evaluator, $options )` |
| `call_evaluator_with_retry` | `private function call_evaluator_with_retry( $evaluator, $state, $questions )` |
| `is_transient_evaluator_error` | `private function is_transient_evaluator_error( $error )` |
| `build_evaluation_state` | `private function build_evaluation_state( $query, $chunk_texts )` |
| `retrieve_single_path_with_evaluation` | `private function retrieve_single_path_with_evaluation( $query, $search_query, $tool_selection, $options, $progress_callback )` |
| `select_corrective_action` | `private function select_corrective_action( $search_query, $retrieval_outcome, $options )` |

`evaluate_retrieval_sufficiency()` sends one Noul "sufficient?" question and
thresholds the returned probability (filter
`gregius_data_rag_evaluator_threshold`, default `0.5`) into a verdict of
`correct`, `insufficient`, or `error`. Evaluator failures fail open (verdict
`error`) and never block answer generation.

### 12.6 Evaluator filters

| Filter | Default | Purpose |
|---|---|---|
| `gregius_data_rag_evaluator_threshold` | `0.5` | Noul probability above which retrieval is graded `correct`. |
| `gregius_data_rag_evaluator_max_reformulations` | `1` | Maximum corrective query rewrites per request. |
| `gregius_data_rag_evaluator_retry_attempts` | `1` | Retry count for transient evaluator failures. |
| `gregius_data_rag_evaluation_framework_adapter` | — | Extends framework adapter dispatch (see section 8). |

### 12.7 Evaluator model catalog

`includes/class-gregius-data-model-catalog.php` —
`Gregius_Data_Model_Catalog::evaluator_models( string $provider ): array`.
The TypeSafe catalog ships two evaluator model IDs: `jev-1.13.0` (default,
pinned) and `jev-latest` (current-stable alias).
