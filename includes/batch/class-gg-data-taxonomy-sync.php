<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Taxonomy Synchronization Class
 *
 * Handles proper synchronization of taxonomy data in the correct order:
 * 1. Terms
 * 2. Term Taxonomies
 * 3. Term Relationships
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'GG_Data_Taxonomy_Sync' ) ) {

	/**
	 * Taxonomy Synchronization class
	 *
	 * @since 1.0.0
	 */
	class GG_Data_Taxonomy_Sync {

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
			$this->connection_name    = $connection_name;

			// Get the provider for this connection.
			$this->provider = $this->connection_manager->get_provider( $connection_name );

			// Set the default connection for database operations.
			$this->db->set_default_connection( $connection_name );
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
		 * Synchronize a single term and its taxonomy
		 *
		 * @param int    $term_id  Term ID.
		 * @param string $taxonomy Taxonomy name.
		 * @param int    $site_id  Site ID.
		 * @return bool Success or failure.
		 */
		public function sync_single_term( $term_id, $taxonomy, $site_id = 1 ) {
			global $wpdb;

			try {
				// Get term data from MySQL.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch term data from WordPress core table for sync operation
				$term = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->terms} WHERE term_id = %d",
						$term_id
					)
				);              if ( ! $term ) {
						$this->logger->log( "Term #{$term_id} not found in MySQL", 'error', 'sync', $this->connection_name );
						return false;
				}

				// Get taxonomy data from MySQL.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch term_taxonomy data from WordPress core table for sync operation
				$term_taxonomy = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
						$term_id,
						$taxonomy
					)
				);
				if ( ! $term_taxonomy ) {
					$this->logger->log( "Term taxonomy for term #{$term_id} and taxonomy '{$taxonomy}' not found in MySQL", 'error', 'sync', $this->connection_name );
					return false;
				}

				// First, sync term.
				$term_result = $this->upsert_term_with_provider(
					$term,
					$site_id,
					$this->connection_name
				);

				if ( ! $term_result ) {
					$this->logger->log( "Failed to sync term #{$term_id}", 'error', 'sync', $this->connection_name );
					return false;
				}

					// Then, sync term taxonomy.
					$taxonomy_result = $this->upsert_term_taxonomy_with_provider(
						$term_taxonomy->term_taxonomy_id,
						$term_taxonomy->term_id,
						$term_taxonomy->taxonomy,
						$term_taxonomy->description,
						$term_taxonomy->parent,
						$term_taxonomy->count,
						$site_id,
						$this->connection_name
					);

				if ( ! $taxonomy_result ) {
					$this->logger->log( "Failed to sync term taxonomy #{$term_taxonomy->term_taxonomy_id}", 'error', 'sync', $this->connection_name );
					return false;
				}

					$this->logger->log( "Successfully synced term #{$term_id} with taxonomy {$taxonomy}", 'debug', 'sync', $this->connection_name );
					return true;

			} catch ( Exception $e ) {
				$this->logger->log( "Error syncing term #{$term_id}: " . $e->getMessage(), 'error', 'sync', $this->connection_name );
				return false;
			}
		}

		/**
		 * Synchronize term relationships for a post
		 *
		 * @param int    $post_id  Post ID.
		 * @param string $taxonomy Taxonomy name.
		 * @param int    $site_id  Site ID.
		 * @return bool Success or failure.
		 */
		public function sync_post_term_relationships( $post_id, $taxonomy, $site_id = 1 ) {
			global $wpdb;

			try {
				// First, ensure all terms and taxonomies exist.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch term relationships with taxonomy data from WordPress core tables for sync operation
				$term_relationships = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT tr.*, tt.term_id, tt.taxonomy, tt.description, tt.parent, tt.count
						FROM {$wpdb->term_relationships} tr
						JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						WHERE tr.object_id = %d AND tt.taxonomy = %s",
						$post_id,
						$taxonomy
					)
				);

				if ( empty( $term_relationships ) ) {
					$this->logger->log( "No term relationships found for post #{$post_id} and taxonomy '{$taxonomy}'", 'debug', 'sync', $this->connection_name );
					return true;
				}

				// First, sync all terms and taxonomies.
				foreach ( $term_relationships as $rel ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch term data from WordPress core table for relationship sync
					$term = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->terms} WHERE term_id = %d",
							$rel->term_id
						)
					);
					if ( ! $term ) {
						$this->logger->log( "Term #{$rel->term_id} not found in MySQL", 'warning', 'sync', $this->connection_name );
						continue;
					}

					// Sync term.
					$this->upsert_term_with_provider(
						$term,
						$site_id,
						$this->connection_name
					);

						// Sync term taxonomy.
						$this->upsert_term_taxonomy_with_provider(
							$rel->term_taxonomy_id,
							$rel->term_id,
							$rel->taxonomy,
							$rel->description,
							$rel->parent,
							$rel->count,
							$site_id,
							$this->connection_name
						);
				}

				// Now, sync all term relationships.
				foreach ( $term_relationships as $rel ) {
					$this->upsert_term_relationship_with_provider(
						$rel->object_id,
						$rel->term_taxonomy_id,
						$rel->term_order,
						$site_id,
						$this->connection_name
					);
				}

				$this->logger->log( "Successfully synced term relationships for post #{$post_id} and taxonomy '{$taxonomy}'", 'debug', 'sync', $this->connection_name );
				return true;

			} catch ( Exception $e ) {
				$this->logger->log( "Error syncing term relationships for post #{$post_id} and taxonomy '{$taxonomy}': " . $e->getMessage(), 'error', 'sync', $this->connection_name );
				return false;
			}
		}

		/**
		 * Run a full synchronization of all taxonomy data
		 *
		 * @param int $site_id Site ID.
		 * @return array Status report.
		 */
		public function full_sync( $site_id = 1 ) {
			global $wpdb;

			$report = array(
				'terms'              => array(
					'total'   => 0,
					'success' => 0,
					'failed'  => 0,
				),
				'term_taxonomies'    => array(
					'total'   => 0,
					'success' => 0,
					'failed'  => 0,
				),
				'term_relationships' => array(
					'total'   => 0,
					'success' => 0,
					'failed'  => 0,
					'skipped' => 0,
				),
			);

			// Step 1: Sync all terms.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all terms from WordPress core table for full sync operation
			$terms                    = $wpdb->get_results( "SELECT * FROM {$wpdb->terms}" );
			$report['terms']['total'] = count( $terms );

			foreach ( $terms as $term ) {
				$result = $this->upsert_term_with_provider( $term, $site_id, $this->connection_name );

				if ( $result ) {
					++$report['terms']['success'];
				} else {
					++$report['terms']['failed'];
				}
			}

			// Step 2: Sync all term taxonomies.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all term_taxonomies from WordPress core table for full sync operation
			$term_taxonomies                    = $wpdb->get_results( "SELECT * FROM {$wpdb->term_taxonomy}" );
			$report['term_taxonomies']['total'] = count( $term_taxonomies );            foreach ( $term_taxonomies as $tax ) {
				$result = $this->upsert_term_taxonomy_with_provider(
					$tax->term_taxonomy_id,
					$tax->term_id,
					$tax->taxonomy,
					$tax->description,
					$tax->parent,
					$tax->count,
					$site_id,
					$this->connection_name
				);

				if ( $result ) {
					++$report['term_taxonomies']['success'];
				} else {
					++$report['term_taxonomies']['failed'];
				}
			}

			// Step 3: Skip term relationships - handled per-post during individual sync.
			$report['term_relationships']['total']   = 0;
			$report['term_relationships']['success'] = 0;
			$report['term_relationships']['failed']  = 0;
			$report['term_relationships']['skipped'] = 'all';
			$report['term_relationships']['note']    = 'Term relationships synced individually with each post for referential integrity';

			return $report;
		}

		/**
		 * Sync terms only (for debugging individual components)
		 *
		 * @param int $site_id Site ID.
		 * @return array Sync results.
		 */
		public function sync_terms( $site_id = 1 ) {
			global $wpdb;

			$report = array(
				'total'   => 0,
				'success' => 0,
				'failed'  => 0,
				'skipped' => 0,
			);

			// Sync all terms.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all terms from WordPress core table for incremental sync operation
			$terms           = $wpdb->get_results( "SELECT * FROM {$wpdb->terms}" );
			$report['total'] = count( $terms );
			foreach ( $terms as $term ) {
				// Terms don't have a native modified timestamp, so we pass null.
				// This means terms will be skipped if already synced (existence check only).
				if ( ! $this->metadata_manager->needs_resync( 'term', $term->term_id, null ) ) {
					++$report['skipped'];
					continue;
				}

				$result = $this->upsert_term_with_provider( $term, $site_id, $this->connection_name );

				if ( $result ) {
					++$report['success'];
					// Mark as synced in PostgreSQL metadata.
					$this->metadata_manager->mark_synced_in_postgresql( 'term', $term->term_id );
				} else {
					++$report['failed'];
				}
			}

			return $report;
		}

		/**
		 * Sync term taxonomies only (for debugging individual components)
		 *
		 * @param int $site_id Site ID.
		 * @return array Sync results.
		 */
		public function sync_term_taxonomies( $site_id = 1 ) {
			global $wpdb;

			$report = array(
				'total'   => 0,
				'success' => 0,
				'failed'  => 0,
				'skipped' => 0,
			);

			// Sync all term taxonomies.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all term_taxonomies from WordPress core table for incremental sync operation
			$term_taxonomies = $wpdb->get_results( "SELECT * FROM {$wpdb->term_taxonomy}" );
			$report['total'] = count( $term_taxonomies );
			foreach ( $term_taxonomies as $tax ) {
				// Term taxonomies don't have a native modified timestamp, so we pass null.
				if ( ! $this->metadata_manager->needs_resync( 'term_taxonomy', $tax->term_taxonomy_id, null ) ) {
					++$report['skipped'];
					continue;
				}

				$result = $this->upsert_term_taxonomy_with_provider(
					$tax->term_taxonomy_id,
					$tax->term_id,
					$tax->taxonomy,
					$tax->description,
					$tax->parent,
					$tax->count,
					$site_id,
					$this->connection_name
				);

				if ( $result ) {
					++$report['success'];
					// Mark as synced in PostgreSQL metadata.
					$this->metadata_manager->mark_synced_in_postgresql( 'term_taxonomy', $tax->term_taxonomy_id );
				} else {
					++$report['failed'];
				}
			}

			return $report;
		}

		/**
		 * Sync term relationships only for enabled post types and statuses
		 *
		 * Filters relationships to only include posts that match the enabled
		 * post types and statuses configured in settings. This prevents orphaned
		 * relationships and ensures data consistency between WordPress and PostgreSQL.
		 *
		 * @param int $site_id Site ID.
		 * @return array Sync results.
		 */
		public function sync_term_relationships( $site_id = 1 ) {
			global $wpdb;

			$report = array(
				'total'   => 0,
				'success' => 0,
				'failed'  => 0,
				'skipped' => 0,
			);

			// Get enabled post types and statuses from settings.
			$settings_manager   = new GG_Data_Settings_Manager();
			$enabled_post_types = $settings_manager->get_with_category( 'sync', $this->connection_name, 'sync_enabled_post_types', array( 'post', 'page' ) );
			$enabled_statuses   = $settings_manager->get_with_category( 'sync', $this->connection_name, 'sync_enabled_statuses', array( 'publish' ) );

			// Build placeholders for IN clauses.
			$post_type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
			$status_placeholders    = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

			// Query only relationships for enabled post types and statuses.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared -- Required to fetch all term relationships for enabled post types/statuses for incremental sync operation. Placeholders are dynamic.
			$query = $wpdb->prepare(
				"SELECT tr.* 
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE p.post_type IN ($post_type_placeholders)
			  	AND p.post_status IN ($status_placeholders)",
				array_merge( $enabled_post_types, $enabled_statuses )
			);

			$relationships = $wpdb->get_results( $query );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
			$report['total'] = count( $relationships );

			foreach ( $relationships as $rel ) {
				// Term relationships don't have a native modified timestamp.
				$composite_id = $rel->object_id . '-' . $rel->term_taxonomy_id;
				if ( ! $this->metadata_manager->needs_resync( 'term_relationship', $composite_id, null ) ) {
					++$report['skipped'];
					continue;
				}

				$result = $this->upsert_term_relationship_with_provider(
					$rel->object_id,
					$rel->term_taxonomy_id,
					$rel->term_order,
					$site_id,
					$this->connection_name
				);

				if ( $result ) {
					++$report['success'];
					// Mark as synced in PostgreSQL metadata.
					$this->metadata_manager->mark_synced_in_postgresql( 'term_relationship', $composite_id );
				} else {
					++$report['failed'];
				}
			}

			return $report;
		}

		/**
		 * Sync all term_taxonomy records for a specific term (pattern)
		 *
		 * @param int $term_id Term ID.
		 * @return int Number of term_taxonomy records synced.
		 */
		private function sync_term_taxonomies_for_term( $term_id ) {
			global $wpdb;

			// Get all term_taxonomy records for this term from WordPress (source of truth).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all term_taxonomy records for a specific term for sync operation
			$term_taxonomies = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->term_taxonomy} WHERE term_id = %d",
					$term_id
				)
			);
			$synced          = 0;

			foreach ( $term_taxonomies as $tax ) {
				try {
					$result = $this->upsert_term_taxonomy_with_provider(
						$tax->term_taxonomy_id,
						$tax->term_id,
						$tax->taxonomy,
						$tax->description,
						$tax->parent,
						$tax->count,
						1,
						$this->connection_name
					);

					if ( $result ) {
						++$synced;
						// Mark term_taxonomy as synced.
						$this->metadata_manager->mark_synced_in_postgresql( 'term_taxonomy', $tax->term_taxonomy_id );
					}
				} catch ( Exception $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical: Log term_taxonomy sync failures for production debugging
					error_log(
						sprintf(
							'GG_Data_Taxonomy_Sync: Failed to sync term_taxonomy %d for term %d: %s',
							$tax->term_taxonomy_id,
							$term_id,
							$e->getMessage()
						)
					);
				}
			}

			return $synced;
		}

		/**
		 * Batch sync terms (Vector-style batch processing)
		 *
		 * Processes a fixed batch of terms with intelligent skip logic.
		 * Returns progress metadata for frontend loop control.
		 *
		 * @param int $batch_size Number of records to process (default 500).
		 * @param int $offset Starting offset for pagination (default 0).
		 * @throws Exception When provider contract validation or bulk sync fails.
		 * @return array {
		 *     Batch processing results.
		 *
		 *     @type bool   $success    Whether batch completed successfully.
		 *     @type int    $processed  Records actually synced.
		 *     @type int    $skipped    Records skipped (already synced).
		 *     @type int    $failed     Records that failed to sync.
		 *     @type bool   $has_more   Whether more records remain.
		 *     @type int    $total      Total number of records to sync.
		 *     @type string $message    Status message (optional).
		 * }
		 */
		public function batch_sync_terms( $batch_size = 500, $offset = 0 ) {
			global $wpdb;

			/**
			 * Filter the batch size for term sync operations.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $batch_size      Number of terms to sync per batch. Default 500.
			 * @param string $connection_name PostgreSQL connection name.
			 */
			$batch_size = apply_filters( 'gg_data_term_sync_batch_size', $batch_size, $this->connection_name );

			$result      = array(
				'success'   => true,
				'processed' => 0,
				'skipped'   => 0,
				'failed'    => 0,
				'has_more'  => false,
				'total'     => 0,
			);
			$total_count = 0;

			try {
				// Get total count for progress tracking.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all terms from WordPress core table for batch sync progress tracking
				$total_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );
				$result['total'] = $total_count; // Get batch of terms.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch batch of terms from WordPress core table for bulk sync operation
				$terms = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->terms} ORDER BY term_id LIMIT %d OFFSET %d",
						$batch_size,
						$offset
					)
				);

				$this->ensure_provider_ready( 'batch term sync' );

				$required_methods = array(
					'bulk_upsert_terms',
					'bulk_upsert_term_taxonomies',
				);

				foreach ( $required_methods as $required_method ) {
					if ( ! method_exists( $this->provider, $required_method ) ) {
						throw new Exception( sprintf( 'Provider contract violation: missing %s()', $required_method ) );
					}
				}

				$terms_to_sync = $terms;
				if ( ! empty( $terms_to_sync ) ) {
					$bulk_result = $this->provider->bulk_upsert_terms( $terms_to_sync, 1, $this->connection_name );
					if ( empty( $bulk_result['success'] ) ) {
						$error_msg = isset( $bulk_result['error'] ) ? $bulk_result['error'] : 'Unknown error';
						throw new Exception( 'Bulk term upsert failed: ' . $error_msg );
					}

					$expected_count  = count( $terms_to_sync );
					$processed_count = isset( $bulk_result['count'] ) ? (int) $bulk_result['count'] : 0;
					if ( $processed_count !== $expected_count ) {
						throw new Exception( sprintf( 'Bulk term upsert count mismatch: expected %d, got %d', $expected_count, $processed_count ) );
					}

					$result['processed'] += $processed_count;

					$term_ids             = array_map(
						function ( $term ) {
							return $term->term_id;
						},
						$terms_to_sync
					);
					$term_ids_placeholder = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Required to fetch term_taxonomy records for batch of terms for bulk sync operation. Placeholders are dynamic.
					$term_taxonomies = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->term_taxonomy} WHERE term_id IN ($term_ids_placeholder)",
							...$term_ids
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

					if ( ! empty( $term_taxonomies ) ) {
						$tax_bulk_result = $this->provider->bulk_upsert_term_taxonomies( $term_taxonomies, 1, $this->connection_name );
						if ( empty( $tax_bulk_result['success'] ) ) {
							$error_msg = isset( $tax_bulk_result['error'] ) ? $tax_bulk_result['error'] : 'Unknown error';
							throw new Exception( 'Bulk term taxonomy upsert failed: ' . $error_msg );
						}

						$expected_tax_count  = count( $term_taxonomies );
						$processed_tax_count = isset( $tax_bulk_result['count'] ) ? (int) $tax_bulk_result['count'] : 0;
						if ( $processed_tax_count !== $expected_tax_count ) {
							throw new Exception( sprintf( 'Bulk term taxonomy upsert count mismatch: expected %d, got %d', $expected_tax_count, $processed_tax_count ) );
						}

						foreach ( $term_taxonomies as $term_taxonomy ) {
							$this->metadata_manager->mark_synced_in_postgresql( 'term_taxonomy', $term_taxonomy->term_taxonomy_id );
						}
					}

					foreach ( $terms_to_sync as $term ) {
						$this->metadata_manager->mark_synced_in_postgresql( 'term', $term->term_id );
					}
				}
			} catch ( Exception $e ) {
				$result['success'] = false;
				$result['message'] = $e->getMessage();
			}

			$result['has_more'] = $result['success'] && ( $offset + $batch_size ) < $total_count;

			if ( ! $result['has_more'] ) {
				$this->metadata_manager->update_sync_stats( 'term' );
				$this->metadata_manager->update_sync_stats( 'term_taxonomy' );
			}

			return $result;
		}

		/**
		 * Batch sync term taxonomies
		 *
		 * @param int $batch_size Number of records to process.
		 * @param int $offset Starting offset for pagination.
		 * @throws Exception When provider contract validation or bulk sync fails.
		 * @return array Batch processing results.
		 */
		public function batch_sync_term_taxonomies( $batch_size = 500, $offset = 0 ) {
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
				// Get total count.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all term_taxonomy records from WordPress core table for batch sync progress tracking
				$total_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy}" );
				$result['total'] = $total_count;            // Get batch of term taxonomies.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch batch of term_taxonomy records from WordPress core table for bulk sync operation
				$term_taxonomies = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id LIMIT %d OFFSET %d",
						$batch_size,
						$offset
					)
				);

				// Filter taxonomies that need syncing.
				$taxonomies_to_sync = array();
				foreach ( $term_taxonomies as $tax ) {
					if ( $this->metadata_manager->is_entity_synced_in_postgresql( 'term_taxonomy', $tax->term_taxonomy_id ) ) {
						++$result['skipped'];
					} else {
						$taxonomies_to_sync[] = array(
							'term_taxonomy_id' => $tax->term_taxonomy_id,
							'term_id'          => $tax->term_id,
							'taxonomy'         => $tax->taxonomy,
							'description'      => $tax->description,
							'parent'           => $tax->parent,
							'count'            => $tax->count,
						);
					}
				}

				$this->ensure_provider_ready( 'batch term taxonomy sync' );

				if ( ! method_exists( $this->provider, 'bulk_upsert_term_taxonomies' ) ) {
					throw new Exception( 'Provider contract violation: missing bulk_upsert_term_taxonomies()' );
				}

				if ( ! empty( $taxonomies_to_sync ) ) {
					$bulk_result = $this->provider->bulk_upsert_term_taxonomies( $taxonomies_to_sync, 1, $this->connection_name );
					if ( empty( $bulk_result['success'] ) ) {
						$error_msg = isset( $bulk_result['error'] ) ? $bulk_result['error'] : 'Unknown error';
						throw new Exception( 'Bulk term taxonomy upsert failed: ' . $error_msg );
					}

					$expected_count  = count( $taxonomies_to_sync );
					$processed_count = isset( $bulk_result['count'] ) ? (int) $bulk_result['count'] : 0;
					if ( $processed_count !== $expected_count ) {
						throw new Exception( sprintf( 'Bulk term taxonomy upsert count mismatch: expected %d, got %d', $expected_count, $processed_count ) );
					}

					$result['processed'] += $processed_count;
					foreach ( $taxonomies_to_sync as $tax_data ) {
						$this->metadata_manager->mark_synced_in_postgresql( 'term_taxonomy', $tax_data['term_taxonomy_id'] );
					}
				}

				$result['has_more'] = ( $offset + $batch_size ) < $total_count;

				// Update aggregate stats when batch completes.
				if ( ! $result['has_more'] ) {
					$this->metadata_manager->update_sync_stats( 'term_taxonomy' );
				}
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical: Log batch sync exceptions for production debugging
				error_log( 'GG_Data_Taxonomy_Sync: Batch sync term taxonomies error - ' . $e->getMessage() );
				$result['success'] = false;
				$result['message'] = $e->getMessage();
			}

			return $result;
		}

		/**
		 * Batch sync term relationships
		 *
		 * @param int $batch_size Number of records to process.
		 * @param int $offset Starting offset for pagination.
		 * @throws Exception When provider contract validation or bulk sync fails.
		 * @return array Batch processing results.
		 */
		public function batch_sync_term_relationships( $batch_size = 500, $offset = 0 ) {
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
				// Get enabled post types and statuses from settings.
				$settings_manager   = new GG_Data_Settings_Manager();
				$enabled_post_types = $settings_manager->get_with_category( 'sync', $this->connection_name, 'sync_enabled_post_types', array( 'post', 'page' ) );
				$enabled_statuses   = $settings_manager->get_with_category( 'sync', $this->connection_name, 'sync_enabled_statuses', array( 'publish' ) );

				// Build placeholders for IN clauses.
				$post_type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
				$status_placeholders    = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

				// Get total count of term relationships (not distinct posts - we need actual relationship count).
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to count term relationships for enabled post types/statuses for batch sync progress tracking. Placeholders are dynamic.
				$count_query = $wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE p.post_type IN ($post_type_placeholders)
					AND p.post_status IN ($status_placeholders)",
					array_merge( $enabled_post_types, $enabled_statuses )
				);
				$total_count = (int) $wpdb->get_var( $count_query );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$result['total'] = $total_count;

				// Get batch of term relationships (filtered).
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Required to fetch batch of term relationships for enabled post types/statuses for bulk sync operation. Placeholders are dynamic.
				$query         = $wpdb->prepare(
					"SELECT tr.*
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE p.post_type IN ($post_type_placeholders)
					AND p.post_status IN ($status_placeholders)
					ORDER BY tr.object_id, tr.term_taxonomy_id
					LIMIT %d OFFSET %d",
					array_merge( $enabled_post_types, $enabled_statuses, array( $batch_size, $offset ) )
				);
				$relationships = $wpdb->get_results( $query );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

				// Filter relationships that need syncing.
				$relationships_to_sync = array();
				$composite_ids         = array();
				foreach ( $relationships as $rel ) {
					$composite_id = $rel->object_id . '-' . $rel->term_taxonomy_id;

					if ( $this->metadata_manager->is_entity_synced_in_postgresql( 'term_relationship', $composite_id ) ) {
						++$result['skipped'];
					} else {
						$relationships_to_sync[] = array(
							'object_id'        => $rel->object_id,
							'term_taxonomy_id' => $rel->term_taxonomy_id,
							'term_order'       => $rel->term_order,
						);
						$composite_ids[]         = $composite_id;
					}
				}

				$this->ensure_provider_ready( 'batch term relationship sync' );

				if ( ! method_exists( $this->provider, 'bulk_upsert_term_relationships' ) ) {
					throw new Exception( 'Provider contract violation: missing bulk_upsert_term_relationships()' );
				}

				if ( ! empty( $relationships_to_sync ) ) {
					$bulk_result = $this->provider->bulk_upsert_term_relationships( $relationships_to_sync, 1, $this->connection_name );
					if ( empty( $bulk_result['success'] ) ) {
						$error_msg = isset( $bulk_result['error'] ) ? $bulk_result['error'] : 'Unknown error';
						throw new Exception( 'Bulk term relationship upsert failed: ' . $error_msg );
					}

					$expected_count  = count( $relationships_to_sync );
					$processed_count = isset( $bulk_result['count'] ) ? (int) $bulk_result['count'] : 0;
					if ( $processed_count !== $expected_count ) {
						throw new Exception( sprintf( 'Bulk term relationship upsert count mismatch: expected %d, got %d', $expected_count, $processed_count ) );
					}

					$result['processed'] += $processed_count;
					foreach ( $composite_ids as $composite_id ) {
						$this->metadata_manager->mark_synced_in_postgresql( 'term_relationship', $composite_id );
					}
				}

				$result['has_more'] = ( $offset + $batch_size ) < $total_count;

				// Update aggregate stats when batch completes.
				if ( ! $result['has_more'] ) {
					$this->metadata_manager->update_sync_stats( 'term_relationships' );
				}
			} catch ( Exception $e ) {
				$result['success'] = false;
				$result['message'] = $e->getMessage();
			}

				return $result;
		}
	}
}
