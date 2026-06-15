<?php
/**
 * REST API Controller for Gregius PostgreSQL Plugin Settings
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * REST API Controller for Gregius PostgreSQL Plugin Settings
 *
 * Provides REST API endpoints for the settings manager to enable
 * React dashboard integration.
 */
class GG_Data_REST_Settings_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'settings';

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize settings manager lazily.
		$this->settings_manager = null;
	}

	/**
	 * Get settings manager instance (lazy initialization)
	 *
	 * @return GG_Data_Settings_Manager
	 */
	private function get_settings_manager() {
		if ( null === $this->settings_manager ) {
			$this->settings_manager = new GG_Data_Settings_Manager();
		}
		return $this->settings_manager;
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// Get all settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		);

		// IMPORTANT: Register specific routes BEFORE parameterized routes
		// Create settings tables for all sites endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/create-tables',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_all_tables' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'description' => 'Force recreation of existing tables',
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			)
		);

		// Get specific setting - must come AFTER specific routes.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'key' => array(
						'description'       => 'The setting key',
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_setting_key' ),
					),
				),
			)
		);

		// Create/Update setting.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			)
		);

		// Update setting.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			)
		);

		// Delete setting.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'key' => array(
						'description'       => 'The setting key',
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_setting_key' ),
					),
				),
			)
		);

		// Bulk operations endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_update' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'settings' => array(
						'description' => 'Array of settings to update',
						'type'        => 'array',
						'required'    => true,
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array( 'type' => 'string' ),
								'value' => array( 'type' => 'mixed' ),
							),
						),
					),
				),
			)
		);

		// Database-specific settings endpoints.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/database/(?P<db_key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_database_settings' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'db_key' => array(
						'description'       => 'The database identifier key',
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_setting_key' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/database/(?P<db_key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_database_settings' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'db_key'   => array(
						'description'       => 'The database identifier key',
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_setting_key' ),
					),
					'settings' => array(
						'description' => 'Database connection settings',
						'type'        => 'object',
						'required'    => true,
					),
				),
			)
		);

		// Category-based settings endpoints (e.g., /settings/search/enabled?connection=default).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<category>[a-z0-9_]+)/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_category_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'category'   => array(
						'description' => 'The setting category',
						'type'        => 'string',
						'required'    => true,
					),
					'key'        => array(
						'description' => 'The setting key',
						'type'        => 'string',
						'required'    => true,
					),
					'connection' => array(
						'description' => 'The connection name',
						'type'        => 'string',
						'default'     => 'default',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<category>[a-z0-9_]+)/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_category_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'category'   => array(
						'description' => 'The setting category',
						'type'        => 'string',
						'required'    => true,
					),
					'key'        => array(
						'description' => 'The setting key',
						'type'        => 'string',
						'required'    => true,
					),
					'connection' => array(
						'description' => 'The connection name',
						'type'        => 'string',
						'default'     => 'default',
					),
					'value'      => array(
						'description' => 'The setting value',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Get all settings
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$prefix = $request->get_param( 'prefix' );

		try {
			if ( $prefix ) {
				$settings = $this->get_settings_manager()->get_by_prefix( $prefix );
			} else {
				$settings = $this->get_settings_manager()->get_all();
			}

			$response_data = array(
				'settings' => $settings,
				'total'    => count( $settings ),
			);

			return new WP_REST_Response( $response_data, 200 );
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to retrieve settings: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get one setting
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$key     = $request->get_param( 'key' );
		$default = $request->get_param( 'default' );

		try {
			$value = $this->get_settings_manager()->get( $key, $default );

			$response_data = array(
				'key'    => $key,
				'value'  => $value,
				'exists' => $this->get_settings_manager()->exists( $key ),
			);

			return new WP_REST_Response( $response_data, 200 );
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to retrieve setting: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Create one setting
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$key   = $request->get_param( 'key' );
		$value = $request->get_param( 'value' );

		if ( $this->get_settings_manager()->exists( $key ) ) {
			return new WP_Error(
				'gg_data_settings_exists',
				'Setting already exists. Use PUT to update.',
				array( 'status' => 409 )
			);
		}

		try {
			$success = $this->get_settings_manager()->set( $key, $value );

			if ( $success ) {
				$response_data = array(
					'key'     => $key,
					'value'   => $value,
					'created' => true,
				);
				return new WP_REST_Response( $response_data, 201 );
			} else {
				return new WP_Error(
					'gg_data_settings_create_failed',
					'Failed to create setting',
					array( 'status' => 500 )
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to create setting: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Update one setting
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$key   = $request->get_param( 'key' );
		$value = $request->get_param( 'value' );

		try {
			$success = $this->get_settings_manager()->set( $key, $value );

			if ( $success ) {
				$response_data = array(
					'key'     => $key,
					'value'   => $value,
					'updated' => true,
				);
				return new WP_REST_Response( $response_data, 200 );
			} else {
				return new WP_Error(
					'gg_data_settings_update_failed',
					'Failed to update setting',
					array( 'status' => 500 )
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to update setting: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete one setting
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$key = $request->get_param( 'key' );

		if ( ! $this->get_settings_manager()->exists( $key ) ) {
			return new WP_Error(
				'gg_data_settings_not_found',
				'Setting not found',
				array( 'status' => 404 )
			);
		}

		try {
			$success = $this->get_settings_manager()->delete( $key );

			if ( $success ) {
				$response_data = array(
					'key'     => $key,
					'deleted' => true,
				);
				return new WP_REST_Response( $response_data, 200 );
			} else {
				return new WP_Error(
					'gg_data_settings_delete_failed',
					'Failed to delete setting',
					array( 'status' => 500 )
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to delete setting: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Bulk update settings
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function bulk_update( $request ) {
		$settings = $request->get_param( 'settings' );

		$results = array();
		$errors  = array();

		foreach ( $settings as $setting ) {
			if ( ! isset( $setting['key'] ) || ! isset( $setting['value'] ) ) {
				$errors[] = 'Missing key or value in setting data';
				continue;
			}

			try {
				$success   = $this->get_settings_manager()->set( $setting['key'], $setting['value'] );
				$results[] = array(
					'key'     => $setting['key'],
					'success' => $success,
				);
			} catch ( Exception $e ) {
				$errors[] = 'Failed to update ' . $setting['key'] . ': ' . $e->getMessage();
			}
		}

		$response_data = array(
			'results' => $results,
			'errors'  => $errors,
			'total'   => count( $settings ),
		);

		$status = empty( $errors ) ? 200 : 207; // 207 Multi-Status for partial success
		return new WP_REST_Response( $response_data, $status );
	}

	/**
	 * Get database-specific settings
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_database_settings( $request ) {
		$db_key = $request->get_param( 'db_key' );

		try {
			$settings = $this->get_settings_manager()->get_database_settings( $db_key );

			$response_data = array(
				'db_key'   => $db_key,
				'settings' => $settings,
				'exists'   => ! is_null( $settings ),
			);

			return new WP_REST_Response( $response_data, 200 );
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to retrieve database settings: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Create database-specific settings
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_database_settings( $request ) {
		$db_key   = $request->get_param( 'db_key' );
		$settings = $request->get_param( 'settings' );

		try {
			$success = $this->get_settings_manager()->set_database_settings( $db_key, $settings );

			if ( $success ) {
				$response_data = array(
					'db_key'   => $db_key,
					'settings' => $settings,
					'created'  => true,
				);
				return new WP_REST_Response( $response_data, 201 );
			} else {
				return new WP_Error(
					'gg_data_settings_create_failed',
					'Failed to create database settings',
					array( 'status' => 500 )
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to create database settings: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get a category-based setting
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_category_item( $request ) {
		$category   = $request->get_param( 'category' );
		$key        = $request->get_param( 'key' );
		$connection = $request->get_param( 'connection' );

		if ( 'search' === $category && defined( 'GG_DATA_SEARCH_SETTINGS_CONNECTION' ) ) {
			$connection = GG_DATA_SEARCH_SETTINGS_CONNECTION;
		}

		try {
			// Use get_with_category method (category, connection, key, default).
			$value = $this->get_settings_manager()->get_with_category( $category, $connection, $key, null );

			$response_data = array(
				'category'   => $category,
				'key'        => $key,
				'connection' => $connection,
				'value'      => $value,
				'exists'     => ! is_null( $value ),
			);

			return new WP_REST_Response( $response_data, 200 );
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to retrieve setting: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Update a category-based setting
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_category_item( $request ) {
		$category   = $request->get_param( 'category' );
		$key        = $request->get_param( 'key' );
		$connection = $request->get_param( 'connection' );
		$value      = $request->get_param( 'value' );

		if ( 'search' === $category && defined( 'GG_DATA_SEARCH_SETTINGS_CONNECTION' ) ) {
			$connection = GG_DATA_SEARCH_SETTINGS_CONNECTION;
		}

		try {
			// Use set_with_category_public method (category, connection, key, value).
			$success = $this->get_settings_manager()->set_with_category_public( $category, $connection, $key, $value );

			if ( $success ) {
				$response_data = array(
					'category'   => $category,
					'key'        => $key,
					'connection' => $connection,
					'value'      => $value,
					'updated'    => true,
				);
				return new WP_REST_Response( $response_data, 200 );
			} else {
				return new WP_Error(
					'gg_data_settings_update_failed',
					'Failed to update setting',
					array( 'status' => 500 )
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_settings_error',
				'Failed to update setting: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Create settings tables for all sites in the network
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_all_tables( $request ) {
		$force = $request->get_param( 'force' );

		try {
			$results = $this->get_settings_manager()->create_settings_tables_for_all_sites();

			$success_count  = 0;
			$error_count    = 0;
			$tables_created = array();

			foreach ( $results as $site_key => $result ) {
				if ( $result['success'] ) {
					++$success_count;
					$tables_created[] = $result['table_name'];
				} else {
					++$error_count;
				}
			}

			$response_data = array(
				'success'        => 0 === $error_count,
				'message'        => sprintf(
					'Created %d settings tables successfully. %d errors.',
					$success_count,
					$error_count
				),
				'tables_created' => $tables_created,
				'results'        => $results,
				'success_count'  => $success_count,
				'error_count'    => $error_count,
				'total_sites'    => count( $results ),
			);

			$status = 0 === $error_count ? 201 : 207; // 201 Created or 207 Multi-Status
			return new WP_REST_Response( $response_data, $status );
		} catch ( Exception $e ) {
			return new WP_Error(
				'gg_data_table_creation_failed',
				'Failed to create settings tables: ' . $e->getMessage(),
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
	 * Check if a given request has access to get a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
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

	/**
	 * Validate setting key
	 *
	 * @param string $value The setting key.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_setting_key( $value ) {
		// Allow alphanumeric characters, underscores, hyphens, and dots.
		return preg_match( '/^[a-zA-Z0-9_.-]+$/', $value );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'prefix' => array(
				'description'       => 'Filter settings by key prefix',
				'type'              => 'string',
				'validate_callback' => array( $this, 'validate_setting_key' ),
			),
		);
	}

	/**
	 * Retrieves the setting schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'setting',
			'type'       => 'object',
			'properties' => array(
				'key'   => array(
					'description' => 'The setting key',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'value' => array(
					'description' => 'The setting value',
					'type'        => 'mixed',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}
}
