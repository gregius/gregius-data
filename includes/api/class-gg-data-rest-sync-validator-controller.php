<?php
/**
 * REST API: Sync Validation Controller
 *
 * Provides endpoints for sync validation operations.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * REST API controller for sync validation operations
 *
 * @since 1.0.0
 */
class GG_Data_REST_Sync_Validator_Controller extends WP_REST_Controller {

	/**
	 * Sync validator instance
	 *
	 * @var GG_Data_Sync_Validator
	 */
	private $validator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'sync/validation';
		$this->validator = new GG_Data_Sync_Validator();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /gg-data/v1/sync/validation - Get validation results.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_validation_status' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// POST /gg-data/v1/sync/validation/run - Run manual validation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/run',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_manual_validation' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		// POST /gg-data/v1/sync/validation/reset - Reset validation data.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_validation' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		// GET /gg-data/v1/sync/validation/fast - Fast metadata-based validation .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fast',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_fast_validation' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'connection' => array(
							'required'          => false,
							'default'           => 'default',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Get validation status
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_validation_status( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$results         = $this->validator->get_validation_results( $connection_name );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $results,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'validation_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Run manual validation
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function run_manual_validation( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$results         = $this->validator->run_validation( $connection_name );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Validation completed successfully', 'gregius-data' ),
					'data'    => $results,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'validation_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Reset validation data
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function reset_validation( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' );
			$this->validator->reset_validation( $connection_name );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Validation data reset successfully', 'gregius-data' ),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'validation_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get fast metadata-based validation
	 *
	 * Uses metadata tables for instant validation (~100ms vs 3-13s).
	 * Queries both MySQL and PostgreSQL metadata tables in parallel.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception When required validation dependencies are unavailable.
	 */
	public function get_fast_validation( $request ) {
		try {
			$connection_name = $request->get_param( 'connection' ) ?? 'default';
			$probe_errors    = array();

			// Initialize settings and metadata manager.
			$settings_manager   = new GG_Data_Settings_Manager();
			$connection_manager = new GG_Data_Connection_Manager();

			$start_time = microtime( true );

			// Use metadata tables for fast validation (same logic for PDO and Supabase).
			// MySQL metadata: what SHOULD be synced (from wp_gg_sync_metadata).
			// PostgreSQL: Use RPC for Supabase, direct query for PDO.
			$metadata_start = microtime( true );

			if ( ! class_exists( 'GG_Data_Sync_Metadata_Manager' ) ) {
				throw new Exception( 'GG_Data_Sync_Metadata_Manager class not found' );
			}

			$metadata_manager = new GG_Data_Sync_Metadata_Manager( $connection_name );
			// Only query MySQL metadata - PostgreSQL data comes from RPC (Supabase) or direct queries (PDO).
			$mysql_summary = $metadata_manager->get_mysql_validation_summary();

			// Get provider for fallback counts (Supabase/PDO).
			$provider = $connection_manager->get_provider( $connection_name );

			$end_time = microtime( true );
			$duration = round( ( $end_time - $start_time ) * 1000, 2 );

			// Transform to format expected by frontend (direct keys, not nested in 'entities').
			$results = array(
				'connection'      => $connection_name,
				'validation_time' => $duration . 'ms',
				'timestamp'       => current_time( 'mysql' ),
				'overall_status'  => 'healthy', // Will be updated based on entities.
			);

			// Get configured entity types from settings (if not already loaded for RPC).
			if ( ! isset( $enabled_post_types ) ) {
				$enabled_post_types = $settings_manager->get_with_category( 'sync', $connection_name, 'sync_enabled_post_types', array() );
				if ( is_string( $enabled_post_types ) ) {
					$enabled_post_types = maybe_unserialize( $enabled_post_types );
				}
			}
			if ( ! isset( $enabled_statuses ) ) {
				$enabled_statuses = $settings_manager->get_with_category( 'sync', $connection_name, 'sync_enabled_statuses', array( 'publish' ) );
				if ( is_string( $enabled_statuses ) ) {
					$enabled_statuses = maybe_unserialize( $enabled_statuses );
				}
			}

			// Get all public post types for validation display (even if not enabled).
			$all_post_types = get_post_types( array( 'public' => true ), 'names' );
			// Add some private post types that might be useful (matching get_post_types endpoint).
			$additional_types = get_post_types( array( 'show_ui' => true ), 'names' );
			$all_post_types   = array_unique( array_merge( $all_post_types, $additional_types ) );

			$sync_meta  = $settings_manager->get_with_category( 'sync', $connection_name, 'sync_meta', true );
			$sync_terms = $settings_manager->get_with_category( 'sync', $connection_name, 'sync_terms', true );

			// Process MySQL summary (what SHOULD be synced).
			$mysql_by_type = array();
			if ( $mysql_summary ) {
				foreach ( $mysql_summary as $row ) {
					$mysql_by_type[ $row['entity_type'] ] = array(
						'total'   => (int) $row['total'],
						'synced'  => (int) $row['synced'],
						'pending' => (int) $row['pending'],
					);
				}
			}

			// Get PostgreSQL connection only for cleaning_needed counts (PDO only).
			$pg_conn = null;

			// Check if connection is PDO-based by looking at connection config.
			$connection_config = $settings_manager->get_connection( $connection_name );
			$is_pdo            = ( isset( $connection_config['type'] ) && ( 'pdo' === $connection_config['type'] || 'postgresql' === $connection_config['type'] ) );

			if ( $is_pdo ) {
				// Try to get connection from provider first.
				if ( $provider && method_exists( $provider, 'get_connection' ) ) {
					$pg_conn = $provider->get_connection();
				}

				// Fallback to creating new connection if provider did not return one.
				if ( ! $pg_conn ) {
					$pg_db = new GG_Data_DB();
					$pg_db->set_default_connection( $connection_name );
					$pg_conn = $pg_db->get_connection( $connection_name );
				}
			}

			// Build entity types list from tracked data AND configured settings.
			$entity_types = array_keys( $mysql_by_type );           // Add configured entities even if not yet tracked.
			if ( $sync_meta ) {
				$entity_types[] = 'postmeta';
			}
			if ( $sync_terms ) {
				$entity_types[] = 'term';
				$entity_types[] = 'term_taxonomy';
				$entity_types[] = 'term_relationships';
			}
			$entity_types = array_unique( $entity_types );
			$has_critical = false;
			$has_warnings = false;

			global $wpdb;
			$pg_prefix = isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';

			foreach ( $entity_types as $entity_type ) {
				// Skip post entity - will handle per post type below.
				if ( 'post' === $entity_type ) {
					continue;
				}

				$mysql_data = isset( $mysql_by_type[ $entity_type ] ) ? $mysql_by_type[ $entity_type ] : array(
					'total'   => 0,
					'synced'  => 0,
					'pending' => 0,
				);

				// Calculate actual WordPress count (don't rely on metadata which might be stale/empty).
				// Always query live WP tables to show accurate drift for new content.
				$wp_total = 0;

				switch ( $entity_type ) {
					case 'term':
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wp_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );
						break;
					case 'term_taxonomy':
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wp_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy}" );
						break;
					case 'term_relationships':
						if ( ! empty( $enabled_post_types ) && ! empty( $enabled_statuses ) ) {
							$post_type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
							$status_placeholders    = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

							// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated based on array count.
							$query = $wpdb->prepare(
								"SELECT COUNT(*) 
								FROM {$wpdb->term_relationships} tr
								INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
								WHERE p.post_type IN ($post_type_placeholders)
								AND p.post_status IN ($status_placeholders)",
								array_merge( $enabled_post_types, $enabled_statuses )
							);
							// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
							// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wp_total = (int) $wpdb->get_var( $query );
							// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
						}
						break;
					case 'postmeta':
						if ( ! empty( $enabled_post_types ) && ! empty( $enabled_statuses ) ) {
							$post_type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
							$status_placeholders    = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

							// Exclude WordPress internal/transient keys.
							$skipped_keys         = array(
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
							$skipped_placeholders = implode( ',', array_fill( 0, count( $skipped_keys ), '%s' ) );

							// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are dynamically generated.
							$query = $wpdb->prepare(
								"SELECT COUNT(*) 
								FROM {$wpdb->postmeta} pm
								INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
								WHERE p.post_type IN ($post_type_placeholders)
								AND p.post_status IN ($status_placeholders)
								AND pm.meta_key NOT IN ($skipped_placeholders)
								AND pm.meta_key NOT LIKE %s
								AND pm.meta_key NOT LIKE %s",
								array_merge( $enabled_post_types, $enabled_statuses, $skipped_keys, array( '_transient%', '_site_transient%' ) )
							);
							// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
							// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wp_total = (int) $wpdb->get_var( $query );
							// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
						}
						break;
				}

				// Use metadata for PostgreSQL counts (already tracked in wp_gg_sync_metadata.pg_count).
				$pg_total = (int) $mysql_data['synced'];

				// Legacy RPC check (unreachable - metadata is source of truth).
				if ( false && $rpc_summary && isset( $rpc_summary['success'] ) && $rpc_summary['success'] ) {
					switch ( $entity_type ) {
						case 'term':
							$pg_total = isset( $rpc_summary['terms'] ) ? (int) $rpc_summary['terms'] : 0;
							break;
						case 'term_taxonomy':
							$pg_total = isset( $rpc_summary['term_taxonomy'] ) ? (int) $rpc_summary['term_taxonomy'] : 0;
							break;
						case 'term_relationship':
							$pg_total = isset( $rpc_summary['term_relationships'] ) ? (int) $rpc_summary['term_relationships'] : 0;
							break;
						case 'postmeta':
							$pg_total = isset( $rpc_summary['postmeta'] ) ? (int) $rpc_summary['postmeta'] : 0;
							break;
						case 'post':
							// Handled per post type below.
							break;
					}
				}
				// Fallback: Query actual PostgreSQL tables for real-time counts.
				// Use provider if it supports count_records (Supabase).
				// NOTE: We intentionally skip live remote queries here to rely on the metadata table (wp_gg_sync_metadata)
				// which is now the source of truth and much faster.
				if ( $pg_conn ) {
					$entity_probe_failed = false;
					// Fallback to PDO connection for providers that don't support count_records.
					try {
						switch ( $entity_type ) {
							case 'term':
								$stmt     = $pg_conn->query( "SELECT COUNT(*) FROM {$pg_prefix}terms" );
								$pg_total = (int) $stmt->fetchColumn();
								break;
							case 'term_taxonomy':
								$stmt     = $pg_conn->query( "SELECT COUNT(*) FROM {$pg_prefix}term_taxonomy" );
								$pg_total = (int) $stmt->fetchColumn();
								break;
							case 'term_relationships':
								// Only count relationships for enabled post types and statuses.
								$enabled_statuses = $settings_manager->get_with_category(
									'sync',
									$connection_name,
									'sync_enabled_statuses',
									array( 'publish' )
								);
								if ( is_string( $enabled_statuses ) ) {
									$enabled_statuses = maybe_unserialize( $enabled_statuses );
								}
								if ( ! empty( $enabled_post_types ) && ! empty( $enabled_statuses ) ) {
									$post_type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '?' ) );
									$status_placeholders    = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );
									$stmt                   = $pg_conn->prepare(
										"SELECT COUNT(*) 
										FROM (
											SELECT DISTINCT tr.object_id, tr.term_taxonomy_id
											FROM {$pg_prefix}term_relationships tr
											INNER JOIN {$pg_prefix}posts p ON tr.object_id = p.id
											WHERE p.post_type IN ($post_type_placeholders)
											AND p.post_status IN ($status_placeholders)
										) subquery"
									);
									$stmt->execute( array_merge( $enabled_post_types, $enabled_statuses ) );
									$pg_total = (int) $stmt->fetchColumn();
								}
								break;
							case 'postmeta':
								// Only count postmeta for enabled post types and statuses.
								$enabled_statuses = $settings_manager->get_with_category(
									'sync',
									$connection_name,
									'sync_enabled_statuses',
									array( 'publish' )
								);
								if ( is_string( $enabled_statuses ) ) {
									$enabled_statuses = maybe_unserialize( $enabled_statuses );
								}
								if ( ! empty( $enabled_post_types ) && ! empty( $enabled_statuses ) ) {
									$post_type_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '?' ) );
									$status_placeholders    = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );

									// Exclude WordPress internal/transient keys (same as MySQL query for consistency).
									$skipped_keys         = array(
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
									$skipped_placeholders = implode( ',', array_fill( 0, count( $skipped_keys ), '?' ) );

									$stmt = $pg_conn->prepare(
										"SELECT COUNT(*) 
									FROM {$pg_prefix}postmeta pm
									INNER JOIN {$pg_prefix}posts p ON pm.post_id = p.id
									WHERE p.post_type IN ($post_type_placeholders)
									AND p.post_status IN ($status_placeholders)
									AND pm.meta_key NOT IN ($skipped_placeholders)
									AND pm.meta_key NOT LIKE ?
									AND pm.meta_key NOT LIKE ?"
									);
									$stmt->execute( array_merge( $enabled_post_types, $enabled_statuses, $skipped_keys, array( '_transient%', '_site_transient%' ) ) );
									$pg_total = (int) $stmt->fetchColumn();
								}
								break;
						}
					} catch ( Exception $e ) {
						$entity_probe_failed = true;
						$probe_errors[]      = array(
							'scope'       => 'entity',
							'entity_type' => $entity_type,
							'operation'   => 'postgres_count_probe',
							'message'     => $e->getMessage(),
						);
					}

					if ( $entity_probe_failed ) {
						$has_critical = true;
					}
				}

