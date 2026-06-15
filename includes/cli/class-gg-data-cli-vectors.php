<?php
/**
 * WP-CLI Vectors Command
 *
 * Generate or rebuild search vectors for PostgreSQL.
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
 * Generate or rebuild search vectors for PostgreSQL.
 *
 * ## EXAMPLES
 *
 *     # Generate vectors for posts missing them
 *     $ wp gg-data vectors generate --connection=gregius-data
 *
 *     # Rebuild all vectors
 *     $ wp gg-data vectors rebuild --connection=gregius-data
 *
 *     # Generate vectors for specific post type
 *     $ wp gg-data vectors generate --connection=gregius-data --post-type=page
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Vectors {

	/**
	 * Vector generator instance
	 *
	 * @var GG_Data_Vector_Generator|null
	 */
	private $generator = null;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger|null
	 */
	private $logger = null;

	/**
	 * Get or create the vector generator instance (lazy loading).
	 *
	 * @return GG_Data_Vector_Generator
	 */
	private function get_generator() {
		if ( null === $this->generator ) {
			$this->generator = new GG_Data_Vector_Generator();
		}
		return $this->generator;
	}

	/**
	 * Get or create the logger instance (lazy loading).
	 *
	 * @return GG_Data_Logger
	 */
	private function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = new GG_Data_Logger();
		}
		return $this->logger;
	}

	/**
	 * Generate vectors for posts without vectors.
	 *
	 * ## OPTIONS
	 *
	 * [--connection=<name>]
	 * : Database connection name.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--embedding-model=<model>]
	 * : Embedding model to use.
	 * ---
	 * default: tfidf-300
	 * ---
	 *
	 * [--post-type=<type>]
	 * : Specific post type for vector generation.
	 * ---
	 * default: all
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Number of posts per batch.
	 * ---
	 * default: 50
	 * ---
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
	 *     # Generate vectors for posts missing them
	 *     $ wp gg-data vectors generate --connection=gregius-data
	 *
	 *     # Generate vectors only for pages
	 *     $ wp gg-data vectors generate --connection=gregius-data --post-type=page
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		$connection      = $assoc_args['connection'] ?? 'gregius-data';
		$embedding_model = $assoc_args['embedding-model'] ?? 'tfidf-300';
		$post_type       = $assoc_args['post-type'] ?? 'all';
		$batch_size      = intval( $assoc_args['batch-size'] ?? 50 );
		$format          = $assoc_args['format'] ?? 'table';

		$this->run_vector_generation( $connection, $embedding_model, $post_type, $batch_size, $format, false );
	}

	/**
	 * Rebuild all vectors (regenerate existing).
	 *
	 * ## OPTIONS
	 *
	 * [--connection=<name>]
	 * : Database connection name.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--embedding-model=<model>]
	 * : Embedding model to use.
	 * ---
	 * default: tfidf-300
	 * ---
	 *
	 * [--post-type=<type>]
	 * : Specific post type for vector generation.
	 * ---
	 * default: all
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Number of posts per batch.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--force]
	 * : Force regeneration (same as rebuild).
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
	 *     # Rebuild all vectors
	 *     $ wp gg-data vectors rebuild --connection=gregius-data
	 *
	 *     # Rebuild with custom batch size
	 *     $ wp gg-data vectors rebuild --connection=gregius-data --batch-size=25
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function rebuild( $args, $assoc_args ) {
		$connection      = $assoc_args['connection'] ?? 'gregius-data';
		$embedding_model = $assoc_args['embedding-model'] ?? 'tfidf-300';
		$post_type       = $assoc_args['post-type'] ?? 'all';
		$batch_size      = intval( $assoc_args['batch-size'] ?? 50 );
		$format          = $assoc_args['format'] ?? 'table';

		$this->run_vector_generation( $connection, $embedding_model, $post_type, $batch_size, $format, true );
	}

	/**
	 * Run vector generation process
	 *
	 * @param string $connection      Connection name.
	 * @param string $embedding_model Embedding model key.
	 * @param string $post_type       Post type or 'all'.
	 * @param int    $batch_size      Batch size.
	 * @param string $format          Output format.
	 * @param bool   $rebuild         Whether to rebuild existing vectors.
	 */
	private function run_vector_generation( $connection, $embedding_model, $post_type, $batch_size, $format, $rebuild ) {
		// Validate batch size.
		$batch_size = $this->validate_batch_size( $batch_size );

		// Validate connection exists.
		if ( ! $this->validate_connection( $connection ) ) {
			return;
		}

		// Get post count.
		$total = $this->get_posts_for_vectors( $post_type, $connection, $rebuild );

		if ( 0 === $total ) {
			WP_CLI::success( 'No posts require vector generation.' );
			return;
		}

		$batches   = (int) ceil( $total / $batch_size );
		$processed = 0;
		$failed    = 0;
		$start     = microtime( true );
		$action    = $rebuild ? 'Rebuilding' : 'Generating';

		WP_CLI::log( sprintf( '%s vectors for %d posts in %d batches...', $action, $total, $batches ) );

		// If using TF-IDF, build vocabulary first.
		if ( strpos( $embedding_model, 'tfidf' ) !== false ) {
			WP_CLI::log( 'Building vocabulary...' );
			$this->build_vocabulary( $connection );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( "{$action} vectors", $total );
		$results  = array();

		for ( $batch = 0; $batch < $batches; $batch++ ) {
			$result = $this->get_generator()->generate_batch( $embedding_model, $batch_size, $connection );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Batch %d failed: %s', $batch + 1, $result->get_error_message() ) );
				$failed += $batch_size;
				continue;
			}

			$batch_processed = $result['processed'] ?? 0;
			$batch_failed    = $result['failed'] ?? 0;

			$processed += $batch_processed;
			$failed    += $batch_failed;

			// Tick progress.
			for ( $i = 0; $i < $batch_processed; $i++ ) {
				$progress->tick();
			}

			$results[] = array(
				'batch'     => $batch + 1,
				'processed' => $batch_processed,
				'failed'    => $batch_failed,
			);

			// Memory cleanup.
			$this->maybe_cleanup_memory();

			// Exit if no more posts to process.
			if ( 0 === $batch_processed ) {
				break;
			}
		}

		$progress->finish();
		$duration = round( microtime( true ) - $start, 1 );

		// Calculate average time per post.
		$avg_time = $processed > 0 ? round( ( $duration * 1000 ) / $processed ) : 0;

		// Output results.
		$this->output_results( $results, $format, $processed, $failed, $duration, $avg_time, $rebuild );
	}

	/**
	 * Build vocabulary for TF-IDF
	 *
	 * @param string $connection Connection name.
	 */
	private function build_vocabulary( $connection ) {
		$vocab_manager = new GG_Data_Vocabulary_Manager( $connection );
		$result        = $vocab_manager->build_vocabulary();

		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( 'Vocabulary build failed: ' . $result->get_error_message() );
		} else {
			WP_CLI::log(
				sprintf(
					'Vocabulary built: %d terms from %d posts',
					$result['term_count'] ?? 0,
					$result['post_count'] ?? 0
				)
			);
		}
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
		// Vectors are CPU-intensive, lower max than sync.
		$max_batch_size = apply_filters( 'gg_data_cli_vectors_max_batch_size', 200 );

		if ( $batch_size < 1 ) {
			WP_CLI::warning( 'Batch size must be at least 1. Using 50.' );
			return 50;
		}

		if ( $batch_size > $max_batch_size ) {
			WP_CLI::warning( sprintf( 'Batch size exceeds maximum (%d). Using %d.', $max_batch_size, $max_batch_size ) );
			return $max_batch_size;
		}

		return $batch_size;
	}

	/**
	 * Get count of posts needing vectors
	 *
	 * @param string $post_type  Post type or 'all'.
	 * @param string $connection Connection name.
	 * @param bool   $rebuild    Whether to count all posts or only missing.
	 * @return int Post count.
	 */
	private function get_posts_for_vectors( $post_type, $connection, $rebuild ) {
		global $wpdb;

		// Get post types to process.
		if ( 'all' === $post_type ) {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
		} else {
			$post_types = array( $post_type );
		}

		if ( empty( $post_types ) ) {
			return 0;
		}

		// Build query.
		$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		if ( $rebuild ) {
			// Count all posts.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders required for IN clause.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_type IN ($type_placeholders)
					AND post_status = 'publish'",
					$post_types
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		} else {
			// Count posts without vectors (using vector queue table).
			$queue_table = $wpdb->prefix . 'gg_data_vector_queue';

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders required for IN clause.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} q
					INNER JOIN {$wpdb->posts} p ON q.post_id = p.ID
					WHERE p.post_type IN ($type_placeholders)
					AND p.post_status = 'publish'
					AND q.status = 'pending'",
					$post_types
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		return $total;
	}

	/**
	 * Output generation results
	 *
	 * @param array  $results   Batch results.
	 * @param string $format    Output format.
	 * @param int    $processed Total processed.
	 * @param int    $failed    Total failed.
	 * @param float  $duration  Total duration.
	 * @param int    $avg_time  Average time per post (ms).
	 * @param bool   $rebuild   Whether this was a rebuild.
	 */
	private function output_results( $results, $format, $processed, $failed, $duration, $avg_time, $rebuild ) {
		$action = $rebuild ? 'Rebuilt' : 'Generated';

		if ( 'json' === $format ) {
			$output = array(
				'action'  => strtolower( $action ),
				'batches' => $results,
				'summary' => array(
					'processed'   => $processed,
					'failed'      => $failed,
					'duration'    => $duration,
					'avg_time_ms' => $avg_time,
				),
			);
			WP_CLI::log( wp_json_encode( $output, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table or CSV format.
		if ( 'csv' === $format ) {
			\WP_CLI\Utils\format_items( 'csv', $results, array( 'batch', 'processed', 'failed' ) );
		}

		// Summary.
		if ( $failed > 0 ) {
			WP_CLI::warning(
				sprintf(
					'Completed with errors: %d vectors %s, %d failed in %ds (avg %dms/post)',
					$processed,
					strtolower( $action ),
					$failed,
					$duration,
					$avg_time
				)
			);
		} else {
			WP_CLI::success(
				sprintf(
					'%s %d vectors in %ds (avg %dms/post)',
					$action,
					$processed,
					$duration,
					$avg_time
				)
			);
		}
	}

	/**
	 * Memory cleanup between batches
	 */
	private function maybe_cleanup_memory() {
		wp_cache_flush();

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}
}
