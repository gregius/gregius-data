<?php
/**
 * WP-CLI Logs Command
 *
 * View and manage plugin logs.
 *
 * @package Gregius_Data
 * @subpackage CLI
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View and manage plugin logs.
 *
 * ## EXAMPLES
 *
 *     # View recent logs
 *     $ wp gg-data logs list
 *
 *     # View RAG errors only
 *     $ wp gg-data logs list --component=rag --level=error
 *
 *     # Export logs as JSON
 *     $ wp gg-data logs export --format=json > logs.json
 *
 *     # Purge logs older than 7 days
 *     $ wp gg-data logs purge --days=7 --yes
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Logs {

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger|null
	 */
	private $logger = null;

	/**
	 * Get or create the logger instance (lazy loading).
	 *
	 * @return GG_Data_Logger
	 */
	private function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = new GG_Data_Logger();
		}
		return $this->logger;
	}

	/**
	 * Display recent logs.
	 *
	 * ## OPTIONS
	 *
	 * [--level=<level>]
	 * : Filter by log level.
	 * ---
	 * options:
	 *   - debug
	 *   - info
	 *   - warning
	 *   - error
	 *   - critical
	 * ---
	 *
	 * [--component=<component>]
	 * : Filter by component.
	 * ---
	 * options:
	 *   - rag
	 *   - search
	 *   - sync
	 *   - vectors
	 *   - connection
	 *   - model
	 *   - cron
	 *   - system
	 * ---
	 *
	 * [--limit=<number>]
	 * : Number of logs to display.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # View recent logs
	 *     $ wp gg-data logs list
	 *
	 *     # View RAG errors only
	 *     $ wp gg-data logs list --component=rag --level=error
	 *
	 *     # View last 100 logs as JSON
	 *     $ wp gg-data logs list --limit=100 --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list( $args, $assoc_args ) {
		$level     = $assoc_args['level'] ?? null;
		$component = $assoc_args['component'] ?? null;
		$limit     = intval( $assoc_args['limit'] ?? 50 );
		$format    = $assoc_args['format'] ?? 'table';

		// Validate level if provided.
		$valid_levels = $this->get_logger()->get_log_levels();
		if ( $level && ! in_array( $level, $valid_levels, true ) ) {
			WP_CLI::error(
				sprintf(
					'Invalid level: %s. Valid levels: %s',
					$level,
					implode( ', ', $valid_levels )
				)
			);
			return;
		}

		// Validate component if provided.
		$valid_components = $this->get_logger()->get_components();
		if ( $component && ! in_array( $component, $valid_components, true ) ) {
			WP_CLI::error(
				sprintf(
					'Invalid component: %s. Valid components: %s',
					$component,
					implode( ', ', $valid_components )
				)
			);
			return;
		}

		// Get logs.
		$result = $this->get_logger()->get_logs(
			array(
				'level'     => $level,
				'component' => $component,
				'per_page'  => $limit,
				'page'      => 1,
			)
		);

		$logs = $result['logs'] ?? array();

		if ( empty( $logs ) ) {
			WP_CLI::warning( 'No logs found matching criteria.' );
			return;
		}

		// Format output.
		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $logs, JSON_PRETTY_PRINT ) );
			return;
		}

		// Prepare items for table/csv format.
		$items = array();
		foreach ( $logs as $log ) {
			$items[] = array(
				'id'         => $log['id'],
				'logged_at'  => $log['logged_at'],
				'level'      => strtoupper( $log['level'] ),
				'component'  => $log['component'],
				'message'    => $this->truncate_message( $log['message'], 60 ),
				'connection' => $log['connection_id'] ?? '',
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'logged_at', 'level', 'component', 'message', 'connection' ) );

		WP_CLI::log( sprintf( 'Showing %d of %d total logs', count( $logs ), $result['total_items'] ?? count( $logs ) ) );
	}

	/**
	 * Export logs to file.
	 *
	 * ## OPTIONS
	 *
	 * [--level=<level>]
	 * : Filter by log level.
	 * ---
	 * options:
	 *   - debug
	 *   - info
	 *   - warning
	 *   - error
	 *   - critical
	 * ---
	 *
	 * [--component=<component>]
	 * : Filter by component.
	 * ---
	 * options:
	 *   - rag
	 *   - search
	 *   - sync
	 *   - vectors
	 *   - connection
	 *   - model
	 *   - cron
	 *   - system
	 * ---
	 *
	 * [--format=<format>]
	 * : Export format.
	 * ---
	 * default: csv
	 * options:
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Export all logs as JSON
	 *     $ wp gg-data logs export --format=json > logs.json
	 *
	 *     # Export error logs as CSV
	 *     $ wp gg-data logs export --level=error --format=csv > errors.csv
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ) {
		$level     = $assoc_args['level'] ?? null;
		$component = $assoc_args['component'] ?? null;
		$format    = $assoc_args['format'] ?? 'csv';

		// Validate level if provided.
		$valid_levels = $this->get_logger()->get_log_levels();
		if ( $level && ! in_array( $level, $valid_levels, true ) ) {
			WP_CLI::error(
				sprintf(
					'Invalid level: %s. Valid levels: %s',
					$level,
					implode( ', ', $valid_levels )
				)
			);
			return;
		}

		// Validate component if provided.
		$valid_components = $this->get_logger()->get_components();
		if ( $component && ! in_array( $component, $valid_components, true ) ) {
			WP_CLI::error(
				sprintf(
					'Invalid component: %s. Valid components: %s',
					$component,
					implode( ', ', $valid_components )
				)
			);
			return;
		}

		// Export logs.
		$export = $this->get_logger()->export_logs(
			array(
				'level'     => $level,
				'component' => $component,
			),
			$format
		);

		// Output to stdout for piping to file.
		WP_CLI::log( $export );
	}

	/**
	 * Purge old logs.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<number>]
	 * : Number of days to retain logs.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Purge logs older than 30 days (default)
	 *     $ wp gg-data logs purge
	 *
	 *     # Purge logs older than 7 days
	 *     $ wp gg-data logs purge --days=7
	 *
	 *     # Purge without confirmation
	 *     $ wp gg-data logs purge --days=7 --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function purge( $args, $assoc_args ) {
		$default_days = (int) get_option( 'gg_data_log_retention_days', 30 );
		$days         = intval( $assoc_args['days'] ?? $default_days );

		// Validate days.
		if ( $days < 1 ) {
			WP_CLI::error( 'Days must be at least 1.' );
			return;
		}

		// Get current stats before purge.
		$stats      = $this->get_logger()->get_stats();
		$total_logs = $stats['total'] ?? 0;

		// Confirmation prompt.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm(
				sprintf(
					'This will delete logs older than %d days. Current total: %d logs. Continue?',
					$days,
					$total_logs
				)
			);
		}

		// Purge old logs.
		$deleted = $this->get_logger()->purge_old_logs( $days, 'manual' );

		if ( $deleted > 0 ) {
			WP_CLI::success( sprintf( 'Purged %d log entries older than %d days.', $deleted, $days ) );
		} else {
			WP_CLI::log( sprintf( 'No logs older than %d days to purge.', $days ) );
		}
	}

	/**
	 * Display log statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # View log statistics
	 *     $ wp gg-data logs stats
	 *
	 *     # Stats as JSON
	 *     $ wp gg-data logs stats --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function stats( $args, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'table';

		$stats = $this->get_logger()->get_stats();

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $stats, JSON_PRETTY_PRINT ) );
			return;
		}

		// Display total.
		WP_CLI::log( sprintf( 'Total Logs: %d', $stats['total'] ?? 0 ) );
		WP_CLI::log( '' );

		// Display by level.
		WP_CLI::log( '=== By Level ===' );
		$level_items = array();
		foreach ( $stats['by_level'] ?? array() as $level => $count ) {
			$level_items[] = array(
				'level' => strtoupper( $level ),
				'count' => $count,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $level_items, array( 'level', 'count' ) );

		WP_CLI::log( '' );

		// Display by component.
		WP_CLI::log( '=== By Component ===' );
		$component_items = array();
		foreach ( $stats['by_component'] ?? array() as $component => $count ) {
			$component_items[] = array(
				'component' => $component,
				'count'     => $count,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $component_items, array( 'component', 'count' ) );
	}

	/**
	 * Truncate message for display
	 *
	 * @param string $message Message to truncate.
	 * @param int    $length  Max length.
	 * @return string Truncated message.
	 */
	private function truncate_message( $message, $length = 60 ) {
		if ( strlen( $message ) <= $length ) {
			return $message;
		}
		return substr( $message, 0, $length - 3 ) . '...';
	}
}
