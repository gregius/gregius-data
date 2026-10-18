<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Plugin deactivation handling
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'Gregius_Data_Deactivator' ) ) {

	/**
	 * Plugin deactivation class
	 */
	class Gregius_Data_Deactivator {

		/**
		 * Actions to perform on plugin deactivation
		 */
		public static function deactivate() {
			self::clear_log_retention_events();

			// Clear scheduled events (active).
			wp_clear_scheduled_hook( 'gregius_data_process_retry_queue' ); // Retry queue processor.
			wp_clear_scheduled_hook( 'gregius_data_check_connection_health' ); // Connection health monitor.
			wp_clear_scheduled_hook( 'gregius_data_daily_validation' ); // Sync validator.

			// Clear deprecated WP-Cron hooks.
			wp_clear_scheduled_hook( 'gregius_data_cleanup_logs' );          // Removed - now manual only.
			wp_clear_scheduled_hook( 'gregius_data_process_simple_vectors' ); // Vector processor.
			wp_clear_scheduled_hook( 'gregius_data_process_batch' );         // Batch processor.
			wp_clear_scheduled_hook( 'gregius_data_retry_failed' );          // Old sync retry.

			// Remove administrator capabilities.
			$admin_role = get_role( 'administrator' );

			if ( $admin_role ) {
				$admin_role->remove_cap( 'manage_gregius_data' );
			}

			// Clear localStorage on next admin page load.
			set_transient( 'gregius_data_clear_localstorage', true, HOUR_IN_SECONDS );

			// Note: We do not remove plugin settings or logs upon deactivation.
		}

		/**
		 * Clear log retention event in each site context.
		 *
		 * @since 1.0.0
		 */
		private static function clear_log_retention_events() {
			if ( ! class_exists( 'Gregius_Data_Log_Retention' ) ) {
				return;
			}

			if ( is_multisite() ) {
				$sites = get_sites( array( 'number' => 0 ) );

				foreach ( $sites as $site ) {
					switch_to_blog( (int) $site->blog_id );
					Gregius_Data_Log_Retention::clear_for_current_site();
					restore_current_blog();
				}

				return;
			}

			Gregius_Data_Log_Retention::clear_for_current_site();
		}
	}
}
