# WP-CLI Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document describes how to use and maintain the Gregius Data WP-CLI interface.

Audience:
- Plugin contributors extending CLI behavior
- DevOps and automation users running maintenance commands
- Site administrators operating search and logging workflows

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)
- Prompt subsystem (override and resolver behavior): [../prompt/developer-documentation.md](../prompt/developer-documentation.md)

## 2. Scope

Covered:
- CLI bootstrap and command registration
- Command contracts (options, defaults, outputs)
- Delegation boundaries to managers/services
- Validation and error behavior patterns
- Extension hooks and maintenance guidance

Not covered:
- Internal implementation details of delegated service managers
- REST/dashboard contracts (documented in their subsystem packages)

## 3. Quick Start

### 3.1 Check Registered Command Surface

```bash
wp gg-data --help
wp gg-data sync --help
wp gg-data vectors --help
wp gg-data answer --help
wp gg-data benchmark --help
wp gg-data evaluation --help
wp gg-data list-connections --help
wp gg-data list-models --help
wp gg-data logs --help
```

[SRS: CLI-FR-01]

### 3.2 Typical Operations

```bash
# Full sync
wp gg-data sync all --connection=gregius-data --batch-size=200

# Generate vectors for pending items
wp gg-data vectors generate --connection=gregius-data --embedding-model=tfidf-300

# RAG answer
wp gg-data answer "What is dementia care?" --format=json

# Benchmark validation and targeted execution
wp gg-data benchmark validate_config
wp gg-data benchmark run --only=P1

# Evaluation validation and adapter-targeted execution
wp gg-data evaluation validate_config
wp gg-data evaluation run --framework=ragas --only=P1

# Logs maintenance
wp gg-data logs list --level=error --limit=20
wp gg-data logs purge --days=30 --yes
```

[SRS: CLI-FR-02, CLI-FR-03, CLI-FR-04, CLI-FR-06]

## 4. Command Registration and Entry Points

Bootstrap source: `includes/cli/class-gg-data-cli.php`

Registered commands:
- `wp gg-data sync`
- `wp gg-data vectors`
- `wp gg-data answer`
- `wp gg-data benchmark`
- `wp gg-data evaluation`
- `wp gg-data list-connections`
- `wp gg-data list-models`
- `wp gg-data logs`

Registration behavior:
- `GG_Data_CLI::register_commands()` returns early when `WP_CLI` runtime is unavailable.

[SRS: CLI-FR-01, CLI-QR-05]

## 5. Command Reference

### 5.1 Sync Commands (`GG_Data_CLI_Sync`)

Source: `includes/cli/class-gg-data-cli-sync.php`

Subcommands:
- `wp gg-data sync posts`
- `wp gg-data sync terms`
- `wp gg-data sync all`

Options:
- `--connection=<name>` (default `gregius-data`)
- `--post-type=<type>` (posts only, default `all`)
- `--batch-size=<number>` (default `100`)
- `--dry-run`
- `--format=table|json|csv` (default `table`)

Implementation notes:
- Connection validation: `validate_connection()` against `GG_Data_Settings_Manager`.
- Batch validation: `validate_batch_size()` with `gg_data_cli_max_batch_size` (default max `1000`).
- Delegation:
  - Posts: `GG_Data_Post_Sync::batch_sync_post_type()`
  - Terms: `GG_Data_Taxonomy_Sync::batch_sync_terms()`, `batch_sync_term_taxonomies()`, `batch_sync_term_relationships()`
- Workload helpers: `get_post_types_to_sync()`, `get_post_count()`.
- Output helper: `output_sync_results()`.
- Memory cleanup helper: `maybe_cleanup_memory()`.

[SRS: CLI-FR-02, CLI-DR-01, CLI-OR-01..04]

### 5.2 Vectors Commands (`GG_Data_CLI_Vectors`)

Source: `includes/cli/class-gg-data-cli-vectors.php`

Subcommands:
- `wp gg-data vectors generate`
- `wp gg-data vectors rebuild`

Options:
- `--connection=<name>` (default `gregius-data`)
- `--embedding-model=<model>` (default `tfidf-300`)
- `--post-type=<type>` (default `all`)
- `--batch-size=<number>` (default `50`)
- `--force` (documented alias intent for rebuild workflows)
- `--format=table|json|csv` (default `table`)

Implementation notes:
- Batch validation uses `gg_data_cli_vectors_max_batch_size` (default max `200`).
- `run_vector_generation()` orchestrates batching and summary output.
- TF-IDF paths call `build_vocabulary()` via `GG_Data_Vocabulary_Manager`.
- Generation delegates to `GG_Data_Vector_Generator::generate_batch()`.
- Post counting uses `get_posts_for_vectors()` with rebuild/non-rebuild query paths.

[SRS: CLI-FR-03, CLI-DR-02, CLI-OR-01..04]

