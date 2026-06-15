<?php
/**
 * REST API: Connection Health Controller
 *
 * Provides endpoints for PostgreSQL connection health monitoring.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * REST API controller for connection health operations
 *
 * @since 1.0.0
 */
class GG_Data_REST_Connection_Health_Controller extends WP_REST_Controller {

	/**
	 * Connection health monitor instance
	 *
	 * @var GG_Data_Connection_Health
	 */
	private $health_monitor;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace      = 'gg-data/v1';
		$this->rest_base      = 'sync/connection-health';
		$this->health_monitor = new GG_Data_Connection_Health();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /gg-data/v1/sync/connection-health - Get health status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health_status' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// POST /gg-data/v1/sync/connection-health/check - Manual health check.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'manual_health_check' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		// POST /gg-data/v1/sync/connection-health/reset - Reset health status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_health_status' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get connection health status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_health_status( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$status          = $this->health_monitor->get_health_status( $connection_name );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $status,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'health_check_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Perform manual health check
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function manual_health_check( $request ) {
		try {
			$this->health_monitor->perform_health_check();
			$status = $this->health_monitor->get_health_status();

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Health check completed', 'gregius-data' ),
					'data'    => $status,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'health_check_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Reset health status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function reset_health_status( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$this->health_monitor->reset_health_status( $connection_name );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Health status reset successfully', 'gregius-data' ),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'health_check_error',
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
