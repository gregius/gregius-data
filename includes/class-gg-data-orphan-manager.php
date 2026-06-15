<?php
/**
 * Orphan Manager
 *
 * Handles detection and deletion of orphan records in the remote database.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Orphan_Manager
 */
class GG_Data_Orphan_Manager {

	/**
	 * Connection name
	 *
	 * @var string
	 */
	protected $connection_name;

	/**
	 * Database provider
	 *
	 * @var GG_Data_DB_Provider
	 */
	protected $provider;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @param string $connection_name Connection name.
	 * @throws Exception If provider cannot be initialized.
	 */
	public function __construct( $connection_name ) {
		$this->logger = new GG_Data_Logger();
		$this->logger->log(
			sprintf( 'Constructor called - Connection: %s', $connection_name ),
			'debug',
			'orphan',
			$connection_name
		);

		$this->connection_name = $connection_name;

		$this->logger->log( 'Creating GG_Data_DB instance', 'debug', 'orphan', $connection_name );
		$db = new GG_Data_DB();

		$this->logger->log( 'Setting default connection', 'debug', 'orphan', $connection_name );
		$db->set_default_connection( $connection_name );

		$this->logger->log( 'Getting connection', 'debug', 'orphan', $connection_name );
		// Initialize connection - for PostgREST this returns false but creates the provider.
		$db->get_connection( $connection_name );

		$this->logger->log( 'Getting provider', 'debug', 'orphan', $connection_name );
		$this->provider = $db->get_provider();

		if ( ! $this->provider ) {
			$last_error = $db->get_last_error();
			$this->logger->log(
				sprintf( 'FAILED to get provider - Last error: %s', $last_error ? $last_error : 'No error message available' ),
				'error',
				'orphan',
				$connection_name
			);
			throw new Exception( 'Could not establish database connection: ' . esc_html( $last_error ) );
		}

		$this->logger->log(
			sprintf( 'Provider obtained - Type: %s', get_class( $this->provider ) ),
			'debug',
			'orphan',
			$connection_name
		);
	}

