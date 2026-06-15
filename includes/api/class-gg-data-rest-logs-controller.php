<?php
/**
 * REST API: Logs Controller
 *
 * Handles log retrieval, filtering, export, and maintenance operations.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * REST API controller for logs operations
 *
 * @since 1.0.0
 */
class GG_Data_REST_Logs_Controller extends WP_REST_Controller {

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
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'logs';
		$this->logger    = new GG_Data_Logger();
	}

	/**
	 * Register the routes for the controller.
	 */
	public function register_routes() {
		// GET /gg-data/v1/logs - Get paginated logs with filtering.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET /gg-data/v1/logs/stats - Get log statistics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// GET /gg-data/v1/logs/export - Export logs as CSV or JSON.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_logs' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array_merge(
						$this->get_collection_params(),
						array(
							'format' => array(
								'description' => __( 'Export format (csv or json)', 'gregius-data' ),
								'type'        => 'string',
								'default'     => 'csv',
								'enum'        => array( 'csv', 'json' ),
							),
						)
					),
				),
			)
		);

		// DELETE /gg-data/v1/logs/purge - Purge old logs.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/purge',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'purge_logs' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'days' => array(
							'description' => __( 'Delete logs older than this many days', 'gregius-data' ),
							'type'        => 'integer',
							'default'     => 30,
							'minimum'     => 1,
							'maximum'     => 365,
						),
					),
				),
			)
		);

		// GET /gg-data/v1/logs/settings - Get logging settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'logging_enabled' => array(
							'description' => __( 'Enable or disable logging', 'gregius-data' ),
							'type'        => 'boolean',
						),
						'log_level'       => array(
							'description' => __( 'Minimum log level to store', 'gregius-data' ),
							'type'        => 'string',
							'enum'        => array( 'debug', 'info', 'warning', 'error', 'critical' ),
						),
						'retention_days'  => array(
							'description' => __( 'Days to retain logs', 'gregius-data' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 365,
						),
					),
				),
			)
		);
	}

	/**
	 * Get collection parameters for log queries.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'page'          => array(
				'description' => __( 'Current page of the collection', 'gregius-data' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'      => array(
				'description' => __( 'Maximum number of items per page', 'gregius-data' ),
				'type'        => 'integer',
				'default'     => 50,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'level'         => array(
				'description' => __( 'Filter by log level (comma-separated for multiple)', 'gregius-data' ),
				'type'        => 'string',
			),
			'component'     => array(
				'description' => __( 'Filter by component (comma-separated for multiple)', 'gregius-data' ),
				'type'        => 'string',
			),
			'connection_id' => array(
				'description' => __( 'Filter by connection ID', 'gregius-data' ),
				'type'        => 'string',
			),
			'search'        => array(
				'description' => __( 'Search term for message text', 'gregius-data' ),
				'type'        => 'string',
			),
			'date_from'     => array(
				'description' => __( 'Start date (Y-m-d format)', 'gregius-data' ),
				'type'        => 'string',
				'format'      => 'date',
			),
			'date_to'       => array(
				'description' => __( 'End date (Y-m-d format)', 'gregius-data' ),
				'type'        => 'string',
				'format'      => 'date',
			),
			'orderby'       => array(
				'description' => __( 'Sort by field', 'gregius-data' ),
				'type'        => 'string',
				'default'     => 'logged_at',
				'enum'        => array( 'id', 'logged_at', 'level', 'component' ),
			),
			'order'         => array(
				'description' => __( 'Sort order', 'gregius-data' ),
				'type'        => 'string',
				'default'     => 'DESC',
				'enum'        => array( 'ASC', 'DESC' ),
			),
		);
	}

	/**
	 * Get logs with filtering and pagination.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_logs( $request ) {
		try {
			$args = array(
				'page'          => $request->get_param( 'page' ),
				'per_page'      => $request->get_param( 'per_page' ),
				'level'         => $request->get_param( 'level' ),
				'component'     => $request->get_param( 'component' ),
				'connection_id' => $request->get_param( 'connection_id' ),
				'search'        => $request->get_param( 'search' ),
				'date_from'     => $request->get_param( 'date_from' ),
				'date_to'       => $request->get_param( 'date_to' ),
				'orderby'       => $request->get_param( 'orderby' ),
				'order'         => $request->get_param( 'order' ),
			);

			$result = $this->logger->get_logs( $args );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $result,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'logs_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get log statistics.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_stats( $request ) {
		try {
			$stats = $this->logger->get_stats();

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $stats,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'logs_stats_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Export logs as CSV or JSON.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function export_logs( $request ) {
		try {
			$args = array(
				'level'         => $request->get_param( 'level' ),
				'component'     => $request->get_param( 'component' ),
				'connection_id' => $request->get_param( 'connection_id' ),
				'search'        => $request->get_param( 'search' ),
				'date_from'     => $request->get_param( 'date_from' ),
				'date_to'       => $request->get_param( 'date_to' ),
			);

			$format   = $request->get_param( 'format' ) ?? 'csv';
			$content  = $this->logger->export_logs( $args, $format );
			$filename = 'logs-' . wp_date( 'Y-m-d' ) . '.' . $format;

			// Set headers for file download.
			$content_type = 'json' === $format ? 'application/json' : 'text/csv';

			$response = new WP_REST_Response( $content, 200 );
			$response->header( 'Content-Type', $content_type );
			$response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );

			return $response;
		} catch ( Exception $e ) {
			return new WP_Error(
				'logs_export_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Purge old logs.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function purge_logs( $request ) {
		try {
			$days = $request->get_param( 'days' );

			if ( null === $days ) {
				$days = (int) get_option( 'gg_data_log_retention_days', 30 );
			}

			$deleted = $this->logger->purge_old_logs( $days, 'manual' );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'deleted' => $deleted,
					),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'logs_purge_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get logging settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_settings( $request ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'logging_enabled' => $this->logger->is_logging_enabled(),
					'log_level'       => $this->logger->get_log_level(),
					'retention_days'  => (int) get_option( 'gg_data_log_retention_days', 30 ),
					'levels'          => $this->logger->get_log_levels(),
					'components'      => $this->logger->get_components(),
				),
			),
			200
		);
	}

	/**
	 * Update logging settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function update_settings( $request ) {
		$updated = array();

		if ( null !== $request->get_param( 'logging_enabled' ) ) {
			update_option( 'gg_data_logging_enabled', (bool) $request->get_param( 'logging_enabled' ) );
			$updated['logging_enabled'] = (bool) $request->get_param( 'logging_enabled' );
		}

		if ( null !== $request->get_param( 'log_level' ) ) {
			$level = $request->get_param( 'log_level' );
			if ( in_array( $level, $this->logger->get_log_levels(), true ) ) {
				update_option( 'gg_data_log_level', $level );
				$updated['log_level'] = $level;
			}
		}

		if ( null !== $request->get_param( 'retention_days' ) ) {
			$days = max( 1, min( 365, intval( $request->get_param( 'retention_days' ) ) ) );
			update_option( 'gg_data_log_retention_days', $days );
			$updated['retention_days'] = $days;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'updated'  => $updated,
					'settings' => array(
						'logging_enabled' => $this->logger->is_logging_enabled(),
						'log_level'       => $this->logger->get_log_level(),
						'retention_days'  => (int) get_option( 'gg_data_log_retention_days', 30 ),
					),
				),
			),
			200
		);
	}

	/**
	 * Check if a given request has access to read logs.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view logs.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Check if a given request has access to update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to update log settings.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Check if a given request has access to delete/purge logs.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to purge logs.', 'gregius-data' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}
}
