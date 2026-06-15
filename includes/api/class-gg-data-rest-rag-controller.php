<?php
/**
 * Generic RAG REST API Controller
 *
 * Provides a unified endpoint for RAG with any embedding model and LLM.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic RAG REST Controller class.
 *
 * Handles /gg-data/v1/rag/chat endpoint.
 *
 * @since 1.0.0
 */
class GG_Data_REST_RAG_Controller extends WP_REST_Controller {

	/**
	 * Rate limit transient key prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const RATE_LIMIT_KEY_PREFIX = 'gg_data_rag_rl_';

	/**
	 * Default anonymous request limit per time window.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RATE_LIMIT_ANON = 10;

	/**
	 * Default authenticated request limit per time window.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RATE_LIMIT_AUTHENTICATED = 30;

	/**
	 * Default rate limit window in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RATE_LIMIT_WINDOW_SECONDS = 60;

	/**
	 * REST API namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'gg-data/v1';

	/**
	 * REST API base route.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'rag';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// POST /gg-data/v1/rag/chat - Generic RAG endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/chat',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_answer' ),
					'permission_callback' => array( $this, 'get_permission_callback' ),
					'args'                => $this->get_chat_params(),
				),
			)
		);

		// POST /gg-data/v1/rag/action - User-triggered tool action.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/action',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'execute_action' ),
					'permission_callback' => array( $this, 'get_permission_callback' ),
					'args'                => $this->get_action_params(),
				),
			)
		);

		// GET /gg-data/v1/rag/actions - List available user-triggerable actions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/actions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_actions' ),
					'permission_callback' => array( $this, 'get_permission_callback' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'PostgreSQL connection name.', 'gregius-data' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for endpoints.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function get_permission_callback( $request ) {
		/**
		 * Filter RAG endpoint permissions.
		 *
		 * @since 1.0.0
		 * @param bool            $allowed True to allow, false to deny.
		 * @param WP_REST_Request $request Request object.
		 */
		$allowed = apply_filters( 'gg_data_rag_endpoint_permission', false, $request );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		if ( true !== $allowed ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'gregius-data' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Generate answer using RAG.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function generate_answer( $request ) {
		$rate_limited = $this->check_rate_limit( $request, 'chat' );
		if ( is_wp_error( $rate_limited ) ) {
			return $rate_limited;
		}

		$query               = $request->get_param( 'query' );
		$connection_name     = $request->get_param( 'connection_name' );
		$embedding_model_key = $request->get_param( 'embedding_model_key' );
		$llm_model_id        = $request->get_param( 'llm_model_id' );
		$rewrite_model       = $request->get_param( 'rewrite_model' );
		$rerank_model_id     = $request->get_param( 'rerank_model_id' );
		$conversation_id     = $request->get_param( 'conversation_id' );
		$messages            = $request->get_param( 'messages' );
		$prompt_id           = $request->get_param( 'prompt_id' );
		$security_prompt_id  = $request->get_param( 'security_prompt_id' );
		$source_turn_index   = absint( $request->get_param( 'source_turn_index' ) );
		$metadata_filter     = $request->get_param( 'metadata_filter' );
		$metadata_manifest   = $request->get_param( 'metadata_manifest' );
		$manifest            = $request->get_param( 'manifest' );
		$forced_tool         = $request->get_param( 'forced_tool' );

		// Validate required parameters.
		if ( empty( $query ) ) {
			return new WP_Error(
				'gg_data_missing_query',
				__( 'Query parameter is required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $connection_name ) ) {
			return new WP_Error(
				'gg_data_missing_connection',
				__( 'Connection name is required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $embedding_model_key ) ) {
			return new WP_Error(
				'gg_data_missing_embedding_model',
				__( 'Embedding model key is required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $llm_model_id ) ) {
			return new WP_Error(
				'gg_data_missing_llm_model',
				__( 'LLM model ID is required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		// Create RAG service instance.
		$rag = new GG_Data_RAG_Service( $connection_name, $embedding_model_key );

		// Get optional parameters.
		$num_chunks = $request->get_param( 'num_chunks' );
		if ( null === $num_chunks || '' === $num_chunks ) {
			$num_chunks = 5;
		}

		$temperature = $request->get_param( 'temperature' );
		if ( null === $temperature || '' === $temperature ) {
			$temperature = 0.7;
		}

		// Only include post_types if explicitly provided in the request body - otherwise let the service use connection's synced types.
		$body           = $request->get_json_params();
		$request_source = array();

		if ( isset( $body['source'] ) ) {
			$request_source = $this->sanitize_source( $body['source'] );
		}

		$source_payload = array_merge(
			array( 'type' => 'rest' ),
			$request_source
		);

		$options = array(
			'num_chunks'         => $num_chunks,
			'temperature'        => $temperature,
			'rewrite_model'      => ! empty( $rewrite_model ) ? $rewrite_model : '',
			'rerank_model_id'    => ! empty( $rerank_model_id ) ? $rerank_model_id : '',
			'metadata_filter'    => is_array( $metadata_filter ) ? $metadata_filter : array(),
			'metadata_manifest'  => is_array( $metadata_manifest ) ? $metadata_manifest : array(),
			'manifest'           => is_array( $manifest ) ? $manifest : array(),
			'forced_tool'        => ! empty( $forced_tool ) ? sanitize_key( (string) $forced_tool ) : '',
			'conversation_id'    => ! empty( $conversation_id ) ? $conversation_id : '',
			'prompt_id'          => absint( $prompt_id ),
			'security_prompt_id' => absint( $security_prompt_id ),
			'source_turn_index'  => $source_turn_index,
			'source'             => $source_payload,
			'messages'           => is_array( $messages ) ? $messages : array(),
		);

		// Check if post_types was explicitly provided in the request (not just the default).
		if ( isset( $body['post_types'] ) && ! empty( $body['post_types'] ) ) {
			$options['post_types'] = $body['post_types'];
		}

		$precomputed_result = apply_filters(
			'gg_data_rag_precomputed_response',
			null,
			$query,
			$llm_model_id,
			$options,
			array(
				'transport' => 'rest',
				'request'   => $request,
			)
		);

		if ( is_wp_error( $precomputed_result ) ) {
			return $precomputed_result;
		}

		if ( is_array( $precomputed_result ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $this->shape_enterprise_response( $precomputed_result ),
				),
				200
			);
		}

		// Generate answer.
		$result = $rag->generate_answer( $query, $llm_model_id, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Build server-side references from citation_sources and content.
		// This makes citations portable across all consumers and aligns with frontend resolution logic.
		$citation_sources = isset( $result['citation_sources'] ) ? $result['citation_sources'] : array();
		$sources          = isset( $result['sources'] ) ? $result['sources'] : array();
		$answer_content   = isset( $result['answer'] ) ? $result['answer'] : '';

		// Ensure citation resolver class is available.
		if ( ! class_exists( 'GG_Data_Citation_Resolver' ) ) {
			require_once __DIR__ . '/../rag/class-gg-data-citation-resolver.php';
		}

		// Build references using server-side resolver.
		$result['references'] = GG_Data_Citation_Resolver::resolve_references(
			$answer_content,
			$citation_sources,
			$sources
		);

		do_action(
			'gg_data_rag_response_generated',
			$result,
			$query,
			$llm_model_id,
			$options,
			array(
				'transport' => 'rest',
				'request'   => $request,
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->shape_enterprise_response( $result ),
			),
			200
		);
	}

	/**
	 * Sanitize conversation messages array.
	 *
	 * Ensures each message has a valid role and sanitized content
	 * to prevent prompt injection and XSS vectors.
	 *
	 * @since 1.0.0
	 * @param array $messages Array of message objects.
	 * @return array Sanitized messages array.
	 */
	public function sanitize_messages( $messages ) {
		if ( ! is_array( $messages ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					function ( $msg ) {
						if ( ! is_array( $msg ) ) {
							return null;
						}
						$role = isset( $msg['role'] ) ? sanitize_text_field( $msg['role'] ) : '';
						// Only allow valid roles.
						if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
							return null;
						}
						return array(
							'role'    => $role,
							'content' => isset( $msg['content'] ) ? wp_kses_post( $msg['content'] ) : '',
						);
					},
					$messages
				)
			)
		);
	}

	/**
	 * Sanitize source payload for request metadata bridging.
	 *
	 * @since 1.0.0
	 * @param mixed $source Source payload.
	 * @return array
	 */
	public function sanitize_source( $source ) {
		if ( ! is_array( $source ) ) {
			return array();
		}

		$sanitized = array();

		if ( isset( $source['type'] ) ) {
			$sanitized['type'] = sanitize_key( (string) $source['type'] );
		}

		if ( isset( $source['post_id'] ) ) {
			$sanitized['post_id'] = absint( $source['post_id'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize metadata filter payload.
	 *
	 * Ensures metadata_filter is always an array/object-like payload for JSONB containment queries.
	 *
	 * @since 1.0.0
	 * @param mixed $metadata_filter Metadata filter payload.
	 * @return array Sanitized metadata filter.
	 */
	public function sanitize_metadata_filter( $metadata_filter ) {
		if ( ! is_array( $metadata_filter ) ) {
			return array();
		}

		return $metadata_filter;
	}

	/**
	 * Sanitize metadata manifest payload.
	 *
	 * @since 1.0.0
	 * @param mixed $metadata_manifest Metadata manifest payload.
	 * @return array Sanitized metadata manifest.
	 */
	public function sanitize_metadata_manifest( $metadata_manifest ) {
		if ( ! is_array( $metadata_manifest ) ) {
			return array();
		}

		return $metadata_manifest;
	}

	/**
	 * Sanitize full request manifest payload.
	 *
	 * @since 1.0.0
	 * @param mixed $manifest Manifest payload.
	 * @return array Sanitized manifest.
	 */
	public function sanitize_manifest( $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return array();
		}

		return $manifest;
	}

	/**
	 * Get parameter schema for chat endpoint.
	 *
	 * @since 1.0.0
	 * @return array Parameter schema.
	 */
	private function get_chat_params() {
		return array(
			'query'               => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'User query/question.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'connection_name'     => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'PostgreSQL connection name.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'embedding_model_key' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Embedding model key (e.g., tfidf-300, text-embedding-3-small).', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'llm_model_id'        => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'LLM model ID for answer generation.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'num_chunks'          => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 5,
				'description'       => __( 'Number of content chunks to retrieve.', 'gregius-data' ),
				'sanitize_callback' => 'absint',
			),
			'post_types'          => array(
				'required'    => false,
				'type'        => 'array',
				'description' => __( 'Post types to search. If not provided, uses connection synced types.', 'gregius-data' ),
				'items'       => array(
					'type' => 'string',
				),
			),
			'temperature'         => array(
				'required'          => false,
				'type'              => 'number',
				'default'           => 0.7,
				'description'       => __( 'LLM temperature (0-1).', 'gregius-data' ),
				'sanitize_callback' => function ( $value ) {
					return floatval( $value );
				},
			),
			'rewrite_model'       => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'LLM model ID for query rewriting. Empty to disable.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'rerank_model_id'     => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'Rerank model ID for result reranking. Empty to disable.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'conversation_id'     => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'Unique conversation ID for interaction tracking.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'prompt_id'           => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 0,
				'description'       => __( 'Selected prompt post ID.', 'gregius-data' ),
				'sanitize_callback' => 'absint',
			),
			'security_prompt_id'  => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 0,
				'description'       => __( 'Selected security prompt post ID.', 'gregius-data' ),
				'sanitize_callback' => 'absint',
			),
			'source_turn_index'   => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 0,
				'description'       => __( 'User turn index used for deterministic tool-context routing.', 'gregius-data' ),
				'sanitize_callback' => 'absint',
			),
			'messages'            => array(
				'required'          => false,
				'type'              => 'array',
				'default'           => array(),
				'description'       => __( 'Previous conversation messages for context.', 'gregius-data' ),
				'sanitize_callback' => array( $this, 'sanitize_messages' ),
				'items'             => array(
					'type'       => 'object',
					'properties' => array(
						'role'    => array(
							'type' => 'string',
							'enum' => array( 'user', 'assistant' ),
						),
						'content' => array(
							'type' => 'string',
						),
					),
				),
			),
			'source'              => array(
				'required'          => false,
				'type'              => 'object',
				'default'           => array(),
				'description'       => __( 'Optional request source metadata.', 'gregius-data' ),
				'sanitize_callback' => array( $this, 'sanitize_source' ),
			),
			'metadata_filter'     => array(
				'required'          => false,
				'type'              => 'object',
				'default'           => array(),
				'description'       => __( 'Optional structured metadata filter for deterministic retrieval narrowing.', 'gregius-data' ),
				'sanitize_callback' => array( $this, 'sanitize_metadata_filter' ),
			),
			'metadata_manifest'   => array(
				'required'          => false,
				'type'              => 'array',
				'default'           => array(),
				'description'       => __( 'Optional context manifest emitted by premium clients for safety-gated filtering.', 'gregius-data' ),
				'sanitize_callback' => array( $this, 'sanitize_metadata_manifest' ),
			),
			'manifest'            => array(
				'required'          => false,
				'type'              => 'object',
				'default'           => array(),
				'description'       => __( 'Optional full request manifest used for deterministic context handling.', 'gregius-data' ),
				'sanitize_callback' => array( $this, 'sanitize_manifest' ),
			),
			'forced_tool'         => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'Optional deterministic tool override.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Execute a user-triggered tool action.
	 *
	 * Allows frontend to directly invoke registered tools without LLM selection.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function execute_action( $request ) {
		$rate_limited = $this->check_rate_limit( $request, 'action' );
		if ( is_wp_error( $rate_limited ) ) {
			return $rate_limited;
		}

		$action_name     = $request->get_param( 'action' );
		$connection_name = $request->get_param( 'connection_name' );
		$llm_model_id    = $request->get_param( 'llm_model_id' );
		$params          = $request->get_param( 'params' );
		$messages        = $request->get_param( 'messages' );
		$conversation_id = $request->get_param( 'conversation_id' );
		$manifest        = $request->get_param( 'manifest' );

		// Create a temporary RAG service to access tool definitions.
		// We need an embedding model, but it may not be used for all tools.
		$embedding_model_key = $request->get_param( 'embedding_model_key' );
		if ( null === $embedding_model_key || '' === $embedding_model_key ) {
			$embedding_model_key = 'tfidf-300';
		}
		$rag = new GG_Data_RAG_Service( $connection_name, $embedding_model_key );

		// Get available tools via reflection (since get_tool_definitions is private).
		$reflection = new ReflectionMethod( $rag, 'get_tool_definitions' );
		$reflection->setAccessible( true );
		$tools = $reflection->invoke( $rag );

		// Check if action is a registered tool with user_action config.
		if ( ! isset( $tools[ $action_name ] ) ) {
			return new WP_Error(
				'gg_data_unknown_action',
				/* translators: %s: action name */
				sprintf( __( 'Unknown action: %s', 'gregius-data' ), $action_name ),
				array( 'status' => 400 )
			);
		}

		$tool = $tools[ $action_name ];

		// Check if tool is user-triggerable.
		if ( ! isset( $tool['user_action'] ) ) {
			return new WP_Error(
				'gg_data_action_not_allowed',
				/* translators: %s: action name */
				sprintf( __( 'Action "%s" cannot be triggered directly by users.', 'gregius-data' ), $action_name ),
				array( 'status' => 403 )
			);
		}

		// Build tool context.
		$tool_context = array(
			'query'           => $params['query'] ?? '',
			'llm_model_id'    => $llm_model_id,
			'options'         => array(
				'conversation_id' => $conversation_id,
				'messages'        => is_array( $messages ) ? $messages : array(),
				'manifest'        => is_array( $manifest ) ? $manifest : array(),
				'source'          => array( 'type' => 'rest-action' ),
			),
			'tool_selection'  => array_merge(
				array( 'tool' => $action_name ),
				is_array( $params ) ? $params : array()
			),
			'trigger'         => 'user',
			'connection_name' => $connection_name,
			'log_context'     => array(
				'action_name' => $action_name,
				'trigger'     => 'user',
			),
		);

		// Log the user-triggered action.
		$logger = new GG_Data_Logger();
		$logger->log(
			sprintf(
				'RAG Service: User triggered action "%s"',
				$action_name
			),
			'info',
			'rag',
			$connection_name,
			array(
				'action_name' => $action_name,
				'trigger'     => 'user',
				'params'      => $params,
			)
		);

		/**
		 * Filter to handle custom RAG tool execution.
		 *
		 * Same filter used for LLM-selected tools. $tool_context['trigger'] = 'user'.
		 *
		 * @since 1.0.0
		 * @param array|null $result       Return tool result array or null to skip.
		 * @param string     $action_name  The action/tool name.
		 * @param array      $tool_context Context including query, options, trigger='user'.
		 */
		$result = apply_filters( "gg_data_rag_tool_{$action_name}", null, $action_name, $tool_context );

		if ( null === $result ) {
			return new WP_Error(
				'gg_data_action_no_handler',
				/* translators: %s: action name */
				sprintf( __( 'No handler registered for action "%s".', 'gregius-data' ), $action_name ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a RAG tool executes (user-triggered).
		 *
		 * @since 1.0.0
		 * @param string $action_name  The action that was executed.
		 * @param array  $result       The action's result (or WP_Error).
		 * @param array  $tool_context Context including query, options, trigger source.
		 */
		do_action( 'gg_data_rag_tool_executed', $action_name, $result, $tool_context );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * List available user-triggerable actions.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function list_actions( $request ) {
		$connection_name = $request->get_param( 'connection_name' );

		// Create RAG service to get tool definitions.
		$rag = new GG_Data_RAG_Service( $connection_name, 'tfidf-300' );

		// Access private method via reflection.
		$reflection = new ReflectionMethod( $rag, 'get_tool_definitions' );
		$reflection->setAccessible( true );
		$tools = $reflection->invoke( $rag );

		// Filter to only user-triggerable actions.
		$actions = array();
		foreach ( $tools as $name => $tool ) {
			if ( isset( $tool['user_action'] ) ) {
				$actions[ $name ] = array(
					'name'        => $name,
					'description' => $tool['description'],
					'label'       => $tool['user_action']['label'] ?? $name,
					'icon'        => $tool['user_action']['icon'] ?? null,
					'parameters'  => $tool['parameters'] ?? array(),
				);
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'actions' => $actions,
				),
			),
			200
		);
	}

	/**
	 * Get parameter schema for action endpoint.
	 *
	 * @since 1.0.0
	 * @return array Parameter schema.
	 */
	private function get_action_params() {
		return array(
			'action'              => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Action/tool name to execute.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'connection_name'     => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'PostgreSQL connection name.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'llm_model_id'        => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'LLM model ID for response generation.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'embedding_model_key' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'tfidf-300',
				'description'       => __( 'Embedding model key (if action needs search).', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'params'              => array(
				'required'    => false,
				'type'        => 'object',
				'default'     => array(),
				'description' => __( 'Action-specific parameters.', 'gregius-data' ),
			),
			'conversation_id'     => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'Unique conversation ID for interaction tracking.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'messages'            => array(
				'required'    => false,
				'type'        => 'array',
				'default'     => array(),
				'description' => __( 'Previous conversation messages for context.', 'gregius-data' ),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'role'    => array(
							'type' => 'string',
							'enum' => array( 'user', 'assistant' ),
						),
						'content' => array(
							'type' => 'string',
						),
					),
				),
			),
			'manifest'            => array(
				'required'          => false,
				'type'              => 'object',
				'default'           => array(),
				'description'       => __( 'Optional full request manifest used for deterministic context handling.', 'gregius-data' ),
				'sanitize_callback' => array( $this, 'sanitize_manifest' ),
			),
		);
	}

	/**
	 * Check fixed-window rate limit for expensive RAG endpoints.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request  Request object.
	 * @param string          $endpoint Endpoint key (chat/action).
	 * @return true|WP_Error True when request is within limit, WP_Error otherwise.
	 */
	private function check_rate_limit( $request, $endpoint ) {
		$user_id  = get_current_user_id();
		$now      = time();
		$endpoint = sanitize_key( $endpoint );

		if ( $user_id > 0 ) {
			$scope = 'authenticated';
			$limit = (int) apply_filters( 'gg_data_rag_rate_limit_authenticated', self::RATE_LIMIT_AUTHENTICATED, $request, $endpoint );
			if ( $limit < 1 ) {
				return true;
			}
			$bucket = $endpoint . '_' . absint( $user_id );
		} else {
			$scope = 'anonymous';
			$limit = (int) apply_filters( 'gg_data_rag_rate_limit_anonymous', self::RATE_LIMIT_ANON, $request, $endpoint );
			if ( $limit < 1 ) {
				return true;
			}
			$bucket = $endpoint . '_' . wp_hash( $this->get_rate_limit_client_ip( $request ) );
		}

		$window = (int) apply_filters( 'gg_data_rag_rate_limit_window_seconds', self::RATE_LIMIT_WINDOW_SECONDS, $request, $endpoint, $scope );
		if ( $window < 1 ) {
			$window = self::RATE_LIMIT_WINDOW_SECONDS;
		}

		$transient_key = self::RATE_LIMIT_KEY_PREFIX . $scope . '_' . $bucket;
		$state         = get_transient( $transient_key );

		if ( ! is_array( $state ) || ! isset( $state['count'], $state['window_started'] ) ) {
			$state = array(
				'count'          => 0,
				'window_started' => $now,
			);
		}

		$count          = absint( $state['count'] );
		$window_started = absint( $state['window_started'] );
		$retry_after    = max( 1, ( $window_started + $window ) - $now );
		$is_blocked     = $count >= $limit;

		/**
		 * Fires when a rate-limit decision is made on a RAG endpoint.
		 *
		 * This action is fired for both allowed and blocked requests, after the rate-limit
		 * scope, limit, and window have been resolved (including filter overrides), but
		 * before the response is returned. It is suitable for logging, telemetry, and debugging
		 * rate-limit behavior.
		 *
		 * This action is observational only and must not mutate enforcement decisions.
		 *
		 * @since 1.0.0
		 * @param bool    $is_blocked     Whether the request is rate-limited (true = blocked).
		 * @param string  $scope          The rate-limit scope: 'authenticated' or 'anonymous'.
		 * @param string  $endpoint       The endpoint key being throttled (e.g., 'chat', 'action').
		 * @param int     $limit          The effective request limit for this scope and window.
		 * @param int     $window         The effective window duration in seconds.
		 * @param int     $current_count  The current request count in the active window.
		 * @param int     $retry_after    Seconds until the next window (if blocked).
		 * @param int     $user_id        The current user ID (0 for anonymous).
		 */
		do_action(
			'gg_data_rag_rate_limit_decision',
			$is_blocked,
			$scope,
			$endpoint,
			$limit,
			$window,
			$count,
			$retry_after,
			$user_id
		);

		if ( $is_blocked ) {
			return new WP_Error(
				'gg_data_rate_limited',
				__( 'Too many requests. Please retry later.', 'gregius-data' ),
				array(
					'status'      => 429,
					'retry_after' => $retry_after,
					'scope'       => $scope,
					'limit'       => $limit,
					'window'      => $window,
				)
			);
		}

		$state['count'] = $count + 1;
		$ttl            = max( 1, ( $window_started + $window ) - $now );

		set_transient( $transient_key, $state, $ttl );

		return true;
	}

	/**
	 * Get best-effort client IP for anonymous rate limiting.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return string
	 */
	private function get_rate_limit_client_ip( $request ) {
		$forwarded_for = $request->get_header( 'x-forwarded-for' );

		if ( ! empty( $forwarded_for ) ) {
			$parts = explode( ',', $forwarded_for );
			if ( ! empty( $parts[0] ) ) {
				return sanitize_text_field( trim( $parts[0] ) );
			}
		}

		$real_ip = $request->get_header( 'x-real-ip' );
		if ( ! empty( $real_ip ) ) {
			return sanitize_text_field( $real_ip );
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return 'unknown';
	}

	/**
	 * Shape the enterprise external response from the internal result.
	 *
	 * Extracts enterprise observability sections for the API response payload,
	 * omitting raw internal pipeline contract fields (answer, sources, metadata,
	 * citation_sources) that are retained internally for hooks, logging, and SSE.
	 *
	 * @since 1.0.0
	 * @param array $result Internal RAG result.
	 * @return array External enterprise response payload.
	 */
	private function shape_enterprise_response( array $result ) {
		$enterprise_keys = array(
			'request',
			'intent',
			'outcome',
			'execution',
			'retrieval_summary',
			'policy',
			'security',
			'context',
			'diagnostics',
			'legacy',
			'references',
		);

		$shaped = array();
		foreach ( $enterprise_keys as $key ) {
			if ( array_key_exists( $key, $result ) ) {
				$shaped[ $key ] = $result[ $key ];
			}
		}

		return $shaped;
	}
}
