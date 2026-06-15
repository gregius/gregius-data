<?php
/**
 * REST API Controller for Schema Management
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * REST API Controller for Schema Management
 *
 * Handles REST API endpoints for PostgreSQL schema operations
 */
class GG_Data_REST_Schema_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'schema';

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
	 * Register the routes for the schema endpoints
	 */
	public function register_routes() {

		// GET /gg-data/v1/schema/status - Get schema status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'connection' => array(
						'description' => 'Connection name to check status for',
						'type'        => 'string',
						'default'     => 'default',
						'required'    => false,
					),
				),
			)
		);

		// POST /gg-data/v1/schema/create - Create schema.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/create',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_schema' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'connection' => array(
						'description' => 'Connection name to create schema for',
						'type'        => 'string',
						'default'     => 'default',
						'required'    => false,
					),
				),
			)
		);

		// POST /gg-data/v1/schema/upgrade - Upgrade schema.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upgrade',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upgrade_schema' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'connection' => array(
						'description' => 'Connection name to upgrade schema for',
						'type'        => 'string',
						'default'     => 'default',
						'required'    => false,
					),
				),
			)
		);

		// POST /gg-data/v1/schema/verify - Verify schema exists (Supabase).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify_schema' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'connection' => array(
						'description' => 'Connection name to verify schema for',
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);

		// GET /gg-data/v1/schema/sql - Download SQL file (Supabase).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sql',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'download_sql' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'connection' => array(
						'description' => 'Connection name to get SQL for',
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Get schema status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_status( $request ) {
		try {
			// Get connection name from request parameter.
			$connection_name = $request->get_param( 'connection' ) ? $request->get_param( 'connection' ) : 'default';

			// Get connection config.
			$settings_manager = $this->get_settings_manager();
			$connections      = $settings_manager->get_all_connections();

			if ( ! isset( $connections[ $connection_name ] ) ) {
				return new WP_Error(
					'connection_not_found',
					/* translators: %s: connection name */
					sprintf( __( 'Connection "%s" not found', 'gregius-data' ), $connection_name ),
					array( 'status' => 404 )
				);
			}

			$config = $connections[ $connection_name ];

			// Handle PostgREST differently from direct PostgreSQL.
			if ( 'postgrest' === $config['type'] ) {
				// For PostgREST, check via REST API and return same format as direct connection.
				require_once plugin_dir_path( __FILE__ ) . '../providers/class-gg-postgrest-provider.php';
				$provider       = new GG_Data_PostgREST_Provider( $config );
				$version_result = $provider->get_schema_version();

				$schema_version = $version_result['version'] ?? '0.0.0';
				$schema_exists  = $version_result['success'] && '0.0.0' !== $schema_version && 'unknown' !== $schema_version;

				// Return same format as PDO for consistent frontend handling.
				$status = array(
					'connected'         => true,
					'connection_name'   => $connection_name,
					'complete'          => $schema_exists,
					'schema_exists'     => $schema_exists,
					'tables'            => array(
						'complete' => $schema_exists,
						'missing'  => array(),
					),
					'vector_extension'  => $schema_exists,  // Assume extensions are installed if schema exists
					'pg_trgm_extension' => $schema_exists,  // Assume extensions are installed if schema exists
					'schema_version'    => $schema_version,
					'version'           => $schema_version,
					'plugin_version'    => defined( 'GG_DATA_VERSION' ) ? GG_DATA_VERSION : '1.0.0',
					'requires_update'   => $schema_exists && version_compare( $schema_version, defined( 'GG_DATA_VERSION' ) ? GG_DATA_VERSION : '1.0.0', '<' ),
					'update_available'  => $schema_exists && version_compare( $schema_version, defined( 'GG_DATA_VERSION' ) ? GG_DATA_VERSION : '1.0.0', '<' ),
				);

				return new WP_REST_Response( $status, 200 );
			} else {
				// For PDO connections, use schema manager.
				$schema_manager = new GG_Data_Schema_Manager();
				$status         = $schema_manager->get_schema_status( $connection_name );

				return new WP_REST_Response( $status, 200 );
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'schema_error',
				'Error getting schema status: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Create schema
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_schema( $request ) {
		try {
			// Get connection name from request parameter.
			$connection_name = $request->get_param( 'connection' ) ? $request->get_param( 'connection' ) : 'default';

			// Get schema manager instance.
			$schema_manager = new GG_Data_Schema_Manager();

			// Create the schema for the specified connection.
			$result = $schema_manager->create_all_tables( $connection_name );

			if ( $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						/* translators: %s: connection name */
						'message' => sprintf( __( 'Schema created successfully for connection "%s"', 'gregius-data' ), $connection_name ),
						'data'    => $result,
					),
					200
				);
			} else {
				return new WP_Error(
					'schema_create_failed',
					$result['message'] ?? __( 'Failed to create schema', 'gregius-data' ),
					array(
						'status' => 500,
						'data'   => $result,
					)
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'schema_error',
				'Error creating schema: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Upgrade schema
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function upgrade_schema( $request ) {
		try {
			// Get connection name from request parameter.
			$connection_name = $request->get_param( 'connection' ) ? $request->get_param( 'connection' ) : 'default';

			// Get schema manager instance.
			$schema_manager = new GG_Data_Schema_Manager();

			// Upgrade the schema for the specified connection.
			$result = $schema_manager->upgrade_schema_to_latest( $connection_name );

			if ( $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						/* translators: %s: connection name */
						'message' => sprintf( __( 'Schema upgraded successfully for connection "%s"', 'gregius-data' ), $connection_name ),
						'data'    => $result,
					),
					200
				);
			} else {
				return new WP_Error(
					'schema_upgrade_failed',
					$result['message'] ?? __( 'Failed to upgrade schema', 'gregius-data' ),
					array(
						'status' => 500,
						'data'   => $result,
					)
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'schema_error',
				'Error upgrading schema: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Verify schema exists (Supabase only)
	 *
	 * Checks if gg_schema_meta table exists and returns current version.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function verify_schema( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );

			// Get connection config.
			$settings_manager = $this->get_settings_manager();
			$connections      = $settings_manager->get_all_connections();

			if ( ! isset( $connections[ $connection_name ] ) ) {
				return new WP_Error(
					'connection_not_found',
					/* translators: %s: connection name */
					sprintf( __( 'Connection "%s" not found', 'gregius-data' ), $connection_name ),
					array( 'status' => 404 )
				);
			}

			$config = $connections[ $connection_name ];

			// Only for Supabase connections.
			if ( 'postgrest' !== $config['type'] && 'supabase' !== $config['type'] ) {
				return new WP_Error(
					'invalid_connection_type',
					__( 'Schema verification is only available for Supabase connections', 'gregius-data' ),
					array( 'status' => 400 )
				);
			}

			// Load PostgREST provider.
			require_once plugin_dir_path( __FILE__ ) . '../providers/class-gg-postgrest-provider.php';
			$provider       = new GG_Data_PostgREST_Provider( $config );           // Check schema version.
			$version_result = $provider->get_schema_version();

			if ( $version_result['success'] && '0.0.0' !== $version_result['version'] ) {
				// Schema exists!
				// Update connection metadata.
				$config['schema_version'] = $version_result['version'];
				$config['schema_status']  = 'ready';
				$settings_manager->save_connection( $connection_name, $config );

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => sprintf(
							/* translators: %s: schema version number */
							__( 'Schema verified successfully. Version: %s', 'gregius-data' ),
							$version_result['version']
						),
						'version' => $version_result['version'],
						'status'  => 'ready',
					),
					200
				);
			} else {
				// Schema doesn't exist yet.
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Schema not found. Please run the SQL file in Supabase SQL Editor.', 'gregius-data' ),
						'version' => '0.0.0',
						'status'  => 'not_created',
					),
					200
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'verification_error',
				'Error verifying schema: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Download SQL file (Supabase only)
	 *
	 * Returns the SQL file content for manual execution in Supabase SQL Editor.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function download_sql( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );

			// Get connection config.
			$settings_manager = $this->get_settings_manager();
			$connections      = $settings_manager->get_all_connections();

			if ( ! isset( $connections[ $connection_name ] ) ) {
				return new WP_Error(
					'connection_not_found',
					/* translators: %s: connection name */
					sprintf( __( 'Connection "%s" not found', 'gregius-data' ), $connection_name ),
					array( 'status' => 404 )
				);
			}

			$config = $connections[ $connection_name ];

			// Only for Supabase connections.
			if ( 'postgrest' !== $config['type'] && 'supabase' !== $config['type'] ) {
				return new WP_Error(
					'invalid_connection_type',
					__( 'SQL download is only available for Supabase connections', 'gregius-data' ),
					array( 'status' => 400 )
				);
			}

			// Get SQL file path.
			$sql_file = plugin_dir_path( __FILE__ ) . '../sql/postgrest-schema.sql';

			if ( ! file_exists( $sql_file ) ) {
				return new WP_Error(
					'sql_file_not_found',
					__( 'SQL file not found', 'gregius-data' ),
					array( 'status' => 404 )
				);
			}

			// Read SQL content.
			$sql_content = file_get_contents( $sql_file );

			if ( false === $sql_content ) {
				return new WP_Error(
					'sql_read_error',
					__( 'Failed to read SQL file', 'gregius-data' ),
					array( 'status' => 500 )
				);
			}

			// Extract project ref for dashboard URL.
			$project_ref = '';
			if ( preg_match( '/https:\/\/([^\.]+)\.supabase\.co/', $config['project_url'], $matches ) ) {
				$project_ref = $matches[1];
			}

			// Return SQL content with instructions.
			return new WP_REST_Response(
				array(
					'success'       => true,
					'sql'           => $sql_content,
					'filename'      => 'postgrest-schema.sql',
					'instructions'  => array(
						'1. Copy the SQL content below',
						'2. Open Supabase SQL Editor: https://supabase.com/dashboard/project/' . $project_ref . '/sql',
						'3. Create a new query',
						'4. Paste the SQL content',
						'5. Click "Run" to execute',
						'6. Wait for completion (30-60 seconds)',
						'7. Return here and click "Verify Schema"',
					),
					'dashboard_url' => $project_ref ? 'https://supabase.com/dashboard/project/' . $project_ref . '/sql' : '',
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'download_error',
				'Error downloading SQL: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
