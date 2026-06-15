<?php
/**
 * REST API: Search Controller
 *
 * Handles search-related REST API endpoints.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_REST_Search_Controller
 *
 * REST API controller for search health monitoring.
 */
class GG_Data_REST_Search_Controller extends WP_REST_Controller {

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings;

	/**
	 * Search fallback instance (lazy loaded)
	 *
	 * @var GG_Data_Search_Fallback
	 */
	private $fallback;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'search';
		$this->settings  = new GG_Data_Settings_Manager();
	}

	/**
	 * Get fallback instance (lazy load)
	 *
	 * @return GG_Data_Search_Fallback
	 */
	private function get_fallback() {
		if ( ! $this->fallback ) {
			$this->fallback = new GG_Data_Search_Fallback();
		}
		return $this->fallback;
	}

	/**
	 * Get search schema instance (lazy load)
	 *
	 * @return GG_Data_Search_Schema
	 */
	private function get_search_schema() {
		static $search_schema = null;
		if ( ! $search_schema ) {
			$search_schema = new GG_Data_Search_Schema();
		}
		return $search_schema;
	}

	/**
	 * Get database instance (lazy load)
	 *
	 * @return GG_Data_DB
	 */
	private function get_db() {
		static $db = null;
		if ( ! $db ) {
			$db = new GG_Data_DB();
		}
		return $db;
	}

	/**
	 * Get canonical search settings scope.
	 *
	 * @return string
	 */
	private function get_search_settings_scope() {
		if ( defined( 'GG_DATA_SEARCH_SETTINGS_CONNECTION' ) ) {
			return GG_DATA_SEARCH_SETTINGS_CONNECTION;
		}

		return '__global__';
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		// GET /gg-data/v1/search/health - Get search health status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => array( $this, 'get_health_permissions_check' ),
				),
			)
		);

		// POST /gg-data/v1/search/health/check - Run manual health check.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health/check',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'check_health' ),
					'permission_callback' => array( $this, 'update_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/search/health/reset - Reset health metrics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_health' ),
					'permission_callback' => array( $this, 'update_health_permissions_check' ),
				),
			)
		);

		// GET /gg-data/v1/search/status - Get search function status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'get_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'default'     => 'default',
						),
					),
				),
			)
		);

		// POST /gg-data/v1/search/schema/create - Create search function and schema.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema/create',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_schema' ),
					'permission_callback' => array( $this, 'update_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'default'     => 'default',
						),
					),
				),
			)
		);

		// GET /gg-data/v1/search/language-status - Get language status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/language-status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_language_status' ),
					'permission_callback' => array( $this, 'get_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/search/update-language - Update search language.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update-language',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_search_language' ),
					'permission_callback' => array( $this, 'update_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/search/fix-settings - Fix missing search settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fix-settings',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'fix_settings' ),
					'permission_callback' => array( $this, 'update_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// GET /gg-data/v1/search/typo-tolerance-status - Get typo tolerance extension status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/typo-tolerance-status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_typo_tolerance_status' ),
					'permission_callback' => array( $this, 'get_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// GET /gg-data/v1/search/typo-tolerance - Get typo tolerance settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/typo-tolerance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_typo_tolerance_settings' ),
					'permission_callback' => array( $this, 'get_health_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/search/typo-tolerance - Update typo tolerance settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/typo-tolerance',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_typo_tolerance_settings' ),
					'permission_callback' => array( $this, 'update_health_permissions_check' ),
					'args'                => array(
						'connection'                    => array(
							'description' => 'Connection name',
							'type'        => 'string',
							'required'    => true,
						),
						'typo_tolerance'                => array(
							'description' => 'Enable typo tolerance',
							'type'        => 'boolean',
							'required'    => false,
						),
						'similarity_threshold'          => array(
							'description' => 'Similarity threshold (0.2-0.5)',
							'type'        => 'number',
							'required'    => false,
						),
						'retrieval_mode'                => array(
							'description' => 'Search retrieval mode',
							'type'        => 'string',
							'enum'        => array( 'hybrid_default', 'postgresql_only' ),
							'required'    => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Get health status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_health( $request ) {
		$fallback = $this->get_fallback();
		$health   = $fallback->get_health();
		$status   = $fallback->get_status();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'status'               => $status,
					'total_searches'       => $health['total_searches'],
					'successful_searches'  => $health['successful_searches'],
					'consecutive_failures' => $health['consecutive_failures'],
					'success_rate'         => round( $health['success_rate'], 2 ),
					'last_success'         => $health['last_success'],
					'last_error'           => $health['last_error'],
					'last_error_time'      => $health['last_error_time'],
					'last_latency_ms'      => $health['last_latency_ms'],
					'last_alert_sent'      => $health['last_alert_sent'],
					'recent_errors'        => $health['recent_errors'],
				),
			),
			200
		);
	}

	/**
	 * Run manual health check
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function check_health( $request ) {
		$connection_name = $request->get_param( 'connection' );
		$fallback        = $this->get_fallback();
		$result          = $fallback->check_health( $connection_name );

		return new WP_REST_Response(
			array(
				'success' => 'healthy' === $result['status'],
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Reset health metrics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function reset_health( $request ) {
		$fallback = $this->get_fallback();
		$fallback->reset_metrics();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Search health metrics reset successfully',
			),
			200
		);
	}

	/**
	 * Get search function status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_status( $request ) {
		$connection_name = $request->get_param( 'connection' );
		if ( empty( $connection_name ) ) {
			$connection_name = 'default';
		}
		$settings_scope = $this->get_search_settings_scope();

		// Check if schema version exists in settings.
		$schema_version = $this->settings->get_with_category( 'search', $settings_scope, 'schema_version', '' );

		// Also check if function actually exists in database.
		$search_schema = $this->get_search_schema();
		$status        = $search_schema->get_status( $connection_name );

		return new WP_REST_Response(
			array(
				'success'         => true,
				'data'            => $status,
				'schema_version'  => $schema_version,
				'connection_name' => $connection_name,
			),
			200
		);
	}

	/**
	 * Create search schema (PostgreSQL FTS function)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function create_schema( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			if ( empty( $connection_name ) ) {
				$connection_name = 'default';
			}
			$settings_scope = $this->get_search_settings_scope();

			// Get PDO connection.
			$db         = $this->get_db();
			$connection = $db->get_connection( $connection_name );
			if ( ! $connection ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'Failed to get database connection for: ' . $connection_name,
					),
					500
				);
			}

			// Use the search schema class to create the function Pass connection name for language storage).
			$search_schema = $this->get_search_schema();
			$success       = $search_schema->create_search_function( $connection, $connection_name );

			if ( ! $success ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'Failed to create search function. Check logs for details.',
					),
					500
				);
			}

			// Store schema version in settings.
			$version = '1.1.0'; // Updated for multi-language support.
			$this->settings->set_with_category_public( 'search', $settings_scope, 'schema_version', $version );

			return new WP_REST_Response(
				array(
					'success' => true,
					'version' => $version,
					'message' => 'Search schema created successfully',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Get language status
	 *
	 * Returns comparison between stored language and current WordPress locale
	 *
	 * @param WP_REST_Request $request Request object with 'connection' parameter.
	 * @return WP_REST_Response Response with language status information.
	 * @throws Exception If language helper file is not found.
	 */
	public function get_language_status( $request ) {
		try {
			$settings_scope = $this->get_search_settings_scope();

			// Require the language helper.
			$language_helper_path = GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-language.php';
			if ( ! file_exists( $language_helper_path ) ) {
				throw new Exception( 'Language helper file not found at: ' . $language_helper_path );
			}
			require_once $language_helper_path;

			// Get stored language (using correct method with category).
			$stored_language = $this->settings->get_with_category( 'search', $settings_scope, 'language', 'english' );

			// Get current site language.
			$current_language = GG_Data_Search_Language::get_site_search_language();
			$current_locale   = get_locale();

			// Check if mismatch.
			$mismatch = ( $stored_language !== $current_language );

			return new WP_REST_Response(
				array(
					'success'  => true,
					'stored'   => $stored_language,
					'current'  => $current_language,
					'locale'   => $current_locale,
					'mismatch' => $mismatch,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Update search language
	 *
	 * Recreates search function with current WordPress locale language
	 *
	 * @param WP_REST_Request $request Request object with 'connection' parameter.
	 * @return WP_REST_Response Response with update status.
	 * @throws Exception If database connection is unavailable.
	 */
	public function update_search_language( $request ) {
		$connection_name = $request->get_param( 'connection' );

		try {
			// Require the schema class.
			require_once GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-schema.php';
			require_once GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-language.php';

			// Get current site language.
			$new_language = GG_Data_Search_Language::get_site_search_language();
			$locale       = get_locale();

			// Get database connection.
			$db         = $this->get_db();
			$connection = $db->get_connection( $connection_name );
			if ( ! $connection ) {
				throw new Exception( 'Database connection not found: ' . $connection_name );
			}

			// Drop existing function (if it exists).
			$drop_sql = 'DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text)';
			$connection->exec( $drop_sql );

			// Recreate function with new language (constructor takes no parameters).
			$schema = new GG_Data_Search_Schema();
			$schema->create_search_function( $connection, $connection_name );

			return new WP_REST_Response(
				array(
					'success'  => true,
					'language' => $new_language,
					'locale'   => $locale,
					'message'  => 'Search language updated successfully',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Fix missing search settings
	 *
	 * Checks for missing search.language setting and adds it if not found.
	 * Does not change schema version - just patches missing settings.
	 *
	 * @param WP_REST_Request $request Request object with 'connection' parameter.
	 * @return WP_REST_Response Response with fixed settings information.
	 */
	public function fix_settings( $request ) {
		$settings_scope  = $this->get_search_settings_scope();
		$connection_name = $request->get_param( 'connection' );

		try {
			require_once GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-language.php';

			$fixed  = array();
			$values = array();

			// Check if search.language setting exists (using correct method with category).
			$stored_language = $this->settings->get_with_category( 'search', $settings_scope, 'language', null );

			if ( null === $stored_language ) {
				// Setting missing - add it.
				$current_language = GG_Data_Search_Language::get_site_search_language();
				$this->settings->set_with_category_public( 'search', $settings_scope, 'language', $current_language );

				$fixed[]                   = 'search.language';
				$values['search.language'] = $current_language;
			}

			$stored_retrieval_mode = $this->settings->get_with_category( 'search', $settings_scope, 'retrieval_mode', null );
			if ( null === $stored_retrieval_mode ) {
				$this->settings->set_with_category_public( 'search', $settings_scope, 'retrieval_mode', 'hybrid_default' );
				$fixed[]                         = 'search.retrieval_mode';
				$values['search.retrieval_mode'] = 'hybrid_default';
			}

			$stored_observability_toggle = $this->settings->get_with_category( 'search', $settings_scope, 'observability_enabled', null );
			if ( null === $stored_observability_toggle ) {
				$this->settings->set_with_category_public( 'search', $settings_scope, 'observability_enabled', false );
				$fixed[]                                = 'search.observability_enabled';
				$values['search.observability_enabled'] = false;
			}

			$stored_trigram_supported = $this->settings->get_with_category( 'search', $connection_name, 'trigram_supported', null );
			if ( null === $stored_trigram_supported ) {
				$trigram_key = 'search.' . $connection_name . '.trigram_supported';
				$this->settings->set_with_category_public( 'search', $connection_name, 'trigram_supported', false );
				// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep dynamic key assignments compact.
				$fixed[]        = $trigram_key;
				$values[ $trigram_key ] = false;
				// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			}

			$stored_vector_supported = $this->settings->get_with_category( 'search', $connection_name, 'vector_supported', null );
			if ( null === $stored_vector_supported ) {
				$vector_key = 'search.' . $connection_name . '.vector_supported';
				$this->settings->set_with_category_public( 'search', $connection_name, 'vector_supported', false );
				// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep dynamic key assignments compact.
				$fixed[]       = $vector_key;
				$values[ $vector_key ] = false;
				// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			}

			if ( empty( $fixed ) ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => 'All search settings are present. No fixes needed.',
						'fixed'   => array(),
					),
					200
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Missing search settings have been added.',
					'fixed'   => $fixed,
					'values'  => $values,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Get typo tolerance extension status
	 *
	 * Checks if pg_trgm extension is installed and available.
	 *
	 * @param WP_REST_Request $request Request object with 'connection' parameter.
	 * @return WP_REST_Response Response with extension status.
	 * @throws Exception If database connection fails or query execution fails.
	 */
	public function get_typo_tolerance_status( $request ) {
		$connection_name = $request->get_param( 'connection' );

		try {
			// Get connection config to determine provider type.
			$connections = $this->settings->get_all_connections();

			if ( ! isset( $connections[ $connection_name ] ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'Connection not found: ' . $connection_name,
					),
					404
				);
			}

			$config   = $connections[ $connection_name ];
			$provider = isset( $config['type'] ) ? $config['type'] : 'postgresql';

			// Handle legacy 'supabase' type name (backward compatibility).
			if ( 'supabase' === $provider ) {
				$provider = 'postgrest';
			}

			// Route to appropriate implementation.
			if ( 'postgrest' === $provider ) {
				return $this->get_typo_tolerance_status_supabase( $connection_name, $config );
			}

			// PDO PostgreSQL path.
			$db         = $this->get_db();
			$connection = $db->get_connection( $connection_name );

			if ( ! $connection ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'No PostgreSQL connection available',
					),
					500
				);
			}

			// Check extension support for both trigram and vector to persist canonical capability truth.
			$stmt         = $connection->query( "SELECT * FROM pg_extension WHERE extname = 'pg_trgm'" );
			$is_installed = (bool) $stmt->fetchColumn();
			$vector_stmt  = $connection->query( "SELECT * FROM pg_extension WHERE extname = 'vector'" );
			$has_vector   = (bool) $vector_stmt->fetchColumn();

			$this->settings->set_with_category_public( 'search', $connection_name, 'trigram_supported', $is_installed );
			$this->settings->set_with_category_public( 'search', $connection_name, 'vector_supported', $has_vector );

			// Get extension version if installed.
			$version = null;
			if ( $is_installed ) {
				$version_sql  = "SELECT extversion FROM pg_extension WHERE extname = 'pg_trgm'";
				$version_stmt = $connection->query( $version_sql );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$version_result = $version_stmt->fetch( PDO::FETCH_ASSOC );
				$version        = $version_result ? $version_result['extversion'] : null;
			}

			return new WP_REST_Response(
				array(
					'success'      => true,
					'is_installed' => $is_installed,
					'version'      => $version,
					'message'      => $is_installed
							? 'pg_trgm extension is installed and available'
							: 'pg_trgm extension is not installed. Typo tolerance will not work.',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Get typo tolerance status for Supabase
	 *
	 * @param string $connection_name Connection name.
	 * @param array  $config          Connection configuration.
	 * @return WP_REST_Response Response with extension status.
	 */
	private function get_typo_tolerance_status_supabase( $connection_name, $config ) {
		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once __DIR__ . '/../providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );

		if ( is_wp_error( $runtime_config ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $runtime_config->get_error_message(),
				),
				500
			);
		}

		// Query schema-status RPC once and derive both extension capabilities.
		$url  = rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/rpc/get_schema_status';
		$args = array(
			'method'  => 'POST',
			'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
			'body'    => '{}',
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to check pg_trgm extension: ' . $response->get_error_message(),
				),
				500
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$extensions   = ( is_array( $data ) && ! empty( $data['extensions'] ) && is_array( $data['extensions'] ) ) ? $data['extensions'] : array();
		$is_installed = ! empty( $extensions['pg_trgm'] );
		$has_vector   = ! empty( $extensions['vector'] );
		$version      = is_string( $extensions['pg_trgm'] ?? null ) ? $extensions['pg_trgm'] : null;

		$this->settings->set_with_category_public( 'search', $connection_name, 'trigram_supported', $is_installed );
		$this->settings->set_with_category_public( 'search', $connection_name, 'vector_supported', $has_vector );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'is_installed' => $is_installed,
				'version'      => $version,
				'message'      => $is_installed
					? 'pg_trgm extension is installed and available'
					: 'pg_trgm extension is not installed. Typo tolerance will not work.',
			),
			200
		);
	}

	/**
	 * Get typo tolerance settings
	 *
	 * Retrieves typo_tolerance, similarity_threshold, and semantic activation settings.
	 *
	 * @param WP_REST_Request $request Request object with 'connection' parameter.
	 * @return WP_REST_Response Response with settings.
	 */
	public function get_typo_tolerance_settings( $request ) {
		$settings_scope = $this->get_search_settings_scope();

		try {
			$typo_tolerance                = $this->settings->get_with_category( 'search', $settings_scope, 'typo_tolerance', false );
			$similarity_threshold          = $this->settings->get_with_category( 'search', $settings_scope, 'similarity_threshold', 0.5 );
			$retrieval_mode                = $this->settings->get_with_category( 'search', $settings_scope, 'retrieval_mode', 'hybrid_default' );

			if ( ! in_array( $retrieval_mode, array( 'hybrid_default', 'postgresql_only' ), true ) ) {
				$retrieval_mode = 'hybrid_default';
			}

			// Convert serialized boolean if needed.
			if ( is_string( $typo_tolerance ) && 'b:0;' === $typo_tolerance ) {
				$typo_tolerance = false;
			} elseif ( is_string( $typo_tolerance ) && 'b:1;' === $typo_tolerance ) {
				$typo_tolerance = true;
			}

			return new WP_REST_Response(
				array(
					'success'                       => true,
					'typo_tolerance'                => (bool) $typo_tolerance,
					'similarity_threshold'          => (float) $similarity_threshold,
					'retrieval_mode'                => $retrieval_mode,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Update typo tolerance settings
	 *
	 * Updates typo_tolerance, similarity_threshold, semantic activation threshold, and/or retrieval_mode settings.
	 *
	 * @param WP_REST_Request $request Request object with typo/threshold/retrieval parameters.
	 * @return WP_REST_Response Response with update status.
	 */
	public function update_typo_tolerance_settings( $request ) {
		$settings_scope                = $this->get_search_settings_scope();
		$typo_tolerance       = $request->get_param( 'typo_tolerance' );
		$similarity_threshold = $request->get_param( 'similarity_threshold' );
		$retrieval_mode       = $request->get_param( 'retrieval_mode' );

		try {
			$updated = array();

			// Update typo_tolerance if provided.
			if ( null !== $typo_tolerance ) {
				$this->settings->set_with_category_public( 'search', $settings_scope, 'typo_tolerance', (bool) $typo_tolerance );
				$updated[] = 'typo_tolerance';
			}

			// Update similarity_threshold if provided.
			if ( null !== $similarity_threshold ) {
				// Validate range.
				$threshold = (float) $similarity_threshold;
				if ( $threshold < 0.2 || $threshold > 0.5 ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'error'   => 'Similarity threshold must be between 0.2 and 0.5',
						),
						400
					);
				}

				$this->settings->set_with_category_public( 'search', $settings_scope, 'similarity_threshold', $threshold );
				$updated[] = 'similarity_threshold';
			}

			if ( null !== $retrieval_mode ) {
				$retrieval_mode = sanitize_key( (string) $retrieval_mode );

				if ( ! in_array( $retrieval_mode, array( 'hybrid_default', 'postgresql_only' ), true ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'error'   => 'Retrieval mode must be one of: hybrid_default, postgresql_only',
						),
						400
					);
				}

				$this->settings->set_with_category_public( 'search', $settings_scope, 'retrieval_mode', $retrieval_mode );
				$updated[] = 'retrieval_mode';
			}

			if ( empty( $updated ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'No settings provided to update',
					),
					400
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'updated' => $updated,
					'message' => 'Typo tolerance settings updated successfully',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Permission check for getting health status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user can manage options.
	 */
	public function get_health_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for updating health status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user can manage options.
	 */
	public function update_health_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