	/**
	 * Process orphans for a specific type
	 *
	 * @param string $type       Type of record (post, postmeta, term, term_taxonomy, term_relationship).
	 * @param int    $batch_size Batch size.
	 * @param int    $offset     Offset.
	 * @param array  $args       Additional arguments (e.g., post_type, preview).
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function process_orphans( $type, $batch_size = 100, $offset = 0, $args = array() ) {
		$batch_size = apply_filters( 'gg_data_orphan_delete_batch_size', $batch_size, $type, $this->connection_name );

		switch ( $type ) {
			case 'post':
				return $this->process_post_orphans( $batch_size, $offset, $args );
			case 'postmeta':
				return $this->process_simple_orphans( 'postmeta', 'meta_id', 'postmeta', $batch_size, $offset );
			case 'term':
				return $this->process_simple_orphans( 'terms', 'term_id', 'term', $batch_size, $offset );
			case 'term_taxonomy':
				return $this->process_simple_orphans( 'term_taxonomy', 'term_taxonomy_id', 'term_taxonomy', $batch_size, $offset );
			case 'term_relationship':
				return $this->process_relationship_orphans( $batch_size, $offset );
			default:
				throw new Exception( sprintf( 'Invalid orphan type: %s', esc_html( $type ) ) );
		}
	}

	/**
	 * Process post orphans
	 *
	 * @param int   $batch_size Batch size.
	 * @param int   $offset     Offset.
	 * @param array $args       Args (post_type, preview).
	 * @return array Result.
	 * @throws Exception On error.
	 */
	protected function process_post_orphans( $batch_size, $offset, $args ) {
		global $wpdb;

		$post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
		$preview   = isset( $args['preview'] ) ? $args['preview'] : false;

		$this->logger->log(
			sprintf( 'Starting post orphan check - Type: %s, Batch: %d, Offset: %d, Preview: %s', $post_type, $batch_size, $offset, $preview ? 'yes' : 'no' ),
			'debug',
			'orphan',
			$this->connection_name,
			array(
				'post_type'  => $post_type,
				'batch_size' => $batch_size,
				'offset'     => $offset,
			)
		);

		// Fetch batch of IDs from remote.
		// Note: wp_posts uses lowercase id column in PostgreSQL.
		$remote_ids = $this->provider->get_ids(
			'posts',
			$batch_size,
			$offset,
			array( 'post_type' => 'eq.' . $post_type ),
			'id'
		);

		if ( is_wp_error( $remote_ids ) ) {
			$this->logger->log(
				sprintf( 'Error fetching remote IDs: %s', $remote_ids->get_error_message() ),
				'error',
				'orphan',
				$this->connection_name
			);
			throw new Exception( esc_html( $remote_ids->get_error_message() ) );
		}

		if ( ! empty( $remote_ids ) && is_array( $remote_ids[0] ) && isset( $remote_ids[0]['id'] ) ) {
			$remote_ids = array_map( 'intval', array_column( $remote_ids, 'id' ) );
		}

		$this->logger->log(
			sprintf( 'Fetched %d remote IDs from PostgreSQL', count( $remote_ids ) ),
			'debug',
			'orphan',
			$this->connection_name
		);

		// Check if sync is enabled for this post type.
		$settings_manager   = new GG_Data_Settings_Manager();
		$enabled_post_types = $settings_manager->get_with_category(
			'sync',
			$this->connection_name,
			'sync_enabled_post_types',
			array()
		);

		// Handle serialized data.
		if ( is_string( $enabled_post_types ) ) {
			$enabled_post_types = maybe_unserialize( $enabled_post_types );
		}

		if ( ! is_array( $enabled_post_types ) ) {
			$enabled_post_types = array();
		}

		$sync_enabled = in_array( $post_type, $enabled_post_types, true );

		$this->logger->log(
			sprintf( 'Sync enabled for %s: %s', $post_type, $sync_enabled ? 'yes' : 'no' ),
			'debug',
			'orphan',
			$this->connection_name
		);

		// Find orphans.
		$orphan_ids = array();
		if ( ! empty( $remote_ids ) ) {
			if ( ! $sync_enabled ) {
				// If sync is disabled, ALL records in PostgreSQL are orphans.
				$orphan_ids = $remote_ids;

				$this->logger->log(
					sprintf( 'Sync disabled - treating all %d PostgreSQL records as orphans', count( $orphan_ids ) ),
					'debug',
					'orphan',
					$this->connection_name
				);
			} else {
				// If sync is enabled, only records not in WordPress are orphans.
				$placeholders = implode( ',', array_fill( 0, count( $remote_ids ), '%d' ) );
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$existing_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_type = %s",
						array_merge( $remote_ids, array( $post_type ) )
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				$orphan_ids = array_diff( $remote_ids, $existing_ids );

				$this->logger->log(
					sprintf( 'Sync enabled - found %d existing posts in WordPress, %d orphans in PostgreSQL', count( $existing_ids ), count( $orphan_ids ) ),
					'debug',
					'orphan',
					$this->connection_name
				);
			}

			if ( ! empty( $orphan_ids ) ) {
				$this->logger->log(
					sprintf( 'Orphan IDs: %s', implode( ', ', array_slice( $orphan_ids, 0, 10 ) ) . ( count( $orphan_ids ) > 10 ? '...' : '' ) ),
					'debug',
					'orphan',
					$this->connection_name
				);
			}
		}
		$orphan_count = count( $orphan_ids );

		if ( $preview ) {
			return array(
				'success' => true,
				'preview' => true,
				'orphans' => array_values( $orphan_ids ),
				'count'   => $orphan_count,
			);
		}

		// Delete orphans.
		$deleted = 0;
		if ( ! empty( $orphan_ids ) ) {
			$this->logger->log(
				sprintf( 'Attempting to delete %d orphan posts from wp_posts (CASCADE will handle related tables)', count( $orphan_ids ) ),
				'debug',
				'orphan',
				$this->connection_name
			);

			$result = $this->provider->delete_ids( 'posts', $orphan_ids, 'id' );

			$this->logger->log(
				sprintf( 'Delete result - Success: %s, Count: %d', $result['success'] ? 'yes' : 'no', isset( $result['count'] ) ? $result['count'] : 0 ),
				$result['success'] ? 'debug' : 'error',
				'orphan',
				$this->connection_name,
				array( 'result' => $result )
			);

			if ( $result['success'] ) {
				$deleted = $result['count'];

				// Skip individual metadata cleanup - wp_gg_sync_metadata_entities table doesn't exist in PostgreSQL.
				// Individual entity tracking is not needed; only aggregate stats are maintained.

				$this->logger->log(
					sprintf( 'Successfully deleted %d orphans from PostgreSQL', $deleted ),
					'info',
					'orphan',
					$this->connection_name
				);

				// Update aggregate stats in MySQL (or delete row if sync disabled and pg_count = 0).
				$metadata_manager = new GG_Data_Sync_Metadata_Manager( $this->connection_name );
				$metadata_manager->update_sync_stats( 'post', $post_type );
			} else {
				$this->logger->log(
					sprintf( 'Failed to delete orphans: %s', $result['message'] ),
					'error',
					'orphan',
					$this->connection_name
				);
				throw new Exception( esc_html( $result['message'] ) );
			}
		}

		return array(
			'success'   => true,
			'deleted'   => $deleted,
			'processed' => count( $remote_ids ),
			'has_more'  => count( $remote_ids ) === $batch_size,
		);
	}