				// MySQL counts come from metadata (cached, fast).
				// No live table queries needed - metadata is updated during sync.

				$drift = $wp_total - $pg_total;
				// Signed drift: negative = PostgreSQL behind, positive = PostgreSQL ahead.
				$drift_percentage = $wp_total > 0 ? ( ( $pg_total - $wp_total ) / $wp_total ) * 100 : 0;

				// Determine status based on absolute drift percentage.
				$status = 'healthy';
				if ( abs( $drift_percentage ) > 5 ) {
					$status       = 'critical';
					$has_critical = true;
				} elseif ( 0 !== $drift_percentage ) {
					$status       = 'warning';
					$has_warnings = true;
				}

				// Map entity types to frontend keys.
				$frontend_key = $entity_type;
				if ( 'term' === $entity_type ) {
					$frontend_key = 'terms';
				} elseif ( 'term_relationships' === $entity_type ) {
					$frontend_key = 'term_relationships';
				}

				// Format data.
				$entity_data = array(
					'wordpress_count'  => $wp_total,
					'postgresql_count' => $pg_total,
					'drift'            => $drift,
					'drift_percentage' => round( $drift_percentage, 2 ),
					'status'           => $status,
					'pending'          => $mysql_data['pending'],
					'probe_error'      => ! empty( $entity_probe_failed ),
				);

