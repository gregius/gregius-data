<?php
/**
 * WordPress Lifecycle Hooks for PostgreSQL Sync
 *
 * @package Gregius_Data
 * @since 1.0.0
 *
 * Handles real-time synchronization between WordPress and PostgreSQL:
 * 1. Post lifecycle: save_post, transition_post_status, before_delete_post
 * 2. Post meta sync: added_post_meta, updated_post_meta, deleted_post_meta (with filtering)
 * 3. Taxonomy lifecycle: created_term, edited_term, delete_term
 * 4. Term relationships: wp_after_insert_post (reliable post-save term association)
 * 5. Fallback: gg_data_orphan_cleanup scheduled task for any missed records
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress lifecycle hooks for real-time PostgreSQL synchronization.
 *
 * @since 1.0.0
 */
class GG_Data_Lifecycle_Hooks {

	/**
	 * The connection manager instance.
	 *
	 * @var GG_Data_Connection_Manager
	 */
	private $connection_manager;

	/**
	 * The settings manager instance.
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * The logger instance.
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * The sync metadata manager instance.
	 *
	 * @var GG_Data_Sync_Metadata_Manager
	 */
	private $metadata_manager;

	/**
	 * Initialize the lifecycle hooks.
	 *
	 * @param GG_Data_Connection_Manager $connection_manager Connection manager instance.
	 * @param GG_Data_Settings_Manager   $settings_manager   Settings manager instance.
	 */
	public function __construct( $connection_manager, $settings_manager ) {
		$this->connection_manager = $connection_manager;
		$this->settings_manager   = $settings_manager;
		$this->logger             = new GG_Data_Logger();

		$this->register_hooks();
	}

	/**
	 * Register WordPress lifecycle hooks.
	 */
	private function register_hooks() {
		// Primary deletion hook - fires before WordPress deletes post.
		add_action( 'before_delete_post', array( $this, 'handle_post_deletion' ), 10, 2 );

		// Automatic sync hook - fires when post is saved/updated.
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );

		// Post status transition hook - fires when post status changes (trash, untrash, etc.).
		add_action( 'transition_post_status', array( $this, 'handle_post_status_transition' ), 10, 3 );

		// Post meta sync hooks.
		add_action( 'added_post_meta', array( $this, 'handle_post_meta_added' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'handle_post_meta_updated' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'handle_post_meta_deleted' ), 10, 4 );

		// Taxonomy sync hooks.
		add_action( 'created_term', array( $this, 'handle_term_created' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'handle_term_edited' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'handle_term_deleted' ), 10, 4 );
		add_action( 'wp_after_insert_post', array( $this, 'handle_post_complete' ), 999, 4 );

