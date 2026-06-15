<?php
/**
 * REST API: Vocabulary Controller
 *
 * Handles vocabulary cache endpoints for .
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vocabulary REST API Controller
 *
 * Endpoints:
 * - POST /gg-data/v1/vocabulary/prepare - Prepare and cache vocabulary
 * - GET /gg-data/v1/vocabulary/status - Get vocabulary status with drift metrics
 * - DELETE /gg-data/v1/vocabulary/cache - Clear vocabulary cache
 */
class GG_Data_REST_Vocabulary_Controller extends WP_REST_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'vocabulary';
	}

	/**
	 * Register the routes
	 */
	public function register_routes() {
		// POST /gg-data/v1/vocabulary/prepare.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/prepare',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'prepare_vocabulary' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// GET /gg-data/v1/vocabulary/status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vocabulary_status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/vocabulary/cache.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cache',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_vocabulary_cache' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Prepare and cache vocabulary
	 *
	 * Builds global TF-IDF vocabulary from all posts in wp_posts_clean
	 * and caches it in PostgreSQL (JSONB) + MySQL (metadata).
	 *
	 * Expected time: ~1 second for 2,577 posts
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_vocabulary( $request ) {
		$start_time      = microtime( true );
		$connection_name = $request->get_param( 'connection_name' );

		try {
			// Initialize vocabulary manager.
			$vocabulary_manager = new GG_Data_Vocabulary_Manager();

			// Prepare vocabulary (builds and caches).
			$result = $vocabulary_manager->prepare_vocabulary( $connection_name );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'],
					),
					500
				);
			}

			$processing_time = ( microtime( true ) - $start_time ) * 1000;

			return new WP_REST_Response(
				array(
					'success'         => true,
					'message'         => 'Vocabulary prepared and cached successfully',
					'vocabulary'      => array(
						'version'      => $result['vocabulary']['version'],
						'post_count'   => $result['vocabulary']['post_count'],
						'unique_terms' => $result['vocabulary']['unique_terms'],
					),
					'connection'      => $connection_name,
					'processing_time' => round( $processing_time, 2 ) . ' ms',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error preparing vocabulary: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}

	/**
	 * Get vocabulary status with drift metrics
	 *
	 * Returns vocabulary cache status including:
	 * - exists: Whether vocabulary is cached
	 * - version: Current vocabulary version
	 * - cached_post_count: Posts used to build vocabulary
	 * - current_post_count: Current posts in wp_posts_clean
	 * - posts_added: New posts since vocabulary built
	 * - drift_percentage: Percentage of vocabulary drift
	 * - status: 'success' (<2%), 'warning' (2-5%), 'error' (>5%)
	 * - needs_regeneration: Whether vocabulary should be rebuilt
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vocabulary_status( $request ) {
		$start_time      = microtime( true );
		$connection_name = $request->get_param( 'connection_name' );

		try {
			// Initialize vocabulary manager.
			$vocabulary_manager = new GG_Data_Vocabulary_Manager();

			// Validate vocabulary status.
			$status = $vocabulary_manager->validate_vocabulary_status( $connection_name );

			$processing_time = ( microtime( true ) - $start_time ) * 1000;

			return new WP_REST_Response(
				array(
					'success'         => true,
					'status'          => $status,
					'connection'      => $connection_name,
					'processing_time' => round( $processing_time, 2 ) . ' ms',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error retrieving vocabulary status: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}

	/**
	 * Clear vocabulary cache
	 *
	 * Removes vocabulary from both PostgreSQL (wp_posts_vocabulary_cache)
	 * and MySQL (wp_gg_settings).
	 *
	 * Used for testing and forcing vocabulary regeneration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function clear_vocabulary_cache( $request ) {
		$start_time      = microtime( true );
		$connection_name = $request->get_param( 'connection_name' );

		try {
			// Initialize vocabulary manager.
			$vocabulary_manager = new GG_Data_Vocabulary_Manager();

			// Clear vocabulary cache.
			$result = $vocabulary_manager->clear_vocabulary_cache( $connection_name );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'],
					),
					500
				);
			}

			$processing_time = ( microtime( true ) - $start_time ) * 1000;

			return new WP_REST_Response(
				array(
					'success'         => true,
					'message'         => 'Vocabulary cache cleared successfully',
					'cleared_version' => $result['cleared_version'],
					'connection'      => $connection_name,
					'processing_time' => round( $processing_time, 2 ) . ' ms',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error clearing vocabulary cache: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}
}
