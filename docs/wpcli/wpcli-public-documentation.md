# WP-CLI — Automating Gregius Data Operations from the Command Line

[Editorial: Feature: WP-CLI | Pages: 1 | Total words: 1600 | Sections: 12 (H2: 11, H3: 2) | Screenshots: 0 | Doc category: /docs/category/operations/ | Audience: Administrator, DevOps]

## Overview

Gregius Data ships a full WP-CLI command surface under `wp gg-data` — eight command families covering sync, vectors, RAG answers, benchmarks, evaluation, listing, and logs. Every subcommand accepts a `--format` flag (table, json, or csv), producing deterministic output for scripts and CI/CD pipelines.

Automation and scripting are a site operator's most reliable tools. Without a command-line interface, routine tasks — syncing content after a migration, rebuilding vectors after a model change, auditing logs — require dashboard clicks or REST calls that compound into brittle workflows. The `wp gg-data` namespace turns these into repeatable, versionable one-liners.

By the end of this page, you will know every command family, its subcommands, flags, defaults, and output formats — enough to script your entire Gregius Data operational surface.

### Prerequisites

- WP-CLI installed and configured for the target WordPress site
- Gregius Data plugin installed and activated
- At least one data connection configured and active (for sync, vectors, answer, and listing commands)
- AI models registered (for vector generation and RAG answer commands)

---

## How to: Get Started

Every subcommand responds to `--help` with its full flag contract. Start here before running anything:

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

All subcommands produce output in one of three formats controlled by `--format`:

| Format | Use case |
|---|---|
| `table` | Interactive terminal use (default) |
| `json` | Script consumption, API bridges, jq pipelines |
| `csv` | Spreadsheet import, reporting pipelines |

---

## Sync Commands

Sync commands move WordPress content into PostgreSQL. Three subcommands cover posts, terms, and a combined full sync.

### Subcommands

| Subcommand | Scope |
|---|---|
| `wp gg-data sync posts` | Sync posts by post type |
| `wp gg-data sync terms` | Sync all taxonomies, term taxonomies, and term relationships |
| `wp gg-data sync all` | Run posts + terms in sequence |

### Flags

| Flag | Default | Description |
|---|---|---|
| `--connection` | `gregius-data` | Target data connection name |
| `--post-type` | `all` | Specific post type (posts only) |
| `--batch-size` | `100` | Posts per batch (max `1000`) |
| `--dry-run` | off | Report what would sync without writing |
| `--format` | `table` | `table`, `json`, or `csv` |

### Examples

```bash
# Full sync with custom batch size
wp gg-data sync all --connection=gregius-data --batch-size=200

# Sync only pages, dry-run first
wp gg-data sync posts --post-type=page --dry-run

# Sync all posts as CSV for reporting
wp gg-data sync posts --format=csv
```

---

## Vectors Commands

Vector commands generate and rebuild semantic embeddings from your synced content. Two subcommands handle initial generation and full rebuilds.

### Subcommands

| Subcommand | Scope |
|---|---|
| `wp gg-data vectors generate` | Generate vectors for content that needs them |
| `wp gg-data vectors rebuild` | Rebuild all vectors for the connection |

### Flags

| Flag | Default | Description |
|---|---|---|
| `--connection` | `gregius-data` | Target data connection name |
| `--embedding-model` | `tfidf-300` | Embedding model ID to use |
| `--post-type` | `all` | Specific post type to process |
| `--batch-size` | `50` | Items per batch (max `200`) |
| `--force` | off | Force regeneration even when vectors exist |
| `--format` | `table` | `table`, `json`, or `csv` |

### Examples

```bash
# Generate vectors with a specific model
wp gg-data vectors generate --embedding-model=hashingtf-murmur3-1024 --batch-size=100

# Full rebuild
wp gg-data vectors rebuild --connection=gregius-data
```

---

## Answer Command

Ask a question through the RAG pipeline directly from the command line. Useful for testing retrieval quality and debugging prompt behavior.

### Command

```
wp gg-data answer <query>
```

### Flags

| Flag | Default | Description |
|---|---|---|
| `--connection` | `gregius-data` | Target data connection |
| `--embedding-model` | `tfidf-300` | Embedding model for search |
| `--agentic-model` | (none) | Model for agentic routing |
| `--rerank-model` | (none) | Model for result reranking |
| `--answer-model` | `gpt-4o-mini` | Model for answer generation |
| `--prompt-id` | (none) | Specific prompt ID (numeric) |
| `--prompt` | (none) | Prompt by slug or exact title |
| `--no-track` | off | Suppress interaction recording |
| `--format` | `table` | `table` or `json` |

### Examples

```bash
# Basic RAG query
wp gg-data answer "What is dementia care?"

# With model overrides and JSON output
wp gg-data answer "What is dementia care?" --embedding-model=text-embedding-3-small --answer-model=gpt-4o --format=json

# Using a specific system prompt by ID
wp gg-data answer "Summarize our privacy policy" --prompt-id=42
```

---

## Benchmark Commands

Benchmark commands validate and run the RAG quality benchmark. Designed for QA reviewers who need repeatable response validation.

### Subcommands

| Subcommand | Purpose |
|---|---|
| `wp gg-data benchmark run` | Execute the full prompt matrix |
| `wp gg-data benchmark list_prompts` | List configured benchmark prompts |
| `wp gg-data benchmark validate_config` | Validate the benchmark configuration file |
| `wp gg-data benchmark scorecard` | Regenerate scorecard from existing artifacts |

