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
wp gregius-data --help
wp gregius-data sync --help
wp gregius-data vectors --help
wp gregius-data answer --help
wp gregius-data evaluation --help
wp gregius-data list-connections --help
wp gregius-data list-models --help
wp gregius-data logs --help
```

[SRS: CLI-FR-01]

### 3.2 Typical Operations

```bash
# Full sync
wp gregius-data sync all --connection=gregius-data --batch-size=200

# Generate vectors for pending items
wp gregius-data vectors generate --connection=gregius-data --embedding-model=hashingtf-murmur3-1024

# RAG answer
wp gregius-data answer "What is dementia care?" --format=json

# Evaluation validation and adapter-targeted execution
wp gregius-data evaluation validate_config
wp gregius-data evaluation run --framework=ragas --only=P1

# Logs maintenance
wp gregius-data logs list --level=error --limit=20
wp gregius-data logs purge --days=30 --yes
```

[SRS: CLI-FR-02, CLI-FR-03, CLI-FR-04, CLI-FR-06]

## 4. Command Registration and Entry Points

Bootstrap source: `includes/cli/class-gregius-data-cli.php`

Registered commands:
- `wp gregius-data sync`
- `wp gregius-data vectors`
- `wp gregius-data answer`
- `wp gregius-data evaluation`
- `wp gregius-data list-connections`
- `wp gregius-data list-models`
- `wp gregius-data logs`

Registration behavior:
- `Gregius_Data_CLI::register_commands()` returns early when `WP_CLI` runtime is unavailable.

[SRS: CLI-FR-01, CLI-QR-05]

## 5. Command Reference

### 5.1 Sync Commands (`Gregius_Data_CLI_Sync`)

Source: `includes/cli/class-gregius-data-cli-sync.php`

Subcommands:
- `wp gregius-data sync posts`
- `wp gregius-data sync terms`
- `wp gregius-data sync all`

Options:
- `--connection=<name>` (default `gregius-data`)
- `--post-type=<type>` (posts only, default `all`)
- `--batch-size=<number>` (default `100`)
- `--dry-run`
- `--format=table|json|csv` (default `table`)

Implementation notes:
- Connection validation: `validate_connection()` against `Gregius_Data_Settings_Manager`.
- Batch validation: `validate_batch_size()` with `gregius_data_cli_max_batch_size` (default max `1000`).
- Delegation:
  - Posts: `Gregius_Data_Post_Sync::batch_sync_post_type()`
  - Terms: `Gregius_Data_Taxonomy_Sync::batch_sync_terms()`, `batch_sync_term_taxonomies()`, `batch_sync_term_relationships()`
- Workload helpers: `get_post_types_to_sync()`, `get_post_count()`.
- Output helper: `output_sync_results()`.
- Memory cleanup helper: `maybe_cleanup_memory()`.

[SRS: CLI-FR-02, CLI-DR-01, CLI-OR-01..04]

### 5.2 Vectors Commands (`Gregius_Data_CLI_Vectors`)

Source: `includes/cli/class-gregius-data-cli-vectors.php`

Subcommands:
- `wp gregius-data vectors generate`
- `wp gregius-data vectors rebuild`

Options:
- `--connection=<name>` (default `gregius-data`)
- `--embedding-model=<model>` (default `hashingtf-murmur3-1024`)
- `--post-type=<type>` (default `all`)
- `--batch-size=<number>` (default `50`)
- `--format=table|json|csv` (default `table`)

Implementation notes:
- Batch validation uses `gregius_data_cli_vectors_max_batch_size` (default max `200`).
- `run_vector_generation()` orchestrates batching and summary output.
- Generation delegates to `Gregius_Data_Vector_Generator::generate_batch()`.
- Post counting uses `get_posts_for_vectors()` with rebuild/non-rebuild query paths.

[SRS: CLI-FR-03, CLI-DR-02, CLI-OR-01..04]

### 5.3 Answer Command (`Gregius_Data_CLI_Answer`)

Source: `includes/cli/class-gregius-data-cli-answer.php`

Command:
- `wp gregius-data answer <query>`

Options:
- `--connection=<name>` (default `gregius-data`)
- `--embedding-model=<model>` (default `hashingtf-murmur3-1024`)
- `--agentic-model=<model>` (optional)
- `--rerank-model=<model>` (optional)
- `--evaluator-model=<model>` (optional)
- `--evaluation-prompt=<id>` (optional)
- `--answer-model=<model>` (default `gpt-4o-mini`)
- `--prompt-id=<id>` (optional)
- `--prompt=<identifier>` (optional, post ID/slug/exact title)
- `--format=table|json` (default `table`)

Implementation notes:
- Query argument is mandatory; missing query terminates with `WP_CLI::error()`.
- Prompt resolution uses `resolve_prompt_id()` and enforces:
  - conflict error when both `--prompt-id` and `--prompt` are provided
  - numeric ID validation
  - slug lookup and exact-title disambiguation
- Delegation to `Gregius_Data_Abilities_Manager::execute_rag_answer()`.
- Output handled by `output_result()` with answer/sources/metadata sections or JSON envelope.

[SRS: CLI-FR-04, CLI-DR-03, CLI-OR-05, CLI-QR-04]

### 5.4 List Commands (`Gregius_Data_CLI_List_Connections`, `Gregius_Data_CLI_List_Models`)

Sources:
- `includes/cli/class-gregius-data-cli-list-connections.php`
- `includes/cli/class-gregius-data-cli-list-models.php`

Commands:
- `wp gregius-data list-connections`
- `wp gregius-data list-models`

Options:
- `list-connections`:
  - `--format=table|json|csv` (default `table`)
  - `--with-embedding-models` (optional)
  - `--with-model-details` (optional; implies `--with-embedding-models`)
- `list-models`:
  - `--connection=<name>` (default `gregius-data`)
  - `--type=embeddings|llm|rerank|evaluator` (optional)
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

### 5.5 Evaluation Commands (`Gregius_Data_CLI_Evaluation`)

Source: `includes/cli/class-gregius-data-cli-evaluation.php`

Subcommands:
- `wp gregius-data evaluation run`
- `wp gregius-data evaluation list_prompts`
- `wp gregius-data evaluation validate_config`
- `wp gregius-data evaluation adapters`

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
- Delegates to `Gregius_Data_Evaluation_Service`.
- Default writable config path resolves from `wp_upload_dir()['basedir']` to `wp-content/uploads/gregius-data/config/rag-evaluation-config.json` on single-site, or the site-specific uploads base on multisite.
- Default artifact root resolves from `wp_upload_dir()['basedir']` to `wp-content/uploads/gregius-data/artifacts/evaluation/` on single-site, or the site-specific uploads base on multisite.
- Shipped template path remains inside the plugin at `includes/cli/resources/evaluation/rag-evaluation-config.example.json`.
- `run` writes raw artifacts, canonical `prompts.jsonl`, framework adapter output, and `manifest.json`.
- Supported framework discovery is exposed through `adapters`; current supported framework is `ragas`.

### 5.6 Logs Commands (`Gregius_Data_CLI_Logs`)

Source: `includes/cli/class-gregius-data-cli-logs.php`

Subcommands:
- `wp gregius-data logs list`
- `wp gregius-data logs export`
- `wp gregius-data logs purge`
- `wp gregius-data logs stats`

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
- Level and component values are validated against `Gregius_Data_Logger` enumerations.
- Delegation:
  - `get_logs()`
  - `export_logs()`
  - `purge_old_logs()`
  - `get_stats()`
- `list` table output truncates message text via `truncate_message()`.

[SRS: CLI-FR-06, CLI-DR-05, CLI-DR-06, CLI-OR-05]

## 6. Delegation Boundaries

CLI classes intentionally avoid embedding core domain logic.

Delegated managers/services:
- `Gregius_Data_Post_Sync`
- `Gregius_Data_Taxonomy_Sync`
- `Gregius_Data_Vector_Generator`
- `Gregius_Data_Abilities_Manager`
- `Gregius_Data_Logger`
- `Gregius_Data_Settings_Manager`

[SRS: CLI-QR-02]

## 7. Extension Points

Filter hooks used by CLI handlers:
- `gregius_data_cli_max_batch_size` (sync max batch bound, default `1000`)
- `gregius_data_cli_vectors_max_batch_size` (vectors max batch bound, default `200`)

Extension guidance:
- Prefer extending manager behavior behind existing delegation boundaries.
- Keep command handlers focused on parsing, validation, and formatting.
- Maintain deterministic JSON/CSV structures for automation compatibility.

## 8. Troubleshooting

### Command not found

Check:
1. WP-CLI is installed and using the correct WordPress root.
2. Plugin is active in the target site.
3. `Gregius_Data_CLI::register_commands()` executed in WP-CLI runtime.

### Invalid connection errors

Cause:
- Connection name is not present in plugin settings.

Resolution:
- Run `wp gregius-data list-connections` and use one of the returned names.

### Batch-size warnings and clamping

Cause:
- Batch size is lower than 1 or higher than configured max filters.

Resolution:
- Use a value within bounds or update relevant max filter.

### Prompt override failures in `answer`

Common cases:
- Both `--prompt-id` and `--prompt` used together.
- Prompt ID does not exist or is not `gregius_prompt` type.
- Prompt title matches multiple posts.

Resolution:
- Use exactly one prompt option and prefer `--prompt-id` for deterministic selection.

### Invalid logs filters

Cause:
- Unsupported `--level` or `--component` value.

Resolution:
- Use the allowed enum values documented in section 5.6.

### Config not found for evaluation

Cause:
- The writable uploads-based config file has not been copied from the shipped template yet.

Resolution:
- Copy the matching example from `includes/cli/resources/...` into the uploads-based config path shown by the command error message.
- Populate the connection and model values before re-running `validate_config` or `run`.

## 9. Parity Coverage Snapshot

Documented and code-backed:
- Bootstrap registration of namespace plus seven command families
- Sync, vectors, answer, list, and logs option/default contracts
- Evaluation option/default contracts, including uploads-based config and artifact paths
- Batch validation, progress, and memory cleanup patterns
- Prompt resolution behavior for answer command
- Logs validation and maintenance command behavior
- Filter hooks governing CLI batch controls