	/**
	 * Process simple orphans (single ID column)
	 *
	 * @param string $table      Table name.
	 * @param string $id_column  ID column name.
	 * @param string $meta_type  Metadata type key.
	 * @param int    $batch_size Batch size.
	 * @param int    $offset     Offset.
	 * @return array Result.
	 * @throws Exception On error.
	 */
	protected function process_simple_orphans( $table, $id_column, $meta_type, $batch_size, $offset ) {
		global $wpdb;

		// Fetch batch of IDs from remote.
		$remote_ids = $this->provider->get_ids(
			$table,
			$batch_size,
			$offset,
			array(),
			$id_column
		);

		if ( is_wp_error( $remote_ids ) ) {
			throw new Exception( esc_html( $remote_ids->get_error_message() ) );
		}

		// Extract IDs.
		$remote_ids = array_column( $remote_ids, $id_column );

		// Find orphans.
		$orphan_ids = array();
		if ( ! empty( $remote_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $remote_ids ), '%d' ) );

			// Map table name to WP table.
			$wp_table = $wpdb->prefix . $table;

			// Escape identifiers.
			$wp_table  = esc_sql( $wp_table );
			$id_column = esc_sql( $id_column );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$existing_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT $id_column FROM $wp_table WHERE $id_column IN ($placeholders)",
					$remote_ids
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			$orphan_ids = array_diff( $remote_ids, $existing_ids );
		}

		// Delete orphans.
		$deleted = 0;
		if ( ! empty( $orphan_ids ) ) {
			$result = $this->provider->delete_ids( $table, $orphan_ids, $id_column );
			if ( $result['success'] ) {
				$deleted = $result['count'];

				// Delete metadata entries.
				$metadata_manager = new GG_Data_Sync_Metadata_Manager( $this->connection_name );
				foreach ( $orphan_ids as $orphan_id ) {
					$metadata_manager->remove_metadata( $meta_type, $orphan_id );
				}

				// Update stats.
				$metadata_manager->update_sync_stats( $meta_type );
			}
		}

		return array(
			'success'   => true,
			'deleted'   => $deleted,
			'processed' => count( $remote_ids ),
			'has_more'  => count( $remote_ids ) === $batch_size,
		);
	}

	/**
	 * Process relationship orphans (composite key)
	 *
	 * @param int $batch_size Batch size.
	 * @param int $offset     Offset.
	 * @return array Result.
	 * @throws Exception On error.
	 */
	protected function process_relationship_orphans( $batch_size, $offset ) {
		global $wpdb;

		// Fetch batch of relationships from remote.
		$remote_rels = $this->provider->get_ids(
			'term_relationships',
			$batch_size,
			$offset,
			array(),
			'object_id, term_taxonomy_id'
		);

		if ( is_wp_error( $remote_rels ) ) {
			throw new Exception( esc_html( $remote_rels->get_error_message() ) );
		}

		$orphan_rels = array();
		if ( ! empty( $remote_rels ) ) {
			// Get all unique object IDs to query WP efficiently.
			$object_ids = array_unique( array_column( $remote_rels, 'object_id' ) );

			if ( ! empty( $object_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wp_rels = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT object_id, term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id IN ($placeholders)",
						$object_ids
					),
					ARRAY_A
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				// Build lookup map.
				$wp_map = array();
				foreach ( $wp_rels as $rel ) {
					$wp_map[ $rel['object_id'] . '-' . $rel['term_taxonomy_id'] ] = true;
				}

				// Identify orphans.
				foreach ( $remote_rels as $rel ) {
					$key = $rel['object_id'] . '-' . $rel['term_taxonomy_id'];
					if ( ! isset( $wp_map[ $key ] ) ) {
						$orphan_rels[] = $rel;
					}
				}
			}
		}

		$deleted = 0;
		foreach ( $orphan_rels as $orphan ) {
			$result = $this->provider->delete_term_relationship( $orphan['object_id'], $orphan['term_taxonomy_id'] );
			if ( $result['success'] ) {
				++$deleted;
			}
		}

		return array(
			'success'   => true,
			'deleted'   => $deleted,
			'processed' => count( $remote_rels ),
			'has_more'  => count( $remote_rels ) === $batch_size,
		);
	}
}