### Flags

| Flag | Default | Description |
|---|---|---|
| `--config` | uploads-based path | Path to benchmark config JSON |
| `--only` | (none) | Run a single prompt by ID (e.g. `P1`) |
| `--scorecard-scope` | `all` | `all` or `selected` |
| `--output-dir` | uploads-based path | Artifact output directory |

### Examples

```bash
# Validate config before running
wp gg-data benchmark validate_config

# Targeted single-prompt run
wp gg-data benchmark run --only=P1 --scorecard-scope=selected

# Regenerate scorecard without re-running
wp gg-data benchmark scorecard --from-dir=/path/to/artifacts
```

---

## Evaluation Commands

Evaluation commands produce framework-ready evaluation datasets from pre-collected RAG prompts. Designed for QA engineers running downstream quality measurement.

### Subcommands

| Subcommand | Purpose |
|---|---|
| `wp gg-data evaluation run` | Execute the full prompt corpus |
| `wp gg-data evaluation list_prompts` | List configured evaluation prompts |
| `wp gg-data evaluation validate_config` | Validate the evaluation configuration |
| `wp gg-data evaluation adapters` | List supported framework adapters |

### Flags

| Flag | Default | Description |
|---|---|---|
| `--config` | uploads-based path | Path to evaluation config JSON |
| `--only` | (none) | Run a single prompt by ID |
| `--framework` | config value | Override framework (e.g. `ragas`) |
| `--output-dir` | uploads-based path | Artifact output directory |

### Examples

```bash
# List supported framework adapters
wp gg-data evaluation adapters

# Validate config, then run
wp gg-data evaluation validate_config
wp gg-data evaluation run --framework=ragas

# Single prompt for targeted validation
wp gg-data evaluation run --only=P1 --framework=ragas
```

---

## Listing Commands

Listing commands discover the configured data connections and registered AI models. Use them before running sync, vectors, or answer commands to confirm valid names.

### Commands

| Command | Purpose |
|---|---|
| `wp gg-data list-connections` | List configured data connections |
| `wp gg-data list-models` | List registered AI models |

### Flags

| Flag | Default | Description |
|---|---|---|
| `--format` | `table` | `table`, `json`, or `csv` |
| `--with-embedding-models` | off | Include embedding model context (connections) |
| `--with-model-details` | off | Include full model details (connections; implies `--with-embedding-models`) |
| `--connection` | `gregius-data` | Connection scope (models) |
| `--type` | (all) | Filter by model type: `embeddings`, `llm`, or `rerank` (models) |

### Examples

```bash
# Discover connection names
wp gg-data list-connections --format=json

# List embedding models only
wp gg-data list-models --type=embeddings --format=table
```

---

## Logs Commands

Logs commands browse, export, purge, and summarize plugin operational logs. Designed for routine maintenance and diagnostics.

### Subcommands

| Subcommand | Purpose |
|---|---|
| `wp gg-data logs list` | View log entries with filters |
| `wp gg-data logs export` | Export filtered logs to CSV or JSON |
| `wp gg-data logs purge` | Delete logs older than N days |
| `wp gg-data logs stats` | Summary statistics |

### Flags

| Flag | Default | Description |
|---|---|---|
| `--level` | (all) | Filter by severity: `debug`, `info`, `warning`, `error`, `critical` |
| `--component` | (all) | Filter by source: `rag`, `search`, `sync`, `vectors`, `connection`, `model`, `cron`, `system` |
| `--connection` | (all) | Filter by data connection name |
| `--limit` | `50` | Max entries returned (list) |
| `--days` | `30` | Age threshold for purge (must be ≥ 1) |
| `--yes` | off | Skip confirmation prompt (purge) |
| `--format` | `table` (list, stats), `csv` (export) | `table`, `json`, or `csv` |

### Examples

```bash
# View recent error logs
wp gg-data logs list --level=error --limit=20

# Export sync component logs as CSV
wp gg-data logs export --component=sync --format=csv

# Purge logs older than 90 days
wp gg-data logs purge --days=90 --yes

# Quick stats overview
wp gg-data logs stats --format=json
```

---

## Permissions

| Command | Required capability | Notes |
|---|---|---|
| `sync` | `manage_options` | Writes to PostgreSQL |
| `vectors` | `manage_options` | Generates and stores embeddings |
| `answer` | `read` | Reads via Abilities API |
| `benchmark` | `manage_options` | Executes RAG pipeline |
| `evaluation` | `manage_options` | Executes RAG pipeline |
| `list-connections` | `manage_options` | Reads connection configuration |
| `list-models` | `manage_options` | Reads model registry |
| `logs` | `manage_options` | Reads and manages operational logs |

---

## Next Steps

**Local:**
- [Sync Commands](#sync-commands)
- [Vectors Commands](#vectors-commands)
- [Answer Command](#answer-command)

**Global:**
- [Gregius Data: Sync](/docs/sync/)
- [Gregius Data: Vectors](/docs/vectors/)
- [Gregius Data: RAG](/docs/rag/)
- [Gregius Data: Logs](/docs/logs/)
- [WP-CLI Handbook](https://make.wordpress.org/cli/handbook/)
