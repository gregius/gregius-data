<?php
/**
 * Sync Metadata Manager
 *
 * @package Gregius_Data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Metadata Manager
 *
 * Manages sync metadata tracking in both MySQL and PostgreSQL databases.
 * Enables instant validation (~100ms) instead of full table scans (3-13s).
 *
 * MySQL table: Tracks what SHOULD be synced (based on settings)
 * PostgreSQL table: Tracks what HAS BEEN synced (actual state)
 *
 * @package Gregius_Data
 * @since 1.0.0
 */
class GG_Data_Sync_Metadata_Manager {

	/**
	 * WordPress database instance
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * PostgreSQL database instance
	 *
	 * @var GG_Data_DB
	 */
	private $pg_db;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Connection manager instance
	 *
	 * @var GG_Data_Connection_Manager
	 */
	private $connection_manager;

	/**
	 * Provider instance
	 *
	 * @var mixed
	 */
	private $provider;

	/**
	 * MySQL table name
	 *
	 * @var string
	 */
	private $mysql_table;

	/**
	 * PostgreSQL table name
	 *
	 * @var string
	 */
	private $pg_table;

	/**
	 * Connection name
	 *
	 * @var string
	 */
	private $connection_name;

	/**
	 * Constructor
	 *
	 * @param string $connection_name Connection name.
	 */
	public function __construct( $connection_name = 'default' ) {
		global $wpdb;
		$this->wpdb               = $wpdb;
		$this->connection_name    = $connection_name;
		$this->mysql_table        = $wpdb->prefix . 'gg_sync_metadata';
		$this->pg_table           = $wpdb->prefix . 'gg_sync_metadata_entities';
		$this->logger             = new GG_Data_Logger();
		$this->connection_manager = new GG_Data_Connection_Manager();

		// Get provider for this connection.
		$this->provider = $this->connection_manager->get_provider( $connection_name );

		// Lazy-load PostgreSQL connection (only initialize when needed).
		// This saves 300-400ms for Supabase when only using MySQL queries.
		$this->pg_db = null;
	}

	/**
	 * Get PostgreSQL database instance (lazy-loaded).
	 *
	 * @return GG_Data_DB PostgreSQL database instance.
	 */
	private function get_pg_db() {
		if ( null === $this->pg_db ) {
			$this->pg_db = new GG_Data_DB();
			$this->pg_db->set_default_connection( $this->connection_name );
		}
		return $this->pg_db;
	}

	/**
	 * Track entity that HAS BEEN synced (PostgreSQL)
	 *
	 * @param string $entity_type Entity type (post, postmeta, term, term_taxonomy, term_relationship).
	 * @param int    $entity_id Original WordPress entity ID.
	 * @param array  $extra_data Optional extra data (post_type, source_id, source_modified_at).
	 * @return bool True on success, false on failure.
	 */
	public function track_synced( $entity_type, $entity_id, $extra_data = array() ) {
		try {
			$conn = $this->get_pg_db()->get_connection();
			if ( ! $conn ) {
				$this->logger->log( 'No PostgreSQL connection available for metadata tracking', 'error', 'sync', $this->connection_name );
				return false;
			}

			$post_type          = isset( $extra_data['post_type'] ) ? $extra_data['post_type'] : null;
			$source_id          = isset( $extra_data['source_id'] ) ? $extra_data['source_id'] : null;
			$source_modified_at = isset( $extra_data['source_modified_at'] ) ? $extra_data['source_modified_at'] : null;

			$sql = "INSERT INTO {$this->pg_table}
				(connection_name, entity_type, entity_id, post_type, source_id, source_modified_at, synced_at, created_at, updated_at)
				VALUES (:connection_name, :entity_type, :entity_id, :post_type, :source_id, :source_modified_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
				ON CONFLICT (connection_name, entity_type, entity_id)
				DO UPDATE SET
					synced_at = CURRENT_TIMESTAMP,
					updated_at = CURRENT_TIMESTAMP,
					post_type = EXCLUDED.post_type,
					source_id = EXCLUDED.source_id,
					source_modified_at = EXCLUDED.source_modified_at";

			$stmt = $conn->prepare( $sql );
			$stmt->execute(
				array(
					':connection_name'    => $this->connection_name,
					':entity_type'        => $entity_type,
					':entity_id'          => $entity_id,
					':post_type'          => $post_type,
					':source_id'          => $source_id,
					':source_modified_at' => $source_modified_at,
				)
			);

			return true;
		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf(
					'Failed to track synced entity in PostgreSQL: %s ID %s - Error: %s',
					$entity_type,
					(string) $entity_id,
					$e->getMessage()
				),
				'error',
				'sync',
				$this->connection_name,
				array(
					'entity_type' => $entity_type,
					'entity_id'   => $entity_id,
				)
			);
			return false;
		}
	}

