<?php
/**
 * Content Cleaning Batch Process Class
 *
 * Handles bulk cleaning of post content into wp_posts_clean table.
 * Mirrors the proven GG_Data_Post_Sync architecture.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'GG_Data_Clean_Batch' ) ) {

	/**
	 * Content Cleaning Batch class
	 */
	class GG_Data_Clean_Batch {

		/**
		 * Database connection handler
		 *
		 * @var GG_Data_DB
		 */
		protected $db;

		/**
		 * Logger instance
		 *
		 * @var GG_Data_Logger
		 */
		protected $logger;

		/**
		 * Content cleaner instance
		 *
		 * @var GG_Data_Content_Cleaner
		 */
		protected $cleaner;

		/**
		 * Connection name to use for database operations
		 *
		 * @var string
		 */
		protected $connection_name;

		/**
		 * Constructor
		 *
		 * @param string $connection_name Connection name to use (defaults to 'default').
		 */
		public function __construct( $connection_name = 'default' ) {
			$this->db              = new GG_Data_DB();
			$this->logger          = new GG_Data_Logger();
			$this->cleaner         = new GG_Data_Content_Cleaner();
			$this->connection_name = $connection_name;

			// Set the default connection for database operations.
			$this->db->set_default_connection( $connection_name );

			// Initialize the connection to set up provider.
			$this->db->get_connection( $connection_name );
		}

		/**
		 * Clean posts of a specific post type in batches with offset support
		 *
		 * Mirrors GG_Data_Post_Sync::batch_sync_post_type() pattern for progressive cleaning.
		 * Returns standardized batch result format with has_more flag for frontend pagination.
		 *
		 * @param string $post_type Post type slug (post, page, product, etc.).
		 * @param int    $batch_size Batch size for processing (default: 100).
		 * @param int    $offset Starting offset for pagination (default: 0).
		 * @throws Exception When PostgreSQL connection is unavailable or database operations fail.
		 * @return array {
		 *     Batch clean results with pagination info.
		 *
		 *     @type bool   $success   Whether batch completed without critical errors.
		 *     @type int    $processed Number of posts successfully cleaned in this batch.
		 *     @type int    $failed    Number of posts that failed to clean in this batch.
		 *     @type bool   $has_more  Whether more posts remain to be cleaned.
		 *     @type int    $total     Total posts needing cleaning (for progress tracking).
		 *     @type string $message   Human-readable status message.
		 *     @type string $post_type Post type that was cleaned.
		 * }
		 */
		public function batch_clean_post_type( $post_type, $batch_size = 100, $offset = 0 ) {
			// Allow developers to customize batch size.
			$batch_size = apply_filters( 'gg_data_clean_batch_size', $batch_size, $post_type, $this->connection_name );

			// Validate post type exists.
			if ( ! post_type_exists( $post_type ) ) {
				$this->logger->log(
					sprintf( 'Post type "%s" does not exist', $post_type ),
					'error'
				);

				return array(
					'success'   => false,
					'processed' => 0,
					'failed'    => 0,
					'has_more'  => false,
					'total'     => 0,
					'post_type' => $post_type,
					'message'   => 'Post type does not exist',
				);
			}

			// Get enabled post statuses from settings.
			$settings_manager = new GG_Data_Settings_Manager();
			$enabled_statuses = $settings_manager->get_with_category(
				'sync',
				$this->connection_name,
				'sync_enabled_statuses',
				array( 'publish' )  // Default to publish only.
			);
			if ( is_string( $enabled_statuses ) ) {
				$enabled_statuses = maybe_unserialize( $enabled_statuses );
			}

			// Validate we have statuses to filter.
			if ( empty( $enabled_statuses ) ) {
				$this->logger->log(
					sprintf( 'No enabled statuses for %s - skipping clean', $post_type ),
					'warning'
				);

				return array(
					'success'   => false,
					'processed' => 0,
					'failed'    => 0,
					'has_more'  => false,
					'total'     => 0,
					'post_type' => $post_type,
					'message'   => 'No enabled statuses configured',
				);
			}

			$results = array(
				'success'   => true,
				'processed' => 0,
				'failed'    => 0,
				'has_more'  => false,
				'total'     => 0,
				'post_type' => $post_type,
				'message'   => '',
			);

			try {
				$conn = $this->db->get_connection( $this->connection_name );
				if ( ! $conn ) {
					throw new Exception( 'PostgreSQL connection unavailable' );
				}

				// Build status placeholders for IN clause.
				$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );

				// First, get total count for progress tracking.
				$count_sql = "
					SELECT COUNT(*) as total
					FROM wp_posts p
					LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
					WHERE pc.post_id IS NULL
					  AND p.post_type = ?
					  AND p.post_status IN ($status_placeholders)
				";

				$count_stmt   = $conn->prepare( $count_sql );
				$count_params = array_merge( array( $post_type ), $enabled_statuses );
				$count_stmt->execute( $count_params );
				$total_count      = (int) $count_stmt->fetchColumn();
				$results['total'] = $total_count;

				if ( 0 === $total_count ) {
					$results['message'] = sprintf( 'No posts needing cleaning for post type "%s"', $post_type );
					$this->logger->log( $results['message'], 'info', 'sync', $connection_name );
					return $results;
				}

				// Query for posts that need cleaning with LIMIT/OFFSET.
				$sql = "
					SELECT p.id, p.post_title, p.post_content, p.post_excerpt, p.post_modified_gmt
					FROM wp_posts p
					LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
					WHERE pc.post_id IS NULL
					  AND p.post_type = ?
					  AND p.post_status IN ($status_placeholders)
					ORDER BY p.id ASC
					LIMIT ? OFFSET ?
				";

				$stmt   = $conn->prepare( $sql );
				$params = array_merge(
					array( $post_type ),
					$enabled_statuses,
					array( $batch_size, $offset )
				);
				$stmt->execute( $params );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$posts_to_clean      = $stmt->fetchAll( PDO::FETCH_OBJ );
				$found_in_batch      = count( $posts_to_clean );             // Determine if more batches remain.
				$results['has_more'] = ( $offset + $found_in_batch ) < $total_count;

				$this->logger->log(
					sprintf(
						'Batch clean for "%s": offset %d, batch size %d, found %d, total %d, has_more: %s',
						$post_type,
						$offset,
						$batch_size,
						$found_in_batch,
						$total_count,
						$results['has_more'] ? 'true' : 'false'
					),
					'info'
				);

				// Clean each post in this batch.
				foreach ( $posts_to_clean as $post ) {
					try {
						$clean_result = $this->cleaner->clean_post(
							$post->id,
							$post->post_title,
							$post->post_content,
							$post->post_excerpt,
							$this->connection_name
						);

						if ( $clean_result ) {
							++$results['processed'];
						} else {
							++$results['failed'];
							$this->logger->log(
								sprintf( 'Failed to clean post %d (type: %s)', $post->id, $post_type ),
								'error'
							);
						}
					} catch ( Exception $e ) {
						++$results['failed'];
						$this->logger->log(
							sprintf( 'Exception cleaning post %d: %s', $post->id, $e->getMessage() ),
							'error'
						);
					}
				}

				$results['message'] = sprintf(
					'Batch complete: %d processed, %d failed. %s',
					$results['processed'],
					$results['failed'],
					$results['has_more'] ? 'More batches remain.' : 'All posts cleaned.'
				);

				$this->logger->log(
					sprintf(
						'Completed batch clean for "%s": %d/%d successful, offset %d, has_more: %s',
						$post_type,
						$results['processed'],
						$found_in_batch,
						$offset,
						$results['has_more'] ? 'true' : 'false'
					),
					'info'
				);

			} catch ( Exception $e ) {
				$results['success'] = false;
				$results['message'] = sprintf( 'Batch clean failed: %s', $e->getMessage() );

				$this->logger->log(
					sprintf( 'Batch clean failed for "%s" at offset %d: %s', $post_type, $offset, $e->getMessage() ),
					'error'
				);
			}

			return $results;
		}

		/**
		 * Clean all posts of a specific post type
		 *
		 * Mirrors GG_Data_Post_Sync::sync_post_type() pattern
		 *
		 * @param string $post_type Post type slug (post, page, product, etc.).
		 * @param int    $batch_size Batch size for processing (default: 100).
		 * @throws Exception When PostgreSQL connection is unavailable or database operations fail.
		 * @return array {
		 *     Clean results.
		 *
		 *     @type int    $found      Total posts found needing cleaning.
		 *     @type int    $cleaned    Successfully cleaned.
		 *     @type int    $failed     Failed to clean.
		 *     @type int    $skipped    Already cleaned (skipped).
		 *     @type string $post_type  Post type that was cleaned.
		 * }
		 */
		public function clean_post_type( $post_type, $batch_size = 100 ) {
			// Validate post type exists.
			if ( ! post_type_exists( $post_type ) ) {
				$this->logger->log(
					sprintf( 'Post type "%s" does not exist', $post_type ),
					'error'
				);

				return array(
					'found'     => 0,
					'cleaned'   => 0,
					'failed'    => 0,
					'skipped'   => 0,
					'post_type' => $post_type,
					'error'     => 'Post type does not exist',
				);
			}

			// Get enabled post statuses from settings.
			$settings_manager = new GG_Data_Settings_Manager();
			$enabled_statuses = $settings_manager->get_with_category(
				'sync',
				$this->connection_name,
				'sync_enabled_statuses',
				array( 'publish' )  // Default to publish only.
			);
			if ( is_string( $enabled_statuses ) ) {
				$enabled_statuses = maybe_unserialize( $enabled_statuses );
			}

			$this->logger->log(
				sprintf(
					'Clean batch for "%s" will use enabled statuses: %s',
					$post_type,
					implode( ', ', $enabled_statuses )
				),
				'info'
			);

			// Validate we have statuses to filter.
			if ( empty( $enabled_statuses ) ) {
				$this->logger->log(
					sprintf( 'No enabled statuses for %s - skipping clean', $post_type ),
					'warning'
				);

				return array(
					'found'     => 0,
					'cleaned'   => 0,
					'failed'    => 0,
					'skipped'   => 0,
					'post_type' => $post_type,
					'error'     => 'No enabled statuses configured',
				);
			}

			$results = array(
				'found'     => 0,
				'cleaned'   => 0,
				'failed'    => 0,
				'skipped'   => 0,
				'post_type' => $post_type,
			);

			try {
				$conn = $this->db->get_connection( $this->connection_name );
				if ( ! $conn ) {
					throw new Exception( 'PostgreSQL connection unavailable' );
				}

				// Build status placeholders for IN clause.
				$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );

				// Query for posts that need cleaning (not in wp_posts_clean).
				$sql = "
					SELECT p.id, p.post_title, p.post_content, p.post_excerpt, p.post_modified_gmt
					FROM wp_posts p
					LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
					WHERE pc.post_id IS NULL
					  AND p.post_type = ?
					  AND p.post_status IN ($status_placeholders)
					LIMIT ?
				";

				$stmt = $conn->prepare( $sql );

				// Bind parameters: post_type, then each status, then batch_size.
				$params = array_merge( array( $post_type ), $enabled_statuses, array( $batch_size ) );
				$stmt->execute( $params );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$posts_to_clean   = $stmt->fetchAll( PDO::FETCH_OBJ );
				$results['found'] = count( $posts_to_clean );               if ( empty( $posts_to_clean ) ) {
					$this->logger->log(
						sprintf( 'No posts found needing cleaning for post type "%s"', $post_type ),
						'info'
					);
					return $results;
				}

				$this->logger->log(
					sprintf( 'Starting clean batch for post type "%s": %d posts found', $post_type, $results['found'] ),
					'info'
				);

				// Clean each post.
				foreach ( $posts_to_clean as $post ) {
					try {
						// Use the cleaner's clean_post method.
						$clean_result = $this->cleaner->clean_post(
							$post->id,
							$post->post_title,
							$post->post_content,
							$post->post_excerpt,
							$this->connection_name
						);

						if ( $clean_result ) {
							++$results['cleaned'];
						} else {
							++$results['failed'];
							$this->logger->log(
								sprintf( 'Failed to clean post %d (type: %s)', $post->id, $post_type ),
								'error'
							);
						}
					} catch ( Exception $e ) {
						++$results['failed'];
						$this->logger->log(
							sprintf( 'Exception cleaning post %d: %s', $post->id, $e->getMessage() ),
							'error'
						);
					}
				}

				$this->logger->log(
					sprintf(
						'Completed clean batch for post type "%s": %d/%d successful',
						$post_type,
						$results['cleaned'],
						$results['found']
					),
					'info'
				);

			} catch ( Exception $e ) {
				$this->logger->log(
					sprintf( 'Clean batch failed for post type "%s": %s', $post_type, $e->getMessage() ),
					'error'
				);

				$results['error'] = $e->getMessage();
			}

			return $results;
		}

		/**
		 * Clean all posts (all post types)
		 *
		 * Processes all public post types in sequence.
		 *
		 * @param int $batch_size Batch size per post type (default: 100).
		 * @return array {
		 *     Overall clean results.
		 *
		 *     @type int   $total_found   Total posts found needing cleaning across all types.
		 *     @type int   $total_cleaned Total successfully cleaned.
		 *     @type int   $total_failed  Total failed.
		 *     @type array $by_post_type  Results broken down by post type.
		 * }
		 */
		public function clean_all_post_types( $batch_size = 100 ) {
			// Get all public post types.
			$post_types = get_post_types( array( 'public' => true ), 'names' );

			$overall_results = array(
				'total_found'   => 0,
				'total_cleaned' => 0,
				'total_failed'  => 0,
				'by_post_type'  => array(),
			);

			$this->logger->log(
				sprintf( 'Starting clean batch for all post types: %s', implode( ', ', $post_types ) ),
				'info'
			);

			foreach ( $post_types as $post_type ) {
				$results = $this->clean_post_type( $post_type, $batch_size );

				$overall_results['total_found']               += $results['found'];
				$overall_results['total_cleaned']             += $results['cleaned'];
				$overall_results['total_failed']              += $results['failed'];
				$overall_results['by_post_type'][ $post_type ] = $results;
			}

			$this->logger->log(
				sprintf(
					'Completed clean batch for all post types: %d found, %d cleaned, %d failed',
					$overall_results['total_found'],
					$overall_results['total_cleaned'],
					$overall_results['total_failed']
				),
				'info'
			);

			return $overall_results;
		}
	}
}
