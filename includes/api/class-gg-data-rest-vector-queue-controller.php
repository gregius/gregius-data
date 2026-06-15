<?php
/**
 * REST API: Vector Queue Controller
 *
 * Handles vector generation queue endpoints for .
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vector Queue REST API Controller
 *
 * Endpoints:
 * - GET /gg-data/v1/vector-queue - List synced posts ready for vectorization
 * - POST /gg-data/v1/vector-queue/generate/{id} - Generate TF-IDF vectors for a post
 * - POST /gg-data/v1/vectors/batch-generate - Generate vectors for all posts
 * - GET /gg-data/v1/vectors/status - Monitor batch processing progress
 * - DELETE /gg-data/v1/vectors - Clear all generated vectors
 */
class GG_Data_REST_Vector_Queue_Controller extends WP_REST_Controller {
	/**
	 * Get the active WordPress table prefix.
	 *
	 * @return string
	 */
	protected function get_table_prefix() {
		return GG_Data_Table_Prefix_Resolver::runtime_prefix();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'vector-queue';
	}

	/**
	 * Register the routes
	 */
	public function register_routes() {
		// GET /gg-data/v1/vector-queue.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vector_queue' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_key'       => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'Model identifier from registry',
						),
						'limit'           => array(
							'required'          => false,
							'default'           => 10,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /gg-data/v1/vector-queue/generate/{id}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_vectors' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id'              => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'connection_name' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /gg-data/v1/vectors/batch-generate.
		register_rest_route(
			$this->namespace,
			'/vectors/batch-generate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_generate_vectors' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_key'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'Model identifier from registry',
						),
						'batch_size'       => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
						'regenerate_since' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// GET /gg-data/v1/vectors/status.
		register_rest_route(
			$this->namespace,
			'/vectors/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vector_status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_key'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// DELETE /gg-data/v1/vectors.
		register_rest_route(
			$this->namespace,
			'/vectors',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_all_vectors' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_key'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /gg-data/v1/vectors/batch-delete (Batch deletion with pagination).
		register_rest_route(
			$this->namespace,
			'/vectors/batch-delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_delete_vectors' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'model_key'       => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'batch_size'      => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 500,
							'sanitize_callback' => 'absint',
						),
						'offset'          => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'limit'           => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /gg-data/v1/vectors/posts (Post-level vector details for table display).
		register_rest_route(
			$this->namespace,
			'/vectors/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vectors_posts_list' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_key'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'per_page'        => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'page'            => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
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
	 * Get vector queue - posts ready for vectorization
	 *
	 * Returns synced posts that either:
	 * - Don't have vectors yet (vectors_generated_at IS NULL)
	 * - Have been modified since vectors were generated
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vector_queue( $request ) {
		$start_time      = microtime( true );
		$connection_name = $request->get_param( 'connection_name' );
		$model_key       = $request->get_param( 'model_key' );
		$limit           = min( $request->get_param( 'limit' ), 25 ); // Cap at 25.

		try {
			// Get database instance.
			$db = new GG_Data_DB();

			// Get connection for direct queries.
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

			// Determine vector table name.
			$vector_table = 'wp_posts_tfidf_300'; // Default.
			if ( ! empty( $model_key ) ) {
				$registry = new GG_Data_Model_Registry();
				$model    = $registry->get_model( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $model_key );
				if ( $model && ! empty( $model['vector_table_name'] ) ) {
					$vector_table = $model['vector_table_name'];
				}
			}

			// Query PostgreSQL for synced posts ready for vectorization.
			// Use LEFT JOIN to check actual vector table.
			// Sanitize table name to prevent SQL injection (though it comes from registry).
			$vector_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $vector_table );
			$table_prefix = $this->get_table_prefix();

			$sql = "SELECT 
						p.id,
						p.post_title,
						p.post_type,
						p.synced_at,
						p.post_modified_gmt as modified_at,
						v.post_id as has_vectors,
						v.generated_at as vector_generated_at
					FROM public.{$table_prefix}posts p
					LEFT JOIN public.{$vector_table} v ON p.id = v.post_id
					WHERE p.synced_at IS NOT NULL
					  AND p.post_status = 'publish'
					  AND v.post_id IS NULL
					ORDER BY p.synced_at DESC
			LIMIT :limit";

			$stmt = $pdo->prepare( $sql );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$stmt->bindValue( ':limit', $limit, PDO::PARAM_INT );
			$stmt->execute();
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$posts = $stmt->fetchAll( PDO::FETCH_ASSOC );           // Add has_vectors flag for clarity.
			foreach ( $posts as &$post ) {
				$post['has_vectors']  = ! empty( $post['has_vectors'] );
				$post['needs_update'] = false; // Not checking modified vs vector timestamp for now.
			}

			$processing_time = ( microtime( true ) - $start_time ) * 1000;

			return new WP_REST_Response(
				array(
					'success'         => true,
					'total'           => count( $posts ),
					'posts'           => $posts,
					'connection'      => $connection_name,
					'model_key'       => $model_key,
					'vector_table'    => $vector_table,
					'processing_time' => round( $processing_time, 2 ) . ' ms',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error retrieving vector queue: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
						'limit'      => $limit,
					),
				),
				500
			);
		}
	}

	/**
	 * Generate TF-IDF vectors for a post
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function generate_vectors( $request ) {
		$start_time      = microtime( true );
		$post_id         = $request->get_param( 'id' );
		$connection_name = $request->get_param( 'connection_name' );

		try {
			// Get database instance.
			$db = new GG_Data_DB();

			// Get connection for direct queries.
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

			// Verify post exists and is synced.
			$stmt = $pdo->prepare( 'SELECT id, post_title, post_type, post_content FROM public.wp_posts WHERE id = :id AND synced_at IS NOT NULL' );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$stmt->bindValue( ':id', $post_id, PDO::PARAM_INT );
			$stmt->execute();
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$post = $stmt->fetch( PDO::FETCH_ASSOC );           if ( ! $post ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Post not found or not synced',
						'post_id' => $post_id,
					),
					404
				);
			}

			// Generate vectors using cached vocabulary (10-20x performance improvement)
			// Uses GG_Data_Vocabulary_Manager for vocabulary caching with drift detection.
			$embeddings = new GG_Data_TFIDF_300_Embeddings( $connection_name );
			$result     = $embeddings->generate_all_vectors();
			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Vector generation failed: ' . $result['message'],
						'post_id' => $post_id,
						'debug'   => $result, // Include full result for debugging.
					),
					500
				);
			}

			$processing_time = ( microtime( true ) - $start_time ) * 1000;

			return new WP_REST_Response(
				array(
					'success'         => true,
					'message'         => 'Vectors generated successfully',
					'post'            => array(
						'id'    => $post['id'],
						'title' => $post['post_title'],
						'type'  => $post['post_type'],
					),
					'vectors'         => array(
						'type'      => 'tfidf',
						'processed' => $result['processed'],
						'total'     => $result['total'],
					),
					'processing_time' => round( $processing_time, 2 ) . ' ms',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error generating vectors: ' . $e->getMessage(),
					'debug'   => array(
						'post_id'    => $post_id,
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}

	/**
	 * Batch generate vectors using Vector Generator
	 *
	 * Processes a batch of posts using the appropriate strategy (TF-IDF or API).
	 * Frontend loops calling this endpoint repeatedly until all posts processed.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function batch_generate_vectors( $request ) {
		$connection_name = $request->get_param( 'connection_name' );
		$model_key       = $request->get_param( 'model_key' );
		$batch_size      = $request->get_param( 'batch_size' );
		if ( null === $batch_size || '' === $batch_size ) {
			$batch_size = 10;
		}

		/**
		 * Filter the batch size for vector/embedding generation.
		 *
		 * Allows customization of how many posts are processed per batch
		 * during vector generation. Lower values reduce memory usage but
		 * increase the number of API calls.
		 *
		 * @since 1.0.0
		 * @param int    $batch_size      Number of posts per batch. Default 10.
		 * @param string $model_key       The embedding model key being used.
		 * @param string $connection_name The database connection name.
		 */
		$batch_size = apply_filters( 'gg_data_embedding_batch_size', $batch_size, $model_key, $connection_name );

		try {
			// Use Vector Generator Orchestrator to route to appropriate strategy.
			$generator = new GG_Data_Vector_Generator();
			$result    = $generator->generate_batch( $model_key, $batch_size, $connection_name );

			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result->get_error_message(),
					),
					$result->get_error_data()['status'] ?? 500
				);
			}

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'],
					),
					500
				);
			}

			// Calculate if more work remains.
			$has_more = $result['processed'] > 0 && $result['processed'] >= $batch_size;

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => $result['message'],
					'batch'   => array(
						'processed'    => $result['processed'],
						'failed'       => $result['failed'],
						'has_more'     => $has_more,
						'total_tokens' => $result['total_tokens'],
					),
					'model'   => $model_key,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error processing batch: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
						'model_key'  => $model_key,
						'batch_size' => $batch_size,
					),
				),
				500
			);
		}
	}

	/**
	 * Get vector processing status
	 *
	 * Returns current progress for batch processing.
	 * Used for polling during vector generation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vector_status( $request ) {
		$connection_name = $request->get_param( 'connection_name' );
		$model_key       = $request->get_param( 'model_key' );

		try {
			if ( ! empty( $model_key ) ) {
				// New logic for specific model.
				$registry = new GG_Data_Model_Registry();
				$model    = $registry->get_model( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $model_key );

				if ( ! $model || empty( $model['vector_table_name'] ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Model not found or no vector table defined',
							'debug'   => array(
								'connection' => $connection_name,
								'model_key'  => $model_key,
							),
						),
						404
					);
				}

				// Check connection type - only PostgreSQL (PDO) connections support this endpoint.
				$settings_manager = new GG_Data_Settings_Manager();
				$connection_type  = $settings_manager->get_with_category( 'connections', $connection_name, 'type' );

				if ( 'postgrest' === $connection_type ) {
					// Query PostgREST/Supabase via REST API.
					$vector_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $model['vector_table_name'] );
					$table_prefix = $this->get_table_prefix();
					if ( 0 === strpos( $vector_table, 'wp_' ) ) {
						$vector_table = substr( $vector_table, 3 );
					}
					if ( 0 !== strpos( $vector_table, $table_prefix ) ) {
						$vector_table = $table_prefix . $vector_table;
					}
					$connection_config = $settings_manager->get_connection( $connection_name );

					if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
						require_once __DIR__ . '/../providers/class-gg-postgrest-provider.php';
					}

					$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $connection_config );

					if ( isset( $runtime_config['error'] ) ) {
						return new WP_REST_Response(
							array(
								'success' => false,
								'message' => $runtime_config['error'],
							),
							500
						);
					}

					$project_url  = $runtime_config['project_url'];
					$table_prefix = $this->get_table_prefix();

					$request_headers = GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false );

					// Count total posts from wp_posts_clean.
					$clean_url      = $project_url . '/rest/v1/' . $table_prefix . 'posts_clean?select=post_id&post_content_clean=not.is.null&limit=0';
					$clean_response = wp_safe_remote_head(
						$clean_url,
						array(
							'headers' => array_merge( $request_headers, array( 'Prefer' => 'count=exact' ) ),
							'timeout' => 30,
						)
					);

					if ( is_wp_error( $clean_response ) ) {
						return new WP_REST_Response(
							array(
								'success' => false,
								'message' => 'Failed to fetch post count: ' . $clean_response->get_error_message(),
							),
							500
						);
					}

					// Parse Content-Range header: "0-0/1234" -> 1234.
					$content_range = wp_remote_retrieve_header( $clean_response, 'content-range' );
					$total_posts   = 0;
					if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
						$total_posts = (int) $matches[1];
					}

					// Count total chunks from wp_posts_chunks.
					$chunks_url      = $project_url . '/rest/v1/' . $table_prefix . 'posts_chunks?select=chunk_id&limit=0';
					$chunks_response = wp_safe_remote_head(
						$chunks_url,
						array(
							'headers' => array_merge( $request_headers, array( 'Prefer' => 'count=exact' ) ),
							'timeout' => 30,
						)
					);

					$total_chunks = 0;
					if ( ! is_wp_error( $chunks_response ) ) {
						$content_range = wp_remote_retrieve_header( $chunks_response, 'content-range' );
						if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
							$total_chunks = (int) $matches[1];
						}
					}

					// Expected vectors = posts × 2 (title + excerpt) + chunks.
					$expected_vectors = ( $total_posts * 2 ) + $total_chunks;

					// Count actual vectors from vector table.
					$vector_url      = $project_url . '/rest/v1/' . $vector_table . '?select=id&limit=0';
					$vector_response = wp_safe_remote_head(
						$vector_url,
						array(
							'headers' => array_merge( $request_headers, array( 'Prefer' => 'count=exact' ) ),
							'timeout' => 30,
						)
					);

					if ( is_wp_error( $vector_response ) ) {
						return new WP_REST_Response(
							array(
								'success' => false,
								'message' => 'Failed to fetch vector count: ' . $vector_response->get_error_message(),
							),
							500
						);
					}

					// Parse Content-Range header for vector count.
					$content_range  = wp_remote_retrieve_header( $vector_response, 'content-range' );
					$actual_vectors = 0;
					if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
						$actual_vectors = (int) $matches[1];
					}

					// Count distinct posts with vectors (posts that have at least a title embedding).
					$posts_vectorized_url      = $project_url . '/rest/v1/' . $vector_table . '?select=post_id&field_type=eq.title&limit=0';
					$posts_vectorized_response = wp_safe_remote_head(
						$posts_vectorized_url,
						array(
							'headers' => array_merge( $request_headers, array( 'Prefer' => 'count=exact' ) ),
							'timeout' => 30,
						)
					);

					$posts_with_vectors = 0;
					if ( ! is_wp_error( $posts_vectorized_response ) ) {
						$content_range = wp_remote_retrieve_header( $posts_vectorized_response, 'content-range' );
						if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
							$posts_with_vectors = (int) $matches[1];
						}
					}

					// Calculate post coverage metrics for cross-client dashboard consistency.
					$post_drift_percentage = 0;
					if ( $total_posts > 0 && $posts_with_vectors < $total_posts ) {
						$post_drift_percentage = round( ( ( $posts_with_vectors - $total_posts ) / $total_posts ) * 100, 1 );
					}
					$post_percentage = $total_posts > 0 ? round( ( $posts_with_vectors / $total_posts ) * 100, 1 ) : 0;

					// Keep vector/chunk coverage metrics available for advanced diagnostics.
					$vector_drift_percentage = $expected_vectors > 0 ? round( ( ( $actual_vectors - $expected_vectors ) / $expected_vectors ) * 100, 1 ) : 0;
					$vector_percentage       = $expected_vectors > 0 ? round( ( $actual_vectors / $expected_vectors ) * 100, 1 ) : 0;

					$status = array(
						'is_processing'               => false,
						'has_vectors'                 => $actual_vectors > 0,
						'total_posts'                 => $total_posts,
						'total_chunks'                => $total_chunks,
						'expected_vectors'            => $expected_vectors,
						'actual_vectors'              => $actual_vectors,
						'processed_posts'             => $posts_with_vectors,
						'posts_with_vectors'          => $posts_with_vectors,
						'posts_pending_vectors'       => $total_posts - $posts_with_vectors,
						'posts_with_outdated_vectors' => 0, // PostgREST doesn't track outdated vectors yet.
						'drift_percentage'            => $post_drift_percentage,
						'percentage'                  => $post_percentage,
						'vector_drift_percentage'     => $vector_drift_percentage,
						'vector_percentage'           => $vector_percentage,
						'status'                      => $actual_vectors >= $expected_vectors ? 'completed' : 'pending',
						'message'                     => $actual_vectors >= $expected_vectors ? 'All vectors generated' : 'Vectors pending',

					);

					return new WP_REST_Response(
						array(
							'success' => true,
							'status'  => $status,
						),
						200
					);
				}

				$vector_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $model['vector_table_name'] );
				$table_prefix = $this->get_table_prefix();
				if ( 0 === strpos( $vector_table, 'wp_' ) ) {
					$vector_table = substr( $vector_table, 3 );
				}
				if ( 0 !== strpos( $vector_table, $table_prefix ) ) {
					$vector_table = $table_prefix . $vector_table;
				}

				$db  = new GG_Data_DB();
				$pdo = $db->get_connection( $connection_name );

				if ( ! $pdo ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Database connection failed',
						),
						500
					);
				}

				// Count total posts (cleaned).
				$table_prefix = $this->get_table_prefix();
				$total_query  = 'SELECT COUNT(*) FROM ' . $table_prefix . 'posts_clean WHERE post_content_clean IS NOT NULL';
				$stmt         = $pdo->query( $total_query );
				$total_posts  = (int) $stmt->fetchColumn();

				// Count distinct posts with vectors.
				$vectors_query      = "SELECT COUNT(DISTINCT post_id) FROM public.{$vector_table}";
				$stmt               = $pdo->query( $vectors_query );
				$posts_with_vectors = (int) $stmt->fetchColumn();

				// Count total vectors for dashboard actions (delete/coverage parity with PostgREST branch).
				$actual_vectors_query = "SELECT COUNT(*) FROM public.{$vector_table}";
				$stmt                 = $pdo->query( $actual_vectors_query );
				$actual_vectors       = (int) $stmt->fetchColumn();

				// Count posts with outdated vectors (post modified after vector generated).
				$outdated_query              = "
					SELECT COUNT(*)
					FROM {$table_prefix}posts_clean pc
					INNER JOIN public.{$table_prefix}posts p ON p.id = pc.post_id
					INNER JOIN public.{$vector_table} v ON pc.post_id = v.post_id
					WHERE p.post_modified_gmt > v.generated_at
				";
				$stmt                        = $pdo->query( $outdated_query );
				$posts_with_outdated_vectors = (int) $stmt->fetchColumn();

				$drift_percentage = 0;
				if ( $total_posts > 0 && $posts_with_vectors < $total_posts ) {
					$drift_percentage = round( ( ( $posts_with_vectors - $total_posts ) / $total_posts ) * 100, 1 );
				}

				$status = array(
					'is_processing'               => false, // We don't track background processing for API models yet.
					'has_vectors'                 => $actual_vectors > 0,
					'total_posts'                 => $total_posts,
					'actual_vectors'              => $actual_vectors,
					'processed_posts'             => $posts_with_vectors,
					'posts_with_vectors'          => $posts_with_vectors,
					'posts_pending_vectors'       => $total_posts - $posts_with_vectors,
					'posts_with_outdated_vectors' => $posts_with_outdated_vectors,
					'drift_percentage'            => $drift_percentage,
					'percentage'                  => $total_posts > 0 ? round( ( $posts_with_vectors / $total_posts ) * 100, 1 ) : 0,
					'vector_drift_percentage'     => $drift_percentage,
					'vector_percentage'           => $total_posts > 0 ? round( ( $posts_with_vectors / $total_posts ) * 100, 1 ) : 0,
					'status'                      => $posts_with_vectors >= $total_posts ? 'completed' : 'pending',
					'message'                     => $posts_with_vectors >= $total_posts ? 'All vectors generated' : 'Vectors pending',
				);

				return new WP_REST_Response(
					array(
						'success' => true,
						'status'  => $status,
					),
					200
				);
			}

			// Legacy logic.
			// Get processing status from vector processor.
			$processor = new GG_Data_Vector_Processor( $connection_name );
			$status    = $processor->get_processing_status( $connection_name );

			// Add time calculations if processing.
			if ( $status['is_processing'] && ! empty( $status['start_time'] ) ) {
				$elapsed                = time() - $status['start_time'];
				$status['elapsed_time'] = $elapsed;

				// Estimate remaining time based on current progress.
				if ( $status['processed_posts'] > 0 ) {
					$rate                          = $elapsed / $status['processed_posts'];
					$remaining_posts               = $status['total_posts'] - $status['processed_posts'];
					$status['estimated_remaining'] = round( $rate * $remaining_posts );
				}
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'status'  => $status,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error retrieving status: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}

	/**
	 * Clear all generated vectors
	 *
	 * Deletes all vectors from wp_posts_tfidf_300 table.
	 * Used for testing and vector regeneration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function clear_all_vectors( $request ) {
		$connection_name = $request->get_param( 'connection_name' );
		$model_key       = $request->get_param( 'model_key' );

		try {
			if ( ! empty( $model_key ) ) {
				// New logic for specific model.
				$registry = new GG_Data_Model_Registry();
				$model    = $registry->get_model( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $model_key );

				if ( ! $model || empty( $model['vector_table_name'] ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Model not found or no vector table defined',
						),
						404
					);
				}

				$table_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $model['vector_table_name'] );

				$db  = new GG_Data_DB();
				$pdo = $db->get_connection( $connection_name );

				if ( ! $pdo ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Database connection failed',
						),
						500
					);
				}

				// Truncate table.
				$sql = "TRUNCATE TABLE public.{$table_name}";
				$pdo->exec( $sql );

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => "Vectors cleared for model {$model_key}",
						'cleared' => 0,
					),
					200
				);
			}

			// Legacy logic.
			// Clear all vectors using vector processor.
			$processor = new GG_Data_Vector_Processor( $connection_name );
			$result    = $processor->clear_simple_vectors( $connection_name );

			if ( ! $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result['message'],
					),
					500
				);
			}

			// Extract count of cleared vectors from result message.
			$cleared = 0;
			if ( preg_match( '/(\d+)/', $result['message'], $matches ) ) {
				$cleared = (int) $matches[1];
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => $result['message'],
					'cleared' => $cleared,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error clearing vectors: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}

	/**
	 * Batch delete vectors with pagination support
	 *
	 * Deletes vectors in batches to avoid timeouts and server overload.
	 * Supports resuming from offset and batch size customization via filter.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function batch_delete_vectors( $request ) {
		$start_time      = microtime( true );
		$model_key       = $request->get_param( 'model_key' );
		$connection_name = $request->get_param( 'connection_name' );
		$batch_size      = absint( $request->get_param( 'batch_size' ) );
		$offset          = absint( $request->get_param( 'offset' ) );
		$limit           = absint( $request->get_param( 'limit' ) );
		$batch_size      = $batch_size > 0 ? $batch_size : 500;

		// Important: for destructive batch deletes, always read from offset 0.
		// Using client-provided offset against a shrinking dataset can skip rows and stall progress.
		$query_offset = 0;

		/**
		 * Filter the batch size for vector deletion.
		 *
		 * Allows customization of how many vectors are deleted per batch
		 * during vector model removal. Lower values reduce risk of timeout
		 * on shared hosting, higher values complete faster on robust servers.
		 *
		 * @since 1.0.0
		 * @param int    $batch_size      Number of vectors per batch. Default 500.
		 * @param string $model_key       The embedding model key being deleted.
		 * @param string $connection_name The database connection name.
		 */
		$batch_size = apply_filters( 'gg_data_vector_delete_batch_size', $batch_size, $model_key, $connection_name );

		try {
			// Validate and get vector table name.
			$registry = new GG_Data_Model_Registry();
			$model    = $registry->get_model( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $model_key );

			if ( ! $model || empty( $model['vector_table_name'] ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Model not found or no vector table defined',
					),
					404
				);
			}

			$table_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $model['vector_table_name'] );

			$deleted_count = 0;
			$errors        = array();
			$remaining     = 0;

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

			if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
				$provider = GG_Data_Provider_Factory::create_provider( $provider_type, $connection, $connection_name );

				$vector_ids = $provider->get_ids( $table_name, $batch_size, $query_offset, array(), 'id' );
				if ( is_wp_error( $vector_ids ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Supabase/PostgREST fetch failed: ' . $vector_ids->get_error_message(),
							'debug'   => array(
								'provider_type'   => $provider_type,
								'connection_name' => $connection_name,
								'table'           => $table_name,
								'offset'          => $offset,
								'batch_size'      => $batch_size,
							),
						),
						500
					);
				}

				$vector_ids = array_map( 'intval', $vector_ids );
				if ( ! empty( $vector_ids ) ) {
					$delete_result = $provider->delete_ids( $table_name, $vector_ids, 'id' );
					if ( ! empty( $delete_result['success'] ) ) {
						$deleted_count = ! empty( $delete_result['count'] ) ? (int) $delete_result['count'] : count( $vector_ids );
						if ( ! empty( $delete_result['warning'] ) ) {
							$errors[] = array(
								'error'          => $delete_result['warning'],
								'affected_count' => count( $vector_ids ),
							);
						}
					} else {
						$errors[] = array(
							'error'          => ! empty( $delete_result['message'] ) ? $delete_result['message'] : 'Delete failed',
							'affected_count' => count( $vector_ids ),
						);
					}
				}

				$remaining_result = $provider->count_records( $table_name );
				if ( empty( $remaining_result['success'] ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Supabase/PostgREST count failed: ' . ( ! empty( $remaining_result['error'] ) ? $remaining_result['error'] : 'unknown error' ),
							'debug'   => array(
								'provider_type'   => $provider_type,
								'connection_name' => $connection_name,
								'table'           => $table_name,
							),
						),
						500
					);
				} else {
					$remaining = (int) $remaining_result['count'];
				}
			} else {
				// Get direct PDO connection for PostgreSQL provider.
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

				// Query vector IDs to delete in this batch.
				$query = "SELECT id FROM public.{$table_name} ORDER BY id LIMIT :batch_size OFFSET :offset";
				$stmt  = $pdo->prepare( $query );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
				$stmt->bindValue( ':batch_size', $batch_size, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
				$stmt->bindValue( ':offset', $query_offset, PDO::PARAM_INT );
				$stmt->execute();
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL.
				$vectors = $stmt->fetchAll( PDO::FETCH_ASSOC );

				$vector_ids = array_column( $vectors, 'id' );

				// Delete vectors in this batch if any found.
				if ( ! empty( $vector_ids ) ) {
					try {
						// Build DELETE query with IN clause.
						$placeholders = implode( ',', array_fill( 0, count( $vector_ids ), '?' ) );
						$delete_query = "DELETE FROM public.{$table_name} WHERE id IN ({$placeholders})";
						$delete_stmt  = $pdo->prepare( $delete_query );

						// Bind all vector IDs.
						foreach ( $vector_ids as $index => $id ) {
							// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required.
							$delete_stmt->bindValue( $index + 1, $id, PDO::PARAM_INT );
						}

						$delete_stmt->execute();
						$deleted_count = $delete_stmt->rowCount();

					} catch ( Exception $e ) {
						$errors[] = array(
							'error'          => $e->getMessage(),
							'affected_count' => count( $vector_ids ),
						);
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep server-side trace for destructive batch delete failures.
						error_log( 'Vector batch delete error: ' . $e->getMessage() );
					}
				}

				// Check if more vectors exist.
				$remaining_query = "SELECT COUNT(*) as count FROM public.{$table_name}";
				$remaining_stmt  = $pdo->query( $remaining_query );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required.
				$remaining = (int) $remaining_stmt->fetchColumn();
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

			// Return response with pagination info.
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
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error during batch delete: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
						'model_key'  => $model_key,
						'offset'     => $offset,
						'batch_size' => $batch_size,
					),
				),
				500
			);
		}
	}

	/**
	 * Get detailed post-level vector status
	 *
	 * Returns list of all posts with their vector generation status.
	 * Used for displaying detailed table in VectorGenerationCard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vectors_posts_list( $request ) {
		$connection_name = $request->get_param( 'connection_name' );
		$model_key       = $request->get_param( 'model_key' );
		$per_page        = $request->get_param( 'per_page' );
		if ( null === $per_page || '' === $per_page ) {
			$per_page = 20;
		}

		$page = $request->get_param( 'page' );
		if ( null === $page || '' === $page ) {
			$page = 1;
		}
		$offset = ( $page - 1 ) * $per_page;

		try {
			// Get database connection.
			$db         = new GG_Data_DB();
			$connection = $db->get_connection( $connection_name );

			if ( ! $connection ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Database connection failed',
					),
					500
				);
			}

			// Determine vector table name.
			$vector_table = 'wp_posts_tfidf_300'; // Default.
			if ( ! empty( $model_key ) ) {
				$registry = new GG_Data_Model_Registry();
				$model    = $registry->get_model( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $model_key );
				if ( $model && ! empty( $model['vector_table_name'] ) ) {
					$vector_table = $model['vector_table_name'];
				}
			}
			$vector_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $vector_table );

			// Query posts with vector status.
			// Join wp_posts_clean with vector table to get vector generation status.
			$table_prefix = $this->get_table_prefix();
			$query        = "
				SELECT 
					pc.post_id,
					pc.post_title,
					pc.post_type,
					v.created_at as vector_generated_at,
					v.vector_dimension,
					CASE WHEN v.post_id IS NOT NULL THEN true ELSE false END as has_vector
				FROM {$table_prefix}posts_clean pc
				LEFT JOIN public.{$vector_table} v ON pc.post_id = v.post_id
				WHERE pc.post_content_clean IS NOT NULL
				ORDER BY pc.post_id ASC
				LIMIT :limit OFFSET :offset
		";

			$stmt = $connection->prepare( $query );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$stmt->bindValue( ':limit', $per_page, PDO::PARAM_INT );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$stmt->bindValue( ':offset', $offset, PDO::PARAM_INT );
			$stmt->execute();
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$posts       = $stmt->fetchAll( PDO::FETCH_ASSOC );           // Get total count for pagination.
			$count_query = '
				SELECT COUNT(*) as total
				FROM ' . $table_prefix . 'posts_clean
				WHERE post_content_clean IS NOT NULL
			';
			$count_stmt  = $connection->query( $count_query );
			$total       = (int) $count_stmt->fetchColumn();

			// Format post data for response.
			$posts_data = array_map(
				function ( $post ) {
					$post_title = isset( $post['post_title'] ) ? (string) $post['post_title'] : '';
					if ( '' === $post_title ) {
						$post_title = __( '(No Title)', 'gregius-data' );
					}

					return array(
						'post_id'             => (int) $post['post_id'],
						'post_title'          => $post_title,
						'post_type'           => $post['post_type'],
						'has_vector'          => (bool) $post['has_vector'],
						'vector_generated_at' => $post['vector_generated_at'],
						'vector_dimensions'   => $post['vector_dimension'] ? (int) $post['vector_dimension'] : 300,
					);
				},
				$posts
			);

			return new WP_REST_Response(
				array(
					'success'    => true,
					'posts'      => $posts_data,
					'pagination' => array(
						'total'       => $total,
						'per_page'    => $per_page,
						'page'        => $page,
						'total_pages' => ceil( $total / $per_page ),
					),
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Error fetching posts list: ' . $e->getMessage(),
					'debug'   => array(
						'connection' => $connection_name,
					),
				),
				500
			);
		}
	}
}