	/**
	 * Remove entity metadata from PostgreSQL tracking table.
	 *
	 * @param string $entity_type Entity type.
	 * @param mixed  $entity_id   Entity ID.
	 * @return bool True on success, false on failure.
	 */
	public function remove_metadata( $entity_type, $entity_id ) {
		try {
			$conn = $this->get_pg_db()->get_connection();
			if ( ! $conn ) {
				$this->logger->log( 'No PostgreSQL connection available for metadata removal', 'error', 'sync', $this->connection_name );
				return false;
			}

			$stmt = $conn->prepare(
				"DELETE FROM {$this->pg_table}
				WHERE connection_name = :connection_name
				AND entity_type = :entity_type
				AND entity_id = :entity_id"
			);
			$stmt->execute(
				array(
					':connection_name' => $this->connection_name,
					':entity_type'     => $entity_type,
					':entity_id'       => (string) $entity_id,
				)
			);

			return true;
		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Failed to remove metadata: %s (%s)', $e->getMessage(), $entity_type ),
				'error',
				'sync',
				$this->connection_name
			);
			return false;
		}
	}

	/**
	 * Get validation summary (counts of synced vs unsynced)
	 *
	 * @return array Array with 'should_sync' and 'has_synced' counts.
	 */
	public function get_validation_summary() {
		// Query MySQL for what SHOULD be synced.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection
		$mysql_counts = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT entity_type, 
				SUM(wp_count) as total, 
				SUM(pg_count) as synced, 
				SUM(CASE WHEN drift < 0 THEN ABS(drift) ELSE 0 END) as pending
				FROM {$this->mysql_table}
				WHERE connection_name = %s
				GROUP BY entity_type",
				$this->connection_name
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

		// Query PostgreSQL for what HAS BEEN synced.
		try {
			$conn = $this->get_pg_db()->get_connection();
			if ( $conn ) {
				$stmt = $conn->prepare(
					"SELECT entity_type, COUNT(*) as total
					FROM {$this->pg_table}
					WHERE connection_name = :connection_name
					GROUP BY entity_type"
				);
				$stmt->execute( array( ':connection_name' => $this->connection_name ) );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connection
				$pg_counts = $stmt->fetchAll( PDO::FETCH_ASSOC );
			} else {
				$pg_counts = array();
			}
		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to get PostgreSQL validation summary: ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
			$pg_counts = array();
		}

		return array(
			'mysql_summary' => $mysql_counts,
			'pg_summary'    => $pg_counts,
		);
	}

	/**
	 * Get MySQL-only validation summary (what SHOULD be synced).
	 * Fast method that only queries local MySQL table without PostgreSQL.
	 * Use this for Supabase connections where PostgreSQL data comes from RPC.
	 *
	 * @return array MySQL summary data.
	 */
	public function get_mysql_validation_summary() {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT entity_type, 
				SUM(wp_count) as total, 
				SUM(pg_count) as synced, 
				SUM(CASE WHEN drift < 0 THEN ABS(drift) ELSE 0 END) as pending
				FROM {$this->mysql_table}
				WHERE connection_name = %s
				GROUP BY entity_type",
				$this->connection_name
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get validation summary for a specific post type.
	 *
	 * @param string $post_type Post type to query.
	 * @return array Summary with mysql_summary and pg_summary.
	 */
	public function get_validation_summary_by_post_type( $post_type ) {
		// Query MySQL for what SHOULD be synced (filtered by post_type).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection
		$mysql_counts = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT entity_type, 
				SUM(wp_count) as total, 
				SUM(pg_count) as synced, 
				SUM(CASE WHEN drift < 0 THEN ABS(drift) ELSE 0 END) as pending
				FROM {$this->mysql_table}
				WHERE connection_name = %s 
				AND entity_type = 'post'
				AND post_type = %s
				GROUP BY entity_type",
				$this->connection_name,
				$post_type
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

		// Query PostgreSQL for what HAS BEEN synced (filtered by post_type).
		try {
			$conn = $this->get_pg_db()->get_connection();
			if ( $conn ) {
				$stmt = $conn->prepare(
					"SELECT entity_type, COUNT(*) as total
					FROM {$this->pg_table}
					WHERE connection_name = :connection_name
					AND entity_type = 'post'
					AND post_type = :post_type
					GROUP BY entity_type"
				);
				$stmt->execute(
					array(
						':connection_name' => $this->connection_name,
						':post_type'       => $post_type,
					)
				);
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connection
				$pg_counts = $stmt->fetchAll( PDO::FETCH_ASSOC );
			} else {
				$pg_counts = array();
			}
		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to get PostgreSQL validation summary for post type: ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
			$pg_counts = array();
		}

		return array(
			'mysql_summary' => $mysql_counts,
			'pg_summary'    => $pg_counts,
		);
	}

	/**
	 * Get MySQL-only validation summary for a specific post type.
	 * Fast method that only queries local MySQL table without PostgreSQL.
	 *
	 * @param string $post_type Post type to query.
	 * @return array MySQL summary data for the post type.
	 */
	public function get_mysql_validation_summary_by_post_type( $post_type ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT entity_type, 
				SUM(wp_count) as total, 
				SUM(pg_count) as synced, 
				SUM(CASE WHEN drift < 0 THEN ABS(drift) ELSE 0 END) as pending
				FROM {$this->mysql_table}
				WHERE connection_name = %s 
				AND entity_type = 'post'
				AND post_type = %s
				GROUP BY entity_type",
				$this->connection_name,
				$post_type
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Check if entity is already synced in PostgreSQL
	 *
	 * Used for intelligent sync - skip entities that are already synced
	 *
	 * @since 1.0.0
	 * @param string $entity_type Entity type (term, post, postmeta, etc.).
	 * @param int    $entity_id   Entity ID.
	 * @return bool True if entity exists in PostgreSQL metadata, false otherwise.
	 */
	public function is_entity_synced_in_postgresql( $entity_type, $entity_id ) {
		try {
			$conn = $this->get_pg_db()->get_connection();
			if ( ! $conn ) {
				return false;
			}

			$stmt = $conn->prepare(
				"SELECT COUNT(*) FROM {$this->pg_table}
				WHERE connection_name = :connection_name
				AND entity_type = :entity_type
				AND entity_id = :entity_id"
			);
			$stmt->execute(
				array(
					':connection_name' => $this->connection_name,
					':entity_type'     => $entity_type,
					':entity_id'       => $entity_id,
				)
			);

			return (int) $stmt->fetchColumn() > 0;
		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to check if entity is synced: ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
			return false;
		}
	}

	/**
	 * Mark entity as synced in PostgreSQL metadata
	 *
	 * Called after successful sync to PostgreSQL
	 *
	 * @since 1.0.0
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity ID.
	 * @param array  $extra_data  Optional extra data (post_type, source_id, etc.).
	 * @return bool True on success, false on failure.
	 */
	public function mark_synced_in_postgresql( $entity_type, $entity_id, $extra_data = array() ) {
		// Delegate to existing track_synced method.
		return $this->track_synced( $entity_type, $entity_id, $extra_data );
	}

	/**
	 * Get metadata for an entity from PostgreSQL
	 *
	 * Returns metadata record including source_modified_at for modification detection
	 *
	 * @since 1.0.0
	 * @param string $entity_type Entity type (term, post, postmeta, etc.).
	 * @param int    $entity_id   Entity ID.
	 * @return array|null Metadata record or null if not found.
	 */
	public function get_entity_metadata( $entity_type, $entity_id ) {
		try {
			$conn = $this->get_pg_db()->get_connection();
			if ( ! $conn ) {
				return null;
			}

			$stmt = $conn->prepare(
				"SELECT entity_type, entity_id, post_type, source_id, source_modified_at, synced_at, created_at, updated_at
				FROM {$this->pg_table}
				WHERE connection_name = :connection_name
				AND entity_type = :entity_type
				AND entity_id = :entity_id"
			);
			$stmt->execute(
				array(
					':connection_name' => $this->connection_name,
					':entity_type'     => $entity_type,
					':entity_id'       => $entity_id,
				)
			);

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connection
			$result = $stmt->fetch( PDO::FETCH_ASSOC );
			return $result ? $result : null;
		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to get entity metadata: ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
			return null;
		}
	}

	/**
	 * Check if entity needs resync based on modification timestamp
	 *
	 * Compares source entity's modification timestamp against metadata to determine
	 * if the entity has been modified since last sync.
	 *
	 * @since 1.0.0
	 * @param string $entity_type         Entity type (post, term, etc.).
	 * @param int    $entity_id           Entity ID.
	 * @param string $source_modified_at  Source entity modification timestamp (post_modified_gmt, etc.).
	 * @return bool True if needs resync (not synced or modified since last sync), false if up-to-date.
	 */
	public function needs_resync( $entity_type, $entity_id, $source_modified_at = null ) {
		$metadata = $this->get_entity_metadata( $entity_type, $entity_id );

		// Not synced yet.
		if ( ! $metadata ) {
			return true;
		}

		// No modification timestamp provided - assume needs resync to be safe.
		if ( null === $source_modified_at ) {
			return true;
		}

		// No modification timestamp stored - needs resync.
		if ( empty( $metadata['source_modified_at'] ) ) {
			return true;
		}

		// Compare timestamps: needs resync if source is newer.
		// Convert to timestamps for reliable comparison.
		$source_ts   = strtotime( $source_modified_at );
		$metadata_ts = strtotime( $metadata['source_modified_at'] );

		// If source is newer than what we synced, needs resync.
		return $source_ts > $metadata_ts;
	}

	/**
	 * Update sync stats after batch sync
	 *
	 * Updates aggregate counts in the new schema.
	 *
	 * @since 1.0.0
	 * @param string $entity_type Entity type (term, post, postmeta, term_relationships).
	 * @param string $post_type   Post type (only for entity_type='post').
	 * @return bool True on success, false on failure.
	 */
	public function update_sync_stats( $entity_type, $post_type = null ) {
		// Count from WordPress (source of truth).
		if ( 'term' === $entity_type ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $wpdb->terms is a WordPress core table property, safe from SQL injection
			$wp_count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->wpdb->terms}" );
		} elseif ( 'term_taxonomy' === $entity_type ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $wpdb->term_taxonomy is a WordPress core table property, safe from SQL injection
			$wp_count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->wpdb->term_taxonomy}" );
		} elseif ( 'post' === $entity_type && $post_type ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- $wpdb->posts is a WordPress core table property, safe from SQL injection
			$wp_count = (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'private', 'draft')",
					$post_type
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'postmeta' === $entity_type ) {
			$settings           = new GG_Data_Settings_Manager();
			$enabled_post_types = $settings->get_with_category( 'sync', $this->connection_name, 'sync_enabled_post_types', array( 'post', 'page' ) );
			$enabled_statuses   = $settings->get_with_category( 'sync', $this->connection_name, 'sync_enabled_statuses', array( 'publish' ) );

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

			if ( empty( $enabled_post_types ) ) {
				$wp_count = 0;
			} else {
				$post_types_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
				$statuses_placeholders   = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

				// Simplified count query for performance (skipping complex meta_key filtering for speed).
				// We accept slight inaccuracy in "Total" vs "Synced" for postmeta to avoid 3s+ queries.
				// Real validation happens in the Validator class.
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $post_types_placeholders and $statuses_placeholders are strings of comma-separated '%s' placeholders, safe for interpolation into query string before prepare()
				$query = "SELECT COUNT(pm.meta_id)
					FROM {$this->wpdb->postmeta} pm
					INNER JOIN {$this->wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type IN ($post_types_placeholders)
					AND p.post_status IN ($statuses_placeholders)";

				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with placeholders
				$wp_count = (int) $this->wpdb->get_var(
					$this->wpdb->prepare( $query, array_merge( $enabled_post_types, $enabled_statuses ) )
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}
		} elseif ( 'term_relationships' === $entity_type ) {
			$settings           = new GG_Data_Settings_Manager();
			$enabled_post_types = $settings->get_with_category( 'sync', $this->connection_name, 'sync_enabled_post_types', array( 'post', 'page' ) );
			$enabled_statuses   = $settings->get_with_category( 'sync', $this->connection_name, 'sync_enabled_statuses', array( 'publish' ) );

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

			if ( empty( $enabled_post_types ) ) {
				$wp_count = 0;
			} else {
				$post_types_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
				$statuses_placeholders   = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $post_types_placeholders and $statuses_placeholders are strings of comma-separated '%s' placeholders, safe for interpolation into query string before prepare()
				$query = "SELECT COUNT(*)
					FROM {$this->wpdb->term_relationships} tr
					INNER JOIN {$this->wpdb->posts} p ON tr.object_id = p.ID
					WHERE p.post_type IN ($post_types_placeholders)
					AND p.post_status IN ($statuses_placeholders)";

				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with placeholders
				$wp_count = (int) $this->wpdb->get_var(
					$this->wpdb->prepare( $query, array_merge( $enabled_post_types, $enabled_statuses ) )
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}
		} else {
			$this->logger->log( 'update_sync_stats: Invalid entity_type or missing post_type', 'error', 'sync', $this->connection_name );
			return false;
		}

		// Count from PostgreSQL.
		$pg_count = $this->count_in_postgresql( $entity_type, $post_type );

		// Calculate drift.
		$drift = $pg_count - $wp_count;

		// Check if sync is enabled for this post type.
		$sync_enabled = false;
		if ( 'post' === $entity_type && $post_type ) {
			$settings           = new GG_Data_Settings_Manager();
			$enabled_post_types = $settings->get_with_category( 'sync', $this->connection_name, 'sync_enabled_post_types', array() );

			if ( is_string( $enabled_post_types ) ) {
				$enabled_post_types = maybe_unserialize( $enabled_post_types );
			}

			if ( ! is_array( $enabled_post_types ) ) {
				$enabled_post_types = array();
			}

			$sync_enabled = in_array( $post_type, $enabled_post_types, true );
		}

		// Update or insert aggregate.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is properly prepared with $wpdb->prepare() and array_filter handles optional post_type
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->mysql_table}
				WHERE connection_name = %s AND entity_type = %s AND post_type " . ( $post_type ? '= %s' : 'IS NULL' ),
				array_filter( array( $this->connection_name, $entity_type, $post_type ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// If sync is disabled AND PostgreSQL is empty, delete the tracking row.
		if ( ! $sync_enabled && 0 === $pg_count && $existing ) {
			$this->wpdb->delete(
				$this->mysql_table,
				array( 'id' => $existing ),
				array( '%d' )
			);

			$this->logger->log(
				sprintf(
					'Deleted sync stats row for %s%s (sync disabled, PostgreSQL empty)',
					$entity_type,
					$post_type ? " ({$post_type})" : ''
				),
				'debug',
				'sync',
				$this->connection_name
			);
			return true;
		}

		$data = array(
			'connection_name' => $this->connection_name,
			'entity_type'     => $entity_type,
			'post_type'       => $post_type,
			'wp_count'        => $wp_count,
			'pg_count'        => $pg_count,
			'drift'           => $drift,
			'last_sync_at'    => current_time( 'mysql' ),
			'sync_status'     => 'completed',
		);

		if ( $existing ) {
			$result = $this->wpdb->update(
				$this->mysql_table,
				$data,
				array( 'id' => $existing ),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $this->wpdb->insert(
				$this->mysql_table,
				$data,
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
			);
		}

		if ( false !== $result ) {
			$this->logger->log(
				sprintf(
					'Updated sync stats for %s%s: WP=%d, PG=%d, Drift=%d',
					$entity_type,
					$post_type ? " ({$post_type})" : '',
					$wp_count,
					$pg_count,
					$drift
				),
				'debug',
				'sync',
				$this->connection_name
			);
			return true;
		}

		$this->logger->log( 'Failed to update sync stats: ' . $this->wpdb->last_error, 'error', 'sync', $this->connection_name );
		return false;
	}

	/**
	 * Count entities in PostgreSQL
	 *
	 * @since 1.0.0
	 * @param string $entity_type Entity type (term, post, postmeta, term_relationships).
	 * @param string $post_type   Post type (only for entity_type='post').
	 * @return int Count of entities in PostgreSQL.
	 */
	private function count_in_postgresql( $entity_type, $post_type = null ) {
		$prefix = GG_Data_Table_Prefix_Resolver::runtime_prefix();

		try {
			if ( ! $this->ensure_provider_ready_for_counts() ) {
				return 0;
			}

			if ( ! $this->provider || ! method_exists( $this->provider, 'count_records' ) ) {
				$this->logger->log( 'Provider contract violation: missing count_records()', 'error', 'sync', $this->connection_name );
				return 0;
			}

			if ( 'term' === $entity_type ) {
				$table = $prefix . 'terms';
			} elseif ( 'term_taxonomy' === $entity_type ) {
				$table = $prefix . 'term_taxonomy';
			} elseif ( 'postmeta' === $entity_type ) {
				$table = $prefix . 'postmeta';
			} elseif ( 'term_relationships' === $entity_type ) {
				$table = $prefix . 'term_relationships';
			} elseif ( 'post' === $entity_type ) {
				$table = $prefix . 'posts';
			} else {
				return 0;
			}

			$where  = ( 'post' === $entity_type && $post_type ) ? array( 'post_type' => "eq.$post_type" ) : array();
			$result = $this->provider->count_records( $table, $where );

			return ! empty( $result['success'] ) ? (int) $result['count'] : 0;

		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to count in PostgreSQL: ' . $e->getMessage(), 'error', 'sync', $this->connection_name );
			return 0;
		}
	}

	/**
	 * Ensure provider is available and connected before aggregate counting.
	 *
	 * @return bool True when provider is ready for count operations.
	 */
	private function ensure_provider_ready_for_counts() {
		if ( ! $this->provider ) {
			$this->logger->log( 'Metadata count provider is unavailable', 'error', 'sync', $this->connection_name );
			return false;
		}

		if ( ! method_exists( $this->provider, 'is_connected' ) || $this->provider->is_connected() ) {
			return true;
		}

		if ( ! method_exists( $this->provider, 'connect' ) ) {
			$this->logger->log( 'Metadata count provider cannot connect', 'error', 'sync', $this->connection_name );
			return false;
		}

		$settings_manager = new GG_Data_Settings_Manager();
		$connection       = $settings_manager->get_connection( $this->connection_name );

		if ( empty( $connection ) || ! is_array( $connection ) ) {
			$this->logger->log( 'Metadata count provider connection configuration missing', 'error', 'sync', $this->connection_name );
			return false;
		}

		$connect_result = $this->provider->connect( $connection );
		if ( ! is_array( $connect_result ) || empty( $connect_result['success'] ) ) {
			$message = is_array( $connect_result ) && ! empty( $connect_result['message'] )
				? $connect_result['message']
				: 'Unknown connection error';

			$this->logger->log(
				'Metadata count provider connection failed',
				'error',
				'sync',
				$this->connection_name,
				array(
					'message' => $message,
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Get aggregate sync statistics
	 *
	 * Returns aggregate counts for UI display.
	 *
	 * @since 1.0.0
	 * @return array Array of aggregate stats.
	 */
	public function get_aggregate_stats() {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection
		$stats = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT entity_type, post_type, wp_count, pg_count, drift, last_sync_at, sync_status, enabled
				FROM {$this->mysql_table}
				WHERE connection_name = %s AND enabled = 1
				ORDER BY entity_type, post_type",
				$this->connection_name
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

		return $stats ? $stats : array();
	}

	/**
	 * Get orphaned post types from metadata table
	 *
	 * Finds post types that have PostgreSQL data but are no longer enabled in settings.
	 * Uses metadata table for fast query without needing PostgreSQL connection.
	 *
	 * @since 1.0.0
	 * @param array  $enabled_post_types Array of currently enabled post types.
	 * @param string $connection_name    Connection name.
	 * @return array Associative array of post_type => pg_count for orphaned types.
	 */
	public function get_orphaned_post_types_from_metadata( $enabled_post_types, $connection_name ) {
		// If no enabled types, all types with data are orphaned.
		if ( empty( $enabled_post_types ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection
			$query = $this->wpdb->prepare(
				"SELECT post_type, pg_count 
				FROM {$this->mysql_table} 
				WHERE connection_name = %s 
				AND entity_type = 'post'
				AND pg_count > 0
				AND post_type IS NOT NULL",
				$connection_name
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// Build placeholders for NOT IN clause.
			$placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, placeholders dynamically built for IN clause, both safe from SQL injection
			$query = $this->wpdb->prepare(
				"SELECT post_type, pg_count 
				FROM {$this->mysql_table} 
				WHERE connection_name = %s 
				AND entity_type = 'post'
				AND pg_count > 0
				AND post_type IS NOT NULL
				AND post_type NOT IN ($placeholders)",
				array_merge( array( $connection_name ), $enabled_post_types )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is properly prepared above using $wpdb->prepare() with placeholders
		$results = $this->wpdb->get_results( $query, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$orphaned = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$orphaned[ $row['post_type'] ] = (int) $row['pg_count'];
			}
		}

		return $orphaned;
	}

	/**
	 * Delete all metadata for a specific connection
	 *
	 * @param string $connection_name Connection name.
	 * @return bool True on success, false on failure.
	 */
	public function delete_connection_metadata( $connection_name ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->mysql_table} WHERE connection_name = %s",
				$connection_name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			$this->logger->log(
				sprintf(
					'Failed to delete connection metadata for %s - Error: %s',
					$connection_name,
					$this->wpdb->last_error
				),
				'error',
				'sync',
				$connection_name
			);
			return false;
		}

		return true;
	}
}
