# Logs Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document describes how to use and extend the Gregius Data logging subsystem.

Audience:
- Plugin contributors implementing operational telemetry
- Administrators and DevOps teams automating log operations
- Integrators consuming logs via REST or WP-CLI

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Logger API and data model
- REST logs route contracts
- WP-CLI logs command contracts
- Dashboard logs store/UI integration points
- Sensitive-data masking behavior and settings

Not covered:
- Feature-level telemetry semantics per subsystem
- External log shipping pipelines

## 3. Quick Start

### 3.1 Write a Log Record in PHP

```php
$logger = new GG_Data_Logger();

$logger->log(
	'RAG retrieval completed',
	'info',
	'rag',
	'default',
	array(
		'query' => 'dementia care',
		'usage' => array(
			'prompt_tokens' => 1200,
			'completion_tokens' => 210,
		),
	)
);
```

[SRS: LOG-FR-02, LOG-FR-03, LOG-DR-02, LOG-DR-04]

### 3.2 Query Logs via REST

```bash
curl -X GET "https://example.com/wp-json/gg-data/v1/logs?component=rag&level=error&page=1&per_page=20" \
  -H "Authorization: Bearer <token>"
```

[SRS: LOG-FR-04, LOG-FR-06]

### 3.3 Manage Logs via WP-CLI

```bash
wp gg-data logs list --component=rag --level=warning --limit=50
wp gg-data logs export --format=json > logs.json
wp gg-data logs purge --days=30 --yes
wp gg-data logs stats
```

[SRS: LOG-FR-05, LOG-FR-07, LOG-FR-08]

## 4. Core API Reference

### 4.1 `GG_Data_Logger`

Source: `includes/class-gg-data-logger.php`

Key methods:
- `log( $message, $level = 'info', $component = 'system', $connection_id = null, $context = array() )`
- `get_logs( $args = array() )`
- `get_stats()`
- `export_logs( $args = array(), $format = 'csv' )`
- `purge_old_logs( $days = 30, $trigger = 'manual' )`
- `create_logs_table()`
- `create_logs_tables_for_all_sites()`
- `migrate_legacy_options()`
- `get_log_levels()`
- `get_components()`

Scheduled retention runtime:
- `GG_Data_Log_Retention::CRON_EVENT` = `gg_data_daily_log_retention_purge`
- `GG_Data_Log_Retention::handle_scheduled_purge()` reads `gg_data_log_retention_days` and calls `purge_old_logs( ..., 'cron' )`.
- Activation/deactivation schedule and clear this event per site context in multisite.

Write-path guardrails:
- no-op when logging disabled
- `debug` writes require debug mode
- writes below configured minimum log level are skipped

### 4.2 Supported Levels and Components

Levels:
- `debug`, `info`, `warning`, `error`, `critical`

Components:
- `rag`, `search`, `sync`, `vectors`, `connection`, `model`, `cron`, `system`, `legacy`

## 5. Storage Contract

### 5.1 Table Schema

Table: `{$wpdb->prefix}gg_data_logs`

Columns:
- `id` (PK)
- `logged_at`
- `level`
- `component`
- `connection_id`
- `message`
- `context` (JSON text)

Indexes:
- `idx_logged_at`
- `idx_level`
- `idx_component`
- `idx_connection`

### 5.2 Context Masking Rules

Sensitive keys (case-insensitive substring match):
- `api_key`, `password`, `secret`, `token`, `authorization`

Preserved usage metric keys:
- `prompt_tokens`, `completion_tokens`, `total_tokens`, `tokens_used`

Masking is recursive for nested arrays.

## 6. REST API Reference

Controller: `GG_Data_REST_Logs_Controller`  
Namespace: `gg-data/v1`  
Base route: `/logs`

### 6.1 `GET /logs`

Purpose: list logs with filters and pagination.

Query params:
- `page`, `per_page`
- `level` (comma-separated)
- `component` (comma-separated)
- `connection_id`
- `search`
- `date_from`, `date_to`
- `orderby` (`id`, `logged_at`, `level`, `component`)
- `order` (`ASC`, `DESC`)

