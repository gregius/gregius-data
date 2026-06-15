<?php
/**
 * Vector processing controller for Gregius Data.
 *
 * Handles background processing of vector generation via WordPress cron.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vector Processing Controller
 *
 * Handles background processing of vectors via WordPress cron
 */
class GG_Data_Vector_Processor {
	/**
	 * Get the active WordPress table prefix.
	 *
	 * @return string
	 */
	private function get_table_prefix() {
		return GG_Data_Table_Prefix_Resolver::runtime_prefix();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Vector processing enabled with UI controls.
		add_action( 'gg_data_process_simple_vectors', array( $this, 'process_simple_vectors_cron' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	/**
	 * Clear all vector processing state and scheduled events
	 */
	private function clear_vector_processing_state() {
		// Clear WordPress options.
		delete_option( 'gg_data_tfidf_300_processing' );
		delete_option( 'gg_data_tfidf_300_start_time' );
		delete_option( 'gg_data_tfidf_300_progress' );

		// Clear any scheduled vector processing events.
		wp_clear_scheduled_hook( 'gg_data_process_simple_vectors' );
	}

	/**
	 * Start simple vector generation
	 *
	 * @return array Status information
	 */
	public function start_simple_vector_generation() {

		try {
			// Check if already processing.
			if ( get_option( 'gg_data_tfidf_300_processing', false ) ) {
				return array(
					'success' => false,
					'message' => 'Simple vector generation already in progress',
				);
			}

			// Set processing flag.
			update_option( 'gg_data_tfidf_300_processing', true );
			update_option( 'gg_data_tfidf_300_start_time', time() );
			delete_option( 'gg_data_tfidf_300_progress' );

			// Schedule immediate processing.
			if ( ! wp_next_scheduled( 'gg_data_process_simple_vectors' ) ) {
				$scheduled = wp_schedule_single_event( time(), 'gg_data_process_simple_vectors' );
			}

			// Try to trigger cron immediately (for testing).
			spawn_cron();

			return array(
				'success' => true,
				'message' => 'Simple vector generation started',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Process simple vectors via cron
	 */
	public function process_simple_vectors_cron() {

		try {
			// Check if we should be processing.
			if ( ! get_option( 'gg_data_tfidf_300_processing', false ) ) {
				return;
			}

			$simple_embeddings = new GG_Data_TFIDF_300_Embeddings();
			$result            = $simple_embeddings->generate_all_vectors();

			if ( $result['success'] ) {
				// Update progress.
				update_option(
					'gg_data_tfidf_300_progress',
					array(
						'processed'  => $result['processed'],
						'total'      => $result['total'],
						'percentage' => round( ( $result['processed'] / $result['total'] ) * 100, 1 ),
						'status'     => 'completed',
						'message'    => $result['message'],
					)
				);

				// Clear processing flag.
				delete_option( 'gg_data_tfidf_300_processing' );
				delete_option( 'gg_data_tfidf_300_start_time' );

			} else {
				// Update progress with error.
				update_option(
					'gg_data_tfidf_300_progress',
					array(
						'processed'  => 0,
						'total'      => 0,
						'percentage' => 0,
						'status'     => 'error',
						'message'    => $result['message'],
					)
				);

				// Clear processing flag.
				delete_option( 'gg_data_tfidf_300_processing' );
				delete_option( 'gg_data_tfidf_300_start_time' );

			}
		} catch ( Exception $e ) {
			// Update progress with error.
			update_option(
				'gg_data_tfidf_300_progress',
				array(
					'processed'  => 0,
					'total'      => 0,
					'percentage' => 0,
					'status'     => 'error',
					'message'    => $e->getMessage(),
				)
			);

			// Clear processing flag.
			delete_option( 'gg_data_tfidf_300_processing' );
			delete_option( 'gg_data_tfidf_300_start_time' );

		}
	}

	/**
	 * Get processing status
	 *
	 * @param string $connection_name Database connection name.
	 * @return array Status information
	 */
	public function get_processing_status( $connection_name = 'default' ) {
		$is_processing = get_option( 'gg_data_tfidf_300_processing', false );
		$progress      = get_option( 'gg_data_tfidf_300_progress', array() );
		$start_time    = get_option( 'gg_data_tfidf_300_start_time', 0 );

		// Get current simple vectors count from database.
		try {
			$table_prefix = $this->get_table_prefix();
			// Detect provider type (Supabase vs PDO).
			$settings      = new GG_Data_Settings_Manager();
			$config        = $settings->get_connection( $connection_name );
			$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

			if ( 'postgrest' === $provider_type ) {
				if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
					require_once __DIR__ . '/../providers/class-gg-postgrest-provider.php';
				}

				$table_prefix = $this->get_table_prefix();

				// Use Supabase REST API to count posts.
				$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );

				if ( ! is_wp_error( $runtime_config ) ) {
					// Count cleaned posts.
					$clean_response = wp_safe_remote_head(
						rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/' . $table_prefix . 'posts_clean?select=post_id&post_content_clean=not.is.null&limit=0',
						array(
							'headers' => array_merge(
								GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
								array(
									'Prefer' => 'count=exact',
								)
							),
						)
					);

					$total_posts = 0;
					if ( ! is_wp_error( $clean_response ) ) {
						$content_range = wp_remote_retrieve_header( $clean_response, 'content-range' );
						if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
							$total_posts = (int) $matches[1];
						}
					}

					// Count vectors.
					$vectors_response = wp_safe_remote_head(
						rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/' . $table_prefix . 'posts_tfidf_300?select=post_id&limit=0',
						array(
							'headers' => array_merge(
								GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
								array(
									'Prefer' => 'count=exact',
								)
							),
						)
					);

					$posts_with_vectors = 0;
					if ( ! is_wp_error( $vectors_response ) ) {
						$content_range = wp_remote_retrieve_header( $vectors_response, 'content-range' );
						if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
							$posts_with_vectors = (int) $matches[1];
						}
					}

					$has_vectors                 = $posts_with_vectors > 0;
					$posts_with_outdated_vectors = 0; // PostgREST doesn't track outdated vectors yet.
				} else {
					$total_posts                 = 0;
					$posts_with_vectors          = 0;
					$posts_with_outdated_vectors = 0;
					$has_vectors                 = false;
				}
			} else {
				// Use PDO for PostgreSQL.
				$db         = new GG_Data_DB();
				$connection = $db->get_connection( $connection_name );

				if ( $connection ) {
					// Count posts with vectors from cleaned posts table.
					$vectors_query = '
						SELECT 
							(SELECT COUNT(*) FROM ' . $table_prefix . 'posts_clean WHERE post_content_clean IS NOT NULL) as total_posts,
							(SELECT COUNT(*) FROM ' . $table_prefix . 'posts_tfidf_300) as posts_with_vectors,
							(SELECT COUNT(*) 
								FROM ' . $table_prefix . 'posts_clean p
								INNER JOIN ' . $table_prefix . 'posts_tfidf_300 v ON p.post_id = v.post_id
								WHERE p.post_modified_gmt > v.generated_at
							) as posts_with_outdated_vectors
					';

					$stmt = $connection->query( $vectors_query );
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
					$vector_counts = $stmt->fetch( PDO::FETCH_ASSOC );

					$total_posts                 = (int) $vector_counts['total_posts'];
					$posts_with_vectors          = (int) $vector_counts['posts_with_vectors'];
					$posts_with_outdated_vectors = (int) $vector_counts['posts_with_outdated_vectors'];
					$has_vectors                 = $posts_with_vectors > 0;
				} else {
					$total_posts                 = 0;
					$posts_with_vectors          = 0;
					$posts_with_outdated_vectors = 0;
					$has_vectors                 = false;
				}
			}
		} catch ( Exception $e ) {
			$total_posts                 = 0;
			$posts_with_vectors          = 0;
			$posts_with_outdated_vectors = 0;
			$has_vectors                 = false;
		}

		// Calculate drift percentage with special handling for orphan scenario.
		if ( 0 === $total_posts && $posts_with_vectors > 0 ) {
			// Special case: All vectors are orphans (100% drift).
			$drift_percentage = 100.0;
		} elseif ( $total_posts > 0 ) {
			// Standard case: Calculate drift relative to total posts.
			$drift_percentage = round( ( ( $posts_with_vectors - $total_posts ) / $total_posts ) * 100, 1 );
		} else {
			// Both zero: No drift.
			$drift_percentage = 0;
		}

		$status = array(
			'is_processing'               => $is_processing,
			'has_vectors'                 => $has_vectors,
			'total_posts'                 => $total_posts,
			'processed_posts'             => $posts_with_vectors, // Legacy field.
			'posts_with_vectors'          => $posts_with_vectors, // Frontend expects this.
			'posts_pending_vectors'       => $total_posts - $posts_with_vectors, // Frontend expects this.
			'posts_with_outdated_vectors' => $posts_with_outdated_vectors,
			'drift_percentage'            => $drift_percentage,
			'percentage'                  => $total_posts > 0 ? round( ( $posts_with_vectors / $total_posts ) * 100, 1 ) : 0,
		);

		if ( $is_processing ) {
			$status['status']  = 'processing';
			$status['message'] = 'Generating simple vectors...';
			if ( $start_time > 0 ) {
				$status['elapsed_time'] = time() - $start_time;
			}
			// Include progress if available.
			if ( ! empty( $progress ) && isset( $progress['processed'] ) ) {
				$status['progress'] = $progress;
			}
		} elseif ( ! empty( $progress ) && isset( $progress['status'] ) ) {
			$status = array_merge( $status, $progress );
		} else {
			$status['status']  = $has_vectors ? 'completed' : 'not_started';
			$status['message'] = $has_vectors ?
				"Simple vectors ready ({$posts_with_vectors} posts)" :
				'No vectors generated yet';
		}

		return $status;
	}

	/**
	 * Stop processing
	 *
	 * @return array Status information
	 */
	public function stop_processing() {
		delete_option( 'gg_data_tfidf_300_processing' );
		delete_option( 'gg_data_tfidf_300_start_time' );

		// Clear any scheduled events.
		wp_clear_scheduled_hook( 'gg_data_process_simple_vectors' );

		return array(
			'success' => true,
			'message' => 'Processing stopped',
		);
	}

	/**
	 * Disable simple vectors
	 *
	 * @return array Status information
	 */
	/**
	 * Clear simple vectors
	 *
	 * @return array Status information
	 */
	public function clear_simple_vectors() {
		try {
			// Stop any processing first.
			$this->stop_processing();

			// Get database connection.
			$db         = new GG_Data_DB();
			$connection = $db->get_connection();

			if ( ! $connection ) {
				return array(
					'success' => false,
					'message' => 'Database connection failed',
				);
			}

			// Clear all TF-IDF vectors.
			$table_name  = $this->get_table_prefix() . 'posts_tfidf_300';
			$clear_query = 'DELETE FROM public.' . $table_name;

			$result = $connection->exec( $clear_query );

			// Clear progress.
			delete_option( 'gg_data_tfidf_300_progress' );

			if ( false !== $result ) {
				return array(
					'success' => true,
					'message' => 'Simple vectors cleared successfully',
				);
			} else {
				return array(
					'success' => false,
					'message' => 'Failed to clear vectors',
				);
			}
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Maybe schedule cron if needed
	 */
	public function maybe_schedule_cron() {
		// Check if we have a stuck processing flag (more than 30 minutes).
		$is_processing = get_option( 'gg_data_simple_vectors_processing', false );
		$start_time    = get_option( 'gg_data_simple_vectors_start_time', 0 );

		if ( $is_processing && $start_time > 0 && ( time() - $start_time ) > 1800 ) {
			// Processing seems stuck, clear flags.
			delete_option( 'gg_data_simple_vectors_processing' );
			delete_option( 'gg_data_simple_vectors_start_time' );
		}
	}
}
