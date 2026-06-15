<?php
/**
 * WP-Cron Schedule Manager
 *
 * Centralizes all custom cron schedule definitions for the plugin.
 * Ensures schedules are registered before any wp_schedule_event() calls.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron Schedule Manager Class
 *
 * Manages custom WP-Cron schedule intervals for background processing.
 */
class GG_Data_Cron_Manager {

	/**
	 * Register custom cron schedules with WordPress.
	 *
	 * Must be called early in plugin load (before any wp_schedule_event calls).
	 *
	 * @since 1.0.0
	 */
	public static function register_schedules() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_schedules' ) );
	}

	/**
	 * Add custom cron schedules to WordPress.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing WordPress cron schedules.
	 * @return array Modified schedules array with custom intervals.
	 */
	public static function add_custom_schedules( $schedules ) {
		// Every minute schedule (Retry Queue processing).
		// Used by: GG_Data_Retry_Queue::__construct() -> wp_schedule_event().
		$schedules['gg_data_every_minute'] = array(
			'interval' => 60,
			'display'  => did_action( 'init' ) ? __( 'Every Minute', 'gregius-data' ) : 'Every Minute',
		);

		// Every 5 minutes schedule (Connection Health monitoring).
		// Used by: GG_DATA_Activator::activate() -> wp_schedule_event().
		$schedules['gg_data_every_five_minutes'] = array(
			'interval' => 300,
			'display'  => did_action( 'init' ) ? __( 'Every 5 Minutes', 'gregius-data' ) : 'Every 5 Minutes',
		);

		return $schedules;
	}

	/**
	 * Get list of all custom schedule intervals.
	 *
	 * Useful for debugging and displaying active schedules.
	 *
	 * @since 1.0.0
	 * @return array Array of schedule keys with their intervals and usage.
	 */
	public static function get_schedules() {
		return array(
			'gg_data_every_minute'       => array(
				'interval'  => 60,
				'display'   => __( 'Every Minute', 'gregius-data' ),
				'used_by'   => 'GG_Data_Retry_Queue (Retry queue processing)',
				'cron_hook' => 'gg_data_process_retry_queue',
			),
			'gg_data_every_five_minutes' => array(
				'interval'  => 300,
				'display'   => __( 'Every 5 Minutes', 'gregius-data' ),
				'used_by'   => 'GG_Data_Connection_Health (Connection health checks)',
				'cron_hook' => 'gg_data_check_connection_health',
			),
		);
	}
}
