<?php
/**
 * Search Fallback Manager
 *
 * Manages graceful degradation from PostgreSQL to MySQL search with health tracking.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Search_Fallback
 *
 * Handles PostgreSQL search with automatic MySQL fallback and health monitoring.
 */
class GG_Data_Search_Fallback {

	/**
	 * Health tracking option name
	 *
	 * @var string
	 */
	const HEALTH_OPTION = 'gg_data_search_health';

	/**
	 * Consecutive failure threshold for email alerts
	 *
	 * @var int
	 */
	const FAILURE_THRESHOLD = 5;

	/**
	 * Email cooldown period (24 hours in seconds)
	 *
	 * @var int
	 */
	const EMAIL_COOLDOWN = 86400;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new GG_Data_Logger();
	}

	/**
	 * Execute search with PostgreSQL fallback to MySQL
	 *
	 * @param callable $postgresql_callback PostgreSQL search function.
	 * @param callable $mysql_callback MySQL fallback function.
	 * @param array    $args Search arguments.
	 * @return mixed Search results from PostgreSQL or MySQL.
	 */
	public function execute_with_fallback( $postgresql_callback, $mysql_callback, $args = array() ) {
		try {
			// Attempt PostgreSQL search.
			$start_time = microtime( true );
			$results    = call_user_func( $postgresql_callback, $args );
			$latency    = round( ( microtime( true ) - $start_time ) * 1000, 2 ); // Convert to millisecond.

			// Success - record metrics.
			$this->record_success( $latency );

			$this->logger->log(
				'PostgreSQL search successful. Latency: ' . $latency . 'ms, Results: ' . ( is_array( $results ) ? count( $results ) : 0 ),
				'debug',
				'search',
				null,
				array(
					'latency_ms'   => $latency,
					'result_count' => is_array( $results ) ? count( $results ) : 0,
				)
			);

			return $results;

		} catch ( Exception $e ) {
			// PostgreSQL search failed - record failure.
			$this->record_failure( $e->getMessage() );

			$this->logger->log(
				'PostgreSQL search failed, falling back to MySQL: ' . $e->getMessage(),
				'error',
				'search',
				null,
				array( 'exception' => get_class( $e ) )
			);

			// Fall back to MySQL.
			try {
				$results = call_user_func( $mysql_callback, $args );

				$this->logger->log(
					'MySQL fallback search successful. Result count: ' . ( is_array( $results ) ? count( $results ) : 0 ),
					'debug',
					'search',
					null,
					array( 'result_count' => is_array( $results ) ? count( $results ) : 0 )
				);

				return $results;

			} catch ( Exception $fallback_error ) {
				$this->logger->log(
					'Both PostgreSQL and MySQL search failed. PG error: ' . $e->getMessage() . ', MySQL error: ' . $fallback_error->getMessage(),
					'critical',
					'search',
					null,
					array(
						'pg_error'    => $e->getMessage(),
						'mysql_error' => $fallback_error->getMessage(),
					)
				);

				// Return empty results rather than breaking the site.
				return array();
			}
		}
	}

	/**
	 * Record successful search
	 *
	 * @param float $latency_ms Search latency in milliseconds.
	 */
	private function record_success( $latency_ms ) {
		$health = $this->get_health();

		++$health['total_searches'];
		++$health['successful_searches'];
		$health['consecutive_failures'] = 0; // Reset failure counter.
		$health['last_success']         = current_time( 'mysql' );
		$health['last_latency_ms']      = $latency_ms;

		// Calculate success rate.
		$health['success_rate'] = ( $health['successful_searches'] / $health['total_searches'] ) * 100;

		$this->save_health( $health );
	}

	/**
	 * Record failed search
	 *
	 * @param string $error_message Error message.
	 */
	private function record_failure( $error_message ) {
		$health = $this->get_health();

		++$health['total_searches'];
		++$health['consecutive_failures'];
		$health['last_error']      = $error_message;
		$health['last_error_time'] = current_time( 'mysql' );

		// Calculate success rate.
		$health['success_rate'] = ( $health['successful_searches'] / $health['total_searches'] ) * 100;

		// Add to recent errors (keep last 10).
		if ( ! isset( $health['recent_errors'] ) ) {
			$health['recent_errors'] = array();
		}
		array_unshift(
			$health['recent_errors'],
			array(
				'message'   => $error_message,
				'timestamp' => current_time( 'mysql' ),
			)
		);
		$health['recent_errors'] = array_slice( $health['recent_errors'], 0, 10 );

		$this->save_health( $health );

		// Check if we should send email alert.
		if ( $health['consecutive_failures'] >= self::FAILURE_THRESHOLD ) {
			$this->maybe_send_alert( $health );
		}
	}

	/**
	 * Get current health status
	 *
	 * @return array Health metrics.
	 */
	public function get_health() {
		$default = array(
			'total_searches'       => 0,
			'successful_searches'  => 0,
			'consecutive_failures' => 0,
			'success_rate'         => 100.0,
			'last_success'         => null,
			'last_error'           => null,
			'last_error_time'      => null,
			'last_latency_ms'      => null,
			'last_alert_sent'      => null,
			'recent_errors'        => array(),
		);

		$health = get_option( self::HEALTH_OPTION, $default );

		// Ensure all keys exist (for upgrades).
		return wp_parse_args( $health, $default );
	}

	/**
	 * Save health status
	 *
	 * @param array $health Health metrics.
	 */
	private function save_health( $health ) {
		update_option( self::HEALTH_OPTION, $health );
	}

	/**
	 * Get current health status code
	 *
	 * @return string 'active', 'degraded', or 'critical'.
	 */
	public function get_status() {
		$health = $this->get_health();

		if ( $health['consecutive_failures'] >= self::FAILURE_THRESHOLD ) {
			return 'critical'; // Using MySQL fallback only.
		}

		if ( $health['consecutive_failures'] > 0 ) {
			return 'degraded'; // Some failures, still mostly working.
		}

		return 'active'; // PostgreSQL working perfectly.
	}

	/**
	 * Send email alert if threshold reached and cooldown expired
	 *
	 * @param array $health Current health metrics.
	 */
	private function maybe_send_alert( $health ) {
		// Check email cooldown.
		if ( ! empty( $health['last_alert_sent'] ) ) {
			$last_alert = strtotime( $health['last_alert_sent'] );
			if ( ( time() - $last_alert ) < self::EMAIL_COOLDOWN ) {
				return; // Still in cooldown period.
			}
		}

		// Send alert email.
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );

		$subject = sprintf(
			'[%s] PostgreSQL Search Degraded',
			$site_name
		);

		$message = sprintf(
			"PostgreSQL search has failed %d consecutive times and is currently using MySQL fallback.\n\n" .
			"Last Error: %s\n" .
			"Last Error Time: %s\n" .
			"Success Rate: %.2f%%\n" .
			"Total Searches: %d\n\n" .
			"Please check your PostgreSQL connection and search configuration.\n\n" .
			'Dashboard: %s',
			$health['consecutive_failures'],
			$health['last_error'],
			$health['last_error_time'],
			$health['success_rate'],
			$health['total_searches'],
			admin_url( 'admin.php?page=gg-data-sync#/search' )
		);

		wp_mail( $admin_email, $subject, $message );

		// Update last alert time.
		$health['last_alert_sent'] = current_time( 'mysql' );
		$this->save_health( $health );

		$this->logger->log(
			'PostgreSQL search alert email sent. Consecutive failures: ' . $health['consecutive_failures'] . ', Admin email: ' . $admin_email,
			'warning',
			'search',
			null,
			array(
				'consecutive_failures' => $health['consecutive_failures'],
				'admin_email'          => $admin_email,
			)
		);
	}

	/**
	 * Reset health metrics
	 *
	 * Useful for testing or after fixing connection issues.
	 */
	public function reset_metrics() {
		delete_option( self::HEALTH_OPTION );
		$this->logger->log(
			'Search health metrics reset',
			'info',
			'search'
		);
	}

	/**
	 * Manual health check
	 *
	 * Performs a test search to verify PostgreSQL connectivity.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $connection_name Optional. PostgreSQL connection name. Default null.
	 * @return array Health check results.
	 * @throws Exception If connection fails or health check query fails.
	 */
	public function check_health( $connection_name = null ) {
		try {
			// If no connection name provided, try to detect it.
			if ( empty( $connection_name ) ) {
				$settings        = new GG_Data_Settings_Manager();
				$connection_name = $settings->get( 'search.active_connection', 'default' );

				// Try to get from search settings if not found.
				if ( empty( $connection_name ) || 'default' === $connection_name ) {
					// Get first available connection from connections category.
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch active connection from custom plugin table wp_gg_settings
					$connection_name = $wpdb->get_var(
						"SELECT DISTINCT connection_name FROM {$wpdb->prefix}gg_settings 
						WHERE category = 'connections' AND is_active = 1 
						ORDER BY connection_name ASC LIMIT 1"
					);
				}
			}

			if ( empty( $connection_name ) ) {
				throw new Exception( 'No active PostgreSQL connection configured' );
			}

			// Get connection config to determine provider type.
			$settings    = new GG_Data_Settings_Manager();
			$connections = $settings->get_all_connections();
			$config      = $connections[ $connection_name ] ?? array();
			$provider    = isset( $config['type'] ) ? $config['type'] : 'postgresql';

			// Route to appropriate health check.
			if ( 'postgrest' === $provider ) {
				return $this->check_health_supabase( $connection_name, $config );
			}

			// PDO PostgreSQL path.
			$db         = new GG_Data_DB();
			$connection = $db->get_connection( $connection_name );

			if ( ! $connection ) {
				throw new Exception( 'Failed to connect to PostgreSQL connection: ' . $connection_name );
			}

			// Simple query to test connectivity using PDO.
			$stmt = $connection->query( 'SELECT 1 as health_check' );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$result = $stmt->fetch( PDO::FETCH_ASSOC );

			if ( ! $result ) {
				throw new Exception( 'Health check query failed' );
			}

			// Validate runtime search function readiness (not only DB connectivity).
			$fn_stmt = $connection->query(
				"SELECT proname FROM pg_proc p
				 JOIN pg_namespace n ON p.pronamespace = n.oid
				 WHERE n.nspname = 'public'
				   AND p.proname = 'search_native_orchestrate'"
			);

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
			$has_orchestrator = $fn_stmt && (bool) $fn_stmt->fetch( PDO::FETCH_COLUMN );

			if ( ! $has_orchestrator ) {
				throw new Exception( 'Missing PostgreSQL search function: search_native_orchestrate' );
			}

			// Record successful check.
			$this->record_success( 0 ); // 0ms latency for health check.

			return array(
				'status'    => 'healthy',
				'message'   => 'PostgreSQL connection working',
				'timestamp' => current_time( 'mysql' ),
			);

		} catch ( Exception $e ) {
			// Record failed check.
			$this->record_failure( $e->getMessage() );

			return array(
				'status'    => 'unhealthy',
				'message'   => $e->getMessage(),
				'timestamp' => current_time( 'mysql' ),
			);
		}
	}

	/**
	 * Check Supabase health via REST API
	 *
	 * @param string $connection_name Connection name.
	 * @param array  $config          Connection configuration.
	 * @return array Health check result.
	 * @throws Exception If health check fails.
	 * @since 1.0.0
	 */
	private function check_health_supabase( $connection_name, $config ) {
		try {
			if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
				require_once __DIR__ . '/../providers/class-gg-postgrest-provider.php';
			}

			$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );

			if ( is_wp_error( $runtime_config ) ) {
				throw new Exception( $runtime_config->get_error_message() );
			}

			// Simple health check - query any table with limit 0.
			$url  = rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/wp_posts?limit=0';
			$args = array(
				'method'  => 'HEAD',
				'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
				'timeout' => 5,
			);

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				throw new Exception( 'Supabase health check failed: ' . $response->get_error_message() );
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $status_code ) {
				throw new Exception( 'Supabase returned status ' . $status_code );
			}

			// Record successful check.
			$this->record_success( 0 ); // 0ms latency for health check.

			return array(
				'status'    => 'healthy',
				'message'   => 'Supabase connection working',
				'timestamp' => current_time( 'mysql' ),
			);

		} catch ( Exception $e ) {
			// Record failed check.
			$this->record_failure( $e->getMessage() );

			return array(
				'status'    => 'unhealthy',
				'message'   => $e->getMessage(),
				'timestamp' => current_time( 'mysql' ),
			);
		}
	}
}
