<?php
/**
 * REST API Controller for PostgreSQL Connection Management
 *
 * Prevents direct access to the file.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for connection management
 *
 * @since 1.0.0
 */
class GG_Data_REST_Connections_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'connections';

	/**
	 * Connection manager instance
	 *
	 * @var GG_Data_Connection_Manager
	 */
	private $connection_manager;

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize managers lazily.
		$this->connection_manager = null;
		$this->settings_manager   = null;
		$this->logger             = new GG_Data_Logger();
	}

	/**
	 * Get connection manager instance (lazy initialization)
	 *
	 * @return GG_Data_Connection_Manager
	 */
	private function get_connection_manager() {
		if ( null === $this->connection_manager ) {
			$this->connection_manager = new GG_Data_Connection_Manager();
		}
		return $this->connection_manager;
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
	 * Register the routes for the connection endpoints
	 */
	public function register_routes() {

		// GET /gg-data/v1/connections - Get all connections.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_connections' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_connection' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_connection_creation_args(),
				),
			)
		);

		// GET|PUT|DELETE /gg-data/v1/connections/{name} - Single connection operations.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<name>(?!all-health$)(?!stats$)[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_connection' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_connection' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_connection_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_connection' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// POST /gg-data/v1/connections/{name}/test - Test connection.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<name>(?!all-health$)(?!stats$)[a-zA-Z0-9_-]+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// GET /gg-data/v1/connections/all-health - Get all connection health.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/all-health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_all_connection_health' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'force' => array(
						'description' => 'Force fresh health checks',
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			)
		);

		// GET /gg-data/v1/connections/{name}/health - Get connection health.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<name>(?!all-health$)(?!stats$)[a-zA-Z0-9_-]+)/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_connection_health' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'force' => array(
						'description' => 'Force a fresh health check',
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			)
		);

		// GET /gg-data/v1/connections/stats - Get connection statistics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_connection_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Get all connections
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_connections( $request ) {
		try {
			$connections = $this->get_settings_manager()->get_all_connections();

			// Remove sensitive data and unserialize/cast values for API response.
			foreach ( $connections as $name => &$config ) {
				$this->mask_sensitive_connection_fields( $config );
				// Unserialize/cast port.
				if ( isset( $config['port'] ) ) {
					$config['port'] = self::parse_serialized_int( $config['port'] );
				}
				// Unserialize/cast connect_timeout.
				if ( isset( $config['connect_timeout'] ) ) {
					$config['connect_timeout'] = self::parse_serialized_int( $config['connect_timeout'] );
				}
				// Unserialize/cast is_active.
				if ( isset( $config['is_active'] ) ) {
					$config['is_active'] = self::parse_serialized_bool( $config['is_active'] );
				}
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $connections,
					'count'   => count( $connections ),
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'get_connections_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Create a new connection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
	 */
	public function create_connection( $request ) {
		// Verify nonce first.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}

		$name   = $request->get_param( 'name' );
		$config = $request->get_param( 'config' );

		// Phase R.2.3: Add database type to connection config (default to PostgreSQL).
		if ( ! isset( $config['type'] ) ) {
			$config['type'] = 'postgresql';
		}

		// Check if connection already exists.
		if ( $this->get_settings_manager()->connection_exists( $name ) ) {
			return new WP_Error(
				'connection_exists',
				/* translators: %s: connection name */
				sprintf( __( 'Connection "%s" already exists.', 'gregius-data' ), $name ),
				array( 'status' => 409 )
			);
		}

		try {
			// For Supabase: Just save connection, schema creation is separate step.
			// Access token is no longer used during connection creation.
			// Schema will be created manually via SQL Editor.

			// Save the new connection.
			$success = $this->get_settings_manager()->save_connection( $name, $config );

			if ( ! $success ) {
				$this->logger->log(
					sprintf( 'Connection Manager: Failed to create connection "%s"', $name ),
					'error',
					'connection',
					$name,
					array(
						'type'    => $config['type'],
						'user_id' => get_current_user_id(),
					)
				);
				return new WP_Error( 'create_failed', 'Failed to create connection', array( 'status' => 500 ) );
			}

			// Log successful creation.
			$this->logger->log(
				sprintf( 'Connection Manager: Created connection "%s"', $name ),
				'info',
				'connection',
				$name,
				array(
					'type'    => $config['type'],
					'user_id' => get_current_user_id(),
				)
			);

			// Prepare response.
			$response_data = array(
				'success' => true,
				/* translators: %s: connection name */
				'message' => sprintf( __( 'Connection "%s" created successfully', 'gregius-data' ), $name ),
				'data'    => array(
					'name' => $name,
					'type' => $config['type'],
				),
			);

			// For Supabase, add schema_status to indicate next step.
			if ( 'postgrest' === $config['type'] ) {
				$response_data['data']['schema_status'] = 'not_created';
				$response_data['message']              .= '. Next: Create schema via SQL Editor.';
			}

			return new WP_REST_Response( $response_data, 201 );
		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Connection Manager: Exception creating connection "%s": %s', $name, $e->getMessage() ),
				'error',
				'connection',
				$name,
				array(
					'exception' => $e->getMessage(),
					'user_id'   => get_current_user_id(),
				)
			);
			return new WP_Error( 'create_connection_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get a single connection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
	 */
	public function get_connection( $request ) {
		$name = $request->get_param( 'name' );
		try {
			$connection = $this->get_settings_manager()->get_connection( $name );
			if ( ! $connection ) {
				return new WP_Error( 'connection_not_found', 'Connection not found', array( 'status' => 404 ) );
			}

			// Phase R.2.3: Ensure database type is set (default to PostgreSQL for existing connections).
			if ( ! isset( $connection['type'] ) ) {
				$connection['type'] = 'postgresql';
			}

			// Remove sensitive data.
			$this->mask_sensitive_connection_fields( $connection );
			// Unserialize/cast port.
			if ( isset( $connection['port'] ) ) {
				$connection['port'] = self::parse_serialized_int( $connection['port'] );
			}
			// Unserialize/cast connect_timeout.
			if ( isset( $connection['connect_timeout'] ) ) {
				$connection['connect_timeout'] = self::parse_serialized_int( $connection['connect_timeout'] );
			}
			// Unserialize/cast is_active.
			if ( isset( $connection['is_active'] ) ) {
				$connection['is_active'] = self::parse_serialized_bool( $connection['is_active'] );
			}
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $connection,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'get_connection_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Parse a serialized PHP integer (e.g., "i:5432;") or return a casted int
	 *
	 * @param mixed $value The value to parse.
	 * @return int
	 */
	private static function parse_serialized_int( $value ) {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return intval( $value );
		}
		if ( is_string( $value ) && preg_match( '/^i:(\d+);$/', $value, $matches ) ) {
			return intval( $matches[1] );
		}
		return 0;
	}

	/**
	 * Parse a serialized PHP boolean (e.g., "b:1;") or return a casted bool
	 *
	 * @param  mixed $value The value to parse.
	 * @return bool
	 */
	private static function parse_serialized_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( 1 === $value || '1' === $value ) {
			return true;
		}
		if ( 0 === $value || '0' === $value ) {
			return false;
		}
		if ( is_string( $value ) && preg_match( '/^b:(0|1);$/', $value, $matches ) ) {
			return '1' === $matches[1];
		}
		return false;
	}

	/**
	 * Update an existing connection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_connection( $request ) {
		// Verify nonce first.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}

		$name   = $request->get_param( 'name' );
		$config = $request->get_param( 'config' );

		if ( ! $this->get_settings_manager()->connection_exists( $name ) ) {
			return new WP_Error( 'connection_not_found', 'Connection not found', array( 'status' => 404 ) );
		}

		// Filter out masked sensitive values to preserve existing keys.
		// When UI sends '***' it means "keep existing value, don't update".
		$sensitive_keys = array( 'password', 'publishable_key', 'secret_key' );
		foreach ( $sensitive_keys as $key ) {
			if ( isset( $config[ $key ] ) && '***' === $config[ $key ] ) {
				unset( $config[ $key ] );
			}
		}

		try {
			// Get existing config.
			$existing_config = $this->get_settings_manager()->get_connection( $name );
			$existing_type   = isset( $existing_config['type'] ) ? $existing_config['type'] : 'postgresql';
			$new_type        = isset( $config['type'] ) ? $config['type'] : $existing_type;

			// If provider type changed, start fresh with only common fields.
			if ( $existing_type !== $new_type ) {
				// Keep only common fields.
				$updated_config = array(
					'type'            => $new_type,
					'description'     => isset( $config['description'] ) ? $config['description'] : '',
					'connect_timeout' => isset( $config['connect_timeout'] ) ? $config['connect_timeout'] : 30,
					'is_active'       => isset( $config['is_active'] ) ? $config['is_active'] : true,
				);
				// Add provider-specific fields from new config.
				$updated_config = array_merge( $updated_config, $config );
			} else {
				// Same provider type - merge with existing.
				$updated_config = array_merge( $existing_config, $config );
			}

			$success = $this->get_settings_manager()->save_connection( $name, $updated_config );

			if ( ! $success ) {
				$this->logger->log(
					sprintf( 'Connection Manager: Failed to update connection "%s"', $name ),
					'error',
					'connection',
					$name,
					array(
						'type'    => $new_type,
						'user_id' => get_current_user_id(),
					)
				);
				return new WP_Error( 'update_failed', 'Failed to update connection', array( 'status' => 500 ) );
			}

			// Log successful update.
			$type_changed = $existing_type !== $new_type;
			$this->logger->log(
				sprintf( 'Connection Manager: Updated connection "%s"', $name ),
				'info',
				'connection',
				$name,
				array(
					'type'         => $new_type,
					'type_changed' => $type_changed,
					'user_id'      => get_current_user_id(),
				)
			);

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Connection updated successfully',
					'data'    => array( 'name' => $name ),
				),
				200
			);

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Connection Manager: Exception updating connection "%s": %s', $name, $e->getMessage() ),
				'error',
				'connection',
				$name,
				array(
					'exception' => $e->getMessage(),
					'user_id'   => get_current_user_id(),
				)
			);
			return new WP_Error( 'update_connection_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Delete a connection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_connection( $request ) {
		// Verify nonce first.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}

		$name = $request->get_param( 'name' );

		if ( ! $this->get_settings_manager()->connection_exists( $name ) ) {
			return new WP_Error( 'connection_not_found', 'Connection not found', array( 'status' => 404 ) );
		}

		try {
			// Get connection type before deletion for logging.
			$existing_config = $this->get_settings_manager()->get_connection( $name );
			$connection_type = isset( $existing_config['type'] ) ? $existing_config['type'] : 'unknown';

			$success = $this->get_settings_manager()->delete_connection( $name );

			if ( ! $success ) {
				$this->logger->log(
					sprintf( 'Connection Manager: Failed to delete connection "%s"', $name ),
					'error',
					'connection',
					$name,
					array(
						'type'    => $connection_type,
						'user_id' => get_current_user_id(),
					)
				);
				return new WP_Error( 'delete_failed', 'Failed to delete connection', array( 'status' => 500 ) );
			}

			// Log successful deletion.
			$this->logger->log(
				sprintf( 'Connection Manager: Deleted connection "%s"', $name ),
				'info',
				'connection',
				$name,
				array(
					'type'    => $connection_type,
					'user_id' => get_current_user_id(),
				)
			);

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Connection deleted successfully',
				),
				200
			);

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Connection Manager: Exception deleting connection "%s": %s', $name, $e->getMessage() ),
				'error',
				'connection',
				$name,
				array(
					'exception' => $e->getMessage(),
					'user_id'   => get_current_user_id(),
				)
			);
			return new WP_Error( 'delete_connection_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Test a connection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function test_connection( $request ) {
		// Verify nonce first.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}

		$name = $request->get_param( 'name' );

		try {
			$test_result = $this->get_connection_manager()->test_connection( $name );

			// Return the test result directly (which already has success/message/details).
			// Don't wrap it in another success:true wrapper.
			return new WP_REST_Response( $test_result, 200 );

		} catch ( Exception $e ) {
			return new WP_Error( 'test_connection_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get connection health
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_connection_health( $request ) {
		$name  = $request->get_param( 'name' );
		$force = $request->get_param( 'force' );

		try {
			$health = $this->get_connection_manager()->get_connection_health( $name, $force );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $health,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'get_health_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get all connection health
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_all_connection_health( $request ) {
		$force = $request->get_param( 'force' );

		try {
			$health = $this->get_connection_manager()->get_all_connection_health( $force );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $health,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'get_all_health_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get connection statistics
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_connection_stats( $request ) {
		try {
			$stats = $this->get_connection_manager()->get_connection_stats();

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $stats,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'get_stats_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Check if user has admin permission with detailed error handling
	 *
	 * @return bool|WP_Error True if user can manage options, WP_Error otherwise
	 */
	public function check_admin_permission() {
		$user_id = get_current_user_id();

		// User not logged in.
		if ( ! $user_id ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'gregius-data' ),
				array( 'status' => 401 )
			);
		}

		// User lacks required capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			$user  = wp_get_current_user();
			$roles = $user->roles ? implode( ', ', $user->roles ) : 'none';

			return new WP_Error(
				'rest_forbidden',
				__( 'Insufficient permissions. Administrator access required.', 'gregius-data' ),
				array(
					'status'       => 403,
					'user_id'      => $user_id,
					'roles'        => $roles,
					'required_cap' => 'manage_options',
				)
			);
		}

		return true;
	}

	/**
	 * Get connection creation arguments
	 *
	 * @return array Arguments for connection creation
	 */
	public function get_connection_creation_args() {
		return array(
			'name'         => array(
				'description'       => 'Connection name',
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_connection_name' ),
			),
			'config'       => array(
				'description' => 'Connection configuration',
				'type'        => 'object',
				'required'    => true,
				'properties'  => array(
					'host'            => array( 'type' => 'string' ),
					'port'            => array( 'type' => 'integer' ),
					'database'        => array( 'type' => 'string' ),
					'username'        => array( 'type' => 'string' ),
					'password'        => array( 'type' => 'string' ),
					'publishable_key' => array( 'type' => 'string' ),
					'secret_key'      => array( 'type' => 'string' ),
					'ssl_mode'        => array( 'type' => 'string' ),
					'connect_timeout' => array( 'type' => 'integer' ),
					'description'     => array( 'type' => 'string' ),
					'is_active'       => array( 'type' => 'boolean' ),
				),
			),
			'access_token' => array(
				'description'       => 'Supabase Management API access token (one-time use, not stored)',
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get connection update arguments
	 *
	 * @return array Arguments for connection updates
	 */
	public function get_connection_update_args() {
		$args                     = $this->get_connection_creation_args();
		$args['name']['required'] = false;
		return $args;
	}

	/**
	 * Validate connection name
	 *
	 * @param string $name Connection name to validate.
	 * @return bool True if valid. False otherwise.
	 */
	public function validate_connection_name( $name ) {
		if ( empty( $name ) ) {
			return false;
		}

		// Allow alphanumeric characters, hyphens, and underscores.
		return preg_match( '/^[a-zA-Z0-9_-]+$/', $name );
	}

	/**
	 * Mask sensitive connection fields in-place for API responses.
	 *
	 * @param array $config Connection configuration.
	 * @return void
	 */
	private function mask_sensitive_connection_fields( &$config ) {
		$keys = array( 'password', 'publishable_key', 'secret_key' );

		foreach ( $keys as $key ) {
			if ( isset( $config[ $key ] ) && ! empty( $config[ $key ] ) ) {
				$config[ $key ] = '***';
			}
		}
	}
}
