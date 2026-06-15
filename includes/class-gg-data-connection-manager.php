<?php
/**
 * PostgreSQL Connection Manager
 *
 * This file contains the connection manager class for handling database connections.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PostgreSQL Connection Manager
 *
 * Handles database connections using provider abstraction layer.
 * Supports multiple database types (PostgreSQL now, MySQL 9.0+ future).
 *
 * @package Gregius_Data
 */
class GG_Data_Connection_Manager {

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Provider instances per connection
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Connection health cache
	 *
	 * @var array
	 */
	private $health_cache = array();

	/**
	 * Cache timeout for health checks (in seconds)
	 *
	 * @var int
	 */
	private $health_cache_timeout = 300; // 5 minutes

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new GG_Data_Settings_Manager();

		// Cleanup connections on shutdown.
		add_action( 'shutdown', array( $this, 'cleanup_connections' ) );
	}

	/**
	 * Get a database provider instance for a connection
	 *
	 * @param string $connection_name Connection name.
	 * @return GG_Data_DB_Provider|false Provider instance or false on failure
	 */
	public function get_provider( $connection_name = 'default' ) {
		// Get connection configuration.
		$config = $this->settings_manager->get_connection( $connection_name );
		if ( ! $config ) {
			return false;
		}

		// Check if provider already exists.
		if ( isset( $this->providers[ $connection_name ] ) ) {
			$provider = $this->providers[ $connection_name ];

			if ( method_exists( $provider, 'is_connected' ) && $provider->is_connected() ) {
				return $provider;
			}

			$connect_result = $provider->connect( $config );
			if ( ! is_array( $connect_result ) || empty( $connect_result['success'] ) ) {
				unset( $this->providers[ $connection_name ] );
				return false;
			}

			return $provider;
		}

		// Determine database type (default to PostgreSQL for existing connections).
		$database_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		try {
			// Create provider instance using factory.
			$provider = GG_Data_Provider_Factory::create_provider( $database_type, $config, $connection_name );

			if ( ! $provider ) {
				return false;
			}

			$connect_result = $provider->connect( $config );
			if ( ! is_array( $connect_result ) || empty( $connect_result['success'] ) ) {
				return false;
			}

			// Cache the provider.
			$this->providers[ $connection_name ] = $provider;

			return $provider;

		} catch ( Exception $e ) {
			return false;
		}
	}


	/**
	 * Test a database connection using provider architecture
	 *
	 * @param string $connection_name Connection name.
	 * @return array Test result with status and details
	 */
	public function test_connection( $connection_name ) {
		$start_time = microtime( true );

		// Get connection configuration.
		$config = $this->settings_manager->get_connection( $connection_name );
		if ( ! $config ) {
			return array(
				'success'       => false,
				'message'       => 'Connection configuration not found',
				'details'       => array(),
				'response_time' => 0,
			);
		}

		// Determine database type (default to PostgreSQL for existing connections).
		$database_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		try {
			// Create provider instance using factory.
			$provider = GG_Data_Provider_Factory::create_provider( $database_type, $config, $connection_name );

			if ( ! $provider ) {
				return array(
					'success'       => false,
					'message'       => 'Failed to create database provider',
					'details'       => array( 'type' => $database_type ),
					'response_time' => round( ( microtime( true ) - $start_time ) * 1000, 2 ),
				);
			}

			// Test connection using provider.
			$result = $provider->test_connection( $config );

			$response_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			// Enhance result with database type information.
			if ( $result['success'] ) {
				$result['details']['database_type']  = $database_type;
				$result['details']['provider_class'] = get_class( $provider );

				// Store version in config for future reference.
				if ( isset( $result['version'] ) ) {
					$config['database_version'] = $result['version'];
					$this->settings_manager->save_connection( $connection_name, $config );
				}
			}

			$result['response_time'] = $response_time;

			return $result;

		} catch ( Exception $e ) {
			return array(
				'success'       => false,
				'message'       => 'Provider error: ' . $e->getMessage(),
				'details'       => array(
					'database_type' => $database_type,
					'host'          => $config['host'] ?? 'unknown',
					'database'      => $config['database'] ?? 'unknown',
				),
				'response_time' => round( ( microtime( true ) - $start_time ) * 1000, 2 ),
			);
		}
	}

	/**
	 * Get health status for a connection
	 *
	 * @param string $connection_name Connection name.
	 * @param bool   $force_check Force a fresh health check.
	 * @return array Health status
	 */
	public function get_connection_health( $connection_name, $force_check = false ) {
		// Check cache first.
		if ( ! $force_check && isset( $this->health_cache[ $connection_name ] ) ) {
			$cached = $this->health_cache[ $connection_name ];
			if ( ( time() - $cached['timestamp'] ) < $this->health_cache_timeout ) {
				return $cached['health'];
			}
		}

		// Perform health check.
		$health = $this->perform_health_check( $connection_name );

		// Cache the result.
		$this->health_cache[ $connection_name ] = array(
			'health'    => $health,
			'timestamp' => time(),
		);

		return $health;
	}

	/**
	 * Perform a health check on a connection
	 *
	 * @param string $connection_name Connection name.
	 * @return array Health check results
	 */
	private function perform_health_check( $connection_name ) {
		$health = array(
			'status'        => 'unknown',
			'message'       => '',
			'checks'        => array(),
			'timestamp'     => time(),
			'response_time' => 0,
		);

		$start_time = microtime( true );

		// Test the connection.
		$test_result = $this->test_connection( $connection_name );

		$health['response_time'] = $test_result['response_time'];

		if ( ! $test_result['success'] ) {
			$health['status']               = 'error';
			$health['message']              = $test_result['message'];
			$health['checks']['connection'] = false;
			return $health;
		}

		$health['checks']['connection'] = true;

		// Additional health checks can be added here.
		$health['checks']['basic_query'] = isset( $test_result['details']['query_test'] ) ?
			$test_result['details']['query_test'] : false;

		// Determine overall status.
		$all_checks_passed = ! in_array( false, $health['checks'], true );

		if ( $all_checks_passed ) {
			$health['status']  = 'healthy';
			$health['message'] = 'All health checks passed';
		} else {
			$health['status']  = 'warning';
			$health['message'] = 'Some health checks failed';
		}

		return $health;
	}

	/**
	 * Get all connection health statuses
	 *
	 * @param bool $force_check Force fresh health checks.
	 * @return array Health statuses for all connections
	 */
	public function get_all_connection_health( $force_check = false ) {
		$connections     = $this->settings_manager->get_all_connections();
		$health_statuses = array();

		foreach ( $connections as $name => $config ) {
			$health_statuses[ $name ] = $this->get_connection_health( $name, $force_check );
		}

		return $health_statuses;
	}

	/**
	 * Close all connections
	 */
	public function cleanup_connections() {
		foreach ( $this->providers as $provider ) {
			if ( method_exists( $provider, 'disconnect' ) ) {
				$provider->disconnect();
			}
		}

		$this->providers    = array();
		$this->health_cache = array();
	}

	/**
	 * Get connection statistics
	 *
	 * @return array Connection statistics
	 */
	public function get_connection_stats() {
		$configured_connections = $this->settings_manager->get_all_connections();
		$stats                  = array(
			'total_connections'  => count( $configured_connections ),
			'active_connections' => 0,
			'connections'        => array(),
			'source'             => 'provider-cache',
		);

		foreach ( $configured_connections as $name => $config ) {
			$provider_exists = isset( $this->providers[ $name ] );
			$connected       = false;

			if ( $provider_exists && method_exists( $this->providers[ $name ], 'is_connected' ) ) {
				$connected = (bool) $this->providers[ $name ]->is_connected();
			}

			if ( $connected ) {
				++$stats['active_connections'];
			}

			$stats['connections'][ $name ] = array(
				'configured'       => true,
				'provider_cached'  => $provider_exists,
				'connected'        => $connected,
				'provider_type'    => isset( $config['type'] ) ? $config['type'] : 'postgresql',
				'last_used'        => null,
				'connection_count' => null,
			);
		}

		return $stats;
	}
}
