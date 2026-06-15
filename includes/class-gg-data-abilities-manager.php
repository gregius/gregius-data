<?php
/**
 * Abilities Manager
 *
 * Registers plugin capabilities with the WordPress Abilities API.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities Manager Class
 */
class GG_Data_Abilities_Manager {

	/**
	 * Initialize the manager.
	 */
	public function init() {
		// Register categories first on their dedicated hook.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		// Then register abilities on the abilities hook.
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register ability categories.
	 * Categories must be registered on wp_abilities_api_categories_init hook.
	 */
	public function register_categories() {
		// Check if the Abilities API is available.
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		// Register 'ai' category for AI-related abilities.
		wp_register_ability_category(
			'ai',
			array(
				'label'       => __( 'AI & Machine Learning', 'gregius-data' ),
				'description' => __( 'Artificial Intelligence and machine learning capabilities', 'gregius-data' ),
			)
		);

	}

	/**
	 * Register abilities if the API is available.
	 * Abilities must be registered on wp_abilities_api_init hook.
	 */
	public function register_abilities() {
		// Check if the Abilities API is available.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// 1. RAG: Answer Question.
		wp_register_ability(
			'gregius-data/answer',
			array(
				'label'               => __( 'Answer Question', 'gregius-data' ),
				'description'         => __( 'Searches site content semantically and answers a question using retrieved context. Requires connection_name, embedding_model, and answer_model. Use "list-connections" and "list-models" to discover valid values. Optional relevance reranking is available via rerank_model. Optional agentic orchestration is available via agentic_model.', 'gregius-data' ),
				'category'            => 'ai',
				'execute_callback'    => array( $this, 'execute_rag_answer' ),
				'permission_callback' => function () {
					return current_user_can( 'read' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'           => array(
							'type'        => 'string',
							'description' => __( 'The question to answer.', 'gregius-data' ),
							'required'    => true,
						),
						'connection_name' => array(
							'type'        => 'string',
							'description' => __( 'Required. The PostgreSQL connection to use. Use "list-connections" to see available connections.', 'gregius-data' ),
							'required'    => true,
						),
						'embedding_model' => array(
							'type'        => 'string',
							'description' => __( 'Required. The embedding model for semantic search. Use "list-models" with type "embeddings" to see options.', 'gregius-data' ),
							'required'    => true,
						),
						'agentic_model'   => array(
							'type'        => 'string',
							'description' => __( 'Optional. Model for query routing and tool selection. Use a fast, cheap LLM. Use "list-models" with type "llm" to see options.', 'gregius-data' ),
							'default'     => '',
						),
						'rerank_model'    => array(
							'type'        => 'string',
							'description' => __( 'Optional. Reranks retrieved documents for better relevance. Use "list-models" with type "rerank" to see options.', 'gregius-data' ),
							'default'     => '',
						),
						'answer_model'    => array(
							'type'        => 'string',
							'description' => __( 'Required. The LLM model for generating the final response. Use "list-models" with type "llm" to see options.', 'gregius-data' ),
							'required'    => true,
						),
						'messages'        => array(
							'type'        => 'array',
							'description' => __( 'Optional conversation history for context. Array of {role: "user"|"assistant", content: string}.', 'gregius-data' ),
							'default'     => array(),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'role'    => array( 'type' => 'string' ),
									'content' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'answer'   => array(
							'type'        => 'string',
							'description' => __( 'The generated answer.', 'gregius-data' ),
						),
						'sources'  => array(
							'type'        => 'array',
							'description' => __( 'List of sources used.', 'gregius-data' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'title' => array( 'type' => 'string' ),
									'url'   => array( 'type' => 'string' ),
								),
							),
						),
						'metadata' => array(
							'type'        => 'object',
							'description' => __( 'Metadata about the RAG response.', 'gregius-data' ),
							'properties'  => array(
								'tool'            => array(
									'type'        => 'string',
									'description' => __( 'Tool selected: search_content, summarize_conversation, respond_directly, or clarify_previous.', 'gregius-data' ),
								),
								'chunks_used'     => array( 'type' => 'integer' ),
								'embedding_model' => array( 'type' => 'string' ),
								'agentic_model'   => array( 'type' => 'string' ),
								'rerank_model'    => array( 'type' => 'string' ),
								'answer_model'    => array( 'type' => 'string' ),
								'connection'      => array( 'type' => 'string' ),
								'prompt'          => array( 'type' => 'object' ),
								'execution_time'  => array( 'type' => 'integer' ),
							),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// 2. Settings: List Connections.
		wp_register_ability(
			'gregius-data/list-connections',
			array(
				'label'               => __( 'List Data Connections', 'gregius-data' ),
				'description'         => __( 'Returns configured vector data connections and their status. Use this to get valid connection_name values for answer. Optionally include active embedding model context per connection. Supports PostgreSQL/PDO and PostgREST (Supabase-style REST) providers.', 'gregius-data' ),
				'category'            => 'ai',
				'execute_callback'    => array( $this, 'execute_list_connections' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_embedding_models' => array(
							'type'        => 'boolean',
							'description' => __( 'Optional. Include active embedding model keys for each connection.', 'gregius-data' ),
							'default'     => false,
						),
						'include_model_details'    => array(
							'type'        => 'boolean',
							'description' => __( 'Optional. Include safe model metadata for each active embedding model. Requires include_embedding_models=true.', 'gregius-data' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'connections' => array(
							'type'        => 'array',
							'description' => __( 'List of configured data connections.', 'gregius-data' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'name'             => array(
										'type'        => 'string',
										'description' => __( 'Connection identifier used in ability calls (for example, "default").', 'gregius-data' ),
									),
									'type'             => array(
										'type'        => 'string',
										'description' => __( 'Provider type. Common values: "postgresql" (direct/PDO) or "postgrest" (Supabase-style REST).', 'gregius-data' ),
									),
									'description'      => array(
										'type'        => 'string',
										'description' => __( 'Optional admin description for humans.', 'gregius-data' ),
									),
									'is_active'        => array(
										'type'        => 'boolean',
										'description' => __( 'Whether this connection is enabled for runtime use.', 'gregius-data' ),
									),
									'embedding_models' => array(
										'type'        => 'object',
										'description' => __( 'Optional embedding model context for this connection when requested.', 'gregius-data' ),
										'properties'  => array(
											'active_keys'  => array(
												'type'  => 'array',
												'description' => __( 'Active embedding model keys assigned to this connection.', 'gregius-data' ),
												'items' => array(
													'type' => 'string',
												),
											),
											'active_count' => array(
												'type' => 'integer',
												'description' => __( 'Count of active embedding model keys for this connection.', 'gregius-data' ),
											),
											'active'       => array(
												'type'  => 'array',
												'description' => __( 'Optional safe metadata for active embedding models when requested.', 'gregius-data' ),
												'items' => array(
													'type' => 'object',
													'properties' => array(
														'id'                => array( 'type' => 'string' ),
														'type'              => array( 'type' => 'string' ),
														'provider'          => array( 'type' => 'string' ),
														'label'             => array( 'type' => 'string' ),
														'is_active'         => array( 'type' => 'boolean' ),
														'dimensions'        => array( 'type' => 'integer' ),
														'description'       => array( 'type' => 'string' ),
														'provider_model_id' => array( 'type' => 'string' ),
													),
												),
											),
										),
									),
								),
							),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// 3. Models: List Models.
		wp_register_ability(
			'gregius-data/list-models',
			array(
				'label'               => __( 'List AI Models', 'gregius-data' ),
				'description'         => __( 'Returns registered AI models by type (embeddings, llm, rerank). Use this to get valid embedding_model, rerank_model, agentic_model, and answer_model values for answer.', 'gregius-data' ),
				'category'            => 'ai',
				'execute_callback'    => array( $this, 'execute_list_models' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'        => 'string',
							'description' => __( 'Filter by model type: "embeddings", "llm", or "rerank". Leave empty for all types.', 'gregius-data' ),
							'enum'        => array( '', 'embeddings', 'llm', 'rerank' ),
							'default'     => '',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'models' => array(
							'type'        => 'array',
							'description' => __( 'List of registered models.', 'gregius-data' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array(
										'type'        => 'string',
										'description' => __( 'Model identifier to use in API calls.', 'gregius-data' ),
									),
									'type'        => array(
										'type'        => 'string',
										'description' => __( 'Model type: embeddings, llm, or rerank.', 'gregius-data' ),
										'enum'        => array( 'embeddings', 'llm', 'rerank' ),
									),
									'provider'    => array(
										'type'        => 'string',
										'description' => __( 'Provider name (e.g., openai, voyage, local).', 'gregius-data' ),
									),
									'label'       => array(
										'type'        => 'string',
										'description' => __( 'Human-readable model name.', 'gregius-data' ),
									),
									'description' => array(
										'type'        => 'string',
										'description' => __( 'Model description.', 'gregius-data' ),
									),
									'dimensions'  => array(
										'type'        => 'integer',
										'description' => __( 'Vector dimensions (embeddings models only).', 'gregius-data' ),
									),
									'is_active'   => array(
										'type'        => 'boolean',
										'description' => __( 'Whether the model is currently active.', 'gregius-data' ),
									),
								),
							),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Execute RAG Answer ability.
	 *
	 * @param array                   $args       Arguments from the ability call.
	 * @param GG_Data_RAG_Service|null $rag_service Optional pre-built RAG service instance for reuse.
	 * @return array Response data.
	 */
	public function execute_rag_answer( $args, $rag_service = null ) {
		$query           = isset( $args['query'] ) ? $args['query'] : '';
		$connection_name = isset( $args['connection_name'] ) ? $args['connection_name'] : '';
		$embedding_model = isset( $args['embedding_model'] ) ? $args['embedding_model'] : '';
		$agentic_model   = isset( $args['agentic_model'] ) ? $args['agentic_model'] : '';
		$rerank_model    = isset( $args['rerank_model'] ) ? $args['rerank_model'] : '';
		$answer_model    = isset( $args['answer_model'] ) ? $args['answer_model'] : '';
		$prompt_id       = isset( $args['prompt_id'] ) ? absint( $args['prompt_id'] ) : 0;
		$prompt          = isset( $args['prompt'] ) ? sanitize_text_field( (string) $args['prompt'] ) : '';
		$conversation_id = isset( $args['conversation_id'] ) ? sanitize_text_field( (string) $args['conversation_id'] ) : '';
		$source          = isset( $args['source'] ) && is_array( $args['source'] ) ? $args['source'] : array();
		$messages        = isset( $args['messages'] ) && is_array( $args['messages'] ) ? $args['messages'] : array();

		if ( '' === $conversation_id || ! wp_is_uuid( $conversation_id ) ) {
			$conversation_id = wp_generate_uuid4();
		}

		$source_type = isset( $source['type'] ) ? sanitize_key( (string) $source['type'] ) : '';
		if ( '' === $source_type ) {
			$source_type = 'mcp';
		}

		$source['type'] = $source_type;

		if ( empty( $query ) ) {
			return new WP_Error( 'missing_query', __( 'Query is required.', 'gregius-data' ) );
		}

		if ( $prompt_id > 0 && '' !== $prompt ) {
			return new WP_Error( 'invalid_prompt_args', __( 'Use either prompt_id or prompt, not both.', 'gregius-data' ) );
		}

		if ( $prompt_id <= 0 && '' !== $prompt ) {
			$resolved_prompt_id = $this->resolve_prompt_identifier( $prompt );

			if ( is_wp_error( $resolved_prompt_id ) ) {
				return $resolved_prompt_id;
			}

			$prompt_id = (int) $resolved_prompt_id;
		}

		// Instantiate RAG service.
		if ( ! class_exists( 'GG_Data_RAG_Service' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'rag/class-gg-data-rag-service.php';
		}

		// Build options for RAG pipeline.
		$options = array(
			'rewrite_model'   => $agentic_model,   // Internal name is still rewrite_model.
			'rerank_model_id' => $rerank_model,
			'prompt_id'       => $prompt_id,
			'conversation_id' => $conversation_id,
			'source'          => $source,
			'messages'        => $messages,
		);

		if ( $rag_service instanceof GG_Data_RAG_Service ) {
			$rag = $rag_service;
		} else {
			$rag = new GG_Data_RAG_Service( $connection_name, $embedding_model );
		}

		return $rag->generate_answer( $query, $answer_model, $options );
	}

	/**
	 * Resolve a prompt identifier (post ID, slug, or exact title) to a post ID.
	 *
	 * @since 1.0.0
	 * @param string $identifier Prompt identifier.
	 * @return int|WP_Error
	 */
	private function resolve_prompt_identifier( $identifier ) {
		$identifier = trim( (string) $identifier );

		if ( '' === $identifier ) {
			return 0;
		}

		if ( ctype_digit( $identifier ) ) {
			return $this->validate_prompt_post_id( absint( $identifier ) );
		}

		$slug = sanitize_title( $identifier );
		$post = get_page_by_path( $slug, OBJECT, GG_Data_Prompt::POST_TYPE );

		if ( $post instanceof WP_Post ) {
			return $this->validate_prompt_post_id( (int) $post->ID );
		}

		$matches = get_posts(
			array(
				'post_type'      => GG_Data_Prompt::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				's'              => $identifier,
				'posts_per_page' => 20,
			)
		);

		$exact_title_ids = array();
		foreach ( $matches as $match ) {
			if ( 0 === strcasecmp( $match->post_title, $identifier ) ) {
				$exact_title_ids[] = (int) $match->ID;
			}
		}

		if ( 1 === count( $exact_title_ids ) ) {
			return $this->validate_prompt_post_id( $exact_title_ids[0] );
		}

		if ( count( $exact_title_ids ) > 1 ) {
			return new WP_Error(
				'ambiguous_prompt_identifier',
				__( 'Prompt title is ambiguous. Use prompt_id with a specific gg_prompt post ID.', 'gregius-data' )
			);
		}

		return new WP_Error(
			'prompt_not_found',
			/* translators: %s: Prompt identifier supplied by caller (ID or title). */
			sprintf( __( 'Prompt "%s" was not found.', 'gregius-data' ), $identifier )
		);
	}

	/**
	 * Validate a gg_prompt post ID.
	 *
	 * @since 1.0.0
	 * @param int $prompt_id Prompt post ID.
	 * @return int|WP_Error
	 */
	private function validate_prompt_post_id( $prompt_id ) {
		$post = get_post( $prompt_id );

		if ( ! $post || GG_Data_Prompt::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'prompt_not_found',
				/* translators: %d: Prompt post ID. */
				sprintf( __( 'Prompt #%d was not found.', 'gregius-data' ), $prompt_id )
			);
		}

		return (int) $post->ID;
	}

	/**
	 * Execute List Connections ability.
	 *
	 * @param array $args Arguments from the ability call.
	 * @return array Response data.
	 */
	public function execute_list_connections( $args ) {
		$include_embedding_models = ! empty( $args['include_embedding_models'] );
		$include_model_details    = $include_embedding_models && ! empty( $args['include_model_details'] );

		if ( ! class_exists( 'GG_Data_Settings_Manager' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'class-gg-data-settings-manager.php';
		}

		$model_manager = null;
		if ( $include_model_details ) {
			if ( ! class_exists( 'GG_Data_Connection_Model_Manager' ) ) {
				require_once plugin_dir_path( __DIR__ ) . 'class-gg-data-connection-model-manager.php';
			}

			if ( class_exists( 'GG_Data_Connection_Model_Manager' ) ) {
				$model_manager = new GG_Data_Connection_Model_Manager();
			}
		}

		$settings    = new GG_Data_Settings_Manager();
		$connections = $settings->get_all_connections();
		$result      = array();

		foreach ( $connections as $name => $config ) {
			$connection = array(
				'name'        => $name,
				'type'        => isset( $config['type'] ) ? $config['type'] : 'postgresql',
				'description' => isset( $config['description'] ) ? $config['description'] : '',
				'is_active'   => isset( $config['is_active'] ) ? (bool) $config['is_active'] : true,
			);

			if ( $include_embedding_models ) {
				$active_model_keys = $settings->get_with_category( 'vectors', $name, 'active_models', array() );

				if ( ! is_array( $active_model_keys ) ) {
					$active_model_keys = array();
				}

				$active_model_keys = array_values(
					array_filter(
						array_map( 'strval', $active_model_keys ),
						'strlen'
					)
				);

				$embedding_models = array(
					'active_keys'  => $active_model_keys,
					'active_count' => count( $active_model_keys ),
				);

				if ( $include_model_details ) {
					$embedding_models['active'] = array();

					if ( null !== $model_manager ) {
						$active_models = $model_manager->get_connection_models( (string) $name );

						if ( is_array( $active_models ) ) {
							foreach ( $active_models as $model ) {
								$model_data = array(
									'id'        => isset( $model['model_key'] ) ? (string) $model['model_key'] : '',
									'type'      => isset( $model['model_type'] ) ? (string) $model['model_type'] : 'embeddings',
									'provider'  => isset( $model['provider'] ) ? (string) $model['provider'] : 'unknown',
									'label'     => isset( $model['label'] ) ? (string) $model['label'] : '',
									'is_active' => isset( $model['is_active'] ) ? (bool) $model['is_active'] : true,
								);

								if ( isset( $model['dimensions'] ) && '' !== (string) $model['dimensions'] ) {
									$model_data['dimensions'] = (int) $model['dimensions'];
								}

								if ( ! empty( $model['description'] ) ) {
									$model_data['description'] = (string) $model['description'];
								}

								if ( ! empty( $model['provider_model_id'] ) ) {
									$model_data['provider_model_id'] = (string) $model['provider_model_id'];
								}

								$embedding_models['active'][] = $model_data;
							}
						}
					}
				}

				$connection['embedding_models'] = $embedding_models;
			}

			$result[] = $connection;
		}

		return array( 'connections' => $result );
	}

	/**
	 * Execute List Models ability.
	 *
	 * @param array $args Arguments from the ability call.
	 * @return array Response data.
	 */
	public function execute_list_models( $args ) {
		$type_filter = isset( $args['type'] ) ? $args['type'] : '';

		if ( ! class_exists( 'GG_Data_Model_Registry' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'class-gg-data-model-registry.php';
		}

		$registry = new GG_Data_Model_Registry();

		$allowed_types = array( 'embeddings', 'llm', 'rerank' );
		$model_type    = ! empty( $type_filter ) && in_array( $type_filter, $allowed_types, true ) ? $type_filter : null;
		$models        = $registry->get_models( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $model_type );
		$result        = array();

		foreach ( $models as $model ) {
			$model_data = array(
				'id'        => isset( $model['model_key'] ) ? $model['model_key'] : '',
				'type'      => isset( $model['model_type'] ) ? $model['model_type'] : 'llm',
				'provider'  => isset( $model['provider'] ) ? $model['provider'] : 'unknown',
				'label'     => isset( $model['label'] ) ? $model['label'] : $model['model_key'],
				'is_active' => isset( $model['is_active'] ) ? (bool) $model['is_active'] : true,
			);

			// Add description if available.
			if ( ! empty( $model['description'] ) ) {
				$model_data['description'] = $model['description'];
			}

			// Add dimensions for embedding models.
			if ( 'embeddings' === $model_data['type'] && ! empty( $model['dimensions'] ) ) {
				$model_data['dimensions'] = (int) $model['dimensions'];
			}

			$result[] = $model_data;
		}

		return array( 'models' => $result );
	}
}