### 5.3 Answer Command (`GG_Data_CLI_Answer`)

Source: `includes/cli/class-gg-data-cli-answer.php`

Command:
- `wp gg-data answer <query>`

Options:
- `--connection=<name>` (default `gregius-data`)
- `--embedding-model=<model>` (default `tfidf-300`)
- `--agentic-model=<model>` (optional)
- `--rerank-model=<model>` (optional)
- `--answer-model=<model>` (default `gpt-4o-mini`)
- `--prompt-id=<id>` (optional)
- `--prompt=<identifier>` (optional, post ID/slug/exact title)
- `--no-track`
- `--format=table|json` (default `table`)

Implementation notes:
- Query argument is mandatory; missing query terminates with `WP_CLI::error()`.
- Prompt resolution uses `resolve_prompt_id()` and enforces:
  - conflict error when both `--prompt-id` and `--prompt` are provided
  - numeric ID validation
  - slug lookup and exact-title disambiguation
- `--no-track` applies `add_filter( 'gg_data_track_interaction', '__return_false' )`.
- Delegation to `GG_Data_Abilities_Manager::execute_rag_answer()`.
- Output handled by `output_result()` with answer/sources/metadata sections or JSON envelope.

[SRS: CLI-FR-04, CLI-DR-03, CLI-OR-05, CLI-OR-06, CLI-QR-04]

### 5.4 List Commands (`GG_Data_CLI_List_Connections`, `GG_Data_CLI_List_Models`)

Sources:
- `includes/cli/class-gg-data-cli-list-connections.php`
- `includes/cli/class-gg-data-cli-list-models.php`

Commands:
- `wp gg-data list-connections`
- `wp gg-data list-models`

Options:
- `list-connections`:
  - `--format=table|json|csv` (default `table`)
  - `--with-embedding-models` (optional)
  - `--with-model-details` (optional; implies `--with-embedding-models`)
- `list-models`:
  - `--connection=<name>` (default `gregius-data`)
  - `--type=embeddings|llm|rerank` (optional)
  - `--format=table|json|csv` (default `table`)

Implementation notes:
- Delegates to abilities manager:
  - `execute_list_connections()`
  - `execute_list_models()`
- `list-connections` passes optional enrichment flags to abilities callback:
  - `include_embedding_models=true` when `--with-embedding-models` is used
  - `include_model_details=true` when `--with-model-details` is used
- `list-connections` default output remains unchanged unless enrichment flags are used.
- Model output appends `dimensions` column when type is `embeddings`.
- Empty result sets emit warnings instead of fatal errors.

[SRS: CLI-FR-05, CLI-DR-04]

### 5.5 Benchmark Commands (`GG_Data_CLI_Benchmark`)

Source: `includes/cli/class-gg-data-cli-benchmark.php`

Subcommands:
- `wp gg-data benchmark run`
- `wp gg-data benchmark list_prompts`
- `wp gg-data benchmark validate_config`
- `wp gg-data benchmark scorecard`

Options:
- `run`:
  - `--config=<path>`
  - `--only=<id>`
  - `--scorecard-scope=all|selected` (default `all`)
  - `--output-dir=<path>`
- `list_prompts`:
  - `--config=<path>`
  - `--format=table|json|csv` (default `table`)
- `validate_config`:
  - `--config=<path>`
- `scorecard`:
  - `--config=<path>`
  - `--only=<id>`
  - `--scorecard-scope=all|selected` (default `all`)
  - `--output-dir=<path>`

Implementation notes:
- Delegates to `GG_Data_Benchmark_Service`.
- Default writable config path resolves from `wp_upload_dir()['basedir']` to `wp-content/uploads/gregius-data/config/rag-benchmark-quality-config.json` on single-site, or the site-specific uploads base on multisite.
- Default artifact root resolves from `wp_upload_dir()['basedir']` to `wp-content/uploads/gregius-data/artifacts/benchmark/` on single-site, or the site-specific uploads base on multisite.
- Shipped template path remains inside the plugin at `includes/cli/resources/benchmark/rag-benchmark-quality-config.example.json`.
- `run` writes scorecard and prompt artifacts; `scorecard` writes scorecard-only output without prompt execution.

### 5.6 Evaluation Commands (`GG_Data_CLI_Evaluation`)

Source: `includes/cli/class-gg-data-cli-evaluation.php`

Subcommands:
- `wp gg-data evaluation run`
- `wp gg-data evaluation list_prompts`
- `wp gg-data evaluation validate_config`
- `wp gg-data evaluation adapters`

Options:
- `run`:
  - `--config=<path>`
  - `--only=<id>`
  - `--framework=<name>`
  - `--output-dir=<path>`
- `list_prompts`:
  - `--config=<path>`
  - `--format=table|json|csv` (default `table`)
- `validate_config`:
  - `--config=<path>`
