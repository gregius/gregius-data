<?php
/**
 * Connection Health Monitor
 *
 * Tracks PostgreSQL connection health, sends email alerts on failures,
 * and provides health status for dashboard display.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Connection Health Monitor Class
 */
class GG_Data_Connection_Health {

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings;

	/**
	 * Health status option key prefix (per-connection)
	 *
	 * @var string
	 */
	const HEALTH_STATUS_KEY_PREFIX = 'gg_data_connection_health_status_';

	/**
	 * Last email sent option key prefix (per-connection)
	 *
	 * @var string
	 */
	const LAST_EMAIL_KEY_PREFIX = 'gg_data_last_health_email_';

	/**
	 * Maximum consecutive failures before alerting
	 *
	 * @var int
	 */
	const FAILURE_THRESHOLD = 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger   = new GG_Data_Logger();
		$this->settings = new GG_Data_Settings_Manager();
	}

	/**
	 * Record a successful connection
	 *
	 * @param string|null $connection_name Optional connection name. If not provided, uses active connection.
	 * @return void
	 */
	public function record_success( $connection_name = null ) {
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$health_status = $this->get_health_status( $connection_name );

		// Check if we're recovering from failures.
		$was_failing = $health_status['consecutive_failures'] >= self::FAILURE_THRESHOLD;

		// Update health status.
		$health_status['status']       = 'healthy';
		$health_status['last_success'] = time();
		$health_status['last_check']   = time();
		++$health_status['total_checks'];
		$health_status['consecutive_failures'] = 0;

		$option_key = self::HEALTH_STATUS_KEY_PREFIX . $connection_name;
		update_option( $option_key, $health_status );

		// Send recovery email if we were in failure state.
		if ( $was_failing ) {
			$this->send_recovery_email( $health_status, $connection_name );
		}

		$this->logger->log( 'Connection health: success for connection: ' . $connection_name, 'info', 'connection', $connection_name );
	}

	/**
	 * Record a failed connection
	 *
	 * @param string      $error_message   Error message from the failure.
	 * @param string|null $connection_name Optional connection name. If not provided, uses active connection.
	 * @return void
	 */
	public function record_failure( $error_message, $connection_name = null ) {
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$health_status = $this->get_health_status( $connection_name );

		$health_status['status']       = 'unhealthy';
		$health_status['last_check']   = time();
		$health_status['last_error']   = $error_message;
		$health_status['last_failure'] = time();
		++$health_status['total_checks'];
		++$health_status['total_failures'];
		++$health_status['consecutive_failures'];

		$option_key = self::HEALTH_STATUS_KEY_PREFIX . $connection_name;
		update_option( $option_key, $health_status );

		// Send email alert after 3 consecutive failures (15 minutes down).
		if ( self::FAILURE_THRESHOLD === $health_status['consecutive_failures'] ) {
			$this->send_alert_email( $health_status, $connection_name );
		}

		$this->logger->log(
			sprintf(
				'Connection health check failed for %s (attempt %d): %s',
				$connection_name,
				$health_status['consecutive_failures'],
				$error_message
			),
			'warning'
		);
	}

	/**
	 * Get current health status
	 *
	 * @param string|null $connection_name Optional connection name. If not provided, uses active connection.
	 * @return array Health status array.
	 */
	public function get_health_status( $connection_name = null ) {
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$default_status = array(
			'status'               => 'unknown',
			'last_check'           => 0,
			'last_success'         => 0,
			'last_failure'         => 0,
			'last_error'           => '',
			'consecutive_failures' => 0,
			'total_checks'         => 0,
			'total_failures'       => 0,
			'uptime_percentage'    => 100.0,
		);

		$option_key    = self::HEALTH_STATUS_KEY_PREFIX . $connection_name;
		$health_status = get_option( $option_key, $default_status );

		// Calculate uptime percentage.
		if ( $health_status['total_checks'] > 0 ) {
			$successful_checks                  = $health_status['total_checks'] - $health_status['total_failures'];
			$health_status['uptime_percentage'] = ( $successful_checks / $health_status['total_checks'] ) * 100;
		}

		return $health_status;
	}

	/**
	 * Perform health check (called by WP-Cron)
	 *
	 * @return void
	 */
	public function perform_health_check() {
		// Get the first active connection.
		$active_connections = $this->settings->get_active_connections();

		if ( empty( $active_connections ) ) {
			$this->record_failure( 'No active database connections configured' );
			return;
		}

		// Use the first active connection for health monitoring.
		$connection_name = key( $active_connections );

		// Use Connection Manager's provider-aware test connection method.
		$connection_manager = new GG_Data_Connection_Manager();
		$test_result        = $connection_manager->test_connection( $connection_name );

		if ( $test_result['success'] ) {
			$this->record_success( $connection_name );
		} else {
			$error_message = isset( $test_result['message'] ) ? $test_result['message'] : 'Unknown connection error';
			$this->record_failure( $error_message, $connection_name );
		}
	}

	/**
	 * Send alert email when connection fails
	 *
	 * @param array  $health_status   Current health status.
	 * @param string $connection_name Connection name.
	 * @return void
	 */
	private function send_alert_email( $health_status, $connection_name ) {
		// Prevent email spam - only send once every 6 hours per connection.
		$email_key       = self::LAST_EMAIL_KEY_PREFIX . $connection_name;
		$last_email_sent = get_option( $email_key, 0 );
		if ( time() - $last_email_sent < ( 6 * HOUR_IN_SECONDS ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf( '[%s] PostgreSQL Connection Failed (%s)', $site_name, $connection_name );

		$downtime_minutes = self::FAILURE_THRESHOLD * 5; // Each check is 5 minutes apart.

		$message = sprintf(
			"PostgreSQL connection has failed for %d minutes.\n\n" .
			"Last successful connection: %s\n" .
			"Consecutive failures: %d\n" .
			"Last error: %s\n\n" .
			"Actions to take:\n" .
			"1. Check PostgreSQL server status\n" .
			"2. Verify network connectivity\n" .
			"3. Review connection settings in plugin dashboard\n\n" .
			"Search functionality may be degraded until connection is restored.\n\n" .
			"---\n" .
			"This email was sent by Gregius Data plugin.\n" .
			'To change this email address, go to WordPress Settings → General → Administration Email Address.',
			$downtime_minutes,
			$health_status['last_success'] ? human_time_diff( $health_status['last_success'], time() ) . ' ago' : 'Never',
			$health_status['consecutive_failures'],
			$health_status['last_error']
		);

		wp_mail( $admin_email, $subject, $message );

		update_option( $email_key, time() );

		$this->logger->log(
			sprintf(
				'Connection health alert email sent to %s for %s (failed for %d minutes)',
				$admin_email,
				$connection_name,
				$downtime_minutes
			),
			'warning'
		);
	}

	/**
	 * Send recovery email when connection is restored
	 *
	 * @param array  $health_status   Current health status.
	 * @param string $connection_name Connection name.
	 * @return void
	 */
	private function send_recovery_email( $health_status, $connection_name ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf( '[%s] PostgreSQL Connection Restored (%s)', $site_name, $connection_name );

		// Calculate approximate downtime.
		$downtime_seconds = time() - $health_status['last_failure'];
		$downtime_minutes = ceil( $downtime_seconds / 60 );

		$message = sprintf(
			"PostgreSQL connection has been restored.\n\n" .
			"Total downtime: approximately %d minutes\n" .
			"Connection is now healthy and operating normally.\n\n" .
			"Search functionality should be working as expected.\n\n" .
			"---\n" .
			"This email was sent by Gregius Data plugin.\n" .
			'To change this email address, go to WordPress Settings → General → Administration Email Address.',
			$downtime_minutes
		);

		wp_mail( $admin_email, $subject, $message );

		$email_key = self::LAST_EMAIL_KEY_PREFIX . $connection_name;
		update_option( $email_key, time() );

		$this->logger->log(
			sprintf(
				'Connection %s restored after %d minutes downtime. Recovery email sent to %s',
				$connection_name,
				$downtime_minutes,
				$admin_email
			),
			'info'
		);
	}

	/**
	 * Reset health status (useful for testing or manual reset)
	 *
	 * @param string|null $connection_name Optional connection name. If not provided, uses active connection.
	 * @return void
	 */
	public function reset_health_status( $connection_name = null ) {
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$status_key = self::HEALTH_STATUS_KEY_PREFIX . $connection_name;
		$email_key  = self::LAST_EMAIL_KEY_PREFIX . $connection_name;

		delete_option( $status_key );
		delete_option( $email_key );

		$this->logger->log( 'Connection health status reset for connection: ' . $connection_name, 'info', 'connection', $connection_name );
	}
}
