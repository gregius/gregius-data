<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Logger class for Gregius Data
 *
 * Database-based logging with component categorization, structured context,
 * and multisite support.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'GG_Data_Logger' ) ) {

	/**
	 * Logger class
	 */
	class GG_Data_Logger {

		/**
		 * Database table name
		 *
		 * @var string
		 */
		private $table_name;

		/**
		 * WordPress database instance
		 *
		 * @var wpdb
		 */
		private $wpdb;

		/**
		 * Log levels in order of severity
		 *
		 * @var array
		 */
		protected $log_levels = array(
			'debug',
			'info',
			'warning',
			'error',
			'critical',
		);

		/**
		 * Valid components
		 *
		 * @var array
		 */
		protected $components = array(
			'rag',
			'search',
			'sync',
			'vectors',
			'connection',
			'model',
			'cron',
			'system',
			'legacy', // For backwards compatibility with old log calls.
		);

		/**
		 * Constructor
		 */
		public function __construct() {
			global $wpdb;
			$this->wpdb       = $wpdb;
			$this->table_name = $wpdb->prefix . 'gg_data_logs';
		}

		/**
		 * Get the table name
		 *
		 * @since 1.0.0
		 * @return string Table name with prefix.
		 */
		public function get_table_name() {
			return $this->table_name;
		}

		/**
		 * Check if debug mode is enabled
		 *
		 * @since 1.0.0
		 * @return bool True if debug mode is enabled.
		 */
		public function is_debug_mode() {
			// Check for constant.
			if ( defined( 'GG_DATA_DEBUG_MODE' ) && GG_DATA_DEBUG_MODE ) {
				return true;
			}

			// Check for WP_DEBUG.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return true;
			}

			// Also check for option (allows runtime toggle).
			return (bool) get_option( 'gg_data_debug_mode', false );
		}

		/**
		 * Check if logging is enabled
		 *
		 * @since 1.0.0
		 * @return bool True if logging is enabled.
		 */
		public function is_logging_enabled() {
			return (bool) get_option( 'gg_data_logging_enabled', true );
		}

		/**
		 * Get the configured minimum log level
		 *
		 * @since 1.0.0
		 * @return string Minimum log level.
		 */
		public function get_log_level() {
			return get_option( 'gg_data_log_level', 'info' );
		}

		/**
		 * Log a message to the database
		 *
		 * @since 1.0.0
		 *
		 * @param string      $message       Log message.
		 * @param string      $level         Log level (debug, info, warning, error, critical).
		 * @param string      $component     Component identifier (rag, search, sync, etc.).
		 * @param string|null $connection_id Connection identifier (optional).
		 * @param array       $context       Structured context data (optional).
		 * @return int|false Insert ID on success, false on failure.
		 */
		public function log( $message, $level = 'info', $component = 'system', $connection_id = null, $context = array() ) {
			// Check if logging is enabled.
			if ( ! $this->is_logging_enabled() ) {
				return false;
			}

			// Validate and normalize log level.
			if ( ! in_array( $level, $this->log_levels, true ) ) {
				$level = 'info';
			}

			// Skip debug messages if debug mode is not enabled.
			if ( 'debug' === $level && ! $this->is_debug_mode() ) {
				return false;
			}

			// Skip if log level is lower than configured minimum.
			$level_idx      = array_search( $level, $this->log_levels, true );
			$configured_idx = array_search( $this->get_log_level(), $this->log_levels, true );
			if ( false !== $level_idx && false !== $configured_idx && $level_idx < $configured_idx ) {
				return false;
			}

			// Validate component.
			if ( ! in_array( $component, $this->components, true ) ) {
				$component = 'legacy';
			}

			// Mask sensitive data in context.
			$context         = $this->mask_sensitive_data( $context );
			$encoded_context = null;

			if ( ! empty( $context ) ) {
				$encoded_context = wp_json_encode( $context );

				if ( false === $encoded_context ) {
					$context         = $this->build_log_context_fallback( $context, 'json_encode_failed' );
					$encoded_context = wp_json_encode( $context );
				}
			}

			// Prepare data for insert.
			$data = array(
				'logged_at'     => current_time( 'mysql' ),
				'level'         => $level,
				'component'     => $component,
				'connection_id' => $connection_id,
				'message'       => $message,
				'context'       => $encoded_context,
			);

			$formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );

			// Insert into database.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom logging table, not WordPress core tables.
			$result = $this->wpdb->insert( $this->table_name, $data, $formats );

			if ( false === $result ) {
				if ( ! empty( $context ) ) {
					$fallback_context         = $this->build_log_context_fallback( $context, 'insert_failed' );
					$fallback_data            = $data;
					$fallback_data['context'] = wp_json_encode( $fallback_context );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom logging table, retrying with compact context.
					$result = $this->wpdb->insert( $this->table_name, $fallback_data, $formats );
				}

				if ( false === $result ) {
					// If insert fails (e.g., table doesn't exist yet), fail silently.
					return false;
				}
			}

			return $this->wpdb->insert_id;
		}

		/**
		 * Build a compact fallback log context when full structured context cannot be stored.
		 *
		 * @since 1.0.0
		 * @param array  $context Full context payload.
		 * @param string $reason  Fallback reason.
		 * @return array
		 */
		private function build_log_context_fallback( $context, $reason ) {
			if ( ! is_array( $context ) ) {
				return array(
					'_fallback_reason' => sanitize_key( (string) $reason ),
					'_summary'         => 'Log context fallback applied.',
				);
			}

			$fallback = array(
				'_fallback_reason' => sanitize_key( (string) $reason ),
				'_summary'         => 'Log context fallback applied.',
			);

			foreach ( array( 'request', 'intent', 'outcome', 'execution', 'retrieval_summary', 'policy', 'security', 'context', 'diagnostics' ) as $key ) {
				if ( isset( $context[ $key ] ) ) {
					$fallback[ $key ] = $context[ $key ];
				}
			}

			foreach ( array( 'interaction_id', 'conversation_id', 'type', 'source', 'query', 'response', 'tool_selected', 'sources' ) as $key ) {
				if ( isset( $context[ $key ] ) ) {
					$fallback[ $key ] = $context[ $key ];
				}
			}

			if ( isset( $fallback['context']['manifest_full'] ) ) {
				unset( $fallback['context']['manifest_full'] );
			}

			if ( isset( $fallback['diagnostics']['security_raw_response'] ) ) {
				unset( $fallback['diagnostics']['security_raw_response'] );
			}

			if ( isset( $fallback['diagnostics']['llm_raw_response'] ) ) {
				unset( $fallback['diagnostics']['llm_raw_response'] );
			}

			return $fallback;
		}

		/**
		 * Mask sensitive data in context arrays
		 *
		 * @since 1.0.0
		 *
		 * @param array $context Context data.
		 * @return array Context with sensitive data masked.
		 */
		private function mask_sensitive_data( $context ) {
			$sensitive_keys    = array( 'api_key', 'password', 'secret', 'token', 'authorization' );
			$usage_metric_keys = array( 'prompt_tokens', 'completion_tokens', 'total_tokens', 'tokens_used' );

			foreach ( $context as $key => $value ) {
				// Preserve usage counters (safe numeric telemetry).
				if ( in_array( strtolower( (string) $key ), $usage_metric_keys, true ) ) {
					continue;
				}

				// Check if key contains sensitive keywords.
				foreach ( $sensitive_keys as $sensitive ) {
					if ( false !== stripos( $key, $sensitive ) ) {
						$context[ $key ] = $this->mask_value( $value );
						break;
					}
				}

				// Recursively check nested arrays.
				if ( is_array( $value ) ) {
					$context[ $key ] = $this->mask_sensitive_data( $value );
				}
			}

			return $context;
		}

		/**
		 * Mask a sensitive value
		 *
		 * @since 1.0.0
		 *
		 * @param string $value Value to mask.
		 * @return string Masked value.
		 */
		private function mask_value( $value ) {
			if ( ! is_string( $value ) ) {
				return '****';
			}

			$length = strlen( $value );
			if ( $length <= 8 ) {
				return '****';
			}

			return substr( $value, 0, 4 ) . '****' . substr( $value, -4 );
		}

		/**
		 * Get logs with filtering and pagination
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Query arguments.
		 * @return array Array with 'logs', 'page', 'per_page', 'total_pages', 'total_items'.
		 */
		public function get_logs( $args = array() ) {
			$defaults = array(
				'page'          => 1,
				'per_page'      => 50,
				'level'         => null,
				'component'     => null,
				'connection_id' => null,
				'search'        => null,
				'date_from'     => null,
				'date_to'       => null,
				'orderby'       => 'logged_at',
				'order'         => 'DESC',
			);

			$args = wp_parse_args( $args, $defaults );

			// Sanitize pagination.
			$page     = max( 1, intval( $args['page'] ) );
			$per_page = min( 100, max( 1, intval( $args['per_page'] ) ) );
			$offset   = ( $page - 1 ) * $per_page;

			// Build WHERE clause.
			$where_clauses = array();
			$where_values  = array();

			// Level filter (can be comma-separated).
			if ( ! empty( $args['level'] ) ) {
				$levels = array_map( 'trim', explode( ',', $args['level'] ) );
				$levels = array_filter(
					$levels,
					function ( $l ) {
						return in_array( $l, $this->log_levels, true );
					}
				);
				if ( ! empty( $levels ) ) {
					$placeholders    = implode( ',', array_fill( 0, count( $levels ), '%s' ) );
					$where_clauses[] = "level IN ($placeholders)";
					$where_values    = array_merge( $where_values, $levels );
				}
			}

			// Component filter (can be comma-separated).
			if ( ! empty( $args['component'] ) ) {
				$components = array_map( 'trim', explode( ',', $args['component'] ) );
				$components = array_filter(
					$components,
					function ( $c ) {
						return in_array( $c, $this->components, true );
					}
				);
				if ( ! empty( $components ) ) {
					$placeholders    = implode( ',', array_fill( 0, count( $components ), '%s' ) );
					$where_clauses[] = "component IN ($placeholders)";
					$where_values    = array_merge( $where_values, $components );
				}
			}

			// Connection filter.
			if ( ! empty( $args['connection_id'] ) ) {
				$where_clauses[] = 'connection_id = %s';
				$where_values[]  = $args['connection_id'];
			}

			// Search filter.
			if ( ! empty( $args['search'] ) ) {
				$where_clauses[] = 'message LIKE %s';
				$where_values[]  = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			}

			// Date range filter.
			if ( ! empty( $args['date_from'] ) ) {
				$where_clauses[] = 'logged_at >= %s';
				$where_values[]  = $args['date_from'] . ' 00:00:00';
			}
			if ( ! empty( $args['date_to'] ) ) {
				$where_clauses[] = 'logged_at <= %s';
				$where_values[]  = $args['date_to'] . ' 23:59:59';
			}

			// Build WHERE string.
			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			// Sanitize ORDER BY.
			$allowed_orderby = array( 'id', 'logged_at', 'level', 'component' );
			$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'logged_at';
			$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

			// Get total count.
			$count_sql = "SELECT COUNT(*) FROM {$this->table_name} $where_sql";
			if ( ! empty( $where_values ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built dynamically with proper escaping.
				$count_sql = $this->wpdb->prepare( $count_sql, $where_values );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query with whitelisted columns and prepared values.
			$total_items = (int) $this->wpdb->get_var( $count_sql );

			// Get logs.
			// $orderby is whitelisted, $order is restricted to ASC/DESC, all values use prepare().
			$sql = "SELECT * FROM {$this->table_name} $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";

			$query_values = array_merge( $where_values, array( $per_page, $offset ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built dynamically with proper escaping.
			$prepared_sql = $this->wpdb->prepare( $sql, $query_values );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query with whitelisted columns and prepared values.
			$results = $this->wpdb->get_results( $prepared_sql, ARRAY_A );

			// Parse context JSON.
			$logs = array();
			foreach ( $results as $row ) {
				$row['context'] = ! empty( $row['context'] ) ? json_decode( $row['context'], true ) : null;
				$logs[]         = $row;
			}

			return array(
				'logs'        => $logs,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
				'total_items' => $total_items,
			);
		}

		/**
		 * Get log statistics
		 *
		 * @since 1.0.0
		 * @return array Statistics with total, by_level, and by_component.
		 */
		public function get_stats() {
			// Get total count.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is a class property, not user input.
			$total = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

			// Get counts by level.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is a class property, not user input.
			$level_results = $this->wpdb->get_results(
				"SELECT level, COUNT(*) as count FROM {$this->table_name} GROUP BY level",
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$by_level = array();
			foreach ( $this->log_levels as $level ) {
				$by_level[ $level ] = 0;
			}
			foreach ( $level_results as $row ) {
				$by_level[ $row['level'] ] = (int) $row['count'];
			}

			// Get counts by component.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is a class property, not user input.
			$component_results = $this->wpdb->get_results(
				"SELECT component, COUNT(*) as count FROM {$this->table_name} GROUP BY component",
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$by_component = array();
			foreach ( $this->components as $component ) {
				$by_component[ $component ] = 0;
			}
			foreach ( $component_results as $row ) {
				$by_component[ $row['component'] ] = (int) $row['count'];
			}

			return array(
				'total'        => $total,
				'by_level'     => $by_level,
				'by_component' => $by_component,
			);
		}

		/**
		 * Export logs with filtering
		 *
		 * @since 1.0.0
		 *
		 * @param array  $args   Filter arguments (same as get_logs, but no pagination).
		 * @param string $format Export format: 'csv' or 'json'.
		 * @return string Formatted export data.
		 */
		public function export_logs( $args = array(), $format = 'csv' ) {
			// Remove pagination for export.
			$args['page']     = 1;
			$args['per_page'] = 10000; // Reasonable limit for export.

			$result = $this->get_logs( $args );
			$logs   = $result['logs'];

			if ( 'json' === $format ) {
				return wp_json_encode(
					array(
						'exported_at' => current_time( 'c' ),
						'filters'     => $args,
						'total_count' => count( $logs ),
						'logs'        => $logs,
					),
					JSON_PRETTY_PRINT
				);
			}

			// Default to CSV.
			$csv_lines   = array();
			$csv_lines[] = 'id,logged_at,level,component,connection_id,message,context';

			foreach ( $logs as $log ) {
				$context_json = ! empty( $log['context'] ) ? wp_json_encode( $log['context'] ) : '';
				$csv_lines[]  = sprintf(
					'%d,"%s","%s","%s","%s","%s","%s"',
					$log['id'],
					$log['logged_at'],
					$log['level'],
					$log['component'],
					$log['connection_id'] ?? '',
					str_replace( '"', '""', $log['message'] ),
					str_replace( '"', '""', $context_json )
				);
			}

			return implode( "\n", $csv_lines );
		}

		/**
		 * Purge old logs
		 *
		 * @since 1.0.0
		 *
		 * @param int    $days    Number of days to retain logs (default: 30).
		 * @param string $trigger Purge trigger source (manual or cron).
		 * @return int Number of deleted rows.
		 */
		public function purge_old_logs( $days = 30, $trigger = 'manual' ) {
			$start_time = microtime( true );
			$site_id    = get_current_blog_id();
			$trigger    = in_array( $trigger, array( 'manual', 'cron' ), true ) ? $trigger : 'manual';

			$days = max( 1, intval( $days ) );
			$days = max( 1, intval( apply_filters( 'gg_data_log_retention_days', $days, $site_id ) ) );

			$threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			do_action( 'gg_data_before_purge_logs', $days, $threshold, $site_id );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is a class property, prepare() is used for values.
			$deleted = $this->wpdb->query(
				$this->wpdb->prepare(
					"DELETE FROM {$this->table_name} WHERE logged_at < %s",
					$threshold
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$deleted = max( 0, (int) $deleted );

			do_action( 'gg_data_after_purge_logs', $days, $deleted, $site_id );

			$this->log(
				'Log retention purge completed.',
				'info',
				'system',
				null,
				array(
					'site_id'                  => $site_id,
					'table_name'               => $this->table_name,
					'retention_days_effective' => $days,
					'threshold'                => $threshold,
					'deleted_rows'             => $deleted,
					'duration_ms'              => (int) round( ( microtime( true ) - $start_time ) * 1000 ),
					'trigger'                  => $trigger,
				)
			);

			return $deleted;
		}

		/**
		 * Create the logs table
		 *
		 * @since 1.0.0
		 * @return bool True on success, false on failure.
		 */
		public function create_logs_table() {
			$charset_collate = $this->wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				logged_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				level varchar(20) NOT NULL,
				component varchar(50) NOT NULL,
				connection_id varchar(100) DEFAULT NULL,
				message text NOT NULL,
				context longtext DEFAULT NULL,
				PRIMARY KEY (id),
				KEY idx_logged_at (logged_at),
				KEY idx_level (level),
				KEY idx_component (component),
				KEY idx_connection (connection_id)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			// Verify table was created.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Check if table exists, prepare() is used.
			$table_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$this->table_name
				)
			) === $this->table_name;
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			return $table_exists;
		}

		/**
		 * Create logs tables for all sites in multisite network
		 *
		 * @since 1.0.0
		 * @return array Results of table creation for each site.
		 */
		public function create_logs_tables_for_all_sites() {
			$results = array();

			if ( is_multisite() ) {
				$sites = get_sites( array( 'number' => 0 ) );

				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );

					// Reinitialize with new prefix.
					$this->table_name          = $this->wpdb->prefix . 'gg_data_logs';
					$results[ $site->blog_id ] = $this->create_logs_table();

					restore_current_blog();
				}
			} else {
				$results[1] = $this->create_logs_table();
			}

			return $results;
		}

		/**
		 * Drop the logs table
		 *
		 * @since 1.0.0
		 * @return bool True on success.
		 */
		public function drop_logs_table() {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop on uninstall.
			$this->wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
			return true;
		}

		/**
		 * Get available log levels
		 *
		 * @since 1.0.0
		 * @return array Log levels.
		 */
		public function get_log_levels() {
			return $this->log_levels;
		}

		/**
		 * Get available components
		 *
		 * @since 1.0.0
		 * @return array Components.
		 */
		public function get_components() {
			return $this->components;
		}
	}
}
