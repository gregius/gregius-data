<?php
/**
 * Log retention scheduler and runtime handler.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GG_Data_Log_Retention' ) ) {

	/**
	 * Handles scheduled log retention purge operations.
	 */
	class GG_Data_Log_Retention {

		/**
		 * Canonical retention cron event.
		 */
		const CRON_EVENT = 'gg_data_daily_log_retention_purge';

		/**
		 * Logger instance.
		 *
		 * @var GG_Data_Logger
		 */
		private $logger;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->logger = new GG_Data_Logger();
		}

		/**
		 * Register runtime hooks.
		 */
		public function init() {
			add_action( self::CRON_EVENT, array( $this, 'handle_scheduled_purge' ) );
		}

		/**
		 * Handle scheduled daily purge.
		 */
		public function handle_scheduled_purge() {
			$days = (int) get_option( 'gg_data_log_retention_days', 30 );
			$this->logger->purge_old_logs( $days, 'cron' );
		}

		/**
		 * Ensure retention event is scheduled for current site.
		 */
		public static function schedule_for_current_site() {
			if ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
				wp_schedule_event( strtotime( 'tomorrow 2:00 AM' ), 'daily', self::CRON_EVENT );
			}
		}

		/**
		 * Clear retention event for current site.
		 */
		public static function clear_for_current_site() {
			wp_clear_scheduled_hook( self::CRON_EVENT );
		}
	}
}
