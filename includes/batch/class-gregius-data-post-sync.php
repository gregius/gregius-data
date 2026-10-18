<?php
/**
 * Post Type Synchronization Class
 *
 * Handles bulk synchronization of posts by post type.
 * Mirrors the proven Gregius_Data_Taxonomy_Sync architecture.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Gregius_Data_Post_Sync' ) ) {

	/**
	 * Post Type Synchronization class
	 */
	class Gregius_Data_Post_Sync {

		/**
		 * Connection manager (provider-aware)
		 *
		 * @var Gregius_Data_Connection_Manager
		 */
		protected $connection_manager;

		/**
		 * Database provider instance
		 *
		 * @var Gregius_Data_DB_Provider
		 */
		protected $provider;

		/**
		 * Database handler (for granular operations)
		 *
		 * @var Gregius_Data_DB
		 */
		protected $db;

		/**
		 * Logger instance
		 *
		 * @var Gregius_Data_Logger
		 */
		protected $logger;

		/**
		 * Sync metadata manager
		 *
		 * @var Gregius_Data_Sync_Metadata_Manager
		 */
		protected $metadata_manager;

		/**
		 * Content cleaner instance
		 *
		 * @var Gregius_Data_Content_Cleaner
		 */
		protected $content_cleaner;

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
			$this->connection_manager = new Gregius_Data_Connection_Manager();
			$this->db                 = new Gregius_Data_DB();
			$this->logger             = new Gregius_Data_Logger();
			$this->metadata_manager   = new Gregius_Data_Sync_Metadata_Manager( $connection_name );
			$this->content_cleaner    = new Gregius_Data_Content_Cleaner();
			$this->connection_name    = $connection_name;

			// Get the provider for this connection.
			$this->provider = $this->connection_manager->get_provider( $connection_name );

			// Set the default connection for database operations.
			$this->db->set_default_connection( $connection_name );

			// Initialize the connection to set up provider.
			$this->db->get_connection( $connection_name );
		}

		/**
		 * Ensure provider exists and is connected before strict batch operations.
		 *
		 * @param string $context Human-readable operation context.
		 * @throws Exception When provider is unavailable, misconfigured, or cannot connect.
		 * @return void
		 */
		private function ensure_provider_ready( $context ) {
			if ( ! $this->provider ) {
				$this->logger->log( 'Batch sync provider unavailable', 'error', 'sync', $this->connection_name, array( 'context' => $context ) );
				throw new Exception( 'Provider is not available for batch sync' );
			}

			$needs_connect = method_exists( $this->provider, 'is_connected' ) && ! $this->provider->is_connected();
			if ( ! $needs_connect ) {
				return;
			}

			if ( ! method_exists( $this->provider, 'connect' ) ) {
				$this->logger->log( 'Batch sync provider cannot connect', 'error', 'sync', $this->connection_name, array( 'context' => $context ) );
				throw new Exception( 'Provider cannot connect for batch sync' );
			}

			$settings_manager = new Gregius_Data_Settings_Manager();
			$connection       = $settings_manager->get_connection( $this->connection_name );

			if ( empty( $connection ) || ! is_array( $connection ) ) {
				$this->logger->log( 'Batch sync provider connection configuration missing', 'error', 'sync', $this->connection_name, array( 'context' => $context ) );
				throw new Exception( 'Provider connection configuration is missing for batch sync' );
			}

			$connect_result = $this->provider->connect( $connection );
			if ( ! is_array( $connect_result ) || empty( $connect_result['success'] ) ) {
				$message = is_array( $connect_result ) && ! empty( $connect_result['message'] )
					? $connect_result['message']
					: 'Unknown connection error';
				$this->logger->log(
					'Batch sync provider connection failed',
					'error',
					'sync',
					$this->connection_name,
					array(
						'context' => $context,
						'message' => $message,
					)
				);
				throw new Exception( 'Provider connection failed for batch sync' );
			}
		}

		/**
		 * Upsert post using provider or fallback to DB
		 *
		 * @param object $post            Post object.
		 * @param string $connection_name Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_post_with_provider( $post, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_post' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_post( $post, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_post( $post, $connection_name );
		}

		/**
		 * Upsert post meta using provider or fallback to DB
		 *
		 * @param int    $meta_id         Meta ID.
		 * @param int    $post_id         Post ID.
		 * @param string $meta_key        Meta key.
		 * @param mixed  $meta_value      Meta value.
		 * @param string $connection_name Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_post_meta_with_provider( $meta_id, $post_id, $meta_key, $meta_value, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_post_meta' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $connection_name );
		}

		/**
		 * Upsert term using provider or fallback to DB
		 *
		 * @param object $term            Term object.
		 * @param string $connection_name Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_term_with_provider( $term, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_term' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_term( $term, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_term( $term, $connection_name );
		}

		/**
		 * Upsert term taxonomy using provider or fallback to DB
		 *
		 * @param int    $term_taxonomy_id Term taxonomy ID.
		 * @param int    $term_id          Term ID.
		 * @param string $taxonomy         Taxonomy name.
		 * @param string $description      Description.
		 * @param int    $parent_term      Parent term ID.
		 * @param int    $count            Term count.
		 * @param string $connection_name  Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_term_taxonomy_with_provider( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_term_taxonomy' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $connection_name );
		}

		/**
		 * Upsert term relationship using provider or fallback to DB
		 *
		 * @param int    $object_id        Object ID (post ID).
		 * @param int    $term_taxonomy_id Term taxonomy ID.
		 * @param int    $term_order       Term order.
		 * @param string $connection_name  Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_term_relationship_with_provider( $object_id, $term_taxonomy_id, $term_order, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_term_relationship' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_term_relationship( $object_id, $term_taxonomy_id, $term_order, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_term_relationship( $object_id, $term_taxonomy_id, $term_order, $connection_name );
		}

		/**
		 * Check if a post row exists in PostgreSQL.
		 *
		 * Used to detect stale sync metadata where a post is marked synced,
		 * but the actual row is missing in PostgreSQL.
		 *
		 * @param int $post_id Post ID.
		 * @return bool True when post row exists, false otherwise.
		 */
		protected function post_exists_in_postgresql( $post_id ) {
			$conn = $this->db->get_connection( $this->connection_name );
			if ( ! $conn ) {
				return false;
			}

			$table_name = $this->db->get_table_name( 'posts' );
			try {
				$stmt = $conn->prepare( "SELECT 1 FROM {$table_name} WHERE id = :id LIMIT 1" );
				$stmt->execute( array( ':id' => (int) $post_id ) );
				return false !== $stmt->fetchColumn();
			} catch ( Exception $e ) {
				$this->logger->log(
					sprintf( 'Post existence check failed for post %d: %s', $post_id, $e->getMessage() ),
					'warning',
					'sync',
					$this->connection_name
				);
				return false;
			}
		}

		/**
		 * Batch synchronize posts of a specific post type
		 *
		 * Mirrors Gregius_Data_Taxonomy_Sync::batch_sync_terms() pattern for consistency.
		 * Uses pagination instead of loading all posts at once for memory efficiency.
		 *
		 * @param string $post_type  Post type slug (post, page, product, etc.).
		 * @param int    $batch_size Number of posts to process per batch (default: 100).
		 * @param int    $offset     Starting offset for pagination (default: 0).
		 * @throws Exception When provider contract validation or bulk sync operations fail.
		 * @return array {
		 *     Batch sync results.
		 *
		 *     @type bool   $success   Whether the batch completed successfully.
		 *     @type int    $processed Number of posts synced in this batch.
		 *     @type int    $skipped   Number of posts skipped (already synced).
		 *     @type int    $failed    Number of posts that failed to sync.
		 *     @type bool   $has_more  Whether more batches remain to process.
		 *     @type int    $total     Total posts matching criteria.
		 * }
		 */
		public function batch_sync_post_type( $post_type, $batch_size = 100, $offset = 0 ) {
			global $wpdb;

			/**
			 * Filter the batch size for post sync operations.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $batch_size      Number of posts to sync per batch. Default 100.
			 * @param string $connection_name PostgreSQL connection name.
			 */
			$batch_size = apply_filters( 'gregius_data_post_sync_batch_size', $batch_size, $this->connection_name );

			$result = array(
				'success'   => true,
				'processed' => 0,
				'skipped'   => 0,
				'failed'    => 0,
				'has_more'  => false,
				'total'     => 0,
			);

			if ( ! post_type_exists( $post_type ) ) {
				$this->logger->log(
					sprintf( 'Post type "%s" does not exist', $post_type ),
					'error'
				);
				$result['success'] = false;
				$result['message'] = 'Post type does not exist';
				return $result;
			}

			try {
				$settings_manager = new Gregius_Data_Settings_Manager();
				$enabled_statuses = $settings_manager->get_with_category(
					'sync',
					$this->connection_name,
					'sync_enabled_statuses',
					array( 'publish' )
				);
				if ( is_string( $enabled_statuses ) ) {
					$enabled_statuses = maybe_unserialize( $enabled_statuses );
				}

				$enabled_post_types = $settings_manager->get_with_category(
					'sync',
					$this->connection_name,
					'sync_enabled_post_types',
					array()
				);
				if ( is_string( $enabled_post_types ) ) {
					$enabled_post_types = maybe_unserialize( $enabled_post_types );
				}
				if ( ! is_array( $enabled_post_types ) ) {
					$enabled_post_types = array();
				}

				if ( empty( $enabled_statuses ) || ! is_array( $enabled_statuses ) ) {
					$result['processed'] = 0;
					$result['total']     = 0;
					return $result;
				}

				$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

				if ( 'attachment' === $post_type ) {
					// Attachments are gated by the eligibility rule (mirrors
					// Gregius_Data_Content_Source::is_eligible_attachment): inherit +
					// parent (non-attachment) of an enabled type/status + pdf + not featured.
					$type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );

					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to count/fetch eligible attachments by parent type/status. Placeholders are dynamic.
					$count_query     = $wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} a
						 INNER JOIN {$wpdb->posts} p ON a.post_parent = p.ID
						 WHERE a.post_type = 'attachment'
						   AND a.post_status = 'inherit'
						   AND p.post_type <> 'attachment'
						   AND p.post_type IN ($type_placeholders)
						   AND p.post_status IN ($status_placeholders)
						   AND a.post_mime_type = 'application/pdf'
						   AND a.ID <> COALESCE((
						       SELECT CAST(pm.meta_value AS UNSIGNED)
						       FROM {$wpdb->postmeta} pm
						       WHERE pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
						       LIMIT 1
						   ), 0)",
						array_merge( $enabled_post_types, $enabled_statuses )
					);
					$total_count     = (int) $wpdb->get_var( $count_query );
					$result['total'] = $total_count;

					$query = $wpdb->prepare(
						"SELECT a.* FROM {$wpdb->posts} a
						 INNER JOIN {$wpdb->posts} p ON a.post_parent = p.ID
						 WHERE a.post_type = 'attachment'
						   AND a.post_status = 'inherit'
						   AND p.post_type <> 'attachment'
						   AND p.post_type IN ($type_placeholders)
						   AND p.post_status IN ($status_placeholders)
						   AND a.post_mime_type = 'application/pdf'
						   AND a.ID <> COALESCE((
						       SELECT CAST(pm.meta_value AS UNSIGNED)
						       FROM {$wpdb->postmeta} pm
						       WHERE pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
						       LIMIT 1
						   ), 0)
						 ORDER BY a.ID ASC
						 LIMIT %d OFFSET %d",
						array_merge( $enabled_post_types, $enabled_statuses, array( $batch_size, $offset ) )
					);
					$posts = $wpdb->get_results( $query );
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				} else {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to count posts by type/status for batch sync progress tracking. Placeholders are dynamic.
					$count_query     = $wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_status IN ($status_placeholders)",
						array_merge( array( $post_type ), $enabled_statuses )
					);
					$total_count     = (int) $wpdb->get_var( $count_query );
					$result['total'] = $total_count;

					$query = $wpdb->prepare(
						"SELECT * FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_status IN ($status_placeholders)
						ORDER BY ID ASC
						LIMIT %d OFFSET %d",
						array_merge( array( $post_type ), $enabled_statuses, array( $batch_size, $offset ) )
					);
					$posts = $wpdb->get_results( $query );
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				}

				$this->ensure_provider_ready( 'batch post sync' );

				$required_methods = array(
					'bulk_upsert_posts',
					'bulk_upsert_postmeta',
					'bulk_upsert_term_relationships',
				);

				foreach ( $required_methods as $required_method ) {
					if ( ! method_exists( $this->provider, $required_method ) ) {
						throw new Exception( sprintf( 'Provider contract violation: missing %s()', $required_method ) );
					}
				}

				$posts_to_sync = array();
				foreach ( $posts as $post ) {
					if ( $this->metadata_manager->is_entity_synced_in_postgresql( 'post', $post->ID ) && ! $this->metadata_manager->needs_resync( 'post', $post->ID, $post->post_modified_gmt ) ) {
						if ( $this->post_exists_in_postgresql( $post->ID ) ) {
							++$result['skipped'];
							continue;
						}

						$this->logger->log(
							sprintf( 'Stale sync metadata detected for post %d; forcing resync', $post->ID ),
							'info',
							'sync',
							$this->connection_name
						);
					}

					$posts_to_sync[] = $post;
				}

				if ( ! empty( $posts_to_sync ) ) {
					$bulk_result = $this->provider->bulk_upsert_posts( $posts_to_sync, $this->connection_name );
					if ( empty( $bulk_result['success'] ) ) {
						$error_msg = isset( $bulk_result['error'] ) ? $bulk_result['error'] : 'Unknown error';
						throw new Exception( 'Bulk post upsert failed: ' . $error_msg );
					}

					$expected_count  = count( $posts_to_sync );
					$processed_count = isset( $bulk_result['count'] ) ? (int) $bulk_result['count'] : 0;
					if ( $processed_count !== $expected_count ) {
						throw new Exception( sprintf( 'Bulk post upsert count mismatch: expected %d, got %d', $expected_count, $processed_count ) );
					}

					$result['processed'] = $processed_count;

					$post_ids = array_map(
						function ( $post ) {
							return $post->ID;
						},
						$posts_to_sync
					);

					$meta_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to fetch all postmeta for batch of posts for bulk sync operation. Placeholders are dynamic.
					$meta_query   = $wpdb->prepare(
						"SELECT * FROM {$wpdb->postmeta}
						WHERE post_id IN ($meta_placeholders)
						ORDER BY meta_id ASC",
						$post_ids
					);
					$all_postmeta = $wpdb->get_results( $meta_query );
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

					if ( ! empty( $all_postmeta ) ) {
						$meta_result = $this->provider->bulk_upsert_postmeta( $all_postmeta, $this->connection_name );
						if ( empty( $meta_result['success'] ) ) {
							$error_msg = isset( $meta_result['error'] ) ? $meta_result['error'] : 'Unknown error';
							throw new Exception( 'Bulk postmeta upsert failed: ' . $error_msg );
						}
					}

					$rel_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to fetch all term relationships for batch of posts for bulk sync operation. Placeholders are dynamic.
					$rel_query         = $wpdb->prepare(
						"SELECT object_id, term_taxonomy_id, term_order
						FROM {$wpdb->term_relationships}
						WHERE object_id IN ($rel_placeholders)
						ORDER BY object_id ASC",
						$post_ids
					);
					$all_relationships = $wpdb->get_results( $rel_query );
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

					if ( ! empty( $all_relationships ) ) {
						$rel_result = $this->provider->bulk_upsert_term_relationships( $all_relationships, $this->connection_name );
						if ( empty( $rel_result['success'] ) ) {
							$error_msg = isset( $rel_result['error'] ) ? $rel_result['error'] : 'Unknown error';
							throw new Exception( 'Bulk term relationships upsert failed: ' . $error_msg );
						}
					}

					if ( 'attachment' === $post_type ) {
						// Attachments extract via clean_post (the content-source resolver).
						// Wrap each in try/catch so one item's failure doesn't abort the batch.
						foreach ( $posts_to_sync as $post ) {
							try {
								$this->content_cleaner->clean_post( $post->ID, $post->post_title, '', $post->post_excerpt, $this->connection_name );
							} catch ( Exception $clean_error ) {
								$this->logger->log(
									sprintf( 'Attachment %d clean failed: %s', $post->ID, $clean_error->getMessage() ),
									'warning',
									'sync',
									$this->connection_name,
									array( 'post_id' => $post->ID )
								);
							}
						}
					} else {
						$clean_result = $this->content_cleaner->bulk_clean_posts( $posts_to_sync, $this->connection_name );
						if ( ! empty( $clean_result['errors'] ) ) {
							throw new Exception( 'Bulk clean failed: ' . implode( '; ', $clean_result['errors'] ) );
						}
					}

					foreach ( $posts_to_sync as $post ) {
						$this->metadata_manager->mark_synced_in_postgresql(
							'post',
							$post->ID,
							array(
								'post_type'          => $post->post_type,
								'source_modified_at' => $post->post_modified_gmt,
							)
						);
					}
				}

				$result['has_more'] = ( $offset + $batch_size ) < $total_count;

				if ( ! $result['has_more'] ) {
					$this->metadata_manager->update_sync_stats( 'post', $post_type );
					$this->metadata_manager->update_sync_stats( 'postmeta' );
					$this->metadata_manager->update_sync_stats( 'term_relationships' );
				}
			} catch ( Exception $e ) {
				$this->logger->log( 'Gregius_Data_Post_Sync: Batch sync error - ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
				$result['success'] = false;
				$result['message'] = $e->getMessage();
			}

			return $result;
		}

		/**
		 * Sync all postmeta for a specific post
		 *
		 * @param int $post_id Post ID.
		 * @return int Number of postmeta synced.
		 */
		private function sync_postmeta_for_post( $post_id ) {
			global $wpdb;

			// Get all postmeta from WordPress (source of truth).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all postmeta for a specific post for sync operation
			$postmeta = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, post_id, meta_key, meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id = %d",
					$post_id
				)
			);
			$synced   = 0;

			foreach ( $postmeta as $meta ) {
				// Skip internal WordPress meta keys.
				if ( $this->should_skip_meta_key( $meta->meta_key ) ) {
					continue;
				}

				try {
					$result = $this->upsert_post_meta_with_provider(
						$meta->meta_id,
						$meta->post_id,
						$meta->meta_key,
						$meta->meta_value,
						$this->connection_name
					);

					if ( $result ) {
						++$synced;
					}
				} catch ( Exception $e ) {
					$this->logger->log(
						sprintf(
							'Gregius_Data_Post_Sync: Failed to sync postmeta %d for post %d: %s',
							$meta->meta_id,
							$post_id,
							$e->getMessage()
						),
						'error'
					);
				}
			}

			return $synced;
		}

		/**
		 * Check if meta key should be skipped during sync
		 *
		 * @param string $meta_key Meta key to check.
		 * @return bool True if should skip.
		 */
		private function should_skip_meta_key( $meta_key ) {
			// Skip internal WordPress meta keys (must match API filtering for consistency).
			// These are WordPress internal/transient keys that shouldn't be synced.
			$skipped_keys = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_old_date',
				'_encloseme',
				'_pingme',
				'_wp_trash_meta_time',
				'_wp_trash_meta_status',
				'_wp_desired_post_slug',
			);

			// Check exact matches.
			if ( in_array( $meta_key, $skipped_keys, true ) ) {
				return true;
			}

			// Check transient patterns.
			if ( strpos( $meta_key, '_transient' ) === 0 || strpos( $meta_key, '_site_transient' ) === 0 ) {
				return true;
			}

			return false;
		}

		/**
		 * Sync term relationships for a specific post
		 *
		 * @param int $post_id Post ID.
		 * @return int Number of relationships synced.
		 */
		private function sync_term_relationships( $post_id ) {
			global $wpdb;

			// Get all term relationships for this post from WordPress.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all term relationships for a specific post for sync operation
			$relationships = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT object_id, term_taxonomy_id, term_order
					FROM {$wpdb->term_relationships}
					WHERE object_id = %d",
					$post_id
				)
			);
			$synced        = 0;

			foreach ( $relationships as $rel ) {
				try {
					// Ensure term exists in PostgreSQL first.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch term data by term_taxonomy_id for relationship sync
					$term_data = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT t.term_id, t.name, t.slug, t.term_group,
						tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count
						FROM {$wpdb->term_taxonomy} tt
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE tt.term_taxonomy_id = %d",
							$rel->term_taxonomy_id
						)
					);

					if ( $term_data ) {
							// Sync term first (idempotent).
							$term_result = $this->upsert_term_with_provider(
								$term_data,
								$this->connection_name
							);

							// Sync term_taxonomy (idempotent).
						if ( $term_result ) {
							$taxonomy_result = $this->upsert_term_taxonomy_with_provider(
								$term_data->term_taxonomy_id,
								$term_data->term_id,
								$term_data->taxonomy,
								$term_data->description,
								$term_data->parent,
								$term_data->count,
								$this->connection_name
							);

							// Sync relationship.
							if ( $taxonomy_result ) {
								$result = $this->upsert_term_relationship_with_provider(
									$rel->object_id,
									$rel->term_taxonomy_id,
									$rel->term_order,
									$this->connection_name
								);

								if ( $result ) {
									++$synced;
								}
							}
						}
					}
				} catch ( Exception $e ) {
					$this->logger->log(
						sprintf(
							'Gregius_Data_Post_Sync: Failed to sync relationship for post %d, term_taxonomy %d: %s',
							$post_id,
							$rel->term_taxonomy_id,
							$e->getMessage()
						),
						'error'
					);
				}
			}

			return $synced;
		}
	}
}