				if ( ! empty( $entity_probe_failed ) ) {
					$entity_data['status'] = 'critical';
				}

				// Skip post entity - will handle per post type below.
				if ( 'post' !== $entity_type ) {
					$results[ $frontend_key ] = $entity_data;
				}
			}

			// Query metadata per post type for ALL post types (to show counts even if disabled).
			if ( ! empty( $all_post_types ) ) {
				$per_type_start = microtime( true );
				global $wpdb;
				$results['posts'] = array();

				// Get enabled statuses for accurate WordPress counts.
				if ( ! isset( $enabled_statuses ) ) {
					$enabled_statuses = $settings_manager->get_with_category(
						'sync',
						$connection_name,
						'sync_enabled_statuses',
						array( 'publish' )
					);
					if ( is_string( $enabled_statuses ) ) {
						$enabled_statuses = maybe_unserialize( $enabled_statuses );
					}
				}

				// Safety check: ensure we have valid statuses.
				if ( empty( $enabled_statuses ) || ! is_array( $enabled_statuses ) ) {
					$enabled_statuses = array( 'publish' );
				}

				foreach ( $all_post_types as $post_type ) {
					// Use MySQL-only metadata query (fast).
					$pt_summary = $metadata_manager->get_mysql_validation_summary_by_post_type( $post_type );

					$pt_mysql_total   = 0;
					$pt_mysql_synced  = 0;
					$pt_mysql_pending = 0;

					// ALWAYS query actual WordPress counts with CURRENT enabled statuses
					// Don't use metadata counts because they reflect statuses at time of last sync,
					// not current user selection.
					$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );
					$query               = $wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} 
						WHERE post_type = %s 
						AND post_status IN ($status_placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $status_placeholders is a safe string of comma-separated '%s' placeholders
						array_merge( array( $post_type ), $enabled_statuses )
					);
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Required to count posts by type/status from WordPress core table for validation, query is prepared above
					$pt_mysql_total = (int) $wpdb->get_var( $query );                  // Get synced/pending from metadata if available.
					if ( ! empty( $pt_summary ) ) {
						$pt_mysql_synced  = (int) $pt_summary[0]['synced'];
						$pt_mysql_pending = (int) $pt_summary[0]['pending'];
					} else {
						// No metadata yet - all pending.
						$pt_mysql_pending = $pt_mysql_total;
					}

					// Use metadata for PostgreSQL count (already tracked).
					// Both PDO and PostgREST share the same metadata source of truth.
					$pt_pg_total            = $pt_mysql_synced;
					$post_type_probe_failed = false;

					// Check if this post type is enabled for sync.
					$is_enabled = in_array( $post_type, $enabled_post_types, true );

					// For disabled types, always display WordPress count as 0.
					// This clearly indicates the type is not being tracked/synced.
					if ( ! $is_enabled ) {
						$pt_mysql_total_display = 0;

						if ( $pt_pg_total > 0 ) {
							// Disabled with orphans: show +100% drift.
							$pt_drift            = $pt_pg_total;
							$pt_drift_percentage = 100.0;
						} else {
							// Disabled with no orphans: show 0% drift.
							$pt_drift            = 0;
							$pt_drift_percentage = 0;
						}
					} else {
						// Enabled: show actual WordPress count and calculate drift normally.
						$pt_mysql_total_display = $pt_mysql_total;
						$pt_drift               = $pt_mysql_total - $pt_pg_total;

						// Calculate drift percentage with special handling for orphan scenario.
						// Signed drift: negative = PostgreSQL behind, positive = PostgreSQL ahead.
						if ( 0 === $pt_mysql_total && $pt_pg_total > 0 ) {
							// Special case: All PostgreSQL records are orphans (100% drift).
							$pt_drift_percentage = 100.0;
						} elseif ( $pt_mysql_total > 0 ) {
							// Standard case: Calculate drift relative to WordPress count.
							$pt_drift_percentage = ( ( $pt_pg_total - $pt_mysql_total ) / $pt_mysql_total ) * 100;
						} else {
							// Both zero: No drift.
							$pt_drift_percentage = 0;
						}
					}

					$pt_status = 'healthy';
					if ( abs( $pt_drift_percentage ) > 5 ) {
						$pt_status    = 'critical';
						$has_critical = true;
					} elseif ( 0 !== $pt_drift_percentage ) {
						$pt_status    = 'warning';
						$has_warnings = true;
					}

					if ( $post_type_probe_failed ) {
						$pt_status = 'critical';
					}

					// Count posts needing cleaning (PDO only - no metadata available).
					$pt_cleaning_needed = 0;
					if ( $pg_conn ) {
						try {
							$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );
							$stmt                = $pg_conn->prepare(
								"SELECT COUNT(*) 
							FROM {$pg_prefix}posts p
							LEFT JOIN {$pg_prefix}posts_clean pc ON p.id = pc.post_id
							WHERE p.post_type = ?
							AND p.post_status IN ($status_placeholders)
							AND pc.post_id IS NULL"
							);
							$stmt->execute( array_merge( array( $post_type ), $enabled_statuses ) );
							$pt_cleaning_needed = (int) $stmt->fetchColumn();
						} catch ( Exception $e ) {
							$probe_errors[] = array(
								'scope'     => 'post_type',
								'post_type' => $post_type,
								'operation' => 'postgres_cleaning_probe',
								'message'   => $e->getMessage(),
							);
						}
					}

					$results['posts'][ $post_type ] = array(
						'wordpress_count'  => $pt_mysql_total_display,
						'postgresql_count' => $pt_pg_total,
						'drift'            => $pt_drift,
						'drift_percentage' => round( $pt_drift_percentage, 2 ),
						'status'           => $pt_status,
						'pending'          => $pt_mysql_pending,
						'cleaning_needed'  => $pt_cleaning_needed,
						'sync_disabled'    => ! $is_enabled,
						'probe_error'      => $post_type_probe_failed,
					);
				}
			}

			// Check for orphaned post types (disabled but still have data in PostgreSQL).
			// Use metadata table for fast detection - works for both PDO and Supabase.
			$orphaned_types = $metadata_manager->get_orphaned_post_types_from_metadata( $enabled_post_types, $connection_name );

			// Also check PostgreSQL directly for any post types that exist there but are not enabled.
			// This catches cases where metadata was cleared but remote data remains.
			if ( $pg_conn ) {
				try {
					$stmt = $pg_conn->query( "SELECT DISTINCT post_type FROM {$pg_prefix}posts" );
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
					$remote_types = $stmt->fetchAll( PDO::FETCH_COLUMN );

					foreach ( $remote_types as $remote_type ) {
						// If not enabled and not already detected as orphaned via metadata.
						if ( ! in_array( $remote_type, $enabled_post_types, true ) && ! isset( $orphaned_types[ $remote_type ] ) ) {
							// Count records for this type.
							$stmt = $pg_conn->prepare( "SELECT COUNT(*) FROM {$pg_prefix}posts WHERE post_type = :post_type" );
							$stmt->execute( array( ':post_type' => $remote_type ) );
							$count = (int) $stmt->fetchColumn();

							if ( $count > 0 ) {
								$orphaned_types[ $remote_type ] = $count;
							}
						}
					}
				} catch ( Exception $e ) {
					$probe_errors[] = array(
						'scope'     => 'orphan_detection',
						'operation' => 'postgres_remote_types_probe',
						'message'   => $e->getMessage(),
					);
					$has_critical   = true;
				}
			}

			// Add orphaned types to results.
			foreach ( $orphaned_types as $post_type => $pg_count ) {
				// Add disabled post types to results with special markers.
				$results['posts'][ $post_type ] = array(
					'wordpress_count'  => 0,  // Not syncing anymore.
					'postgresql_count' => $pg_count,
					'drift'            => -$pg_count,  // Negative drift (more in PG than WP).
					'drift_percentage' => 100.0,
					'status'           => 'orphaned',  // Special status for disabled types.
					'pending'          => 0,
					'cleaning_needed'  => $pg_count,
					'sync_disabled'    => true,  // Flag for UI to show different messaging.
				);

				// Mark as critical since orphaned data exists.
				$has_critical = true;
			}

			// Set overall status.
			if ( $has_critical ) {
				$results['overall_status'] = 'critical';
			} elseif ( $has_warnings ) {
				$results['overall_status'] = 'warning';
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $results,
					'meta'    => array(
						'method'            => 'metadata',
						'fast'              => true,
						'duration'          => $duration . 'ms',
						'probe_error_count' => count( $probe_errors ),
						'probe_errors'      => $probe_errors,
					),
				),
				200
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'validation_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to delete items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
