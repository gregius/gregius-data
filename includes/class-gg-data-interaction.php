<?php
/**
 * Interaction tracking for Gregius Data
 *
 * Registers the gg_interaction custom post type and provides
 * functions for recording search and RAG interactions.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Interaction
 *
 * Manages interaction tracking via custom post type.
 */
class GG_Data_Interaction {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	const POST_TYPE = 'gg_interaction';

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	const META_PREFIX = '_gg_interaction_';

	/**
	 * Cookie name used to identify anonymous guest chat sessions.
	 *
	 * @var string
	 */
	const GUEST_SESSION_COOKIE = 'gg_rag_sid';

	/**
	 * Guest session cookie TTL in seconds.
	 *
	 * @var int
	 */
	const GUEST_SESSION_TTL = 2592000;

	/**
	 * Logger instance.
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new GG_Data_Logger();
	}

	/**
	 * Initialize the interaction system.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'load-post.php', array( $this, 'maybe_redirect_interaction_editor_to_correct_blog' ), 1 );
		add_filter( 'gg_data_should_sync_post', array( $this, 'exclude_from_realtime_sync' ), 10, 2 );

		// Hook listeners for interaction tracking.
		add_action( 'gg_data_rag_complete', array( $this, 'on_rag_complete' ), 10, 3 );
		add_action( 'gg_data_search_completed', array( $this, 'on_search_completed' ), 10, 1 );
	}

	/**
	 * Register meta fields for REST API exposure.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_fields() {
		$meta_fields = array(
			'_gg_interaction_type'            => array(
				'type'        => 'string',
				'description' => __( 'Interaction type (search or rag)', 'gregius-data' ),
			),
			'_gg_interaction_source'          => array(
				'type'        => 'string',
				'description' => __( 'Source of interaction (frontend, rest, wpcli, mcp)', 'gregius-data' ),
			),
			'_gg_interaction_connection'      => array(
				'type'        => 'string',
				'description' => __( 'Connection slug used', 'gregius-data' ),
			),
			'_gg_interaction_conversation_id' => array(
				'type'        => 'string',
				'description' => __( 'Conversation UUID for RAG multi-turn', 'gregius-data' ),
			),
			'_gg_interaction_zero_results'    => array(
				'type'        => 'boolean',
				'description' => __( 'Whether any turn had zero results', 'gregius-data' ),
			),
			'_gg_interaction_data'            => array(
				'type'        => 'string',
				'description' => __( 'JSON data with full interaction details', 'gregius-data' ),
			),
		);

		/**
		 * Filter interaction meta fields before registration.
		 *
		 * Allows developers to add custom meta fields to the gg_interaction post type.
		 * Fields added here will be registered with register_post_meta() and exposed via REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param array $meta_fields {
		 *     Array of meta field definitions keyed by meta key.
		 *
		 *     @type string $type        Data type ('string', 'integer', 'boolean', 'number').
		 *     @type string $description Field description for REST API schema.
		 *     @type mixed  $default     Optional. Default value.
		 * }
		 */
		$meta_fields = apply_filters( 'gg_data_interaction_meta_fields', $meta_fields );