		// Orphan detection scheduled task (fallback).
		add_action( 'gg_data_orphan_cleanup', array( $this, 'cleanup_orphaned_records' ) );
	}

	/**
	 * Handle post save/update - sync to PostgreSQL automatically.
	 *
	 * Fires after post is saved to WordPress database.
	 * Validates post status and type before syncing.
	 *
	 * @param int     $post_id Post ID being saved.
	 * @param WP_Post $post    Post object being saved.
	 * @param bool    $update  Whether this is an update or new post.
	 */
	public function handle_post_save( $post_id, $post, $update ) {
		// Prevent infinite loops - check if we're already processing this post.
		static $processing = array();
		if ( isset( $processing[ $post_id ] ) ) {
			return;
		}
		$processing[ $post_id ] = true;

		// Validate post ID.
		if ( empty( $post_id ) ) {
			$this->logger->log( 'Save hook called with empty post ID', 'warning', 'sync' );
			return;
		}

		// Get post object if not provided (handle different hook signatures).
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$this->logger->log( "Could not retrieve post object for ID {$post_id}", 'warning', 'sync', null, array( 'post_id' => $post_id ) );
				return;
			}
		}

		// Skip auto-saves and revisions.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Get active connection.
		$connection_name = $this->get_active_connection();
		if ( empty( $connection_name ) ) {
			$this->logger->log( "No active PostgreSQL connection - skipping sync for post {$post_id}", 'info', 'sync', null, array( 'post_id' => $post_id ) );
			return;
		}

		// Check if post type should be synced.
		if ( ! $this->is_post_type_synced( $post->post_type, $connection_name ) ) {
			$this->logger->log(
				"Post type '{$post->post_type}' not synced - skipping for post {$post_id}",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'   => $post_id,
					'post_type' => $post->post_type,
				)
			);
			return;
		}

		/**
		 * Filter whether a specific post should be synced.
		 *
		 * Allows post-type-level or per-post exclusion from real-time sync.
		 * Used by interaction tracking to exclude gg_interaction posts.
		 *
		 * @since 1.0.0
		 * @param bool $should_sync Whether the post should be synced. Default true.
		 * @param int  $post_id     Post ID.
		 */
		if ( ! apply_filters( 'gg_data_should_sync_post', true, $post_id ) ) {
			$this->logger->log( "Post {$post_id} excluded from sync by filter", 'debug', 'sync', $connection_name, array( 'post_id' => $post_id ) );
			return;
		}

		// Handle sync based on post status.
		if ( $this->is_post_status_synced( $post->post_status, $connection_name ) ) {
			// Sync to PostgreSQL with error handling to prevent blocking WordPress saves.
			$this->logger->log(
				"Syncing post {$post_id} with status '{$post->post_status}' to PostgreSQL",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'     => $post_id,
					'post_status' => $post->post_status,
				)
			);

			try {
				$this->sync_to_postgresql( $post, $connection_name, $update );
			} catch ( Exception $sync_error ) {
				// CRITICAL: Never let PostgreSQL sync errors block WordPress post saves.
				$this->logger->log(
					sprintf(
						'PostgreSQL sync failed for post %d: %s (WordPress save unaffected)',
						$post_id,
						$sync_error->getMessage()
					),
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post_id,
						'error'   => $sync_error->getMessage(),
					)
				);
			}
		} else {
			// Remove from PostgreSQL if it exists there.
			$this->logger->log(
				"Post {$post_id} status '{$post->post_status}' not synced - removing from PostgreSQL if exists",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'     => $post_id,
					'post_status' => $post->post_status,
				)
			);

			try {
				$this->delete_from_postgresql( $post_id, $connection_name );
			} catch ( Exception $delete_error ) {
				// Log deletion errors but don't block WordPress saves.
				$this->logger->log(
					sprintf(
						'PostgreSQL deletion failed for post %d: %s (WordPress save unaffected)',
						$post_id,
						$delete_error->getMessage()
					),
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post_id,
						'error'   => $delete_error->getMessage(),
					)
				);
			}
		}

		// Clean up processing flag.
		static $processing;
		unset( $processing[ $post_id ] );
	}

	/**
	 * Handle post status transitions (trash, untrash, publish, etc.).
	 *
	 * Fires when post status changes, including trash/untrash operations.
	 * Complements save_post hook which doesn't fire for trash operations.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function handle_post_status_transition( $new_status, $old_status, $post ) {
		// Skip if status didn't actually change.
		if ( $new_status === $old_status ) {
			return;
		}

		// Skip auto-saves and revisions.
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		// Skip new post creation (old_status='new' means the post was just created).
		// There's nothing to delete from PostgreSQL for a brand new post.
		if ( 'new' === $old_status ) {
			return;
		}

		// Get active connection.
		$connection_name = $this->get_active_connection();
		if ( empty( $connection_name ) ) {
			return;
		}

		// Check if post should be synced (allows filtering out specific post types).
		if ( ! apply_filters( 'gg_data_should_sync_post', true, $post->ID ) ) {
			return;
		}

		// Check if post type is configured for sync.
		if ( ! $this->is_post_type_synced( $post->post_type, $connection_name ) ) {
			$this->logger->log(
				"Post type '{$post->post_type}' not synced - skipping status transition for post {$post->ID}",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'   => $post->ID,
					'post_type' => $post->post_type,
				)
			);
			return;
		}

		$this->logger->log(
			"Post {$post->ID} status transition: '{$old_status}' → '{$new_status}'",
			'debug',
			'sync',
			$connection_name,
			array(
				'post_id'    => $post->ID,
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);

		// Handle sync based on new status.
		if ( $this->is_post_status_synced( $new_status, $connection_name ) ) {
			// New status is syncable - sync/update post.
			$this->logger->log(
				"Syncing post {$post->ID} after status change to '{$new_status}'",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'    => $post->ID,
					'new_status' => $new_status,
				)
			);

			try {
				$this->sync_to_postgresql( $post, $connection_name, true );
			} catch ( Exception $sync_error ) {
				$this->logger->log(
					sprintf(
						'PostgreSQL sync failed for post %d status transition: %s',
						$post->ID,
						$sync_error->getMessage()
					),
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post->ID,
						'error'   => $sync_error->getMessage(),
					)
				);
			}
		} else {
			// New status is NOT syncable - delete from PostgreSQL.
			$this->logger->log(
				"Deleting post {$post->ID} from PostgreSQL after status change to '{$new_status}'",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'    => $post->ID,
					'new_status' => $new_status,
				)
			);

			try {
				$this->delete_from_postgresql( $post->ID, $connection_name );
			} catch ( Exception $delete_error ) {
				$this->logger->log(
					sprintf(
						'PostgreSQL deletion failed for post %d status transition: %s',
						$post->ID,
						$delete_error->getMessage()
					),
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post->ID,
						'error'   => $delete_error->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Handle post deletion - remove from PostgreSQL immediately.
	 *
	 * Fires when WordPress is about to delete a post permanently.
	 * Does NOT fire when post is moved to trash (can be restored).
	 *
	 * @param int     $post_id Post ID being deleted.
	 * @param WP_Post $post    Post object being deleted.
	 */
	public function handle_post_deletion( $post_id, $post ) {
		// Validate post ID.
		if ( empty( $post_id ) ) {
			$this->logger->log( 'Lifecycle hook called with empty post ID', 'warning', 'sync' );
			return;
		}

		// Get active connection.
		$connection_name = $this->get_active_connection();
		if ( empty( $connection_name ) ) {
			$this->logger->log( "No active PostgreSQL connection - skipping deletion for post {$post_id}", 'info', 'sync', null, array( 'post_id' => $post_id ) );
			return;
		}

		// Check if this post type is synced to PostgreSQL.
		if ( ! $this->is_post_type_synced( $post->post_type, $connection_name ) ) {
			$this->logger->log(
				"Post type '{$post->post_type}' not synced - skipping deletion for post {$post_id}",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'   => $post_id,
					'post_type' => $post->post_type,
				)
			);
			return;
		}

		// Delete from PostgreSQL.
		$this->delete_from_postgresql( $post_id, $connection_name );
	}

	/**
	 * Handle post meta addition - sync new metadata to PostgreSQL.
	 *
	 * Fires when new post meta is added via add_post_meta() or update_post_meta().
	 * Automatically handles ACF fields, Yoast SEO, WooCommerce, and all plugin metadata.
	 *
	 * @param int    $meta_id    Meta ID of the added metadata.
	 * @param int    $post_id    Post ID the metadata belongs to.
	 * @param string $meta_key   Meta key of the added metadata.
	 * @param mixed  $meta_value Meta value of the added metadata.
	 */
	public function handle_post_meta_added( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->sync_post_meta_to_postgresql( $meta_id, $post_id, $meta_key, $meta_value, 'added' );
	}

	/**
	 * Handle post meta update - sync updated metadata to PostgreSQL.
	 *
	 * Fires when existing post meta is updated via update_post_meta().
	 * Automatically handles ACF fields, Yoast SEO, WooCommerce, and all plugin metadata.
	 *
	 * @param int    $meta_id    Meta ID of the updated metadata.
	 * @param int    $post_id    Post ID the metadata belongs to.
	 * @param string $meta_key   Meta key of the updated metadata.
	 * @param mixed  $meta_value Meta value of the updated metadata.
	 */
	public function handle_post_meta_updated( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->sync_post_meta_to_postgresql( $meta_id, $post_id, $meta_key, $meta_value, 'updated' );
	}

	/**
	 * Handle post meta deletion - remove metadata from PostgreSQL.
	 *
	 * Fires when post meta is deleted via delete_post_meta().
	 * Automatically handles ACF fields, Yoast SEO, WooCommerce, and all plugin metadata.
	 *
	 * @param array  $meta_ids   Array of meta IDs being deleted.
	 * @param int    $post_id    Post ID the metadata belongs to.
	 * @param string $meta_key   Meta key of the deleted metadata.
	 * @param mixed  $_meta_value Meta value of the deleted metadata (unused; required by hook signature).
	 */
	public function handle_post_meta_deleted( $meta_ids, $post_id, $meta_key, $_meta_value ) {
		unset( $_meta_value ); // Required by hook signature but not used in delete flow.

		// Get active connection.
		$connection_name = $this->get_active_connection();
		if ( empty( $connection_name ) ) {
			return;
		}

		// Check if parent post's type is configured for sync.
		$post = get_post( $post_id );
		if ( ! $post || ! $this->is_post_type_synced( $post->post_type, $connection_name ) ) {
			return;
		}

		// WordPress passes array of meta IDs for bulk deletion.
		if ( is_array( $meta_ids ) ) {
			foreach ( $meta_ids as $meta_id ) {
				$this->delete_post_meta_from_postgresql( $meta_id, $post_id, $meta_key );
			}
		} else {
			$this->delete_post_meta_from_postgresql( $meta_ids, $post_id, $meta_key );
		}
	}

	/**
	 * Sync post meta to PostgreSQL database.
	 *
	 * Performs upsert operation for post metadata using existing database infrastructure.
	 * CRITICAL: Never blocks WordPress saves - all PostgreSQL operations are non-blocking.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param string $operation  Operation type ('added' or 'updated').
	 * @return bool Success status (for logging only - doesn't affect WordPress).
	 */
	private function sync_post_meta_to_postgresql( $meta_id, $post_id, $meta_key, $meta_value, $operation ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- False positive: meta_key is a variable/array key for logging, not a WP_Query argument.
		try {
			// Debug logging.
			$this->logger->log( "Meta sync starting: {$operation} meta_id={$meta_id}, post_id={$post_id}, key={$meta_key}", 'debug', 'sync' );

			// Skip meta keys that shouldn't be synced (cache, temp data, etc.).
			if ( $this->should_skip_meta_key( $meta_key ) ) {
				$this->logger->log(
					"Skipping meta key '{$meta_key}' for post {$post_id} (filtered out)",
					'debug',
					'sync',
					null,
					array(
						'post_id'  => $post_id,
						'meta_key' => $meta_key,
					)
				);
				return false;
			}

			// Get active connection.
			$connection_name = $this->get_active_connection();
			if ( empty( $connection_name ) ) {
				$this->logger->log( "No active PostgreSQL connection - skipping meta sync for post {$post_id}", 'info', 'sync', null, array( 'post_id' => $post_id ) );
				return false;
			}

			// Check if the parent post is synced to this connection.
			if ( ! $this->is_post_synced_to_connection( $post_id, $connection_name ) ) {
				$this->logger->log( "Post {$post_id} not synced to connection '{$connection_name}' - skipping meta sync", 'debug', 'sync', $connection_name, array( 'post_id' => $post_id ) );
				return false;
			}

			// Initialize database.
			$db         = new GG_Data_DB();
			$connection = $db->get_connection( $connection_name );

			// PostgREST provider: get_connection() returns false (no PDO) but the provider is initialised.
			// Delegate directly to the provider's HTTP-based upsert.
			if ( ! $connection && $db->is_postgrest_connection( $connection_name ) ) {
				$provider = $db->get_provider();
				if ( ! $provider ) {
					$this->logger->log( "PostgREST provider unavailable for meta sync on connection '{$connection_name}'", 'error', 'sync', $connection_name );
					return false;
				}
				$result = $provider->upsert_post_meta( $post_id, $meta_key, $meta_value, 1, $connection_name );
				if ( $result ) {
					$this->logger->log(
						"Successfully {$operation} post meta {$meta_id} (key: {$meta_key}) for post {$post_id} via PostgREST",
						'info',
						'sync',
						$connection_name,
						array(
							'post_id'  => $post_id,
							'meta_id'  => $meta_id,
							'meta_key' => $meta_key,
						)
					);
				} else {
					$this->logger->log(
						"Failed to {$operation} post meta {$meta_id} for post {$post_id} via PostgREST",
						'error',
						'sync',
						$connection_name,
						array(
							'post_id'  => $post_id,
							'meta_id'  => $meta_id,
							'meta_key' => $meta_key,
						)
					);
				}
				return $result;
			}

			if ( ! $connection ) {
				$this->logger->log( "Failed to get database connection '{$connection_name}' for meta sync", 'error', 'sync', $connection_name );
				return false;
			}

			// Test the connection with a simple query.
			try {
				$test_stmt   = $connection->query( 'SELECT 1' );
				$test_result = $test_stmt->fetchColumn();
			} catch ( Exception $e ) {
				$this->logger->log( 'Connection test failed for meta sync: ' . $e->getMessage(), 'error', 'sync', $connection_name, array( 'error' => $e->getMessage() ) );
				return false;
			}

			// Check if the DB object and method exist.
			if ( ! method_exists( $db, 'upsert_post_meta' ) ) {
				$this->logger->log( 'upsert_post_meta method does not exist on DB object', 'error', 'sync', $connection_name );
				return false;
			}

			try {
				// Sync meta to PostgreSQL using existing infrastructure.
				$result = $db->upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, 1, $connection_name );
			} catch ( Exception $e ) {
				$this->logger->log(
					'Exception during meta sync: ' . $e->getMessage(),
					'error',
					'sync',
					$connection_name,
					array(
						'post_id'  => $post_id,
						'meta_key' => $meta_key,
						'error'    => $e->getMessage(),
					)
				);
				$result = false;
			}

			if ( $result ) {
				$this->logger->log(
					"Successfully {$operation} post meta {$meta_id} (key: {$meta_key}) for post {$post_id} in PostgreSQL",
					'info',
					'sync',
					$connection_name,
					array(
						'post_id'  => $post_id,
						'meta_id'  => $meta_id,
						'meta_key' => $meta_key,
					)
				);
				return true;
			} else {
				$this->logger->log(
					"Failed to {$operation} post meta {$meta_id} for post {$post_id} in PostgreSQL",
					'error',
					'sync',
					$connection_name,
					array(
						'post_id'  => $post_id,
						'meta_id'  => $meta_id,
						'meta_key' => $meta_key,
					)
				);
				return false;
			}
		} catch ( Exception $e ) {
			// CRITICAL: Never let PostgreSQL errors block WordPress meta operations.
			$this->logger->log(
				sprintf(
					'PostgreSQL meta sync failed for post %d meta %s: %s (WordPress meta operation completed normally)',
					$post_id,
					$meta_key,
					$e->getMessage()
				),
				'error',
				'sync',
				null,
				array(
					'post_id'  => $post_id,
					'meta_key' => $meta_key,
					'error'    => $e->getMessage(),
				)
			);

			return false;
		}
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}

	/**
	 * Delete post meta from PostgreSQL database.
	 *
	 * Removes post metadata from PostgreSQL when deleted from WordPress.
	 * CRITICAL: Never blocks WordPress deletions - all PostgreSQL operations are non-blocking.
	 *
	 * @param int    $meta_id  Meta ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return bool Success status (for logging only - doesn't affect WordPress).
	 */
	private function delete_post_meta_from_postgresql( $meta_id, $post_id, $meta_key ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- False positive: meta_key is a variable/array key for logging, not a WP_Query argument.
		try {
			// Debug logging.
			$this->logger->log( "Meta delete starting: meta_id={$meta_id}, post_id={$post_id}, key={$meta_key}", 'debug', 'sync' );

			// Get active connection.
			$connection_name = $this->get_active_connection();
			if ( empty( $connection_name ) ) {
				$this->logger->log( "No active PostgreSQL connection - skipping meta deletion for post {$post_id}", 'info', 'sync', null, array( 'post_id' => $post_id ) );
				return false;
			}

			// Use DB class instead of connection_manager for consistency.
			$db         = new GG_Data_DB();
			$connection = $db->get_connection( $connection_name );

			// PostgREST provider: get_connection() returns false (no PDO) but the provider is initialised.
			// Delegate directly to the provider's HTTP-based delete.
			if ( ( ! $connection || ! is_object( $connection ) ) && $db->is_postgrest_connection( $connection_name ) ) {
				$provider = $db->get_provider();
				if ( ! $provider ) {
					$this->logger->log( "PostgREST provider unavailable for meta deletion on connection '{$connection_name}'", 'error', 'sync', $connection_name, array( 'post_id' => $post_id ) );
					return false;
				}
				$result = $provider->delete_post_meta( $meta_id );
				if ( isset( $result['success'] ) && $result['success'] ) {
					$this->logger->log(
						"Successfully deleted post meta {$meta_id} (key: {$meta_key}) for post {$post_id} via PostgREST",
						'info',
						'sync',
						$connection_name,
						array(
							'post_id'  => $post_id,
							'meta_id'  => $meta_id,
							'meta_key' => $meta_key,
						)
					);
					return true;
				} else {
					$message = isset( $result['message'] ) ? $result['message'] : 'unknown error';
					$this->logger->log(
						"Failed to delete post meta {$meta_id} via PostgREST: {$message}",
						'error',
						'sync',
						$connection_name,
						array(
							'post_id'  => $post_id,
							'meta_id'  => $meta_id,
							'meta_key' => $meta_key,
						)
					);
					return false;
				}
			}

			if ( ! $connection || ! is_object( $connection ) ) {
				$this->logger->log( "Failed to get database connection '{$connection_name}' for meta deletion. Connection: " . gettype( $connection ), 'error', 'sync', $connection_name, array( 'post_id' => $post_id ) );
				return false;
			}

			// Prepare delete statement.
			$stmt = $connection->prepare( 'DELETE FROM wp_postmeta WHERE meta_id = :meta_id AND post_id = :post_id AND meta_key = :meta_key' );
			// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$stmt->bindValue( ':meta_id', $meta_id, PDO::PARAM_INT );
			$stmt->bindValue( ':post_id', $post_id, PDO::PARAM_INT );
			$stmt->bindValue( ':meta_key', $meta_key, PDO::PARAM_STR );
			// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO

			// Execute deletion.
			$result = $stmt->execute();

			if ( $result ) {
				$rows_affected = $stmt->rowCount();

				if ( $rows_affected > 0 ) {
					$this->logger->log(
						"Successfully deleted post meta {$meta_id} (key: {$meta_key}) for post {$post_id} from PostgreSQL",
						'info',
						'sync',
						$connection_name,
						array(
							'post_id'  => $post_id,
							'meta_id'  => $meta_id,
							'meta_key' => $meta_key,
						)
					);
				} else {
					$this->logger->log(
						"Post meta {$meta_id} not found in PostgreSQL - may not have been synced",
						'debug',
						'sync',
						$connection_name,
						array(
							'post_id' => $post_id,
							'meta_id' => $meta_id,
						)
					);
				}

				return true;
			} else {
				$error_info = $stmt->errorInfo();
				$this->logger->log(
					"Failed to delete post meta {$meta_id}: {$error_info[2]}",
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post_id,
						'meta_id' => $meta_id,
						'error'   => $error_info[2],
					)
				);
				return false;
			}
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
		} catch ( PDOException $e ) {
			$this->logger->log(
				"PostgreSQL meta deletion error for post {$post_id} meta {$meta_key}: " . $e->getMessage(),
				'error',
				'sync',
				null,
				array(
					'post_id'  => $post_id,
					'meta_key' => $meta_key,
					'error'    => $e->getMessage(),
				)
			);
			return false;
		}
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}

	/**
	 * Check if meta key should be skipped from sync.
	 *
	 * Uses existing filtering logic from base class with plugin-specific exclusions.
	 * Filters out cache data, temporary data, and internal WordPress meta.
	 *
	 * @param string $meta_key Meta key to check.
	 * @return bool True if should skip, false if should sync.
	 */
	private function should_skip_meta_key( $meta_key ) {
		// Skip keys that start with underscore (WordPress internal meta) by default.
		$skip_underscore = true;

		// Keys to always skip (cache, temp data, etc.).
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
			'_wp_attached_file',
			'_wp_attachment_metadata',
		);

		/**
		 * Filter the meta keys to skip during PostgreSQL sync.
		 *
		 * Allows developers to exclude additional meta keys from being synced,
		 * or remove keys from the default skip list.
		 *
		 * @since 1.0.0
		 *
		 * @param array $skip_keys Array of meta keys to skip.
		 * @return array Filtered array of meta keys to skip.
		 */
		$skip_keys = apply_filters( 'gg_data_skip_meta_keys', $skip_keys );

		// Check if key should be skipped.
		$should_skip = in_array( $meta_key, $skip_keys, true );

		// Skip underscore keys unless specifically allowed.
		if ( $skip_underscore && strpos( $meta_key, '_' ) === 0 ) {
			// Allow some important underscore keys (SEO, ACF, etc.).
			$allowed_underscore_keys = array(
				'_yoast_wpseo_title',
				'_yoast_wpseo_metadesc',
				'_thumbnail_id',
				'_wp_page_template',
			);

			// Allow ACF fields (start with field_ or are ACF format).
			if ( strpos( $meta_key, 'field_' ) === 0 || strpos( $meta_key, '_field_' ) !== false ) {
				$should_skip = false;
			} elseif ( in_array( $meta_key, $allowed_underscore_keys, true ) ) {
				$should_skip = false;
			} else {
				$should_skip = true;
			}
		}

		return $should_skip;
	}

	/**
	 * Check if post is synced to a specific connection.
	 *
	 * Verifies that the post exists in PostgreSQL for the given connection.
	 *
	 * @param int    $post_id         Post ID to check.
	 * @param string $connection_name Connection name.
	 * @return bool True if post exists in PostgreSQL, false otherwise.
	 */
	private function is_post_synced_to_connection( $post_id, $connection_name ) {
		try {
			// Use GG_Data_DB to get proper PDO connection.
			$db   = new GG_Data_DB();
			$conn = $db->get_connection( $connection_name );
			if ( ! $conn ) {
				return false;
			}

			// Check if post exists in PostgreSQL (use wp_posts table name).
			$stmt = $conn->prepare( 'SELECT COUNT(*) FROM wp_posts WHERE ID = :post_id' );
			// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO required for PostgreSQL, $wpdb does not support PostgreSQL.
			$stmt->bindValue( ':post_id', $post_id, \PDO::PARAM_INT );
			// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
			$stmt->execute();

			$count = $stmt->fetchColumn();
			return $count > 0;      } catch ( PDOException $e ) {
			$this->logger->log(
				"Error checking post sync status for post {$post_id}: " . $e->getMessage(),
				'error',
				'sync',
				null,
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				)
			);
			return false;
			}
	}

	/**
	 * Delete post from PostgreSQL database.
	 *
	 * Removes post from posts table and all vector tables (CASCADE).
	 * Uses the provider interface to support both PDO and PostgREST connections.
	 *
	 * @param int    $post_id         Post ID to delete.
	 * @param string $connection_name Connection name.
	 * @return bool Success status.
	 */
	private function delete_from_postgresql( $post_id, $connection_name ) {
		try {
			// Use Connection Manager to get the appropriate provider.
			$connection_manager = new GG_Data_Connection_Manager();
			$provider           = $connection_manager->get_provider( $connection_name );

			if ( ! $provider ) {
				$this->logger->log( "Failed to get provider for connection '{$connection_name}' for deletion", 'error', 'sync', $connection_name, array( 'post_id' => $post_id ) );
				return false;
			}

			// Use provider's delete_post method (works for both PDO and PostgREST).
			$result = $provider->delete_post( $post_id );

			if ( $result['success'] ) {
				$this->logger->log( "Successfully deleted post {$post_id} from PostgreSQL (connection: {$connection_name})", 'info', 'sync', $connection_name, array( 'post_id' => $post_id ) );

				// Clean up metadata entry to keep sync metadata accurate.
				$metadata_manager = new GG_Data_Sync_Metadata_Manager( $connection_name );
				$metadata_manager->remove_metadata( 'post', $post_id );

				return true;
			} else {
				$message = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
				$this->logger->log(
					"Failed to delete post {$post_id}: {$message}",
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post_id,
						'error'   => $message,
					)
				);
				return false;
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				"PostgreSQL deletion error for post {$post_id}: " . $e->getMessage(),
				'error',
				'sync',
				$connection_name,
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Sync post to PostgreSQL database.
	 *
	 * Performs upsert operation (insert or update) and triggers content cleaning.
	 * CRITICAL: Never blocks WordPress saves - all PostgreSQL operations are non-blocking.
	 *
	 * @param WP_Post $post            Post object to sync.
	 * @param string  $connection_name Connection name.
	 * @param bool    $is_update       Whether this is an update operation.
	 * @return bool Success status (for logging only - doesn't affect WordPress save).
	 */
	private function sync_to_postgresql( $post, $connection_name, $is_update = false ) {
		try {
			// Initialize database and content cleaner.
			$db              = new GG_Data_DB();
			$content_cleaner = new GG_Data_Content_Cleaner();

			// Establish connection before using DB methods.
			// For PostgREST connections, get_connection() returns false but the provider is still valid.
			$connection = $db->get_connection( $connection_name );
			if ( ! $connection && ! $db->is_postgrest_connection( $connection_name ) ) {
				$this->logger->log( "No connection available for {$connection_name}", 'error', 'sync', $connection_name );
				return;
			}

			// Store ORIGINAL content in wp_posts (true WordPress mirror).
			$result = $db->upsert_post( $post, 1, $connection_name );           if ( ! $result ) {
				$this->logger->log( "Failed to sync post {$post->ID} to PostgreSQL (WordPress save unaffected)", 'error', 'sync', $connection_name, array( 'post_id' => $post->ID ) );
				return false;
			}

			// Track post in metadata with modification timestamp for intelligent resync.
			if ( ! isset( $this->metadata_manager ) || ! $this->metadata_manager ) {
				$this->metadata_manager = new GG_Data_Sync_Metadata_Manager( $connection_name );
			}

			$this->metadata_manager->track_synced(
				'post',
				$post->ID,
				array(
					'post_type'          => $post->post_type,
					'source_modified_at' => $post->post_modified_gmt,
				)
			);

			// Term relationship sync now handled by wp_after_insert_post hook.
			// This ensures term relationships are synced after WordPress has completely finished.
			// processing the post, including all meta data and term assignments.

			// Clean content for search/vectors/AI features.
			// Re-enabled with enhanced error logging to debug 500 error.
			try {
				$this->logger->log( "Starting content cleaning for post {$post->ID}", 'debug', 'sync', $connection_name, array( 'post_id' => $post->ID ) );            $clean_result = $content_cleaner->clean_post(
					$post->ID,
					$post->post_title,
					$post->post_content,
					$post->post_excerpt,
					$connection_name
				);

				if ( $clean_result ) {
					$this->logger->log( "Successfully cleaned post {$post->ID}", 'debug', 'sync', $connection_name, array( 'post_id' => $post->ID ) );
				} else {
					$this->logger->log( "Content cleaning returned false for post {$post->ID}", 'warning', 'sync', $connection_name, array( 'post_id' => $post->ID ) );
				}
			} catch ( Exception $clean_error ) {
				// Log cleaning failure but don't fail the sync.
				$this->logger->log(
					sprintf(
						'Failed to clean post %d: %s (post synced but needs reconciliation)',
						$post->ID,
						$clean_error->getMessage()
					),
					'warning',
					'sync',
					$connection_name,
					array(
						'post_id' => $post->ID,
						'error'   => $clean_error->getMessage(),
					)
				);
				// Also log stack trace for debugging.
				$this->logger->log( 'Stack trace: ' . $clean_error->getTraceAsString(), 'debug', 'sync', $connection_name );
			} catch ( Error $clean_error ) {
				// Catch PHP 7+ Error objects (fatal errors that can be caught).
				$this->logger->log(
					sprintf(
						'PHP Error during cleaning post %d: %s',
						$post->ID,
						$clean_error->getMessage()
					),
					'error',
					'sync',
					$connection_name,
					array(
						'post_id' => $post->ID,
						'error'   => $clean_error->getMessage(),
					)
				);
				$this->logger->log( 'Stack trace: ' . $clean_error->getTraceAsString(), 'debug', 'sync', $connection_name );
			}           $action = $is_update ? 'updated' : 'created';
			$this->logger->log(
				"Successfully {$action} post {$post->ID} in PostgreSQL (connection: {$connection_name})",
				'info',
				'sync',
				$connection_name,
				array(
					'post_id' => $post->ID,
					'action'  => $action,
				)
			);

			return true;

		} catch ( Exception $e ) {
			// CRITICAL: Never let PostgreSQL errors block WordPress saves.
			$this->logger->log(
				sprintf(
					'PostgreSQL sync failed for post %d: %s (WordPress save completed normally)',
					$post->ID,
					$e->getMessage()
				),
				'error',
				'sync',
				$connection_name,
				array(
					'post_id' => $post->ID,
					'error'   => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Check if post status is configured for sync to PostgreSQL.
	 *
	 * @param string $post_status     Post status to check.
	 * @param string $connection_name Connection name.
	 * @return bool True if synced, false otherwise.
	 */
	private function is_post_status_synced( $post_status, $connection_name ) {
		// Get enabled post statuses from sync settings.
		$enabled_statuses = $this->settings_manager->get_with_category(
			'sync',
			$connection_name,
			'sync_enabled_statuses',
			array( 'publish', 'draft', 'private', 'pending', 'future' ) // Default to 100% sync.
		);

		// Handle serialized array.
		if ( is_string( $enabled_statuses ) ) {
			$enabled_statuses = maybe_unserialize( $enabled_statuses );
		}

		if ( ! is_array( $enabled_statuses ) ) {
			$enabled_statuses = array( 'publish' );
		}

		return in_array( $post_status, $enabled_statuses, true );
	}

	/**
	 * Cleanup orphaned records (fallback mechanism).
	 *
	 * Identifies posts in PostgreSQL that no longer exist in WordPress.
	 * Scheduled task runs periodically to catch any missed deletions.
	 */
	public function cleanup_orphaned_records() {
		$connection_name = $this->get_active_connection();
		if ( empty( $connection_name ) ) {
			return;
		}

		try {
			// Use the provider to get all post IDs - works with both PDO and PostgREST.
			$provider = GG_Data_Provider_Factory::create( $connection_name );
			if ( ! $provider ) {
				$this->logger->log( 'Orphan cleanup: Failed to create provider', 'error', 'sync', $connection_name );
				return;
			}

			// Use the get_ids method to retrieve post IDs from PostgreSQL.
			$result = $provider->get_ids( 'posts', 1000, 0, array(), 'id' );

			if ( is_wp_error( $result ) ) {
				$this->logger->log( 'Orphan cleanup: ' . $result->get_error_message(), 'error', 'sync', $connection_name, array( 'error' => $result->get_error_message() ) );
				return;
			}

			$pg_posts = $result;

			if ( empty( $pg_posts ) ) {
				$this->logger->log( 'No posts found in PostgreSQL for orphan cleanup', 'debug', 'sync', $connection_name );
				return;
			}

			$orphaned_count = 0;

			// Check each PostgreSQL post against WordPress.
			foreach ( $pg_posts as $post_id ) {
				$wp_post = get_post( $post_id );

				// Post doesn't exist in WordPress - orphaned record.
				if ( ! $wp_post || 'trash' === $wp_post->post_status ) {
					$provider->delete_post( $post_id );
					++$orphaned_count;
				}
			}

			if ( $orphaned_count > 0 ) {
				$this->logger->log( "Orphan cleanup: Removed {$orphaned_count} orphaned records from PostgreSQL", 'info', 'sync', $connection_name, array( 'orphaned_count' => $orphaned_count ) );
			} else {
				$this->logger->log( 'Orphan cleanup: No orphaned records found', 'debug', 'sync', $connection_name );
			}
		} catch ( Exception $e ) {
			$this->logger->log( 'Orphan cleanup error: ' . $e->getMessage(), 'error', 'sync', $connection_name, array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Get the first active PostgreSQL connection name.
	 *
	 * Returns the first connection marked as active. For operations that need
	 * to sync to all active connections, use get_active_connections() instead.
	 *
	 * @return string|null Active connection name or null.
	 */
	private function get_active_connection() {
		$connections = $this->settings_manager->get_all_connections();

		foreach ( $connections as $connection_name => $connection_config ) {
			// Handle multiple is_active formats: serialized boolean (b:1;), string ('1'), or native bool.
			$is_active_value = $connection_config['is_active'] ?? '';
			$is_active       = ( 'b:1;' === $is_active_value || '1' === $is_active_value || true === $is_active_value );

			if ( $is_active ) {
				return $connection_name;
			}
		}

		return null;
	}

	/**
	 * Check if post type is configured for sync to PostgreSQL.
	 *
	 * @param string $post_type       Post type to check.
	 * @param string $connection_name Connection name.
	 * @return bool True if synced, false otherwise.
	 */
	private function is_post_type_synced( $post_type, $connection_name ) {
		// Get enabled post types from sync settings.
		$enabled_post_types = $this->settings_manager->get_with_category(
			'sync',
			$connection_name,
			'sync_enabled_post_types',
			array()
		);

		// Handle serialized array.
		if ( is_string( $enabled_post_types ) ) {
			$enabled_post_types = maybe_unserialize( $enabled_post_types );
		}

		if ( ! is_array( $enabled_post_types ) ) {
			$enabled_post_types = array();
		}

		return in_array( $post_type, $enabled_post_types, true );
	}

	/**
	 * Schedule orphan cleanup task if not already scheduled.
	 */
	public static function schedule_orphan_cleanup() {
		if ( ! wp_next_scheduled( 'gg_data_orphan_cleanup' ) ) {
			// Schedule daily cleanup at 3 AM.
			wp_schedule_event( strtotime( 'tomorrow 3:00 AM' ), 'daily', 'gg_data_orphan_cleanup' );
		}
	}

	/**
	 * Unschedule orphan cleanup task.
	 */
	public static function unschedule_orphan_cleanup() {
		$timestamp = wp_next_scheduled( 'gg_data_orphan_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'gg_data_orphan_cleanup' );
		}
	}

	/**
	 * Handle term creation - sync new term to PostgreSQL.
	 *
	 * Fires when a new term is created in WordPress.
	 * Automatically syncs term and term_taxonomy to PostgreSQL.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	public function handle_term_created( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->should_sync_terms() ) {
			return;
		}

		$this->logger->log(
			"Term created: #{$term_id} (taxonomy: {$taxonomy})",
			'debug',
			'sync',
			null,
			array(
				'term_id'  => $term_id,
				'taxonomy' => $taxonomy,
			)
		);

		try {
			// Include taxonomy sync class.
			require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';

			$taxonomy_sync = new GG_Data_Taxonomy_Sync();
			$result        = $taxonomy_sync->sync_single_term( $term_id, $taxonomy );

			if ( $result ) {
				$this->logger->log(
					"Successfully synced new term #{$term_id} to PostgreSQL",
					'info',
					'sync',
					null,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			} else {
				$this->logger->log(
					"Failed to sync new term #{$term_id} to PostgreSQL",
					'error',
					'sync',
					null,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				"Error syncing new term #{$term_id}: " . $e->getMessage(),
				'error',
				'sync',
				null,
				array(
					'term_id'  => $term_id,
					'taxonomy' => $taxonomy,
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle term edit - update term in PostgreSQL.
	 *
	 * Fires when a term is updated in WordPress.
	 * Updates corresponding term and term_taxonomy in PostgreSQL.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	public function handle_term_edited( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->should_sync_terms() ) {
			return;
		}

		$this->logger->log(
			"Term edited: #{$term_id} (taxonomy: {$taxonomy})",
			'debug',
			'sync',
			null,
			array(
				'term_id'  => $term_id,
				'taxonomy' => $taxonomy,
			)
		);

		try {
			// Include taxonomy sync class.
			require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';

			$taxonomy_sync = new GG_Data_Taxonomy_Sync();
			$result        = $taxonomy_sync->sync_single_term( $term_id, $taxonomy );

			if ( $result ) {
				$this->logger->log(
					"Successfully updated term #{$term_id} in PostgreSQL",
					'info',
					'sync',
					null,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			} else {
				$this->logger->log(
					"Failed to update term #{$term_id} in PostgreSQL",
					'error',
					'sync',
					null,
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				"Error updating term #{$term_id}: " . $e->getMessage(),
				'error',
				'sync',
				null,
				array(
					'term_id'  => $term_id,
					'taxonomy' => $taxonomy,
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle term deletion - remove term from PostgreSQL.
	 *
	 * Fires when a term is deleted from WordPress.
	 * Removes corresponding term data from PostgreSQL.
	 *
	 * @param int    $term_id      Term ID.
	 * @param int    $tt_id        Term taxonomy ID.
	 * @param string $taxonomy     Taxonomy name.
	 * @param object $_deleted_term Deleted term object (unused; required by hook signature).
	 */
	public function handle_term_deleted( $term_id, $tt_id, $taxonomy, $_deleted_term ) {
		unset( $_deleted_term ); // Required by hook signature but not used in delete flow.

		if ( ! $this->should_sync_terms() ) {
			return;
		}

		$this->logger->log(
			"Term deleted: #{$term_id} (taxonomy: {$taxonomy})",
			'debug',
			'sync',
			null,
			array(
				'term_id'  => $term_id,
				'taxonomy' => $taxonomy,
			)
		);

		try {
			$active_connections = $this->settings_manager->get_active_connections();

			foreach ( $active_connections as $connection_name => $connection_config ) {
				$db      = new GG_Data_DB();
				$site_id = 1; // Default site ID for single site or main site.

				// Delete term relationships first (due to foreign key constraints).
				$relationships_result = $db->delete_term_relationships_by_taxonomy_id( $tt_id, $site_id );

				// Delete term taxonomy.
				$taxonomy_result = $db->delete_term_taxonomy( $tt_id, $site_id );

				// Delete term (only if no other taxonomies reference it).
				$term_result = $db->delete_term_if_unused( $term_id, $site_id );

				if ( $relationships_result && $taxonomy_result ) {
					$this->logger->log(
						"Successfully deleted term #{$term_id} from PostgreSQL",
						'info',
						'sync',
						$connection_name,
						array(
							'term_id'  => $term_id,
							'taxonomy' => $taxonomy,
						)
					);
				} else {
					$this->logger->log(
						"Partial deletion of term #{$term_id} from PostgreSQL",
						'warning',
						'sync',
						$connection_name,
						array(
							'term_id'  => $term_id,
							'taxonomy' => $taxonomy,
						)
					);
				}
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				"Error deleting term #{$term_id}: " . $e->getMessage(),
				'error',
				'sync',
				null,
				array(
					'term_id'  => $term_id,
					'taxonomy' => $taxonomy,
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle post complete - sync term relationships after post is fully inserted.
	 *
	 * Fires after WordPress has completely finished inserting/updating a post,
	 * including all meta data and term relationships. This replaces the
	 * set_object_terms hook for more reliable term relationship syncing.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post        Post object.
	 * @param bool    $_update      Whether this is an existing post being updated (unused; required by hook signature).
	 * @param WP_Post $_post_before Previous version of the post (for updates, unused; required by hook signature).
	 */
	public function handle_post_complete( $post_id, $post, $_update, $_post_before ) {
		unset( $_update, $_post_before ); // Required by hook signature but not used by this sync path.

		// Prevent infinite loops - check if we're already processing this post.
		static $processing = array();
		if ( isset( $processing[ $post_id ] ) ) {
			return;
		}
		$processing[ $post_id ] = true;

		// Early filtering for performance - skip non-content operations.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Skip AJAX requests to avoid duplicate syncs.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		// Skip if term sync is disabled.
		if ( ! $this->should_sync_terms() ) {
			return;
		}

		// Get active connection.
		$connection_name = $this->get_active_connection();
		if ( empty( $connection_name ) ) {
			$this->logger->log( "No active PostgreSQL connection - skipping term relationship sync for post {$post_id}", 'info', 'sync', null, array( 'post_id' => $post_id ) );
			return;
		}

		// Check if post type is configured for sync.
		if ( ! $this->is_post_type_synced( $post->post_type, $connection_name ) ) {
			$this->logger->log(
				"Post type '{$post->post_type}' not synced - skipping term relationship sync for post {$post_id}",
				'debug',
				'sync',
				$connection_name,
				array(
					'post_id'   => $post_id,
					'post_type' => $post->post_type,
				)
			);
			return;
		}

		$this->logger->log( "Post complete: post #{$post_id}, syncing term relationships to connection: {$connection_name}", 'debug', 'sync', $connection_name, array( 'post_id' => $post_id ) );

		try {
			// Use our dedicated database method for term relationship sync.
			$db     = new GG_Data_DB();
			$result = $db->sync_post_term_relationships( $post_id, $connection_name, 'wp_after_insert_post' );

			if ( $result ) {
				$this->logger->log( "Successfully synced term relationships for post #{$post_id} via wp_after_insert_post hook", 'info', 'sync', $connection_name, array( 'post_id' => $post_id ) );
			} else {
				$this->logger->log( "Failed to sync term relationships for post #{$post_id} via wp_after_insert_post hook", 'warning', 'sync', $connection_name, array( 'post_id' => $post_id ) );
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				"Error syncing term relationships for post #{$post_id}: " . $e->getMessage(),
				'error',
				'sync',
				$connection_name,
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				)
			);
		}

		// Clean up processing flag.
		static $processing;
		unset( $processing[ $post_id ] );
	}

	/**
	 * Check if term sync is enabled for any active connection.
	 *
	 * Note: Setting key is 'sync_sync_terms' due to UI prefixing 'sync_' to all sync category settings.
	 * This matches the database storage but creates redundant naming (sync_sync_*).
	 *
	 * @return bool True if term sync is enabled, false otherwise.
	 */
	private function should_sync_terms() {
		$active_connections = $this->settings_manager->get_active_connections();

		foreach ( $active_connections as $connection_name => $connection_config ) {
			// Note: Key is 'sync_sync_terms' not 'sync_terms' due to UI prefixing.
			$sync_terms = $this->settings_manager->get_with_category( 'sync', $connection_name, 'sync_sync_terms' );
			if ( $sync_terms ) {
				return true;
			}
		}

		return false;
	}
}
