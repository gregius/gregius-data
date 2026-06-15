<?php
/**
 * Post Metadata Synchronization Class
 *
 * Handles bulk synchronization of postmeta with smart scoping.
 * Only syncs metadata for posts that exist in PostgreSQL (relational integrity).
 * Mirrors the proven GG_Data_Post_Sync architecture.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'GG_Data_Postmeta_Sync' ) ) {

	/**
	 * Post Metadata Synchronization class
	 */
	class GG_Data_Postmeta_Sync {

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
		 * Settings Manager instance
		 *
		 * @var GG_Data_Settings_Manager
		 */
		protected $settings_manager;

		/**
		 * Sync metadata manager
		 *
		 * @var GG_Data_Sync_Metadata_Manager
		 */
		protected $metadata_manager;

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
			$this->db               = new GG_Data_DB();
			$this->logger           = new GG_Data_Logger();
			$this->settings_manager = new GG_Data_Settings_Manager();
			$this->metadata_manager = new GG_Data_Sync_Metadata_Manager( $connection_name );
			$this->connection_name  = $connection_name;

			// Set the default connection for database operations.
			$this->db->set_default_connection( $connection_name );

			// Initialize the connection to set up provider.
			$this->db->get_connection( $connection_name );
		}

		/**
		 * Batch synchronize postmeta with smart scoping
		 *
		 * Mirrors GG_Data_Post_Sync::batch_sync_post_type() pattern for consistency.
		 * Uses INNER JOIN to ensure we only sync metadata for posts that exist
		 * in PostgreSQL and match the user's configured post types and statuses.
		 * Uses pagination instead of loading all metadata at once for memory efficiency.
		 *
		 * @param int $batch_size Records per batch (default: 5000).
		 * @param int $offset     Starting offset for pagination (default: 0).
		 * @param int $site_id    Site ID for multisite (default: 1).
		 * @return array {
		 *     Batch sync results.
		 *
		 *     @type bool   $success   Whether the batch completed successfully.
		 *     @type int    $processed Number of postmeta records synced in this batch.
		 *     @type int    $skipped   Number of postmeta records skipped (already synced).
		 *     @type int    $failed    Number of postmeta records that failed to sync.
		 *     @type bool   $has_more  Whether more batches remain to process.
		 *     @type int    $total     Total postmeta records matching criteria.
		 * }
		 */
		public function batch_sync_postmeta( $batch_size = 5000, $offset = 0, $site_id = 1 ) {
			global $wpdb;

			$result = array(
				'success'   => true,
				'processed' => 0,
				'skipped'   => 0,
				'failed'    => 0,
				'has_more'  => false,
				'total'     => 0,
			);

			try {
				// Get user-configured post types and statuses from sync settings.
				$enabled_post_types = $this->settings_manager->get_with_category(
					'sync',
					$this->connection_name,
					'sync_enabled_post_types',
					array()
				);

				$enabled_statuses = $this->settings_manager->get_with_category(
					'sync',
					$this->connection_name,
					'sync_enabled_statuses',
					array( 'publish' )
				);

				if ( is_string( $enabled_post_types ) ) {
					$enabled_post_types = maybe_unserialize( $enabled_post_types );
				}
				if ( is_string( $enabled_statuses ) ) {
					$enabled_statuses = maybe_unserialize( $enabled_statuses );
				}

				if ( ! is_array( $enabled_post_types ) ) {
					$enabled_post_types = array();
				}
				if ( ! is_array( $enabled_statuses ) ) {
					$enabled_statuses = array( 'publish' );
				}

				// If no post types configured, return early.
				if ( empty( $enabled_post_types ) ) {
					$this->logger->log(
						'No post types configured for sync - skipping postmeta batch sync',
						'info'
					);
					$result['success'] = false;
					$result['message'] = 'No post types configured for sync';
					return $result;
				}

				// Build SQL IN clauses with proper escaping.
				$post_types_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
				$statuses_placeholders   = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

				// Get total count for progress tracking.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to count postmeta records for enabled post types/statuses for batch sync progress tracking. Placeholders are dynamic.
				$count_query = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type IN ($post_types_placeholders)
					AND p.post_status IN ($statuses_placeholders)",
					array_merge( $enabled_post_types, $enabled_statuses )
				);
				$total_count = (int) $wpdb->get_var( $count_query );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$result['total'] = $total_count;

				// Get batch of postmeta using direct query with INNER JOIN.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to fetch batch of postmeta records for enabled post types/statuses for bulk sync operation. Placeholders are dynamic.
				$query        = $wpdb->prepare(
					"SELECT pm.* FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type IN ($post_types_placeholders)
					AND p.post_status IN ($statuses_placeholders)
					ORDER BY pm.meta_id ASC
					LIMIT %d OFFSET %d",
					array_merge( $enabled_post_types, $enabled_statuses, array( $batch_size, $offset ) )
				);
				$meta_records = $wpdb->get_results( $query );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

				// Process each postmeta record in the batch.
				foreach ( $meta_records as $meta ) {
					// Apply filtering logic.
					if ( $this->should_skip_meta_key( $meta->meta_key ) ) {
						++$result['skipped'];
						continue;
					}

					// Skip if already synced and not modified.
					if ( $this->metadata_manager->is_entity_synced_in_postgresql( 'postmeta', $meta->meta_id ) ) {
						++$result['skipped'];
						continue;
					}

					try {
						/*
						 * Upsert to PostgreSQL.
						 * Note: meta_key and meta_value usage here is safe and performant.
						 * The query filtering happens via INNER JOIN on wp_posts (indexed by post_type/post_status).
						 * We're only reading these columns from already-filtered results, not querying BY them.
						 * No WHERE meta_key or WHERE meta_value clauses are used in the actual database query.
						 */
						$sync_result = $this->db->upsert_post_meta(
							array(
								'meta_id'    => $meta->meta_id,
								'post_id'    => $meta->post_id,
								'meta_key'   => $meta->meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Safe: Reading column from JOIN-filtered results.
								'meta_value' => $meta->meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Safe: Reading column from JOIN-filtered results.
							),
							$site_id,
							null,
							null,
							1,
							$this->connection_name
						);                      if ( false !== $sync_result ) {
								++$result['processed'];
								// Mark as synced.
								$this->metadata_manager->mark_synced_in_postgresql(
									'postmeta',
									$meta->meta_id,
									array( 'source_id' => $meta->post_id )
								);
						} else {
							++$result['failed'];
							$this->logger->log(
								sprintf( 'Failed to sync meta_id %d', $meta->meta_id ),
								'error'
							);
						}
					} catch ( Exception $e ) {
						++$result['failed'];
						$this->logger->log(
							sprintf( 'Exception syncing meta_id %d: %s', $meta->meta_id, $e->getMessage() ),
							'error'
						);
					}
				}

				// Determine if more records remain.
				$result['has_more'] = ( $offset + $batch_size ) < $total_count;

				// Update aggregate stats when batch completes.
				if ( ! $result['has_more'] ) {
					$this->metadata_manager->update_sync_stats( 'postmeta' );
				}
			} catch ( Exception $e ) {
				$this->logger->log( 'GG_Data_Postmeta_Sync: Batch sync error - ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
				$result['success'] = false;
				$result['message'] = $e->getMessage();
			}

			return $result;
		}

		/**
		 * Mirror filtering logic from GG_Data_Lifecycle_Hooks
		 * Reuse should_skip_meta_key() patterns to maintain consistency
		 *
		 * @param string $meta_key Meta key to check.
		 * @return bool True if should skip, false otherwise.
		 */
		private function should_skip_meta_key( $meta_key ) {
			// Skip WordPress internal/transient keys only.
			// For search/AI use cases, we want to sync most metadata.
			$skip_keys = array(
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

			if ( in_array( $meta_key, $skip_keys, true ) ) {
				return true;
			}

			// Skip transient cache keys.
			if ( strpos( $meta_key, '_transient' ) === 0 || strpos( $meta_key, '_site_transient' ) === 0 ) {
				return true;
			}

			// Sync everything else (including SEO, ACF, custom fields, etc.).
			return false;
		}
	}
}
