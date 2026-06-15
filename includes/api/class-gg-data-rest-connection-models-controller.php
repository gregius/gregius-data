<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * REST API: Connection-Models Controller
 *
 * Manages which embedding models are active per PostgreSQL connection.
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/includes/api
 * @since      1.0.0
 */

/**
 * Connection-Models REST Controller
 *
 * Endpoints:
 * - GET    /gg-data/v1/connections/{connection}/vectors/models
 * - POST   /gg-data/v1/connections/{connection}/vectors/models
 * - DELETE /gg-data/v1/connections/{connection}/vectors/models/{modelKey}
 *
 * @since 1.0.0
 */
class GG_Data_REST_Connection_Models_Controller extends WP_REST_Controller {

	/**
	 * Connection-Model Manager instance
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    GG_Data_Connection_Model_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'connections/(?P<connection>[a-zA-Z0-9_-]+)/vectors/models';
		$this->manager   = new GG_Data_Connection_Model_Manager();
	}

	/**
	 * Register the routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// GET /gg-data/v1/connections/{connection}/vectors/models - Get all models for connection.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Connection name', 'gregius-data' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Connection name', 'gregius-data' ),
						),
						'model_key'  => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Model key from global registry', 'gregius-data' ),
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/connections/{connection}/vectors/models/{modelKey} - Remove model from connection.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<modelKey>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'connection' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'Connection name', 'gregius-data' ),
					),
					'modelKey'   => array(
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'Model key to remove', 'gregius-data' ),
					),
				),
			)
		);
	}

	/**
	 * Get all active models for a connection
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_items( $request ) {
		$connection_name = $request->get_param( 'connection' );
		$models          = $this->manager->get_connection_models( $connection_name );

		// Mask API keys for security.
		foreach ( $models as &$model ) {
			if ( isset( $model['config']['api_key'] ) ) {
				$model['config']['api_key'] = '***';
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $models,
				'count'   => count( $models ),
			),
			200
		);
	}

	/**
	 * Add existing model to connection
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function create_item( $request ) {
		$connection_name = $request->get_param( 'connection' );
		$model_key       = $request->get_param( 'model_key' );

		if ( empty( $model_key ) ) {
			return new WP_Error(
				'missing_model_key',
				__( 'model_key is required', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->manager->add_model_to_connection( $connection_name, $model_key );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Model added to connection', 'gregius-data' ),
			),
			201
		);
	}

	/**
	 * Remove model from connection
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function delete_item( $request ) {
		$connection_name = $request->get_param( 'connection' );
		$model_key       = $request->get_param( 'modelKey' );

		$result = $this->manager->remove_model_from_connection( $connection_name, $model_key );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Model removed from connection', 'gregius-data' ),
			),
			200
		);
	}

	/**
	 * Check permissions for getting items
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check permissions for creating item
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check permissions for deleting item
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
