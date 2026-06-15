<?php
/**
 * Generic RAG Service
 *
 * Provides retrieval-augmented generation using any embedding model and LLM.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic RAG Service class.
 *
 * Handles retrieval and generation with configurable embedding and LLM models.
 *
 * @since 1.0.0
 */
class GG_Data_RAG_Service {

	/**
	 * Connection name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $connection_name;

	/**
	 * Embedding model key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $embedding_model_key;

	/**
	 * Search integration instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Search_Integration
	 */
	private $search_integration;

	/**
	 * Abilities manager instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Abilities_Manager
	 */
	private $abilities_manager;

	/**
	 * Settings manager instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Model registry instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Model_Registry
	 */
	private $model_registry;

	/**
	 * Token counter instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Token_Counter
	 */
	private $token_counter;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Model context limits (in tokens).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $model_context_limits = array(
		// OpenAI..
		'gpt-4o'                   => 128000,
		'gpt-4o-mini'              => 128000,
		// DeepSeek.
		'deepseek-chat'            => 64000,
		'deepseek-reasoner'        => 64000,
		// Anthropic Claude 4.5.
		'claude-opus-4-20250514'   => 200000,
		'claude-sonnet-4-20250514' => 200000,
		'claude-haiku-4-20250514'  => 200000,
		// Google Gemini.
		'gemini-2.5-flash'         => 1048576,
		'gemini-2.5-pro'           => 1048576,
		'gemini-2.0-flash-lite'    => 1048576,
	);

	/**
	 * Native max output tokens per model (provider hard ceilings).
	 * Used as the default when a model record has no max_tokens configured.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $model_max_tokens = array(
		// OpenAI.
		'gpt-3.5-turbo'              => 4096,
		'gpt-4'                      => 8192,
		'gpt-4-turbo'                => 4096,
		'gpt-4o'                     => 16384,
		'gpt-4o-mini'                => 16384,
		// DeepSeek.
		'deepseek-chat'              => 8192,
		'deepseek-reasoner'          => 8192,
		// Anthropic.
		'claude-opus-4-20250514'     => 8192,
		'claude-sonnet-4-20250514'   => 8192,
		'claude-haiku-4-20250514'    => 8192,
		'claude-3-5-sonnet-20241022' => 8192,
		'claude-3-5-haiku-20241022'  => 8192,
		'claude-3-opus-20240229'     => 4096,
		'claude-3-sonnet-20240229'   => 4096,
		'claude-3-haiku-20240307'    => 4096,
		// Google Gemini.
		'gemini-2.5-flash'           => 8192,
		'gemini-2.5-pro'             => 8192,
		'gemini-2.0-flash'           => 8192,
		'gemini-2.0-flash-lite'      => 8192,
		'gemini-1.5-pro'             => 8192,
		'gemini-1.5-flash'           => 8192,
		'gemini-1.5-flash-8b'        => 8192,
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $connection_name Connection name.
	 * @param string $embedding_model_key Embedding model key (e.g., 'tfidf-300', 'text-embedding-3-small').
	 */
	public function __construct( $connection_name, $embedding_model_key ) {
		$this->connection_name     = $connection_name;
		$this->embedding_model_key = $embedding_model_key;
		$this->search_integration  = new GG_Data_Search_Integration();
		$this->settings_manager    = new GG_Data_Settings_Manager();
		$this->model_registry      = new GG_Data_Model_Registry();
		$this->token_counter       = new GG_Data_Token_Counter();
		$this->logger              = new GG_Data_Logger();
		// Note: AI Client uses static methods, no instantiation needed.
	}

	// ========================================================================
	// EXTENSION CONTRACT v1 (Stable API for Custom Tool Handlers)
	// ========================================================================
	//
	// This section documents the v1 handler capability contract. Custom tool
	// implementations (registered via 'gg_data_rag_tools' filter and executed
	// via 'gg_data_rag_tool_{name}' filter) can invoke these methods to access
	// shared capabilities without coupling to foundation implementation details.
	//
	// CONTRACT GUARANTEES:
	// - These methods remain stable across foundation versions.
	// - Method signatures and return shapes are versioned (currently v1).
	// - Custom handlers must not invoke private methods outside this contract.
	// - All extension handlers execute in the same execution context (connection,
	// embedding model, LLM registry) as the foundation RAG service.
	//
	// RESPONSE SHAPE REQUIREMENTS:
	// All handlers (both foundation and custom) must return responses with:
	// {
	// "answer": string,             // Required: user-facing response text.
	// "sources": array,             // Required: array of source metadata objects.
	// "metadata": {                 // Required: tool execution metadata.
	// "tool": string,             // Tool name.
	// "tool_selected": string,    // How tool was selected (forced/agentic).
	// ...additional fields        // Tool-specific metadata.
	// }
	// }
	//
	// Available methods for custom handlers are documented below.
	// ========================================================================

	/**
	 * Stream LLM response - shared capability for all tool handlers.
	 *
	 * CONTRACT v1: Stable method for generating LLM responses with optional
	 * token-by-token streaming. Handlers call this to synthesize answers.
	 *
	 * @since 1.0.0
	 * @param string        $prompt             The prompt/query to send to the LLM.
	 * @param string        $llm_model_id       LLM model ID.
	 * @param string        $system_prompt      System message for the LLM.
	 * @param callable|null $progress_callback  Optional callback for SSE streaming.
	 * @param array         $options            Optional. Additional options (max_tokens, temperature).
	 * @return array|WP_Error {
	 *   @type string $text              Generated response text.
	 *   @type string $reasoning_content Optional reasoning from thinking models.
	 *   @type array  $usage             Token usage data.
	 *   @type string $provider          Provider ID (openai, anthropic, etc).
	 *   @type string $model             Model name used.
	 *   @type array  $raw_response      Complete API response.
	 *   @type int    $execution_time_ms Execution time in milliseconds.
	 * }
	 */
	public function stream_llm( $prompt, $llm_model_id, $system_prompt, $progress_callback = null, $options = array() ) {
		return $this->stream_llm_response( $prompt, $llm_model_id, $system_prompt, $progress_callback, $options );
	}

	/**
	 * Search and synthesize - shared capability for all tool handlers.
	 *
	 * CONTRACT v1: Stable method for retrieving relevant chunks and generating
	 * a synthesized answer. Handlers call this to implement search-based tools.
	 *
	 * @since 1.0.0
	 * @param string $query              User query.
	 * @param string $llm_model_id       LLM model ID.
	 * @param array  $options            RAG options (post_types, metadata_filter, etc).
	 * @param float  $start_time         microtime(true) at caller's start.
	 * @param bool   $fallback           Whether this is a fallback call. Default false.
	 * @return array|WP_Error Response with 'answer', 'sources', 'metadata' keys.
	 */
	public function search_synthesize( $query, $llm_model_id, $options, $start_time, $fallback = false ) {
		return $this->synthesize_search_content( $query, $llm_model_id, $options, $start_time, $fallback );
	}

	/**
	 * Append manifest metadata to result - shared capability for all handlers.
	 *
	 * CONTRACT v1: Stable method for normalizing response metadata. All handlers
	 * should call this before returning to ensure consistent manifest tracking.
	 *
	 * @since 1.0.0
	 * @param array $result              Tool result payload.
	 * @param array $options             RAG options containing manifest data.
	 * @return array Normalized result with manifest metadata appended.
	 */
	public function append_manifest_metadata( $result, $options ) {
		return $this->append_manifest_metadata_to_result( $result, $options );
	}

	/**
	 * Get available tools - shared capability for all handlers.
	 *
	 * CONTRACT v1: Stable method for discovering available tools. Handlers call this
	 * to validate tool availability (e.g., in forced-tool routing) or to list tools.
	 *
	 * @since 1.0.0
	 * @return array Array of tool definitions, each with 'name', 'description', 'parameters' keys.
	 */
	public function get_available_tools() {
		return $this->get_tool_definitions();
	}

	// ========================================================================
	// END CONTRACT v1 (Stable API)
	// ========================================================================

	/**
	 * Get synced post types from connection settings.
	 *
	 * @since 1.0.0
	 * @return array Array of enabled post types, defaults to ['post', 'page'].
	 */
	private function get_synced_post_types() {
		$post_types = $this->settings_manager->get_with_category( 'sync', $this->connection_name, 'sync_enabled_post_types' );

		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			return $post_types;
		}

