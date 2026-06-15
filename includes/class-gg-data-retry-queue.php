<?php
/**
 * Retry Queue Manager
 *
 * Manages automatic retry of failed sync operations with exponential backoff.
 * Integrates with WP-Cron for background processing.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retry Queue Manager Class
 *
 * Handles queueing, processing, and retry logic for failed sync operations.
 */
class GG_Data_Retry_Queue {

	/**
	 * Maximum retry attempts before moving to dead letter queue
	 *
	 * @var int
	 */
	const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * WordPress option key for retry queue storage
	 *
	 * @var string
	 */
	const OPTION_KEY = 'gg_data_retry_queue';

	/**
	 * WordPress option key for dead letter queue storage
	 *
	 * @var string
	 */
	const DEAD_LETTER_KEY = 'gg_data_dead_letter_queue';

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Initialize retry queue and schedule background processor
	 */
	public function __construct() {
		$this->logger = new GG_Data_Logger();

		// Schedule retry processor on init (not plugins_loaded)
		// to avoid triggering cron_schedules filter too early,
		// which causes _load_textdomain_just_in_time notices
		// from other plugins that hook cron_schedules with __() calls.
		add_action( 'init', array( $this, 'schedule_events' ) );

		// Hook into WP-Cron to process queue.
		add_action( 'gg_data_process_retry_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Schedule the retry queue processor cron event.
	 *
	 * Deferred to 'init' to comply with WP 6.7+ translation timing rules.
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( 'gg_data_process_retry_queue' ) ) {
			wp_schedule_event( time(), 'gg_data_every_minute', 'gg_data_process_retry_queue' );
		}
	}

	/**
	 * Add failed sync operation to retry queue
	 *
	 * @param string $operation_type Type of operation (sync_post, sync_meta, delete_post).
	 * @param int    $entity_id      ID of the entity (post ID, meta ID, etc.).
	 * @param string $error_message  The error message from failed operation.
	 * @param array  $operation_data Additional data needed to retry operation (optional).
	 * @return bool True if queued for retry, false if permanent error.
	 */
	public function queue_for_retry( $operation_type, $entity_id, $error_message, $operation_data = array() ) {
		// Classify error to determine if retry is safe.
		$error_classification = GG_Data_Error_Handler::classify_error( $error_message );

		// Don't queue permanent errors.
		if ( ! $error_classification['retry_safe'] ) {
			$this->logger->log(
				sprintf(
					'Permanent error for %s #%d - not queuing for retry: %s',
					$operation_type,
					$entity_id,
					$error_message
				),
				'error'
			);
			return false;
		}

		$queue = get_option( self::OPTION_KEY, array() );

		$queue_item = array(
			'operation_type' => $operation_type,
			'entity_id'      => $entity_id,
			'error_message'  => $error_message,
			'error_type'     => $error_classification['type'],
			'operation_data' => $operation_data,
			'attempt_count'  => 1,
			'first_attempt'  => current_time( 'mysql' ),
			'next_retry'     => wp_date( 'Y-m-d H:i:s', time() + GG_Data_Error_Handler::calculate_retry_delay( 1 ) ),
			'last_error'     => $error_message,
		);

		$queue_key           = $operation_type . '_' . $entity_id;
		$queue[ $queue_key ] = $queue_item;

		update_option( self::OPTION_KEY, $queue );

		$this->logger->log(
			sprintf(
				'Queued %s #%d for retry (attempt 1/%d) - next retry in %d seconds',
				$operation_type,
				$entity_id,
				self::MAX_RETRY_ATTEMPTS,
				GG_Data_Error_Handler::calculate_retry_delay( 1 )
			),
			'warning'
		);

		return true;
	}

	/**
	 * Process retry queue - runs via WP-Cron every minute
	 *
	 * Attempts to retry queued operations that are ready for retry.
	 */
	public function process_queue() {
		$queue = get_option( self::OPTION_KEY, array() );

		if ( empty( $queue ) ) {
			return; // Nothing to process.
		}

		$now       = time();
		$processed = 0;
		$succeeded = 0;
		$failed    = 0;

		foreach ( $queue as $queue_key => $item ) {
			// Skip if not ready for retry yet.
			if ( strtotime( $item['next_retry'] ) > $now ) {
				continue;
			}

			++$processed;

			// Attempt the operation.
			$success = $this->retry_operation( $item );

			if ( $success ) {
				// Success - remove from queue.
				unset( $queue[ $queue_key ] );
				++$succeeded;

				$this->logger->log(
					sprintf(
						'Retry succeeded for %s #%d after %d attempts',
						$item['operation_type'],
						$item['entity_id'],
						$item['attempt_count']
					),
					'info'
				);

			} elseif ( $item['attempt_count'] >= self::MAX_RETRY_ATTEMPTS ) {
				// Max retries reached - move to dead letter queue.
				$this->move_to_dead_letter( $item );
				unset( $queue[ $queue_key ] );
				++$failed;

				$this->logger->log(
					sprintf(
						'Max retries reached for %s #%d - moved to dead letter queue',
						$item['operation_type'],
						$item['entity_id']
					),
					'error'
				);

			} else {
				// Retry failed - increment attempt and schedule next retry.
				++$item['attempt_count'];
				$item['next_retry']  = wp_date(
					'Y-m-d H:i:s',
					time() + GG_Data_Error_Handler::calculate_retry_delay( $item['attempt_count'] )
				);
				$queue[ $queue_key ] = $item;

				$this->logger->log(
					sprintf(
						'Retry failed for %s #%d (attempt %d/%d) - next retry in %d seconds',
						$item['operation_type'],
						$item['entity_id'],
						$item['attempt_count'],
						self::MAX_RETRY_ATTEMPTS,
						GG_Data_Error_Handler::calculate_retry_delay( $item['attempt_count'] )
					),
					'warning'
				);
			}
		}

		// Save updated queue.
		update_option( self::OPTION_KEY, $queue );

		if ( $processed > 0 ) {
			$this->logger->log(
				sprintf(
					'Retry queue processed: %d items (success: %d, failed: %d, pending: %d)',
					$processed,
					$succeeded,
					$failed,
					count( $queue )
				),
				'info'
			);
		}
	}

