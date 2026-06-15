<?php
/**
 * REST API Controller for Content Sync Management
 *
 * Handles REST API endpoints for content synchronization operations
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Management REST API Controller
 */
class GG_Data_REST_Sync_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'sync';

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
		// Initialize settings manager lazily.
		$this->settings_manager = null;
		$this->logger           = new GG_Data_Logger();
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
		// GET /gg-data/v1/sync/post-types.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_post_types' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// GET /gg-data/v1/sync/configuration.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/configuration',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sync_configuration' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to get configuration for', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/configuration.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/configuration',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_sync_configuration' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection'         => array(
							'description' => __( 'Connection name to save configuration for', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'enabled_post_types' => array(
							'description' => __( 'Array of enabled post types for sync', 'gregius-data' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'required'    => true,
						),
						'enabled_statuses'   => array(
							'description' => __( 'Array of enabled post statuses for sync', 'gregius-data' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'required'    => true,
						),
						'real_time_sync'     => array(
							'description' => __( 'Whether to enable real-time sync', 'gregius-data' ),
							'required'    => false,
						),
						'sync_meta'          => array(
							'description' => __( 'Whether to sync post metadata', 'gregius-data' ),
							'required'    => false,
						),
						'sync_terms'         => array(
							'description' => __( 'Whether to sync taxonomy terms', 'gregius-data' ),
							'required'    => false,
						),
					),
				),
			)
		);

		// GET /gg-data/v1/sync/status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sync_status' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to get status for', 'gregius-data' ),
							'type'        => 'string',
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/taxonomy/bulk.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/taxonomy/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync_taxonomy_bulk' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'site_id'    => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/taxonomy/terms.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/taxonomy/terms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync_taxonomy_terms' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'site_id'    => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/taxonomy/taxonomies.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/taxonomy/taxonomies',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync_taxonomy_taxonomies' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'site_id'    => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/taxonomy/relationships.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/taxonomy/relationships',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync_taxonomy_relationships' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'site_id'    => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// GET /gg-data/v1/sync/taxonomy/validation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/taxonomy/validation',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_taxonomy_sync_validation' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to validate', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/post-type/{type}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post-type/(?P<type>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync_post_type' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'type'       => array(
							'description' => __( 'Post type to sync', 'gregius-data' ),
							'type'        => 'string',
							'required'    => true,
						),
						'connection' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'site_id'    => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/post-type/{type}/clean.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post-type/(?P<type>[a-zA-Z0-9_-]+)/clean',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_post_type' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'type'       => array(
							'description' => __( 'Post type to clean', 'gregius-data' ),
							'type'        => 'string',
							'required'    => true,
						),
						'connection' => array(
							'description' => __( 'Connection name', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Batch size for cleaning', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'     => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/sync/post-type/{type}/orphans.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post-type/(?P<type>[a-zA-Z0-9_-]+)/orphans',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_orphan_posts' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'type'       => array(
							'description' => __( 'Post type to check for orphans', 'gregius-data' ),
							'type'        => 'string',
							'required'    => true,
						),
						'connection' => array(
							'description' => __( 'Connection name', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Batch size for deletion', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'     => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
						'preview'    => array(
							'description' => __( 'Preview orphans without deleting', 'gregius-data' ),
							'type'        => 'boolean',
							'default'     => false,
							'required'    => false,
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/sync/postmeta/orphans.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/postmeta/orphans',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_orphan_postmeta' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Batch size for deletion', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'     => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/sync/term-relationships/orphans.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/term-relationships/orphans',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_orphan_term_relationships' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Batch size for deletion', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'     => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/sync/term-taxonomy/orphans.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/term-taxonomy/orphans',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_orphan_term_taxonomy' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Batch size for deletion', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'     => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/sync/terms/orphans.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/terms/orphans',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_orphan_terms' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Batch size for deletion', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'     => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/batch-sync-postmeta (Batch processing for postmeta).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch-sync-postmeta',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_sync_postmeta' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection_name' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size'      => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 5000,
							'required'    => false,
						),
						'offset'          => array(
							'description' => __( 'Offset for batch processing', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
						'site_id'         => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/postmeta (legacy single-request endpoint).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/postmeta',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync_postmeta_bulk' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'site_id'    => array(
							'description' => __( 'Site ID for multisite installations', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
						'batch_size' => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 5000,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/batch-sync-post-type/:type (Batch processing for posts).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch-sync-post-type/(?P<type>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_sync_post_type' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'type'            => array(
							'description' => __( 'Post type slug', 'gregius-data' ),
							'type'        => 'string',
							'required'    => true,
						),
						'connection_name' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size'      => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 100,
							'required'    => false,
						),
						'offset'          => array(
							'description' => __( 'Offset for pagination', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
						'site_id'         => array(
							'description' => __( 'Site ID for multisite', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 1,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/batch-sync-terms (Vector-style batch processing).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch-sync-terms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_sync_terms' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection_name' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size'      => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 500,
							'required'    => false,
						),
						'offset'          => array(
							'description' => __( 'Offset for pagination', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/batch-sync-term-taxonomies.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch-sync-term-taxonomies',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_sync_term_taxonomies' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection_name' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size'      => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 500,
							'required'    => false,
						),
						'offset'          => array(
							'description' => __( 'Offset for pagination', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/batch-sync-term-relationships.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch-sync-term-relationships',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_sync_term_relationships' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection_name' => array(
							'description' => __( 'Connection name to sync to', 'gregius-data' ),
							'type'        => 'string',
							'default'     => 'default',
							'required'    => false,
						),
						'batch_size'      => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 500,
							'required'    => false,
						),
						'offset'          => array(
							'description' => __( 'Offset for pagination', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);

		// POST /gg-data/v1/sync/batch-delete.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch-delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_delete_sync' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'connection_name' => array(
							'description' => __( 'Connection name for deletion', 'gregius-data' ),
							'type'        => 'string',
							'required'    => true,
						),
						'batch_size'      => array(
							'description' => __( 'Records per batch', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 500,
							'required'    => false,
						),
						'offset'          => array(
							'description' => __( 'Offset for pagination', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
						'limit'           => array(
							'description' => __( 'Optional client-side limit tracker', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 0,
							'required'    => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Get available post types for sync
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_post_types( $request ) {
		try {
			// Get all public post types.
			$post_types = get_post_types( array( 'public' => true ), 'objects' );

			// Add some private post types that might be useful.
			$additional_types = get_post_types( array( 'show_ui' => true ), 'objects' );
			$post_types       = array_merge( $post_types, $additional_types );

			$formatted_types = array();
			foreach ( $post_types as $type => $object ) {
				// Get detailed counts per status.
				$status_counts = (array) wp_count_posts( $type );

				$formatted_types[] = array(
					'name'          => $type,
					'label'         => $object->labels->name,
					'description'   => $object->description,
					'public'        => $object->public,
					'count'         => $status_counts['publish'] ?? 0,  // Default published count for display.
					'status_counts' => $status_counts,  // All status counts for dynamic calculation.
				);
			}

			// Get available post statuses.
			$post_statuses      = get_post_stati( array(), 'objects' );
			$formatted_statuses = array();
			foreach ( $post_statuses as $status => $object ) {
				$formatted_statuses[] = array(
					'name'        => $status,
					'label'       => $object->label,
					'description' => $object->description ?? '',
				);
			}

			return new WP_REST_Response(
				array(
					'success'       => true,
					'post_types'    => $formatted_types,
					'post_statuses' => $formatted_statuses,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'post_types_error',
				/* translators: %s: error message */
				sprintf( __( 'Failed to get post types: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get sync configuration
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_sync_configuration( $request ) {
		try {
			// Get connection name from request, default to 'default'.
			$connection_name = $request->get_param( 'connection' );
			if ( empty( $connection_name ) ) {
				$connection_name = 'default';
			}

			// Get global defaults from activation (empty by default - user must choose).
			$default_post_types = get_option( 'gg_data_sync_enabled_post_types', array() );
			$default_statuses   = get_option( 'gg_data_sync_enabled_statuses', array() );
			$default_realtime   = get_option( 'gg_data_sync_real_time_sync', false );

			$config = array(
				'enabled_post_types' => $this->get_sync_setting_for_connection( $connection_name, 'enabled_post_types', $default_post_types ),
				'enabled_statuses'   => $this->get_sync_setting_for_connection( $connection_name, 'enabled_statuses', $default_statuses ),
				'real_time_sync'     => $this->get_sync_setting_for_connection( $connection_name, 'real_time_sync', $default_realtime ),
				'sync_meta'          => $this->get_sync_setting_for_connection( $connection_name, 'sync_meta', true ),
				'sync_terms'         => $this->get_sync_setting_for_connection( $connection_name, 'sync_terms', true ),
				'last_updated'       => $this->get_sync_setting_for_connection( $connection_name, 'last_updated', null ),
			);          return new WP_REST_Response(
				array(
					'success'       => true,
					'configuration' => $config,
					'connection'    => $connection_name,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'sync_config_error',
				/* translators: %s: error message */
				sprintf( __( 'Failed to get sync configuration: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Save sync configuration
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function save_sync_configuration( $request ) {
		try {
			// Get connection name from request, default to 'default'.
			$connection_name = $request->get_param( 'connection' );
			if ( empty( $connection_name ) ) {
				$connection_name = 'default';
			}

			$enabled_post_types = $request->get_param( 'enabled_post_types' );
			$enabled_statuses   = $request->get_param( 'enabled_statuses' );
			$real_time_sync     = $request->get_param( 'real_time_sync' );
			$sync_meta          = $request->get_param( 'sync_meta' );
			$sync_terms         = $request->get_param( 'sync_terms' );

			// Validate and sanitize post types.
			$available_types    = get_post_types();
			$enabled_post_types = is_array( $enabled_post_types ) ? array_values( array_intersect( $enabled_post_types, $available_types ) ) : array();

			// Validate and sanitize post statuses.
			$available_statuses = get_post_stati();
			$enabled_statuses   = is_array( $enabled_statuses ) ? array_values( array_intersect( $enabled_statuses, $available_statuses ) ) : array();          // Save configuration for specific connection.
			$this->set_sync_setting_for_connection( $connection_name, 'enabled_post_types', $enabled_post_types );
			$this->set_sync_setting_for_connection( $connection_name, 'enabled_statuses', $enabled_statuses );
			$this->set_sync_setting_for_connection( $connection_name, 'real_time_sync', $real_time_sync );
			$this->set_sync_setting_for_connection( $connection_name, 'sync_meta', $sync_meta );
			$this->set_sync_setting_for_connection( $connection_name, 'sync_terms', $sync_terms );

			/**
			 * Fires after sync post types configuration is updated for a connection.
			 * Allows downstream plugins to rebuild corpus-level derived artifacts.
			 *
			 * @param string $connection_name    The connection being configured.
			 * @param array  $enabled_post_types Sanitized list of enabled post type slugs.
			 */
			do_action( 'gg_data_sync_post_types_updated', $connection_name, $enabled_post_types );

			$this->set_sync_setting_for_connection( $connection_name, 'last_updated', current_time( 'mysql' ) );

			// Build and return the saved configuration.
			$saved_config = array(
				'enabled_post_types' => $enabled_post_types,
				'enabled_statuses'   => $enabled_statuses,
				'real_time_sync'     => $real_time_sync,
				'sync_meta'          => $sync_meta,
				'sync_terms'         => $sync_terms,
				'last_updated'       => current_time( 'mysql' ),
			);

			return new WP_REST_Response(
				array(
					'success'       => true,
					'message'       => __( 'Sync configuration saved successfully!', 'gregius-data' ),
					'connection'    => $connection_name,
					'configuration' => $saved_config,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'sync_config_save_error',
				/* translators: %s: error message */
				sprintf( __( 'Failed to save sync configuration: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get sync status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_sync_status( $request ) {
		try {
			// Hybrid connection resolution pattern:
			// 1. Try to get connection from request parameter.
			$connection_name = $request->get_param( 'connection' );

			// 2. Fall back to active connection if not provided
			if ( empty( $connection_name ) ) {
				$connection_name = $this->get_active_connection_from_settings();
			}

			// 3. Fail explicitly if still no connection (no 'default' fallback!).
			if ( empty( $connection_name ) ) {
				return new WP_Error(
					'no_connection',
					__( 'No connection specified and no active connection found. Please select a database connection.', 'gregius-data' ),
					array( 'status' => 400 )
				);
			}

			// 4. Validate connection is actually active.
			if ( ! $this->is_connection_active( $connection_name ) ) {
				return new WP_Error(
					'inactive_connection',
					/* translators: %s: connection name */
					sprintf( __( 'Connection "%s" is not active.', 'gregius-data' ), $connection_name ),
					array( 'status' => 400 )
				);
			}

			// Get current sync configuration for THIS connection.
			$enabled_post_types = $this->get_sync_setting_for_connection( $connection_name, 'enabled_post_types', array( 'post', 'page' ) );
			$enabled_statuses   = $this->get_sync_setting_for_connection( $connection_name, 'enabled_statuses', array( 'publish' ) );

			// Count total posts matching criteria.
			$total_posts = 0;
			foreach ( $enabled_post_types as $post_type ) {
				foreach ( $enabled_statuses as $status ) {
					$count        = wp_count_posts( $post_type );
					$total_posts += $count->$status ?? 0;
				}
			}

			// Check if sync is currently running.
			$is_syncing   = $this->get_transient( 'gg_data_sync_running' ) ? true : false;
			$synced_posts = $this->get_sync_setting_for_connection( $connection_name, 'synced_posts_count', 0 );
			$last_sync    = $this->get_sync_setting_for_connection( $connection_name, 'last_sync', null );

			return new WP_REST_Response(
				array(
					'success'      => true,
					'total_posts'  => $total_posts,
					'synced_posts' => $synced_posts,
					'is_syncing'   => $is_syncing,
					'last_sync'    => $last_sync,
					'connection'   => $connection_name,
					'progress'     => $is_syncing ? $this->get_sync_progress_data() : null,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'sync_status_error',
				/* translators: %s: error message */
				sprintf( __( 'Failed to get sync status: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Batch sync terms (Vector-style batch processing)
	 *
	 * Processes a fixed batch of terms synchronously, similar to vector generation.
	 * Frontend loops calling this endpoint repeatedly until all terms processed.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function batch_sync_terms( $request ) {
		$connection_name = $request->get_param( 'connection_name' ) ?? 'default';
		$batch_size      = $request->get_param( 'batch_size' ) ?? 500;
		$offset          = $request->get_param( 'offset' ) ?? 0;

		try {
			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->batch_sync_terms( $batch_size, $offset );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'] ?? __( 'Batch sync failed', 'gregius-data' ),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					/* translators: %d: number of terms processed */
					'message' => sprintf( __( 'Processed %d terms', 'gregius-data' ), $result['processed'] ),
					'batch'   => $result,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					/* translators: %s: error message */
					'message' => sprintf( __( 'Error processing batch: %s', 'gregius-data' ), $e->getMessage() ),
				),
				500
			);
		}
	}

	/**
	 * Batch sync term taxonomies (Vector-style batch processing)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function batch_sync_term_taxonomies( $request ) {
		$connection_name = $request->get_param( 'connection_name' ) ?? 'default';
		$batch_size      = $request->get_param( 'batch_size' ) ?? 500;
		$offset          = $request->get_param( 'offset' ) ?? 0;

		try {
			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->batch_sync_term_taxonomies( $batch_size, $offset );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'] ?? __( 'Batch sync failed', 'gregius-data' ),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					/* translators: %d: number of term taxonomies processed */
					'message' => sprintf( __( 'Processed %d term taxonomies', 'gregius-data' ), $result['processed'] ),
					'batch'   => $result,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					/* translators: %s: error message */
					'message' => sprintf( __( 'Error processing batch: %s', 'gregius-data' ), $e->getMessage() ),
				),
				500
			);
		}
	}

	/**
	 * Batch sync term relationships (Vector-style batch processing)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function batch_sync_term_relationships( $request ) {
		$connection_name = $request->get_param( 'connection_name' ) ?? 'default';
		$batch_size      = $request->get_param( 'batch_size' ) ?? 500;
		$offset          = $request->get_param( 'offset' ) ?? 0;

		try {
			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->batch_sync_term_relationships( $batch_size, $offset );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'] ?? __( 'Batch sync failed', 'gregius-data' ),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					/* translators: %d: number of term relationships processed */
					'message' => sprintf( __( 'Processed %d term relationships', 'gregius-data' ), $result['processed'] ),
					'batch'   => $result,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					/* translators: %s: error message */
					'message' => sprintf( __( 'Error processing batch: %s', 'gregius-data' ), $e->getMessage() ),
				),
				500
			);
		}
	}

	/**
	 * Check permissions for sync operations
	 *
	 * @return bool|WP_Error
	 */
	public function permissions_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage content sync.', 'gregius-data' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Get sync progress data from transient
	 *
	 * @return array
	 */
	private function get_sync_progress_data() {
		$progress = $this->get_transient( 'gg_data_sync_progress' );
		if ( ! $progress ) {
			$progress = array(
				'total'             => 0,
				'processed'         => 0,
				'current_operation' => __( 'Sync not running', 'gregius-data' ),
			);
		}
		return $progress;
	}

	/**
	 * Set transient wrapper for better testing
	 *
	 * @param string $key Transient key.
	 * @param mixed  $value Transient value.
	 * @param int    $expiration Expiration time.
	 * @return bool
	 */
	private function set_transient( $key, $value, $expiration ) {
		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Get transient wrapper for better testing
	 *
	 * @param string $key Transient key.
	 * @return mixed
	 */
	private function get_transient( $key ) {
		return get_transient( $key );
	}

	/**
	 * Delete transient wrapper for better testing
	 *
	 * @param string $key Transient key.
	 * @return bool
	 */
	private function delete_transient( $key ) {
		return delete_transient( $key );
	}

	/**
	 * Get sync setting helper method
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	private function get_sync_setting( $key, $default_value = null ) {
		return $this->get_settings_manager()->get( "sync_{$key}", $default_value );
	}

	/**
	 * Set sync setting helper method
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	private function set_sync_setting( $key, $value ) {
		return $this->get_settings_manager()->set( "sync_{$key}", $value );
	}

	/**
	 * Get sync setting for a specific connection
	 *
	 * This method retrieves sync settings scoped to a specific connection,
	 * enabling independent sync configurations for each database connection.
	 *
	 * @param string $connection_name The connection name (e.g., 'clri_local', 'default').
	 * @param string $key Setting key (without 'sync_' prefix).
	 * @param mixed  $default_value Default value if setting doesn't exist.
	 * @return mixed The setting value or default.
	 */
	private function get_sync_setting_for_connection( $connection_name, $key, $default_value = null ) {
		return $this->get_settings_manager()->get_with_category( 'sync', $connection_name, "sync_{$key}", $default_value );
	}

	/**
	 * Set sync setting for a specific connection
	 *
	 * This method stores sync settings scoped to a specific connection,
	 * enabling independent sync configurations for each database connection.
	 *
	 * @param string $connection_name The connection name (e.g., 'clri_local', 'default').
	 * @param string $key Setting key (without 'sync_' prefix).
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	private function set_sync_setting_for_connection( $connection_name, $key, $value ) {
		return $this->get_settings_manager()->set_with_category_public( 'sync', $connection_name, "sync_{$key}", $value );
	}

	/**
	 * Get the active connection from database settings
	 *
	 * Queries the wp_gg_settings table to find which connection
	 * is currently marked as active (is_active = 'b:1;').
	 *
	 * @return string|null Connection name if found, null otherwise.
	 */
	private function get_active_connection_from_settings() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'gg_settings';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name constructed from $wpdb->prefix, safe from SQL injection. Required to query custom settings table with serialized boolean values
		$connection_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT connection_name 
				FROM {$table_name} 
				WHERE setting_key = %s 
				AND setting_value = %s
				LIMIT 1",
				'is_active',
				'b:1;'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $connection_name;
	}

	/**
	 * Check if a connection is active
	 *
	 * Validates that the given connection exists and is marked as active
	 * in the wp_gg_settings table.
	 *
	 * @param string $connection_name The connection name to validate.
	 * @return bool True if connection is active, false otherwise.
	 */
	private function is_connection_active( $connection_name ) {
		if ( empty( $connection_name ) ) {
			return false;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'gg_settings';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name constructed from $wpdb->prefix, safe from SQL injection. Required to validate connection status from custom settings table
		$is_active = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value 
				FROM {$table_name} 
				WHERE connection_name = %s 
				AND setting_key = %s
				LIMIT 1",
				$connection_name,
				'is_active'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return 'b:1;' === $is_active;
	}

	/**
	 * Run bulk taxonomy synchronization
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function sync_taxonomy_bulk( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$site_id         = $request->get_param( 'site_id' );

			$service     = new GG_Data_Sync_Service( $connection_name );
			$sync_report = $service->sync_taxonomy_full( $site_id );

			// Add timestamp.
			$sync_report['timestamp']  = current_time( 'mysql' );
			$sync_report['connection'] = $connection_name;

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Taxonomy bulk sync completed successfully', 'gregius-data' ),
					'data'    => $sync_report,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'taxonomy_sync_failed',
				/* translators: %s: error message */
				sprintf( __( 'Taxonomy sync failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get taxonomy sync validation status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_taxonomy_sync_validation( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );

			$service            = new GG_Data_Sync_Service( $connection_name );
			$validation_results = $service->validate_sync();

			// Extract only taxonomy-related validation.
			$taxonomy_validation = array(
				'timestamp'          => $validation_results['timestamp'],
				'connection'         => $connection_name,
				'terms'              => $validation_results['terms'],
				'term_taxonomy'      => $validation_results['term_taxonomy'],
				'term_relationships' => $validation_results['term_relationships'],
			);

			// Determine overall taxonomy sync status.
			$has_critical_drift = false;
			$has_minor_drift    = false;

			foreach ( array( 'terms', 'term_taxonomy', 'term_relationships' ) as $table ) {
				if ( isset( $taxonomy_validation[ $table ]['status'] ) ) {
					$status = $taxonomy_validation[ $table ]['status'];
					if ( 'critical' === $status ) {
						$has_critical_drift = true;
					} elseif ( 'warning' === $status ) {
						$has_minor_drift = true;
					}
				}
			}

			// Set overall status.
			if ( $has_critical_drift ) {
				$taxonomy_validation['overall_status'] = 'critical';
			} elseif ( $has_minor_drift ) {
				$taxonomy_validation['overall_status'] = 'warning';
			} else {
				$taxonomy_validation['overall_status'] = 'good';
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $taxonomy_validation,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'taxonomy_validation_failed',
				/* translators: %s: error message */
				sprintf( __( 'Taxonomy validation failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Sync terms only (for debugging)
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function sync_taxonomy_terms( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$site_id         = $request->get_param( 'site_id' );

			$service = new GG_Data_Sync_Service( $connection_name );
			$results = $service->sync_taxonomy_terms_only( $site_id );

			return rest_ensure_response(
				array(
					'success'   => true,
					'data'      => $results,
					'message'   => 'Terms synchronization completed',
					'timestamp' => current_time( 'mysql' ),
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'terms_sync_failed',
				/* translators: %s: error message */
				sprintf( __( 'Terms sync failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Sync term taxonomies only (for debugging)
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function sync_taxonomy_taxonomies( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$site_id         = $request->get_param( 'site_id' );

			$service = new GG_Data_Sync_Service( $connection_name );
			$results = $service->sync_taxonomy_taxonomies_only( $site_id );

			return rest_ensure_response(
				array(
					'success'   => true,
					'data'      => $results,
					'message'   => 'Term taxonomies synchronization completed',
					'timestamp' => current_time( 'mysql' ),
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'term_taxonomies_sync_failed',
				/* translators: %s: error message */
				sprintf( __( 'Term taxonomies sync failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Sync term relationships only (for debugging)
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function sync_taxonomy_relationships( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$site_id         = $request->get_param( 'site_id' );

			$service = new GG_Data_Sync_Service( $connection_name );
			$results = $service->sync_taxonomy_relationships_only( $site_id );

			return rest_ensure_response(
				array(
					'success'   => true,
					'data'      => $results,
					'message'   => 'Term relationships synchronization completed',
					'timestamp' => current_time( 'mysql' ),
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'term_relationships_sync_failed',
				/* translators: %s: error message */
				sprintf( __( 'Term relationships sync failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Batch sync postmeta (mirrors batch_sync_post_type pattern)
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function batch_sync_postmeta( $request ) {
		$connection_name = $request->get_param( 'connection_name' ) ?? 'default';
		$batch_size      = $request->get_param( 'batch_size' ) ?? 5000;
		$offset          = $request->get_param( 'offset' ) ?? 0;
		$site_id         = $request->get_param( 'site_id' ) ?? 1;

		try {
			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->batch_sync_postmeta( $batch_size, $offset, $site_id );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'] ?? __( 'Batch sync failed', 'gregius-data' ),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					/* translators: %d: number of postmeta records processed */
					'message' => sprintf( __( 'Processed %d postmeta records', 'gregius-data' ), $result['processed'] ),
					'batch'   => $result,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					/* translators: %s: error message */
					'message' => sprintf( __( 'Error processing batch: %s', 'gregius-data' ), $e->getMessage() ),
				),
				500
			);
		}
	}

	/**
	 * Batch sync post type (mirrors batch_sync_terms pattern)
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function batch_sync_post_type( $request ) {
		$post_type       = $request->get_param( 'type' );
		$connection_name = $request->get_param( 'connection_name' ) ?? 'default';
		$batch_size      = $request->get_param( 'batch_size' ) ?? 100;
		$offset          = $request->get_param( 'offset' ) ?? 0;
		$site_id         = $request->get_param( 'site_id' ) ?? 1;

		try {
			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->batch_sync_post_type( $post_type, $batch_size, $offset, $site_id );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'] ?? __( 'Batch sync failed', 'gregius-data' ),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					/* translators: %d: number of posts processed */
					'message' => sprintf( __( 'Processed %d posts', 'gregius-data' ), $result['processed'] ),
					'batch'   => $result,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					/* translators: %s: error message */
					'message' => sprintf( __( 'Error processing batch: %s', 'gregius-data' ), $e->getMessage() ),
				),
				500
			);
		}
	}

	/**
	 * Sync specific post type
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function sync_post_type( $request ) {
		try {
			$post_type       = $request->get_param( 'type' );
			$connection_name = $request->get_param( 'connection' );
			$site_id         = $request->get_param( 'site_id' );

			$service = new GG_Data_Sync_Service( $connection_name );
			$results = $service->sync_post_type( $post_type, $site_id );

			// Check if there was an error during sync.
			if ( isset( $results['error'] ) ) {
				return new WP_Error(
					'post_type_sync_failed',
					$results['error'],
					array( 'status' => 400 )
				);
			}

			return rest_ensure_response(
				array(
					'success'   => true,
					'data'      => $results,
					'message'   => sprintf(
					/* translators: 1: post type, 2: number synced, 3: number failed */
						__( 'Synced %1$d/%2$d %3$s posts', 'gregius-data' ),
						$results['success'],
						$results['total'],
						$post_type
					),
					'timestamp' => current_time( 'mysql' ),
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'post_type_sync_failed',
				/* translators: %s: error message */
				sprintf( __( 'Post type sync failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Sync postmeta in bulk with smart scoping
	 *
	 * Only syncs metadata for posts that exist in PostgreSQL (relational integrity).
	 * Uses INNER JOIN to filter metadata to synced posts only.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function sync_postmeta_bulk( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$site_id         = $request->get_param( 'site_id' );
			$batch_size      = $request->get_param( 'batch_size' );

			$service = new GG_Data_Sync_Service( $connection_name );
			$results = $service->sync_postmeta_bulk( $batch_size, $site_id );

			return rest_ensure_response(
				array(
					'success'   => true,
					'data'      => $results,
					'message'   => sprintf(
						/* translators: 1: number of synced records, 2: number of skipped records */
						__( 'Synced %1$d postmeta records (%2$d skipped)', 'gregius-data' ),
						$results['success'],
						$results['skipped']
					),
					'timestamp' => current_time( 'mysql' ),
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'postmeta_sync_failed',
				/* translators: %s: error message */
				sprintf( __( 'Postmeta bulk sync failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Clean specific post type content
	 *
	 * Processes posts from wp_posts to wp_posts_clean,
	 * respecting user's enabled post status settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_post_type( $request ) {
		try {
			$post_type       = $request->get_param( 'type' );
			$connection_name = $request->get_param( 'connection' );
			$batch_size      = $request->get_param( 'batch_size' ) ?? 100;
			$offset          = $request->get_param( 'offset' ) ?? 0;

			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->clean_post_type( $post_type, $batch_size, $offset );

			// Check if result has 'cleaned' key (legacy return format) or is new format.
			if ( isset( $result['cleaned'] ) ) {
				return rest_ensure_response(
					array(
						'success'   => true,
						'data'      => $result,
						'message'   => sprintf(
						/* translators: 1: post type, 2: number of failures */
							__( 'Cleaned %1$d/%2$d %3$s posts', 'gregius-data' ),
							$result['cleaned'],
							$result['found'],
							$post_type
						),
						'timestamp' => current_time( 'mysql' ),
					)
				);
			}

			return rest_ensure_response(
				array(
					'success' => $result['success'],
					'message' => sprintf(
					/* translators: 1: post type, 2: number of posts deleted */
						__( 'Processed %d posts', 'gregius-data' ),
						$result['processed']
					),
					'batch'   => $result,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'post_type_clean_failed',
				/* translators: %s: error message */
				sprintf( __( 'Post type clean failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete orphan posts from PostgreSQL that don't exist in WordPress
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error
	 */
	public function delete_orphan_posts( $request ) {
		$this->logger->log( 'delete_orphan_posts called', 'debug', 'sync' );

		try {
			$post_type       = $request->get_param( 'type' );
			$connection_name = $request->get_param( 'connection' );
			$batch_size      = $request->get_param( 'batch_size' ) ?? 100;
			$offset          = $request->get_param( 'offset' ) ?? 0;
			$preview         = $request->get_param( 'preview' ) ?? false;

			$this->logger->log(
				sprintf( 'Parameters - Type: %s, Connection: %s, Batch: %d, Offset: %d, Preview: %s', $post_type, $connection_name, $batch_size, $offset, $preview ? 'yes' : 'no' ),
				'debug',
				'sync',
				$connection_name
			);

			$this->logger->log( 'Creating GG_Data_Sync_Service instance', 'debug', 'sync', $connection_name );
			$service = new GG_Data_Sync_Service( $connection_name );

			$this->logger->log( 'Calling delete_orphans method', 'debug', 'sync', $connection_name );
			$result = $service->delete_orphans(
				'post',
				$batch_size,
				$offset,
				array(
					'post_type' => $post_type,
					'preview'   => $preview,
				)
			);

			return rest_ensure_response(
				array_merge(
					$result,
					array(
						'message' => sprintf(
						/* translators: 1: number of orphans deleted, 2: post type */
							__( 'Processed %1$d orphan %2$s posts', 'gregius-data' ),
							$result['deleted'] ?? 0,
							$post_type
						),
					)
				)
			);

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Exception caught - Type: %s, Message: %s', get_class( $e ), $e->getMessage() ),
				'error',
				'sync',
				null,
				array(
					'exception_type' => get_class( $e ),
					'file'           => $e->getFile(),
					'line'           => $e->getLine(),
					'trace'          => $e->getTraceAsString(),
				)
			);

			return new WP_Error(
				'orphan_delete_failed',
				/* translators: %s: error message */
				sprintf( __( 'Orphan deletion failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete orphan postmeta from PostgreSQL that don't exist in WordPress
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error
	 */
	public function delete_orphan_postmeta( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$batch_size      = $request->get_param( 'batch_size' ) ? intval( $request->get_param( 'batch_size' ) ) : 2000;
			$offset          = $request->get_param( 'offset' ) ?? 0;

			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->delete_orphans( 'postmeta', $batch_size, $offset );

			return rest_ensure_response(
				array_merge(
					$result,
					array(
						'message' => sprintf(
						/* translators: 1: number of postmeta orphans deleted */
							__( 'Deleted %d orphan postmeta records', 'gregius-data' ),
							$result['deleted'] ?? 0
						),
					)
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'orphan_delete_failed',
				/* translators: %s: error message */
				sprintf( __( 'Postmeta orphan deletion failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete orphan terms from PostgreSQL that don't exist in WordPress
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error
	 */
	public function delete_orphan_terms( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$batch_size      = $request->get_param( 'batch_size' ) ? intval( $request->get_param( 'batch_size' ) ) : 500;
			$offset          = $request->get_param( 'offset' ) ?? 0;

			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->delete_orphans( 'term', $batch_size, $offset );

			return rest_ensure_response(
				array_merge(
					$result,
					array(
						'message' => sprintf(
						/* translators: 1: number of orphans deleted */
							__( 'Deleted %d orphan terms', 'gregius-data' ),
							$result['deleted'] ?? 0
						),
					)
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'orphan_delete_failed',
				/* translators: %s: error message */
				sprintf( __( 'Term orphan deletion failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete orphan term_taxonomy from PostgreSQL that don't exist in WordPress
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error
	 */
	public function delete_orphan_term_taxonomy( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$batch_size      = $request->get_param( 'batch_size' ) ? intval( $request->get_param( 'batch_size' ) ) : 500;
			$offset          = $request->get_param( 'offset' ) ?? 0;

			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->delete_orphans( 'term_taxonomy', $batch_size, $offset );

			return rest_ensure_response(
				array_merge(
					$result,
					array(
						'message' => sprintf(
						/* translators: 1: number of orphans deleted */
							__( 'Deleted %d orphan term_taxonomy records', 'gregius-data' ),
							$result['deleted'] ?? 0
						),
					)
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'orphan_delete_failed',
				/* translators: %s: error message */
				sprintf( __( 'Term taxonomy orphan deletion failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}   /**
		 * Delete orphan term_relationships from PostgreSQL that don't exist in WordPress
		 *
		 * @param WP_REST_Request $request Request object.
		 * @return WP_REST_Response|WP_Error Response object or error
		 */
	public function delete_orphan_term_relationships( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$batch_size      = $request->get_param( 'batch_size' ) ? intval( $request->get_param( 'batch_size' ) ) : 500;
			$offset          = $request->get_param( 'offset' ) ?? 0;

			$service = new GG_Data_Sync_Service( $connection_name );
			$result  = $service->delete_orphans( 'term_relationship', $batch_size, $offset );

			return rest_ensure_response(
				array_merge(
					$result,
					array(
						'message' => sprintf(
						/* translators: 1: number of orphans deleted */
							__( 'Deleted %d orphan term_relationships', 'gregius-data' ),
							$result['deleted'] ?? 0
						),
					)
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'orphan_delete_failed',
				/* translators: %s: error message */
				sprintf( __( 'Term relationship orphan deletion failed: %s', 'gregius-data' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Batch delete synced content rows from PostgreSQL tables.
	 *
	 * Delete order mirrors vector deletion safety semantics and preserves
	 * destructive pagination behavior (always query from offset 0):
	 * - wp_posts_chunks (chunk_id)
	 * - wp_posts_clean (post_id)
	 * - wp_posts (id)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function batch_delete_sync( $request ) {
		$start_time      = microtime( true );
		$connection_name = $request->get_param( 'connection_name' ) ?? 'default';
		$batch_size      = absint( $request->get_param( 'batch_size' ) );
		$offset          = absint( $request->get_param( 'offset' ) );
		$limit           = absint( $request->get_param( 'limit' ) );

		if ( $batch_size <= 0 ) {
			$batch_size = 500;
		}

		// Important: for destructive batch deletes, always read from offset 0.
		// Using client-provided offset against a shrinking dataset can skip rows and stall progress.
		$query_offset = 0;

		/**
		 * Filter default batch size for sync deletion.
		 *
		 * @since 1.0.0
		 * @param int    $batch_size      Number of rows per batch. Default 500.
		 * @param string $connection_name Connection name being deleted.
		 */
		$batch_size = apply_filters( 'gg_data_sync_delete_batch_size', $batch_size, $connection_name );

		$delete_sequence = array(
			array(
				'table'  => 'wp_posts_chunks',
				'id_key' => 'chunk_id',
				'hook'   => 'gg_data_sync_delete_batch_size_wp_posts_chunks',
			),
			array(
				'table'  => 'wp_posts_clean',
				'id_key' => 'post_id',
				'hook'   => 'gg_data_sync_delete_batch_size_wp_posts_clean',
			),
			array(
				'table'  => 'wp_posts',
				'id_key' => 'id',
				'hook'   => 'gg_data_sync_delete_batch_size_wp_posts',
			),
		);

		try {
			$settings_manager = new GG_Data_Settings_Manager();
			$connection       = $settings_manager->get_connection( $connection_name );

			if ( empty( $connection ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Database connection not found in settings',
						'debug'   => array(
							'connection_name' => $connection_name,
						),
					),
					500
				);
			}

			$provider_type = isset( $connection['type'] ) ? $connection['type'] : 'postgresql';
			$deleted_count = 0;
			$errors        = array();
			$remaining     = 0;
			$current_table = null;

			if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
				$provider = GG_Data_Provider_Factory::create_provider( $provider_type, $connection, $connection_name );

				foreach ( $delete_sequence as $table_config ) {
					$current_table    = $table_config['table'];
					$id_key           = $table_config['id_key'];
					$table_batch_size = apply_filters( $table_config['hook'], $batch_size, $connection_name );
					$table_batch_size = absint( $table_batch_size );
					if ( $table_batch_size <= 0 ) {
						$table_batch_size = 1;
					}

					$record_ids = $provider->get_ids( $current_table, $table_batch_size, $query_offset, array(), $id_key );
					if ( is_wp_error( $record_ids ) ) {
						return new WP_REST_Response(
							array(
								'success' => false,
								'message' => 'Supabase/PostgREST fetch failed: ' . $record_ids->get_error_message(),
								'debug'   => array(
									'provider_type'   => $provider_type,
									'connection_name' => $connection_name,
									'table'           => $current_table,
									'offset'          => $offset,
									'batch_size'      => $table_batch_size,
								),
							),
							500
						);
					}

					$record_ids = array_map( 'intval', $record_ids );
					if ( empty( $record_ids ) ) {
						continue;
					}

					$delete_result = $provider->delete_ids( $current_table, $record_ids, $id_key );
					if ( ! empty( $delete_result['success'] ) ) {
						$deleted_count = ! empty( $delete_result['count'] ) ? (int) $delete_result['count'] : count( $record_ids );
						if ( ! empty( $delete_result['warning'] ) ) {
							$errors[] = array(
								'error'          => $delete_result['warning'],
								'affected_count' => count( $record_ids ),
								'table'          => $current_table,
							);
						}
					} else {
						$errors[] = array(
							'error'          => ! empty( $delete_result['message'] ) ? $delete_result['message'] : 'Delete failed',
							'affected_count' => count( $record_ids ),
							'table'          => $current_table,
						);
					}

					// Delete one batch from the first table with remaining rows, then return.
					break;
				}

				foreach ( $delete_sequence as $table_config ) {
					$remaining_result = $provider->count_records( $table_config['table'] );
					if ( empty( $remaining_result['success'] ) ) {
						return new WP_REST_Response(
							array(
								'success' => false,
								'message' => 'Supabase/PostgREST count failed: ' . ( ! empty( $remaining_result['error'] ) ? $remaining_result['error'] : 'unknown error' ),
								'debug'   => array(
									'provider_type'   => $provider_type,
									'connection_name' => $connection_name,
									'table'           => $table_config['table'],
								),
							),
							500
						);
					}

					$remaining += (int) $remaining_result['count'];
				}
			} else {
				$db  = new GG_Data_DB();
				$pdo = $db->get_connection( $connection_name );

				if ( ! $pdo ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Database connection failed: ' . $db->get_last_error(),
						),
						500
					);
				}

				foreach ( $delete_sequence as $table_config ) {
					$current_table    = $table_config['table'];
					$id_key           = $table_config['id_key'];
					$table_batch_size = apply_filters( $table_config['hook'], $batch_size, $connection_name );
					$table_batch_size = absint( $table_batch_size );
					if ( $table_batch_size <= 0 ) {
						$table_batch_size = 1;
					}

					$query = "SELECT {$id_key} FROM public.{$current_table} ORDER BY {$id_key} LIMIT :batch_size OFFSET :offset";
					$stmt  = $pdo->prepare( $query );
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
					$stmt->bindValue( ':batch_size', $table_batch_size, PDO::PARAM_INT );
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
					$stmt->bindValue( ':offset', $query_offset, PDO::PARAM_INT );
					$stmt->execute();
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
					$rows = $stmt->fetchAll( PDO::FETCH_ASSOC );

					$record_ids = array_map( 'intval', array_column( $rows, $id_key ) );
					if ( empty( $record_ids ) ) {
						continue;
					}

					try {
						$placeholders = implode( ',', array_fill( 0, count( $record_ids ), '?' ) );
						$delete_query = "DELETE FROM public.{$current_table} WHERE {$id_key} IN ({$placeholders})";
						$delete_stmt  = $pdo->prepare( $delete_query );

						foreach ( $record_ids as $index => $id ) {
							// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
							$delete_stmt->bindValue( $index + 1, $id, PDO::PARAM_INT );
						}

						$delete_stmt->execute();
						$deleted_count = $delete_stmt->rowCount();
					} catch ( Exception $e ) {
						$errors[] = array(
							'error'          => $e->getMessage(),
							'affected_count' => count( $record_ids ),
							'table'          => $current_table,
						);
						$this->logger->log( 'Sync batch delete error: ' . $e->getMessage(), 'error', 'sync', $connection_name );
					}

					// Delete one batch from the first table with remaining rows, then return.
					break;
				}

				foreach ( $delete_sequence as $table_config ) {
					$remaining_query = "SELECT COUNT(*) as count FROM public.{$table_config['table']}";
					$remaining_stmt  = $pdo->query( $remaining_query );
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required.
					$remaining += (int) $remaining_stmt->fetchColumn();
				}
			}

			$has_more = $remaining > 0;

			// Safety: if no rows were deleted but rows still remain, stop to prevent endless polling loops.
			if ( 0 === $deleted_count && $has_more ) {
				$has_more = false;
				$errors[] = array(
					'error' => 'No rows deleted while rows remain. Stopping to prevent infinite loop.',
				);
			}

			$duration_ms = round( ( microtime( true ) - $start_time ) * 1000 );

			return new WP_REST_Response(
				array(
					'success'       => true,
					'deleted'       => $deleted_count,
					'total_deleted' => $offset + $deleted_count,
					'has_more'      => $has_more,
					'next_offset'   => $offset + $deleted_count,
					'duration_ms'   => $duration_ms,
					'errors'        => $errors,
					'limit'         => $limit,
					'table'         => $current_table,
					'remaining'     => $remaining,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error during sync batch delete: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
						'offset'     => $offset,
						'batch_size' => $batch_size,
					),
				),
				500
			);
		}
	}
}