		foreach ( $meta_fields as $meta_key => $args ) {
			$meta_args = array(
				'type'              => $args['type'],
				'description'       => $args['description'] ?? '',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'string' === $args['type'] ? 'sanitize_text_field' : null,
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
			);

			// Only add default if explicitly provided (WordPress requires type match).
			if ( array_key_exists( 'default', $args ) ) {
				$meta_args['default'] = $args['default'];
			}

			register_post_meta( self::POST_TYPE, $meta_key, $meta_args );
		}
	}

	/**
	 * Handle RAG completion - record interaction.
	 *
	 * @since 1.0.0
	 * @param array  $result         RAG result with answer, sources, metadata.
	 * @param string $query          User query.
	 * @param int    $execution_time Execution time in milliseconds.
	 */
	public function on_rag_complete( $result, $query, $execution_time ) {
		$meta = $result['metadata'] ?? array();
		if ( empty( $meta ) && isset( $result['legacy']['metadata'] ) && is_array( $result['legacy']['metadata'] ) ) {
			$meta = $result['legacy']['metadata'];
		}

		$conversation_id = '';
		if ( ! empty( $meta['conversation_id'] ) ) {
			$conversation_id = (string) $meta['conversation_id'];
		} elseif ( ! empty( $result['request']['conversation_id'] ) ) {
			$conversation_id = (string) $result['request']['conversation_id'];
		} elseif ( ! empty( $result['legacy']['conversation_id'] ) ) {
			$conversation_id = (string) $result['legacy']['conversation_id'];
		}

		$connection_name = '';
		if ( ! empty( $meta['connection'] ) ) {
			$connection_name = (string) $meta['connection'];
		} elseif ( ! empty( $result['legacy']['connection'] ) ) {
			$connection_name = (string) $result['legacy']['connection'];
		}

		$response_text = '';
		if ( isset( $result['answer'] ) && is_string( $result['answer'] ) ) {
			$response_text = $result['answer'];
		} elseif ( isset( $result['outcome']['answer'] ) && is_string( $result['outcome']['answer'] ) ) {
			$response_text = $result['outcome']['answer'];
		} elseif ( isset( $result['legacy']['answer'] ) && is_string( $result['legacy']['answer'] ) ) {
			$response_text = $result['legacy']['answer'];
		}

		$result_sources = array();
		if ( isset( $result['sources'] ) && is_array( $result['sources'] ) ) {
			$result_sources = $result['sources'];
		} elseif ( isset( $result['outcome']['sources'] ) && is_array( $result['outcome']['sources'] ) ) {
			$result_sources = $result['outcome']['sources'];
		} elseif ( isset( $result['legacy']['sources'] ) && is_array( $result['legacy']['sources'] ) ) {
			$result_sources = $result['legacy']['sources'];
		}

		// Skip if no conversation_id (tracking not enabled for this request).
		if ( empty( $conversation_id ) ) {
			$this->logger->log( 'RAG interaction skipped - no conversation_id', 'debug', 'interaction' );
			return;
		}

		$interaction_args = array(
			'connection'          => $connection_name,
			'query'               => $meta['search_query'] ?? $query,
			'original_query'      => $query,
			'response'            => $response_text,
			'source'              => $meta['source'] ?? array( 'type' => 'rest' ),
			'sources'             => wp_list_pluck( $result_sources, 'post_id' ),
			'sources_details'     => $result_sources,
			'citation_sources'    => $meta['citation_sources'] ?? array(),
			'tool'                => $meta['tool_selected'] ?? null,
			'models'              => array(
				'agentic'   => $meta['rewrite_model'] ?? null,
				'embedding' => $meta['embedding_model'] ?? null,
				'rerank'    => $meta['rerank_model'] ?? null,
				'answer'    => $meta['llm_model'] ?? null,
			),
			'search'              => array(
				'post_types'      => $meta['post_types'] ?? array(),
				'metadata_filter' => $meta['metadata_filter'] ?? array(),
			),
			'latency'             => array(
				'total' => $meta['execution_time'] ?? 0,
			),
			'retrieval'           => $meta['retrieval'] ?? array(),
			'policy'              => $meta['policy'] ?? array(),
			'prompt'              => $meta['prompt'] ?? array(),
			'security_check'      => $meta['security_check'] ?? array(),
			// LLM response metadata for turn recording.
			'usage'               => $meta['usage'] ?? array(),
			'provider'            => $meta['provider'] ?? '',
			'model_used'          => $meta['model_used'] ?? '',
			'reasoning_content'   => $meta['reasoning_content'] ?? '',
			'llm_response'        => $meta['raw_response'] ?? array(), // Complete API response.
			'manifest'            => $meta['manifest'] ?? array(),
			'manifest_hash'       => $meta['manifest_hash'] ?? '',
			'manifest_size_bytes' => $meta['manifest_size_bytes'] ?? 0,
			'manifest_version'    => $meta['manifest_version'] ?? '',
		);

		$post_id = 0;
		$post_id = self::record_rag(
			$conversation_id,
			$interaction_args
		);

		$record_error = null;
		if ( is_wp_error( $post_id ) ) {
			$record_error = $post_id->get_error_message();
			$post_id      = 0;
			$this->logger->log(
				'Failed to record RAG interaction: ' . $record_error,
				'error',
				'interaction'
			);
		}

		// Build log context for Logs UI display.
		$log_context = self::build_rag_log_context_payload(
			$post_id,
			$conversation_id,
			$result,
			$query,
			$interaction_args,
			$execution_time
		);

		if ( ! empty( $record_error ) ) {
			$log_context['persistence'] = array(
				'status' => 'failed',
				'error'  => (string) $record_error,
			);
		}

		/**
		 * Filter interaction log context before it's written to the logs table.
		 *
		 * Allows developers to add custom fields to the log context for display in the Logs UI.
		 * This is useful for including custom meta fields registered via gg_data_interaction_meta_fields.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $log_context The log context array to be stored.
		 * @param int    $post_id     The interaction post ID.
		 * @param string $type        The interaction type ('rag' or 'search').
		 */
		$log_context = apply_filters( 'gg_data_interaction_log_context', $log_context, $post_id, 'rag' );

		$this->logger->log(
			sprintf( 'RAG: %s', $meta['search_query'] ?? $query ),
			'info',
			'rag',
			$connection_name,
			$log_context
		);

		if ( $post_id > 0 ) {
			/**
			 * Fires after an interaction has been recorded.
			 *
			 * Allows developers to perform additional actions after an interaction is saved,
			 * such as sending notifications, updating analytics, or syncing to external systems.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $post_id Interaction post ID.
			 * @param string $type    Interaction type ('search' or 'rag').
			 * @param array  $args    Original interaction data used for recording.
			 */
			do_action( 'gg_data_interaction_recorded', $post_id, 'rag', $interaction_args );
		}
	}

	/**
	 * Build the log-facing RAG payload for the Logs UI.
	 *
	 * Uses the canonical observability payload without legacy top-level aliases.
	 *
	 * @since 1.0.0
	 * @param int    $interaction_id  Interaction post ID.
	 * @param string $conversation_id Conversation ID.
	 * @param array  $result          Result payload.
	 * @param string $original_query  Original query.
	 * @param array  $args            Interaction arguments.
	 * @param int    $execution_time  Execution time in milliseconds.
	 * @return array
	 */
	private static function build_rag_log_context_payload( $interaction_id, $conversation_id, array $result, $original_query, array $args, $execution_time ) {
		return self::build_rag_observability_payload( $interaction_id, $conversation_id, $result, $original_query, $args, $execution_time );
	}

	/**
	 * Build the enterprise observability payload for a RAG interaction.
	 *
	 * Stores canonical enterprise sections only.
	 *
	 * @since 1.0.0
	 * @param int    $interaction_id  Interaction post ID.
	 * @param string $conversation_id Conversation ID.
	 * @param array  $result          Result payload.
	 * @param string $original_query  Original query.
	 * @param array  $args            Interaction arguments.
	 * @param int    $execution_time  Execution time in milliseconds.
	 * @return array
	 */
	private static function build_rag_observability_payload( $interaction_id, $conversation_id, array $result, $original_query, array $args, $execution_time ) {
		$source         = isset( $args['source'] ) && is_array( $args['source'] ) ? $args['source'] : array();
		$security_check = isset( $args['security_check'] ) && is_array( $args['security_check'] ) ? $args['security_check'] : array();
		$manifest       = isset( $args['manifest'] ) && is_array( $args['manifest'] ) ? $args['manifest'] : array();

		$is_debug                    = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY );
		$include_verbose_diagnostics = (bool) apply_filters( 'gg_data_rag_include_verbose_diagnostics', $is_debug, $result, $args );
		$security_raw                = array();

		if ( isset( $security_check['raw_response'] ) && is_array( $security_check['raw_response'] ) ) {
			$security_raw = $security_check['raw_response'];
			unset( $security_check['raw_response'] );
		}

		$query_payload = array(
			'original'  => $original_query,
			'rewritten' => $args['query'] ?? $original_query,
		);

		$sources_payload = array_map(
			function ( $source_item ) {
				return array(
					'id'    => $source_item['post_id'] ?? 0,
					'title' => $source_item['title'] ?? '',
					'url'   => $source_item['url'] ?? '',
					'score' => $source_item['score'] ?? 0,
				);
			},
			$result['sources'] ?? array()
		);

		$models_payload = array(
			'agentic'   => $args['models']['agentic'] ?? null,
			'embedding' => $args['models']['embedding'] ?? null,
			'rerank'    => $args['models']['rerank'] ?? null,
			'answer'    => $args['models']['answer'] ?? null,
		);

		$latency_payload = array(
			'total_ms' => isset( $args['latency']['total'] ) ? (int) $args['latency']['total'] : (int) $execution_time,
		);

		$payload = array(
			'request'           => array(
				'interaction_id'  => (int) $interaction_id,
				'conversation_id' => (string) $conversation_id,
				'type'            => 'rag',
				'source'          => isset( $source['type'] ) ? sanitize_key( (string) $source['type'] ) : 'rest',
			),
			'intent'            => array(
				'original_query'     => $query_payload['original'],
				'rewritten_query'    => $query_payload['rewritten'],
				'tool_selected'      => $args['tool'] ?? null,
				'security_status'    => $security_check['status'] ?? '',
				'policy_decision'    => $args['policy']['decision'] ?? '',
				'policy_reason_code' => $args['policy']['reason_code'] ?? '',
			),
			'outcome'           => array(
				'answer'     => $args['response'] ?? '',
				'sources'    => $sources_payload,
				'latency_ms' => $latency_payload['total_ms'],
				'status'     => 'completed',
			),
			'execution'         => array(
				'models'     => $models_payload,
				'provider'   => $args['provider'] ?? '',
				'model_used' => $args['model_used'] ?? '',
				'usage'      => $args['usage'] ?? array(),
			),
			'retrieval_summary' => self::build_rag_retrieval_summary_payload( $args['retrieval'] ?? array() ),
			'policy'            => $args['policy'] ?? array(),
			'security'          => $security_check,
			'context'           => array(
				'manifest'            => self::build_rag_manifest_context_preview( $manifest ),
				'manifest_hash'       => $args['manifest_hash'] ?? '',
				'manifest_size_bytes' => isset( $args['manifest_size_bytes'] ) ? (int) $args['manifest_size_bytes'] : 0,
				'manifest_version'    => $args['manifest_version'] ?? '',
			),
			'diagnostics'       => array(
				'reasoning_content' => $args['reasoning_content'] ?? '',
				'has_security_raw'  => ! empty( $security_raw ),
				'has_llm_raw'       => isset( $args['llm_response'] ) && is_array( $args['llm_response'] ) && ! empty( $args['llm_response'] ),
			),
		);

		if ( $include_verbose_diagnostics ) {
			$payload['diagnostics']['security_raw_response'] = $security_raw;
			$payload['diagnostics']['llm_raw_response']      = $args['llm_response'] ?? array();
			$payload['context']['manifest_full']             = $manifest;
		}

		return $payload;
	}

	/**
	 * Build a slim manifest preview for default context payloads.
	 *
	 * @since 1.0.0
	 * @param array $manifest Manifest payload.
	 * @return array
	 */
	private static function build_rag_manifest_context_preview( array $manifest ) {
		$entity = isset( $manifest['entity'] ) && is_array( $manifest['entity'] ) ? $manifest['entity'] : array();
		$state  = isset( $manifest['state'] ) && is_array( $manifest['state'] ) ? $manifest['state'] : array();

		return array(
			'manifest_version' => isset( $manifest['manifest_version'] ) ? sanitize_text_field( (string) $manifest['manifest_version'] ) : '',
			'state'            => array(
				'conversation_id'      => isset( $state['conversation_id'] ) ? sanitize_text_field( (string) $state['conversation_id'] ) : '',
				'journey_depth_turns'  => isset( $state['journey_depth_turns'] ) ? (int) $state['journey_depth_turns'] : 0,
				'is_historical_resume' => ! empty( $state['is_historical_resume'] ),
				'active_mode'          => isset( $state['active_mode'] ) ? sanitize_key( (string) $state['active_mode'] ) : '',
			),
			'entity'           => array(
				'id'      => isset( $entity['id'] ) ? (int) $entity['id'] : 0,
				'type'    => isset( $entity['type'] ) ? sanitize_key( (string) $entity['type'] ) : '',
				'status'  => isset( $entity['status'] ) ? sanitize_key( (string) $entity['status'] ) : '',
				'title'   => isset( $entity['title']['rendered'] ) ? sanitize_text_field( (string) $entity['title']['rendered'] ) : '',
				'summary' => isset( $entity['meta']['_gg_manifest_summary'] ) ? sanitize_text_field( (string) $entity['meta']['_gg_manifest_summary'] ) : '',
			),
		);
	}

	/**
	 * Build a concise retrieval summary payload for interaction logs.
	 *
	 * @since 1.0.0
	 * @param array $retrieval Full retrieval payload.
	 * @return array
	 */
	private static function build_rag_retrieval_summary_payload( array $retrieval ) {
		return array(
			'mode'            => isset( $retrieval['mode'] ) ? sanitize_key( (string) $retrieval['mode'] ) : '',
			'raw_count'       => isset( $retrieval['raw_count'] ) ? (int) $retrieval['raw_count'] : 0,
			'qualified_count' => isset( $retrieval['qualified_count'] ) ? (int) $retrieval['qualified_count'] : 0,
			'selected_count'  => isset( $retrieval['selected_count'] ) ? (int) $retrieval['selected_count'] : 0,
			'posts_retrieved' => isset( $retrieval['posts_retrieved'] ) ? (int) $retrieval['posts_retrieved'] : 0,
			'chunks_final'    => isset( $retrieval['chunks_final'] ) ? (int) $retrieval['chunks_final'] : 0,
			'rerank_applied'  => ! empty( $retrieval['rerank_applied'] ),
		);
	}

	/**
	 * Handle search completion - record interaction.
	 *
	 * @since 1.0.0
	 * @param array $results Search results data.
	 */
	public function on_search_completed( $results ) {
		// Skip if search tracking is not enabled or no connection.
		if ( empty( $results['connection'] ) ) {
			return;
		}

		$source_type = isset( $results['source_type'] ) ? sanitize_key( (string) $results['source_type'] ) : 'frontend';
		if ( ! in_array( $source_type, array( 'frontend', 'admin', 'rest', 'wpcli' ), true ) ) {
			$source_type = 'frontend';
		}

		$post_id = self::record_search(
			// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Keep telemetry payload readable without excessive whitespace padding.
			array(
				'connection'   => $results['connection'],
				'query'        => $results['search_term'] ?? '',
				'zero_results' => $results['zero_results'] ?? false,
				'source'       => array( 'type' => $source_type ),
				'data'         => array(
					'query'  => array(
						'original' => $results['search_term'] ?? '',
					),
					'search' => array(
						'post_types'            => $results['post_types'] ?? array(),
						'results_count'         => $results['total_count'] ?? 0,
						'retrieval_mode'        => $results['retrieval_mode'] ?? 'hybrid_default',
						'search_strategy'       => $results['search_strategy'] ?? 'none',
						'signal_counts'         => isset( $results['signal_counts'] ) && is_array( $results['signal_counts'] ) ? $results['signal_counts'] : array(),
						'dominant_signal'       => $results['dominant_signal'] ?? 'none',
						'mysql_merge_applied'   => ! empty( $results['mysql_merge_applied'] ),
						'mysql_merge_contributed' => ! empty( $results['mysql_merge_contributed'] ),
						'latency_total_ms'      => isset( $results['latency_total_ms'] ) ? (float) $results['latency_total_ms'] : 0,
						'latency_postgresql_ms' => isset( $results['latency_postgresql_ms'] ) ? (float) $results['latency_postgresql_ms'] : 0,
						'latency_mysql_ms'      => isset( $results['latency_mysql_ms'] ) ? (float) $results['latency_mysql_ms'] : 0,
						'latency_pg_extension_checks_ms' => isset( $results['latency_pg_extension_checks_ms'] ) ? (float) $results['latency_pg_extension_checks_ms'] : 0,
						'latency_pg_vector_readiness_ms' => isset( $results['latency_pg_vector_readiness_ms'] ) ? (float) $results['latency_pg_vector_readiness_ms'] : 0,
						'latency_pg_execute_ms'          => isset( $results['latency_pg_execute_ms'] ) ? (float) $results['latency_pg_execute_ms'] : 0,
						'latency_pg_fetch_ms'            => isset( $results['latency_pg_fetch_ms'] ) ? (float) $results['latency_pg_fetch_ms'] : 0,
						'latency_pg_total_ms'            => isset( $results['latency_pg_total_ms'] ) ? (float) $results['latency_pg_total_ms'] : 0,
						'observability_expensive_probes_enabled' => ! empty( $results['observability_expensive_probes_enabled'] ),
						'observability_expensive_probe_executed' => ! empty( $results['observability_expensive_probe_executed'] ),
						'observability_mode'   => $results['observability_mode'] ?? 'baseline',
						'dedup_removed_count'   => isset( $results['dedup_removed_count'] ) ? (int) $results['dedup_removed_count'] : 0,
						'degraded'              => ! empty( $results['degraded'] ),
						'fallback_reason'       => $results['fallback_reason'] ?? '',
						'is_slow'               => ! empty( $results['is_slow'] ),
						'postgresql_failed'     => ! empty( $results['postgresql_failed'] ),
						'embedding_model'       => $results['embedding_model'] ?? '',
						'vector_table'          => $results['vector_table'] ?? '',
					),
				),
			)
			// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		);

		if ( is_wp_error( $post_id ) ) {
			$this->logger->log(
				'Failed to record search interaction: ' . $post_id->get_error_message(),
				'error',
				'interaction'
			);
		} else {
			// Build log context for Logs UI display.
			// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Keep telemetry payload readable without excessive whitespace padding.
			$log_context = array(
				'request'     => array(
					'interaction_id' => $post_id,
					'type'           => 'search',
					'source'         => $source_type,
				),
				'query'       => array(
					'text'       => $results['search_term'] ?? '',
					'post_types' => $results['post_types'] ?? array(),
				),
				'outcome'     => array(
					'results_count' => $results['total_count'] ?? 0,
					'zero_results'  => $results['zero_results'] ?? false,
				),
				'retrieval'   => array(
					'mode'                  => $results['retrieval_mode'] ?? 'hybrid_default',
					'strategy'              => $results['search_strategy'] ?? 'none',
					'signal_counts'         => isset( $results['signal_counts'] ) && is_array( $results['signal_counts'] ) ? $results['signal_counts'] : array(),
					'dominant_signal'       => $results['dominant_signal'] ?? 'none',
					'mysql_merge_applied'   => ! empty( $results['mysql_merge_applied'] ),
					'mysql_merge_contributed' => ! empty( $results['mysql_merge_contributed'] ),
					'degraded'              => ! empty( $results['degraded'] ),
					'fallback_reason'       => $results['fallback_reason'] ?? '',
					'postgresql_failed'     => ! empty( $results['postgresql_failed'] ),
					'dedup_removed_count'   => isset( $results['dedup_removed_count'] ) ? (int) $results['dedup_removed_count'] : 0,
				),
				'latency'     => array(
					'total_ms'            => isset( $results['latency_total_ms'] ) ? (float) $results['latency_total_ms'] : 0,
					'postgresql_ms'       => isset( $results['latency_postgresql_ms'] ) ? (float) $results['latency_postgresql_ms'] : 0,
					'mysql_ms'            => isset( $results['latency_mysql_ms'] ) ? (float) $results['latency_mysql_ms'] : 0,
					'pg_extension_checks_ms' => isset( $results['latency_pg_extension_checks_ms'] ) ? (float) $results['latency_pg_extension_checks_ms'] : 0,
					'pg_vector_readiness_ms'  => isset( $results['latency_pg_vector_readiness_ms'] ) ? (float) $results['latency_pg_vector_readiness_ms'] : 0,
					'pg_execute_ms'        => isset( $results['latency_pg_execute_ms'] ) ? (float) $results['latency_pg_execute_ms'] : 0,
					'pg_fetch_ms'          => isset( $results['latency_pg_fetch_ms'] ) ? (float) $results['latency_pg_fetch_ms'] : 0,
					'pg_total_ms'          => isset( $results['latency_pg_total_ms'] ) ? (float) $results['latency_pg_total_ms'] : 0,
				),
				'execution'   => array(
					'embedding_model' => $results['embedding_model'] ?? '',
					'vector_table'    => $results['vector_table'] ?? '',
				),
				'diagnostics' => array(
					'observability_mode'  => $results['observability_mode'] ?? 'baseline',
					'expensive_probes_enabled' => ! empty( $results['observability_expensive_probes_enabled'] ),
					'expensive_probe_executed' => ! empty( $results['observability_expensive_probe_executed'] ),
					'is_slow'             => ! empty( $results['is_slow'] ),
				),
			);
			// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned

			/**
			 * Filters the log context for a search interaction before logging.
			 *
			 * Allows developers to add custom fields to the interaction log entry.
			 * This is the same filter used for RAG interactions, with the type parameter
			 * indicating the interaction type.
			 *
			 * @since 1.0.0
			 *
			 * @param array $log_context The log context array.
			 * @param int   $post_id     The interaction post ID.
			 * @param string $type       The interaction type ('search').
			 */
			$log_context = apply_filters( 'gg_data_interaction_log_context', $log_context, $post_id, 'search' );

			$this->logger->log(
				sprintf( 'Search: %s', $results['search_term'] ?? '' ),
				'info',
				'search',
				$results['connection'] ?? '',
				$log_context
			);

			/**
			 * Fires after a search interaction has been recorded.
			 *
			 * Allows developers to perform additional actions after interaction storage.
			 * This is the same action used for RAG interactions, with the type parameter
			 * indicating the interaction type.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $post_id The interaction post ID.
			 * @param string $type    The interaction type ('search').
			 * @param array  $results The search results data passed to the handler.
			 */
			do_action( 'gg_data_interaction_recorded', $post_id, 'search', $results );
		}
	}

	/**
	 * Register the gg_interaction custom post type.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Interactions', 'post type general name', 'gregius-data' ),
			'singular_name'      => _x( 'Interaction', 'post type singular name', 'gregius-data' ),
			'menu_name'          => _x( 'Interactions', 'admin menu', 'gregius-data' ),
			'name_admin_bar'     => _x( 'Interaction', 'add new on admin bar', 'gregius-data' ),
			'add_new'            => _x( 'Add New', 'interaction', 'gregius-data' ),
			'add_new_item'       => __( 'Add New Interaction', 'gregius-data' ),
			'new_item'           => __( 'New Interaction', 'gregius-data' ),
			'edit_item'          => __( 'Edit Interaction', 'gregius-data' ),
			'view_item'          => __( 'View Interaction', 'gregius-data' ),
			'all_items'          => __( 'All Interactions', 'gregius-data' ),
			'search_items'       => __( 'Search Interactions', 'gregius-data' ),
			'not_found'          => __( 'No interactions found.', 'gregius-data' ),
			'not_found_in_trash' => __( 'No interactions found in Trash.', 'gregius-data' ),
		);

		$args = array(
			// Visibility - admin-only, not public.
			'public'                => false,
			'publicly_queryable'    => false,
			'exclude_from_search'   => true,

			// Admin UI - show in admin but not in menu (accessed via plugin dashboard).
			'show_ui'               => true,
			'show_in_menu'          => false,

			// REST API (uses custom controller for admin-only access).
			'show_in_rest'          => true,
			'rest_namespace'        => 'gg-data/v1',
			'rest_base'             => 'interactions',
			'rest_controller_class' => 'GG_Data_REST_Interactions_Controller',

			// Capabilities - admin-only access.
			'capability_type'       => 'post',
			'capabilities'          => array(
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
			),
			'map_meta_cap'          => false,

			// Features.
			'supports'              => array( 'title', 'editor', 'custom-fields' ),
			'can_export'            => true,

			// Labels.
			'labels'                => $labels,
		);

		register_post_type( self::POST_TYPE, $args );

		$this->logger->log( 'Interaction post type registered', 'debug', 'interaction' );
	}

	/**
	 * Resolve multisite interaction editor context without implicit cross-site lookup.
	 *
	 * Site-local editor requests must remain site-local. Cross-site editor redirects
	 * are allowed only when an explicit `site_id` is provided by a super admin and
	 * the target site contains an editable `gg_interaction` record for the same ID.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_redirect_interaction_editor_to_correct_blog() {
		if ( ! is_admin() || ! is_multisite() ) {
			return;
		}

		$method = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_UNSAFE_RAW );
		$method = is_string( $method ) ? strtoupper( sanitize_text_field( $method ) ) : '';
		if ( 'GET' !== $method ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$post_id = is_int( $post_id ) ? $post_id : 0;
		if ( $post_id <= 0 ) {
			return;
		}

		$requested_action = filter_input( INPUT_GET, 'action', FILTER_UNSAFE_RAW );
		$requested_action = is_string( $requested_action ) && '' !== $requested_action ? sanitize_key( $requested_action ) : 'edit';
		if ( 'edit' !== $requested_action ) {
			return;
		}

		// Validate nonce when present but allow legacy edit links that do not include one.
		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		if ( is_string( $nonce ) && '' !== $nonce ) {
			$nonce          = sanitize_text_field( $nonce );
			$is_valid_nonce = wp_verify_nonce( $nonce, 'update-post_' . $post_id ) || wp_verify_nonce( $nonce, 'edit-post_' . $post_id );
			if ( ! $is_valid_nonce ) {
				return;
			}
		}

		// Defensive normalization: clear leaked switch_to_blog() state before post.php resolves the post.
		if ( function_exists( 'ms_is_switched' ) ) {
			while ( ms_is_switched() ) {
				restore_current_blog();
			}
		}

		$current_post = get_post( $post_id );

		// Strict tenancy boundary: if this site resolves the post ID, never redirect.
		if ( $current_post ) {
			return;
		}

		$requested_site_id = filter_input( INPUT_GET, 'site_id', FILTER_VALIDATE_INT );
		$requested_site_id = is_int( $requested_site_id ) ? $requested_site_id : 0;
		if ( $requested_site_id <= 0 ) {
			return;
		}

		if ( ! is_super_admin() ) {
			return;
		}

		$current_site_id = get_current_blog_id();
		if ( $requested_site_id === $current_site_id ) {
			return;
		}

		$target_site = get_site( $requested_site_id );
		if ( ! $target_site ) {
			return;
		}

		$target_url = '';
		switch_to_blog( $requested_site_id );
		try {
			$candidate_post = get_post( $post_id );
			if ( ! $candidate_post || self::POST_TYPE !== $candidate_post->post_type ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$target_url = add_query_arg(
				array(
					'post'   => absint( $post_id ),
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);
		} finally {
			restore_current_blog();
		}

		if ( empty( $target_url ) ) {
			return;
		}

		wp_safe_redirect( $target_url );
		exit;
	}

	/**
	 * Exclude interactions from real-time sync.
	 *
	 * Interactions are synced manually/on-demand, not on every save.
	 *
	 * @since 1.0.0
	 * @param bool $should_sync Whether the post should be synced.
	 * @param int  $post_id     Post ID.
	 * @return bool Modified sync decision.
	 */
	public function exclude_from_realtime_sync( $should_sync, $post_id ) {
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			return false;
		}
		return $should_sync;
	}

	/**
	 * Validate a conversation ID.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Client-provided conversation ID.
	 * @return string|WP_Error Sanitized UUID or error.
	 */
	public static function validate_conversation_id( $conversation_id ) {
		if ( empty( $conversation_id ) ) {
			return new WP_Error(
				'missing_conversation_id',
				__( 'Conversation ID is required for RAG requests.', 'gregius-data' )
			);
		}

		// Validate UUID format.
		if ( ! wp_is_uuid( $conversation_id ) ) {
			return new WP_Error(
				'invalid_conversation_id',
				__( 'Conversation ID must be a valid UUID.', 'gregius-data' )
			);
		}

		return sanitize_text_field( $conversation_id );
	}

	/**
	 * Record a search interaction.
	 *
	 * Creates one post per search query.
	 *
	 * @since 1.0.0
	 * @param array $args {
	 *     Interaction arguments.
	 *
	 *     @type string $connection   Connection slug.
	 *     @type string $query        Search query.
	 *     @type bool   $zero_results Whether the search returned zero results.
	 *     @type array  $source       Source context (type, page_id, etc.).
	 *     @type array  $data         Full interaction data for JSON storage.
	 * }
	 * @return int|WP_Error Post ID or error.
	 */
	public static function record_search( array $args ) {
		$defaults = array(
			'connection'   => '',
			'query'        => '',
			'zero_results' => false,
			'source'       => array( 'type' => 'rest' ),
			'data'         => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => 'Search: ' . sanitize_text_field( $args['query'] ),
				'post_content' => '',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store meta fields.
		update_post_meta( $post_id, self::META_PREFIX . 'type', 'search' );
		update_post_meta( $post_id, self::META_PREFIX . 'source', $args['source']['type'] ?? 'rest' );
		update_post_meta( $post_id, self::META_PREFIX . 'connection', $args['connection'] );
		update_post_meta( $post_id, self::META_PREFIX . 'zero_results', (bool) $args['zero_results'] );

		$save_result = self::save_interaction_data_meta( $post_id, $args['data'] );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return $post_id;
	}

	/**
	 * Record a RAG interaction.
	 *
	 * Creates new conversation or appends turn to existing one.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Client-provided conversation UUID.
	 * @param array  $args {
	 *     Turn arguments.
	 *
	 *     @type string $connection      Connection slug.
	 *     @type string $query           User query (possibly rewritten).
	 *     @type string $original_query  Original query before rewrite.
	 *     @type string $response        AI response text.
	 *     @type array  $source          Source context (type, page_id, etc.).
	 *     @type array  $sources         Array of source post IDs.
	 *     @type array  $sources_details Array of source details (id, title, etc.).
	 *     @type array  $models          Models used (agentic, embedding, rerank, answer).
	 *     @type array  $search          Search parameters (post_types, mode).
	 *     @type array  $latency         Latency breakdown (total, search, rerank, answer).
	 *     @type array  $retrieval       Retrieval funnel details for this turn.
	 *     @type array  $policy          Retrieval policy used for this turn.
	 * }
	 * @return int|WP_Error Post ID or error.
	 */
	public static function record_rag( $conversation_id, array $args ) {
		// Validate conversation ID.
		$validated_id = self::validate_conversation_id( $conversation_id );
		if ( is_wp_error( $validated_id ) ) {
			return $validated_id;
		}

		$lock_key = self::META_PREFIX . 'lock_' . md5( $validated_id );
		if ( ! self::acquire_conversation_lock( $lock_key ) ) {
			return new WP_Error(
				'gg_data_interaction_lock_timeout',
				__( 'Could not acquire conversation lock for interaction recording.', 'gregius-data' )
			);
		}

		try {
			// Check for existing conversation by UUID (necessary for multi-turn RAG).
			$existing_ids = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find conversation by client-provided UUID.
					'meta_query'     => array(
						array(
							'key'   => self::META_PREFIX . 'conversation_id',
							'value' => $validated_id,
						),
						array(
							'key'   => self::META_PREFIX . 'type',
							'value' => 'rag',
						),
					),
				)
			);

			if ( ! empty( $existing_ids ) ) {
				// Deterministically append to the oldest conversation row.
				return self::append_rag_turn( (int) $existing_ids[0], $args );
			}

			// Create new conversation post.
			return self::create_rag_conversation( $validated_id, $args );
		} finally {
			self::release_conversation_lock( $lock_key );
		}
	}

	/**
	 * Acquire a short-lived conversation lock to prevent concurrent write clobbering.
	 *
	 * @since 1.0.0
	 * @param string $lock_key Lock option key.
	 * @param int    $timeout  Lock acquisition timeout in seconds.
	 * @return bool
	 */
	private static function acquire_conversation_lock( $lock_key, $timeout = 5 ) {
		$deadline = microtime( true ) + max( 1, (int) $timeout );

		while ( microtime( true ) < $deadline ) {
			if ( add_option( $lock_key, (string) microtime( true ), '', false ) ) {
				return true;
			}

			usleep( 100000 );
		}

		return false;
	}

	/**
	 * Release the conversation lock.
	 *
	 * @since 1.0.0
	 * @param string $lock_key Lock option key.
	 * @return void
	 */
	private static function release_conversation_lock( $lock_key ) {
		delete_option( $lock_key );
	}

	/**
	 * Create a new RAG conversation post.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Client-provided conversation UUID.
	 * @param array  $args            First turn arguments.
	 * @return int|WP_Error Post ID or error.
	 */
	private static function create_rag_conversation( $conversation_id, array $args ) {
		$defaults = array(
			'connection'      => '',
			'query'           => '',
			'original_query'  => '',
			'response'        => '',
			'source'          => array( 'type' => 'rest' ),
			'sources'         => array(),
			'sources_details' => array(),
			'models'          => array(),
			'search'          => array(),
			'latency'         => array(),
			'retrieval'       => array(),
			'policy'          => array(),
			'prompt'          => array(),
			'security_check'  => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Use original_query if not provided.
		if ( empty( $args['original_query'] ) ) {
			$args['original_query'] = $args['query'];
		}

		$turn_content = self::format_turn( 1, $args );

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => 'RAG: ' . sanitize_text_field( $args['query'] ),
				'post_content' => "# RAG Conversation\nStarted: " . current_time( 'mysql' ) . "\n\n---\n\n" . $turn_content,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store meta fields.
		update_post_meta( $post_id, self::META_PREFIX . 'type', 'rag' );
		update_post_meta( $post_id, self::META_PREFIX . 'source', $args['source']['type'] ?? 'rest' );
		update_post_meta( $post_id, self::META_PREFIX . 'conversation_id', $conversation_id );
		update_post_meta( $post_id, self::META_PREFIX . 'connection', $args['connection'] );

		if ( ! is_user_logged_in() ) {
			$guest_session_hash = self::get_guest_session_hash( true );
			if ( '' !== $guest_session_hash ) {
				update_post_meta( $post_id, self::META_PREFIX . 'guest_session_hash', $guest_session_hash );
			}
		}

		// Build data structure with first turn.
		$data = array(
			'source'         => $args['source'],
			'models'         => $args['models'],
			'search'         => $args['search'],
			'policy'         => $args['policy'],
			'prompt'         => $args['prompt'],
			'security_check' => $args['security_check'],
			'turns'          => array( self::build_turn_data( $args ) ),
			'totals'         => array(
				'turns'        => 1,
				'latency'      => $args['latency']['total'] ?? 0,
				'sources_used' => $args['sources'],
			),
		);

		$data['observability'] = self::build_rag_observability_payload(
			$post_id,
			$conversation_id,
			array( 'sources' => $args['sources_details'] ?? array() ),
			$args['original_query'] ?? $args['query'],
			$args,
			$args['latency']['total'] ?? 0
		);

		$save_result = self::save_interaction_data_meta( $post_id, $data );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return $post_id;
	}

	/**
	 * Append a turn to an existing RAG conversation.
	 *
	 * @since 1.0.0
	 * @param int   $post_id Existing conversation post ID.
	 * @param array $args    Turn arguments.
	 * @return int|WP_Error Post ID or error.
	 */
	private static function append_rag_turn( $post_id, array $args ) {
		$defaults = array(
			'query'           => '',
			'original_query'  => '',
			'response'        => '',
			'sources'         => array(),
			'sources_details' => array(),
			'latency'         => array(),
			'retrieval'       => array(),
			'policy'          => array(),
			'prompt'          => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		if ( ! is_user_logged_in() ) {
			$source_type = sanitize_key( (string) get_post_meta( $post_id, self::META_PREFIX . 'source', true ) );

			// Cookie-bound guest continuity applies to browser-origin channels only.
			$requires_guest_session_hash = in_array( $source_type, array( '', 'frontend', 'rest' ), true );

			if ( $requires_guest_session_hash ) {
				$stored_guest_session_hash  = (string) get_post_meta( $post_id, self::META_PREFIX . 'guest_session_hash', true );
				$current_guest_session_hash = self::get_guest_session_hash( true );

				if ( '' !== $stored_guest_session_hash && '' !== $current_guest_session_hash && ! hash_equals( $stored_guest_session_hash, $current_guest_session_hash ) ) {
					return new WP_Error(
						'gg_data_interaction_guest_session_mismatch',
						__( 'You do not have permission to continue this guest conversation.', 'gregius-data' ),
						array( 'status' => 403 )
					);
				}

				if ( '' === $stored_guest_session_hash && '' !== $current_guest_session_hash ) {
					update_post_meta( $post_id, self::META_PREFIX . 'guest_session_hash', $current_guest_session_hash );
				}
			}
		}

		// Read current content once so turn numbering can be recovered from stored markdown
		// if JSON meta is stale or malformed.
		$content            = (string) get_post_field( 'post_content', $post_id );
		$content_turn_count = self::count_turn_headings( $content );

		// Get existing data.
		$data_json  = get_post_meta( $post_id, self::META_PREFIX . 'data', true );
		$data       = json_decode( $data_json, true );
		$json_error = json_last_error();

		if ( ! empty( $data_json ) && ( JSON_ERROR_NONE !== $json_error || ! is_array( $data ) ) ) {
			self::log_malformed_interaction_data( $post_id, $data_json, $json_error );
		}

		if ( ! is_array( $data ) ) {
			$data = array(
				'turns'  => array(),
				'totals' => array(
					'turns'        => 0,
					'latency'      => 0,
					'sources_used' => array(),
				),
			);
		}

		if ( ! isset( $data['turns'] ) || ! is_array( $data['turns'] ) ) {
			$data['turns'] = array();
		}

		if ( ! isset( $data['totals'] ) || ! is_array( $data['totals'] ) ) {
			$data['totals'] = array();
		}

		if ( ! isset( $data['totals']['sources_used'] ) || ! is_array( $data['totals']['sources_used'] ) ) {
			$data['totals']['sources_used'] = array();
		}

		$meta_turn_count = count( $data['turns'] );
		$turn_num        = max( $meta_turn_count, $content_turn_count ) + 1;

		$existing_latency = isset( $data['totals']['latency'] ) ? (int) $data['totals']['latency'] : 0;
		$turn_latency     = isset( $args['latency']['total'] ) ? (int) $args['latency']['total'] : 0;

		// Append turn to data.
		$data['turns'][] = self::build_turn_data( $args );

		// Update totals.
		$data['totals']['turns']        = max( (int) ( $data['totals']['turns'] ?? 0 ), $turn_num );
		$data['totals']['latency']      = $existing_latency + $turn_latency;
		$data['totals']['sources_used'] = array_unique(
			array_merge(
				$data['totals']['sources_used'] ?? array(),
				$args['sources']
			)
		);

		// Keep latest applied policy at conversation level for quick inspection.
		if ( ! empty( $args['policy'] ) && is_array( $args['policy'] ) ) {
			$data['policy'] = $args['policy'];
		}

		// Keep latest prompt resolution metadata at conversation level.
		if ( ! empty( $args['prompt'] ) && is_array( $args['prompt'] ) ) {
			$data['prompt'] = $args['prompt'];
		}

		// Keep latest security check result at conversation level for audit trail.
		if ( ! empty( $args['security_check'] ) && is_array( $args['security_check'] ) ) {
			$data['security_check'] = $args['security_check'];
		}

		$data['observability'] = self::build_rag_observability_payload(
			$post_id,
			(string) get_post_meta( $post_id, self::META_PREFIX . 'conversation_id', true ),
			array( 'sources' => $args['sources_details'] ?? array() ),
			$args['original_query'] ?? $args['query'],
			$args,
			$args['latency']['total'] ?? 0
		);

		$save_result = self::save_interaction_data_meta( $post_id, $data );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		// Append to content.
		$content .= "\n\n---\n\n" . self::format_turn( $turn_num, $args );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		return $post_id;
	}

	/**
	 * Returns the current anonymous guest session hash.
	 *
	 * @since 1.0.0
	 * @param bool $create Whether to create a guest session when missing.
	 * @return string
	 */
	public static function get_guest_session_hash( $create = false ) {
		if ( is_user_logged_in() ) {
			return '';
		}

		$session_id = self::get_guest_session_id( $create );
		if ( '' === $session_id ) {
			return '';
		}

		return hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) );
	}

	/**
	 * Gets or creates an anonymous guest session id.
	 *
	 * @since 1.0.0
	 * @param bool $create Whether to create a guest session when missing.
	 * @return string
	 */
	private static function get_guest_session_id( $create = false ) {
		$cookie_name = self::GUEST_SESSION_COOKIE;

		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$session_id = sanitize_text_field( wp_unslash( (string) $_COOKIE[ $cookie_name ] ) );
			if ( '' !== $session_id && preg_match( '/^[A-Za-z0-9_-]{20,128}$/', $session_id ) ) {
				return $session_id;
			}
		}

		if ( ! $create ) {
			return '';
		}

		$session_id = wp_generate_uuid4();
		$secure     = is_ssl();
		$path       = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain     = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		if ( ! headers_sent() ) {
			setcookie( $cookie_name, $session_id, time() + self::GUEST_SESSION_TTL, $path, $domain, $secure, true );
		}

		$_COOKIE[ $cookie_name ] = $session_id;

		return $session_id;
	}

	/**
	 * Persist interaction payload meta safely.
	 *
	 * WordPress post meta runs stripslashes() on write; JSON must be slashed
	 * before storage so embedded quotes remain valid JSON after retrieval.
	 *
	 * @since 1.0.0
	 * @param int   $post_id Interaction post ID.
	 * @param array $data    Interaction payload data.
	 * @return true|WP_Error
	 */
	private static function save_interaction_data_meta( $post_id, array $data ) {
		$json = wp_json_encode( $data );

		if ( false === $json ) {
			return new WP_Error(
				'gg_data_interaction_json_encode_failed',
				__( 'Failed to encode interaction payload as JSON.', 'gregius-data' )
			);
		}

		$result = update_post_meta( $post_id, self::META_PREFIX . 'data', wp_slash( $json ) );

		if ( false === $result ) {
			$stored = get_post_meta( $post_id, self::META_PREFIX . 'data', true );
			if ( $stored !== $json ) {
				return new WP_Error(
					'gg_data_interaction_meta_write_failed',
					__( 'Failed to persist interaction payload meta.', 'gregius-data' )
				);
			}
		}

		return true;
	}

	/**
	 * Log malformed interaction payload decode attempts.
	 *
	 * @since 1.0.0
	 * @param int    $post_id    Interaction post ID.
	 * @param string $data_json  Raw JSON payload string.
	 * @param int    $json_error JSON error code.
	 * @return void
	 */
	private static function log_malformed_interaction_data( $post_id, $data_json, $json_error ) {
		$conversation_id = (string) get_post_meta( $post_id, self::META_PREFIX . 'conversation_id', true );

		$logger = new GG_Data_Logger();
		$logger->log(
			'Malformed interaction payload detected during decode fallback.',
			'warning',
			'system',
			null,
			array(
				'interaction_id'   => (int) $post_id,
				'conversation_id'  => $conversation_id,
				'json_error_code'  => (int) $json_error,
				'json_error_msg'   => json_last_error_msg(),
				'payload_bytes'    => strlen( (string) $data_json ),
				'payload_md5'      => md5( (string) $data_json ),
				'fallback_applied' => true,
			)
		);
	}

	/**
	 * Count existing markdown turn headings in a conversation body.
	 *
	 * @since 1.0.0
	 * @param string $content Conversation post content.
	 * @return int
	 */
	private static function count_turn_headings( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return 0;
		}

		if ( preg_match_all( '/^##\s+Turn\s+\d+\s*$/m', $content, $matches ) ) {
			return count( $matches[0] );
		}

		return 0;
	}

	/**
	 * Build turn data array for JSON storage.
	 *
	 * Stores normalized turn data including LLM metadata.
	 * Only includes fields with actual data (no fabrication).
	 *
	 * @since 1.0.0
	 * @param array $args Turn arguments.
	 * @return array Turn data with: timestamp, query, response, sources, latency, retrieval, policy, tool, provider, model_used, usage, reasoning_content.
	 */
	private static function build_turn_data( array $args ) {
		$turn_data = array(
			'timestamp' => current_time( 'c' ),
			'query'     => array(
				'original'  => $args['original_query'] ?? $args['query'],
				'rewritten' => $args['query'],
			),
			'response'  => $args['response'] ?? '',
			'sources'   => $args['sources'] ?? array(),
			'latency'   => $args['latency'] ?? array(),
		);

		if ( isset( $args['sources_details'] ) && is_array( $args['sources_details'] ) && ! empty( $args['sources_details'] ) ) {
			$turn_data['sources_details'] = $args['sources_details'];
		}

		// Persist citation source ordering so [Source N] markers can be
		// resolved correctly on conversation hydration/replay.
		if ( isset( $args['citation_sources'] ) && is_array( $args['citation_sources'] ) && ! empty( $args['citation_sources'] ) ) {
			$turn_data['citation_sources'] = $args['citation_sources'];
		}

		// Include tool selection if provided (RAG agentic flows).
		if ( ! empty( $args['tool'] ) ) {
			$turn_data['tool'] = $args['tool'];
		}

		// Include LLM usage metadata if provided (from generateTextWithMetadata).
		if ( isset( $args['usage'] ) && is_array( $args['usage'] ) && count( $args['usage'] ) > 0 ) {
			$turn_data['usage'] = $args['usage'];
		}

		// Include provider if identified.
		if ( ! empty( $args['provider'] ) ) {
			$turn_data['provider'] = $args['provider'];
		}

		// Include model used if provided.
		if ( ! empty( $args['model_used'] ) ) {
			$turn_data['model_used'] = $args['model_used'];
		}

		// Include reasoning content if available (from reasoning models like DeepSeek R1, Gemini thinking).
		if ( ! empty( $args['reasoning_content'] ) ) {
			$turn_data['reasoning_content'] = $args['reasoning_content'];
		}

		// Include complete raw API response for audit trail.
		if ( isset( $args['llm_response'] ) && is_array( $args['llm_response'] ) && ! empty( $args['llm_response'] ) ) {
			$turn_data['llm_response'] = $args['llm_response'];
		}

		// Include retrieval funnel details if available.
		if ( isset( $args['retrieval'] ) && is_array( $args['retrieval'] ) && ! empty( $args['retrieval'] ) ) {
			$turn_data['retrieval'] = $args['retrieval'];
		}

		// Include retrieval policy if available.
		if ( isset( $args['policy'] ) && is_array( $args['policy'] ) && ! empty( $args['policy'] ) ) {
			$turn_data['policy'] = $args['policy'];
		}

		// Include prompt metadata if available.
		if ( isset( $args['prompt'] ) && is_array( $args['prompt'] ) && ! empty( $args['prompt'] ) ) {
			$turn_data['prompt'] = $args['prompt'];
		}

		// Include security check result for per-turn audit trail.
		if ( isset( $args['security_check'] ) && is_array( $args['security_check'] ) && ! empty( $args['security_check'] ) ) {
			$turn_data['security_check'] = $args['security_check'];
		}

		// Include manifest and manifest metadata fields if provided.
		if ( isset( $args['manifest'] ) && is_array( $args['manifest'] ) && ! empty( $args['manifest'] ) ) {
			$turn_data['manifest'] = $args['manifest'];
		}

		if ( ! empty( $args['manifest_hash'] ) ) {
			$turn_data['manifest_hash'] = $args['manifest_hash'];
		}

		if ( isset( $args['manifest_size_bytes'] ) && $args['manifest_size_bytes'] > 0 ) {
			$turn_data['manifest_size_bytes'] = (int) $args['manifest_size_bytes'];
		}

		if ( ! empty( $args['manifest_version'] ) ) {
			$turn_data['manifest_version'] = $args['manifest_version'];
		}

		return $turn_data;
	}

	/**
	 * Format a turn for post_content.
	 *
	 * @since 1.0.0
	 * @param int   $turn_num Turn number.
	 * @param array $args     Turn arguments.
	 * @return string Formatted turn content.
	 */
	private static function format_turn( $turn_num, array $args ) {
		$content  = "## Turn {$turn_num}\n\n";
		$content .= '**User:** ' . ( $args['query'] ?? '' ) . "\n\n";
		$content .= '**Assistant:** ' . ( $args['response'] ?? '' ) . "\n\n";
		$content .= "**Sources:**\n";

		$sources_details = $args['sources_details'] ?? array();
		if ( ! empty( $sources_details ) ) {
			foreach ( $sources_details as $source ) {
				$source_title = $source['title'] ?? 'Untitled';
				$source_url   = $source['url'] ?? '';
				if ( ! empty( $source_url ) ) {
					$content .= "- [{$source_title}]({$source_url})\n";
				} else {
					$content .= "- {$source_title}\n";
				}
			}
		} else {
			$content .= "- No sources\n";
		}

		return $content;
	}
}

require_once __DIR__ . '/class-gg-data-interaction-functions.php';