	/**
	 * Attempt to retry the failed operation
	 *
	 * @param array $item Queue item with operation details.
	 * @return bool True if retry succeeded, false otherwise.
	 */
	private function retry_operation( $item ) {
		$db = new GG_Data_DB();

		try {
			switch ( $item['operation_type'] ) {
				case 'sync_post':
					// Get fresh post data from WordPress.
					$post = get_post( $item['entity_id'] );
					if ( ! $post ) {
						// Post no longer exists - consider it successful.
						return true;
					}
					return $db->upsert_post( $post );

				case 'sync_meta':
					// Retry metadata sync.
					if ( isset( $item['operation_data']['meta_id'] ) ) {
						return $db->upsert_post_meta(
							$item['operation_data']['meta_id'],
							$item['entity_id'],
							$item['operation_data']['meta_key'],
							$item['operation_data']['meta_value'],
							1, // blog_id.
							null // connection_name - will use default connection logic.
						);
					}
					return false;

				case 'delete_post':
					// Retry post deletion.
					if ( isset( $item['operation_data']['site_id'] ) ) {
						return $db->delete_post( $item['entity_id'], $item['operation_data']['site_id'], $item['connection_name'] );
					}
					return false;

				case 'delete_meta':
					// Retry meta deletion.
					if ( isset( $item['operation_data']['meta_key'], $item['operation_data']['site_id'] ) ) {
						return $db->delete_post_meta(
							$item['entity_id'],
							$item['operation_data']['meta_key'],
							$item['operation_data']['site_id'],
							null // connection_name - will use default connection logic.
						);
					}
					return false;

				default:
					$this->logger->log(
						sprintf( 'Unknown operation type: %s', $item['operation_type'] ),
						'error'
					);
					return false;
			}
		} catch ( Exception $e ) {
			// Update last error.
			$item['last_error'] = $e->getMessage();
			return false;
		}
	}

	/**
	 * Move permanently failed item to dead letter queue
	 *
	 * @param array $item Queue item that failed all retries.
	 */
	private function move_to_dead_letter( $item ) {
		$dead_letter = get_option( self::DEAD_LETTER_KEY, array() );

		$item['moved_to_dead_letter'] = current_time( 'mysql' );
		$dead_letter[]                = $item;

		// Keep only last 100 dead letter items to prevent bloat.
		if ( count( $dead_letter ) > 100 ) {
			$dead_letter = array_slice( $dead_letter, -100 );
		}

		update_option( self::DEAD_LETTER_KEY, $dead_letter );
	}

	/**
	 * Get current retry queue status for dashboard
	 *
	 * @return array {
	 *     Queue status information.
	 *
	 *     @type int   $pending_retries       Number of items pending retry.
	 *     @type int   $failed_permanently    Number of items in dead letter queue.
	 *     @type array $items                 Queue items (first 10).
	 *     @type array $dead_letter_items     Dead letter items (last 10).
	 * }
	 */
	public function get_queue_status() {
		$queue       = get_option( self::OPTION_KEY, array() );
		$dead_letter = get_option( self::DEAD_LETTER_KEY, array() );

		return array(
			'pending_retries'    => count( $queue ),
			'failed_permanently' => count( $dead_letter ),
			'items'              => array_slice( array_values( $queue ), 0, 10 ),
			'dead_letter_items'  => array_slice( $dead_letter, -10 ),
		);
	}

	/**
	 * Manually retry a dead letter item
	 *
	 * @param int $item_index Index of item in dead letter queue.
	 * @return bool True if item moved back to retry queue, false otherwise.
	 */
	public function manual_retry( $item_index ) {
		$dead_letter = get_option( self::DEAD_LETTER_KEY, array() );

		if ( ! isset( $dead_letter[ $item_index ] ) ) {
			return false;
		}

		$item = $dead_letter[ $item_index ];

		// Reset attempt count and move back to retry queue.
		$item['attempt_count'] = 1;
		$item['next_retry']    = wp_date( 'Y-m-d H:i:s', time() );
		unset( $item['moved_to_dead_letter'] );

		$queue               = get_option( self::OPTION_KEY, array() );
		$queue_key           = $item['operation_type'] . '_' . $item['entity_id'];
		$queue[ $queue_key ] = $item;

		update_option( self::OPTION_KEY, $queue );

		// Remove from dead letter.
		unset( $dead_letter[ $item_index ] );
		update_option( self::DEAD_LETTER_KEY, array_values( $dead_letter ) );

		$this->logger->log(
			sprintf(
				'Manually retrying %s #%d from dead letter queue',
				$item['operation_type'],
				$item['entity_id']
			),
			'info'
		);

		return true;
	}

	/**
	 * Clear dead letter queue
	 *
	 * @return int Number of items cleared.
	 */
	public function clear_dead_letter_queue() {
		$dead_letter = get_option( self::DEAD_LETTER_KEY, array() );
		$count       = count( $dead_letter );

		delete_option( self::DEAD_LETTER_KEY );

		$this->logger->log(
			sprintf( 'Dead letter queue cleared - removed %d items', $count ),
			'info'
		);

		return $count;
	}

	/**
	 * Cleanup on deactivation
	 *
	 * Unschedule WP-Cron events.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'gg_data_process_retry_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'gg_data_process_retry_queue' );
		}
	}
}