		// Default fallback.
		return array( 'post', 'page' );
	}

	/**
	 * Get connection configuration for Supabase.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Connection config or error.
	 */
	private function get_connection_config() {
		$connections = $this->settings_manager->get_all_connections();

		if ( is_wp_error( $connections ) ) {
			return $connections;
		}

		if ( ! is_array( $connections ) ) {
			return new WP_Error(
				'gg_data_missing_connection',
				/* translators: %s: Connection name */
				sprintf( __( 'Connection "%s" not found', 'gregius-data' ), $this->connection_name )
			);
		}

		$config = $connections[ $this->connection_name ] ?? array();

		if ( empty( $config ) || ! is_array( $config ) ) {
			return new WP_Error(
				'gg_data_missing_connection',
				/* translators: %s: Connection name */
				sprintf( __( 'Connection "%s" not found', 'gregius-data' ), $this->connection_name )
			);
		}

		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once dirname( __DIR__, 1 ) . '/providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );

		if ( is_wp_error( $runtime_config ) ) {
			return $runtime_config;
		}

		if ( isset( $runtime_config['error'] ) ) {
			return new WP_Error(
				'gg_data_missing_connection',
				/* translators: %s: Connection name */
				sprintf( __( 'Connection "%s" missing required Supabase keys', 'gregius-data' ), $this->connection_name )
			);
		}

		return array_merge( $config, $runtime_config );
	}

	/**
	 * Get vector table configuration for the embedding model.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Vector config or error.
	 */
	private function get_vector_table_config() {
		$model = $this->model_registry->get_model( $this->connection_name, $this->embedding_model_key );

		if ( ! $model ) {
			// Try parent connection 'gregius-data' for shared models.
			$model = $this->model_registry->get_model( 'gregius-data', $this->embedding_model_key );
		}

		if ( ! $model ) {
			return new WP_Error(
				'gg_data_model_not_found',
				/* translators: %s: Embedding model key */
				sprintf( __( 'Embedding model "%s" not found', 'gregius-data' ), $this->embedding_model_key )
			);
		}

		if ( ! isset( $model['dimensions'] ) ) {
			return new WP_Error(
				'gg_data_not_embedding_model',
				/* translators: %s: Embedding model key */
				sprintf( __( 'Model "%s" is not an embedding model', 'gregius-data' ), $this->embedding_model_key )
			);
		}

		// Get table name from model config.
		$table_name = $model['vector_table_name'] ?? '';

		if ( empty( $table_name ) ) {
			return new WP_Error(
				'gg_data_missing_table_name',
				/* translators: %s: Embedding model key */
				sprintf( __( 'Embedding model "%s" has no vector_table_name configured', 'gregius-data' ), $this->embedding_model_key )
			);
		}

		// Row-per-embedding schema: single 'embedding' column with field_type discriminator.
		// No longer using separate post_content_X and post_title_X columns.
		return array(
			'table_name'       => $table_name,
			'embedding_column' => 'embedding',
			'model_key'        => $model['model_key'] ?? $this->embedding_model_key,
			'dimensions'       => (int) $model['dimensions'],
			'field_types'      => array( 'title', 'excerpt', 'chunk' ),
		);
	}

	/**
	 * Retrieve relevant content chunks for a query using Supabase vector search.
	 *
	 * Uses the new row-per-embedding schema with wp_posts_chunks for actual chunk text.
	 * Falls back to FTS search or WP_Query if semantic search fails.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @param array  $options Optional. Search options.
	 * @return array Array of content chunks with metadata.
	 */
	public function retrieve_chunks( $query, $options = array() ) {
		// Use connection's synced post types as default.
		$defaults = array(
			'num_results'     => 5,
			'max_tokens'      => 2000,
			'post_types'      => $this->get_synced_post_types(),
			'use_semantic'    => false,  // Use FTS+vector hybrid search by default.
			'disable_vector'  => false,
			'metadata_filter' => array(),
		);

		$options = wp_parse_args( $options, $defaults );

		// Route retrieval by backend type so RAG policy logic works for both
		// PostgREST/Supabase and direct PostgreSQL (PDO) connections.
		$connections   = $this->settings_manager->get_all_connections();
		$connection    = $connections[ $this->connection_name ] ?? array();
		$provider_type = $connection['type'] ?? 'postgresql';

		if ( ! in_array( $provider_type, array( 'postgrest', 'supabase' ), true ) ) {
			return $this->retrieve_chunks_pdo( $query, $options );
		}

		// Get connection config.
		$config = $this->get_connection_config();
		if ( is_wp_error( $config ) ) {
			// Fallback to WP_Query if connection not available.
			return $this->retrieve_chunks_fallback( $query, $options );
		}

		// Try semantic-only chunk retrieval if explicitly requested.
		// Note: Semantic-only search is stricter (pure vector similarity).
		// FTS+vector hybrid (default) is better for keyword queries like "403 error".
		if ( $options['use_semantic'] ) {
			$semantic_chunks = $this->retrieve_semantic_chunks( $query, $options );
			if ( ! empty( $semantic_chunks ) ) {
				/**
				 * Filter chunks after retrieval.
				 *
				 * @since 1.0.0
				 * @param array  $chunks  Retrieved chunks.
				 * @param string $query   User query.
				 * @param array  $options Search options.
				 */
				return apply_filters( 'gg_data_rag_chunks', $semantic_chunks, $query, $options );
			}

			// Log if semantic was tried but returned nothing.
			$this->logger->log(
				'RAG Service: Semantic search returned no results, falling back to FTS',
				'info',
				'rag',
				$this->connection_name
			);
		}

		// Use FTS+vector hybrid search (default path).
		// Get vector table config.
		$vector_config = $this->get_vector_table_config();
		if ( is_wp_error( $vector_config ) ) {
			// Fallback to WP_Query if vector config not available.
			return $this->retrieve_chunks_fallback( $query, $options );
		}

		$base_url = rtrim( $config['project_url'], '/' );

		// Get search settings from global settings.
		$language             = $this->settings_manager->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'language', 'english' );
		$similarity_threshold = $this->settings_manager->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', 0.5 );

		// Build RPC request payload.
		$rpc_url           = $base_url . '/rest/v1/rpc/search_rag_orchestrate';
		$payload           = array(
			'search_text'          => $query,
			'post_types'           => $options['post_types'],
			'limit_count'          => $options['num_results'],
			'search_language'      => $language,
			'enable_trigram'       => true,
			'similarity_threshold' => (float) $similarity_threshold,
			'enable_vector'        => ! (bool) $options['disable_vector'],
			'vector_table'         => $vector_config['table_name'],
			'vector_column'        => $vector_config['embedding_column'],
			'metadata_filter'      => ( is_array( $options['metadata_filter'] ) && ! empty( $options['metadata_filter'] ) ) ? $options['metadata_filter'] : new stdClass(),
		);

		// Execute Supabase RPC request.
		$args = array(
			'method'  => 'POST',
			'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $config['publishable_key'] ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		);

		$response = wp_remote_request( $rpc_url, $args );

		if ( is_wp_error( $response ) ) {
			// Fallback to WP_Query on network error.
			return $this->retrieve_chunks_fallback( $query, $options );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			// Fallback to WP_Query on API error.
			return $this->retrieve_chunks_fallback( $query, $options );
		}

		$results = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $results ) ) {
			return array();
		}

		// Format results as chunks.
		$chunks = array();
		foreach ( $results as $result ) {
			// Supabase returns 'post_id', not 'id'.
			// Column names may be prefixed (fts_post_id, tg_post_id, vec_post_id).
			$post_id = $result['post_id'] ?? $result['fts_post_id'] ?? $result['tg_post_id'] ?? $result['vec_post_id'] ?? $result['id'] ?? 0;
			$post    = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			// Score may be prefixed depending on match type (fts_, tg_, vec_).
			$score = $result['relevance_score']
				?? $result['fts_relevance_score']
				?? $result['tg_relevance_score']
				?? $result['vec_relevance_score']
				?? $result['combined_score']
				?? $result['score']
				?? 0.0;

			$chunks[] = array(
				'post_id'    => $post_id,
				'title'      => $post->post_title,
				'excerpt'    => $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 ),
				'content'    => $post->post_content,
				'url'        => get_permalink( $post_id ),
				'score'      => (float) $score,
				'match_type' => $result['fts_match_type'] ?? $result['tg_match_type'] ?? $result['vec_match_type'] ?? $result['match_type'] ?? 'unknown',
			);
		}

		/**
		 * Filter chunks after retrieval.
		 *
		 * @since 1.0.0
		 * @param array  $chunks  Retrieved chunks.
		 * @param string $query   User query.
		 * @param array  $options Search options.
		 */
		$chunks = apply_filters( 'gg_data_rag_chunks', $chunks, $query, $options );

		$this->logger->log(
			sprintf(
				'RAG Service: Retrieved %d chunks using %s',
				count( $chunks ),
				$this->embedding_model_key
			),
			'info',
			'rag',
			$this->connection_name,
			array(
				'chunk_count'     => count( $chunks ),
				'embedding_model' => $this->embedding_model_key,
				'connection'      => $this->connection_name,
				'post_types'      => $options['post_types'] ?? array(),
				'sql_function'    => $rag_rpc_fn,
				'fusion_signals'  => array_count_values( array_column( $chunks, 'match_type' ) ),
			)
		);

		return $chunks;
	}

		/**
		 * Retrieve relevant content chunks for a query using direct PostgreSQL (PDO).
		 *
		 * Uses the same search_rag_orchestrate function as the PostgREST path, executed
		 * through a PDO connection. This keeps compare/coverage governance behavior
		 * consistent across backend types.
		 *
		 * @since 1.0.0
		 * @param string $query   Search query.
		 * @param array  $options Search options.
		 * @return array Array of content chunks with metadata.
		 */
	private function retrieve_chunks_pdo( $query, $options ) {
		$db         = new GG_Data_DB();
		$connection = $db->get_connection( $this->connection_name );

		if ( ! $connection ) {
			return $this->retrieve_chunks_fallback( $query, $options );
		}

		$language             = $this->settings_manager->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'language', 'english' );
		$similarity_threshold = $this->settings_manager->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', 0.5 );

		// Build post types array literal for PostgreSQL function call.
		$post_types_safe  = array_map(
			function ( $type ) {
				return "'" . str_replace( "'", "''", $type ) . "'";
			},
			$options['post_types']
		);
		$post_types_array = 'ARRAY[' . implode( ',', $post_types_safe ) . ']::text[]';

		$vector_config = $this->get_vector_table_config();
		if ( is_wp_error( $vector_config ) ) {
			// Vector config unavailable: disable vector and continue with FTS/trigram only.
			$vector_config = array(
				'table_name'       => '',
				'embedding_column' => 'embedding',
			);
		}

		$disable_vector = ! empty( $options['disable_vector'] );
		$enable_vector  = false;
		try {
			$ext_check    = $connection->query( "SELECT COUNT(*) FROM pg_extension WHERE extname = 'vector'" );
			$has_pgvector = $ext_check && (int) $ext_check->fetchColumn() > 0;
			$vector_table = $vector_config['table_name'] ?? '';

			if ( ! $disable_vector && $has_pgvector && ! empty( $vector_table ) ) {
				$vector_check  = $connection->query( "SELECT COUNT(*) FROM {$vector_table} LIMIT 1" );
				$has_vectors   = $vector_check && (int) $vector_check->fetchColumn() > 0;
				$enable_vector = $has_vectors;
			}
		} catch ( Exception $e ) {
			$enable_vector = false;
		}

		$rag_pdo_fn        = 'search_rag_orchestrate';
		$sql               = "SELECT * FROM {$rag_pdo_fn}(:search_term::text, {$post_types_array}, :limit_count::int, :language::text, :enable_trigram::boolean, :similarity_threshold::real, :enable_vector::boolean, :vector_table::text, :vector_column::text, :metadata_filter::jsonb)";

		try {
			$stmt = $connection->prepare( $sql );
			$stmt->execute(
				array(
					':search_term'          => $query,
					':limit_count'          => (int) $options['num_results'],
					':language'             => $language,
					':enable_trigram'       => 'true',
					':similarity_threshold' => (float) $similarity_threshold,
					':enable_vector'        => $enable_vector ? 'true' : 'false',
					':vector_table'         => $vector_config['table_name'] ?? '',
					':vector_column'        => $vector_config['embedding_column'] ?? 'embedding',
					':metadata_filter'      => wp_json_encode( ( is_array( $options['metadata_filter'] ) && ! empty( $options['metadata_filter'] ) ) ? $options['metadata_filter'] : new stdClass() ),
				)
			);

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections.
			$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		} catch ( Exception $e ) {
			return $this->retrieve_chunks_fallback( $query, $options );
		}

		if ( empty( $results ) ) {
			return array();
		}

		$chunks = array();
		foreach ( $results as $result ) {
			$post_id = $result['post_id'] ?? $result['fts_post_id'] ?? $result['tg_post_id'] ?? $result['vec_post_id'] ?? $result['id'] ?? 0;
			$post    = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$score = $result['relevance_score']
				?? $result['fts_relevance_score']
				?? $result['tg_relevance_score']
				?? $result['vec_relevance_score']
				?? $result['combined_score']
				?? $result['score']
				?? 0.0;

			$chunks[] = array(
				'post_id'    => (int) $post_id,
				'title'      => $post->post_title,
				'excerpt'    => $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 ),
				'content'    => $post->post_content,
				'url'        => get_permalink( $post_id ),
				'score'      => (float) $score,
				'match_type' => $result['fts_match_type'] ?? $result['tg_match_type'] ?? $result['vec_match_type'] ?? $result['match_type'] ?? 'unknown',
			);
		}

		$chunks = apply_filters( 'gg_data_rag_chunks', $chunks, $query, $options );

		$this->logger->log(
			sprintf(
				'RAG Service (PDO): Retrieved %d chunks using %s',
				count( $chunks ),
				$this->embedding_model_key
			),
			'info',
			'rag',
			$this->connection_name,
			array(
				'chunk_count'     => count( $chunks ),
				'embedding_model' => $this->embedding_model_key,
				'connection'      => $this->connection_name,
				'post_types'      => $options['post_types'] ?? array(),
				'sql_function'    => $rag_pdo_fn,
				'fusion_signals'  => array_count_values( array_column( $chunks, 'match_type' ) ),
			)
		);

		return $chunks;
	}

	/**
	 * Fallback to WordPress native search when Supabase is unavailable.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @param array  $options Search options.
	 * @return array Array of content chunks.
	 */
	private function retrieve_chunks_fallback( $query, $options ) {
		$args = array(
			's'              => $query,
			'posts_per_page' => $options['num_results'],
			'post_type'      => $options['post_types'],
			'post_status'    => 'publish',
			'orderby'        => 'relevance',
		);

		$search_query = new WP_Query( $args );
		$posts        = $search_query->posts;

		if ( empty( $posts ) ) {
			return array();
		}

		$chunks = array();
		foreach ( $posts as $post ) {
			$chunks[] = array(
				'post_id'    => $post->ID,
				'title'      => $post->post_title,
				'excerpt'    => ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 ),
				'content'    => $post->post_content,
				'url'        => get_permalink( $post->ID ),
				'score'      => 1.0,
				'match_type' => 'fallback',
			);
		}

		return $chunks;
	}

	/**
	 * Retrieve semantic chunks using the new row-per-embedding schema.
	 *
	 * Uses search_rag_get_context() RPC function to get actual chunk text
	 * from wp_posts_chunks table, respecting token limits.
	 *
	 * @since 2.1.0
	 * @param string $query   Search query.
	 * @param array  $options Search options.
	 * @return array Array of chunks with text, metadata and scores.
	 */
	private function retrieve_semantic_chunks( $query, $options = array() ) {
		$defaults = array(
			'num_results' => 5,
			'max_tokens'  => 2000,
			'post_types'  => $this->get_synced_post_types(),
		);
		$options  = wp_parse_args( $options, $defaults );

		// Get connection config.
		$config = $this->get_connection_config();
		if ( is_wp_error( $config ) ) {
			return array();
		}

		$base_url = rtrim( $config['project_url'], '/' );

		$vector_config = $this->get_vector_table_config();
		if ( is_wp_error( $vector_config ) ) {
			$this->logger->log(
				'RAG Service: Semantic search unavailable because embedding vector storage is not configured: ' . $vector_config->get_error_message(),
				'warning',
				'rag',
				$this->connection_name,
				array(
					'embedding_model' => $this->embedding_model_key,
					'error_code'      => $vector_config->get_error_code(),
				)
			);
			return array();
		}

		// First, generate query vector using gg_generate_search_vector.
		$vector_rpc_url = $base_url . '/rest/v1/rpc/gg_generate_search_vector';
		$language       = $this->settings_manager->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'language', 'english' );

		$vector_payload = array(
			'search_text'        => $query,
			'search_language'    => $language,
			'vector_table_name'  => $vector_config['table_name'],
			'vector_column_name' => $vector_config['embedding_column'],
		);

		$vector_args = array(
			'method'  => 'POST',
			'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $config['publishable_key'] ),
			'body'    => wp_json_encode( $vector_payload ),
			'timeout' => 30,
		);

		$vector_response = wp_remote_request( $vector_rpc_url, $vector_args );

		if ( is_wp_error( $vector_response ) ) {
			$this->logger->log(
				'RAG Service: Failed to generate query vector: ' . $vector_response->get_error_message(),
				'error',
				'rag',
				$this->connection_name
			);
			return array();
		}

		$vector_body   = wp_remote_retrieve_body( $vector_response );
		$vector_result = json_decode( $vector_body, true );

		if ( empty( $vector_result ) || ! isset( $vector_result[0]['vector'] ) ) {
			$this->logger->log(
				'RAG Service: No vector returned from query vectorization',
				'warning',
				'rag',
				$this->connection_name
			);
			return array();
		}

		$query_vector = $vector_result[0]['vector'];

		// Retrieve chunks through the model-resolved vector store.
		$rag_rpc_url = $base_url . '/rest/v1/rpc/search_rag_get_context';
		$rag_payload = array(
			'query_vector'      => $query_vector,
			'match_count'       => $options['num_results'],
			'max_tokens'        => $options['max_tokens'],
			'vector_table_name' => $vector_config['table_name'],
			'post_types'        => $options['post_types'],
		);

		$rag_args = array(
			'method'  => 'POST',
			'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $config['publishable_key'] ),
			'body'    => wp_json_encode( $rag_payload ),
			'timeout' => 30,
		);

		$rag_response = wp_remote_request( $rag_rpc_url, $rag_args );

		if ( is_wp_error( $rag_response ) ) {
			$this->logger->log(
				'RAG Service: Failed to retrieve RAG context: ' . $rag_response->get_error_message(),
				'error',
				'rag',
				$this->connection_name
			);
			return array();
		}

		$rag_body   = wp_remote_retrieve_body( $rag_response );
		$rag_result = json_decode( $rag_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $rag_result ) ) {
			return array();
		}

		// Format results as chunks with actual chunk text.
		$chunks = array();
		foreach ( $rag_result as $row ) {
			$post_id = $row['post_id'] ?? 0;
			$post    = get_post( $post_id );

			$chunks[] = array(
				'post_id'     => $post_id,
				'chunk_index' => $row['chunk_index'] ?? 0,
				'title'       => $row['post_title'] ?? ( $post ? $post->post_title : '' ),
				'content'     => $row['chunk_text'] ?? '',  // Actual chunk text, not full post!
				'excerpt'     => wp_trim_words( $row['chunk_text'] ?? '', 30 ),
				'url'         => $post ? get_permalink( $post_id ) : '',
				'score'       => (float) ( $row['similarity'] ?? 0.0 ),
				'match_type'  => 'semantic_chunk',
				'tokens'      => $row['running_tokens'] ?? 0,
			);
		}

		$this->logger->log(
			sprintf(
				'RAG Service: Retrieved %d semantic chunks using %s (max %d tokens)',
				count( $chunks ),
				$this->embedding_model_key,
				$options['max_tokens']
			),
			'info',
			'rag',
			$this->connection_name,
			array(
				'chunk_count'     => count( $chunks ),
				'embedding_model' => $this->embedding_model_key,
			)
		);

		return $chunks;
	}

	/**
	 * Rerank retrieved chunks using a rerank model.
	 *
	 * Uses a rerank model (Voyage AI or Cohere) to re-score and reorder
	 * chunks based on their relevance to the query.
	 *
	 * @since 1.0.0
	 * @param array  $chunks          Array of content chunks to rerank.
	 * @param string $query           The search query.
	 * @param string $rerank_model_id The rerank model ID from the model registry.
	 * @return array|WP_Error Reranked chunks or error.
	 */
	private function rerank_chunks( $chunks, $query, $rerank_model_id ) {
		if ( empty( $chunks ) || empty( $rerank_model_id ) ) {
			return $chunks;
		}

		// Ensure chunks have sequential numeric keys for proper index matching with reranker results.
		$chunks = array_values( $chunks );

		// Get rerank model configuration from registry.
		// Rerank models are stored globally (not connection-specific), so use 'gregius-data' as connection.
		$model_registry = new GG_Data_Model_Registry();
		$model_config   = $model_registry->get_model( 'gregius-data', $rerank_model_id );

		if ( ! $model_config ) {
			$this->logger->log(
				sprintf( 'RAG Service: Rerank model not found: %s', $rerank_model_id ),
				'warning',
				'rag',
				$this->connection_name,
				array( 'model_id' => $rerank_model_id )
			);
			return $chunks;
		}

		$provider_id       = $model_config['provider'] ?? '';
		$provider_model_id = $model_config['provider_model_id'] ?? '';

		// API key can be stored in different locations depending on model registration.
		$api_key = $model_config['config']['api_key']
			?? $model_config['api_key']
			?? '';

		if ( empty( $provider_id ) || empty( $provider_model_id ) ) {
			$this->logger->log(
				'RAG Service: Invalid rerank model configuration',
				'warning',
				'rag',
				$this->connection_name,
				array( 'model_id' => $rerank_model_id )
			);
			return $chunks;
		}

		// Get provider instance.
		$provider = $this->get_provider_instance( $provider_id, $api_key );
		if ( ! $provider ) {
			$this->logger->log(
				sprintf( 'RAG Service: Could not instantiate provider: %s', $provider_id ),
				'error',
				'rag',
				$this->connection_name,
				array( 'provider_id' => $provider_id )
			);
			return $chunks;
		}

		// Ensure provider supports reranking.
		if ( ! method_exists( $provider, 'rerank' ) ) {
			$this->logger->log(
				sprintf( 'RAG Service: Provider %s does not support reranking', $provider_id ),
				'warning',
				'rag',
				null,
				array( 'provider_id' => $provider_id )
			);
			return $chunks;
		}

		// Extract document content from chunks for reranking.
		// Use content with title for better context.
		$documents = array();
		foreach ( $chunks as $chunk ) {
			$doc_text = '';
			if ( ! empty( $chunk['title'] ) ) {
				$doc_text .= $chunk['title'] . "\n\n";
			}
			$content     = isset( $chunk['content'] ) ? $chunk['content'] : '';
			$excerpt     = isset( $chunk['excerpt'] ) ? $chunk['excerpt'] : '';
			$doc_text   .= ! empty( $content ) ? $content : $excerpt;
			$documents[] = $doc_text;
		}

		// Call provider's rerank method.
		$rerank_options = array(
			'model'   => $provider_model_id,
			'api_key' => $api_key,
			'top_n'   => count( $chunks ), // Return all, we'll use original count.
		);

		$result = $provider->rerank( $query, $documents, $rerank_options );

		if ( is_wp_error( $result ) ) {
			$this->logger->log(
				sprintf( 'RAG Service: Reranking failed: %s', $result->get_error_message() ),
				'error',
				'rag',
				$this->connection_name,
				array(
					'provider_id' => $provider_id,
					'error_code'  => $result->get_error_code(),
				)
			);
			return $chunks;
		}

		if ( empty( $result['results'] ) ) {
			return $chunks;
		}

		// Reorder chunks based on relevance scores.
		$reranked_chunks = array();
		foreach ( $result['results'] as $rerank_result ) {
			$index = $rerank_result['index'];
			if ( isset( $chunks[ $index ] ) ) {
				$chunk                   = $chunks[ $index ];
				$chunk['rerank_score']   = $rerank_result['relevance_score'];
				$chunk['original_score'] = $chunk['score'];
				$chunk['score']          = $rerank_result['relevance_score'];
				$reranked_chunks[]       = $chunk;
			}
		}

		$this->logger->log(
			sprintf(
				'RAG Service: Reranked %d chunks using %s/%s',
				count( $reranked_chunks ),
				$provider_id,
				$provider_model_id
			),
			'info',
			'rag',
			$this->connection_name,
			array(
				'chunk_count'       => count( $reranked_chunks ),
				'provider_id'       => $provider_id,
				'provider_model_id' => $provider_model_id,
			)
		);

		return $reranked_chunks;
	}

	/**
	 * Get a provider instance by ID.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID (e.g., 'voyage', 'cohere').
	 * @param string $api_key     API key for the provider.
	 * @return GG_Data_AI_Provider_Interface|null Provider instance or null.
	 */
	private function get_provider_instance( $provider_id, $api_key = '' ) {
		$provider_map = array(
			'openai'    => 'GG_Data_OpenAI_Provider',
			'voyage'    => 'GG_Data_Voyage_Provider',
			'cohere'    => 'GG_Data_Cohere_Provider',
			'anthropic' => 'GG_Data_Anthropic_Provider',
			'gemini'    => 'GG_Data_Gemini_Provider',
			'deepseek'  => 'GG_Data_DeepSeek_Provider',
			'internal'  => 'GG_Data_Internal_Provider',
		);

		if ( ! isset( $provider_map[ $provider_id ] ) ) {
			return null;
		}

		$class_name = $provider_map[ $provider_id ];
		if ( ! class_exists( $class_name ) ) {
			return null;
		}

		return new $class_name( $api_key );
	}

	/**
	 * Emit a progress event via callback.
	 *
	 * Used by SSE handler to stream real-time progress to the frontend.
	 *
	 * @since 1.0.0
	 * @param callable|null $callback Progress callback function.
	 * @param string        $stage    Progress stage identifier.
	 * @param mixed         $data     Optional data for the stage.
	 */
	private function emit_progress( $callback, $stage, $data = null ) {
		if ( is_callable( $callback ) ) {
			call_user_func( $callback, $stage, $data );
		}
	}

	/**
	 * Generate an answer using retrieval-augmented generation.
	 *
	 * @since 1.0.0
	 * @param string $query User query.
	 * @param string $llm_model_id LLM model ID to use for generation.
	 * @param array  $options Optional. RAG options including progress_callback for SSE streaming.
	 * @return array|WP_Error Answer data or error.
	 */
	public function generate_answer( $query, $llm_model_id, $options = array() ) {
		$start_time     = microtime( true );
		$tool_selection = array(
			'tool' => 'search_content',
		);

		// Use connection's synced post types as default.
		$defaults = array(
			'num_chunks'        => 5,
			'post_types'        => $this->get_synced_post_types(),
			'temperature'       => 0.7,
			'rewrite_model'     => '',
			'rerank_model_id'   => '',
			'metadata_filter'   => array(),
			'manifest'          => array(),
			'messages'          => array(),
			'forced_tool'       => '',
			'source_turn_index' => 0,
			'progress_callback' => null,
		);

		$options                      = wp_parse_args( $options, $defaults );
		$options['source_turn_index'] = absint( $options['source_turn_index'] ?? 0 );

		// Extract progress callback for SSE streaming.
		$progress_callback = $options['progress_callback'];

		/**
		 * Fires at the start of a RAG request.
		 *
		 * @since 1.0.0
		 * @param string $query   User query.
		 * @param array  $options RAG options.
		 * @param int    $user_id Current user ID.
		 */
		do_action( 'gg_data_rag_request', $query, $options, get_current_user_id() );

		// Emit: Analyzing question.
		$this->emit_progress( $progress_callback, 'analyzing', __( 'Analyzing your question...', 'gregius-data' ) );

		// Step 0a: Security gatekeeper — runs before tool selection and any LLM call.
		// Sends user query + security prompt to the LLM; blocks the entire pipeline if unsafe.
		$security_check = $this->run_security_gatekeeper_check( $query, $llm_model_id, $options );

		if ( is_wp_error( $security_check ) ) {
			do_action( 'gg_data_rag_error', $security_check, $query, array( 'step' => 'security_check' ) );
			return $security_check;
		}

		if ( 'unsafe' === ( $security_check['status'] ?? 'safe' ) ) {
			$blocked_result = $this->build_security_blocked_response( $query, $llm_model_id, $query, $tool_selection, $security_check, $options, $start_time );
			$blocked_result = $this->append_manifest_metadata_to_result( $blocked_result, $options );
			$execution_time = ( microtime( true ) - $start_time ) * 1000;

			do_action( 'gg_data_rag_complete', $blocked_result, $query, $execution_time );

			do_action(
				'gg_data_rag_tool_executed',
				'search_content',
				$blocked_result,
				array(
					'query'           => $query,
					'llm_model_id'    => $llm_model_id,
					'options'         => $options,
					'tool_selection'  => $tool_selection,
					'trigger'         => 'security_block',
					'connection_name' => $this->connection_name,
				)
			);

			return $blocked_result;
		}

		// Store security_check result in options so all tool handlers and metadata builders can access it.
		$options['security_check'] = $security_check;

		// Normalize and validate the manifest payload against the canonical schema contract.
		// This ensures all downstream handlers receive a predictable, sanitized structure
		// regardless of which client plugin generated the manifest.
		$options['manifest'] = GG_Data_Manifest_Validator::normalize(
			isset( $options['manifest'] ) ? $options['manifest'] : array()
		);

		$forced_tool = ! empty( $options['forced_tool'] ) ? sanitize_key( (string) $options['forced_tool'] ) : '';

		// Phase B: Validate forced_tool against available tools (generic validation).
		// Any tool provided by 'gg_data_rag_tools' filter is valid; removes hardcoded whitelist.
		if ( ! empty( $forced_tool ) ) {
			$available_tools   = $this->get_available_tools();
			$is_tool_available = false;

			foreach ( $available_tools as $tool ) {
				if ( $tool['name'] === $forced_tool ) {
					$is_tool_available = true;
					break;
				}
			}

			// If forced tool exists, route through generic handler.
			if ( $is_tool_available ) {
				$tool_selection = array(
					'tool'         => $forced_tool,
					'search_query' => $query,
					'reason'       => 'forced_by_client',
				);

				$tool_context = array(
					'query'           => $query,
					'llm_model_id'    => $llm_model_id,
					'options'         => $options,
					'tool_selection'  => $tool_selection,
					'trigger'         => 'forced',
					'connection_name' => $this->connection_name,
				);

				// Generic tool handler dispatch via filter (no hardcoded if/else).
				// Phase B ensures this path handles all tools, not just foundation ones.
				$result = apply_filters(
					"gg_data_rag_tool_{$forced_tool}",
					null,  // Default: no result (foundation tool not found in filters).
					$forced_tool,
					$tool_context
				);

				// If no handler was found (null result from filter), return error.
				if ( null === $result ) {
					$result = new WP_Error(
						'tool_not_found',
						/* translators: %s: Tool name */
						sprintf( __( 'Tool handler for "%s" not found', 'gregius-data' ), $forced_tool )
					);
				}

				if ( ! is_wp_error( $result ) ) {
					$result = $this->append_manifest_metadata_to_result( $result, $options );
				}

				do_action( 'gg_data_rag_tool_executed', $forced_tool, $result, $tool_context );

				if ( ! is_wp_error( $result ) && ! empty( $options['conversation_id'] ) ) {
					$execution_time = ( microtime( true ) - $start_time ) * 1000;
					do_action( 'gg_data_rag_complete', $result, $query, $execution_time );
				}

				return $result;
			}
		}

		// Step 0b: Tool selection - determine how to handle this query.
		// This is "Agentic RAG" - the LLM decides whether to search, summarize conversation, or respond directly.
		if ( ! empty( $options['rewrite_model'] ) ) {
			$tool_selection = $this->select_tool( $query, $options['messages'], $options['rewrite_model'] );

			if ( ! is_wp_error( $tool_selection ) ) {
				// Get agentic model info for logging.
				$agentic_model_id   = $options['rewrite_model'];
				$agentic_model_info = ( new GG_Data_Model_Registry() )->get_model( 'gregius-data', $agentic_model_id );
				$agentic_provider   = $agentic_model_info['provider'] ?? 'unknown';
				$agentic_model_name = $agentic_model_info['config']['provider_model_id'] ?? $agentic_model_id;

				// Build log context.
				$log_context = array(
					'provider_id'       => $agentic_provider,
					'provider_model_id' => $agentic_model_name,
					'tool_selected'     => $tool_selection['tool'] ?? 'unknown',
					'has_history'       => ! empty( $options['messages'] ),
				);

				// Add tool-specific context.
				if ( ! empty( $tool_selection['reason'] ) ) {
					$log_context['reason'] = $tool_selection['reason'];
				}
				if ( ! empty( $tool_selection['clarification_type'] ) ) {
					$log_context['clarification_type'] = $tool_selection['clarification_type'];
				}

				// For search_content, determine the search query and check if rewritten.
				$tool_name = $tool_selection['tool'] ?? 'search_content';
				if ( 'respond_directly' === $tool_name && ! $this->should_allow_direct_response( $query ) ) {
					$tool_name                      = 'search_content';
					$tool_selection['tool']         = 'search_content';
					$tool_selection['search_query'] = $query;
					$tool_selection['reason']       = 'guardrail_forced_search_content';
				}
				// Guardrail 2: redirect under-specified queries to respond_directly so the
				// LLM can request clarification instead of running retrieval on vague terms.
				if ( 'search_content' === $tool_name && $this->is_under_specified_query( $query ) ) {
					$tool_name                = 'respond_directly';
					$tool_selection['tool']   = 'respond_directly';
					$tool_selection['reason'] = 'ambiguity_gate';
					unset( $tool_selection['search_query'] );
				}
				if ( 'search_content' === $tool_name || ! in_array( $tool_name, array( 'summarize_conversation', 'respond_directly', 'clarify_previous' ), true ) ) {
					if ( ! empty( $tool_selection['search_query'] ) ) {
						$search_query                = $tool_selection['search_query'];
						$log_context['search_query'] = $search_query;

						// Log if query was rewritten.
						if ( $search_query !== $query ) {
							$log_context['query_rewritten'] = true;
							$log_context['original_query']  = $query;
						}
					} else {
						$search_query                = $query;
						$log_context['search_query'] = $query;
					}

					if ( isset( $tool_selection['metadata_filter'] ) && is_array( $tool_selection['metadata_filter'] ) ) {
						$options['metadata_filter'] = $tool_selection['metadata_filter'];
					}
				}

				$this->logger->log(
					sprintf(
						'RAG Service: Agentic model selected tool "%s" using %s/%s',
						$tool_name,
						$agentic_provider,
						$agentic_model_name
					),
					'info',
					'rag',
					$this->connection_name,
					$log_context
				);

				// Build context for tool handlers.
				$tool_context = array(
					'query'           => $query,
					'llm_model_id'    => $llm_model_id,
					'options'         => $options,
					'tool_selection'  => $tool_selection,
					'trigger'         => 'llm',
					'connection_name' => $this->connection_name,
					'log_context'     => $log_context,
				);

				// Route to appropriate handler based on selected tool.
				switch ( $tool_name ) {
					case 'summarize_conversation':
						$this->emit_progress( $progress_callback, 'tool_selected', __( 'Summarizing conversation...', 'gregius-data' ) );
						$result = $this->handle_summarize_conversation( $query, $llm_model_id, $options );
						if ( ! is_wp_error( $result ) ) {
							$result = $this->append_manifest_metadata_to_result( $result, $options );
						}

						/**
						 * Fires after a RAG tool executes.
						 *
						 * @since 1.0.0
						 * @param string $tool_name    The tool that was executed.
						 * @param array  $result       The tool's result (or WP_Error).
						 * @param array  $tool_context Context including query, options, trigger source.
						 */
						do_action( 'gg_data_rag_tool_executed', $tool_name, $result, $tool_context );

						// Fire gg_data_rag_complete hook for interaction tracking.
						if ( ! is_wp_error( $result ) && ! empty( $options['conversation_id'] ) ) {
							$execution_time = ( microtime( true ) - $start_time ) * 1000;
							/**
							 * Fires after RAG completes successfully.
							 *
							 * @since 1.0.0
							 * @param array  $result         Response data.
							 * @param string $query          User query.
							 * @param int    $execution_time Execution time in milliseconds.
							 */
							do_action( 'gg_data_rag_complete', $result, $query, $execution_time );
						}

						return $result;

					case 'respond_directly':
						$this->emit_progress( $progress_callback, 'tool_selected', __( 'Responding...', 'gregius-data' ) );
						$result = $this->handle_respond_directly( $query, $llm_model_id, $options, $tool_selection['reason'] ?? '' );
						if ( ! is_wp_error( $result ) ) {
							$result = $this->append_manifest_metadata_to_result( $result, $options );
						}

						/** This action is documented above. */
						do_action( 'gg_data_rag_tool_executed', $tool_name, $result, $tool_context );

						// Fire gg_data_rag_complete hook for interaction tracking.
						if ( ! is_wp_error( $result ) && ! empty( $options['conversation_id'] ) ) {
							$execution_time = ( microtime( true ) - $start_time ) * 1000;
							/** This action is documented above. */
							do_action( 'gg_data_rag_complete', $result, $query, $execution_time );
						}

						return $result;

					case 'clarify_previous':
						$this->emit_progress( $progress_callback, 'tool_selected', __( 'Clarifying previous response...', 'gregius-data' ) );
						$result = $this->handle_clarify_previous( $query, $llm_model_id, $options, $tool_selection['clarification_type'] ?? 'rephrase' );
						if ( ! is_wp_error( $result ) ) {
							$result = $this->append_manifest_metadata_to_result( $result, $options );
						}

						/** This action is documented above. */
						do_action( 'gg_data_rag_tool_executed', $tool_name, $result, $tool_context );

						// Fire gg_data_rag_complete hook for interaction tracking.
						if ( ! is_wp_error( $result ) && ! empty( $options['conversation_id'] ) ) {
							$execution_time = ( microtime( true ) - $start_time ) * 1000;
							/** This action is documented above. */
							do_action( 'gg_data_rag_complete', $result, $query, $execution_time );
						}

						return $result;

					case 'compare_content':
						$this->emit_progress( $progress_callback, 'tool_selected', __( 'Comparing content...', 'gregius-data' ) );
						$result = $this->handle_compare_content( $query, $llm_model_id, $options, $tool_selection );
						if ( ! is_wp_error( $result ) ) {
							$result = $this->append_manifest_metadata_to_result( $result, $options );
						}

						/** This action is documented above. */
						do_action( 'gg_data_rag_tool_executed', $tool_name, $result, $tool_context );

						// Fire gg_data_rag_complete hook for interaction tracking.
						if ( ! is_wp_error( $result ) && ! empty( $options['conversation_id'] ) ) {
							$execution_time = ( microtime( true ) - $start_time ) * 1000;
							/** This action is documented above. */
							do_action( 'gg_data_rag_complete', $result, $query, $execution_time );
						}

						return $result;

					case 'search_content':
						// Continue with normal RAG flow below.
						break;

					default:
						// Phase D: Check for custom/premium tool handler via generic filter.
						// No hardcoded cases for premium tools; all route through this path.
						/**
						 * Filter to handle custom RAG tool execution.
						 *
						 * Return an array with 'answer', 'sources', and 'metadata' to handle the tool.
						 * Return null to fall back to search_content behavior.
						 *
						 * @since 1.0.0
						 * @param array|null $result       Return tool result array or null to skip.
						 * @param string     $tool_name    The selected tool name.
						 * @param array      $tool_context Context including query, options, tool_selection, trigger.
						 */
						$custom_result = apply_filters( "gg_data_rag_tool_{$tool_name}", null, $tool_name, $tool_context );

						if ( null !== $custom_result ) {
							$progress_message = sprintf(
								/* translators: %s: tool name */
								__( 'Running %s...', 'gregius-data' ),
								$tool_name
							);
							$this->emit_progress( $progress_callback, 'tool_selected', $progress_message );

							/** This action is documented above. */
							if ( ! is_wp_error( $custom_result ) ) {
								$custom_result = $this->append_manifest_metadata_to_result( $custom_result, $options );
							}
							do_action( 'gg_data_rag_tool_executed', $tool_name, $custom_result, $tool_context );

							return $custom_result;
						}

						// Unknown tool with no handler - fall through to search_content.
						$this->logger->log(
							sprintf( 'RAG Service: Unknown tool "%s" with no handler, falling back to search_content', $tool_name ),
							'warning',
							'rag',
							$this->connection_name
						);
						break;
				}
			}
		}

		// If no tool selection occurred (no agentic model), use original query.
		if ( ! isset( $search_query ) ) {
			$search_query = $query;
		}

		// Normalize WordPress-specific query patterns that hurt FTS matching.
		// e.g. "core columns block" → "columns block" to avoid matching theme
		// changelogs that reference the core/ block namespace heavily.
		$search_query = $this->normalize_retrieval_query( $search_query );

		/**
		 * Filter the search query before retrieval.
		 *
		 * Allows developers to expand or modify the search query for better matching.
		 * Useful for synonym expansion, location-to-contact mapping, etc.
		 *
		 * @since 1.0.0
		 * @param string $search_query The query to search with (possibly rewritten).
		 * @param string $query        The original user query.
		 * @param array  $options      RAG options including messages and rewrite_model.
		 */
		$search_query = apply_filters( 'gg_data_rag_search_query', $search_query, $query, $options );

		/**
		 * Filter retrieval criteria before candidate search.
		 *
		 * Allows developers to add constraints (e.g., date ranges, document classes, taxonomy filters)
		 * that are applied during the retrieval planning phase.
		 *
		 * @since 1.0.0
		 * @param array  $filter_criteria Retrieval criteria array (empty array by default).
		 * @param string $query           Original user query.
		 * @param array  $options         RAG options including messages, models, connection name.
		 * @return array Modified retrieval criteria.
		 */
		$retrieval_filters = apply_filters( 'gg_data_rag_pre_retrieval_filter', array(), $query, $options );

		// Merge retrieval filters into options for downstream retrieval functions.
		if ( is_array( $retrieval_filters ) && ! empty( $retrieval_filters ) ) {
			$options['retrieval_filters'] = $retrieval_filters;
		}

		$query_plan       = $this->build_lightweight_query_plan( $query, $search_query, $tool_selection, $options );
		$retrieval_policy = $this->resolve_hybrid_retrieval_policy( $query_plan['intent'] ?? 'single', $options, $search_query );

		$this->logger->log(
			sprintf(
				'RAG Service: Query planner %s (%d subqueries)',
				! empty( $query_plan['active'] ) ? 'active' : 'skipped',
				count( $query_plan['subqueries'] ?? array() )
			),
			'debug',
			'rag',
			$this->connection_name,
			array(
				'planner' => $query_plan,
			)
		);

		// Emit: Searching knowledge base.
		$this->emit_progress( $progress_callback, 'searching', __( 'Searching knowledge base...', 'gregius-data' ) );

		// Step 2: Retrieve candidate posts using hybrid FTS/vector search.
		// Always retrieve 6 post candidates for passage-level expansion.
		$planned_retrieval = $this->retrieve_post_candidates_from_plan( $query_plan, $options, $retrieval_policy );
		$post_candidates   = $planned_retrieval['candidates'];

		if ( empty( $post_candidates ) ) {
			$error = new WP_Error(
				'no_chunks_found',
				__( 'No relevant content found for your question.', 'gregius-data' )
			);

			/**
			 * Fires when RAG encounters an error.
			 *
			 * @since 1.0.0
			 * @param WP_Error $error   Error object.
			 * @param string   $query   User query.
			 * @param array    $context Additional context.
			 */
			do_action( 'gg_data_rag_error', $error, $query, array( 'step' => 'retrieval' ) );

			return $error;
		}

		// Emit: Found sources.
		$this->emit_progress(
			$progress_callback,
			'found',
			array(
				'count'   => count( $post_candidates ),
				/* translators: %d: number of sources found */
				'message' => sprintf( __( 'Found %d relevant sources', 'gregius-data' ), count( $post_candidates ) ),
			)
		);

		// Step 2.5: Passage retrieval — expand post candidates to chunk-level context.
		// Fetches actual chunk text from wp_posts_chunks and selects the best passages
		// for context assembly. Falls back to full post content when unavailable.
		$connection_config = $this->get_connection_config();
		$chunks_by_post    = array();

		if ( ! is_wp_error( $connection_config ) ) {
			$post_ids       = array_unique( array_column( $post_candidates, 'post_id' ) );
			$chunks_by_post = $this->fetch_chunks_for_posts( $post_ids, $connection_config );
		}

		// Retrieval funnel stats — populated during passage retrieval; included in interaction metadata.
		$retrieval_stats = array(
			'mode'                               => 'hybrid_post_to_chunk',
			'posts_retrieved'                    => count( $post_candidates ),
			'chunk_candidates_total'             => 0,
			'chunk_candidates_prefiltered'       => 0,
			'chunks_final'                       => 0,
			'rerank_enabled'                     => ! empty( $options['rerank_model_id'] ),
			'neighbor_expansion_used'            => false,
			'planner_active'                     => ! empty( $query_plan['active'] ),
			'planner_source'                     => $query_plan['planner_source'] ?? 'none',
			'planner_intent'                     => $query_plan['intent'] ?? 'single',
			'planner_subqueries'                 => $query_plan['subqueries'] ?? array(),
			'planner_subqueries_total'           => count( $query_plan['subqueries'] ?? array() ),
			'planner_subqueries_executed'        => (int) ( $planned_retrieval['metrics']['subqueries_executed'] ?? 0 ),
			'planner_candidate_counts'           => $planned_retrieval['metrics']['candidate_counts'] ?? array(),
			'planner_unique_candidates_total'    => (int) ( $planned_retrieval['metrics']['unique_candidates_total'] ?? 0 ),
			'planner_duplicate_candidates_total' => (int) ( $planned_retrieval['metrics']['duplicate_candidates_total'] ?? 0 ),
			'retrieval_policy'                   => $retrieval_policy,
			'mode_contribution'                  => $this->build_retrieval_mode_contribution( $post_candidates ),
		);

		if ( ! empty( $chunks_by_post ) ) {
			// Build normalized chunk candidates from chunk rows + post scores.
			$chunk_candidates                          = $this->build_chunk_candidates( $post_candidates, $chunks_by_post );
			$retrieval_stats['chunk_candidates_total'] = count( $chunk_candidates );

			// Prefilter: cap to 2 chunks per post, 12 total before scoring.
			$chunk_candidates                                = $this->prefilter_chunk_candidates( $chunk_candidates, 2, 12, $search_query );
			$retrieval_stats['chunk_candidates_prefiltered'] = count( $chunk_candidates );
			$rerank_policy                                   = $this->resolve_formal_rerank_policy( 'single', count( $chunk_candidates ), $options, $query );
			$rerank_applied                                  = false;

			// Score chunk candidates: rerank when configured, heuristic otherwise.
			if ( ! empty( $rerank_policy['should_run'] ) ) {
				$this->emit_progress( $progress_callback, 'reranking', __( 'Reranking results...', 'gregius-data' ) );
				$reranked = $this->rerank_chunks( $chunk_candidates, $search_query, $options['rerank_model_id'] );
				if ( ! is_wp_error( $reranked ) ) {
					$chunk_candidates = $reranked;
					$rerank_applied   = true;
				} else {
					// Reranking failed — fall back to heuristic scoring.
					$chunk_candidates = $this->heuristic_score_chunks( $chunk_candidates, $search_query );
				}
			} else {
				$chunk_candidates = $this->heuristic_score_chunks( $chunk_candidates, $search_query );
			}

			$ranked_single_candidates = $chunk_candidates;
			$acceptance               = $this->apply_candidate_acceptance_policy( $ranked_single_candidates, 'single', $rerank_policy, $rerank_applied );
			$chunk_candidates         = $acceptance['candidates'];
			if ( empty( $chunk_candidates ) && ! empty( $acceptance['metrics']['input_count'] ) ) {
				$recovered_chunks = $this->recover_single_block_lookup_candidates( $ranked_single_candidates, $search_query );
				if ( ! empty( $recovered_chunks ) ) {
					$chunk_candidates                         = $recovered_chunks;
					$acceptance['metrics']['accepted_count']  = count( $chunk_candidates );
					$acceptance['metrics']['acceptance_mode'] = 'post_rerank_block_lookup_recovery';
				}
			}
			$retrieval_stats['rerank_policy']  = $rerank_policy;
			$retrieval_stats['rerank_applied'] = $rerank_applied;
			$retrieval_stats['acceptance']     = $acceptance['metrics'];

			// Finalize: enforce diversity (max 2 per post) and keep top 6.
			$chunks = $this->finalize_context_chunks( $chunk_candidates );

			// Selective neighbor expansion for top 2 incomplete passages.
			$chunks_before_expansion = count( $chunks );
			$chunks                  = $this->expand_neighbor_chunks_if_needed( $chunks, $chunks_by_post );

			$retrieval_stats['chunks_final']            = count( $chunks );
			$retrieval_stats['neighbor_expansion_used'] = count( $chunks ) > $chunks_before_expansion;

			$this->logger->log(
				sprintf(
					'RAG Service: Passage retrieval selected %d chunks from %d post candidates',
					count( $chunks ),
					count( $post_candidates )
				),
				'info',
				'rag',
				$this->connection_name,
				$retrieval_stats
			);
		} else {
			// Fallback: chunk table not populated or connection unavailable.
			// Use post-level retrieval with full post content (existing behavior).
			$chunks                          = $post_candidates;
			$retrieval_stats['mode']         = 'document_level';
			$retrieval_stats['chunks_final'] = count( $chunks );

			$this->logger->log(
				'RAG Service: No chunk data available; using document-level context assembly',
				'info',
				'rag',
				$this->connection_name
			);

			// Apply reranking to post-level chunks as before.
			$rerank_policy  = $this->resolve_formal_rerank_policy( 'single', count( $chunks ), $options, $query );
			$rerank_applied = false;
			if ( ! empty( $rerank_policy['should_run'] ) ) {
				$this->emit_progress( $progress_callback, 'reranking', __( 'Reranking results...', 'gregius-data' ) );
				$reranked = $this->rerank_chunks( $chunks, $search_query, $options['rerank_model_id'] );
				if ( ! is_wp_error( $reranked ) ) {
					$chunks         = $reranked;
					$rerank_applied = true;
				}
				// If reranking fails, continue with original chunks (graceful degradation).
			}

			$ranked_single_candidates = $chunks;
			$acceptance               = $this->apply_candidate_acceptance_policy( $ranked_single_candidates, 'single', $rerank_policy, $rerank_applied );
			$chunks                   = $acceptance['candidates'];
			if ( empty( $chunks ) && ! empty( $acceptance['metrics']['input_count'] ) ) {
				$recovered_chunks = $this->recover_single_block_lookup_candidates( $ranked_single_candidates, $search_query );
				if ( ! empty( $recovered_chunks ) ) {
					$chunks                                   = $recovered_chunks;
					$acceptance['metrics']['accepted_count']  = count( $chunks );
					$acceptance['metrics']['acceptance_mode'] = 'post_rerank_block_lookup_recovery';
				}
			}
			$retrieval_stats['rerank_policy']  = $rerank_policy;
			$retrieval_stats['rerank_applied'] = $rerank_applied;
			$retrieval_stats['acceptance']     = $acceptance['metrics'];

			$retrieval_stats['chunks_final'] = count( $chunks );
		}

		if ( empty( $chunks ) ) {
			$single_profile         = $this->get_policy_threshold_profile( 'single', $query, $options );
			$single_raw_count       = (int) ( $retrieval_stats['chunk_candidates_total'] ?? count( $post_candidates ) );
			$single_qualified_count = 0;
			$single_selected_count  = 0;
			$single_reason_code     = ! empty( $retrieval_stats['rerank_applied'] ) ? 'below_threshold_post_rerank' : 'no_relevant_context';

			$retrieval_metadata = $this->build_retrieval_parity(
				$single_raw_count,
				$single_qualified_count,
				$single_selected_count,
				$retrieval_stats
			);

			$policy_metadata = $this->build_policy_parity(
				'single',
				'abstain',
				$single_reason_code,
				$single_profile['id'],
				array(
					'agentic_model_active'   => ! empty( $options['rewrite_model'] ),
					'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
					'lexical_active'         => ! empty( $retrieval_policy['lexical_active'] ),
					'retrieval_query_class'  => $retrieval_policy['query_class'] ?? 'general',
					'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
					'retrieval_mode_source'  => $retrieval_policy['mode_source'] ?? 'query_class_default',
					'retrieval_mode'         => $retrieval_policy['mode'] ?? 'hybrid',
					'retrieval_policy_id'    => $retrieval_policy['id'] ?? 'retrieval_single_hybrid',
					'rerank_model_active'    => ! empty( $options['rerank_model_id'] ),
					'thresholds'             => $single_profile,
					'candidate_post_limit'   => 6,
					'prefilter_per_post'     => 2,
					'final_chunk_limit'      => 6,
					'neighbor_expand_top_n'  => 2,
				)
			);

			$response_text = $this->build_abstain_message(
				__( 'I could not find relevant information in the website content to answer that question.', 'gregius-data' ),
				$query,
				$policy_metadata,
				$retrieval_metadata,
				$options
			);

			$metadata = $this->ensure_policy_retrieval_contract(
				array(
					'chunks_used'       => 0,
					'embedding_model'   => $this->embedding_model_key,
					'llm_model'         => $llm_model_id,
					'connection'        => $this->connection_name,
					'execution_time'    => round( ( microtime( true ) - $start_time ) * 1000 ),
					'reasoning_content' => '',
					'usage'             => array(),
					'provider'          => '',
					'model_used'        => '',
					'raw_response'      => array(),
					'conversation_id'   => $options['conversation_id'] ?? null,
					'source'            => $options['source'] ?? array( 'type' => 'rest' ),
					'original_query'    => $query,
					'search_query'      => $search_query ?? $query,
					'post_types'        => $options['post_types'] ?? array(),
					'rewrite_model'     => $options['rewrite_model'] ?? null,
					'rerank_model'      => $options['rerank_model_id'] ?? null,
					'citation_sources'  => array(),
					'tool_selected'     => 'search_content',
					'prompt'            => array(),
					'security_check'    => $security_check,
					'query_plan'        => $query_plan,
					'retrieval'         => $retrieval_metadata,
					'policy'            => $policy_metadata,
				),
				'single'
			);

			$result = array(
				'answer'   => $response_text,
				'sources'  => array(),
				'metadata' => $metadata,
			);

			$result = apply_filters( 'gg_data_rag_response', $result, $query );
			$result = $this->append_manifest_metadata_to_result( $result, $options );
			if ( is_array( $result ) ) {
				$result_metadata    = isset( $result['metadata'] ) && is_array( $result['metadata'] ) ? $result['metadata'] : array();
				$result['metadata'] = $this->ensure_policy_retrieval_contract( $result_metadata, 'single' );
				$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );
			}

			$execution_time = ( microtime( true ) - $start_time ) * 1000;
			do_action( 'gg_data_rag_complete', $result, $query, $execution_time );

			$search_tool_context = array(
				'query'           => $query,
				'llm_model_id'    => $llm_model_id,
				'options'         => $options,
				'tool_selection'  => array(
					'tool'         => 'search_content',
					'search_query' => $search_query ?? $query,
				),
				'trigger'         => isset( $options['rewrite_model'] ) && ! empty( $options['rewrite_model'] ) ? 'llm' : 'direct',
				'connection_name' => $this->connection_name,
			);

			do_action( 'gg_data_rag_tool_executed', 'search_content', $result, $search_tool_context );

			return $result;
		}

		// Step 3: Get model configuration first (needed for token limits).
		// The llm_model_id from the block is a model key (e.g., "gpt-4o-mini").
		$model_registry = new GG_Data_Model_Registry();

		// Debug: Log the model ID being looked up.
		$this->logger->log(
			sprintf( 'RAG Service: Looking up model with llm_model_id: "%s"', $llm_model_id ),
			'debug',
			'rag',
			$this->connection_name
		);

		// LLM models are stored globally (not connection-specific), so use 'gregius-data' as connection.
		$model_data = $model_registry->get_model( 'gregius-data', $llm_model_id );

		// Debug: Log raw model data.
		$this->logger->log(
			sprintf( 'RAG Service: Model data retrieved: %s', wp_json_encode( $model_data ) ),
			'debug',
			'rag',
			$this->connection_name
		);

		if ( ! $model_data || is_wp_error( $model_data ) ) {
			$error = new WP_Error(
				'invalid_model',
				// translators: %s is the model ID.
				sprintf( __( 'Model "%s" not found.', 'gregius-data' ), $llm_model_id )
			);

			do_action( 'gg_data_rag_error', $error, $query, array( 'step' => 'model_lookup' ) );

			return $error;
		}

		// Extract model configuration.
		// The Model Registry already decodes JSON, so config is an array.
		$model_config = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$model_name   = $model_config['provider_model_id'] ?? $model_data['provider_model_id'] ?? $llm_model_id;

		// Get provider from model config (defaults to 'openai' for backward compatibility).
		$provider_id = $model_data['provider'] ?? $model_config['provider'] ?? 'openai';

		// Debug: Log extracted provider info.
		$this->logger->log(
			sprintf(
				'RAG Service: Provider resolution - model_data[provider]=%s, model_config[provider]=%s, resolved=%s, model_name=%s',
				$model_data['provider'] ?? 'null',
				$model_config['provider'] ?? 'null',
				$provider_id,
				$model_name
			),
			'debug',
			'rag',
			$this->connection_name
		);

		// Get max_tokens from model config (user-configured output limit).
		// Get max_tokens from model config (user-configured output limit).
		// Fall back to the model's native ceiling rather than an arbitrary 500.
		$native_max = $this->model_max_tokens[ $model_name ] ?? 8192;
		$max_tokens = (int) ( $model_data['max_tokens'] ?? $model_config['max_tokens'] ?? $native_max );

		// Get context_window from model config.
		// Default to model's full limit if not configured.
		$model_hard_limit = $this->model_context_limits[ $model_name ] ?? 16385;
		$default_context  = $model_hard_limit;
		$context_window   = (int) ( $model_data['context_window'] ?? $model_config['context_window'] ?? $default_context );

		// Hard cap based on known model limits to prevent API errors.
		if ( $context_window > $model_hard_limit ) {
			$context_window = $model_hard_limit;
		}

		// Step 3: Build context from chunks with token limiting.
		// Pass max_tokens and context_window for proper token budgeting.
		$context = $this->build_context( $chunks, $model_name, $max_tokens, $context_window );

		/**
		 * Filter the built context before sending to LLM.
		 *
		 * @since 1.0.0
		 * @param string $context Built context string.
		 * @param array  $chunks  Selected chunks.
		 * @param string $query   User query.
		 */
		$context = apply_filters( 'gg_data_rag_context', $context, $chunks, $query );

		// Step 4: Build prompt.
		$prompt = $this->build_prompt( $query, $context );

		// Emit: Generating answer.
		$this->emit_progress( $progress_callback, 'generating', __( 'Generating answer...', 'gregius-data' ) );

		// Step 5: Resolve the active system prompt from stored gg_prompt posts.
		$current_date      = wp_date( 'F j, Y' );
		$current_time      = wp_date( 'g:i A T' );
		$prompt_resolution = $this->resolve_system_prompt( $options );

		if ( is_wp_error( $prompt_resolution ) ) {
			return $prompt_resolution;
		}

		$system_prompt = $prompt_resolution['content'];

		/**
		 * Filter the RAG system prompt.
		 *
		 * @since 1.0.0
		 * @param string $system_prompt Default system prompt.
		 * @param array  $chunks        Selected content chunks.
		 * @param string $query         User's question.
		 * @param string $current_date  Current date.
		 * @param string $current_time  Current time.
		 */
		$system_prompt = apply_filters(
			'gg_data_rag_system_prompt',
			$system_prompt,
			$chunks,
			$query,
			$current_date,
			$current_time
		);

		// Enforce inline citations in all answer generations, including legacy prompts.
		$system_prompt .= "\n\nIMPORTANT: Include inline citations using [Source N] markers that match the provided context labels.";

		// Call AI Client with proper model name, connection, and max_tokens.
		// LLM models are stored under 'gregius-data' connection where the API key is configured.
		// Provider is determined from the model config (openai, deepseek, etc.).
		// Note: We intentionally do NOT pass conversation history to the answer LLM.
		// Conversation history is used for query rewriting (pronoun resolution) only.
		// The RAG context from retrieved documents should be the sole source for answers.
		$ai_request = GG_Data_Ai_Client::prompt( $prompt )
			->setSystemMessage( $system_prompt )
			->usingProvider( $provider_id ) // Provider from model config (openai, deepseek, etc.).
			->usingModel( $model_name ) // Actual model name (e.g., "gpt-4o-mini", "deepseek-chat").
			->usingConnection( 'gregius-data' ) // Connection name where API key is stored.
			->withMaxTokens( $max_tokens ); // User-configured output token limit.

		// Check if streaming is available and progress callback is set.
		$use_streaming = $progress_callback && $ai_request->supportsStreaming();

		$this->logger->log(
			sprintf(
				'RAG Service: Streaming check - provider: %s, supports_streaming: %s, has_callback: %s',
				$provider_id,
				$ai_request->supportsStreaming() ? 'true' : 'false',
				$progress_callback ? 'true' : 'false'
			),
			'debug',
			'rag',
			$this->connection_name
		);

		if ( $use_streaming ) {
			// Stream the response token by token.
			$this->emit_progress( $progress_callback, 'streaming', __( 'Generating answer...', 'gregius-data' ) );

			$stream_callback = function ( $chunk ) use ( $progress_callback ) {
				// Forward each chunk via progress callback.
				if ( ! empty( $chunk['content'] ) ) {
					$this->emit_progress( $progress_callback, 'token', array( 'content' => $chunk['content'] ) );
				}
				if ( ! empty( $chunk['reasoning_content'] ) ) {
					$this->emit_progress( $progress_callback, 'reasoning_token', array( 'content' => $chunk['reasoning_content'] ) );
				}
			};

			$response = $ai_request->generateTextStream( $stream_callback );

			// Extract text, reasoning, and usage from streaming response.
			$response_text      = is_array( $response ) ? ( $response['text'] ?? '' ) : $response;
			$reasoning_content  = is_array( $response ) ? ( $response['reasoning_content'] ?? '' ) : '';
			$llm_usage          = is_array( $response ) ? ( $response['usage'] ?? array() ) : array();
			$llm_model_returned = is_array( $response ) ? ( $response['model'] ?? '' ) : '';
			$llm_provider       = is_array( $response ) ? ( $response['provider'] ?? '' ) : '';
		} else {
			// Non-streaming: emit generating progress and wait for full response.
			$this->emit_progress( $progress_callback, 'generating', __( 'Generating answer...', 'gregius-data' ) );

			// Generate text with metadata (for thinking models like DeepSeek R1).
			$response = $ai_request->generateTextWithMetadata();

			// Extract text, reasoning, and usage from response.
			$response_text      = is_array( $response ) ? ( $response['text'] ?? '' ) : $response;
			$reasoning_content  = is_array( $response ) ? ( $response['reasoning_content'] ?? '' ) : '';
			$llm_usage          = is_array( $response ) ? ( $response['usage'] ?? array() ) : array();
			$llm_model_returned = is_array( $response ) ? ( $response['model'] ?? '' ) : '';
			$llm_provider       = is_array( $response ) ? ( $response['provider'] ?? '' ) : '';
		}

		if ( is_wp_error( $response_text ) ) {
			do_action( 'gg_data_rag_error', $response_text, $query, array( 'step' => 'ai_call' ) );
			return $response_text;
		}

		/**
		 * Filter raw LLM response text before citation processing.
		 *
		 * Allows developers to post-process answer text (e.g., rephrasing, safety checks,
		 * format transforms, redaction) before citation markers are normalized.
		 *
		 * @since 1.0.0
		 * @param string $response_text      Raw LLM response text.
		 * @param array  $raw_response       Full LLM response object/array.
		 * @param string $model              Model name used for generation.
		 * @param string $query              Original user query.
		 * @return string Filtered response text.
		 */
		$response_text = apply_filters( 'gg_data_rag_llm_response', $response_text, $response, $model_name, $query );

		$this->logger->log(
			sprintf(
				'RAG Service: Generated answer using %s/%s',
				$provider_id,
				$model_name
			),
			'info',
			'rag',
			$this->connection_name,
			array(
				'provider_id'       => $provider_id,
				'provider_model_id' => $model_name,
				'max_tokens'        => $max_tokens,
				'chunks_used'       => count( $chunks ),
			)
		);

		// Step 6: Format response.
		$execution_time = ( microtime( true ) - $start_time ) * 1000;
		$sources        = $this->format_sources( $chunks );

		/**
		 * Filter the sources list.
		 *
		 * @since 1.0.0
		 * @param array $sources Sources extracted from chunks.
		 * @param array $chunks  Selected chunks.
		 */
		$sources          = apply_filters( 'gg_data_rag_sources', $sources, $chunks );
		$citation_sources = $this->build_citation_sources( $chunks );
		$response_text    = $this->sanitize_inline_citation_markers( (string) $response_text, $citation_sources );

		$single_profile         = $this->get_policy_threshold_profile( 'single', $query, $options );
		$single_raw_count       = (int) ( $retrieval_stats['chunk_candidates_total'] ?? count( $post_candidates ) );
		$single_qualified_count = $this->count_qualified_chunks( $chunks, $single_profile['min_relevance'] );
		$single_selected_count  = count( $chunks );
		$single_decision        = $single_selected_count > 0 ? 'full' : 'abstain';
		$single_reason_code     = $single_qualified_count > 0 ? 'full_primary_coverage' : 'below_threshold_post_rerank';

		$metadata = array(
			// Existing fields.
			'chunks_used'       => count( $chunks ),
			'embedding_model'   => $this->embedding_model_key,
			'llm_model'         => $llm_model_id,
			'connection'        => $this->connection_name,
			'execution_time'    => round( $execution_time ),
			'reasoning_content' => $reasoning_content, // For thinking models like DeepSeek R1.

			// LLM response metadata (for turn recording).
			'usage'             => $llm_usage, // Token usage from LLM response.
			'provider'          => $llm_provider, // Provider name from LLM response.
			'model_used'        => $llm_model_returned, // Model name from LLM response.
			'raw_response'      => is_array( $response ) ? ( $response['raw_response'] ?? array() ) : array(), // Complete API response.

			// New fields for interaction tracking.
			'conversation_id'   => $options['conversation_id'] ?? null,
			'source'            => $options['source'] ?? array( 'type' => 'rest' ),
			'original_query'    => $query,
			'search_query'      => $search_query ?? $query,
			'post_types'        => $options['post_types'] ?? array(),
			'metadata_filter'   => $options['metadata_filter'] ?? array(),
			'rewrite_model'     => $options['rewrite_model'] ?? null,
			'rerank_model'      => $options['rerank_model_id'] ?? null,
			'citation_sources'  => $citation_sources,
			'tool_selected'     => 'search_content', // Tool used for this flow.
			'prompt'            => $prompt_resolution['metadata'] ?? array(),
			'security_check'    => $security_check,
			'query_plan'        => $query_plan,

			// Retrieval funnel for eval/observability.
			'retrieval'         => $this->build_retrieval_parity(
				$single_raw_count,
				$single_qualified_count,
				$single_selected_count,
				$retrieval_stats
			),
			'policy'            => $this->build_policy_parity(
				'single',
				$single_decision,
				$single_reason_code,
				$single_profile['id'],
				array(
					'agentic_model_active'   => ! empty( $options['rewrite_model'] ),
					'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
					'lexical_active'         => ! empty( $retrieval_policy['lexical_active'] ),
					'retrieval_query_class'  => $retrieval_policy['query_class'] ?? 'general',
					'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
					'retrieval_mode_source'  => $retrieval_policy['mode_source'] ?? 'query_class_default',
					'retrieval_mode'         => $retrieval_policy['mode'] ?? 'hybrid',
					'retrieval_policy_id'    => $retrieval_policy['id'] ?? 'retrieval_single_hybrid',
					'rerank_model_active'    => ! empty( $options['rerank_model_id'] ),
					'thresholds'             => $single_profile,
					'candidate_post_limit'   => 6,
					'prefilter_per_post'     => 2,
					'final_chunk_limit'      => 6,
					'neighbor_expand_top_n'  => 2,
				)
			),
		);

		$metadata = $this->ensure_policy_retrieval_contract( $metadata, 'single' );

		$result = array(
			'answer'   => $response_text,
			'sources'  => $sources,
			'chunks'   => $chunks,
			'metadata' => $metadata,
		);

		/**
		 * Filter the RAG response before returning.
		 *
		 * @since 1.0.0
		 * @param array  $result Response data.
		 * @param string $query  User query.
		 */
		$result = apply_filters( 'gg_data_rag_response', $result, $query );
		$result = $this->append_manifest_metadata_to_result( $result, $options );

		// Re-guard metadata contract in case external filters modify parity fields.
		if ( is_array( $result ) ) {
			$result_metadata    = isset( $result['metadata'] ) && is_array( $result['metadata'] )
				? $result['metadata']
				: array();
			$result['metadata'] = $this->ensure_policy_retrieval_contract( $result_metadata, 'single' );
			$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );
		}

		/**
		 * Fires after RAG completes successfully.
		 *
		 * @since 1.0.0
		 * @param array  $result         Response data.
		 * @param string $query          User query.
		 * @param int    $execution_time Execution time in milliseconds.
		 */
		do_action( 'gg_data_rag_complete', $result, $query, $execution_time );

		// Fire tool executed action for search_content (for consistency with other tools).
		$search_tool_context = array(
			'query'           => $query,
			'llm_model_id'    => $llm_model_id,
			'options'         => $options,
			'tool_selection'  => array(
				'tool'         => 'search_content',
				'search_query' => $search_query ?? $query,
			),
			'trigger'         => isset( $options['rewrite_model'] ) && ! empty( $options['rewrite_model'] ) ? 'llm' : 'direct',
			'connection_name' => $this->connection_name,
		);

		/** This action is documented in generate_answer() switch statement. */
		do_action( 'gg_data_rag_tool_executed', 'search_content', $result, $search_tool_context );

		return $result;
	}

	/**
	 * Build context string from content chunks with token limiting.
	 *
	 * @since 1.0.0
	 * @param array  $chunks            Content chunks.
	 * @param string $llm_model         LLM model for context limit fallback. Default 'gpt-4o-mini'.
	 * @param int    $max_output_tokens Maximum tokens reserved for output. Default 500.
	 * @param int    $context_window    User-configured context window. Default 0 (auto from model).
	 * @return string Context string.
	 */
	private function build_context( $chunks, $llm_model = 'gpt-4o-mini', $max_output_tokens = 500, $context_window = 0 ) {
		// Get model's hard limit.
		$model_limit = $this->model_context_limits[ $llm_model ] ?? 16385;

		// Use provided context_window or default to model limit.
		if ( $context_window <= 0 ) {
			$context_window = $model_limit;
		}

		// Cap at model's hard limit.
		$context_window = min( $context_window, $model_limit );

		// Calculate available input tokens.
		// Reserve: max_output_tokens (for LLM response) + 1500 (safety margin for system prompt, user query, formatting).
		// Using 1500 instead of 500 to account for token estimation inaccuracy.
		$reserved_tokens = $max_output_tokens + 1500;
		$max_context     = $context_window - $reserved_tokens;

		// Ensure we have positive context budget.
		if ( $max_context < 1000 ) {
			$max_context = 1000;
		}

		// Maximum tokens per chunk to ensure we include multiple sources.
		$max_tokens_per_chunk = min( 2000, (int) ( $max_context / max( 1, count( $chunks ) ) ) );

		$context_parts = array();
		$total_tokens  = 0;

		foreach ( $chunks as $i => $chunk ) {
			// Use truncated content to fit within limits.
			$content = $this->truncate_to_tokens( $chunk['content'], $max_tokens_per_chunk );

			$chunk_text = sprintf(
				"[Source %d: %s]\n%s",
				$i + 1,
				$chunk['title'],
				$content
			);

			$chunk_tokens = $this->token_counter->estimate_tokens( $chunk_text );

			// Stop if adding this chunk would exceed context limit.
			if ( $total_tokens + $chunk_tokens > $max_context ) {
				break;
			}

			$context_parts[] = $chunk_text;
			$total_tokens   += $chunk_tokens;
		}

		return implode( "\n\n", $context_parts );
	}

	/**
	 * Truncate text to fit within token limit.
	 *
	 * @since 1.0.0
	 * @param string $text       Text to truncate.
	 * @param int    $max_tokens Maximum tokens allowed.
	 * @return string Truncated text.
	 */
	private function truncate_to_tokens( $text, $max_tokens ) {
		// Strip HTML tags for cleaner content.
		$text = wp_strip_all_tags( $text );

		$estimated_tokens = $this->token_counter->estimate_tokens( $text );

		if ( $estimated_tokens <= $max_tokens ) {
			return $text;
		}

		// Approximate characters for target tokens (~4 chars per token).
		$target_chars = $max_tokens * 4;

		// Truncate with ellipsis.
		return mb_substr( $text, 0, $target_chars ) . '...';
	}

	/**
	 * Build RAG prompt.
	 *
	 * @since 1.0.0
	 * @param string $query User query.
	 * @param string $context Context from chunks.
	 * @return string Prompt.
	 */
	private function build_prompt( $query, $context ) {
		return sprintf(
			"Answer the user's question based on the provided context. If the context doesn't contain relevant information, say so. Include inline citations using [Source N] for factual claims, where N matches the context labels. If one sentence relies on multiple sources, cite at most two markers.\n\nContext:\n%s\n\nQuestion: %s\n\nAnswer:",
			$context,
			$query
		);
	}

	/**
	 * Remove invalid inline citation markers that reference missing sources.
	 *
	 * The model may occasionally emit markers such as [Source 5] when only
	 * three sources are present. This guard preserves in-range markers and strips
	 * out-of-range markers so UI citations remain consistent with the source list.
	 *
	 * @since 1.0.0
	 * @param string $text    Generated answer text.
	 * @param array  $sources Source list attached to the response.
	 * @return string Cleaned answer text.
	 */
	private function sanitize_inline_citation_markers( $text, $sources ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}

		$max_sources = is_array( $sources ) ? count( $sources ) : 0;

		$text = preg_replace_callback(
			'/\[Source\s+(\d+)\]/i',
			function ( $matches ) use ( $max_sources ) {
				$index = isset( $matches[1] ) ? (int) $matches[1] : 0;

				if ( $index < 1 || $index > $max_sources ) {
					return '';
				}

				return '[Source ' . $index . ']';
			},
			$text
		);

		// Trim spacing artifacts left behind by removed markers.
		$text = preg_replace( '/\s+([,.;:!?])/', '$1', $text );
		// Collapse runs of horizontal whitespace only (spaces/tabs) — preserve newlines
		// so markdown paragraph/heading breaks survive the sanitization round-trip.
		$text = preg_replace( '/[^\S\r\n]{2,}/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Format chunks as source references.
	 *
	 * Filters out sources below the minimum relevance threshold to avoid
	 * showing irrelevant sources when the LLM correctly identifies that
	 * context doesn't help answer the question.
	 *
	 * @since 1.0.0
	 * @param array $chunks Content chunks.
	 * @return array Source references.
	 */
	private function format_sources( $chunks ) {
		$sources = array();

		/**
		 * Filter the minimum relevance score threshold for sources.
		 *
		 * Sources with a relevance score below this threshold will not be
		 * included in the response. This prevents showing irrelevant sources
		 * when retrieved chunks don't actually help answer the question.
		 *
		 * The score comes from the reranker (if enabled) or vector similarity.
		 * Reranker scores (Cohere/Voyage) range from 0.0 to 1.0.
		 *
		 * @since 1.0.0
		 * @param float $threshold Minimum relevance score (0.0 to 1.0). Default 0.5.
		 * @param array $chunks    The chunks being filtered.
		 */
		$min_relevance = apply_filters( 'gg_data_rag_source_min_relevance', 0.5, $chunks );

		// Deduplicate by post_id, keeping the highest-scoring entry per post.
		// Passage retrieval may produce multiple chunks from the same post; sources
		// should still be shown at the post level.
		$seen_posts = array();

		foreach ( $chunks as $chunk ) {
			// Skip sources below relevance threshold.
			$score = isset( $chunk['score'] ) ? (float) $chunk['score'] : 0;
			if ( $score < $min_relevance ) {
				continue;
			}

			$pid = (int) $chunk['post_id'];
			if ( ! isset( $seen_posts[ $pid ] ) || $score > $seen_posts[ $pid ]['score'] ) {
				$seen_posts[ $pid ] = array(
					'post_id' => $pid,
					'title'   => $chunk['title'],
					'url'     => $chunk['url'],
					'score'   => $score,
				);
			}
		}

		return array_values( $seen_posts );
	}

	/**
	 * Build citation source map aligned with [Source N] context labels.
	 *
	 * @since 1.0.0
	 * @param array $chunks Context chunks in label order.
	 * @return array Citation source map.
	 */
	private function build_citation_sources( $chunks ) {
		$citation_sources = array();

		foreach ( $chunks as $chunk ) {
			$citation_sources[] = array(
				'post_id' => isset( $chunk['post_id'] ) ? (int) $chunk['post_id'] : 0,
				'title'   => $chunk['title'] ?? '',
				'url'     => $chunk['url'] ?? '',
			);
		}

		return $citation_sources;
	}

	/**
	 * Select the appropriate tool to handle the user's query.
	 *
	 * This implements "Agentic RAG" - the LLM decides how to handle each query:
	 * - search_content: Search website content (standard RAG)
	 * - summarize_conversation: Summarize or reference the current chat
	 * - respond_directly: Answer without searching (greetings, time, can't help)
	 * - clarify_previous: Clarify, simplify, or expand previous response
	 *
	 * Uses the Tool Strategy Pattern to support native tool calling when available
	 * (OpenAI, Anthropic, Gemini, DeepSeek) with prompt-based fallback for others.
	 *
	 * @since 1.0.0
	 * @param string $query    User's query.
	 * @param array  $messages Conversation history.
	 * @param string $model_id LLM model ID for tool selection.
	 * @return array|WP_Error Tool selection result or error.
	 */
	private function select_tool( $query, $messages, $model_id ) {
		// Get tool calling strategy for this model.
		$strategy = GG_Data_Tool_Strategy_Factory::create_for_model( $model_id );

		// Get tool definitions.
		$tools = $this->get_tool_definitions();

		// Use strategy to select tool.
		return $strategy->select_tool( $query, $messages, $tools, $model_id );
	}

	/**
	 * Get tool definitions in standard format.
	 *
	 * These definitions are used by both native tool calling and prompt-based strategies.
	 *
	 * @since 1.0.0
	 * @return array Tool definitions with name, description, and parameters.
	 */
	private function get_tool_definitions(): array {
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		$tools = array(
			'search_content'         => array(
				'name'        => 'search_content',
				'description' => 'Search website pages and posts for information. Use for questions about site content, topics, pages, people, programs, etc.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'search_query'    => array(
							'type'        => 'string',
							'description' => 'Optimized search terms extracted from user query.',
						),
						'metadata_filter' => array(
							'type'                 => 'object',
							'description'          => 'Optional JSON metadata filter applied during retrieval using JSONB containment.',
							'additionalProperties' => true,
						),
					),
					'required'   => array( 'search_query' ),
				),
			),
			'summarize_conversation' => array(
				'name'        => 'summarize_conversation',
				'description' => 'Summarize or reference the current chat history. Use ONLY when user explicitly asks about "our conversation", "what we discussed", "this chat", "our session".',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'reason' => array(
							'type'        => 'string',
							'description' => 'Brief reason for selecting this tool.',
						),
					),
				),
			),
			'respond_directly'       => array(
				'name'        => 'respond_directly',
				'description' => 'Answer without searching. Use for: greetings ("hi", "hello"), meta questions ("what can you do"), time/date questions, or when you cannot help.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'reason' => array(
							'type'        => 'string',
							'description' => 'Brief reason for responding directly.',
						),
					),
				),
			),
			'clarify_previous'       => array(
				'name'        => 'clarify_previous',
				'description' => 'Clarify, simplify, expand, or rephrase the LAST assistant response. Use when user asks to explain something differently without asking a new question. Requires conversation history.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'clarification_type' => array(
							'type'        => 'string',
							'enum'        => array( 'simpler', 'detailed', 'example', 'rephrase' ),
							'description' => 'Type of clarification requested.',
						),
					),
					'required'   => array( 'clarification_type' ),
				),
			),
			'compare_content'        => array(
				'name'        => 'compare_content',
				'description' => 'Compare two or more topics, programs, services, or entities side by side. Use when the user asks "compare X vs Y", "difference between X and Y", or "how does X differ from Y".',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'entities'     => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'The entities or topics to compare (minimum 2).',
						),
						'aspects'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Specific aspects to compare, e.g. "cost", "eligibility", "location" (optional).',
						),
						'search_query' => array(
							'type'        => 'string',
							'description' => 'The original compare phrasing for fallback search.',
						),
					),
					'required'   => array( 'entities' ),
				),
			),
		);
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned

		/**
		 * Filter available RAG tools.
		 *
		 * Allows developers to register custom tools that can be selected by the agentic model
		 * or triggered directly via the REST API action endpoint.
		 *
		 * Each tool should have:
		 * - 'name': (string) Tool identifier (lowercase, underscores).
		 * - 'description': (string) When the LLM should use this tool.
		 * - 'parameters': (array) JSON schema for tool parameters.
		 * - 'user_action': (array, optional) For user-triggerable tools:
		 *   - 'label': (string) Display label for UI.
		 *   - 'icon': (string, optional) Dashicon name.
		 *
		 * @since 1.0.0
		 * @param array $tools Default tool definitions keyed by tool name.
		 * @return array Filtered tool definitions.
		 */
		return apply_filters( 'gg_data_rag_tools', $tools );
	}

	/**
	 * Stream LLM response with optional token-by-token output.
	 *
	 * Shared streaming method for any tool (direct response, summarize, clarify, etc.).
	 * Handles both streaming and non-streaming modes based on capability and callback availability.
	 *
	 * @since 1.0.0
	 * @param string        $query              The prompt/query to send to the LLM.
	 * @param string        $llm_model_id       LLM model ID.
	 * @param string        $system_prompt      System message for the LLM.
	 * @param callable|null $progress_callback Optional callback for SSE streaming progress events.
	 * @param array         $options             Optional. Additional options (max_tokens, temperature, etc.).
	 * @return array|WP_Error Response with 'text' and optional 'reasoning_content' keys, or error.
	 */
	private function stream_llm_response( $query, $llm_model_id, $system_prompt, $progress_callback = null, $options = array() ) {
		$start_time = microtime( true );

		// Get model info.
		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $llm_model_id );

		if ( ! $model_data || is_wp_error( $model_data ) ) {
			return new WP_Error( 'invalid_model', 'LLM model not found.' );
		}

		$model_config = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$model_name   = $model_config['provider_model_id'] ?? $model_data['provider_model_id'] ?? $llm_model_id;
		$provider_id  = $model_data['provider'] ?? $model_config['provider'] ?? 'openai';
		$native_max   = $this->model_max_tokens[ $model_name ] ?? 8192;
		$max_tokens   = (int) ( $options['max_tokens'] ?? $model_data['max_tokens'] ?? $model_config['max_tokens'] ?? $native_max );

		// Build AI request.
		$ai_request = GG_Data_Ai_Client::prompt( $query )
			->setSystemMessage( $system_prompt )
			->usingProvider( $provider_id )
			->usingModel( $model_name )
			->usingConnection( 'gregius-data' )
			->withMaxTokens( $max_tokens );

		// Check if streaming is available and callback is provided.
		$use_streaming = $progress_callback && $ai_request->supportsStreaming();

		if ( $use_streaming ) {
			// Emit streaming start event.
			$this->emit_progress( $progress_callback, 'streaming', __( 'Generating answer...', 'gregius-data' ) );

			// Define stream callback to forward chunks via progress events.
			$stream_callback = function ( $chunk ) use ( $progress_callback ) {
				if ( ! empty( $chunk['content'] ) ) {
					$this->emit_progress( $progress_callback, 'token', array( 'content' => $chunk['content'] ) );
				}
				if ( ! empty( $chunk['reasoning_content'] ) ) {
					$this->emit_progress( $progress_callback, 'reasoning_token', array( 'content' => $chunk['reasoning_content'] ) );
				}
			};

			// Stream the response.
			$response = $ai_request->generateTextStream( $stream_callback );

			// Extract text and reasoning from streaming response.
			$response_text     = is_array( $response ) ? ( $response['text'] ?? '' ) : $response;
			$reasoning_content = is_array( $response ) ? ( $response['reasoning_content'] ?? '' ) : '';
			$usage_data        = is_array( $response ) ? ( $response['usage'] ?? array() ) : array();
		} else {
			// Non-streaming: emit generating progress and wait for full response.
			if ( $progress_callback ) {
				$this->emit_progress( $progress_callback, 'generating', __( 'Generating answer...', 'gregius-data' ) );
			}

			// Generate text with metadata (for thinking models like DeepSeek R1).
			$response = $ai_request->generateTextWithMetadata();

			// Extract text, reasoning, and usage from response.
			$response_text     = is_array( $response ) ? ( $response['text'] ?? '' ) : $response;
			$reasoning_content = is_array( $response ) ? ( $response['reasoning_content'] ?? '' ) : '';
			$usage_data        = is_array( $response ) ? ( $response['usage'] ?? array() ) : array();
		}

		if ( is_wp_error( $response_text ) ) {
			return $response_text;
		}

		$execution_time = ( microtime( true ) - $start_time ) * 1000;

		return array(
			'text'              => $response_text,
			'reasoning_content' => $reasoning_content,
			'usage'             => $usage_data,
			'provider'          => $provider_id,
			'model'             => $model_name,
			'raw_response'      => is_array( $response ) ? ( $response['raw_response'] ?? array() ) : array(), // Complete API response.
			'execution_time_ms' => round( $execution_time ),
		);
	}

	/**
	 * Handle summarize_conversation tool - summarize the chat history.
	 *
	 * @since 1.0.0
	 * @param string $query        User's query.
	 * @param string $llm_model_id LLM model ID.
	 * @param array  $options      RAG options including messages.
	 * @return array Response data.
	 */
	private function handle_summarize_conversation( $query, $llm_model_id, $options ) {
		$start_time = microtime( true );
		$messages   = $options['messages'] ?? array();

		// Build conversation for summarization.
		$conversation_text = '';
		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				$role               = 'user' === $msg['role'] ? 'User' : 'Assistant';
				$conversation_text .= "{$role}: {$msg['content']}\n\n";
			}
		}

		if ( empty( $conversation_text ) ) {
			return array(
				'answer'   => "We haven't discussed anything yet in this conversation. Feel free to ask me about the website content!",
				'sources'  => array(),
				'metadata' => array(
					'tool'            => 'summarize_conversation',
					'tool_selected'   => 'summarize_conversation',
					'chunks_used'     => 0,
					'conversation_id' => $options['conversation_id'] ?? null,
					'source'          => $options['source'] ?? array( 'type' => 'rest' ),
					'connection'      => $this->connection_name,
					'rewrite_model'   => $options['rewrite_model'] ?? null,
					'embedding_model' => $this->embedding_model_key,
					'execution_time'  => round( ( microtime( true ) - $start_time ) * 1000 ),
					'security_check'  => $options['security_check'] ?? array(),
				),
			);
		}

		$system_prompt = 'You are a helpful assistant. The user wants to know about your conversation. Provide a clear, concise summary or answer based on the chat history provided.';

		$prompt = "Our conversation so far:\n\n{$conversation_text}\n\nUser's request: {$query}";

		// Use shared streaming method with progress callback.
		$progress_callback = $options['progress_callback'] ?? null;

		$response = $this->stream_llm_response(
			$prompt,
			$llm_model_id,
			$system_prompt,
			$progress_callback
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$execution_time = ( microtime( true ) - $start_time ) * 1000;

		return array(
			'answer'   => $response['text'],
			'sources'  => array(),
			'metadata' => array(
				'tool'              => 'summarize_conversation',
				'tool_selected'     => 'summarize_conversation',
				'chunks_used'       => 0,
				'conversation_id'   => $options['conversation_id'] ?? null,
				'source'            => $options['source'] ?? array( 'type' => 'rest' ),
				'connection'        => $this->connection_name,
				'rewrite_model'     => $options['rewrite_model'] ?? null,
				'embedding_model'   => $this->embedding_model_key,
				'llm_model'         => $llm_model_id,
				'execution_time'    => round( $execution_time ),
				'reasoning_content' => $response['reasoning_content'] ?? '',
				'usage'             => $response['usage'] ?? array(),
				'provider'          => $response['provider'] ?? '',
				'model_used'        => $response['model'] ?? '',
				'security_check'    => $options['security_check'] ?? array(),
			),
		);
	}

	/**
	 * Append manifest logging fields to response metadata.
	 *
	 * @since 1.0.0
	 * @param array $result  Tool result payload.
	 * @param array $options RAG options.
	 * @return array
	 */
	private function append_manifest_metadata_to_result( $result, $options ) {
		if ( ! is_array( $result ) ) {
			return $result;
		}

		$manifest      = isset( $options['manifest'] ) && is_array( $options['manifest'] ) ? $options['manifest'] : array();
		$manifest_json = wp_json_encode( $manifest );
		$metadata      = isset( $result['metadata'] ) && is_array( $result['metadata'] ) ? $result['metadata'] : array();

		$metadata['manifest']            = $manifest;
		$metadata['manifest_hash']       = is_string( $manifest_json ) ? hash( 'sha256', $manifest_json ) : '';
		$metadata['manifest_size_bytes'] = is_string( $manifest_json ) ? strlen( $manifest_json ) : 0;
		$metadata['manifest_version']    = isset( $manifest['manifest_version'] ) ? sanitize_text_field( (string) $manifest['manifest_version'] ) : '';

		$result['metadata'] = $metadata;
		$result             = $this->append_observability_sections_to_result( $result );

		return $result;
	}

	/**
	 * Append enterprise observability sections to a RAG result.
	 *
	 * Preserves legacy root fields for backward compatibility while exposing a
	 * summary-first structure optimized for logs, support, and operators.
	 *
	 * @since 1.0.0
	 * @param array $result Result payload.
	 * @return array
	 */
	private function append_observability_sections_to_result( array $result ) {
		$metadata = isset( $result['metadata'] ) && is_array( $result['metadata'] ) ? $result['metadata'] : array();
		$policy   = isset( $metadata['policy'] ) && is_array( $metadata['policy'] ) ? $metadata['policy'] : array();
		$security = isset( $metadata['security_check'] ) && is_array( $metadata['security_check'] ) ? $metadata['security_check'] : array();
		$source   = isset( $metadata['source'] ) && is_array( $metadata['source'] ) ? $metadata['source'] : array();
		$manifest = isset( $metadata['manifest'] ) && is_array( $metadata['manifest'] ) ? $metadata['manifest'] : array();

		$is_debug                    = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY );
		$include_verbose_diagnostics = (bool) apply_filters( 'gg_data_rag_include_verbose_diagnostics', $is_debug, $result, $metadata );

		$security_raw = array();
		if ( isset( $security['raw_response'] ) && is_array( $security['raw_response'] ) ) {
			$security_raw = $security['raw_response'];
			unset( $security['raw_response'] );
		}

		$sections = array(
			'request'           => array(
				'interaction_id'  => isset( $result['interaction_id'] ) ? absint( $result['interaction_id'] ) : 0,
				'conversation_id' => isset( $metadata['conversation_id'] ) ? sanitize_text_field( (string) $metadata['conversation_id'] ) : '',
				'type'            => 'rag',
				'source'          => isset( $source['type'] ) ? sanitize_key( (string) $source['type'] ) : 'rest',
			),
			'intent'            => array(
				'original_query'     => isset( $metadata['original_query'] ) ? sanitize_text_field( (string) $metadata['original_query'] ) : '',
				'rewritten_query'    => isset( $metadata['search_query'] ) ? sanitize_text_field( (string) $metadata['search_query'] ) : '',
				'tool_selected'      => isset( $metadata['tool_selected'] ) ? sanitize_key( (string) $metadata['tool_selected'] ) : '',
				'security_status'    => isset( $security['status'] ) ? sanitize_key( (string) $security['status'] ) : '',
				'policy_decision'    => isset( $policy['decision'] ) ? sanitize_key( (string) $policy['decision'] ) : '',
				'policy_reason_code' => isset( $policy['reason_code'] ) ? sanitize_key( (string) $policy['reason_code'] ) : '',
			),
			'outcome'           => array(
				'answer'     => isset( $result['answer'] ) ? (string) $result['answer'] : '',
				'sources'    => isset( $result['sources'] ) && is_array( $result['sources'] ) ? $result['sources'] : array(),
				'latency_ms' => isset( $metadata['execution_time'] ) ? (int) $metadata['execution_time'] : 0,
				'status'     => 'completed',
			),
			'execution'         => array(
				'models'     => array(
					'agentic'   => $metadata['rewrite_model'] ?? null,
					'embedding' => $metadata['embedding_model'] ?? null,
					'rerank'    => $metadata['rerank_model'] ?? null,
					'answer'    => $metadata['llm_model'] ?? null,
				),
				'provider'   => isset( $metadata['provider'] ) ? sanitize_key( (string) $metadata['provider'] ) : '',
				'model_used' => isset( $metadata['model_used'] ) ? sanitize_text_field( (string) $metadata['model_used'] ) : '',
				'usage'      => isset( $metadata['usage'] ) && is_array( $metadata['usage'] ) ? $metadata['usage'] : array(),
			),
			'retrieval_summary' => $this->build_retrieval_summary_section( $metadata['retrieval'] ?? array() ),
			'policy'            => $policy,
			'security'          => $security,
			'context'           => array(
				'manifest'            => $this->build_manifest_context_preview( $manifest ),
				'manifest_hash'       => isset( $metadata['manifest_hash'] ) ? sanitize_text_field( (string) $metadata['manifest_hash'] ) : '',
				'manifest_size_bytes' => isset( $metadata['manifest_size_bytes'] ) ? (int) $metadata['manifest_size_bytes'] : 0,
				'manifest_version'    => isset( $metadata['manifest_version'] ) ? sanitize_text_field( (string) $metadata['manifest_version'] ) : '',
			),
			'diagnostics'       => array(
				'reasoning_content' => isset( $metadata['reasoning_content'] ) ? (string) $metadata['reasoning_content'] : '',
				'has_security_raw'  => ! empty( $security_raw ),
				'has_llm_raw'       => isset( $metadata['raw_response'] ) && is_array( $metadata['raw_response'] ) && ! empty( $metadata['raw_response'] ),
			),
		);

		if ( $include_verbose_diagnostics ) {
			$sections['diagnostics']['security_raw_response'] = $security_raw;
			$sections['diagnostics']['llm_raw_response']      = isset( $metadata['raw_response'] ) && is_array( $metadata['raw_response'] ) ? $metadata['raw_response'] : array();
			$sections['context']['manifest_full']             = $manifest;
		}

		$legacy = array(
			'interaction_id'      => isset( $result['interaction_id'] ) ? absint( $result['interaction_id'] ) : 0,
			'conversation_id'     => isset( $metadata['conversation_id'] ) ? sanitize_text_field( (string) $metadata['conversation_id'] ) : '',
			'type'                => 'rag',
			'source'              => isset( $source['type'] ) ? sanitize_key( (string) $source['type'] ) : 'rest',
			'query'               => array(
				'original'  => isset( $metadata['original_query'] ) ? sanitize_text_field( (string) $metadata['original_query'] ) : '',
				'rewritten' => isset( $metadata['search_query'] ) ? sanitize_text_field( (string) $metadata['search_query'] ) : '',
			),
			'response'            => isset( $result['answer'] ) ? (string) $result['answer'] : '',
			'tool_selected'       => isset( $metadata['tool_selected'] ) ? sanitize_key( (string) $metadata['tool_selected'] ) : '',
			'sources'             => isset( $result['sources'] ) && is_array( $result['sources'] ) ? $result['sources'] : array(),
			'models'              => $sections['execution']['models'],
			'search'              => array(
				'post_types'      => $metadata['post_types'] ?? array(),
				'metadata_filter' => $metadata['metadata_filter'] ?? array(),
			),
			'latency'             => array(
				'total_ms' => isset( $metadata['execution_time'] ) ? (int) $metadata['execution_time'] : 0,
			),
			'retrieval'           => isset( $metadata['retrieval'] ) && is_array( $metadata['retrieval'] ) ? $metadata['retrieval'] : array(),
			'prompt'              => isset( $metadata['prompt'] ) && is_array( $metadata['prompt'] ) ? $metadata['prompt'] : array(),
			'security_check'      => isset( $metadata['security_check'] ) && is_array( $metadata['security_check'] ) ? $metadata['security_check'] : array(),
			'usage'               => isset( $metadata['usage'] ) && is_array( $metadata['usage'] ) ? $metadata['usage'] : array(),
			'provider'            => isset( $metadata['provider'] ) ? sanitize_key( (string) $metadata['provider'] ) : '',
			'model_used'          => isset( $metadata['model_used'] ) ? sanitize_text_field( (string) $metadata['model_used'] ) : '',
			'reasoning_content'   => isset( $metadata['reasoning_content'] ) ? (string) $metadata['reasoning_content'] : '',
			'manifest'            => $manifest,
			'manifest_hash'       => isset( $metadata['manifest_hash'] ) ? sanitize_text_field( (string) $metadata['manifest_hash'] ) : '',
			'manifest_size_bytes' => isset( $metadata['manifest_size_bytes'] ) ? (int) $metadata['manifest_size_bytes'] : 0,
			'manifest_version'    => isset( $metadata['manifest_version'] ) ? sanitize_text_field( (string) $metadata['manifest_version'] ) : '',
			'answer'              => isset( $result['answer'] ) ? (string) $result['answer'] : '',
			'metadata'            => $metadata,
		);

		$sections['legacy'] = $legacy;

		// Merge enterprise sections additively onto the original result.
		// Top-level contract fields (answer, sources, metadata, citation_sources, interaction_id)
		// remain intact for internal hooks, guards, logging, and SSE consumers downstream.
		// The REST boundary applies strict external schema before the API response.
		foreach ( $sections as $section_key => $section_value ) {
			$result[ $section_key ] = $section_value;
		}

		return $result;
	}

	/**
	 * Build a slim manifest preview for default context payloads.
	 *
	 * @since 1.0.0
	 * @param array $manifest Manifest payload.
	 * @return array
	 */
	private function build_manifest_context_preview( array $manifest ) {
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
	 * Build a concise retrieval summary section.
	 *
	 * @since 1.0.0
	 * @param array $retrieval Full retrieval payload.
	 * @return array
	 */
	private function build_retrieval_summary_section( $retrieval ) {
		if ( ! is_array( $retrieval ) ) {
			return array();
		}

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
	 * Summarize the current entity identified by the request manifest.
	 *
	 * @since 1.0.0
	 * @param string $query        User query.
	 * @param string $llm_model_id LLM model ID.
	 * @param array  $options      RAG options.
	 * @return array|WP_Error
	 */
	private function handle_summarize_current_entity( $query, $llm_model_id, $options ) {
		$start_time = microtime( true );
		$manifest   = isset( $options['manifest'] ) && is_array( $options['manifest'] ) ? $options['manifest'] : array();

		$entity         = isset( $manifest['entity'] ) && is_array( $manifest['entity'] ) ? $manifest['entity'] : array();
		$entity_id      = isset( $entity['id'] ) ? absint( $entity['id'] ) : 0;
		$entity_type    = isset( $entity['type'] ) ? sanitize_key( (string) $entity['type'] ) : '';
		$entity_title   = isset( $entity['title']['rendered'] ) ? sanitize_text_field( (string) $entity['title']['rendered'] ) : '';
		$source_post_id = isset( $options['source']['post_id'] ) ? absint( $options['source']['post_id'] ) : 0;

		if ( $entity_id <= 0 && $source_post_id > 0 ) {
			$entity_id = $source_post_id;
		}

		if ( '' === $entity_type && $entity_id > 0 ) {
			$entity_type = (string) get_post_type( $entity_id );
		}

		if ( $entity_id <= 0 ) {
			return array(
				'answer'   => __( 'I could not determine which content to summarize.', 'gregius-data' ),
				'sources'  => array(),
				'metadata' => array(
					'tool'            => 'summarize_current_entity',
					'tool_selected'   => 'summarize_current_entity',
					'chunks_used'     => 0,
					'conversation_id' => $options['conversation_id'] ?? null,
					'source'          => $options['source'] ?? array( 'type' => 'rest' ),
					'connection'      => $this->connection_name,
					'embedding_model' => $this->embedding_model_key,
					'llm_model'       => $llm_model_id,
					'execution_time'  => round( ( microtime( true ) - $start_time ) * 1000 ),
					'security_check'  => $options['security_check'] ?? array(),
				),
			);
		}

		$summary_meta_key = apply_filters( 'gg_data_rag_entity_summary_meta_key', '_gg_manifest_summary', $entity_type, $entity_id );
		$cached_summary   = get_post_meta( $entity_id, $summary_meta_key, true );

		if ( is_string( $cached_summary ) && '' !== trim( $cached_summary ) ) {
			return array(
				'answer'   => trim( $cached_summary ),
				'sources'  => array(),
				'metadata' => array(
					'tool'             => 'summarize_current_entity',
					'tool_selected'    => 'summarize_current_entity',
					'cache_hit'        => true,
					'summary_meta_key' => $summary_meta_key,
					'entity_id'        => $entity_id,
					'entity_type'      => $entity_type,
					'chunks_used'      => 0,
					'conversation_id'  => $options['conversation_id'] ?? null,
					'source'           => $options['source'] ?? array( 'type' => 'rest' ),
					'connection'       => $this->connection_name,
					'embedding_model'  => $this->embedding_model_key,
					'llm_model'        => $llm_model_id,
					'execution_time'   => round( ( microtime( true ) - $start_time ) * 1000 ),
					'security_check'   => $options['security_check'] ?? array(),
				),
			);
		}

		$entity_content = $this->get_entity_content_for_summary( $entity_id );

		if ( '' === $entity_content ) {
			return array(
				'answer'   => __( 'I could not load enough content to generate a summary.', 'gregius-data' ),
				'sources'  => array(),
				'metadata' => array(
					'tool'            => 'summarize_current_entity',
					'tool_selected'   => 'summarize_current_entity',
					'cache_hit'       => false,
					'entity_id'       => $entity_id,
					'entity_type'     => $entity_type,
					'chunks_used'     => 0,
					'conversation_id' => $options['conversation_id'] ?? null,
					'source'          => $options['source'] ?? array( 'type' => 'rest' ),
					'connection'      => $this->connection_name,
					'embedding_model' => $this->embedding_model_key,
					'llm_model'       => $llm_model_id,
					'execution_time'  => round( ( microtime( true ) - $start_time ) * 1000 ),
					'security_check'  => $options['security_check'] ?? array(),
				),
			);
		}

		$summary_prompt = sprintf(
			"Entity type: %s\nEntity title: %s\n\nSummarize the content below in 2-4 short paragraphs. Prioritize key facts, purpose, and concrete next steps if present.\n\n%s",
			$entity_type,
			$entity_title,
			$entity_content
		);

		$response = $this->stream_llm_response(
			$summary_prompt,
			$llm_model_id,
			'You summarize WordPress content clearly and faithfully. Do not invent facts and do not include information not present in the source.',
			$options['progress_callback'] ?? null
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$summary_text = trim( (string) ( $response['text'] ?? '' ) );
		if ( '' !== $summary_text ) {
			update_post_meta( $entity_id, $summary_meta_key, $summary_text );
		}

		return array(
			'answer'   => $summary_text,
			'sources'  => array(),
			'metadata' => array(
				'tool'              => 'summarize_current_entity',
				'tool_selected'     => 'summarize_current_entity',
				'cache_hit'         => false,
				'summary_meta_key'  => $summary_meta_key,
				'entity_id'         => $entity_id,
				'entity_type'       => $entity_type,
				'chunks_used'       => 0,
				'conversation_id'   => $options['conversation_id'] ?? null,
				'source'            => $options['source'] ?? array( 'type' => 'rest' ),
				'connection'        => $this->connection_name,
				'embedding_model'   => $this->embedding_model_key,
				'llm_model'         => $llm_model_id,
				'execution_time'    => round( ( microtime( true ) - $start_time ) * 1000 ),
				'reasoning_content' => $response['reasoning_content'] ?? '',
				'usage'             => $response['usage'] ?? array(),
				'provider'          => $response['provider'] ?? '',
				'model_used'        => $response['model'] ?? '',
				'raw_response'      => $response['raw_response'] ?? array(),
				'security_check'    => $options['security_check'] ?? array(),
			),
		);
	}

	/**
	 * Recommend related content using manifest-derived metadata filtering.
	 *
	 * @since 1.0.0
	 * @param string $query        User query.
	 * @param string $llm_model_id LLM model ID.
	 * @param array  $options      RAG options.
	 * @return array|WP_Error
	 */
	private function handle_recommend_related_content( $query, $llm_model_id, $options ) {
		$start_time     = microtime( true );
		$manifest       = isset( $options['manifest'] ) && is_array( $options['manifest'] ) ? $options['manifest'] : array();
		$derived_filter = $this->build_manifest_related_filter( $manifest );
		$search_query   = '' !== trim( $query ) ? $query : __( 'Recommend related content for the current item', 'gregius-data' );

		$search_options = $options;
		if ( ! empty( $derived_filter ) ) {
			$current_filter                    = isset( $search_options['metadata_filter'] ) && is_array( $search_options['metadata_filter'] ) ? $search_options['metadata_filter'] : array();
			$search_options['metadata_filter'] = array_merge( $current_filter, $derived_filter );
		}

		$result = $this->synthesize_search_content( $search_query, $llm_model_id, $search_options, $start_time, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $result['metadata'] ) && is_array( $result['metadata'] ) ) {
			$result['metadata']['tool']                    = 'recommend_related_content';
			$result['metadata']['tool_selected']           = 'recommend_related_content';
			$result['metadata']['manifest_derived_filter'] = $derived_filter;
		}

		return $result;
	}

	/**
	 * Load canonical content used for deterministic entity summaries.
	 *
	 * @since 1.0.0
	 * @param int $entity_id Post ID.
	 * @return string
	 */
	private function get_entity_content_for_summary( $entity_id ) {
		$entity_id = absint( $entity_id );
		if ( $entity_id <= 0 ) {
			return '';
		}

		try {
			$db         = new GG_Data_DB();
			$connection = $db->get_connection( $this->connection_name );

			if ( $connection ) {
				$stmt = $connection->prepare( 'SELECT post_title_clean, post_content_clean FROM wp_posts_clean WHERE post_id = :post_id LIMIT 1' );
				$stmt->execute( array( ':post_id' => $entity_id ) );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections.
				$row = $stmt->fetch( PDO::FETCH_ASSOC );

				if ( is_array( $row ) ) {
					$title   = isset( $row['post_title_clean'] ) ? (string) $row['post_title_clean'] : '';
					$content = isset( $row['post_content_clean'] ) ? (string) $row['post_content_clean'] : '';
					$text    = trim( $title . "\n\n" . $content );

					if ( '' !== $text ) {
						return mb_substr( $text, 0, 12000 );
					}
				}
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'RAG Service: Failed loading wp_posts_clean summary content for post_id=%d', $entity_id ),
				'debug',
				'rag',
				$this->connection_name,
				array( 'error' => $e->getMessage() )
			);
		}

		$post = get_post( $entity_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return '';
		}

		$text = trim( $post->post_title . "\n\n" . wp_strip_all_tags( (string) $post->post_content ) );

		return '' !== $text ? mb_substr( $text, 0, 12000 ) : '';
	}

	/**
	 * Build a metadata_filter payload from manifest taxonomies.
	 *
	 * @since 1.0.0
	 * @param array $manifest Manifest payload.
	 * @return array
	 */
	private function build_manifest_related_filter( $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return array();
		}

		$entity      = isset( $manifest['entity'] ) && is_array( $manifest['entity'] ) ? $manifest['entity'] : array();
		$taxonomies  = isset( $entity['taxonomies'] ) && is_array( $entity['taxonomies'] ) ? $entity['taxonomies'] : array();
		$entity_type = isset( $entity['type'] ) ? sanitize_key( (string) $entity['type'] ) : '';
		$terms       = array();

		foreach ( $taxonomies as $taxonomy_key => $taxonomy_terms ) {
			if ( ! is_array( $taxonomy_terms ) ) {
				continue;
			}

			$taxonomy = sanitize_key( (string) $taxonomy_key );
			if ( 'categories' === $taxonomy ) {
				$taxonomy = 'category';
			} elseif ( 'tags' === $taxonomy ) {
				$taxonomy = 'post_tag';
			}

			foreach ( $taxonomy_terms as $term ) {
				if ( ! is_array( $term ) ) {
					continue;
				}

				$term_id   = isset( $term['id'] ) ? absint( $term['id'] ) : 0;
				$term_slug = isset( $term['slug'] ) ? sanitize_title( (string) $term['slug'] ) : '';
				if ( $term_id <= 0 || '' === $term_slug || '' === $taxonomy ) {
					continue;
				}

				$terms[] = array(
					'taxonomy'  => $taxonomy,
					'term_slug' => $term_slug,
					'term_id'   => $term_id,
				);
			}
		}

		$terms  = array_slice( $terms, 0, 6 );
		$filter = array();

		if ( '' !== $entity_type ) {
			$filter['post_type'] = $entity_type;
		}

		if ( ! empty( $terms ) ) {
			$filter['taxonomy_manifest'] = $terms;
		}

		return $filter;
	}


	/**
	 * Handle respond_directly tool - respond without searching.
	 *
	 * Used for greetings, time questions, and graceful declines.
	 *
	 * @since 1.0.0
	 * @param string $query        User's query.
	 * @param string $llm_model_id LLM model ID.
	 * @param array  $options      RAG options.
	 * @param string $reason       Reason from tool selection.
	 * @return array Response data.
	 */
	private function handle_respond_directly( $query, $llm_model_id, $options, $reason = '' ) {
		$start_time = microtime( true );

		$prompt_resolution = $this->resolve_system_prompt( $options );

		if ( is_wp_error( $prompt_resolution ) ) {
			return $prompt_resolution;
		}

		$system_prompt = $prompt_resolution['content'];

		// Use shared streaming method with progress callback.
		$progress_callback = $options['progress_callback'] ?? null;

		$response = $this->stream_llm_response(
			$query,
			$llm_model_id,
			$system_prompt,
			$progress_callback
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$execution_time = ( microtime( true ) - $start_time ) * 1000;

		return array(
			'answer'   => $response['text'],
			'sources'  => array(),
			'metadata' => array(
				'tool'              => 'respond_directly',
				'tool_selected'     => 'respond_directly',
				'reason'            => $reason,
				'chunks_used'       => 0,
				'conversation_id'   => $options['conversation_id'] ?? null,
				'source'            => $options['source'] ?? array( 'type' => 'rest' ),
				'connection'        => $this->connection_name,
				'prompt'            => $prompt_resolution['metadata'] ?? array(),
				'rewrite_model'     => $options['rewrite_model'] ?? null,
				'embedding_model'   => $this->embedding_model_key,
				'llm_model'         => $llm_model_id,
				'execution_time'    => round( $execution_time ),
				'reasoning_content' => $response['reasoning_content'] ?? '',
				'usage'             => $response['usage'] ?? array(),
				'provider'          => $response['provider'] ?? '',
				'model_used'        => $response['model'] ?? '',
				'raw_response'      => $response['raw_response'] ?? array(), // Complete API response.
				'security_check'    => $options['security_check'] ?? array(),
			),
		);
	}

	/**
	 * Resolve the effective system prompt from stored prompt posts.
	 *
	 * @since 1.0.0
	 * @param array $options RAG options.
	 * @return array|WP_Error
	 */
	private function resolve_system_prompt( $options = array() ) {
		if ( ! class_exists( 'GG_Data_Prompt_Resolver' ) ) {
			return new WP_Error(
				'gg_data_prompt_resolver_missing',
				__( 'Prompt resolver is not available.', 'gregius-data' )
			);
		}

		$resolver    = new GG_Data_Prompt_Resolver();
		$prompt_type = isset( $options['prompt_type'] ) ? sanitize_key( $options['prompt_type'] ) : 'system';

		if ( '' === $prompt_type ) {
			$prompt_type = 'system';
		}

		return $resolver->resolve_prompt(
			isset( $options['prompt_id'] ) ? absint( $options['prompt_id'] ) : 0,
			$prompt_type
		);
	}

	/**
	 * Run security gatekeeper classification before retrieval.
	 *
	 * @since 1.0.0
	 * @param string $query        User query.
	 * @param string $llm_model_id Model ID used for answer generation.
	 * @param array  $options      RAG options.
	 * @return array|WP_Error
	 */
	private function run_security_gatekeeper_check( $query, $llm_model_id, $options ) {
		$security_prompt_options                = $options;
		$security_prompt_options['prompt_type'] = 'security';
		$security_prompt_options['prompt_id']   = isset( $options['security_prompt_id'] ) ? absint( $options['security_prompt_id'] ) : 0;

		$prompt_resolution = $this->resolve_system_prompt( $security_prompt_options );

		if ( is_wp_error( $prompt_resolution ) ) {
			return $prompt_resolution;
		}

		$security_prompt = trim( (string) ( $prompt_resolution['content'] ?? '' ) );

		if ( '' === $security_prompt ) {
			return new WP_Error(
				'gg_data_security_prompt_empty',
				__( 'Security gatekeeper prompt is empty.', 'gregius-data' )
			);
		}

		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $llm_model_id );

		if ( ! $model_data || is_wp_error( $model_data ) ) {
			return new WP_Error(
				'invalid_security_model',
				__( 'Security gatekeeper model could not be resolved.', 'gregius-data' )
			);
		}

		$model_config = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$model_name   = $model_config['provider_model_id'] ?? $model_data['provider_model_id'] ?? $llm_model_id;
		$provider_id  = $model_data['provider'] ?? $model_config['provider'] ?? 'openai';
		$native_max   = $this->model_max_tokens[ $model_name ] ?? 8192;
		$max_tokens   = (int) ( $model_data['max_tokens'] ?? $model_config['max_tokens'] ?? $native_max );

		$security_request = GG_Data_Ai_Client::prompt( sprintf( "User query:\n%s", (string) $query ) )
			->setSystemMessage( $security_prompt )
			->usingProvider( $provider_id )
			->usingModel( $model_name )
			->usingConnection( 'gregius-data' )
			->withMaxTokens( min( 256, $max_tokens ) );

		$response = $security_request->generateTextWithMetadata();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_text = is_array( $response ) ? ( $response['text'] ?? '' ) : (string) $response;
		$parsed        = $this->parse_security_gatekeeper_response( $response_text );

		$security_check = array(
			'status'       => $parsed['status'],
			'reason'       => $parsed['reason'],
			'prompt'       => $prompt_resolution['metadata'] ?? array(),
			'provider'     => is_array( $response ) ? ( $response['provider'] ?? '' ) : '',
			'model_used'   => is_array( $response ) ? ( $response['model'] ?? '' ) : '',
			'usage'        => is_array( $response ) ? ( $response['usage'] ?? array() ) : array(),
			'raw_response' => is_array( $response ) ? ( $response['raw_response'] ?? array() ) : array(),
		);

		return $security_check;
	}

	/**
	 * Parse the security gatekeeper response into a normalized structure.
	 *
	 * @since 1.0.0
	 * @param string $response_text LLM response text.
	 * @return array
	 */
	private function parse_security_gatekeeper_response( $response_text ) {
		$status = 'safe';
		$reason = __( 'No policy violation detected.', 'gregius-data' );

		$decoded = json_decode( trim( (string) $response_text ), true );

		if ( is_array( $decoded ) ) {
			$decoded_status = strtoupper( sanitize_text_field( (string) ( $decoded['status'] ?? 'SAFE' ) ) );
			$decoded_reason = sanitize_text_field( (string) ( $decoded['reason'] ?? '' ) );

			if ( 'UNSAFE' === $decoded_status ) {
				$status = 'unsafe';
			}

			if ( '' !== $decoded_reason ) {
				$reason = $decoded_reason;
			}
		} elseif ( false !== stripos( (string) $response_text, 'unsafe' ) ) {
			$status = 'unsafe';
			$reason = sanitize_text_field( (string) $response_text );
		}

		return array(
			'status' => $status,
			'reason' => $reason,
		);
	}

	/**
	 * Build a short-circuit response when security policy blocks retrieval.
	 *
	 * @since 1.0.0
	 * @param string $query          Original query.
	 * @param string $llm_model_id   Answer model ID.
	 * @param string $search_query   Planned search query.
	 * @param array  $tool_selection Tool selection metadata.
	 * @param array  $security_check Security check result.
	 * @param array  $options        RAG options.
	 * @param float  $start_time     Request start time.
	 * @return array
	 */
	private function build_security_blocked_response( $query, $llm_model_id, $search_query, $tool_selection, $security_check, $options, $start_time ) {
		$execution_time = round( ( microtime( true ) - $start_time ) * 1000 );

		$retrieval_metadata = $this->build_retrieval_parity( 0, 0, 0, array( 'mode' => 'security_blocked' ) );
		$policy_metadata    = $this->build_policy_parity(
			'single',
			'abstain',
			'security_blocked',
			'policy_security_gatekeeper',
			array(
				'agentic_model_active'   => ! empty( $options['rewrite_model'] ),
				'embedding_model_active' => ! empty( $this->embedding_model_key ),
				'lexical_active'         => true,
			)
		);

		$metadata = $this->ensure_policy_retrieval_contract(
			array(
				'chunks_used'       => 0,
				'embedding_model'   => $this->embedding_model_key,
				'llm_model'         => $llm_model_id,
				'connection'        => $this->connection_name,
				'execution_time'    => $execution_time,
				'reasoning_content' => '',
				'usage'             => array(),
				'provider'          => '',
				'model_used'        => '',
				'raw_response'      => array(),
				'conversation_id'   => $options['conversation_id'] ?? null,
				'source'            => $options['source'] ?? array( 'type' => 'rest' ),
				'original_query'    => $query,
				'search_query'      => $search_query,
				'post_types'        => $options['post_types'] ?? array(),
				'rewrite_model'     => $options['rewrite_model'] ?? null,
				'rerank_model'      => $options['rerank_model_id'] ?? null,
				'citation_sources'  => array(),
				'tool_selected'     => $tool_selection['tool'] ?? 'search_content',
				'prompt'            => array(),
				'security_check'    => $security_check,
				'query_plan'        => array(
					'active' => false,
					'intent' => 'single',
				),
				'retrieval'         => $retrieval_metadata,
				'policy'            => $policy_metadata,
			),
			'single'
		);

		return array(
			'answer'   => __( 'I can\'t help with that request.', 'gregius-data' ),
			'sources'  => array(),
			'metadata' => $metadata,
		);
	}


	/**
	 * Handle clarify_previous tool - clarify, simplify, or expand the last response.
	 *
	 * Used when users ask for clarification without wanting a new search.
	 * Examples: "explain that simpler", "give me an example", "what do you mean?"
	 *
	 * @since 1.0.0
	 * @param string $query              User's query.
	 * @param string $llm_model_id       LLM model ID.
	 * @param array  $options            RAG options including messages.
	 * @param string $clarification_type Type of clarification: simpler|detailed|example|rephrase.
	 * @return array Response data.
	 */
	private function handle_clarify_previous( $query, $llm_model_id, $options, $clarification_type = 'rephrase' ) {
		$start_time = microtime( true );
		$messages   = $options['messages'] ?? array();

		// Get the last assistant message from conversation history.
		$last_assistant_message = $this->get_last_assistant_message( $messages );

		if ( empty( $last_assistant_message ) ) {
			return array(
				'answer'   => "I don't have a previous response to clarify. Please ask me a question first, and then I can explain it in different ways if needed.",
				'sources'  => array(),
				'metadata' => array(
					'tool'            => 'clarify_previous',
					'tool_selected'   => 'clarify_previous',
					'type'            => $clarification_type,
					'chunks_used'     => 0,
					'conversation_id' => $options['conversation_id'] ?? null,
					'source'          => $options['source'] ?? array( 'type' => 'rest' ),
					'connection'      => $this->connection_name,
					'rewrite_model'   => $options['rewrite_model'] ?? null,
					'embedding_model' => $this->embedding_model_key,
					'execution_time'  => round( ( microtime( true ) - $start_time ) * 1000 ),
					'security_check'  => $options['security_check'] ?? array(),
				),
			);
		}

		// Build clarification prompt based on type.
		$clarification_prompts = array(
			'simpler'  => "The user wants a simpler explanation. Rewrite the following response using plain, everyday language. Avoid jargon and technical terms. Use shorter sentences.\n\nOriginal response:\n%s\n\nSimpler explanation:",
			'detailed' => "The user wants more details. Expand on the following response with additional information, context, and depth. Maintain accuracy.\n\nOriginal response:\n%s\n\nDetailed explanation:",
			'example'  => "The user wants a concrete example. Based on the following response, provide a practical, real-world example that illustrates the concept clearly.\n\nOriginal response:\n%s\n\nExample:",
			'rephrase' => "The user wants this explained differently. Rephrase the following response using different words and structure while keeping the same meaning.\n\nOriginal response:\n%s\n\nRephrased:",
		);

		// Default to rephrase if unknown type.
		if ( ! isset( $clarification_prompts[ $clarification_type ] ) ) {
			$clarification_type = 'rephrase';
		}

		$prompt = sprintf( $clarification_prompts[ $clarification_type ], $last_assistant_message );

		$system_prompt = 'You are a helpful assistant skilled at explaining concepts in different ways. Your goal is to help the user understand the previous response better. Be clear, helpful, and maintain accuracy.';

		// Use shared streaming method with progress callback.
		$progress_callback = $options['progress_callback'] ?? null;

		$response = $this->stream_llm_response(
			$prompt,
			$llm_model_id,
			$system_prompt,
			$progress_callback
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$execution_time = ( microtime( true ) - $start_time ) * 1000;

		return array(
			'answer'   => $response['text'],
			'sources'  => array(),
			'metadata' => array(
				'tool'              => 'clarify_previous',
				'tool_selected'     => 'clarify_previous',
				'type'              => $clarification_type,
				'chunks_used'       => 0,
				'conversation_id'   => $options['conversation_id'] ?? null,
				'source'            => $options['source'] ?? array( 'type' => 'rest' ),
				'connection'        => $this->connection_name,
				'rewrite_model'     => $options['rewrite_model'] ?? null,
				'embedding_model'   => $this->embedding_model_key,
				'llm_model'         => $llm_model_id,
				'execution_time'    => round( $execution_time ),
				'reasoning_content' => $response['reasoning_content'] ?? '',
				'usage'             => $response['usage'] ?? array(),
				'provider'          => $response['provider'] ?? '',
				'model_used'        => $response['model'] ?? '',
				'raw_response'      => $response['raw_response'] ?? array(), // Complete API response.
				'security_check'    => $options['security_check'] ?? array(),
			),
		);
	}

	// =========================================================================
	// Passage Retrieval Methods (Step 1 & 2 — RAG-PASSAGE-RETRIEVAL-IMPLEMENTATION)
	// =========================================================================

	/**
	 * Fetch chunk rows from wp_posts_chunks for a given set of post IDs.
	 *
	 * @since 2.2.0
	 * @param int[] $post_ids Post IDs to fetch chunks for.
	 * @param array $config   Connection config with 'project_url' and 'api_key'.
	 * @return array Chunks grouped by int post_id. Empty array on failure.
	 */
	private function fetch_chunks_for_posts( $post_ids, $config ) {
		if ( empty( $post_ids ) ) {
			return array();
		}

		$base_url = rtrim( $config['project_url'], '/' );
		$ids_list = implode( ',', array_map( 'intval', $post_ids ) );

		$url = add_query_arg(
			array(
				'select'  => 'post_id,chunk_index,chunk_text,token_count',
				'post_id' => 'in.(' . $ids_list . ')',
				'order'   => 'post_id.asc,chunk_index.asc',
			),
			$base_url . '/rest/v1/wp_posts_chunks'
		);

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $config['publishable_key'] ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->logger->log(
				'RAG Service: Failed to fetch chunk rows from wp_posts_chunks',
				'warning',
				'rag',
				$this->connection_name
			);
			return array();
		}

		$rows = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $rows ) ) {
			return array();
		}

		$chunks_by_post = array();
		foreach ( $rows as $row ) {
			$pid = (int) $row['post_id'];
			if ( ! isset( $chunks_by_post[ $pid ] ) ) {
				$chunks_by_post[ $pid ] = array();
			}
			$chunks_by_post[ $pid ][] = $row;
		}

		return $chunks_by_post;
	}

	/**
	 * Build normalized chunk candidates by merging chunk rows with post-level scores.
	 *
	 * @since 2.2.0
	 * @param array $post_results   Post-level results from retrieve_chunks().
	 * @param array $chunks_by_post Chunk rows grouped by post_id from fetch_chunks_for_posts().
	 * @return array Normalized chunk candidates.
	 */
	private function build_chunk_candidates( $post_results, $chunks_by_post ) {
		$post_lookup = array();
		foreach ( $post_results as $post_result ) {
			$post_lookup[ (int) $post_result['post_id'] ] = $post_result;
		}

		$candidates = array();
		foreach ( $chunks_by_post as $post_id => $chunks ) {
			$post_data = $post_lookup[ $post_id ] ?? null;
			if ( ! $post_data ) {
				continue;
			}

			foreach ( $chunks as $chunk ) {
				$candidates[] = array(
					'post_id'         => $post_id,
					'chunk_index'     => (int) $chunk['chunk_index'],
					'title'           => $post_data['title'],
					'content'         => $chunk['chunk_text'],
					'excerpt'         => wp_trim_words( $chunk['chunk_text'], 30 ),
					'url'             => $post_data['url'],
					'score'           => (float) $post_data['score'],
					'post_score'      => (float) $post_data['score'],
					'chunk_score'     => 0.0,
					'rerank_score'    => null,
					'match_type'      => 'hybrid_chunk',
					'selection_stage' => 'candidate',
					'tokens'          => (int) ( $chunk['token_count'] ?? 0 ),
				);
			}
		}

		return $candidates;
	}

	/**
	 * Prefilter chunk candidates before scoring to prevent any single post from
	 * dominating the downstream scorer.
	 *
	 * @since 2.2.0
	 * @param array  $chunk_candidates Candidates from build_chunk_candidates().
	 * @param int    $max_per_post     Maximum chunks to retain per post. Default 2.
	 * @param int    $max_total        Maximum total candidates to pass downstream. Default 12.
	 * @param string $search_query    Optional search query used for chunk overlap scoring.
	 * @return array Prefiltered candidates.
	 */
	private function prefilter_chunk_candidates( $chunk_candidates, $max_per_post = 2, $max_total = 12, $search_query = '' ) {
		foreach ( $chunk_candidates as &$candidate ) {
			if ( ! isset( $candidate['post_score'] ) ) {
				$candidate['post_score'] = isset( $candidate['score'] ) ? (float) $candidate['score'] : 0.0;
			}
			if ( ! isset( $candidate['chunk_score'] ) ) {
				$candidate['chunk_score'] = 0.0;
			}
		}
		unset( $candidate );

		// Compute chunk-level term-overlap score when a search query is available.
		// This ensures relevant chunks buried in long documents (e.g. glossaries,
		// long changelogs) are not discarded in favour of positionally-early chunks.
		if ( ! empty( $search_query ) ) {
			$query_terms = $this->tokenize_for_overlap( $search_query );
			foreach ( $chunk_candidates as &$candidate ) {
				if ( 0.0 === (float) $candidate['chunk_score'] ) {
					$candidate['chunk_score'] = $this->compute_lexical_overlap( $candidate['content'], $query_terms );
				}
			}
			unset( $candidate );
		}

		$by_post = array();
		foreach ( $chunk_candidates as $candidate ) {
			$pid = $candidate['post_id'];
			if ( ! isset( $by_post[ $pid ] ) ) {
				$by_post[ $pid ] = array();
			}
			$by_post[ $pid ][] = $candidate;
		}

		$prefiltered = array();
		foreach ( $by_post as $post_chunks ) {
			// Sort by chunk-level term-overlap score descending so the most query-relevant
			// chunks are kept. Fall back to positional order when scores are equal,
			// preserving the original behaviour for documents with no term overlap.
			usort(
				$post_chunks,
				function ( $a, $b ) {
					$score_diff = $b['chunk_score'] <=> $a['chunk_score'];
					if ( 0 !== $score_diff ) {
						return $score_diff;
					}
					$a_index = isset( $a['chunk_index'] ) ? (int) $a['chunk_index'] : 0;
					$b_index = isset( $b['chunk_index'] ) ? (int) $b['chunk_index'] : 0;
					return $a_index <=> $b_index;
				}
			);

			$kept = array_slice( $post_chunks, 0, $max_per_post );
			foreach ( $kept as &$chunk ) {
				$chunk['selection_stage'] = 'prefilter';
			}
			unset( $chunk );

			$prefiltered = array_merge( $prefiltered, $kept );
		}

		// Retain retrieval ranking order across posts.
		usort(
			$prefiltered,
			function ( $a, $b ) {
				$b_post_score = isset( $b['post_score'] ) ? (float) $b['post_score'] : ( isset( $b['score'] ) ? (float) $b['score'] : 0.0 );
				$a_post_score = isset( $a['post_score'] ) ? (float) $a['post_score'] : ( isset( $a['score'] ) ? (float) $a['score'] : 0.0 );
				return $b_post_score <=> $a_post_score;
			}
		);

		return array_slice( $prefiltered, 0, $max_total );
	}

	/**
	 * Score chunk candidates heuristically when no rerank model is configured.
	 *
	 * Formula: 0.55 * post_score + 0.30 * lexical_overlap + 0.10 * title_boost + 0.05 * position_boost
	 *
	 * @since 2.2.0
	 * @param array  $chunk_candidates Prefiltered candidates.
	 * @param string $query            Effective search query (possibly rewritten).
	 * @return array Candidates sorted by heuristic score descending.
	 */
	private function heuristic_score_chunks( $chunk_candidates, $query ) {
		$query_terms = $this->tokenize_for_overlap( $query );

		foreach ( $chunk_candidates as &$chunk ) {
			$post_score      = (float) ( $chunk['post_score'] ?? 0.0 );
			$lexical_overlap = $this->compute_lexical_overlap( $chunk['content'], $query_terms );
			$title_boost     = $this->compute_title_boost( $chunk['title'], $query_terms );
			$position_boost  = 0 === $chunk['chunk_index'] ? 1.0 : ( $chunk['chunk_index'] <= 2 ? 0.5 : 0.0 );

			$chunk['chunk_score'] = (
				0.55 * $post_score
				+ 0.30 * $lexical_overlap
				+ 0.10 * $title_boost
				+ 0.05 * $position_boost
			);
			$chunk['score']       = $chunk['chunk_score'];
		}
		unset( $chunk );

		usort(
			$chunk_candidates,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return $chunk_candidates;
	}

	/**
	 * Enforce diversity and count limits on the final context chunk set.
	 *
	 * @since 2.2.0
	 * @param array $chunks       Scored candidates, sorted by score descending.
	 * @param int   $max_chunks   Maximum total chunks. Default 6.
	 * @param int   $max_per_post Maximum chunks from a single post. Default 2.
	 * @return array Final selected chunks.
	 */
	private function finalize_context_chunks( $chunks, $max_chunks = 6, $max_per_post = 2 ) {
		$final      = array();
		$post_count = array();

		foreach ( $chunks as $chunk ) {
			if ( count( $final ) >= $max_chunks ) {
				break;
			}

			$pid = $chunk['post_id'];
			if ( ( $post_count[ $pid ] ?? 0 ) >= $max_per_post ) {
				continue;
			}

			$chunk['selection_stage'] = 'final';
			$final[]                  = $chunk;
			$post_count[ $pid ]       = ( $post_count[ $pid ] ?? 0 ) + 1;
		}

		return $final;
	}

	/**
	 * Expand neighbor chunks for top passages that appear incomplete.
	 *
	 * Applied only to the top 2 final chunks. Bounded by a per-request token budget
	 * to prevent noise from indiscriminate expansion.
	 *
	 * @since 2.2.0
	 * @param array $selected_chunks Final selected chunks.
	 * @param array $chunks_by_post  All available chunk rows grouped by post_id.
	 * @return array Selected chunks with any neighbor expansions appended.
	 */
	private function expand_neighbor_chunks_if_needed( $selected_chunks, $chunks_by_post ) {
		$top_n            = 2;
		$token_budget_max = 300;
		$budget           = 0;

		$selected_keys = array();
		foreach ( $selected_chunks as $chunk ) {
			if ( ! isset( $chunk['post_id'], $chunk['chunk_index'] ) ) {
				continue;
			}

			$selected_keys[ $chunk['post_id'] . '_' . $chunk['chunk_index'] ] = true;
		}

		$expanded     = $selected_chunks;
		$expand_limit = min( $top_n, count( $selected_chunks ) );

		for ( $i = 0; $i < $expand_limit; $i++ ) {
			$chunk = $selected_chunks[ $i ];

			if ( ! isset( $chunk['post_id'], $chunk['chunk_index'] ) ) {
				continue;
			}

			if ( ! $this->chunk_appears_incomplete( $chunk['content'] ) ) {
				continue;
			}

			if ( $budget >= $token_budget_max ) {
				break;
			}

			$post_id      = $chunk['post_id'];
			$next_index   = $chunk['chunk_index'] + 1;
			$neighbor_key = $post_id . '_' . $next_index;

			if ( isset( $selected_keys[ $neighbor_key ] ) ) {
				continue;
			}

			$post_chunks = $chunks_by_post[ $post_id ] ?? array();
			$neighbor    = null;
			foreach ( $post_chunks as $raw_chunk ) {
				if ( (int) $raw_chunk['chunk_index'] === $next_index ) {
					$neighbor = $raw_chunk;
					break;
				}
			}

			if ( ! $neighbor ) {
				continue;
			}

			$neighbor_tokens = (int) ( $neighbor['token_count'] ?? 0 );
			if ( $budget + $neighbor_tokens > $token_budget_max ) {
				continue;
			}

			$neighbor_chunk                    = $chunk;
			$neighbor_chunk['chunk_index']     = $next_index;
			$neighbor_chunk['content']         = $neighbor['chunk_text'];
			$neighbor_chunk['excerpt']         = wp_trim_words( $neighbor['chunk_text'], 30 );
			$neighbor_chunk['tokens']          = $neighbor_tokens;
			$neighbor_chunk['selection_stage'] = 'neighbor_expansion';
			$neighbor_chunk['score']           = $chunk['score'] * 0.9;

			$expanded[]                     = $neighbor_chunk;
			$selected_keys[ $neighbor_key ] = true;
			$budget                        += $neighbor_tokens;

			$this->logger->log(
				sprintf( 'RAG Service: Neighbor expansion applied for post %d chunk %d', $post_id, $next_index ),
				'info',
				'rag',
				$this->connection_name
			);
		}

		return $expanded;
	}

	/**
	 * Determine whether a chunk passage appears incomplete.
	 *
	 * @since 2.2.0
	 * @param string $text Chunk text.
	 * @return bool True if the passage appears incomplete.
	 */
	private function chunk_appears_incomplete( $text ) {
		$text = trim( wp_strip_all_tags( $text ) );

		if ( strlen( $text ) < 100 ) {
			return true;
		}

		if ( ! preg_match( '/[.!?]\s*$/', $text ) ) {
			return true;
		}

		if ( preg_match( '/^[a-z]/', $text ) ) {
			return true;
		}

		if ( preg_match( '/\b(the following|as follows|listed below|see below)\b/i', $text ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Tokenize text into lowercase terms for lexical overlap scoring.
	 *
	 * @since 2.2.0
	 * @param string $text Input text.
	 * @return array Lowercase terms longer than 2 characters.
	 */
	private function tokenize_for_overlap( $text ) {
		$text  = strtolower( wp_strip_all_tags( $text ) );
		$words = preg_split( '/\W+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return array_values(
			array_filter(
				$words,
				function ( $w ) {
					return strlen( $w ) > 2;
				}
			)
		);
	}

	/**
	 * Compute normalized lexical overlap between chunk content and query terms.
	 *
	 * @since 2.2.0
	 * @param string $content     Chunk content.
	 * @param array  $query_terms Terms from tokenize_for_overlap().
	 * @return float Overlap score 0.0-1.0.
	 */
	private function compute_lexical_overlap( $content, $query_terms ) {
		if ( empty( $query_terms ) ) {
			return 0.0;
		}

		$content_lower = strtolower( wp_strip_all_tags( $content ) );
		$matched       = 0;

		foreach ( $query_terms as $term ) {
			if ( false !== strpos( $content_lower, $term ) ) {
				++$matched;
			}
		}

		return min( 1.0, $matched / count( $query_terms ) );
	}

	/**
	 * Compute title-to-query alignment boost.
	 *
	 * @since 2.2.0
	 * @param string $title       Post title.
	 * @param array  $query_terms Terms from tokenize_for_overlap().
	 * @return float Boost score 0.0-1.0.
	 */
	private function compute_title_boost( $title, $query_terms ) {
		if ( empty( $query_terms ) ) {
			return 0.0;
		}

		$title_lower = strtolower( $title );
		$matched     = 0;

		foreach ( $query_terms as $term ) {
			if ( false !== strpos( $title_lower, $term ) ) {
				++$matched;
			}
		}

		return min( 1.0, $matched / count( $query_terms ) );
	}


	/**
	 * Get the last assistant message from conversation history.
	 *
	 * @since 1.0.0
	 * @param array $messages Conversation history.
	 * @return string|null Last assistant message content or null if not found.
	 */
	private function get_last_assistant_message( $messages ) {
		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return null;
		}

		// Iterate backwards to find the last assistant message.
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			if ( isset( $messages[ $i ]['role'] ) && 'assistant' === $messages[ $i ]['role'] ) {
				return $messages[ $i ]['content'] ?? null;
			}
		}

		return null;
	}

	// =========================================================================
	// Compare Tool Methods (compare_content + coverage gate)
	// =========================================================================

	/**
	 * Extract shared context terms from a compare query by removing entity names and compare keywords.
	 *
	 * Used to enrich per-entity search queries with shared domain context
	 * (e.g., "programs in Ontario" when comparing two specific programs).
	 *
	 * @since 1.0.0
	 * @param string   $query    Original query.
	 * @param string[] $entities Entity names to strip.
	 * @return string Cleaned context terms, or empty string.
	 */
	private function extract_context_terms( $query, $entities ) {
		$context = $query;

		foreach ( $entities as $entity ) {
			$context = str_ireplace( $entity, '', $context );
		}

		$keywords_to_strip = array(
			'compare',
			'difference between',
			'differences between',
			'versus',
			' vs ',
			' vs.',
			'between',
			' and ',
			'can',
			'could',
			'would',
			'should',
			'how does',
			'how do',
			'can you tell me',
			'tell me',
			'please',
			'differ',
			'what is the',
			'what are the',
			"what's the",
		);

		foreach ( $keywords_to_strip as $kw ) {
			$context = str_ireplace( $kw, ' ', $context );
		}

		// Remove stopword residue left from rewritten compare phrasing.
		$context = preg_replace( '/\b(the|a|an|to|for|of|in|on|at|with|about|can|could|would|should|is|are|do|does|did|anf|block|blocks)\b/i', ' ', $context );

		// Collapse repeated tokens like "the the" if any remain.
		$context = preg_replace( '/\b(\w+)\s+\1\b/i', '$1', $context );

		$context = trim( preg_replace( '/\s+/', ' ', $context ), ' ?,.!' );

		// Avoid noisy one-word leftovers that harm compare retrieval quality.
		if ( strlen( $context ) < 4 ) {
			return '';
		}

		return $context;
	}

				/**
				 * Normalize post types for compare retrieval.
				 *
				 * Compare requests can come from frontend payloads where post_types is
				 * present but empty. In that case, default to synced post types.
				 *
				 * @since 1.0.0
				 * @param array $post_types Requested post types.
				 * @return array Normalized post types.
				 */
	private function normalize_compare_post_types( $post_types ) {
		if ( empty( $post_types ) || ! is_array( $post_types ) ) {
			return $this->get_synced_post_types();
		}

		return $post_types;
	}

	/**
	 * Build a lightweight query plan for multi-query retrieval.
	 *
	 * Phase 3 planner is deterministic and low-latency by design.
	 * Planner only activates when an agentic model is configured.
	 *
	 * @since 1.0.0
	 * @param string $query Original user query.
	 * @param string $search_query Search query after rewrites/filters.
	 * @param array  $tool_selection Tool selection output.
	 * @param array  $options Request options.
	 * @return array Planner metadata and subqueries.
	 */
	private function build_lightweight_query_plan( $query, $search_query, $tool_selection, $options ) {
		$agentic_active = ! empty( $options['rewrite_model'] );
		$intent         = ( isset( $tool_selection['tool'] ) && 'compare_content' === $tool_selection['tool'] ) ? 'compare' : 'single';

		$entities = array();
		if ( ! empty( $tool_selection['entities'] ) && is_array( $tool_selection['entities'] ) ) {
			$entities = array_values( array_filter( array_map( 'trim', $tool_selection['entities'] ) ) );
		}

		$aspects = array();
		if ( ! empty( $tool_selection['aspects'] ) && is_array( $tool_selection['aspects'] ) ) {
			$aspects = array_values( array_filter( array_map( 'trim', $tool_selection['aspects'] ) ) );
		}

		if ( empty( $aspects ) && $agentic_active ) {
			$aspects = preg_split( '/\s+(?:and|also|plus)\s+/i', (string) $search_query );
			$aspects = is_array( $aspects ) ? $aspects : array();
			$aspects = array_values( array_filter( array_map( 'trim', $aspects ) ) );
			if ( count( $aspects ) < 2 ) {
				$aspects = array();
			}
		}

		$max_subqueries = (int) apply_filters( 'gg_data_rag_planner_max_subqueries', 3, $this->connection_name, $query, $search_query );
		$max_subqueries = max( 1, $max_subqueries );

		$subqueries = array( trim( (string) $search_query ) );
		if ( $agentic_active && ! empty( $aspects ) ) {
			foreach ( $aspects as $aspect ) {
				$subquery = trim( $search_query . ' ' . $aspect );
				if ( ! empty( $subquery ) ) {
					$subqueries[] = $subquery;
				}
			}
		}

		$subqueries = array_values( array_unique( array_filter( $subqueries ) ) );
		$subqueries = array_slice( $subqueries, 0, $max_subqueries );

		return array(
			'active'          => $agentic_active,
			'intent'          => $intent,
			'entities'        => $entities,
			'aspects'         => $aspects,
			'subqueries'      => $subqueries,
			'max_subqueries'  => $max_subqueries,
			'planner_source'  => count( $subqueries ) > 1 ? 'deterministic' : 'single_query',
		);
	}

	/**
	 * Classify the query for retrieval-policy defaults.
	 *
	 * @since 1.0.0
	 * @param string $query   Original user query.
	 * @param string $intent  Governance intent.
	 * @param array  $options Request options.
	 * @return string Query class.
	 */
	private function classify_retrieval_query_class( $query, $intent, $options = array() ) {
		$normalized_intent = in_array( $intent, array( 'single', 'compare' ), true ) ? $intent : 'single';
		$normalized_query  = trim( preg_replace( '/\s+/', ' ', strtolower( (string) $query ) ) );

		if ( 'compare' === $normalized_intent ) {
			$query_class = 'compare';
		} elseif ( '' === $normalized_query ) {
			$query_class = 'general';
		} elseif ( preg_match( '/["\'][^"\']+["\']/', $normalized_query ) || preg_match( '/\b(?:title|exact|named|called|slug)\b/', $normalized_query ) ) {
			$query_class = 'exact_match';
		} elseif ( preg_match( '/^(?:who|what|when|where|why|how)\b/', $normalized_query ) || preg_match( '/\b(?:explain|describe|difference|benefits|process|steps|overview|policy|requirements?)\b/', $normalized_query ) ) {
			$query_class = 'conceptual';
		} else {
			$token_count = str_word_count( preg_replace( '/[^a-z0-9\s-]+/i', ' ', $normalized_query ) );
			if ( $token_count > 0 && $token_count <= 4 && ! preg_match( '/\b(?:can|could|should|would|is|are|do|does|did|how|why|what|when|where|which)\b/', $normalized_query ) ) {
				$query_class = 'entity_lookup';
			} else {
				$query_class = 'general';
			}
		}

		$query_class = (string) apply_filters(
			'gg_data_rag_policy_query_class',
			$query_class,
			$query,
			$normalized_intent,
			$options,
			$this->connection_name
		);

		if ( ! in_array( $query_class, array( 'compare', 'exact_match', 'entity_lookup', 'conceptual', 'general' ), true ) ) {
			$query_class = 'general';
		}

		return $query_class;
	}

				/**
				 * Resolve the default retrieval mode for a classified query.
				 *
				 * @since 1.0.0
				 * @param string $query_class Query class.
				 * @return string Default requested mode.
				 */
	private function get_default_retrieval_mode_for_query_class( $query_class ) {
		switch ( $query_class ) {
			case 'exact_match':
			case 'entity_lookup':
				return 'lexical';
			case 'conceptual':
				return 'semantic';
			case 'compare':
			case 'general':
			default:
				return 'hybrid';
		}
	}

	/**
	 * Resolve formal hybrid retrieval policy for the current intent.
	 *
	 * @since 1.0.0
	 * @param string $intent Governance intent.
	 * @param array  $options Request options.
	 * @param string $query Original user query.
	 * @return array Retrieval policy.
	 */
	private function resolve_hybrid_retrieval_policy( $intent, $options = array(), $query = '' ) {
		$normalized_intent = in_array( $intent, array( 'single', 'compare' ), true ) ? $intent : 'single';
		$embedding_active  = ! empty( $this->embedding_model_key );
		$lexical_active    = (bool) apply_filters( 'gg_data_rag_policy_lexical_active', true, $this->connection_name, $normalized_intent, $options );
		$query_class       = $this->classify_retrieval_query_class( $query, $normalized_intent, $options );
		$default_mode      = $this->get_default_retrieval_mode_for_query_class( $query_class );

		// Short block lookups (e.g., "columns block") should not disable vector retrieval.
		if ( 'lexical' === $default_mode && $this->is_block_lookup_query( $query ) ) {
			$default_mode = 'hybrid';
		}

		$requested_mode = (string) apply_filters(
			'gg_data_rag_policy_retrieval_mode',
			$default_mode,
			$this->connection_name,
			$normalized_intent,
			$options,
			$query,
			$query_class
		);

		$requested_mode = strtolower( $requested_mode );
		if ( ! in_array( $requested_mode, array( 'auto', 'hybrid', 'semantic', 'lexical' ), true ) ) {
			$requested_mode = $default_mode;
		}

		$resolved_mode = 'hybrid';
		if ( 'semantic' === $requested_mode ) {
			$resolved_mode = $embedding_active ? 'semantic' : 'lexical';
		} elseif ( 'lexical' === $requested_mode ) {
			$resolved_mode = $lexical_active ? 'lexical' : 'semantic';
		} elseif ( $embedding_active && $lexical_active ) {
				$resolved_mode = 'hybrid';
		} elseif ( $embedding_active ) {
			$resolved_mode = 'semantic';
		} else {
			$resolved_mode = 'lexical';
		}

		$policy = array(
			'id'                     => 'retrieval_' . $normalized_intent . '_' . $resolved_mode,
			'intent'                 => $normalized_intent,
			'query_class'            => $query_class,
			'default_mode'           => $default_mode,
			'requested_mode'         => $requested_mode,
			'mode_source'            => $requested_mode === $default_mode ? 'query_class_default' : 'filter_override',
			'mode'                   => $resolved_mode,
			'embedding_model_active' => (bool) $embedding_active,
			'lexical_active'         => (bool) $lexical_active,
			'use_semantic'           => 'semantic' === $resolved_mode,
			'disable_vector'         => 'lexical' === $resolved_mode,
		);

		return apply_filters(
			'gg_data_rag_retrieval_policy',
			$policy,
			$normalized_intent,
			$query,
			$options,
			$this->connection_name
		);
	}

	/**
	 * Apply retrieval policy mode to retrieve_chunks options.
	 *
	 * @since 1.0.0
	 * @param array $options Base retrieval options.
	 * @param array $policy Retrieval policy.
	 * @return array Retrieval options with policy-applied mode flags.
	 */
	private function apply_retrieval_policy_to_options( $options, $policy ) {
		$mode = $policy['mode'] ?? 'hybrid';

		if ( 'semantic' === $mode ) {
			$options['use_semantic']   = true;
			$options['disable_vector'] = false;
		} elseif ( 'lexical' === $mode ) {
			$options['use_semantic']   = false;
			$options['disable_vector'] = true;
		} else {
			$options['use_semantic']   = false;
			$options['disable_vector'] = false;
		}

		return $options;
	}

	/**
	 * Summarize retrieval contribution by lexical/semantic match types.
	 *
	 * @since 1.0.0
	 * @param array $candidates Post candidates.
	 * @return array Contribution summary.
	 */
	private function build_retrieval_mode_contribution( $candidates ) {
		$contribution = array(
			'lexical'  => 0,
			'semantic' => 0,
			'unknown'  => 0,
			'total'    => 0,
		);

		foreach ( $candidates as $candidate ) {
			$match_type = strtolower( (string) ( $candidate['match_type'] ?? '' ) );
			if ( false !== strpos( $match_type, 'vec' ) || false !== strpos( $match_type, 'semantic' ) ) {
				++$contribution['semantic'];
			} elseif ( false !== strpos( $match_type, 'fts' ) || false !== strpos( $match_type, 'trigram' ) || false !== strpos( $match_type, 'tg' ) || false !== strpos( $match_type, 'fallback' ) || false !== strpos( $match_type, 'lex' ) ) {
				++$contribution['lexical'];
			} else {
				++$contribution['unknown'];
			}
			++$contribution['total'];
		}

		return $contribution;
	}

	/**
	 * Resolve formal rerank policy for an intent and candidate volume.
	 *
	 * @since 1.0.0
	 * @param string $intent Intent class.
	 * @param int    $candidate_count Candidate count prior to rerank.
	 * @param array  $options Request options.
	 * @param string $query Original user query.
	 * @return array Rerank policy.
	 */
	private function resolve_formal_rerank_policy( $intent, $candidate_count, $options = array(), $query = '' ) {
		$normalized_intent            = in_array( $intent, array( 'single', 'compare' ), true ) ? $intent : 'single';
		$model_active                 = ! empty( $options['rerank_model_id'] );
		$intent_defaults              = array(
			'single'  => array(
				'min_candidates'       => 3,
				'acceptance_threshold' => 0.35,
			),
			'compare' => array(
				'min_candidates'       => 5,
				'acceptance_threshold' => 0.35,
			),
		);
		$default_min_candidates       = (int) ( $intent_defaults[ $normalized_intent ]['min_candidates'] ?? 3 );
		$default_acceptance_threshold = (float) ( $intent_defaults[ $normalized_intent ]['acceptance_threshold'] ?? 0.35 );
		$min_candidates               = (int) apply_filters(
			'gg_data_rag_rerank_min_candidates',
			$default_min_candidates,
			$this->connection_name,
			$normalized_intent,
			$options
		);
		$min_candidates               = max( 1, $min_candidates );

		$acceptance_threshold = (float) apply_filters(
			'gg_data_rag_rerank_acceptance_threshold',
			$default_acceptance_threshold,
			$this->connection_name,
			$normalized_intent,
			$options
		);

		if ( 'compare' === $normalized_intent ) {
			$acceptance_threshold = (float) apply_filters(
				'gg_data_rag_compare_rerank_acceptance_threshold',
				$acceptance_threshold,
				$this->connection_name,
				$options,
				$query
			);
		}

		$should_run = $model_active && ( (int) $candidate_count >= $min_candidates );

		$skip_reason = '';
		if ( ! $model_active ) {
			$skip_reason = 'no_rerank_model';
		} elseif ( (int) $candidate_count < $min_candidates ) {
			$skip_reason = 'below_candidate_min';
		}

		$policy = array(
			'id'                           => 'rerank_' . $normalized_intent . '_default',
			'intent'                       => $normalized_intent,
			'default_min_candidates'       => $default_min_candidates,
			'default_acceptance_threshold' => $default_acceptance_threshold,
			'rerank_model_active'          => $model_active,
			'should_run'                   => $should_run,
			'min_candidates'               => $min_candidates,
			'candidate_count'              => (int) $candidate_count,
			'acceptance_threshold'         => $acceptance_threshold,
			'skip_reason'                  => $skip_reason,
		);

		return apply_filters(
			'gg_data_rag_rerank_policy',
			$policy,
			$normalized_intent,
			$query,
			$candidate_count,
			$options,
			$this->connection_name
		);
	}

	/**
	 * Apply candidate acceptance threshold based on rerank policy state.
	 *
	 * @since 1.0.0
	 * @param array  $candidates Candidate chunks.
	 * @param string $intent Intent class.
	 * @param array  $rerank_policy Resolved rerank policy.
	 * @param bool   $rerank_applied Whether rerank was actually executed.
	 * @return array {
	 *   @type array $candidates Accepted candidates.
	 *   @type array $metrics Acceptance metrics.
	 * }
	 */
	private function apply_candidate_acceptance_policy( $candidates, $intent, $rerank_policy, $rerank_applied ) {
		$profile         = $this->get_policy_threshold_profile( $intent );
		$threshold       = $rerank_applied
			? (float) ( $rerank_policy['acceptance_threshold'] ?? 0.35 )
			: (float) ( $profile['min_relevance'] ?? 0.0 );
		$acceptance_mode = $rerank_applied ? 'post_rerank' : 'first_stage';

		$accepted = array();
		foreach ( $candidates as $candidate ) {
					$score = isset( $candidate['score'] ) ? (float) $candidate['score'] : 0.0;
			if ( $score >= $threshold ) {
				$accepted[] = $candidate;
			}
		}

		// Compare fallback-top-k is intentionally disabled by default to keep
		// acceptance deterministic and authoritative. Enable only via filter
		// for explicit compatibility needs.
		$compare_fallback_enabled = (bool) apply_filters(
			'gg_data_rag_compare_acceptance_fallback_enabled',
			false,
			$this->connection_name,
			$rerank_applied,
			$rerank_policy
		);

		if ( 'compare' === $intent && $compare_fallback_enabled && empty( $accepted ) && ! empty( $candidates ) ) {
			$fallback_limit  = (int) apply_filters( 'gg_data_rag_compare_acceptance_fallback_top_k', 6, $this->connection_name );
			$fallback_limit  = max( 1, $fallback_limit );
			$accepted        = array_slice( $candidates, 0, $fallback_limit );
			$acceptance_mode = $rerank_applied ? 'post_rerank_fallback_topk' : 'first_stage_fallback_topk';
		}

		if ( 'compare' === $intent && empty( $accepted ) && ! empty( $candidates ) && $rerank_applied ) {
			$soft_floor = (float) apply_filters(
				'gg_data_rag_compare_acceptance_soft_floor_threshold',
				0.2,
				$this->connection_name,
				$threshold,
				$rerank_policy
			);

			$soft_floor    = min( $threshold, max( 0.0, $soft_floor ) );
			$soft_accepted = array();

			foreach ( $candidates as $candidate ) {
				$score = isset( $candidate['score'] ) ? (float) $candidate['score'] : 0.0;
				if ( $score >= $soft_floor ) {
					$soft_accepted[] = $candidate;
				}
			}

			if ( ! empty( $soft_accepted ) ) {
				$accepted        = $soft_accepted;
				$acceptance_mode = 'post_rerank_soft_floor';
			}
		}

		return array(
			'candidates' => $accepted,
			'metrics'    => array(
				'acceptance_threshold' => $threshold,
				'accepted_count'       => count( $accepted ),
				'input_count'          => count( $candidates ),
				'acceptance_mode'      => $acceptance_mode,
			),
		);
	}

	/**
	 * Normalize a retrieval query by removing WordPress-specific noise terms that
	 * hurt FTS matching without improving semantic recall.
	 *
	 * "core image block"    → "image block"
	 * "core/image block"    → "image block"
	 * "the core list block" → "list block"
	 *
	 * The word "core" in "core <name> block" refers to the WordPress core namespace
	 * (e.g. core/columns). Theme changelogs and release notes use this prefix
	 * extensively, so including it in FTS queries inflates their rank over the
	 * dedicated block documentation articles.
	 *
	 * @since 1.0.0
	 * @param string $query The search query to normalize.
	 * @return string Normalized query.
	 */
	private function normalize_retrieval_query( $query ) {
		$normalized = trim( (string) $query );

		// "core <name> block" or "core/<name> block" → "<name> block".
		$normalized = preg_replace( '/\bcore[\/ ]+(\S+\s+block)\b/i', '$1', $normalized );

		// "the core <name> block" → "<name> block" (common in HelpHub prose).
		$normalized = preg_replace( '/\bthe\s+core\s+(\S+\s+block)\b/i', '$1', $normalized );

		$normalized = trim( $normalized );

		return '' !== $normalized ? $normalized : $query;
	}

	/**
	 * Determine whether a query lacks a specific entity anchor and is too vague for retrieval.
	 *
	 * Queries that match are redirected to respond_directly so the LLM can ask a clarifying
	 * question rather than running retrieval against vague terms and returning a speculative answer.
	 *
	 * @since 1.0.0
	 * @param string $query User query.
	 * @return bool True when the query is too under-specified for retrieval.
	 */
	private function is_under_specified_query( $query ) {
		$normalized = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $query ) ) );

		if ( '' === $normalized ) {
			return false;
		}

		$ambiguous_patterns = array(
			// "tell me about things/stuff/everything" — catch-all meta queries with no entity.
			'/^tell me (about|everything about|all about) (things?|stuff|everything|all|content|information)\.?$/',
			// "tell me about blocks" — valid topic but no specific block named; needs narrowing.
			'/^tell me (about|everything about|all about) (word ?press )?blocks?\.?$/',
			// "what styles/settings/options should I use" — missing context anchor.
			'/^what (styles?|settings?|options?|things?|items?) should (i|we|you) use\??\.*$/',
			// "show/give/tell me everything/all/stuff" — open-ended with no topic.
			'/^(show|give|tell) me (everything|all|stuff|things|content|information|more)\.?$/',
		);

		foreach ( $ambiguous_patterns as $pattern ) {
			if ( preg_match( $pattern, $normalized ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a query is appropriate for direct non-retrieval response.
	 *
	 * @since 1.0.0
	 * @param string $query User query.
	 * @return bool True when direct response is appropriate.
	 */
	private function should_allow_direct_response( $query ) {
		$normalized = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $query ) ) );

		if ( '' === $normalized ) {
			return true;
		}

		$direct_patterns = array(
			'/^(hi|hello|hey|yo|good morning|good afternoon|good evening)\b/',
			'/^(thanks|thank you|thx)\b/',
			'/\b(what can you do|who are you|how can you help)\b/',
			'/\b(what time is it|what date is it|today\'s date|current time)\b/',
			'/\b(help me|need help)\b/',
		);

		foreach ( $direct_patterns as $pattern ) {
			if ( preg_match( $pattern, $normalized ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a filtered abstain message that is governed by retrieval outcome.
	 *
	 * @since 1.0.0
	 * @param string $default_message Default abstain message.
	 * @param string $query Original user query.
	 * @param array  $policy Policy metadata.
	 * @param array  $retrieval Retrieval metadata.
	 * @param array  $options Request options.
	 * @return string Abstain message.
	 */
	private function build_abstain_message( $default_message, $query, $policy, $retrieval, $options = array() ) {
		return (string) apply_filters(
			'gg_data_rag_abstain_message',
			$default_message,
			$query,
			$policy,
			$retrieval,
			$options,
			$this->connection_name
		);
	}

	/**
	 * Emit governance decision action for auditability.
	 *
	 * @since 1.0.0
	 * @param string $query Original user query.
	 * @param array  $policy Policy metadata.
	 * @param array  $retrieval Retrieval metadata.
	 * @param array  $options Request options.
	 */
	private function emit_governance_decision( $query, $policy, $retrieval, $options = array() ) {
		do_action(
			'gg_data_rag_governance_decision',
			$query,
			$policy,
			$retrieval,
			$options,
			$this->connection_name
		);
	}

	/**
	 * Retrieve post candidates from planner subqueries.
	 *
	 * @since 1.0.0
	 * @param array $query_plan Query plan from build_lightweight_query_plan().
	 * @param array $options Request options.
	 * @param array $retrieval_policy Retrieval policy metadata.
	 * @return array {
	 *   @type array $candidates Post candidates.
	 *   @type array $metrics Planner retrieval metrics.
	 * }
	 */
	private function retrieve_post_candidates_from_plan( $query_plan, $options, $retrieval_policy = array() ) {
		$post_types          = $options['post_types'] ?? $this->get_synced_post_types();
		$final_post_limit    = (int) apply_filters( 'gg_data_rag_planner_final_post_limit', 6, $this->connection_name, $query_plan, $options );
		$subquery_post_limit = (int) apply_filters( 'gg_data_rag_planner_subquery_limit', 4, $this->connection_name, $query_plan, $options );
		$final_post_limit    = max( 1, $final_post_limit );
		$subquery_post_limit = max( 1, $subquery_post_limit );

		$subqueries = isset( $query_plan['subqueries'] ) && is_array( $query_plan['subqueries'] )
			? $query_plan['subqueries']
			: array();

		if ( empty( $subqueries ) ) {
			return array(
				'candidates' => array(),
				'metrics'    => array(
					'subqueries_executed'        => 0,
					'candidate_counts'           => array(),
					'unique_candidates_total'    => 0,
					'duplicate_candidates_total' => 0,
				),
			);
		}

		if ( empty( $query_plan['active'] ) || 1 === count( $subqueries ) ) {
			$retrieve_options = $this->apply_retrieval_policy_to_options(
				array(
					'num_results'     => $final_post_limit,
					'post_types'      => $post_types,
					'metadata_filter' => isset( $options['metadata_filter'] ) && is_array( $options['metadata_filter'] ) ? $options['metadata_filter'] : array(),
				),
				$retrieval_policy
			);

			$candidates = $this->retrieve_chunks(
				$subqueries[0],
				$retrieve_options
			);

			if ( is_wp_error( $candidates ) ) {
				$candidates = array();
			}

			return array(
				'candidates' => $candidates,
				'metrics'    => array(
					'subqueries_executed'        => 1,
					'candidate_counts'           => array(
						array(
							'subquery'           => $subqueries[0],
							'count'              => count( $candidates ),
							'unique_contributed' => count( $candidates ),
							'duplicate_count'    => 0,
						),
					),
					'unique_candidates_total'    => count( $candidates ),
					'duplicate_candidates_total' => 0,
				),
			);
		}

		$merged           = array();
		$candidate_counts = array();
		$seen_post_ids    = array();
		$duplicate_total  = 0;

		foreach ( $subqueries as $subquery ) {
			$retrieve_options = $this->apply_retrieval_policy_to_options(
				array(
					'num_results'     => $subquery_post_limit,
					'post_types'      => $post_types,
					'metadata_filter' => isset( $options['metadata_filter'] ) && is_array( $options['metadata_filter'] ) ? $options['metadata_filter'] : array(),
				),
				$retrieval_policy
			);

			$candidates = $this->retrieve_chunks(
				$subquery,
				$retrieve_options
			);

			if ( is_wp_error( $candidates ) ) {
				$candidates = array();
			}

			$unique_contributed = 0;
			$duplicate_count    = 0;

			foreach ( $candidates as $candidate ) {
				$post_id = isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : 0;
				if ( ! $post_id ) {
					continue;
				}

				if ( isset( $seen_post_ids[ $post_id ] ) ) {
					++$duplicate_count;
				} else {
					$seen_post_ids[ $post_id ] = true;
					++$unique_contributed;
				}

				if ( ! isset( $merged[ $post_id ] ) ) {
					$merged[ $post_id ] = $candidate;
					continue;
				}

				$current_score = isset( $merged[ $post_id ]['score'] ) ? (float) $merged[ $post_id ]['score'] : 0.0;
				$new_score     = isset( $candidate['score'] ) ? (float) $candidate['score'] : 0.0;

				if ( $new_score > $current_score ) {
					$merged[ $post_id ] = array_merge( $merged[ $post_id ], $candidate );
				}
			}

			$duplicate_total   += $duplicate_count;
			$candidate_counts[] = array(
				'subquery'           => $subquery,
				'count'              => count( $candidates ),
				'unique_contributed' => $unique_contributed,
				'duplicate_count'    => $duplicate_count,
			);
		}

		$merged = array_values( $merged );

		usort(
			$merged,
			function ( $a, $b ) {
				$score_a = isset( $a['score'] ) ? (float) $a['score'] : 0.0;
				$score_b = isset( $b['score'] ) ? (float) $b['score'] : 0.0;

				if ( $score_a === $score_b ) {
					return 0;
				}

				return ( $score_a > $score_b ) ? -1 : 1;
			}
		);

		return array(
			'candidates' => array_slice( $merged, 0, $final_post_limit ),
			'metrics'    => array(
				'subqueries_executed'        => count( $subqueries ),
				'candidate_counts'           => $candidate_counts,
				'unique_candidates_total'    => count( $seen_post_ids ),
				'duplicate_candidates_total' => $duplicate_total,
			),
		);
	}

	/**
	 * Get threshold profile metadata for a governance intent.
	 *
	 * @since 1.0.0
	 * @param string $intent Governance intent: compare|single.
	 * @param string $query Original user query.
	 * @param array  $options Request options.
	 * @return array Threshold profile data.
	 */
	private function get_policy_threshold_profile( $intent, $query = '', $options = array() ) {
		if ( 'compare' === $intent ) {
			$threshold = (float) apply_filters( 'gg_data_rag_policy_compare_threshold', 0.1, $this->connection_name );

			$profile = array(
				'id'            => 'compare_default',
				'min_relevance' => $threshold,
			);

			return apply_filters(
				'gg_data_rag_threshold_profile',
				$profile,
				'compare',
				$query,
				$options,
				$this->connection_name
			);
		}

		$threshold = (float) apply_filters( 'gg_data_rag_policy_single_threshold', 0.1, $this->connection_name );

		$profile = array(
			'id'            => 'single_default',
			'min_relevance' => $threshold,
		);

		return apply_filters(
			'gg_data_rag_threshold_profile',
			$profile,
			'single',
			$query,
			$options,
			$this->connection_name
		);
	}

				/**
				 * Count chunks that meet the relevance threshold.
				 *
				 * @since 1.0.0
				 * @param array $chunks Chunks to evaluate.
				 * @param float $threshold Minimum relevance threshold.
				 * @return int Number of qualifying chunks.
				 */
	private function count_qualified_chunks( $chunks, $threshold ) {
		$qualified = 0;

		foreach ( $chunks as $chunk ) {
			$score = isset( $chunk['score'] ) ? (float) $chunk['score'] : 1.0;
			if ( $score >= $threshold ) {
				++$qualified;
			}
		}

		return $qualified;
	}

				/**
				 * Build standardized retrieval parity metadata.
				 *
				 * @since 1.0.0
				 * @param int   $raw_count Raw retrieved evidence count.
				 * @param int   $qualified_count Qualified evidence count.
				 * @param int   $selected_count Final selected evidence count.
				 * @param array $extra Optional extra fields.
				 * @return array Retrieval parity metadata.
				 */
	private function build_retrieval_parity( $raw_count, $qualified_count, $selected_count, $extra = array() ) {
		$base = array(
			'raw_count'       => (int) $raw_count,
			'qualified_count' => (int) $qualified_count,
			'selected_count'  => (int) $selected_count,
		);

		return array_merge( $base, $extra );
	}

				/**
				 * Build standardized policy parity metadata.
				 *
				 * @since 1.0.0
				 * @param string $intent Intent class.
				 * @param string $decision Decision outcome.
				 * @param string $reason_code Machine-readable reason code.
				 * @param string $threshold_profile Threshold profile identifier.
				 * @param array  $extra Optional extra policy fields.
				 * @return array Policy parity metadata.
				 */
	private function build_policy_parity( $intent, $decision, $reason_code, $threshold_profile, $extra = array() ) {
		$base = array(
			'intent'            => $intent,
			'decision'          => $decision,
			'reason_code'       => $reason_code,
			'threshold_profile' => $threshold_profile,
		);

		return array_merge( $base, $extra );
	}

				/**
				 * Merge chunk arrays and deduplicate by post_id + chunk_index composite key.
				 *
				 * @since 1.0.0
				 * @param array $chunks Chunks to deduplicate.
				 * @return array Deduplicated chunks preserving original order.
				 */
	private function merge_deduplicate_chunks( $chunks ) {
		$seen    = array();
		$deduped = array();

		foreach ( $chunks as $chunk ) {
			$post_id     = $chunk['post_id'] ?? '';
			$chunk_index = $chunk['chunk_index'] ?? $chunk['id'] ?? '';
			$key         = $post_id . '_' . $chunk_index;

			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$deduped[]    = $chunk;
			}
		}

		return $deduped;
	}

				/**
				 * Build per-slot evidence summary for compare telemetry.
				 *
				 * @since 1.0.0
				 * @param array $chunks_per_slot Coverage map from slot => chunk list.
				 * @return array Slot evidence summary with counts and post IDs.
				 */
	private function summarize_slot_evidence( $chunks_per_slot ) {
		$summary = array();

		foreach ( $chunks_per_slot as $slot_name => $slot_chunks ) {
			$post_ids = array();

			foreach ( $slot_chunks as $chunk ) {
				$post_ids[] = isset( $chunk['post_id'] ) ? (int) $chunk['post_id'] : 0;
			}

			$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

			$summary[ $slot_name ] = array(
				'chunk_count' => count( $slot_chunks ),
				'post_ids'    => $post_ids,
			);
		}

		return $summary;
	}

				/**
				 * Recover compare candidates from ranked chunks when strict acceptance drops all.
				 *
				 * Uses coverage slot matching with a relaxed threshold and keeps the top
				 * ranked chunk per covered entity slot to preserve deterministic one-sided
				 * partial behavior without enabling broad top-k fallback.
				 *
				 * @since 1.0.0
				 * @param array $ranked_chunks Ranked chunk candidates.
				 * @param array $entities Compared entities.
				 * @return array Recovered candidate chunks.
				 */
	private function recover_compare_slot_candidates( $ranked_chunks, $entities ) {
		if ( empty( $ranked_chunks ) || empty( $entities ) ) {
			return array();
		}

		$coverage_gate = new GG_Data_Coverage_Gate( 0.0 );
		$coverage      = $coverage_gate->evaluate( $ranked_chunks, 'compare', $this->build_compare_slots( $entities ) );
		$recovered     = array();

		foreach ( $coverage['covered_slots'] ?? array() as $slot_name ) {
			$slot_chunks = $coverage['chunks_per_slot'][ $slot_name ] ?? array();
			if ( empty( $slot_chunks ) ) {
				continue;
			}

			$slot_chunks = $this->sort_chunks_by_score_desc( $slot_chunks );
			$recovered[] = $slot_chunks[0];
		}

		$recovered = $this->merge_deduplicate_chunks( $recovered );
		$max_keep  = (int) apply_filters( 'gg_data_rag_compare_slot_recovery_max_chunks', 4, $this->connection_name );
		$max_keep  = max( 1, $max_keep );

		return array_slice( $recovered, 0, $max_keep );
	}

				/**
				 * Determine whether a query is likely a specific block lookup.
				 *
				 * @since 1.0.0
				 * @param string $query User query.
				 * @return bool True when query resembles a block lookup.
				 */
	private function is_block_lookup_query( $query ) {
		$normalized = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $query ) ) );

		if ( '' === $normalized ) {
			return false;
		}

		if ( preg_match( '/\bcore\/[a-z0-9\-_]+\b/u', $normalized ) ) {
			return true;
		}

		return (bool) preg_match( '/\b[a-z0-9\-_]+\s+blocks?\b/u', $normalized );
	}

				/**
				 * Recover block-related candidates for single-intent queries.
				 *
				 * Only applies when strict post-rerank acceptance removed all candidates.
				 *
				 * @since 1.0.0
				 * @param array  $ranked_chunks Ranked chunk candidates.
				 * @param string $query Original user query.
				 * @return array Recovered chunk candidates.
				 */
	private function recover_single_block_lookup_candidates( $ranked_chunks, $query ) {
		if ( empty( $ranked_chunks ) || ! $this->is_block_lookup_query( $query ) ) {
			return array();
		}

		$normalized = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $query ) ) );
		$tokens     = preg_split( '/\s+/', $normalized );
		$stop_words = array( 'core', 'block', 'blocks', 'the', 'a', 'an', 'about', 'tell', 'me', 'what', 'is' );

		$meaningful_tokens = array_values(
			array_filter(
				$tokens,
				function ( $token ) use ( $stop_words ) {
					$token = trim( (string) $token );
					if ( strlen( $token ) < 3 ) {
						return false;
					}

					return ! in_array( $token, $stop_words, true );
				}
			)
		);

		$recovered = array();

		foreach ( $ranked_chunks as $chunk ) {
			$title    = strtolower( (string) ( $chunk['title'] ?? '' ) );
			$content  = strtolower( wp_strip_all_tags( (string) ( $chunk['content'] ?? '' ) ) );
			$haystack = $title . ' ' . $content;

			if ( false === strpos( $haystack, 'block' ) ) {
				continue;
			}

			$token_match = empty( $meaningful_tokens );
			foreach ( $meaningful_tokens as $token ) {
				if ( preg_match( '/\\b' . preg_quote( $token, '/' ) . '\\b/u', $haystack ) ) {
					$token_match = true;
					break;
				}
			}

			if ( $token_match ) {
				$recovered[] = $chunk;
			}
		}

		if ( empty( $recovered ) ) {
			return array();
		}

		$recovered = $this->sort_chunks_by_score_desc( $recovered );
		$recovered = $this->merge_deduplicate_chunks( $recovered );

		$max_keep = (int) apply_filters( 'gg_data_rag_single_block_lookup_recovery_max_chunks', 2, $this->connection_name );
		$max_keep = max( 1, $max_keep );

		return array_slice( $recovered, 0, $max_keep );
	}

				/**
				 * Build balance validation telemetry for compare synthesis selection.
				 *
				 * @since 1.0.0
				 * @param array $slot_counts Selected chunk counts per covered slot.
				 * @param array $config Applied balancing config.
				 * @param array $covered_slots Covered slot names.
				 * @return array Balance validation metrics.
				 */
	private function build_compare_balance_telemetry( $slot_counts, $config, $covered_slots = array() ) {
		$covered_slots = is_array( $covered_slots ) ? array_values( $covered_slots ) : array();
		$counts        = array();

		foreach ( $covered_slots as $slot_name ) {
			$counts[ $slot_name ] = (int) ( $slot_counts[ $slot_name ] ?? 0 );
		}

		$total_selected = array_sum( $counts );
		$slot_total     = count( $counts );

		if ( 0 === $slot_total || 0 === $total_selected ) {
			return array(
				'selected_total'         => (int) $total_selected,
				'covered_slot_count'     => (int) $slot_total,
				'max_share'              => 0.0,
				'min_share'              => 0.0,
				'share_spread'           => 0.0,
				'expected_even_share'    => 0.0,
				'dominant_slot'          => '',
				'max_per_slot_violation' => false,
				'min_per_slot_violation' => false,
			);
		}

		$dominant_slot = '';
		$max_count     = -1;
		$min_count     = PHP_INT_MAX;

		foreach ( $counts as $slot_name => $count ) {
			if ( $count > $max_count ) {
				$max_count     = $count;
				$dominant_slot = $slot_name;
			}

			if ( $count < $min_count ) {
				$min_count = $count;
			}
		}

		$max_share           = round( $max_count / $total_selected, 4 );
		$min_share           = round( $min_count / $total_selected, 4 );
		$expected_even_share = round( 1 / $slot_total, 4 );
		$share_spread        = round( $max_share - $min_share, 4 );

		$max_per_slot = (int) ( $config['max_per_slot'] ?? 0 );
		$min_per_slot = (int) ( $config['min_per_slot'] ?? 0 );

		$max_violation = false;
		$min_violation = false;

		foreach ( $counts as $count ) {
			if ( $max_per_slot > 0 && $count > $max_per_slot ) {
				$max_violation = true;
			}

			if ( $min_per_slot > 0 && $count < $min_per_slot ) {
				$min_violation = true;
			}
		}

		return array(
			'selected_total'         => (int) $total_selected,
			'covered_slot_count'     => (int) $slot_total,
			'max_share'              => $max_share,
			'min_share'              => $min_share,
			'share_spread'           => $share_spread,
			'expected_even_share'    => $expected_even_share,
			'dominant_slot'          => $dominant_slot,
			'max_per_slot_violation' => (bool) $max_violation,
			'min_per_slot_violation' => (bool) $min_violation,
		);
	}

				/**
				 * Build balanced synthesis chunks for compare responses.
				 *
				 * Enforces anti-dominance limits so one entity cannot crowd final context.
				 * Selection strategy:
				 * 1) Ensure minimum chunks per covered slot.
				 * 2) Round-robin fill per slot up to per-slot cap.
				 * 3) Score-based fill for remaining capacity respecting per-slot cap.
				 *
				 * @since 1.0.0
				 * @param array $coverage Coverage result from GG_Data_Coverage_Gate::evaluate().
				 * @return array {
				 *   @type array $chunks Selected synthesis chunks.
				 *   @type array $slot_counts Selected chunk count per slot.
				 *   @type array $config Applied balancing config.
				 * }
				 */
	private function build_balanced_compare_synthesis_chunks( $coverage ) {
		$max_total = (int) apply_filters( 'gg_data_rag_compare_synthesis_max_chunks', 8, $this->connection_name );
		$max_total = max( 1, $max_total );

		$max_per_slot = (int) apply_filters( 'gg_data_rag_compare_max_chunks_per_entity', 3, $this->connection_name );
		$max_per_slot = max( 1, min( $max_per_slot, $max_total ) );

		$min_per_slot = (int) apply_filters( 'gg_data_rag_compare_min_chunks_per_covered_entity', 1, $this->connection_name );
		$min_per_slot = max( 0, min( $min_per_slot, $max_per_slot ) );

		$covered_slots   = $coverage['covered_slots'] ?? array();
		$chunks_per_slot = $coverage['chunks_per_slot'] ?? array();

		if ( empty( $covered_slots ) || empty( $chunks_per_slot ) ) {
			return array(
				'chunks'      => array(),
				'slot_counts' => array(),
				'config'      => array(
					'max_total'    => $max_total,
					'max_per_slot' => $max_per_slot,
					'min_per_slot' => $min_per_slot,
				),
			);
		}

		$slot_candidates = array();
		$slot_counts     = array();
		$slot_indexes    = array();

		foreach ( $covered_slots as $slot_name ) {
			$candidates                    = $chunks_per_slot[ $slot_name ] ?? array();
			$slot_candidates[ $slot_name ] = $this->sort_chunks_by_score_desc( $candidates );
			$slot_counts[ $slot_name ]     = 0;
			$slot_indexes[ $slot_name ]    = 0;
		}

		$selected       = array();
		$selected_keys  = array();
		$selected_count = 0;

		$append_chunk = function ( $slot_name, $chunk ) use ( &$selected, &$selected_keys, &$slot_counts ) {
			$key = $this->get_chunk_dedupe_key( $chunk );
			if ( isset( $selected_keys[ $key ] ) ) {
				return false;
			}

			$selected_keys[ $key ] = true;
			$selected[]            = $chunk;
			++$slot_counts[ $slot_name ];

			return true;
		};

					$next_slot_chunk = function ( $slot_name ) use ( &$slot_candidates, &$slot_indexes, &$selected_keys ) {
						$candidates = $slot_candidates[ $slot_name ] ?? array();

						while ( isset( $candidates[ $slot_indexes[ $slot_name ] ] ) ) {
							$chunk = $candidates[ $slot_indexes[ $slot_name ] ];
							++$slot_indexes[ $slot_name ];

							$key = $this->get_chunk_dedupe_key( $chunk );
							if ( isset( $selected_keys[ $key ] ) ) {
								continue;
							}

							return $chunk;
						}

						return null;
					};

		if ( $min_per_slot > 0 ) {
			foreach ( $covered_slots as $slot_name ) {
				while ( $slot_counts[ $slot_name ] < $min_per_slot && $selected_count < $max_total ) {
					$chunk = $next_slot_chunk( $slot_name );
					if ( null === $chunk ) {
						break;
					}

					if ( $append_chunk( $slot_name, $chunk ) ) {
						++$selected_count;
					}
				}
			}
		}

		while ( $selected_count < $max_total ) {
			$progress = false;

			foreach ( $covered_slots as $slot_name ) {
				if ( $slot_counts[ $slot_name ] >= $max_per_slot || $selected_count >= $max_total ) {
					continue;
				}

				$chunk = $next_slot_chunk( $slot_name );
				if ( null === $chunk ) {
					continue;
				}

				if ( $append_chunk( $slot_name, $chunk ) ) {
					++$selected_count;
					$progress = true;
				}
			}

			if ( ! $progress ) {
				break;
			}
		}

		if ( count( $selected ) < $max_total ) {
			$global_candidates = array();
			foreach ( $covered_slots as $slot_name ) {
				foreach ( $slot_candidates[ $slot_name ] as $chunk ) {
					$global_candidates[] = array(
						'slot'  => $slot_name,
						'chunk' => $chunk,
					);
				}
			}

			usort(
				$global_candidates,
				function ( $a, $b ) {
					$score_a = isset( $a['chunk']['score'] ) ? (float) $a['chunk']['score'] : 0.0;
					$score_b = isset( $b['chunk']['score'] ) ? (float) $b['chunk']['score'] : 0.0;
					if ( $score_a === $score_b ) {
						return 0;
					}

					return ( $score_a < $score_b ) ? 1 : -1;
				}
			);

			foreach ( $global_candidates as $candidate ) {
				if ( count( $selected ) >= $max_total ) {
					break;
				}

				$slot_name = $candidate['slot'];
				if ( $slot_counts[ $slot_name ] >= $max_per_slot ) {
					continue;
				}

				$append_chunk( $slot_name, $candidate['chunk'] );
			}
		}

					return array(
						'chunks'      => $selected,
						'slot_counts' => $slot_counts,
						'config'      => array(
							'max_total'    => $max_total,
							'max_per_slot' => $max_per_slot,
							'min_per_slot' => $min_per_slot,
						),
					);
	}

	/**
	 * Sort chunks by descending score.
	 *
	 * @since 1.0.0
	 * @param array $chunks Chunk list.
	 * @return array Sorted chunk list.
	 */
	private function sort_chunks_by_score_desc( $chunks ) {
		$sorted = $chunks;

		usort(
			$sorted,
			function ( $a, $b ) {
				$score_a = isset( $a['score'] ) ? (float) $a['score'] : 0.0;
				$score_b = isset( $b['score'] ) ? (float) $b['score'] : 0.0;
				if ( $score_a === $score_b ) {
					return 0;
				}

				return ( $score_a < $score_b ) ? 1 : -1;
			}
		);

		return $sorted;
	}

	/**
	 * Build a stable chunk dedupe key.
	 *
	 * @since 1.0.0
	 * @param array $chunk Chunk data.
	 * @return string Dedupe key.
	 */
	private function get_chunk_dedupe_key( $chunk ) {
		$post_id     = $chunk['post_id'] ?? '';
		$chunk_index = $chunk['chunk_index'] ?? $chunk['id'] ?? '';
		return $post_id . '_' . $chunk_index;
	}

	/**
	 * Build compare sources from per-slot evidence.
	 *
	 * Ensures each covered entity contributes at least one source candidate,
	 * then deduplicates by post ID while preserving entity attribution.
	 *
	 * @since 1.0.0
	 * @param array $coverage Coverage result from GG_Data_Coverage_Gate::evaluate().
	 * @return array Source references with entity attribution.
	 */
	private function build_compare_sources( $coverage ) {
		$sources_by_post = array();
		$slot_entities   = $coverage['slot_entities'] ?? array();
		$primary_map     = $coverage['primary_chunks_per_slot'] ?? array();
		$fallback_map    = $coverage['chunks_per_slot'] ?? array();

		foreach ( $slot_entities as $slot_name => $entity_name ) {
			$slot_chunks = $primary_map[ $slot_name ] ?? array();
			if ( empty( $slot_chunks ) ) {
				$slot_chunks = $fallback_map[ $slot_name ] ?? array();
			}

			if ( empty( $slot_chunks ) ) {
				continue;
			}

			$best_chunk = $this->select_best_scored_chunk( $slot_chunks );
			$post_id    = (int) ( $best_chunk['post_id'] ?? 0 );

			if ( 0 === $post_id ) {
				continue;
			}

			$score = isset( $best_chunk['score'] ) ? (float) $best_chunk['score'] : 0.0;

			if ( ! isset( $sources_by_post[ $post_id ] ) ) {
				$sources_by_post[ $post_id ] = array(
					'post_id'  => $post_id,
					'title'    => $best_chunk['title'] ?? '',
					'url'      => $best_chunk['url'] ?? '',
					'score'    => $score,
					'entities' => array(),
				);
			} elseif ( $score > (float) $sources_by_post[ $post_id ]['score'] ) {
				$sources_by_post[ $post_id ]['score'] = $score;
			}

			$sources_by_post[ $post_id ]['entities'][] = (string) $entity_name;
			$sources_by_post[ $post_id ]['entities']   = array_values( array_unique( $sources_by_post[ $post_id ]['entities'] ) );
		}

		if ( empty( $sources_by_post ) ) {
			return array();
		}

		usort(
			$sources_by_post,
			function ( $a, $b ) {
				$score_a = isset( $a['score'] ) ? (float) $a['score'] : 0.0;
				$score_b = isset( $b['score'] ) ? (float) $b['score'] : 0.0;
				if ( $score_a === $score_b ) {
					return 0;
				}

				return ( $score_a < $score_b ) ? 1 : -1;
			}
		);

		return array_values( $sources_by_post );
	}

	/**
	 * Build per-entity source references from coverage slots.
	 *
	 * @since 1.0.0
	 * @param array $coverage Coverage result from GG_Data_Coverage_Gate::evaluate().
	 * @return array Entity name => source list.
	 */
	private function build_compare_sources_by_entity( $coverage ) {
		$result        = array();
		$slot_entities = $coverage['slot_entities'] ?? array();
		$primary_map   = $coverage['primary_chunks_per_slot'] ?? array();
		$fallback_map  = $coverage['chunks_per_slot'] ?? array();

		foreach ( $slot_entities as $slot_name => $entity_name ) {
			$slot_chunks = $primary_map[ $slot_name ] ?? array();
			if ( empty( $slot_chunks ) ) {
				$slot_chunks = $fallback_map[ $slot_name ] ?? array();
			}

			if ( empty( $slot_chunks ) ) {
				$result[ $entity_name ] = array();
				continue;
			}

			$result[ $entity_name ] = $this->format_sources( $slot_chunks );
		}

		return $result;
	}

				/**
				 * Select the highest-scoring chunk from a slot chunk list.
				 *
				 * @since 1.0.0
				 * @param array $chunks Slot chunk list.
				 * @return array Best chunk.
				 */
	private function select_best_scored_chunk( $chunks ) {
		$best       = $chunks[0];
		$best_score = isset( $best['score'] ) ? (float) $best['score'] : 0.0;

		foreach ( $chunks as $chunk ) {
			$score = isset( $chunk['score'] ) ? (float) $chunk['score'] : 0.0;
			if ( $score > $best_score ) {
				$best       = $chunk;
				$best_score = $score;
			}
		}

		return $best;
	}

				/**
				 * Build the synthesis prompt for compare_content.
				 *
				 * Supports 2–N entities. Varies by coverage decision: full comparison vs.
				 * partial (one or more entities missing evidence).
				 *
				 * @since 1.0.0
				 * @param string $query    Original user query.
				 * @param array  $entities Ordered array of entity strings.
				 * @param string $context  Assembled context string from chunks.
				 * @param array  $coverage Coverage decision array from GG_Data_Coverage_Gate.
				 * @return string Synthesis prompt.
				 */
	private function build_compare_prompt( $query, $entities, $context, $coverage ) {
		$entity_list = $this->format_entity_list( $entities, 'and' );

		if ( 'partial' === $coverage['decision'] ) {
			$slot_entities      = $coverage['slot_entities'] ?? array();
			$covered_entities   = array();
			$uncovered_entities = array();

			foreach ( $slot_entities as $slot => $entity ) {
				if ( in_array( $slot, $coverage['covered_slots'], true ) ) {
					$covered_entities[] = $entity;
				} else {
					$uncovered_entities[] = $entity;
				}
			}

			$covered_list   = $this->format_entity_list( $covered_entities, 'and' );
			$uncovered_list = $this->format_entity_list( $uncovered_entities, 'and' );

			return sprintf(
				"The user wants to compare %s. The context below contains information about %s only.\n\n" .
				"Instructions:\n" .
				"1. Summarize what you know about %s from the context.\n" .
				"2. Clearly state that information about %s was not found in the website content.\n" .
				"3. Do NOT fabricate any details about %s.\n" .
				"4. Ground every claim in the provided sources and include inline citations using [Source N] markers that match the context labels.\n\n" .
				"Context:\n%s\n\nUser question: %s\n\nAnswer:",
				$entity_list,
				$covered_list,
				$covered_list,
				$uncovered_list,
				$uncovered_list,
				$context,
				$query
			);
		}

		// Full coverage: compare all N items.
		return sprintf(
			"Compare %s based solely on the provided context.\n\n" .
			"Instructions:\n" .
			"1. Provide a clear comparison covering key similarities and differences across all items.\n" .
			"2. Structure the response for readability (e.g., bullet points or short paragraphs per aspect).\n" .
			"3. Ground every claim in the provided sources and include inline citations using [Source N] markers that match the context labels.\n" .
			"4. If the context does not cover a specific aspect for any item, say so — do not fabricate.\n\n" .
			"Context:\n%s\n\nUser question: %s\n\nComparison:",
			$entity_list,
			$context,
			$query
		);
	}

				/**
				 * Build metadata array for compare_content responses.
				 *
				 * @since 1.0.0
				 * @param string $query             Original user query.
				 * @param string $llm_model_id      LLM model ID.
				 * @param array  $options           RAG options.
				 * @param array  $entities          Entity names compared.
				 * @param array  $coverage          Coverage decision result from GG_Data_Coverage_Gate.
				 * @param array  $chunks            Chunks used for synthesis.
				 * @param string $coverage_decision 'full' | 'partial' | 'abstain'.
				 * @param float  $start_time        microtime(true) at handler start.
				 * @param bool   $fallback_used     Whether synthesis fallback was triggered. Default false.
				 * @return array Metadata array.
				 */
	private function build_compare_metadata( $query, $llm_model_id, $options, $entities, $coverage, $chunks, $coverage_decision, $start_time, $fallback_used = false ) {
		$normalized_post_types = $this->normalize_compare_post_types( $options['post_types'] ?? array() );

		return array(
			'tool'                => 'compare_content',
			'tool_selected'       => 'compare_content',
			'entities'            => $entities,
			'coverage'            => array(
				'decision'        => $coverage_decision,
				'covered_slots'   => $coverage['covered_slots'] ?? array(),
				'uncovered_slots' => $coverage['uncovered_slots'] ?? array(),
				'evidence_count'  => $coverage['evidence_count'] ?? 0,
				'threshold_used'  => $coverage['threshold_used'] ?? 0.0,
			),
			'chunks_used'         => count( $chunks ),
			'fallback_used'       => $fallback_used,
			'conversation_id'     => $options['conversation_id'] ?? null,
			'source'              => $options['source'] ?? array( 'type' => 'rest' ),
			'connection'          => $this->connection_name,
			'rewrite_model'       => $options['rewrite_model'] ?? null,
			'rerank_model'        => $options['rerank_model_id'] ?? null,
			'rerank_model_active' => ! empty( $options['rerank_model_id'] ),
			'post_types'          => $normalized_post_types,
			'embedding_model'     => $this->embedding_model_key,
			'llm_model'           => $llm_model_id,
			'execution_time'      => round( ( microtime( true ) - $start_time ) * 1000 ),
			'security_check'      => $options['security_check'] ?? array(),
		);
	}

				/**
				 * Build a deterministic fallback answer for compare_content when LLM synthesis fails.
				 *
				 * Extracts short excerpts from the top chunk for each covered entity slot
				 * and formats them as a plain-text partial summary without LLM involvement.
				 * Supports 2–N entities via coverage slot_entities map.
				 *
				 * @since 1.0.0
				 * @param array $entities Ordered array of entity strings.
				 * @param array $coverage Coverage result from GG_Data_Coverage_Gate.
				 * @return string Fallback answer text.
				 */
	private function build_compare_fallback_answer( $entities, $coverage ) {
		$parts         = array();
		$slot_entities = $coverage['slot_entities'] ?? array();

		foreach ( $coverage['chunks_per_slot'] as $slot_name => $slot_chunks ) {
			if ( empty( $slot_chunks ) ) {
				continue;
			}

			$entity  = $slot_entities[ $slot_name ] ?? $slot_name;
			$excerpt = mb_substr( wp_strip_all_tags( $slot_chunks[0]['content'] ?? '' ), 0, 300 );

			if ( ! empty( $excerpt ) ) {
				$parts[] = $entity . ': ' . $excerpt . '…';
			}
		}

		if ( empty( $parts ) ) {
			return __( 'I found some content but encountered an error generating the comparison. Please try again.', 'gregius-data' );
		}

		return implode( "\n\n", $parts );
	}

				/**
				 * Handle compare_content tool — per-entity retrieval, coverage gate, synthesis.
				 *
				 * This handler is called both from the deterministic pre-route (trigger=rule)
				 * and the LLM tool selection switch (trigger=llm).
				 *
				 * Flow:
				 * 1. Extract entity list from tool_selection.
				 * 2. Per-entity retrieval (4 chunks each).
				 * 3. Merge and deduplicate.
				 * 4. Optional reranking.
				 * 5. Coverage gate evaluation.
				 * 6. Abstain / partial / full synthesis based on decision.
				 *
				 * @since 1.0.0
				 * @param string $query          Original user query.
				 * @param string $llm_model_id   LLM model ID.
				 * @param array  $options        RAG options.
				 * @param array  $tool_selection Tool selection result (contains 'entities').
				 * @return array|WP_Error Response data or error.
				 */
	private function handle_compare_content( $query, $llm_model_id, $options, $tool_selection ) {
		$start_time         = microtime( true );
		$progress_callback  = $options['progress_callback'] ?? null;
		$retrieval_policy   = $this->resolve_hybrid_retrieval_policy( 'compare', $options, $query );
		$entities           = $tool_selection['entities'] ?? array();
		$compare_post_types = $this->normalize_compare_post_types( $options['post_types'] ?? array() );
		$retrieval_stats    = array(
			'entity_queries'      => array(),
			'entity_candidates'   => array(),
			'entity_count'        => 0,
			'combined_query_used' => false,
			'combined_query'      => '',
			'combined_candidates' => 0,
			'total_candidates'    => 0,
			'post_types'          => $compare_post_types,
		);

		// Require at least 2 entities — fallback to search_content on bad input.
		if ( count( $entities ) < 2 ) {
			$this->logger->log(
				'RAG Service: compare_content handler received fewer than 2 entities — falling back to search_content',
				'warning',
				'rag',
				$this->connection_name,
				array(
					'entities' => $entities,
					'query'    => $query,
				)
			);

			// Re-enter generate_answer's search path by returning a search_content result.
			// We can't recurse into generate_answer safely, so we re-use retrieve_chunks directly.
			return $this->synthesize_search_content( $query, $llm_model_id, $options, $start_time, true );
		}

		// Cap entities to avoid runaway context and retrieval costs.
		$max_entities = (int) apply_filters( 'gg_data_rag_compare_max_entities', 6, $this->connection_name );
		if ( count( $entities ) > $max_entities ) {
			$entities = array_slice( $entities, 0, $max_entities );
			$this->logger->log(
				sprintf( 'RAG Service: compare_content capped entity list to %d items', $max_entities ),
				'info',
				'rag',
				$this->connection_name,
				array( 'entities' => $entities )
			);
		}

		$context_terms = $this->extract_context_terms( $query, $entities );

		// Step 1: Per-entity retrieval with adaptive chunk budget.
		// Use 4 chunks per entity for 2–3 items; reduce to 3 for 4+ items to stay inside context limits.
		$chunks_per_entity = count( $entities ) > 3 ? 3 : 4;
		$all_raw_chunks    = array();
		$entity_queries    = array();
		$entity_candidates = array();

		$this->emit_progress( $progress_callback, 'searching', __( 'Searching for comparison content...', 'gregius-data' ) );

		foreach ( $entities as $idx => $entity ) {
			$entity_query   = trim( preg_replace( '/\s+/', ' ', $entity . ( ! empty( $context_terms ) ? ' ' . $context_terms : '' ) ) );
			$entity_options = array(
				'num_results' => $chunks_per_entity,
				'post_types'  => $compare_post_types,
			);
			$entity_chunks  = $this->retrieve_chunks(
				$entity_query,
				$this->apply_retrieval_policy_to_options(
					$entity_options,
					$retrieval_policy
				)
			);
			if ( is_wp_error( $entity_chunks ) ) {
				$entity_chunks = array();
			}

			// Prefilter: select most relevant chunks per post for this entity, not just first N.
			if ( ! empty( $entity_chunks ) ) {
				$entity_chunks = $this->prefilter_chunk_candidates( $entity_chunks, 2, 12, $entity_query );
			}
			$entity_queries[ 'entity_' . $idx ]    = $entity_query;
			$entity_candidates[ 'entity_' . $idx ] = count( $entity_chunks );
			$all_raw_chunks                        = array_merge( $all_raw_chunks, $entity_chunks );
		}

		// Step 2: Merge and deduplicate.
		$all_chunks                           = $this->merge_deduplicate_chunks( $all_raw_chunks );
		$retrieval_stats['entity_queries']    = $entity_queries;
		$retrieval_stats['entity_candidates'] = $entity_candidates;
		$retrieval_stats['entity_count']      = count( $entities );
		$retrieval_stats['total_candidates']  = count( $all_chunks );
		$retrieval_stats['rerank_validation'] = array(
			'pre_rerank_count'      => count( $all_chunks ),
			'post_rerank_count'     => count( $all_chunks ),
			'post_accept_count'     => count( $all_chunks ),
			'dropped_by_acceptance' => 0,
			'acceptance_drop_rate'  => 0.0,
			'rerank_applied'        => false,
			'rerank_policy_id'      => '',
		);

		// Step 3: Optional reranking over merged set.
		$rerank_policy  = $this->resolve_formal_rerank_policy( 'compare', count( $all_chunks ), $options, $query );
		$rerank_applied = false;
		if ( ! empty( $rerank_policy['should_run'] ) && ! empty( $all_chunks ) ) {
			$this->emit_progress( $progress_callback, 'reranking', __( 'Reranking results...', 'gregius-data' ) );
			$reranked = $this->rerank_chunks( $all_chunks, $query, $options['rerank_model_id'] );
			if ( ! is_wp_error( $reranked ) ) {
				$all_chunks     = $reranked;
				$rerank_applied = true;
			}
		}
		$ranked_compare_candidates = $all_chunks;
		$acceptance                = $this->apply_candidate_acceptance_policy( $ranked_compare_candidates, 'compare', $rerank_policy, $rerank_applied );
		$all_chunks                = $acceptance['candidates'];
		if ( empty( $all_chunks ) && ! empty( $acceptance['metrics']['input_count'] ) ) {
			$recovered_chunks = $this->recover_compare_slot_candidates( $ranked_compare_candidates, $entities );
			if ( ! empty( $recovered_chunks ) ) {
				$all_chunks                               = $recovered_chunks;
				$acceptance['metrics']['accepted_count']  = count( $all_chunks );
				$acceptance['metrics']['acceptance_mode'] = 'post_rerank_slot_recovery';
			}
		}
		$retrieval_stats['rerank_policy']     = $rerank_policy;
		$retrieval_stats['rerank_applied']    = $rerank_applied;
		$retrieval_stats['acceptance']        = $acceptance['metrics'];
		$post_rerank_count                    = (int) ( $acceptance['metrics']['input_count'] ?? count( $all_chunks ) );
		$post_accept_count                    = (int) ( $acceptance['metrics']['accepted_count'] ?? count( $all_chunks ) );
		$dropped_by_acceptance                = max( 0, $post_rerank_count - $post_accept_count );
		$retrieval_stats['rerank_validation'] = array(
			'pre_rerank_count'      => (int) ( $retrieval_stats['rerank_validation']['pre_rerank_count'] ?? 0 ),
			'post_rerank_count'     => $post_rerank_count,
			'post_accept_count'     => $post_accept_count,
			'dropped_by_acceptance' => $dropped_by_acceptance,
			'acceptance_drop_rate'  => $post_rerank_count > 0 ? round( $dropped_by_acceptance / $post_rerank_count, 4 ) : 0.0,
			'rerank_applied'        => (bool) $rerank_applied,
			'rerank_policy_id'      => (string) ( $rerank_policy['id'] ?? '' ),
		);

		// Step 4: Coverage gate evaluation.
		$coverage_gate                            = new GG_Data_Coverage_Gate();
		$coverage                                 = $coverage_gate->evaluate( $all_chunks, 'compare', $this->build_compare_slots( $entities ) );
		$retrieval_stats['slot_evidence']         = $this->summarize_slot_evidence( $coverage['chunks_per_slot'] ?? array() );
		$retrieval_stats['slot_evidence_primary'] = $this->summarize_slot_evidence( $coverage['primary_chunks_per_slot'] ?? array() );

		$this->logger->log(
			sprintf(
				'RAG Service: compare_content coverage decision="%s" for %d entities [%s]',
				$coverage['decision'],
				count( $entities ),
				implode( ', ', $entities )
			),
			'info',
			'rag',
			$this->connection_name,
			array(
				'entities'        => $entities,
				'covered_slots'   => $coverage['covered_slots'],
				'uncovered_slots' => $coverage['uncovered_slots'],
				'evidence_count'  => $coverage['evidence_count'],
			)
		);

		// Step 5: Abstain path.
		if ( 'abstain' === $coverage['decision'] ) {
			$combined_parts = array();
			foreach ( $entities as $combined_entity ) {
				$combined_parts[] = trim( (string) $combined_entity );
			}
			if ( ! empty( $context_terms ) ) {
				$combined_parts[] = $context_terms;
			}
			$combined_query = trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $combined_parts ) ) ) );

			if ( ! empty( $combined_query ) ) {
				$combined_chunks = $this->retrieve_chunks(
					$combined_query,
					$this->apply_retrieval_policy_to_options(
						array(
							'num_results' => 6,
							'post_types'  => $compare_post_types,
						),
						$retrieval_policy
					)
				);

				if ( ! is_wp_error( $combined_chunks ) && ! empty( $combined_chunks ) ) {
					$retrieval_stats['combined_query_used'] = true;
					$retrieval_stats['combined_query']      = $combined_query;
					$retrieval_stats['combined_candidates'] = count( $combined_chunks );

					$all_chunks                          = $this->merge_deduplicate_chunks( array_merge( $all_chunks, $combined_chunks ) );
					$retrieval_stats['total_candidates'] = count( $all_chunks );

					if ( ! empty( $rerank_policy['should_run'] ) ) {
						$reranked_combined = $this->rerank_chunks( $all_chunks, $query, $options['rerank_model_id'] );
						if ( ! is_wp_error( $reranked_combined ) ) {
							$all_chunks     = $reranked_combined;
							$rerank_applied = true;
						}
					}

					$ranked_compare_candidates = $all_chunks;
					$acceptance                = $this->apply_candidate_acceptance_policy( $ranked_compare_candidates, 'compare', $rerank_policy, $rerank_applied );
					$all_chunks                = $acceptance['candidates'];
					if ( empty( $all_chunks ) && ! empty( $acceptance['metrics']['input_count'] ) ) {
						$recovered_chunks = $this->recover_compare_slot_candidates( $ranked_compare_candidates, $entities );
						if ( ! empty( $recovered_chunks ) ) {
							$all_chunks                               = $recovered_chunks;
							$acceptance['metrics']['accepted_count']  = count( $all_chunks );
							$acceptance['metrics']['acceptance_mode'] = 'post_rerank_slot_recovery';
						}
					}
					$retrieval_stats['rerank_applied']    = $rerank_applied;
					$retrieval_stats['acceptance']        = $acceptance['metrics'];
					$post_rerank_count                    = (int) ( $acceptance['metrics']['input_count'] ?? count( $all_chunks ) );
					$post_accept_count                    = (int) ( $acceptance['metrics']['accepted_count'] ?? count( $all_chunks ) );
					$dropped_by_acceptance                = max( 0, $post_rerank_count - $post_accept_count );
					$retrieval_stats['rerank_validation'] = array(
						'pre_rerank_count'      => (int) ( $retrieval_stats['total_candidates'] ?? $post_rerank_count ),
						'post_rerank_count'     => $post_rerank_count,
						'post_accept_count'     => $post_accept_count,
						'dropped_by_acceptance' => $dropped_by_acceptance,
						'acceptance_drop_rate'  => $post_rerank_count > 0 ? round( $dropped_by_acceptance / $post_rerank_count, 4 ) : 0.0,
						'rerank_applied'        => (bool) $rerank_applied,
						'rerank_policy_id'      => (string) ( $rerank_policy['id'] ?? '' ),
					);

					$coverage                                 = $coverage_gate->evaluate( $all_chunks, 'compare', $this->build_compare_slots( $entities ) );
					$retrieval_stats['slot_evidence']         = $this->summarize_slot_evidence( $coverage['chunks_per_slot'] ?? array() );
					$retrieval_stats['slot_evidence_primary'] = $this->summarize_slot_evidence( $coverage['primary_chunks_per_slot'] ?? array() );
				}
			}
		}

		$compare_profile         = $this->get_policy_threshold_profile( 'compare', $query, $options );
		$compare_raw_count       = (int) ( $retrieval_stats['total_candidates'] ?? 0 );
		$compare_qualified_count = $this->count_qualified_chunks( $all_chunks, $compare_profile['min_relevance'] );
		$compare_reason_code     = 'full_primary_coverage';

		if ( 'abstain' === $coverage['decision'] ) {
			$compare_reason_code = 'no_qualified_evidence';
		} elseif ( 'partial' === $coverage['decision'] ) {
			if ( ! empty( $retrieval_stats['combined_query_used'] ) ) {
				$compare_reason_code = 'fallback_combined_query_partial';
			} else {
				$missing_count = count( $coverage['uncovered_slots'] ?? array() );
				if ( $missing_count > 1 ) {
					$compare_reason_code = 'multiple_slots_missing';
				} elseif ( 1 === $missing_count ) {
					$compare_reason_code = 'one_slot_missing';
				} else {
					$compare_reason_code = 'primary_evidence_partial';
				}
			}
		}

		// Step 5: Abstain path.
		if ( 'abstain' === $coverage['decision'] ) {
			$retrieval_stats['balance_validation'] = array(
				'status' => 'skipped',
				'reason' => 'abstain_before_synthesis',
			);

			$result = array(
				'answer'   => $this->build_abstain_message(
					sprintf(
						/* translators: %s: formatted list of entity names */
						__( 'I couldn\'t find information about %s in the website content. Please try a different question or check if these topics are covered on the site.', 'gregius-data' ),
						$this->format_entity_list( $entities )
					),
					$query,
					$this->build_policy_parity(
						'compare',
						$coverage['decision'],
						$compare_reason_code,
						$compare_profile['id']
					),
					$this->build_retrieval_parity(
						$compare_raw_count,
						$compare_qualified_count,
						0,
						$retrieval_stats
					),
					$options
				),
				'sources'  => array(),
				'chunks'   => $all_chunks,
				'metadata' => $this->ensure_policy_retrieval_contract(
					array_merge(
						$this->build_compare_metadata( $query, $llm_model_id, $options, $entities, $coverage, array(), 'abstain', $start_time ),
						array(
							'retrieval' => $this->build_retrieval_parity(
								$compare_raw_count,
								$compare_qualified_count,
								0,
								$retrieval_stats
							),
							'policy'    => $this->build_policy_parity(
								'compare',
								$coverage['decision'],
								$compare_reason_code,
								$compare_profile['id'],
								array(
									'agentic_model_active' => ! empty( $options['rewrite_model'] ),
									'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
									'lexical_active'       => ! empty( $retrieval_policy['lexical_active'] ),
									'retrieval_query_class' => $retrieval_policy['query_class'] ?? 'compare',
									'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
									'retrieval_mode_source' => $retrieval_policy['mode_source'] ?? 'query_class_default',
									'retrieval_mode'       => $retrieval_policy['mode'] ?? 'hybrid',
									'retrieval_policy_id'  => $retrieval_policy['id'] ?? 'retrieval_compare_hybrid',
									'rerank_model_active'  => ! empty( $options['rerank_model_id'] ),
									'thresholds'           => $compare_profile,
									'coverage_mode'        => ( 'full' === $coverage['decision'] ) ? 'full_primary' : 'partial_or_abstain',
									'covered_slots'        => $coverage['covered_slots'] ?? array(),
									'uncovered_slots'      => $coverage['uncovered_slots'] ?? array(),
								)
							),
						)
					),
					'compare'
				),
			);

			$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );

			return $result;
		}

		// Step 6: Build balanced synthesis chunk set from covered slots.
		$balanced_selection                      = $this->build_balanced_compare_synthesis_chunks( $coverage );
		$synthesis_chunks                        = $balanced_selection['chunks'];
		$retrieval_stats['slot_selected_counts'] = $balanced_selection['slot_counts'];
		$retrieval_stats['balance']              = $balanced_selection['config'];
		$retrieval_stats['balance_validation']   = $this->build_compare_balance_telemetry(
			$balanced_selection['slot_counts'] ?? array(),
			$balanced_selection['config'] ?? array(),
			$coverage['covered_slots'] ?? array()
		);

		// Optionally expand neighbor chunks for richer context.
		$connection_config = $this->get_connection_config();
		if ( ! is_wp_error( $connection_config ) ) {
			$post_ids       = array_unique( array_column( $synthesis_chunks, 'post_id' ) );
			$chunks_by_post = $this->fetch_chunks_for_posts( $post_ids, $connection_config );
			if ( ! empty( $chunks_by_post ) ) {
				$synthesis_chunks = $this->expand_neighbor_chunks_if_needed( $synthesis_chunks, $chunks_by_post );
				$synthesis_chunks = $this->merge_deduplicate_chunks( $synthesis_chunks );
				$synthesis_chunks = array_slice( $synthesis_chunks, 0, (int) $balanced_selection['config']['max_total'] );
			}
		}
		$compare_selected_count = count( $synthesis_chunks );

		// Step 7: Build context and synthesis prompt.
		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $llm_model_id );
		$model_config   = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$model_name     = $model_config['provider_model_id'] ?? $llm_model_id;
		$native_max     = $this->model_max_tokens[ $model_name ] ?? 8192;
		$max_tokens     = (int) ( $model_data['max_tokens'] ?? $model_config['max_tokens'] ?? $native_max );

		$context = $this->build_context( $synthesis_chunks, $model_name, $max_tokens );
		$prompt  = $this->build_compare_prompt( $query, $entities, $context, $coverage );

		$prompt_resolution = $this->resolve_system_prompt( $options );
		if ( is_wp_error( $prompt_resolution ) ) {
			return $prompt_resolution;
		}

		$this->emit_progress( $progress_callback, 'generating', __( 'Generating comparison...', 'gregius-data' ) );

		$response = $this->stream_llm_response(
			$prompt,
			$llm_model_id,
			$prompt_resolution['content'],
			$progress_callback
		);

		// Step 8: Handle LLM failure — return deterministic fallback.
		if ( is_wp_error( $response ) ) {
			$compare_sources = $this->build_compare_sources( $coverage );
			$result          = array(
				'answer'   => $this->build_compare_fallback_answer( $entities, $coverage ),
				'sources'  => $compare_sources,
				'chunks'   => $synthesis_chunks,
				'metadata' => $this->ensure_policy_retrieval_contract(
					array_merge(
						$this->build_compare_metadata( $query, $llm_model_id, $options, $entities, $coverage, $synthesis_chunks, $coverage['decision'], $start_time, true ),
						array(
							'retrieval'         => $this->build_retrieval_parity(
								$compare_raw_count,
								$compare_qualified_count,
								$compare_selected_count,
								$retrieval_stats
							),
							'policy'            => $this->build_policy_parity(
								'compare',
								$coverage['decision'],
								$compare_reason_code,
								$compare_profile['id'],
								array(
									'agentic_model_active' => ! empty( $options['rewrite_model'] ),
									'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
									'lexical_active'       => ! empty( $retrieval_policy['lexical_active'] ),
									'retrieval_query_class' => $retrieval_policy['query_class'] ?? 'compare',
									'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
									'retrieval_mode_source' => $retrieval_policy['mode_source'] ?? 'query_class_default',
									'retrieval_mode'       => $retrieval_policy['mode'] ?? 'hybrid',
									'retrieval_policy_id'  => $retrieval_policy['id'] ?? 'retrieval_compare_hybrid',
									'rerank_model_active'  => ! empty( $options['rerank_model_id'] ),
									'thresholds'           => $compare_profile,
									'coverage_mode'        => ( 'full' === $coverage['decision'] ) ? 'full_primary' : 'partial_or_abstain',
									'covered_slots'        => $coverage['covered_slots'] ?? array(),
									'uncovered_slots'      => $coverage['uncovered_slots'] ?? array(),
								)
							),
							'sources_by_entity' => $this->build_compare_sources_by_entity( $coverage ),
						)
					),
					'compare'
				),
			);

			$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );

			return $result;
		}

		$sources          = $this->build_compare_sources( $coverage );
		$citation_sources = $this->build_citation_sources( $synthesis_chunks );
		$answer_text      = $this->sanitize_inline_citation_markers( (string) ( $response['text'] ?? '' ), $citation_sources );

		$result = array(
			'answer'   => $answer_text,
			'sources'  => $sources,
			'chunks'   => $synthesis_chunks,
			'metadata' => $this->ensure_policy_retrieval_contract(
				array_merge(
					$this->build_compare_metadata( $query, $llm_model_id, $options, $entities, $coverage, $synthesis_chunks, $coverage['decision'], $start_time ),
					array(
						'retrieval'         => $this->build_retrieval_parity(
							$compare_raw_count,
							$compare_qualified_count,
							$compare_selected_count,
							$retrieval_stats
						),
						'policy'            => $this->build_policy_parity(
							'compare',
							$coverage['decision'],
							$compare_reason_code,
							$compare_profile['id'],
							array(
								'agentic_model_active'   => ! empty( $options['rewrite_model'] ),
								'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
								'lexical_active'         => ! empty( $retrieval_policy['lexical_active'] ),
								'retrieval_query_class'  => $retrieval_policy['query_class'] ?? 'compare',
								'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
								'retrieval_mode_source'  => $retrieval_policy['mode_source'] ?? 'query_class_default',
								'retrieval_mode'         => $retrieval_policy['mode'] ?? 'hybrid',
								'retrieval_policy_id'    => $retrieval_policy['id'] ?? 'retrieval_compare_hybrid',
								'rerank_model_active'    => ! empty( $options['rerank_model_id'] ),
								'thresholds'             => $compare_profile,
								'coverage_mode'          => ( 'full' === $coverage['decision'] ) ? 'full_primary' : 'partial_or_abstain',
								'covered_slots'          => $coverage['covered_slots'] ?? array(),
								'uncovered_slots'        => $coverage['uncovered_slots'] ?? array(),
							)
						),
						'sources_by_entity' => $this->build_compare_sources_by_entity( $coverage ),
						'citation_sources'  => $citation_sources,
						'reasoning_content' => $response['reasoning_content'] ?? '',
						'usage'             => $response['usage'] ?? array(),
						'provider'          => $response['provider'] ?? '',
						'model_used'        => $response['model'] ?? '',
						'raw_response'      => $response['raw_response'] ?? array(),
						'prompt'            => $prompt_resolution['metadata'] ?? array(),
					)
				),
				'compare'
			),
		);

		$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );

		return $result;
	}

				/**
				 * Synthesize a search_content response from retrieved chunks.
				 *
				 * Internal helper used by handle_compare_content() when it falls back to
				 * standard search behavior. Performs full retrieval + generation but skips
				 * tool selection.
				 *
				 * @since 1.0.0
				 * @param string $query        Original user query.
				 * @param string $llm_model_id LLM model ID.
				 * @param array  $options      RAG options.
				 * @param float  $start_time   microtime(true) at caller's start.
				 * @param bool   $fallback     Whether this is a fallback call. Default false.
				 * @return array|WP_Error Response data or error.
				 */
	private function synthesize_search_content( $query, $llm_model_id, $options, $start_time, $fallback = false ) {
		$progress_callback = $options['progress_callback'] ?? null;
		$search_query      = $query;
		$post_types        = $this->normalize_compare_post_types( $options['post_types'] ?? array() );
		$retrieval_policy  = $this->resolve_hybrid_retrieval_policy( 'single', $options, $query );

		$post_candidates = $this->retrieve_chunks(
			$query,
			$this->apply_retrieval_policy_to_options(
				array(
					'num_results'     => 6,
					'post_types'      => $post_types,
					'metadata_filter' => isset( $options['metadata_filter'] ) && is_array( $options['metadata_filter'] ) ? $options['metadata_filter'] : array(),
				),
				$retrieval_policy
			)
		);

		if ( empty( $post_candidates ) || is_wp_error( $post_candidates ) ) {
			return new WP_Error(
				'no_chunks_found',
				__( 'No relevant content found for your question.', 'gregius-data' )
			);
		}

		// Passage retrieval + scoring (mirrors generate_answer flow).
		$connection_config = $this->get_connection_config();
		$chunks            = $post_candidates;

		if ( ! is_wp_error( $connection_config ) ) {
			$post_ids       = array_unique( array_column( $post_candidates, 'post_id' ) );
			$chunks_by_post = $this->fetch_chunks_for_posts( $post_ids, $connection_config );

			if ( ! empty( $chunks_by_post ) ) {
				$chunk_candidates = $this->build_chunk_candidates( $post_candidates, $chunks_by_post );
				$chunk_candidates = $this->prefilter_chunk_candidates( $chunk_candidates, 2, 12, $search_query );
				$rerank_policy    = $this->resolve_formal_rerank_policy( 'single', count( $chunk_candidates ), $options, $query );
				$rerank_applied   = false;

				if ( ! empty( $rerank_policy['should_run'] ) ) {
					$reranked = $this->rerank_chunks( $chunk_candidates, $search_query, $options['rerank_model_id'] );
					if ( is_wp_error( $reranked ) ) {
						$chunk_candidates = $this->heuristic_score_chunks( $chunk_candidates, $query );
					} else {
						$chunk_candidates = $reranked;
						$rerank_applied   = true;
					}
				} else {
					$chunk_candidates = $this->heuristic_score_chunks( $chunk_candidates, $query );
				}

				$ranked_single_candidates = $chunk_candidates;
				$acceptance               = $this->apply_candidate_acceptance_policy( $ranked_single_candidates, 'single', $rerank_policy, $rerank_applied );
				$chunk_candidates         = $acceptance['candidates'];
				if ( empty( $chunk_candidates ) && ! empty( $acceptance['metrics']['input_count'] ) ) {
					$recovered_chunks = $this->recover_single_block_lookup_candidates( $ranked_single_candidates, $search_query );
					if ( ! empty( $recovered_chunks ) ) {
						$chunk_candidates = $recovered_chunks;
					}
				}

				$chunks = $this->finalize_context_chunks( $chunk_candidates );
				$chunks = $this->expand_neighbor_chunks_if_needed( $chunks, $chunks_by_post );
			}
		} else {
			$rerank_policy  = $this->resolve_formal_rerank_policy( 'single', count( $chunks ), $options, $query );
			$rerank_applied = false;
			if ( ! empty( $rerank_policy['should_run'] ) ) {
				$reranked = $this->rerank_chunks( $chunks, $search_query, $options['rerank_model_id'] );
				if ( ! is_wp_error( $reranked ) ) {
					$chunks         = $reranked;
					$rerank_applied = true;
				}
			}
			$ranked_single_candidates = $chunks;
			$acceptance               = $this->apply_candidate_acceptance_policy( $ranked_single_candidates, 'single', $rerank_policy, $rerank_applied );
			$chunks                   = $acceptance['candidates'];
			if ( empty( $chunks ) && ! empty( $acceptance['metrics']['input_count'] ) ) {
				$recovered_chunks = $this->recover_single_block_lookup_candidates( $ranked_single_candidates, $search_query );
				if ( ! empty( $recovered_chunks ) ) {
					$chunks = $recovered_chunks;
				}
			}
		}

		if ( empty( $chunks ) ) {
			$single_profile = $this->get_policy_threshold_profile( 'single', $query, $options );
			$retrieval      = $this->build_retrieval_parity( count( $post_candidates ), 0, 0 );
			$policy         = $this->build_policy_parity(
				'single',
				'abstain',
				'no_relevant_context',
				$single_profile['id'],
				array(
					'agentic_model_active'   => ! empty( $options['rewrite_model'] ),
					'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
					'lexical_active'         => ! empty( $retrieval_policy['lexical_active'] ),
					'retrieval_query_class'  => $retrieval_policy['query_class'] ?? 'general',
					'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
					'retrieval_mode_source'  => $retrieval_policy['mode_source'] ?? 'query_class_default',
					'retrieval_mode'         => $retrieval_policy['mode'] ?? 'hybrid',
					'retrieval_policy_id'    => $retrieval_policy['id'] ?? 'retrieval_single_hybrid',
					'rerank_model_active'    => ! empty( $options['rerank_model_id'] ),
					'thresholds'             => $single_profile,
				)
			);

			$result = array(
				'answer'   => $this->build_abstain_message(
					__( 'I could not find relevant information in the website content to answer that question.', 'gregius-data' ),
					$query,
					$policy,
					$retrieval,
					$options
				),
				'sources'  => array(),
				'metadata' => $this->ensure_policy_retrieval_contract(
					array(
						'tool'              => 'search_content',
						'tool_selected'     => 'search_content',
						'fallback_used'     => $fallback,
						'chunks_used'       => 0,
						'conversation_id'   => $options['conversation_id'] ?? null,
						'source'            => $options['source'] ?? array( 'type' => 'rest' ),
						'connection'        => $this->connection_name,
						'rewrite_model'     => $options['rewrite_model'] ?? null,
						'post_types'        => $post_types,
						'embedding_model'   => $this->embedding_model_key,
						'llm_model'         => $llm_model_id,
						'execution_time'    => round( ( microtime( true ) - $start_time ) * 1000 ),
						'reasoning_content' => '',
						'usage'             => array(),
						'provider'          => '',
						'model_used'        => '',
						'raw_response'      => array(),
						'prompt'            => array(),
						'citation_sources'  => array(),
						'retrieval'         => $retrieval,
						'policy'            => $policy,
					),
					'single'
				),
			);

			$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );

			return $result;
		}

		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $llm_model_id );
		$model_config   = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$model_name     = $model_config['provider_model_id'] ?? $llm_model_id;
		$native_max     = $this->model_max_tokens[ $model_name ] ?? 8192;
		$max_tokens     = (int) ( $model_data['max_tokens'] ?? $model_config['max_tokens'] ?? $native_max );

		$context = $this->build_context( $chunks, $model_name, $max_tokens );
		$prompt  = $this->build_prompt( $query, $context );

		$prompt_resolution = $this->resolve_system_prompt( $options );
		if ( is_wp_error( $prompt_resolution ) ) {
			return $prompt_resolution;
		}

		$this->emit_progress( $progress_callback, 'generating', __( 'Generating answer...', 'gregius-data' ) );

		$response = $this->stream_llm_response(
			$prompt,
			$llm_model_id,
			$prompt_resolution['content'],
			$progress_callback
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$execution_time         = ( microtime( true ) - $start_time ) * 1000;
		$sources                = $this->format_sources( $chunks );
		$citation_sources       = $this->build_citation_sources( $chunks );
		$answer_text            = $this->sanitize_inline_citation_markers( (string) ( $response['text'] ?? '' ), $citation_sources );
		$single_profile         = $this->get_policy_threshold_profile( 'single', $query, $options );
		$single_raw_count       = count( $post_candidates );
		$single_qualified_count = $this->count_qualified_chunks( $chunks, $single_profile['min_relevance'] );
		$single_selected_count  = count( $chunks );
		$single_decision        = $single_selected_count > 0 ? 'full' : 'abstain';
		$single_reason_code     = $single_qualified_count > 0 ? 'full_primary_coverage' : 'below_threshold_post_rerank';
		$retrieval_policy       = $this->resolve_hybrid_retrieval_policy( 'single', $options, $query );

		$result = array(
			'answer'   => $answer_text,
			'sources'  => $sources,
			'metadata' => $this->ensure_policy_retrieval_contract(
				array(
					'tool'              => 'search_content',
					'tool_selected'     => 'search_content',
					'fallback_used'     => $fallback,
					'chunks_used'       => count( $chunks ),
					'conversation_id'   => $options['conversation_id'] ?? null,
					'source'            => $options['source'] ?? array( 'type' => 'rest' ),
					'connection'        => $this->connection_name,
					'rewrite_model'     => $options['rewrite_model'] ?? null,
					'post_types'        => $post_types,
					'embedding_model'   => $this->embedding_model_key,
					'llm_model'         => $llm_model_id,
					'execution_time'    => round( $execution_time ),
					'reasoning_content' => $response['reasoning_content'] ?? '',
					'usage'             => $response['usage'] ?? array(),
					'provider'          => $response['provider'] ?? '',
					'model_used'        => $response['model'] ?? '',
					'raw_response'      => $response['raw_response'] ?? array(),
					'prompt'            => $prompt_resolution['metadata'] ?? array(),
					'citation_sources'  => $citation_sources,
					'retrieval'         => $this->build_retrieval_parity(
						$single_raw_count,
						$single_qualified_count,
						$single_selected_count
					),
					'policy'            => $this->build_policy_parity(
						'single',
						$single_decision,
						$single_reason_code,
						$single_profile['id'],
						array(
							'agentic_model_active'   => ! empty( $options['rewrite_model'] ),
							'embedding_model_active' => ! empty( $retrieval_policy['embedding_model_active'] ),
							'lexical_active'         => ! empty( $retrieval_policy['lexical_active'] ),
							'retrieval_query_class'  => $retrieval_policy['query_class'] ?? 'general',
							'retrieval_default_mode' => $retrieval_policy['default_mode'] ?? 'hybrid',
							'retrieval_mode_source'  => $retrieval_policy['mode_source'] ?? 'query_class_default',
							'retrieval_mode'         => $retrieval_policy['mode'] ?? 'hybrid',
							'retrieval_policy_id'    => $retrieval_policy['id'] ?? 'retrieval_single_hybrid',
							'rerank_model_active'    => ! empty( $options['rerank_model_id'] ),
							'thresholds'             => $single_profile,
							'candidate_post_limit'   => 6,
							'prefilter_per_post'     => 2,
							'final_chunk_limit'      => 6,
							'neighbor_expand_top_n'  => 2,
						)
					),
				),
				'single'
			),
		);

		$this->emit_governance_decision( $query, $result['metadata']['policy'], $result['metadata']['retrieval'], $options );

		return $result;
	}

				/**
				 * Build deterministic compare slot names for coverage evaluation.
				 *
				 * @since 1.0.0
				 * @param array $entities Ordered array of entity strings.
				 * @return array Slot map keyed by entity_0..entity_N.
				 */
	private function build_compare_slots( $entities ) {
		$slots = array();
		foreach ( array_values( $entities ) as $idx => $entity ) {
			$slots[ 'entity_' . $idx ] = $entity;
		}

		return $slots;
	}

				/**
				 * Format an array of entity names into a readable list string.
				 *
				 * Examples:
				 *  - ['A']           → '"A"'
				 *  - ['A', 'B']      → '"A" and "B"'
				 *  - ['A', 'B', 'C'] → '"A", "B", and "C"'
				 *
				 * @since 1.0.0
				 * @param array  $entities    Array of entity name strings.
				 * @param string $conjunction Final conjunction word. Default 'or'.
				 * @return string Formatted list.
				 */
	private function format_entity_list( $entities, $conjunction = 'or' ) {
		$quoted = array_map(
			function ( $e ) {
				return '"' . esc_html( $e ) . '"';
			},
			array_values( $entities )
		);
		$count  = count( $quoted );

		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $quoted[0];
		}
		if ( 2 === $count ) {
			return $quoted[0] . ' ' . $conjunction . ' ' . $quoted[1];
		}

		$last = array_pop( $quoted );
		return implode( ', ', $quoted ) . ', ' . $conjunction . ' ' . $last;
	}

				/**
				 * Ensure policy/retrieval metadata contract keys are always present.
				 *
				 * @since 1.0.0
				 * @param array  $metadata    Response metadata.
				 * @param string $intent_hint Intent hint: single|compare.
				 * @return array Normalized metadata.
				 */
	private function ensure_policy_retrieval_contract( $metadata, $intent_hint = 'single' ) {
		$intent  = in_array( $intent_hint, array( 'single', 'compare' ), true ) ? $intent_hint : 'single';
		$profile = $this->get_policy_threshold_profile( $intent );
		$missing = array();

		$policy = isset( $metadata['policy'] ) && is_array( $metadata['policy'] ) ? $metadata['policy'] : array();
		if ( empty( $policy ) ) {
			$missing[] = 'policy';
		}

		$policy_defaults = array(
			'intent'                 => $intent,
			'decision'               => 'abstain',
			'reason_code'            => 'contract_guard_default',
			'threshold_profile'      => $profile['id'],
			'agentic_model_active'   => false,
			'embedding_model_active' => ! empty( $this->embedding_model_key ),
			'lexical_active'         => true,
			'retrieval_mode'         => 'hybrid',
			'retrieval_policy_id'    => 'retrieval_' . $intent . '_hybrid',
			'rerank_model_active'    => false,
		);
		$policy          = array_merge( $policy_defaults, $policy );

		foreach ( array( 'intent', 'decision', 'reason_code', 'threshold_profile', 'agentic_model_active', 'embedding_model_active', 'lexical_active', 'retrieval_mode', 'retrieval_policy_id', 'rerank_model_active' ) as $policy_key ) {
			if ( ! isset( $metadata['policy'][ $policy_key ] ) ) {
				$missing[] = 'policy.' . $policy_key;
			}
		}

		$policy['agentic_model_active']   = (bool) $policy['agentic_model_active'];
		$policy['embedding_model_active'] = (bool) $policy['embedding_model_active'];
		$policy['lexical_active']         = (bool) $policy['lexical_active'];
		$policy['rerank_model_active']    = (bool) $policy['rerank_model_active'];

		$retrieval = isset( $metadata['retrieval'] ) && is_array( $metadata['retrieval'] ) ? $metadata['retrieval'] : array();
		if ( empty( $retrieval ) ) {
			$missing[] = 'retrieval';
		}

		$retrieval_defaults = array(
			'raw_count'       => 0,
			'qualified_count' => 0,
			'selected_count'  => 0,
		);
		$retrieval          = array_merge( $retrieval_defaults, $retrieval );

		foreach ( array( 'raw_count', 'qualified_count', 'selected_count' ) as $retrieval_key ) {
			if ( ! isset( $metadata['retrieval'][ $retrieval_key ] ) ) {
				$missing[] = 'retrieval.' . $retrieval_key;
			}
			$retrieval[ $retrieval_key ] = (int) $retrieval[ $retrieval_key ];
		}

		$metadata['policy']    = $policy;
		$metadata['retrieval'] = $retrieval;

		if ( ! empty( $missing ) ) {
			$this->logger->log(
				'RAG Service: metadata contract guard filled missing parity fields',
				'warning',
				'rag',
				$this->connection_name,
				array(
					'intent'  => $intent,
					'missing' => array_values( array_unique( $missing ) ),
				)
			);
		}

		return $metadata;
	}
}
