<?php
/**
 * Error Handler Class
 *
 * Classifies PostgreSQL errors as transient (retry-safe) or permanent (requires intervention).
 * Used by retry queue system to determine if failed sync operations should be retried.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error Handler Class
 *
 * Provides error classification and retry delay calculation for sync operations.
 */
class GG_Data_Error_Handler {

	/**
	 * Transient error patterns that should be retried
	 *
	 * These errors are usually temporary and resolve themselves:
	 * - Network issues (timeouts, connection refused)
	 * - Resource contention (deadlocks, too many connections)
	 * - Temporary failures (server restarts, maintenance)
	 *
	 * @var array
	 */
	const TRANSIENT_ERROR_PATTERNS = array(
		'connection timeout',
		'server has gone away',
		'lost connection',
		'deadlock detected',
		'could not connect to server',
		'too many connections',
		'connection refused',
		'network error',
		'temporary failure',
		'try again later',
		'statement timeout',
		'connection reset',
		'broken pipe',
		'no route to host',
		'operation timed out',
		'connection timed out',
		'lock wait timeout',
		'query timeout',
	);

	/**
	 * Permanent error patterns that should NOT be retried
	 *
	 * These errors require manual intervention to fix:
	 * - Schema issues (missing columns/tables)
	 * - Data validation errors (constraint violations)
	 * - Authentication/permission issues
	 *
	 * @var array
	 */
	const PERMANENT_ERROR_PATTERNS = array(
		'column does not exist',
		'table does not exist',
		'foreign key constraint',
		'unique violation',
		'invalid input syntax',
		'permission denied',
		'authentication failed',
		'relation does not exist',
		'syntax error',
		'invalid parameter',
		'undefined column',
		'undefined table',
		'duplicate key value',
		'check constraint',
		'not null violation',
		'invalid schema',
	);

	/**
	 * Classify error as transient, permanent, or unknown
	 *
	 * @param string $error_message The error message to classify.
	 * @return array {
	 *     Classification result.
	 *
	 *     @type string $type         Error type: 'transient', 'permanent', or 'unknown'.
	 *     @type bool   $retry_safe   Whether it's safe to retry this error.
	 *     @type string $message      Human-readable classification message.
	 * }
	 */
	public static function classify_error( $error_message ) {
		$error_lower = strtolower( $error_message );

		// Check for transient patterns.
		foreach ( self::TRANSIENT_ERROR_PATTERNS as $pattern ) {
			if ( strpos( $error_lower, $pattern ) !== false ) {
				return array(
					'type'       => 'transient',
					'retry_safe' => true,
					'message'    => 'Transient error detected - will retry with exponential backoff',
				);
			}
		}

		// Check for permanent patterns.
		foreach ( self::PERMANENT_ERROR_PATTERNS as $pattern ) {
			if ( strpos( $error_lower, $pattern ) !== false ) {
				return array(
					'type'       => 'permanent',
					'retry_safe' => false,
					'message'    => 'Permanent error - manual intervention required',
				);
			}
		}

		// Unknown error - cautiously allow retry.
		return array(
			'type'       => 'unknown',
			'retry_safe' => true,
			'message'    => 'Unknown error type - will retry cautiously',
		);
	}

	/**
	 * Calculate next retry delay with exponential backoff
	 *
	 * Retry schedule:
	 * - Attempt 1: 5 seconds
	 * - Attempt 2: 30 seconds
	 * - Attempt 3: 5 minutes (300 seconds)
	 *
	 * @param int $attempt_number The attempt number (1-based).
	 * @return int Delay in seconds before next retry.
	 */
	public static function calculate_retry_delay( $attempt_number ) {
		// Exponential backoff: 5s, 30s, 5min.
		$delays = array( 5, 30, 300 );

		$index = min( $attempt_number - 1, count( $delays ) - 1 );
		return $delays[ $index ];
	}

	/**
	 * Format retry schedule for display
	 *
	 * @param int $max_attempts Maximum number of retry attempts.
	 * @return string Human-readable retry schedule.
	 */
	public static function get_retry_schedule_text( $max_attempts = 3 ) {
		$schedule_parts = array();

		for ( $i = 1; $i <= $max_attempts; $i++ ) {
			$delay            = self::calculate_retry_delay( $i );
			$schedule_parts[] = sprintf(
				/* translators: 1: attempt number, 2: delay in seconds */
				__( 'Attempt %1$d: %2$ds', 'gregius-data' ),
				$i,
				$delay
			);
		}

		return implode( ', ', $schedule_parts );
	}
}
