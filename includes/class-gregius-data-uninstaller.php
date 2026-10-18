<?php
/**
 * Uninstall handler for Gregius Data.
 *
 * Preserves all data by default. When the user opted in via the
 * `gregius_data_remove_data_on_uninstall` flag (surfaced through the
 * deactivation modal), the WordPress-side data is removed on uninstall.
 *
 * The external PostgreSQL/Supabase mirror is intentionally NEVER touched —
 * it is the user's mirrored content and is removed only through the
 * plugin's explicit delete-synced-data action while the plugin is active.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data-retention-aware uninstaller.
 *
 * Self-contained: only relies on WordPress core and `$wpdb`, so it can run
 * from `uninstall.php` where the plugin is not loaded.
 */
class Gregius_Data_Uninstaller {

	/**
	 * Option (blog) / site option (network) that records the user's choice.
	 *
	 * Stored as a plain option — never in the custom `gregius_data_settings` table — so
	 * it remains readable even if that table is dropped.
	 *
	 * @var string
	 */
	const RETENTION_OPTION = 'gregius_data_remove_data_on_uninstall';

	/**
	 * Read the retention flag (site option on multisite, blog option otherwise).
	 *
	 * @return bool
	 */
	public static function get_retention_flag() {
		if ( is_multisite() ) {
			return (bool) get_site_option( self::RETENTION_OPTION, false );
		}
		return (bool) get_option( self::RETENTION_OPTION, false );
	}

	/**
	 * Write the retention flag (site option on multisite, blog option otherwise).
	 *
	 * @param bool $value Whether to remove data on uninstall.
	 * @return bool The normalized stored value.
	 */
	public static function set_retention_flag( $value ) {
		$value = (bool) $value;

		if ( is_multisite() ) {
			update_site_option( self::RETENTION_OPTION, $value );
		} else {
			update_option( self::RETENTION_OPTION, $value );
		}

		return $value;
	}

	/**
	 * Run the uninstall routine.
	 *
	 * Always: clear cron, remove the capability, and drop ephemeral transients.
	 * Only when the flag is set: delete options, transients, tables, prompt
	 * data, and (on multisite) network options.
	 *
	 * @return void
	 */
	public static function run() {
		$remove   = self::get_retention_flag();
		$blog_ids = self::get_blog_ids();

		foreach ( $blog_ids as $blog_id ) {
			if ( is_multisite() ) {
				switch_to_blog( (int) $blog_id );
			}

			self::clear_cron();
			self::remove_capability();
			delete_transient( 'gregius_data_clear_localstorage' );

			if ( $remove ) {
				self::remove_blog_data();
			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

		if ( is_multisite() ) {
			delete_site_option( self::RETENTION_OPTION );

			if ( $remove ) {
				self::remove_network_data();
			}
		} else {
			delete_option( self::RETENTION_OPTION );
		}

		wp_cache_flush();
	}

	/**
	 * List of blogs to process.
	 *
	 * @return int[]
	 */
	private static function get_blog_ids() {
		if ( ! is_multisite() ) {
			return array( 1 );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all blog IDs for uninstall cleanup.
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

		return array_map( 'intval', is_array( $blog_ids ) ? $blog_ids : array() );
	}

	/**
	 * Clear scheduled cron events (Action Scheduler when present, WP-Cron always).
	 *
	 * @return void
	 */
	private static function clear_cron() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'gregius_data_process_retry_queue', null, 'gregius-data' );
			as_unschedule_all_actions( 'gregius_data_process_batch_sync', null, 'gregius-data' );
			as_unschedule_all_actions( 'gregius_data_process_batch_embeddings', null, 'gregius-data' );
			as_unschedule_all_actions( 'gregius_data_sync_post', null, 'gregius-data' );
			as_unschedule_all_actions( 'gregius_data_delete_post', null, 'gregius-data' );
		}

		$hooks = array(
			'gregius_data_process_retry_queue',
			'gregius_data_process_batch_sync',
			'gregius_data_process_batch_embeddings',
			'gregius_data_sync_post',
			'gregius_data_delete_post',
			'gregius_data_check_connection_health',
			'gregius_data_daily_validation',
			'gregius_data_orphan_cleanup',
			'gregius_data_daily_log_retention_purge',
			'gregius_data_cleanup_logs',
			'gregius_data_process_simple_vectors',
			'gregius_data_process_batch',
			'gregius_data_retry_failed',
		);

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Remove the plugin capability from the administrator role.
	 *
	 * @return void
	 */
	private static function remove_capability() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( 'manage_gregius_data' );
		}
	}

	/**
	 * Remove current-blog plugin data (options, transients, tables, prompts).
	 *
	 * @return void
	 */
	private static function remove_blog_data() {
		global $wpdb;

		$option_likes = array(
			'gregius_data_',
			'gregius_data_',
		);

		foreach ( $option_likes as $prefix ) {
			$like = $wpdb->esc_like( $prefix ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall-time batch cleanup; no cache invalidation needed (plugin removed).
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}

		$transient_prefixes = array(
			'_transient_gregius_data_',
			'_transient_timeout_gregius_data_',
			'_site_transient_gregius_data_',
			'_site_transient_timeout_gregius_data_',
		);

		foreach ( $transient_prefixes as $prefix ) {
			$like = $wpdb->esc_like( $prefix ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall-time transient cleanup; no cache invalidation needed (plugin removed).
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}

		$tables = array(
			$wpdb->prefix . 'gregius_data_settings',
			$wpdb->prefix . 'gregius_data_sync_metadata',
			$wpdb->prefix . 'gregius_data_logs',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall-time table cleanup.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}

		self::remove_prompt_data();
		self::remove_interaction_data();
	}

	/**
	 * Remove plugin-owned interaction data (posts and their metadata).
	 *
	 * Uses direct bulk SQL (high volume — one post per query) rather than the
	 * per-post WordPress-core path used for prompts (few rows plus a taxonomy).
	 * The `gregius_interaction` post type carries no revisions or taxonomy.
	 *
	 * @return void
	 */
	private static function remove_interaction_data() {
		global $wpdb;

		// Delete interaction meta first (covers _gregius_interaction_* and filter-added keys), then the posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall-time bulk cleanup; no cache invalidation needed (plugin removed).
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta}
			 WHERE post_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = 'gregius_interaction' )"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall-time bulk cleanup; no cache invalidation needed (plugin removed).
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'gregius_interaction'" );
	}

	/**
	 * Remove plugin-owned prompt data (posts, terms, and their metadata).
	 *
	 * Uses WordPress core APIs only; works without the plugin's CPT/taxonomy
	 * being registered (uninstall.php runs with the plugin not loaded).
	 *
	 * @return void
	 */
	private static function remove_prompt_data() {
		$prompt_ids = get_posts(
			array(
				'post_type'      => 'gregius_prompt',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( (array) $prompt_ids as $prompt_id ) {
			wp_delete_post( (int) $prompt_id, true );
		}

		$term_ids = get_terms(
			array(
				'taxonomy'   => 'gregius_prompt_type',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) ) {
			return;
		}

		foreach ( $term_ids as $term_id ) {
			wp_delete_term( (int) $term_id, 'gregius_prompt_type' );
		}
	}

	/**
	 * Remove network-level (sitemeta) plugin data on multisite.
	 *
	 * @return void
	 */
	private static function remove_network_data() {
		global $wpdb;

		foreach ( array( 'gregius_data_', 'gregius_data_' ) as $prefix ) {
			$like = $wpdb->esc_like( $prefix ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall-time network option cleanup.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $like ) );
		}
	}
}
