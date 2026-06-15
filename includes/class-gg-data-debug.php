<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Debug helper class for Gregius Data
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'GG_Data_Debug' ) ) {

	/**
	 * Debug helper class
	 */
	class GG_Data_Debug {

		/**
		 * Check if debug mode is enabled
		 *
		 * @return bool True if debug mode is enabled
		 */
		public static function is_debug_mode() {
			// Check for constant.
			if ( defined( 'GG_DATA_DEBUG_MODE' ) && GG_DATA_DEBUG_MODE ) {
				return true;
			}

			// Also check for option (allows runtime toggle).
			return (bool) get_option( 'gg_data_debug_mode', false );
		}

		/**
		 * Log a debug message if debug mode is enabled
		 *
		 * @param string $message The debug message to log.
		 * @param object $logger The logger instance (optional).
		 * @return void
		 */
		public static function log( $message, $logger = null ) {
			// Skip if debug mode is not enabled.
			if ( ! self::is_debug_mode() ) {
				return;
			}

			// If logger is provided, use it.
			if ( $logger && is_object( $logger ) && method_exists( $logger, 'log' ) ) {
				$logger->log( $message, 'debug', 'system' );
				return;
			}

			// Otherwise, use the default logger if available.
			if ( class_exists( 'GG_Data_Logger' ) ) {
				$default_logger = new GG_Data_Logger();
				$default_logger->log( $message, 'debug', 'system' );
			}
		}
	}
}
