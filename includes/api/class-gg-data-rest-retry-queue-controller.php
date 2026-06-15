<?php
/**
 * REST API: Retry Queue Controller
 *
 * Handles retry queue status, manual retry, and dead letter queue management.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * REST API controller for retry queue operations
 *
 * @since 1.0.0
 */
class GG_Data_REST_Retry_Queue_Controller extends WP_REST_Controller {

	/**
	 * Retry queue manager instance
	 *
	 * @var GG_Data_Retry_Queue
	 */
	private $retry_queue;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace   = 'gg-data/v1';
		$this->rest_base   = 'sync/retry-queue';
		$this->retry_queue = new GG_Data_Retry_Queue();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /gg-data/v1/sync/retry-queue - Get queue status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_queue_status' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// POST /gg-data/v1/sync/retry-queue/retry/{index} - Manual retry from dead letter queue.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/retry/(?P<index>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'manual_retry' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'index' => array(
							'description' => __( 'Index of the dead letter queue item to retry', 'gregius-data' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/sync/retry-queue/clear - Clear dead letter queue.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clear',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_dead_letter' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get retry queue status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_queue_status( $request ) {
		try {
			$status = $this->retry_queue->get_queue_status();

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $status,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'retry_queue_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Manual retry of dead letter item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function manual_retry( $request ) {
		$index = $request->get_param( 'index' );

		try {
			$result = $this->retry_queue->manual_retry( $index );

			if ( $result ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Item moved back to retry queue', 'gregius-data' ),
					),
					200
				);
			} else {
				return new WP_Error(
					'retry_failed',
					__( 'Failed to move item to retry queue', 'gregius-data' ),
					array( 'status' => 400 )
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'retry_queue_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Clear dead letter queue
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clear_dead_letter( $request ) {
		try {
			$count = $this->retry_queue->clear_dead_letter_queue();

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => sprintf(
						/* translators: %d: number of items cleared */
						__( 'Cleared %d items from dead letter queue', 'gregius-data' ),
						$count
					),
					'count'   => $count,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'retry_queue_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to delete a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
