<?php
/**
 * WP-CLI Sync Command
 *
 * Synchronize WordPress data to PostgreSQL.
 *
 * @package Gregius_Data
 * @subpackage CLI
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync WordPress data to PostgreSQL.
 *
 * ## EXAMPLES
 *
 *     # Sync all posts
 *     $ wp gg-data sync posts --connection=gregius-data
 *
 *     # Sync specific post type
 *     $ wp gg-data sync posts --connection=gregius-data --post-type=page
 *
 *     # Sync all terms
 *     $ wp gg-data sync terms --connection=gregius-data
 *
 *     # Full sync with preview
 *     $ wp gg-data sync all --connection=gregius-data --dry-run
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Sync {

	/**
	 * Sync posts and postmeta to PostgreSQL.
	 *
	 * ## OPTIONS
	 *
	 * [--connection=<name>]
	 * : Database connection name.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--post-type=<type>]
	 * : Specific post type to sync (post, page, custom).
	 * ---
	 * default: all
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Number of items per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--dry-run]
	 * : Preview operations without executing.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Sync all posts
	 *     $ wp gg-data sync posts --connection=gregius-data
	 *
	 *     # Sync only pages in batches of 200
	 *     $ wp gg-data sync posts --connection=gregius-data --post-type=page --batch-size=200
	 *
	 *     # Preview sync without executing
	 *     $ wp gg-data sync posts --connection=gregius-data --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function posts( $args, $assoc_args ) {
		$connection = $assoc_args['connection'] ?? 'gregius-data';
		$post_type  = $assoc_args['post-type'] ?? 'all';
		$batch_size = intval( $assoc_args['batch-size'] ?? 100 );
		$dry_run    = isset( $assoc_args['dry-run'] );
		$format     = $assoc_args['format'] ?? 'table';

		// Validate batch size.
		$batch_size = $this->validate_batch_size( $batch_size );

		// Validate connection exists.
		if ( ! $this->validate_connection( $connection ) ) {
			return;
		}

		// Get post types to sync.
		$post_types = $this->get_post_types_to_sync( $post_type );

		if ( empty( $post_types ) ) {
			WP_CLI::error( sprintf( 'Invalid post type: %s', $post_type ) );
			return;
		}

		$results     = array();
		$total_start = microtime( true );

		foreach ( $post_types as $type ) {
			$type_result = $this->sync_post_type( $type, $connection, $batch_size, $dry_run );
			$results[]   = $type_result;
		}

		$total_duration = round( microtime( true ) - $total_start, 1 );

		// Output results.
		$this->output_sync_results( $results, $format, $total_duration, $dry_run );
	}

	/**
	 * Sync terms and term relationships to PostgreSQL.
	 *
	 * Syncs all terms, term_taxonomies, and term_relationships.
	 * The taxonomy filter is informational only (for reporting purposes).
	 *
	 * ## OPTIONS
	 *
	 * [--connection=<name>]
	 * : Database connection name.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Number of items per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--dry-run]
	 * : Preview operations without executing.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Sync all terms
	 *     $ wp gg-data sync terms --connection=gregius-data
	 *
	 *     # Preview sync without executing
	 *     $ wp gg-data sync terms --connection=gregius-data --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function terms( $args, $assoc_args ) {
		$connection = $assoc_args['connection'] ?? 'gregius-data';
		$batch_size = intval( $assoc_args['batch-size'] ?? 100 );
		$dry_run    = isset( $assoc_args['dry-run'] );
		$format     = $assoc_args['format'] ?? 'table';

		// Validate batch size.
		$batch_size = $this->validate_batch_size( $batch_size );

		// Validate connection exists.
		if ( ! $this->validate_connection( $connection ) ) {
			return;
		}

		$results     = array();
		$total_start = microtime( true );

		// Sync terms.
		$results[] = $this->sync_all_terms( $connection, $batch_size, $dry_run );

		// Sync term_taxonomies.
		$results[] = $this->sync_all_term_taxonomies( $connection, $batch_size, $dry_run );

		// Sync term_relationships.
		$results[] = $this->sync_all_term_relationships( $connection, $batch_size, $dry_run );

		$total_duration = round( microtime( true ) - $total_start, 1 );

		// Output results.
		$this->output_sync_results( $results, $format, $total_duration, $dry_run, 'terms' );
	}

	/**
	 * Sync both posts and terms to PostgreSQL.
	 *
	 * ## OPTIONS
	 *
	 * [--connection=<name>]
	 * : Database connection name.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Number of items per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--dry-run]
	 * : Preview operations without executing.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Full sync
	 *     $ wp gg-data sync all --connection=gregius-data
	 *
	 *     # Full sync with custom batch size
	 *     $ wp gg-data sync all --connection=gregius-data --batch-size=500
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function all( $args, $assoc_args ) {
		$connection = $assoc_args['connection'] ?? 'gregius-data';
		$batch_size = intval( $assoc_args['batch-size'] ?? 100 );
		$dry_run    = isset( $assoc_args['dry-run'] );
		$format     = $assoc_args['format'] ?? 'table';

		// Validate batch size.
		$batch_size = $this->validate_batch_size( $batch_size );

		// Validate connection exists.
		if ( ! $this->validate_connection( $connection ) ) {
			return;
		}

		$total_start = microtime( true );

		WP_CLI::log( '=== Syncing Posts ===' );
		$this->posts( array(), array_merge( $assoc_args, array( 'post-type' => 'all' ) ) );

		WP_CLI::log( '' );
		WP_CLI::log( '=== Syncing Terms ===' );
		$this->terms( array(), array_merge( $assoc_args, array( 'taxonomy' => 'all' ) ) );

		$total_duration = round( microtime( true ) - $total_start, 1 );

		if ( ! $dry_run ) {
			WP_CLI::success( sprintf( 'Full sync completed in %ds', $total_duration ) );
		} else {
			WP_CLI::log( sprintf( 'Dry run completed in %ds', $total_duration ) );
		}
	}

	/**
	 * Sync a single post type
	 *
	 * @param string $post_type  Post type slug.
	 * @param string $connection Connection name.
	 * @param int    $batch_size Batch size.
	 * @param bool   $dry_run    Whether to preview only.
	 * @return array Result data.
	 */
	private function sync_post_type( $post_type, $connection, $batch_size, $dry_run ) {
		$post_sync = new GG_Data_Post_Sync( $connection );
		$site_id   = get_current_blog_id();

		// Get total count.
		$total = $this->get_post_count( $post_type );

		if ( 0 === $total ) {
			return array(
				'type'      => $post_type,
				'total'     => 0,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'skipped',
			);
		}

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '[DRY RUN] Would sync %d %s items', $total, $post_type ) );
			return array(
				'type'      => $post_type,
				'total'     => $total,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'dry-run',
			);
		}

		$batches   = (int) ceil( $total / $batch_size );
		$processed = 0;
		$failed    = 0;
		$start     = microtime( true );

		WP_CLI::log( sprintf( 'Syncing %d %s items in %d batches...', $total, $post_type, $batches ) );

		$progress = \WP_CLI\Utils\make_progress_bar( "Syncing {$post_type}", $total );

		for ( $batch = 0; $batch < $batches; $batch++ ) {
			$offset = $batch * $batch_size;
			$result = $post_sync->batch_sync_post_type( $post_type, $batch_size, $offset, $site_id );

			if ( isset( $result['processed'] ) ) {
				$processed += $result['processed'];
			}
			if ( isset( $result['failed'] ) ) {
				$failed += $result['failed'];
			}

			// Tick progress for each item in batch.
			$batch_count = $result['processed'] ?? 0;
			for ( $i = 0; $i < $batch_count; $i++ ) {
				$progress->tick();
			}

			// Memory cleanup.
			$this->maybe_cleanup_memory();
		}

		$progress->finish();
		$duration = round( microtime( true ) - $start, 1 );

		return array(
			'type'      => $post_type,
			'total'     => $total,
			'processed' => $processed,
			'failed'    => $failed,
			'duration'  => $duration,
			'status'    => $failed > 0 ? 'partial' : 'success',
		);
	}

	/**
	 * Sync all terms to PostgreSQL
	 *
	 * @param string $connection Connection name.
	 * @param int    $batch_size Batch size.
	 * @param bool   $dry_run    Whether to preview only.
	 * @return array Result data.
	 */
	private function sync_all_terms( $connection, $batch_size, $dry_run ) {
		global $wpdb;

		$tax_sync = new GG_Data_Taxonomy_Sync( $connection );

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all terms
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );

		if ( 0 === $total ) {
			return array(
				'type'      => 'terms',
				'total'     => 0,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'skipped',
			);
		}

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '[DRY RUN] Would sync %d terms', $total ) );
			return array(
				'type'      => 'terms',
				'total'     => $total,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'dry-run',
			);
		}

		$batches   = (int) ceil( $total / $batch_size );
		$processed = 0;
		$failed    = 0;
		$start     = microtime( true );

		WP_CLI::log( sprintf( 'Syncing %d terms in %d batches...', $total, $batches ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Syncing terms', $total );

		for ( $batch = 0; $batch < $batches; $batch++ ) {
			$offset = $batch * $batch_size;
			$result = $tax_sync->batch_sync_terms( $batch_size, $offset );

			if ( isset( $result['processed'] ) ) {
				$processed += $result['processed'];
			}
			if ( isset( $result['failed'] ) ) {
				$failed += $result['failed'];
			}

			// Tick progress for each item in batch.
			$batch_count = $result['processed'] ?? 0;
			for ( $i = 0; $i < $batch_count; $i++ ) {
				$progress->tick();
			}

			// Memory cleanup.
			$this->maybe_cleanup_memory();
		}

		$progress->finish();
		$duration = round( microtime( true ) - $start, 1 );

		return array(
			'type'      => 'terms',
			'total'     => $total,
			'processed' => $processed,
			'failed'    => $failed,
			'duration'  => $duration,
			'status'    => $failed > 0 ? 'partial' : 'success',
		);
	}

	/**
	 * Sync all term_taxonomies to PostgreSQL
	 *
	 * @param string $connection Connection name.
	 * @param int    $batch_size Batch size.
	 * @param bool   $dry_run    Whether to preview only.
	 * @return array Result data.
	 */
	private function sync_all_term_taxonomies( $connection, $batch_size, $dry_run ) {
		global $wpdb;

		$tax_sync = new GG_Data_Taxonomy_Sync( $connection );

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all term_taxonomies
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy}" );

		if ( 0 === $total ) {
			return array(
				'type'      => 'term_taxonomies',
				'total'     => 0,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'skipped',
			);
		}

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '[DRY RUN] Would sync %d term_taxonomies', $total ) );
			return array(
				'type'      => 'term_taxonomies',
				'total'     => $total,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'dry-run',
			);
		}

		$batches   = (int) ceil( $total / $batch_size );
		$processed = 0;
		$failed    = 0;
		$start     = microtime( true );

		WP_CLI::log( sprintf( 'Syncing %d term_taxonomies in %d batches...', $total, $batches ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Syncing term_taxonomies', $total );

		for ( $batch = 0; $batch < $batches; $batch++ ) {
			$offset = $batch * $batch_size;
			$result = $tax_sync->batch_sync_term_taxonomies( $batch_size, $offset );

			if ( isset( $result['processed'] ) ) {
				$processed += $result['processed'];
			}
			if ( isset( $result['failed'] ) ) {
				$failed += $result['failed'];
			}

			// Tick progress for each item in batch.
			$batch_count = $result['processed'] ?? 0;
			for ( $i = 0; $i < $batch_count; $i++ ) {
				$progress->tick();
			}

			// Memory cleanup.
			$this->maybe_cleanup_memory();
		}

		$progress->finish();
		$duration = round( microtime( true ) - $start, 1 );

		return array(
			'type'      => 'term_taxonomies',
			'total'     => $total,
			'processed' => $processed,
			'failed'    => $failed,
			'duration'  => $duration,
			'status'    => $failed > 0 ? 'partial' : 'success',
		);
	}

	/**
	 * Sync all term_relationships to PostgreSQL
	 *
	 * @param string $connection Connection name.
	 * @param int    $batch_size Batch size.
	 * @param bool   $dry_run    Whether to preview only.
	 * @return array Result data.
	 */
	private function sync_all_term_relationships( $connection, $batch_size, $dry_run ) {
		global $wpdb;

		$tax_sync = new GG_Data_Taxonomy_Sync( $connection );

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all term_relationships
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships}" );

		if ( 0 === $total ) {
			return array(
				'type'      => 'term_relationships',
				'total'     => 0,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'skipped',
			);
		}

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '[DRY RUN] Would sync %d term_relationships', $total ) );
			return array(
				'type'      => 'term_relationships',
				'total'     => $total,
				'processed' => 0,
				'failed'    => 0,
				'duration'  => 0,
				'status'    => 'dry-run',
			);
		}

		$batches   = (int) ceil( $total / $batch_size );
		$processed = 0;
		$failed    = 0;
		$start     = microtime( true );

		WP_CLI::log( sprintf( 'Syncing %d term_relationships in %d batches...', $total, $batches ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Syncing term_relationships', $total );

		for ( $batch = 0; $batch < $batches; $batch++ ) {
			$offset = $batch * $batch_size;
			$result = $tax_sync->batch_sync_term_relationships( $batch_size, $offset );

			if ( isset( $result['processed'] ) ) {
				$processed += $result['processed'];
			}
			if ( isset( $result['failed'] ) ) {
				$failed += $result['failed'];
			}

			// Tick progress for each item in batch.
			$batch_count = $result['processed'] ?? 0;
			for ( $i = 0; $i < $batch_count; $i++ ) {
				$progress->tick();
			}

			// Memory cleanup.
			$this->maybe_cleanup_memory();
		}

		$progress->finish();
		$duration = round( microtime( true ) - $start, 1 );

		return array(
			'type'      => 'term_relationships',
			'total'     => $total,
			'processed' => $processed,
			'failed'    => $failed,
			'duration'  => $duration,
			'status'    => $failed > 0 ? 'partial' : 'success',
		);
	}

	/**
	 * Validate connection exists
	 *
	 * @param string $connection Connection name.
	 * @return bool True if valid.
	 */
	private function validate_connection( $connection ) {
		$settings    = new GG_Data_Settings_Manager();
		$connections = $settings->get_all_connections();

		if ( ! isset( $connections[ $connection ] ) ) {
			WP_CLI::error(
				sprintf(
					'Invalid connection: %s. Available connections: %s',
					$connection,
					implode( ', ', array_keys( $connections ) )
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Validate batch size
	 *
	 * @param int $batch_size Requested batch size.
	 * @return int Validated batch size.
	 */
	private function validate_batch_size( $batch_size ) {
		/**
		 * Filter the maximum batch size for CLI operations.
		 *
		 * @since 1.0.0
		 * @param int $max_batch_size Maximum allowed batch size.
		 */
		$max_batch_size = apply_filters( 'gg_data_cli_max_batch_size', 1000 );

		if ( $batch_size < 1 ) {
			WP_CLI::warning( 'Batch size must be at least 1. Using 100.' );
			return 100;
		}

		if ( $batch_size > $max_batch_size ) {
			WP_CLI::warning( sprintf( 'Batch size exceeds maximum (%d). Using %d.', $max_batch_size, $max_batch_size ) );
			return $max_batch_size;
		}

		return $batch_size;
	}

	/**
	 * Get post types to sync
	 *
	 * @param string $post_type Requested post type or 'all'.
	 * @return array Array of post type slugs.
	 */
	private function get_post_types_to_sync( $post_type ) {
		if ( 'all' === $post_type ) {
			// Get all public post types.
			return get_post_types( array( 'public' => true ), 'names' );
		}

		// Validate single post type.
		if ( post_type_exists( $post_type ) ) {
			return array( $post_type );
		}

		return array();
	}

	/**
	 * Get post count for a post type
	 *
	 * @param string $post_type Post type slug.
	 * @return int Post count.
	 */
	private function get_post_count( $post_type ) {
		$counts = wp_count_posts( $post_type );
		$total  = 0;

		// Get enabled statuses from settings.
		$settings         = new GG_Data_Settings_Manager();
		$enabled_statuses = $settings->get_with_category( 'sync', 'default', 'sync_enabled_statuses', array( 'publish' ) );

		if ( is_string( $enabled_statuses ) ) {
			$enabled_statuses = maybe_unserialize( $enabled_statuses );
		}

		if ( empty( $enabled_statuses ) || ! is_array( $enabled_statuses ) ) {
			$enabled_statuses = array( 'publish' );
		}

		foreach ( $enabled_statuses as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += $counts->$status;
			}
		}

		return $total;
	}

	/**
	 * Output sync results
	 *
	 * @param array  $results  Array of result data.
	 * @param string $format   Output format.
	 * @param float  $duration Total duration.
	 * @param bool   $dry_run  Whether this was a dry run.
	 * @param string $type     Type of sync (posts or terms).
	 */
	private function output_sync_results( $results, $format, $duration, $dry_run, $type = 'posts' ) {
		$total_processed = 0;
		$total_failed    = 0;

		foreach ( $results as $result ) {
			$total_processed += $result['processed'];
			$total_failed    += $result['failed'];
		}

		if ( 'json' === $format ) {
			$output = array(
				'type'    => $type,
				'results' => $results,
				'summary' => array(
					'processed' => $total_processed,
					'failed'    => $total_failed,
					'duration'  => $duration,
					'dry_run'   => $dry_run,
				),
			);
			WP_CLI::log( wp_json_encode( $output, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table or CSV format.
		$fields = array( 'type', 'total', 'processed', 'failed', 'duration', 'status' );
		\WP_CLI\Utils\format_items( $format, $results, $fields );

		// Summary.
		if ( ! $dry_run ) {
			if ( $total_failed > 0 ) {
				WP_CLI::warning(
					sprintf(
						'Completed with errors: %d synced, %d failed in %ds',
						$total_processed,
						$total_failed,
						$duration
					)
				);
			} else {
				WP_CLI::success(
					sprintf(
						'Synced %d %s in %ds',
						$total_processed,
						$type,
						$duration
					)
				);
			}
		}
	}

	/**
	 * Memory cleanup between batches
	 */
	private function maybe_cleanup_memory() {
		// Clear object cache.
		wp_cache_flush();

		// Garbage collection.
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}
}
