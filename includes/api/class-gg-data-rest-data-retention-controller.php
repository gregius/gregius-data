<?php
/**
 * REST API Controller for the data-retention preference.
 *
 * Records the user's "keep or remove data on uninstall" choice, surfaced
 * through the deactivation modal. Deletion only ever happens at uninstall —
 * deactivation never removes data.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Data-retention REST controller.
 */
class GG_Data_REST_Data_Retention_Controller extends WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = 'gg-data/v1';

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'data-retention';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'remove_data' => array(
						'description'       => 'Whether to remove all plugin data when the plugin is deleted.',
						'type'              => 'boolean',
						'required'          => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	/**
	 * Permission check for updating the retention preference.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_options' );
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Store the retention preference.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		$remove = (bool) $request->get_param( 'remove_data' );

		GG_Data_Uninstaller::set_retention_flag( $remove );

		return rest_ensure_response( array( 'remove_data' => $remove ) );
	}
}