Response (success envelope):
- `success: true`
- `data.logs[]`
- `data.page`, `data.per_page`, `data.total_pages`, `data.total_items`

### 6.2 `GET /logs/stats`

Returns:
- `success: true`
- `data.total`
- `data.by_level`
- `data.by_component`

### 6.3 `GET /logs/export`

Query params:
- same filters as list
- `format`: `csv` or `json`

Returns downloadable content with `Content-Disposition` header.

### 6.4 `DELETE /logs/purge`

Query/body param:
- `days` (1..365, default 30)

Behavior notes:
- Purge execution is constrained to the current site's `{prefix}gg_data_logs` table.
- `gg_interaction` posts and interaction meta are not purge targets.
- Logger emits retention extension hooks and observability context on each purge run.

Returns:
- `success: true`
- `data.deleted`

### 6.5 `GET /logs/settings`

Returns:
- `logging_enabled`
- `log_level`
- `retention_days`
- `levels[]`
- `components[]`

### 6.6 `POST /logs/settings`

Accepts updates for:
- `logging_enabled`
- `log_level`
- `retention_days`

Returns updated settings snapshot.

### 6.7 Permissions

All logs management routes require:
- `current_user_can( 'manage_options' )`

## 7. WP-CLI Reference

Class: `GG_Data_CLI_Logs`

Commands:
- `wp gg-data logs list [--level=<level>] [--component=<component>] [--limit=<n>] [--format=table|json|csv]`
- `wp gg-data logs export [--level=<level>] [--component=<component>] [--format=csv|json]`
- `wp gg-data logs purge [--days=<n>] [--yes]`
- `wp gg-data logs stats [--format=table|json]`

Validation behavior:
- Invalid levels/components return CLI errors
- Purge requires `days >= 1`
- When `--days` is omitted, default uses `gg_data_log_retention_days` (fallback 30).

## 8. Retention Hook Contract

The logger exposes these Tier 1 extension points:
- `apply_filters( 'gg_data_log_retention_days', int $days, int $site_id ): int`
- `do_action( 'gg_data_before_purge_logs', int $days, string $threshold, int $site_id )`
- `do_action( 'gg_data_after_purge_logs', int $days, int $deleted, int $site_id )`

Observability context emitted on retention completion:
- `site_id`
- `table_name`
- `retention_days_effective`
- `threshold`
- `deleted_rows`
- `duration_ms`
- `trigger` (`manual` or `cron`)

## 9. Dashboard Integration

UI component:
- `assets/src/scripts/dashboard/pages/LogsPage.js`

Store modules:
- `assets/src/scripts/dashboard/stores/logs/index.js`
- `assets/src/scripts/dashboard/stores/logs/actions.js`
- `assets/src/scripts/dashboard/stores/logs/selectors.js`
- `assets/src/scripts/dashboard/stores/logs/resolvers.js`

Implemented dashboard behavior:
- level/component/connection/date filters
- pagination controls
- auto-refresh polling (5s)
- export actions (CSV/JSON)
- expandable context view

## 10. Troubleshooting

### No logs are being written

Check:
1. `gg_data_logging_enabled` option is `true`.
2. Message level is not below `gg_data_log_level`.
3. `debug` logs are not expected unless debug mode is enabled.

### Logs API returns permission error

Expected unless current user has `manage_options`.

### Export output appears empty

Check active filters (`level`, `component`, date range, search) and retry with broader criteria.

## 11. Parity Coverage Snapshot

Documented and code-backed:
- Logger schema and APIs
- Levels/components enumerations
- REST route contracts (`/logs`, `/logs/stats`, `/logs/export`, `/logs/purge`, `/logs/settings`)
- CLI contracts (`list`, `export`, `purge`, `stats`)
- Settings options (`gg_data_logging_enabled`, `gg_data_log_level`, `gg_data_log_retention_days`, `gg_data_debug_mode`)
- Sensitive context masking rules and usage-key allowlist
- Scheduled retention runtime (`gg_data_daily_log_retention_purge`) and multisite scheduling lifecycle
- Retention hook contracts and observability payload fields
- Explicit immutability boundary for `gg_interaction` under log retention operations
