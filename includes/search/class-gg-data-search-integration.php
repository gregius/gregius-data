<?php
/**
 * Search Integration
 *
 * Integrates PostgreSQL full-text search with WordPress search queries.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Search_Integration
 *
 * Hooks into WordPress search to provide PostgreSQL FTS results.
 */
class GG_Data_Search_Integration {

	/**
	 * Database instance
	 *
	 * @var GG_Data_DB
	 */
	private $db;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Fallback manager instance
	 *
	 * @var GG_Data_Search_Fallback
	 */
	private $fallback;

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings;

	/**
	 * Model registry instance
	 *
	 * @var GG_Data_Model_Registry
	 */
	private $model_registry;

	/**
	 * Cached search results (post IDs with relevance scores)
	 *
	 * @var array
	 */
	private $search_results = array();

	/**
	 * Last PostgreSQL phase timing diagnostics for the current request.
	 *
	 * @var array
	 */
	private $last_postgresql_diagnostics = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db             = new GG_Data_DB();
		$this->logger         = new GG_Data_Logger();
		$this->fallback       = new GG_Data_Search_Fallback();
		$this->settings       = new GG_Data_Settings_Manager();
		$this->model_registry = new GG_Data_Model_Registry();

		$this->last_postgresql_diagnostics = $this->get_default_postgresql_diagnostics();
	}

	/**
	 * Get default PostgreSQL phase timing diagnostics.
	 *
	 * @return array Default timing values in milliseconds.
	 */
	private function get_default_postgresql_diagnostics() {
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Long telemetry keys make strict alignment noisy.
		return array(
			'latency_pg_extension_checks_ms' => 0.0,
			'latency_pg_vector_readiness_ms' => 0.0,
			'latency_pg_execute_ms'          => 0.0,
			'latency_pg_fetch_ms'            => 0.0,
			'latency_pg_total_ms'            => 0.0,
			'observability_expensive_probes_enabled' => false,
			'observability_expensive_probe_executed' => false,
		);
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	}

	/**
	 * Initialize hooks
	 */
	public function init() {
		// Hook into WordPress search - check if enabled on each request.
		add_filter( 'posts_search', array( $this, 'filter_posts_search' ), 10, 2 );
		add_filter( 'posts_orderby', array( $this, 'filter_posts_orderby' ), 10, 2 );
	}

	/**
	 * Filter posts search query
	 *
	 * Intercepts WordPress search and replaces with PostgreSQL FTS results.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query Query object.
	 * @return string Modified search SQL.
	 */
	public function filter_posts_search( $search, $query ) {
		$search_param = $query->get( 's' );
		$source_type  = $this->get_search_source_type();
		$search_start = microtime( true );

		$pg_diagnostics = $this->get_default_postgresql_diagnostics();

		// Reset cached relevance scores for each search request.
		$this->search_results = array();

		$this->last_postgresql_diagnostics = $pg_diagnostics;

		// Check if search is enabled globally.
		$enabled = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'enabled', false );

		if ( ! $enabled ) {
			return $search;
		}

		// Apply rate limiting (skip PostgreSQL enhancement when threshold exceeded).
		$rate_config = apply_filters(
			'gg_data_search_rate_limit',
			array(
				'limit'  => 30,
				'window' => 60,
			)
		);

		if ( ! empty( $rate_config ) && ! current_user_can( 'manage_options' ) ) {
			$rate_limit  = isset( $rate_config['limit'] ) ? (int) $rate_config['limit'] : 30;
			$rate_window = isset( $rate_config['window'] ) ? (int) $rate_config['window'] : 60;

			if ( $rate_limit > 0 && $rate_window > 0 ) {
				$ip    = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
				$key   = 'gg_data_search_rl_' . md5( $ip );
				$count = (int) get_transient( $key );

				if ( $count >= $rate_limit ) {
					return $search;
				}

				set_transient( $key, $count + 1, $rate_window );
			}
		}

		// Get active connection from global settings.
		$connection_name = $this->get_active_connection();

		if ( empty( $connection_name ) ) {
			return $search; // No active connection configured.
		}

		$search_term = $query->get( 's' );

		// Skip if no search term.
		if ( empty( $search_term ) ) {
			return $search;
		}

		// Only process main query (but don't check is_search since themes may break it).
		if ( ! $query->is_main_query() ) {
			return $search;
		}

		if ( empty( $search_term ) ) {
			return $search;
		}

		// Get synced post types from settings (stored in 'sync' category, not 'content').
		$synced_post_types = $this->settings->get_with_category( 'sync', $connection_name, 'sync_enabled_post_types', array() );

		if ( empty( $synced_post_types ) ) {
			$synced_post_types = array( 'post', 'page' ); // Default assumption.
		}

		// Get post types requested by the query.
		$requested_post_types = $query->get( 'post_type' );

		// Preserve admin list-table context when post type is not explicitly set.
		if ( empty( $requested_post_types ) && 'admin' === $source_type ) {
			$requested_post_types = 'post';
		}

		// Handle 'any' post type (WordPress default when no specific type is set).
		if ( 'any' === $requested_post_types || empty( $requested_post_types ) ) {
			$requested_post_types = array(); // Treat as no specific types requested.
		}

		// Ensure it's an array.
		if ( ! empty( $requested_post_types ) && ! is_array( $requested_post_types ) ) {
			$requested_post_types = array( $requested_post_types );
		}

		// Determine which post types to search where.
		$pg_post_types    = array(); // Search in PostgreSQL.
		$all_search_types = array(); // All post types to search.
		$retrieval_mode   = $this->get_retrieval_mode();

		if ( ! empty( $requested_post_types ) ) {
			// Specific post types requested.
			$all_search_types = $requested_post_types;
			// PostgreSQL searches only synced types.
			$pg_post_types = array_intersect( $requested_post_types, $synced_post_types );
		} else {
			// No specific post types - search synced types in PostgreSQL + all public types in MySQL.
			$pg_post_types = $synced_post_types;
			// Get all public post types for MySQL search to ensure complete coverage.
			$public_post_types = get_post_types( array( 'public' => true ), 'names' );
			$all_search_types  = array_values( $public_post_types );
		}

		// STEP 1: Search PostgreSQL for synced post types.
		$pg_ids              = array();
		$signal_counts       = array(
			'fts'     => 0,
			'trigram' => 0,
			'vector'  => 0,
		);
		$postgresql_failed   = false;
		$fallback_reason     = '';
		$latency_pg_ms       = 0.0;
		$mysql_merge_enabled = ( 'hybrid_default' === $retrieval_mode );
		if ( ! empty( $pg_post_types ) ) {
			$pg_phase_start    = microtime( true );
			$postgresql_search = function ( $args ) {
				return $this->execute_postgresql_search(
					$args['search_term'],
					$args['post_types']
				);
			};

			$mysql_fallback = function ( $args ) {
				unset( $args );
				return array(
					array(
						'__fallback' => 'postgresql_failed',
					),
				);
			};

			$pg_results = $this->fallback->execute_with_fallback(
				$postgresql_search,
				$mysql_fallback,
				array(
					'search_term' => $search_term,
					'post_types'  => $pg_post_types,
				)
			);

			$pg_diagnostics = $this->last_postgresql_diagnostics;
			$latency_pg_ms  = round( ( microtime( true ) - $pg_phase_start ) * 1000, 2 );

			if ( ! empty( $pg_results ) && isset( $pg_results[0]['__fallback'] ) && 'postgresql_failed' === $pg_results[0]['__fallback'] ) {
				$postgresql_failed = true;
				$fallback_reason   = 'postgresql_failed_internal_fallback';
				$pg_results        = array();
			}

			if ( ! empty( $pg_results ) ) {
				$pg_ids               = array_column( $pg_results, 'post_id' );
				$this->search_results = $pg_results; // Store for orderby.
				$signal_counts        = $this->build_signal_counts_from_results( $pg_results );
			}
		}

		// STEP 2: Search MySQL when hybrid merge is enabled or when PostgreSQL failed.
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
		$mysql_ids            = array();
		$latency_mysql_ms     = 0.0;
		$mysql_count_before   = 0;
		$mysql_merge_applied = ! empty( $all_search_types ) && ( $mysql_merge_enabled || $postgresql_failed );
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		if ( $mysql_merge_applied ) {
			$mysql_phase_start  = microtime( true );
			$mysql_ids          = $this->search_mysql_unsynced_types( $search_term, $all_search_types );
			$latency_mysql_ms   = round( ( microtime( true ) - $mysql_phase_start ) * 1000, 2 );
			$mysql_count_before = count( $mysql_ids );
		}

		// STEP 3: Deduplicate (PostgreSQL wins).
		if ( ! empty( $pg_ids ) && ! empty( $mysql_ids ) ) {
			$mysql_ids = array_diff( $mysql_ids, $pg_ids );
		}
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
		$mysql_count_after      = count( $mysql_ids );
		$dedup_removed_count    = max( 0, $mysql_count_before - $mysql_count_after );
		$mysql_merge_contributed = $mysql_count_after > 0;
		$dominant_signal         = $this->determine_dominant_signal( $signal_counts );
		$search_strategy         = $this->determine_search_strategy( $signal_counts, $mysql_merge_contributed );
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		// STEP 4: Merge results (PostgreSQL first, MySQL second).
		$final_ids           = array_merge( $pg_ids, $mysql_ids );
		$latency_total_ms    = round( ( microtime( true ) - $search_start ) * 1000, 2 );
		$degraded            = $postgresql_failed;
		$slow_threshold_ms   = (float) apply_filters( 'gg_data_search_slow_log_ms', 400, $search_term, $connection_name, $retrieval_mode );
		$is_slow             = $latency_total_ms >= $slow_threshold_ms;
		$execution_log_level = ( $degraded || $is_slow ) ? 'warning' : 'debug';
		$observability_mode  = ! empty( $pg_diagnostics['observability_expensive_probes_enabled'] ) ? 'enhanced' : 'baseline';

		// Log the combined search results.
		$this->logger->log(
			sprintf(
				'Enhanced Search completed: "%s" - %d total results (PostgreSQL: %d, MySQL: %d) [mode=%s source=%s pg_failed=%s total_ms=%.2f]',
				esc_html( $search_term ),
				count( $final_ids ),
				count( $pg_ids ),
				count( $mysql_ids ),
				$retrieval_mode,
				$source_type,
				$postgresql_failed ? 'true' : 'false',
				$latency_total_ms
			),
			$execution_log_level,
			'search',
			$connection_name,
			// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Keep telemetry payload readable without excessive whitespace padding.
			array(
				'search_term'           => $search_term,
				'total_results'         => count( $final_ids ),
				'postgresql_count'      => count( $pg_ids ),
				'mysql_count'           => count( $mysql_ids ),
				'search_strategy'       => $search_strategy,
				'signal_counts'         => $signal_counts,
				'dominant_signal'       => $dominant_signal,
				'mysql_merge_applied'   => $mysql_merge_applied,
				'mysql_merge_contributed' => $mysql_merge_contributed,
				'latency_total_ms'      => $latency_total_ms,
				'latency_postgresql_ms' => $latency_pg_ms,
				'latency_mysql_ms'      => $latency_mysql_ms,
				'latency_pg_extension_checks_ms' => $pg_diagnostics['latency_pg_extension_checks_ms'],
				'latency_pg_vector_readiness_ms' => $pg_diagnostics['latency_pg_vector_readiness_ms'],
				'latency_pg_execute_ms'          => $pg_diagnostics['latency_pg_execute_ms'],
				'latency_pg_fetch_ms'            => $pg_diagnostics['latency_pg_fetch_ms'],
				'latency_pg_total_ms'            => $pg_diagnostics['latency_pg_total_ms'],
				'observability_expensive_probes_enabled' => $pg_diagnostics['observability_expensive_probes_enabled'],
				'observability_expensive_probe_executed' => $pg_diagnostics['observability_expensive_probe_executed'],
				'observability_mode'   => $observability_mode,
				'dedup_removed_count'   => $dedup_removed_count,
				'retrieval_mode'        => $retrieval_mode,
				'source_type'           => $source_type,
				'degraded'              => $degraded,
				'fallback_reason'       => $fallback_reason,
				'is_slow'               => $is_slow,
				'slow_threshold_ms'     => $slow_threshold_ms,
				'log_level'             => $execution_log_level,
				'postgresql_failed'     => $postgresql_failed,
				'post_types_pg'         => $pg_post_types,
				'post_types_mysql'      => $all_search_types,
				'embedding_model'       => $pg_results['embedding_model'] ?? '',
				'vector_table'          => $pg_results['vector_table'] ?? '',
			)
			// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		);

		/**
		 * Fires after search completes.
		 *
		 * Used by interaction tracking to record search queries.
		 *
		 * @since 1.0.0
		 * @param array $results {
		 *     Search results and metadata.
		 *
		 *     @type array  $post_ids      Array of matching post IDs.
		 *     @type int    $total_count   Total number of results.
		 *     @type string $search_term   The search query.
		 *     @type string $connection    Connection name.
		 *     @type array  $post_types    Post types searched.
		 *     @type string $source_type   Search source context (frontend, admin, rest, wpcli).
		 *     @type string $retrieval_mode Configured retrieval mode.
		 *     @type float  $latency_total_ms End-to-end search latency in milliseconds.
		 *     @type float  $latency_postgresql_ms PostgreSQL phase latency in milliseconds.
		 *     @type float  $latency_mysql_ms MySQL phase latency in milliseconds.
		 *     @type float  $latency_pg_extension_checks_ms PostgreSQL extension checks phase latency in milliseconds.
		 *     @type float  $latency_pg_vector_readiness_ms PostgreSQL vector readiness phase latency in milliseconds.
		 *     @type float  $latency_pg_execute_ms PostgreSQL SQL prepare+execute phase latency in milliseconds.
		 *     @type float  $latency_pg_fetch_ms PostgreSQL SQL fetch phase latency in milliseconds.
		 *     @type float  $latency_pg_total_ms PostgreSQL total method latency in milliseconds.
		 *     @type string $search_strategy High-level search strategy classification.
		 *     @type array  $signal_counts Match counts by signal type (fts/trigram/vector).
		 *     @type string $dominant_signal Dominant signal for PostgreSQL results.
		 *     @type bool   $mysql_merge_applied Whether MySQL merge step executed.
		 *     @type bool   $mysql_merge_contributed Whether MySQL contributed final results.
		 *     @type bool   $observability_expensive_probes_enabled Whether expensive telemetry probes are enabled.
		 *     @type bool   $observability_expensive_probe_executed Whether an expensive telemetry probe ran for this request.
		 *     @type int    $dedup_removed_count Number of MySQL candidates removed during dedupe.
		 *     @type bool   $degraded True when fallback/degradation occurred.
		 *     @type string $fallback_reason Diagnostic fallback reason code.
		 *     @type bool   $is_slow True when latency exceeds slow threshold.
		 *     @type bool   $postgresql_failed Whether PostgreSQL execution failed and fallback was used.
		 * }
		 */
		do_action(
			'gg_data_search_completed',
			// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Keep telemetry payload readable without excessive whitespace padding.
			array(
				'post_ids'              => $final_ids,
				'total_count'           => count( $final_ids ),
				'search_term'           => $search_term,
				'connection'            => $connection_name,
				'post_types'            => array_unique( array_merge( $pg_post_types, $all_search_types ) ),
				'zero_results'          => empty( $final_ids ),
				'source_type'           => $source_type,
				'retrieval_mode'        => $retrieval_mode,
				'search_strategy'       => $search_strategy,
				'signal_counts'         => $signal_counts,
				'dominant_signal'       => $dominant_signal,
				'mysql_merge_applied'   => $mysql_merge_applied,
				'mysql_merge_contributed' => $mysql_merge_contributed,
				'latency_total_ms'      => $latency_total_ms,
				'latency_postgresql_ms' => $latency_pg_ms,
				'latency_mysql_ms'      => $latency_mysql_ms,
				'latency_pg_extension_checks_ms' => $pg_diagnostics['latency_pg_extension_checks_ms'],
				'latency_pg_vector_readiness_ms' => $pg_diagnostics['latency_pg_vector_readiness_ms'],
				'latency_pg_execute_ms'          => $pg_diagnostics['latency_pg_execute_ms'],
				'latency_pg_fetch_ms'            => $pg_diagnostics['latency_pg_fetch_ms'],
				'latency_pg_total_ms'            => $pg_diagnostics['latency_pg_total_ms'],
				'observability_expensive_probes_enabled' => $pg_diagnostics['observability_expensive_probes_enabled'],
				'observability_expensive_probe_executed' => $pg_diagnostics['observability_expensive_probe_executed'],
				'observability_mode'   => $observability_mode,
				'dedup_removed_count'   => $dedup_removed_count,
				'degraded'              => $degraded,
				'fallback_reason'       => $fallback_reason,
				'is_slow'               => $is_slow,
				'postgresql_failed'     => $postgresql_failed,
				'embedding_model' => $pg_results['embedding_model'] ?? '',
				'vector_table'    => $pg_results['vector_table'] ?? '',
			)
			// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		);

		if ( ! empty( $final_ids ) ) {
			global $wpdb;
			$ids_string = implode( ',', array_map( 'intval', $final_ids ) );

			// Override search to use our merged post IDs.
			return " AND {$wpdb->posts}.ID IN ({$ids_string}) ";
		}

		// Fall back to default WordPress search.
		return $search;
	}

	/**
	 * Search MySQL for unsynced post types
	 *
	 * @param string $search_term Search query.
	 * @param array  $post_types Post types to search in MySQL.
	 * @return array Post IDs found by MySQL search.
	 */
	private function search_mysql_unsynced_types( $search_term, $post_types ) {
		global $wpdb;

		if ( empty( $post_types ) || empty( $search_term ) ) {
			return array();
		}

		// Escape search term for LIKE query.
		$search_term_escaped = '%' . $wpdb->esc_like( $search_term ) . '%';

		// Build post type placeholders.
		$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// Query MySQL for unsynced post types using basic LIKE search.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders dynamically constructed for IN clause with variable post types count, safe from SQL injection
		$sql = $wpdb->prepare(
			"SELECT ID 
		FROM {$wpdb->posts} 
		WHERE post_status = 'publish'
		AND post_type IN ({$type_placeholders})
		AND (post_title LIKE %s OR post_content LIKE %s)
		LIMIT 50",
			array_merge( $post_types, array( $search_term_escaped, $search_term_escaped ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Required to fetch post IDs matching search term from WordPress core table for fallback search. SQL is prepared above using $wpdb->prepare()
		$results = $wpdb->get_col( $sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return array_map( 'intval', $results );
	}

	/**
	 * Filter posts order by
	 *
	 * Maintains PostgreSQL relevance ranking order.
	 *
	 * @param string   $orderby Order by SQL.
	 * @param WP_Query $query Query object.
	 * @return string Modified order by SQL.
	 */
	public function filter_posts_orderby( $orderby, $query ) {
		// Only process if we have cached search results.
		if ( empty( $this->search_results ) || ! $query->is_search() || ! $query->is_main_query() ) {
			return $orderby;
		}

		// Extract post IDs in relevance order.
		$post_ids = array_column( $this->search_results, 'post_id' );

		if ( empty( $post_ids ) ) {
			return $orderby;
		}

		global $wpdb;
		$ids_string = implode( ',', array_map( 'intval', $post_ids ) );

		// Use MySQL FIELD() to maintain PostgreSQL relevance order.
		// FIELD(ID, 123, 456, 789) returns the position in the list.
		return "FIELD({$wpdb->posts}.ID, {$ids_string})";
	}

	/**
	 * Execute PostgreSQL search
	 *
	 * Enhanced search features automatically enabled when "Enable Enhanced Search" toggle is on:
	 * - Full-text search with field weighting (title, excerpt, content)
	 * - Stemming ("running" finds "run", "runs", "ran")
	 * - Stop word filtering (ignores "the", "a", "and")
	 * - Typo tolerance (auto-enabled if pg_trgm extension available)
	 * - Vector-based semantic search (auto-enabled if pgvector extension + vectors available)
	 *
	 * Search priority: FTS (exact) → Trigram (typo-tolerant) → Vector (semantic)
	 * Vector search finds conceptually related content (e.g., "vehicle" finds "car", "automobile")
	 *
	 * @param string      $search_term      Search query.
	 * @param array       $post_types       Post types to search.
	 * @param string|null $connection_name  Optional. Specific connection to use.
	 * @param string|null $embedding_model  Optional. Embedding model to use for vector search.
	 * @return array Search results.
	 * @throws Exception If no active connection is available or database query fails.
	 */
	private function execute_postgresql_search( $search_term, $post_types, $connection_name = null, $embedding_model = null ) {
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
		$pg_total_start               = microtime( true );
		$pg_diagnostics               = $this->get_default_postgresql_diagnostics();
		$expensive_probes_enabled  = $this->is_observability_enabled();
		$expensive_probe_executed  = false;
		$pg_diagnostics['observability_expensive_probes_enabled'] = $expensive_probes_enabled;
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		// Get active connection name if not provided.
		if ( empty( $connection_name ) ) {
			$connection_name = $this->get_active_connection();
		}

		if ( empty( $connection_name ) ) {
			throw new Exception( 'No active PostgreSQL connection name available' );
		}

		// Get connection config to determine provider type.
		$connections = $this->settings->get_all_connections();
		$config      = $connections[ $connection_name ] ?? array();
		$provider    = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		// Route to appropriate implementation based on provider.
		if ( 'postgrest' === $provider ) {
			$this->last_postgresql_diagnostics = $pg_diagnostics;
			return $this->execute_postgresql_search_supabase( $search_term, $post_types, $connection_name, $config, $embedding_model );
		}

		// PDO PostgreSQL path.
		$connection = $this->db->get_connection( $connection_name );

		if ( ! $connection ) {
			throw new Exception( 'No PostgreSQL connection available for: ' . esc_html( $connection_name ) );
		}

		// Get stored search settings from global settings.
		$language             = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'language', 'english' );
		$similarity_threshold = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', 0.5 );

		/**
		 * Filter the trigram similarity threshold for typo tolerance.
		 *
		 * Controls how similar the search term must be to content for trigram pattern matching.
		 * Lower threshold = more lenient matching (more results, more false positives).
		 * Higher threshold = stricter matching (fewer results, fewer false positives).
		 *
		 * @since 1.0.0
		 *
		 * @param float  $similarity_threshold Default similarity threshold (0.0-1.0).
		 * @param string $connection_name      PostgreSQL connection name.
		 * @param string $search_term          The search query.
		 * @return float Filtered similarity threshold.
		 */
		$similarity_threshold = apply_filters(
			'gg_data_search_similarity_threshold',
			$similarity_threshold,
			$connection_name,
			$search_term
		);

		$typo_tolerance = false;
		$has_pgvector   = false;

		if ( $expensive_probes_enabled ) {
			$extension_checks_start = microtime( true );

			// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
			try {
				$extension_check = $connection->query( "SELECT COUNT(*) FROM pg_extension WHERE extname = 'pg_trgm'" );
				$has_extension   = $extension_check && $extension_check->fetchColumn() > 0;
				$typo_tolerance  = $has_extension;
			} catch ( Exception $e ) {
				$typo_tolerance = false;
				$this->logger->log(
					'pg_trgm extension check failed; continuing search with typo tolerance disabled',
					'debug',
					'search',
					$connection_name,
					array(
						'exception' => $e->getMessage(),
					)
				);
			}

			try {
				$extension_check = $connection->query( "SELECT COUNT(*) FROM pg_extension WHERE extname = 'vector'" );
				$has_pgvector    = $extension_check && $extension_check->fetchColumn() > 0;
			} catch ( Exception $e ) {
				$has_pgvector = false;
			}

			$this->persist_search_capabilities( $connection_name, $typo_tolerance, $has_pgvector );
			$pg_diagnostics['latency_pg_extension_checks_ms'] = round( ( microtime( true ) - $extension_checks_start ) * 1000, 2 );
			// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		} else {
			// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep OFF-branch assignments compact and readable.
			$capabilities    = $this->get_persisted_search_capabilities( $connection_name );
			$typo_tolerance  = ! empty( $capabilities['trigram_supported'] );
			$has_pgvector    = ! empty( $capabilities['vector_supported'] );
			$pg_diagnostics['latency_pg_extension_checks_ms'] = 0.0;
			// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		}

		// Vector search is automatically enabled when enhanced search is on (if pgvector extension + vectors are available).
		// Requirements: pgvector extension installed AND vectors exist.
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
		$vector_search = false;
		$has_vectors = false;
		$vector_table = '';
		$vector_column = '';
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		// Determine which embedding model to use.
		$model_key = $this->get_embedding_model_for_search( $connection_name, $embedding_model );

		// Get vector table configuration from model.
		$vector_config = $this->get_vector_table_config( $connection_name, $model_key );

		if ( ! is_wp_error( $vector_config ) ) {
			$vector_table  = $vector_config['table_name'];
			$vector_column = $vector_config['content_column'];
		}

		// Base capability path: extension + table config enables vector path even when observability is OFF.
		$vector_search = $has_pgvector && ! empty( $vector_table );

		if ( $expensive_probes_enabled ) {
			$vector_readiness_start = microtime( true );

			try {
				if ( $vector_search ) {
					// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
					$vector_check = $connection->query( "SELECT COUNT(*) FROM {$vector_table} WHERE embedding IS NOT NULL" );
					$vector_count = $vector_check ? (int) $vector_check->fetchColumn() : 0;
					$has_vectors  = $vector_count > 0;
					// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
					$expensive_probe_executed = true;

					// Keep vector search true only when vectors exist once readiness probe is enabled.
					$vector_search = $has_vectors;
				}
			} catch ( Exception $e ) {
				// Vector search disabled if table probe fails while observability readiness checks are enabled.
				$vector_search = false;
			}

			$pg_diagnostics['latency_pg_vector_readiness_ms'] = round( ( microtime( true ) - $vector_readiness_start ) * 1000, 2 );
		} else {
			$pg_diagnostics['latency_pg_vector_readiness_ms'] = 0.0;
		}

		$pg_diagnostics['observability_expensive_probe_executed'] = $expensive_probe_executed;
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		// Build post types array for PostgreSQL.
		// Use ARRAY constructor in SQL instead of parameter binding for array.
		$post_types_safe  = array_map(
			function ( $type ) {
				return "'" . str_replace( "'", "''", $type ) . "'";
			},
			$post_types
		);
		$post_types_array = 'ARRAY[' . implode( ',', $post_types_safe ) . ']::text[]';

		// Prepare search function parameters.
		$precomputed_query_vector = $this->get_precomputed_query_vector_literal( $search_term, $model_key, $vector_table );
		$sql                      = "SELECT * FROM search_native_orchestrate(:search_term::text, {$post_types_array}, 50, :language::text, :enable_trigram::boolean, :similarity_threshold::real, :enable_vector::boolean, :vector_table::text, :vector_column::text, :rrf_k::integer, :precomputed_query_vector::text)";

		try {
			$params = array(
				':search_term'              => $search_term,
				':language'                 => $language,
				':enable_trigram'           => $typo_tolerance ? 'true' : 'false',
				':similarity_threshold'     => $similarity_threshold,
				':enable_vector'            => $vector_search ? 'true' : 'false',
				':vector_table'             => ! empty( $vector_table ) ? $vector_table : 'wp_posts_tfidf_300',
				':vector_column'            => ! empty( $vector_column ) ? $vector_column : 'embedding',
				':rrf_k'                    => 60,
				':precomputed_query_vector' => $precomputed_query_vector,
			);

			$execute_start = microtime( true );

			$stmt = $connection->prepare( $sql );
			$stmt->execute( $params );
			$pg_diagnostics['latency_pg_execute_ms'] = round( ( microtime( true ) - $execute_start ) * 1000, 2 );

			$fetch_start = microtime( true );

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$results = $stmt->fetchAll( PDO::FETCH_ASSOC );

			$pg_diagnostics['latency_pg_fetch_ms'] = round( ( microtime( true ) - $fetch_start ) * 1000, 2 );
			$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
			$this->last_postgresql_diagnostics     = $pg_diagnostics;

			$results['embedding_model'] = $model_key;
			$results['vector_table']    = $vector_table;

			$this->logger->log(
				'PostgreSQL search executed. Search term: ' . esc_html( $search_term ) . ', Result count: ' . count( $results ),
				'info',
				'search',
				null,
				array(
					'search_term'      => $search_term,
					'result_count'     => count( $results ),
					'fusion_signals'   => array_count_values( array_column( $results, 'match_type' ) ),
					'embedding_model'  => $model_key,
					'vector_table'     => $vector_table,
				)
			);
			return $results;
		} catch ( Exception $e ) {
			$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
			$this->last_postgresql_diagnostics     = $pg_diagnostics;

			$this->logger->log(
				'PostgreSQL search failed: ' . $e->getMessage(),
				'error',
				'search',
				null,
				array( 'exception' => get_class( $e ) )
			);

			if ( $this->is_missing_function_error( $e, 'search_native_orchestrate' ) ) {
				$repair_result = $this->repair_missing_search_functions( $connection, $connection_name );

				if ( ! is_wp_error( $repair_result ) && $repair_result ) {
					$this->logger->log(
						'Repaired missing PostgreSQL search functions; retrying original search function',
						'warning',
						'search',
						$connection_name,
						array( 'sql_function' => 'search_native_orchestrate' )
					);

					try {
						$retry_execute_start = microtime( true );
						$retry_stmt          = $connection->prepare( $sql );
						$retry_stmt->execute( $params );
						$pg_diagnostics['latency_pg_execute_ms'] += round( ( microtime( true ) - $retry_execute_start ) * 1000, 2 );

						$retry_fetch_start = microtime( true );
						// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO required for direct PostgreSQL connections, which is the plugin's core architecture
						$retry_results     = $retry_stmt->fetchAll( PDO::FETCH_ASSOC );
						$pg_diagnostics['latency_pg_fetch_ms'] += round( ( microtime( true ) - $retry_fetch_start ) * 1000, 2 );
						$pg_diagnostics['latency_pg_total_ms']  = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
						$this->last_postgresql_diagnostics      = $pg_diagnostics;

						$this->logger->log(
							'PostgreSQL search retry after function repair succeeded',
							'warning',
							'search',
							null,
							array(
								'search_term'  => $search_term,
								'result_count' => count( $retry_results ),
								'sql_function' => 'search_native_orchestrate',
							)
						);

						return $retry_results;
					} catch ( Exception $retry_error ) {
						$this->logger->log(
							'PostgreSQL search retry after repair failed: ' . $retry_error->getMessage(),
							'error',
							'search',
							null,
							array( 'exception' => get_class( $retry_error ) )
						);
					}
				}
			}

			throw $e;
		}
	}

	/**
	 * Check whether a PDO exception indicates a missing PostgreSQL function.
	 *
	 * @param Exception $exception     Exception thrown by PDO.
	 * @param string    $function_name Function name to match in the error payload.
	 * @return bool
	 */
	private function is_missing_function_error( $exception, $function_name ) {
		$message = $exception->getMessage();

		if ( empty( $message ) ) {
			return false;
		}

		return false !== stripos( $message, 'SQLSTATE[42883]' )
			&& false !== stripos( $message, 'function' )
			&& false !== stripos( $message, $function_name )
			&& false !== stripos( $message, 'does not exist' );
	}

	/**
	 * Attempt to recreate PostgreSQL search SQL functions on the active PDO connection.
	 *
	 * @param PDO    $connection      Active PostgreSQL PDO connection.
	 * @param string $connection_name Connection identifier for settings/logging scope.
	 * @return bool|WP_Error True when functions were recreated, false on expected failure, or WP_Error when schema class is unavailable.
	 */
	private function repair_missing_search_functions( $connection, $connection_name ) {
		$schema_class = 'GG_Data_Search_Schema';

		if ( ! class_exists( $schema_class ) ) {
			$schema_file = GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-schema.php';
			if ( file_exists( $schema_file ) ) {
				require_once $schema_file;
			}
		}

		if ( ! class_exists( $schema_class ) ) {
			return new WP_Error( 'gg_data_missing_search_schema_class', 'Search schema class unavailable for runtime repair.' );
		}

		try {
			$schema = new GG_Data_Search_Schema();
			return (bool) $schema->create_search_function( $connection, $connection_name );
		} catch ( Exception $repair_exception ) {
			$this->logger->log(
				'Runtime search function repair failed: ' . $repair_exception->getMessage(),
				'error',
				'search',
				$connection_name,
				array( 'exception' => get_class( $repair_exception ) )
			);

			return false;
		}
	}

	/**
	 * Get the active PostgreSQL connection name for search
	 *
	 * Returns the connection configured in global search settings.
	 * Search settings are stored globally under the '__global__' scope key.
	 *
	 * @param string|null $connection_name Optional. Specific connection to use (overrides setting).
	 * @return string|null Connection name for search or null if not configured.
	 */
	private function get_active_connection( $connection_name = null ) {
		// If specific connection is requested, validate it and return it.
		if ( ! empty( $connection_name ) ) {
			$connections = $this->settings->get_all_connections();
			if ( isset( $connections[ $connection_name ] ) ) {
				return $connection_name;
			}
			// Connection not found, fall through to read from settings.
		}

		// Read connection from global search settings.
		$search_connection = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'connection', '' );

		if ( ! empty( $search_connection ) ) {
			// Validate the connection exists and is active.
			$connections = $this->settings->get_all_connections();
			if ( isset( $connections[ $search_connection ] ) ) {
				$is_active_value = $connections[ $search_connection ]['is_active'] ?? '';
				$is_active       = ( 'b:1;' === $is_active_value || '1' === $is_active_value || true === $is_active_value );
				if ( $is_active ) {
					return $search_connection;
				}
			}
		}

		return null;
	}

	/**
	 * Execute Supabase semantic search via RPC
	 *
	 * Calls the search_native_orchestrate PostgreSQL function via Supabase REST API RPC endpoint.
	 * This enables full semantic search with vector similarity using the <=> operator.
	 *
	 * @param string $search_term     Search query.
	 * @param array  $post_types      Post types to search.
	 * @param string $connection_name Connection name for settings.
	 * @param array  $config          Connection configuration.
	 * @param string $embedding_model Optional. Embedding model to use.
	 * @return array Search results.
	 * @throws Exception If Supabase API call fails.
	 */
	private function execute_postgresql_search_supabase( $search_term, $post_types, $connection_name, $config, $embedding_model = null ) {
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.
		$pg_total_start               = microtime( true );
		$pg_diagnostics               = $this->get_default_postgresql_diagnostics();
		$expensive_probes_enabled  = $this->is_observability_enabled();
		$expensive_probe_executed  = false;
		$pg_diagnostics['observability_expensive_probes_enabled'] = $expensive_probes_enabled;
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once __DIR__ . '/../providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );

		if ( is_wp_error( $runtime_config ) ) {
			$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
			$this->last_postgresql_diagnostics     = $pg_diagnostics;
			throw new Exception( esc_html( $runtime_config->get_error_message() ) );
		}

		// Get search settings from global settings.
		$language             = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'language', 'english' );
		$similarity_threshold = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', 0.5 );

		/**
		 * Filter the trigram similarity threshold (Supabase).
		 *
		 * @since 1.0.0
		 * @param float  $similarity_threshold Default similarity threshold.
		 * @param string $connection_name      Connection name.
		 * @param string $search_term          Search query.
		 * @return float Filtered threshold.
		 */
		$similarity_threshold = apply_filters(
			'gg_data_search_similarity_threshold',
			$similarity_threshold,
			$connection_name,
			$search_term
		);

		$typo_tolerance = false;
		$has_pgvector   = false;
		$vector_search  = false;
		$has_vectors    = false;
		$vector_table   = '';
		$vector_column  = '';

		if ( $expensive_probes_enabled ) {
			$extension_checks_start = microtime( true );
			$capabilities           = $this->get_supabase_extension_capabilities( $runtime_config );
			$typo_tolerance         = ! empty( $capabilities['trigram_supported'] );
			$has_pgvector           = ! empty( $capabilities['vector_supported'] );
			$this->persist_search_capabilities( $connection_name, $typo_tolerance, $has_pgvector );
			$pg_diagnostics['latency_pg_extension_checks_ms'] = round( ( microtime( true ) - $extension_checks_start ) * 1000, 2 );
		} else {
			// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep OFF-branch assignments compact and readable.
			$capabilities    = $this->get_persisted_search_capabilities( $connection_name );
			$typo_tolerance  = ! empty( $capabilities['trigram_supported'] );
			$has_pgvector    = ! empty( $capabilities['vector_supported'] );
			$pg_diagnostics['latency_pg_extension_checks_ms'] = 0.0;
			// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		}
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Keep assignment formatting compact in telemetry block.

		// Determine which embedding model to use.
		$model_key = $this->get_embedding_model_for_search( $connection_name, $embedding_model );

		// Get vector table configuration from model.
		$vector_config = $this->get_vector_table_config( $connection_name, $model_key );

		if ( ! is_wp_error( $vector_config ) ) {
			$vector_table  = $vector_config['table_name'];
			$vector_column = $vector_config['content_column'];
		}

		// Base capability path: extension + table config enables vector path even when observability is OFF.
		$vector_search = $has_pgvector && ! empty( $vector_table );

		if ( $expensive_probes_enabled ) {
			$vector_readiness_start = microtime( true );

			// Check for pgvector extension + vectors.
			if ( $vector_search ) {
				// Exact row counts require Supabase to evaluate full cardinality and are therefore opt-in.
				$vectors_url = rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/' . $vector_table;
				$args        = array(
					'method'  => 'HEAD',
					'headers' => array_merge(
						GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
						array(
							'Prefer' => 'count=exact',
						)
					),
				);

				$response = wp_remote_request( $vectors_url, $args );
				if ( ! is_wp_error( $response ) ) {
					$content_range = wp_remote_retrieve_header( $response, 'content-range' );
					if ( ! empty( $content_range ) && preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
						$has_vectors = (int) $matches[1] > 0;
					}
					$expensive_probe_executed = true;
				}

				$vector_search = $has_pgvector && $has_vectors;
			}

			$pg_diagnostics['latency_pg_vector_readiness_ms'] = round( ( microtime( true ) - $vector_readiness_start ) * 1000, 2 );
		} else {
			$pg_diagnostics['latency_pg_vector_readiness_ms'] = 0.0;
		}
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		$pg_diagnostics['observability_expensive_probe_executed'] = $expensive_probe_executed;
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		// Build RPC request payload.
		$rpc_url = rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/rpc/search_native_orchestrate';
		$payload = array(
			'search_text'          => $search_term,
			'post_types'           => $post_types,
			'limit_count'          => 50,
			'search_language'      => $language,
			'enable_trigram'       => $typo_tolerance,
			'similarity_threshold' => (float) $similarity_threshold,
			'enable_vector'        => $vector_search,
			'vector_table'         => ! empty( $vector_table ) ? $vector_table : 'wp_posts_tfidf_300',
			'vector_column'        => ! empty( $vector_column ) ? $vector_column : 'embedding',
			'rrf_k'                => 60,
		);

		// Prepare HTTP request args.
		$args = array(
			'method'  => 'POST',
			'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		);

		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Long telemetry keys make strict alignment impractical here.
		$execute_start = microtime( true );

		$response = wp_remote_request( $rpc_url, $args );
		$pg_diagnostics['latency_pg_execute_ms'] = round( ( microtime( true ) - $execute_start ) * 1000, 2 );
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		if ( is_wp_error( $response ) ) {
			$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
			$this->last_postgresql_diagnostics     = $pg_diagnostics;
			throw new Exception( 'Supabase RPC request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$fetch_start = microtime( true );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$pg_diagnostics['latency_pg_fetch_ms'] = round( ( microtime( true ) - $fetch_start ) * 1000, 2 );
			$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
			$this->last_postgresql_diagnostics     = $pg_diagnostics;
			throw new Exception( 'Supabase RPC returned status ' . esc_html( $status_code ) . ' - ' . esc_html( $body ) );
		}

		$results = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$pg_diagnostics['latency_pg_fetch_ms'] = round( ( microtime( true ) - $fetch_start ) * 1000, 2 );
			$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
			$this->last_postgresql_diagnostics     = $pg_diagnostics;
			throw new Exception( 'Failed to decode Supabase RPC response' );
		}

		$pg_diagnostics['latency_pg_fetch_ms'] = round( ( microtime( true ) - $fetch_start ) * 1000, 2 );
		$pg_diagnostics['latency_pg_total_ms'] = round( ( microtime( true ) - $pg_total_start ) * 1000, 2 );
		$this->last_postgresql_diagnostics     = $pg_diagnostics;

		$results['embedding_model'] = $model_key;
		$results['vector_table']    = $vector_table;

		return $results;
	}

	/**
	 * Get Supabase extension capability map from the canonical schema-status RPC.
	 *
	 * @param array $runtime_config Canonical Supabase runtime config.
	 * @return array {
	 *     Capability map.
	 *
	 *     @type bool $trigram_supported True when pg_trgm support is reported.
	 *     @type bool $vector_supported  True when vector support is reported.
	 * }
	 */
	private function get_supabase_extension_capabilities( $runtime_config ) {
		$capabilities = array(
			'trigram_supported' => false,
			'vector_supported'  => false,
		);

		$rpc_url = rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/rpc/get_schema_status';
		$args    = array(
			'method'  => 'POST',
			'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], false ),
			'body'    => '{}',
		);

		$response = wp_remote_request( $rpc_url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $capabilities;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['extensions'] ) || ! is_array( $data['extensions'] ) ) {
			return $capabilities;
		}

		$capabilities['trigram_supported'] = ! empty( $data['extensions']['pg_trgm'] );
		$capabilities['vector_supported']  = ! empty( $data['extensions']['vector'] );

		return $capabilities;
	}

	/**
	 * Get vector table configuration from embedding model
	 *
	 * Resolves the PostgreSQL table and column names from the model's configuration.
	 * Uses row-per-embedding schema with single 'embedding' column and 'field_type' for differentiation.
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_key       Embedding model key (e.g., 'tfidf-300').
	 * @return array|WP_Error {
	 *     Vector table configuration or error.
	 *
	 *     @type string $table_name        PostgreSQL table name (e.g., 'wp_posts_tfidf_300').
	 *     @type string $content_column    Column name for vectors (always 'embedding' in row-per-embedding schema).
	 * }
	 */
	private function get_vector_table_config( $connection_name, $model_key ) {
		// Get model from registry.
		$model = $this->model_registry->get_model( $connection_name, $model_key );

		if ( ! $model ) {
			return new WP_Error(
				'gg_data_model_not_found',
				/* translators: 1: Embedding model key, 2: Connection name */
				sprintf( __( 'Embedding model "%1$s" not found for connection "%2$s"', 'gregius-data' ), $model_key, $connection_name )
			);
		}

		// Validate it's an embedding model.
		if ( ! isset( $model['dimensions'] ) ) {
			return new WP_Error(
				'gg_data_not_embedding_model',
				/* translators: %s: Model key */
				sprintf( __( 'Model "%s" is not an embedding model', 'gregius-data' ), $model_key )
			);
		}

		// Get table name from model config.
		$table_name = isset( $model['vector_table_name'] ) ? $model['vector_table_name'] : '';

		if ( empty( $table_name ) ) {
			return new WP_Error(
				'gg_data_missing_table_name',
				/* translators: %s: Embedding model key */
				sprintf( __( 'Embedding model "%s" has no vector_table_name configured', 'gregius-data' ), $model_key )
			);
		}

		// Row-per-embedding schema uses single 'embedding' column with 'field_type' for differentiation.
		// The field_type column distinguishes between 'title', 'excerpt', and 'chunk' embeddings.
		$content_column = 'embedding';

		return array(
			'table_name'     => $table_name,
			'content_column' => $content_column,
		);
	}

	/**
	 * Get embedding model for search
	 *
	 * Determines which embedding model to use based on:
	 * 1. Explicit parameter (highest priority)
	 * 2. Connection's search setting
	 * 3. Default fallback (tfidf-300)
	 *
	 * @param string      $connection_name Connection name.
	 * @param string|null $model_key_param Optional model key passed as parameter.
	 * @return string Embedding model key to use.
	 */
	private function get_embedding_model_for_search( $connection_name, $model_key_param = null ) {
		// Priority 1: Explicit parameter.
		if ( ! empty( $model_key_param ) ) {
			return $model_key_param;
		}

		// Priority 2: Global search setting.
		$search_model = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'embedding_model', null );
		if ( ! empty( $search_model ) ) {
			return $search_model;
		}

		// Priority 3: Default fallback.
		return 'tfidf-300';
	}

	/**
	 * Build a precomputed query vector literal for stateless hashing models.
	 *
	 * @param string $search_term Query text.
	 * @param string $model_key   Active embedding model key.
	 * @param string $vector_table Active vector table.
	 * @return string|null PostgreSQL vector literal when supported, otherwise null.
	 */
	private function get_precomputed_query_vector_literal( $search_term, $model_key, $vector_table ) {
		if ( empty( $search_term ) || ! is_string( $search_term ) ) {
			return null;
		}

		$model_key    = strtolower( (string) $model_key );
		$vector_table = strtolower( (string) $vector_table );

		if ( false === strpos( $model_key, 'hashingtf' ) && false === strpos( $vector_table, 'hashingtf' ) ) {
			return null;
		}

		if ( ! class_exists( 'GG_Data_HashingTF_Embeddings' ) ) {
			require_once dirname( __DIR__ ) . '/vectors/class-gg-data-hashingtf-embeddings.php';
		}

		$embeddings = new GG_Data_HashingTF_Embeddings();

		return $embeddings->generate_query_vector_literal( $search_term );
	}

	/**
	 * Get retrieval mode for native search.
	 *
	 * @return string One of: hybrid_default, postgresql_only.
	 */
	private function get_retrieval_mode() {
		$retrieval_mode = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'retrieval_mode', 'hybrid_default' );

		if ( ! is_string( $retrieval_mode ) ) {
			return 'hybrid_default';
		}

		$retrieval_mode = sanitize_key( $retrieval_mode );

		if ( ! in_array( $retrieval_mode, array( 'hybrid_default', 'postgresql_only' ), true ) ) {
			return 'hybrid_default';
		}

		return $retrieval_mode;
	}

	/**
	 * Determine whether observability probes are enabled.
	 *
	 * @return bool
	 */
	private function is_observability_enabled() {
		$enabled = $this->settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'observability_enabled', false );

		if ( is_string( $enabled ) ) {
			return in_array( $enabled, array( '1', 'true' ), true );
		}

		return (bool) $enabled;
	}

	/**
	 * Get persisted search capabilities for a connection.
	 *
	 * OFF mode relies on persisted capability truth and does not perform live checks.
	 *
	 * @param string $connection_name Connection name.
	 * @return array {
	 *     Persisted capability map.
	 *
	 *     @type bool $trigram_supported True when trigram is supported.
	 *     @type bool $vector_supported  True when vector is supported.
	 * }
	 */
	private function get_persisted_search_capabilities( $connection_name ) {
		$trigram_supported = $this->settings->get_with_category( 'search', $connection_name, 'trigram_supported', false );
		$vector_supported  = $this->settings->get_with_category( 'search', $connection_name, 'vector_supported', false );

		return array(
			'trigram_supported' => true === $trigram_supported || 1 === $trigram_supported || '1' === $trigram_supported,
			'vector_supported'  => true === $vector_supported || 1 === $vector_supported || '1' === $vector_supported,
		);
	}

	/**
	 * Persist search capability state for a connection.
	 *
	 * @param string $connection_name    Connection name.
	 * @param bool   $trigram_supported  Whether pg_trgm support is available.
	 * @param bool   $vector_supported   Whether vector support is available.
	 * @return void
	 */
	private function persist_search_capabilities( $connection_name, $trigram_supported, $vector_supported ) {
		if ( empty( $connection_name ) ) {
			return;
		}

		$this->settings->set_with_category_public( 'search', $connection_name, 'trigram_supported', (bool) $trigram_supported );
		$this->settings->set_with_category_public( 'search', $connection_name, 'vector_supported', (bool) $vector_supported );
	}

	/**
	 * Build signal counts from PostgreSQL result rows.
	 *
	 * @param array $results PostgreSQL result rows.
	 * @return array
	 */
	private function build_signal_counts_from_results( $results ) {
		$signal_counts = array(
			'fts'     => 0,
			'trigram' => 0,
			'vector'  => 0,
		);

		foreach ( $results as $row ) {
			$match_type = isset( $row['match_type'] ) ? sanitize_key( (string) $row['match_type'] ) : '';

			if ( false !== strpos( $match_type, 'fts' ) ) {
				++$signal_counts['fts'];
			}

			if ( false !== strpos( $match_type, 'trigram' ) ) {
				++$signal_counts['trigram'];
			}

			if ( false !== strpos( $match_type, 'vector' ) ) {
				++$signal_counts['vector'];
			}
		}

		return $signal_counts;
	}

	/**
	 * Determine dominant signal from signal counts.
	 *
	 * @param array $signal_counts Signal counts by type.
	 * @return string
	 */
	private function determine_dominant_signal( $signal_counts ) {
		$max_count = max( $signal_counts );

		if ( $max_count <= 0 ) {
			return 'none';
		}

		$dominant = array_keys(
			array_filter(
				$signal_counts,
				static function ( $count ) use ( $max_count ) {
					return (int) $count === (int) $max_count;
				}
			)
		);

		if ( count( $dominant ) > 1 ) {
			return 'mixed';
		}

		return $dominant[0];
	}

	/**
	 * Determine high-level search strategy based on PostgreSQL signals and MySQL contribution.
	 *
	 * @param array $signal_counts Signal counts by type.
	 * @param bool  $mysql_merge_contributed Whether MySQL contributed final results.
	 * @return string
	 */
	private function determine_search_strategy( $signal_counts, $mysql_merge_contributed ) {
		$has_fts     = ! empty( $signal_counts['fts'] );
		$has_trigram = ! empty( $signal_counts['trigram'] );
		$has_vector  = ! empty( $signal_counts['vector'] );

		if ( $mysql_merge_contributed ) {
			if ( ! $has_fts && ! $has_trigram && ! $has_vector ) {
				return 'mysql_only';
			}

			return 'hybrid_with_mysql';
		}

		if ( $has_fts && ! $has_trigram && ! $has_vector ) {
			return 'fts_only';
		}

		if ( ! $has_fts && $has_trigram && ! $has_vector ) {
			return 'trigram_only';
		}

		if ( ! $has_fts && ! $has_trigram && $has_vector ) {
			return 'vector_only';
		}

		if ( $has_fts && $has_trigram && ! $has_vector ) {
			return 'fts_plus_trigram';
		}

		if ( $has_fts && ! $has_trigram && $has_vector ) {
			return 'fts_plus_vector';
		}

		if ( $has_fts || $has_trigram || $has_vector ) {
			return 'fused';
		}

		return 'none';
	}

	/**
	 * Determine runtime source type for current search request.
	 *
	 * @return string Source type used for interaction attribution.
	 */
	private function get_search_source_type() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wpcli';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( is_admin() ) {
			return 'admin';
		}

		return 'frontend';
	}
}