- `adapters`:
  - no command-specific options

Implementation notes:
- Delegates to `GG_Data_Evaluation_Service`.
- Default writable config path resolves from `wp_upload_dir()['basedir']` to `wp-content/uploads/gregius-data/config/rag-evaluation-config.json` on single-site, or the site-specific uploads base on multisite.
- Default artifact root resolves from `wp_upload_dir()['basedir']` to `wp-content/uploads/gregius-data/artifacts/evaluation/` on single-site, or the site-specific uploads base on multisite.
- Shipped template path remains inside the plugin at `includes/cli/resources/evaluation/rag-evaluation-config.example.json`.
- `run` writes raw artifacts, canonical `prompts.jsonl`, framework adapter output, and `manifest.json`.
- Supported framework discovery is exposed through `adapters`; current supported framework is `ragas`.

### 5.7 Logs Commands (`GG_Data_CLI_Logs`)

Source: `includes/cli/class-gg-data-cli-logs.php`

Subcommands:
- `wp gg-data logs list`
- `wp gg-data logs export`
- `wp gg-data logs purge`
- `wp gg-data logs stats`

Options:
- `logs list`:
  - `--level=debug|info|warning|error|critical`
  - `--component=rag|search|sync|vectors|connection|model|cron|system`
  - `--limit=<number>` (default `50`)
  - `--format=table|json|csv` (default `table`)
- `logs export`:
  - `--level=<level>`
  - `--component=<component>`
  - `--format=csv|json` (default `csv`)
- `logs purge`:
  - `--days=<number>` (default `30`, must be `>= 1`)
  - `--yes`
- `logs stats`:
  - `--format=table|json` (default `table`)

Implementation notes:
- Level and component values are validated against `GG_Data_Logger` enumerations.
- Delegation:
  - `get_logs()`
  - `export_logs()`
  - `purge_old_logs()`
  - `get_stats()`
- `list` table output truncates message text via `truncate_message()`.

[SRS: CLI-FR-06, CLI-DR-05, CLI-DR-06, CLI-OR-06]

## 6. Delegation Boundaries

CLI classes intentionally avoid embedding core domain logic.

Delegated managers/services:
- `GG_Data_Post_Sync`
- `GG_Data_Taxonomy_Sync`
- `GG_Data_Vector_Generator`
- `GG_Data_Vocabulary_Manager`
- `GG_Data_Abilities_Manager`
- `GG_Data_Logger`
- `GG_Data_Settings_Manager`

[SRS: CLI-QR-02]

## 7. Extension Points

Filter hooks used by CLI handlers:
- `gg_data_cli_max_batch_size` (sync max batch bound, default `1000`)
- `gg_data_cli_vectors_max_batch_size` (vectors max batch bound, default `200`)
- `gg_data_track_interaction` (runtime tracking suppression for `answer --no-track`)

Extension guidance:
- Prefer extending manager behavior behind existing delegation boundaries.
- Keep command handlers focused on parsing, validation, and formatting.
- Maintain deterministic JSON/CSV structures for automation compatibility.

## 8. Troubleshooting

### Command not found

Check:
1. WP-CLI is installed and using the correct WordPress root.
2. Plugin is active in the target site.
3. `GG_Data_CLI::register_commands()` executed in WP-CLI runtime.

### Invalid connection errors

Cause:
- Connection name is not present in plugin settings.

Resolution:
- Run `wp gg-data list-connections` and use one of the returned names.

### Batch-size warnings and clamping

Cause:
- Batch size is lower than 1 or higher than configured max filters.

Resolution:
- Use a value within bounds or update relevant max filter.

### Prompt override failures in `answer`

Common cases:
- Both `--prompt-id` and `--prompt` used together.
- Prompt ID does not exist or is not `gg_prompt` type.
- Prompt title matches multiple posts.

Resolution:
- Use exactly one prompt option and prefer `--prompt-id` for deterministic selection.

### Invalid logs filters

Cause:
- Unsupported `--level` or `--component` value.

Resolution:
- Use the allowed enum values documented in section 5.7.

### Config not found for benchmark or evaluation

Cause:
- The writable uploads-based config file has not been copied from the shipped template yet.

Resolution:
- Copy the matching example from `includes/cli/resources/...` into the uploads-based config path shown by the command error message.
- Populate the connection and model values before re-running `validate_config` or `run`.

## 9. Parity Coverage Snapshot

Documented and code-backed:
- Bootstrap registration of namespace plus eight command families
- Sync, vectors, answer, list, and logs option/default contracts
- Benchmark and evaluation option/default contracts, including uploads-based config and artifact paths
- Batch validation, progress, and memory cleanup patterns
- Prompt resolution and no-track behavior for answer command
- Logs validation and maintenance command behavior
- Filter hooks governing CLI batch controls and tracking suppression
