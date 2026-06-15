<?php
/**
 * Post Type Synchronization Class
 *
 * Handles bulk synchronization of posts by post type.
 * Mirrors the proven GG_Data_Taxonomy_Sync architecture.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'GG_Data_Post_Sync' ) ) {

	/**
	 * Post Type Synchronization class
	 */
	class GG_Data_Post_Sync {

		/**
		 * Connection manager (provider-aware)
		 *
		 * @var GG_Data_Connection_Manager
		 */
		protected $connection_manager;

		/**
		 * Database provider instance
		 *
		 * @var GG_Data_DB_Provider
		 */
		protected $provider;

		/**
		 * Database handler (for granular operations)
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
		 * Sync metadata manager
		 *
		 * @var GG_Data_Sync_Metadata_Manager
		 */
		protected $metadata_manager;

		/**
		 * Content cleaner instance
		 *
		 * @var GG_Data_Content_Cleaner
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
			$this->connection_manager = new GG_Data_Connection_Manager();
			$this->db                 = new GG_Data_DB();
			$this->logger             = new GG_Data_Logger();
			$this->metadata_manager   = new GG_Data_Sync_Metadata_Manager( $connection_name );
			$this->content_cleaner    = new GG_Data_Content_Cleaner();
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

			$settings_manager = new GG_Data_Settings_Manager();
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
		 * @param int    $site_id         Site ID.
		 * @param string $connection_name Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_post_with_provider( $post, $site_id, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_post' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_post( $post, $site_id, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_post( $post, $site_id, $connection_name );
		}

		/**
		 * Upsert post meta using provider or fallback to DB
		 *
		 * @param int    $meta_id         Meta ID.
		 * @param int    $post_id         Post ID.
		 * @param string $meta_key        Meta key.
		 * @param mixed  $meta_value      Meta value.
		 * @param int    $site_id         Site ID.
		 * @param string $connection_name Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_post_meta_with_provider( $meta_id, $post_id, $meta_key, $meta_value, $site_id, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_post_meta' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $site_id, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $site_id, $connection_name );
		}

		/**
		 * Upsert term using provider or fallback to DB
		 *
		 * @param object $term            Term object.
		 * @param int    $site_id         Site ID.
		 * @param string $connection_name Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_term_with_provider( $term, $site_id, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_term' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_term( $term, $site_id, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_term( $term, $site_id, $connection_name );
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
		 * @param int    $site_id          Site ID.
		 * @param string $connection_name  Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_term_taxonomy_with_provider( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $site_id, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_term_taxonomy' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $site_id, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $site_id, $connection_name );
		}

		/**
		 * Upsert term relationship using provider or fallback to DB
		 *
		 * @param int    $object_id        Object ID (post ID).
		 * @param int    $term_taxonomy_id Term taxonomy ID.
		 * @param int    $term_order       Term order.
		 * @param int    $site_id          Site ID.
		 * @param string $connection_name  Connection name.
		 * @return bool Success status.
		 */
		protected function upsert_term_relationship_with_provider( $object_id, $term_taxonomy_id, $term_order, $site_id, $connection_name ) {
			if ( $this->provider && method_exists( $this->provider, 'upsert_term_relationship' ) ) {
				$provider_connected = ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected();
				if ( $provider_connected ) {
					$provider_result = $this->provider->upsert_term_relationship( $object_id, $term_taxonomy_id, $term_order, $site_id, $connection_name );
					if ( $provider_result ) {
						return true;
					}
				}
			}
			return $this->db->upsert_term_relationship( $object_id, $term_taxonomy_id, $term_order, $site_id, $connection_name );
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
		 * Mirrors GG_Data_Taxonomy_Sync::batch_sync_terms() pattern for consistency.
		 * Uses pagination instead of loading all posts at once for memory efficiency.
		 *
		 * @param string $post_type  Post type slug (post, page, product, etc.).
		 * @param int    $batch_size Number of posts to process per batch (default: 100).
		 * @param int    $offset     Starting offset for pagination (default: 0).
		 * @param int    $site_id    Site ID for multisite (default: 1).
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
		public function batch_sync_post_type( $post_type, $batch_size = 100, $offset = 0, $site_id = 1 ) {
			global $wpdb;

			/**
			 * Filter the batch size for post sync operations.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $batch_size      Number of posts to sync per batch. Default 100.
			 * @param string $connection_name PostgreSQL connection name.
			 */
			$batch_size = apply_filters( 'gg_data_post_sync_batch_size', $batch_size, $this->connection_name );

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
				$settings_manager = new GG_Data_Settings_Manager();
				$enabled_statuses = $settings_manager->get_with_category(
					'sync',
					$this->connection_name,
					'sync_enabled_statuses',
					array( 'publish' )
				);
				if ( is_string( $enabled_statuses ) ) {
					$enabled_statuses = maybe_unserialize( $enabled_statuses );
				}

				if ( empty( $enabled_statuses ) || ! is_array( $enabled_statuses ) ) {
					$result['processed'] = 0;
					$result['total']     = 0;
					return $result;
				}

				$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to count posts by type/status for batch sync progress tracking. Placeholders are dynamic.
				$count_query = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_status IN ($status_placeholders)",
					array_merge( array( $post_type ), $enabled_statuses )
				);
				$total_count = (int) $wpdb->get_var( $count_query );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$result['total'] = $total_count;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to fetch batch of posts by type/status for bulk sync operation. Placeholders are dynamic.
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
					$bulk_result = $this->provider->bulk_upsert_posts( $posts_to_sync, $site_id, $this->connection_name );
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
						$meta_result = $this->provider->bulk_upsert_postmeta( $all_postmeta, $site_id, $this->connection_name );
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
						$rel_result = $this->provider->bulk_upsert_term_relationships( $all_relationships, $site_id, $this->connection_name );
						if ( empty( $rel_result['success'] ) ) {
							$error_msg = isset( $rel_result['error'] ) ? $rel_result['error'] : 'Unknown error';
							throw new Exception( 'Bulk term relationships upsert failed: ' . $error_msg );
						}
					}

					$clean_result = $this->content_cleaner->bulk_clean_posts( $posts_to_sync, $this->connection_name );
					if ( ! empty( $clean_result['errors'] ) ) {
						throw new Exception( 'Bulk clean failed: ' . implode( '; ', $clean_result['errors'] ) );
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
				$this->logger->log( 'GG_Data_Post_Sync: Batch sync error - ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
				$result['success'] = false;
				$result['message'] = $e->getMessage();
			}

			return $result;
		}

		/**
		 * Sync all postmeta for a specific post
		 *
		 * @param int $post_id Post ID.
		 * @param int $site_id Site ID for multisite.
		 * @return int Number of postmeta synced.
		 */
		private function sync_postmeta_for_post( $post_id, $site_id = 1 ) {
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
						$site_id,
						$this->connection_name
					);

					if ( $result ) {
						++$synced;
					}
				} catch ( Exception $e ) {
					$this->logger->log(
						sprintf(
							'GG_Data_Post_Sync: Failed to sync postmeta %d for post %d: %s',
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
		 * @param int $site_id Site ID for multisite.
		 * @return int Number of relationships synced.
		 */
		private function sync_term_relationships( $post_id, $site_id = 1 ) {
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
								$site_id,
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
								$site_id,
								$this->connection_name
							);

							// Sync relationship.
							if ( $taxonomy_result ) {
								$result = $this->upsert_term_relationship_with_provider(
									$rel->object_id,
									$rel->term_taxonomy_id,
									$rel->term_order,
									$site_id,
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
							'GG_Data_Post_Sync: Failed to sync relationship for post %d, term_taxonomy %d: %s',
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
