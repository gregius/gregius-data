<?php
/**
 * REST API Controller for AI Model Management
 *
 * Handles CRUD operations for AI Model configurations.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/api
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for model management
 *
 * @since 1.0.0
 */
class GG_Data_REST_Models_Controller extends WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = 'gg-data/v1';

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'models';

	/**
	 * Model Registry instance
	 *
	 * @var GG_Data_Model_Registry
	 */
	private $model_registry;

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Global scope for model definition records.
	 *
	 * @var string
	 */
	private $model_scope;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new GG_Data_Settings_Manager();
		$this->model_registry   = new GG_Data_Model_Registry();
		$this->model_scope      = GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL;
	}

	/**
	 * Register the routes for the model endpoints
	 */
	public function register_routes() {

		// GET /gg-data/v1/models - Get all models.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_models' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'connection_name' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'            => array(
							'required'          => false,
							'type'              => 'string',
							'enum'              => array( 'embeddings', 'llm', 'rerank' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'provider'        => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_model' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_model_creation_args(),
				),
			)
		);

		// GET /gg-data/v1/models/providers - Get available providers.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/providers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_providers' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// POST /gg-data/v1/models/test - Test model configuration.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'test_model' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'config' => array(
							'description' => 'Model configuration to test',
							'type'        => 'object',
							'required'    => true,
							'properties'  => array(
								'provider' => array( 'type' => 'string' ),
								'api_key'  => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);

		// POST /gg-data/v1/models/{id}/reset-usage - Reset usage stats.
		// Note: Use a more permissive regex to handle model IDs with dots (e.g., rerank-2.5).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w\.\-]+)/reset-usage',
			array(
				'args' => array(
					'id' => array(
						'description'       => 'Model identifier (e.g., gpt-4o-mini).',
						'type'              => 'string',
						'required'          => true,
						// Do not sanitize - preserve dots in model IDs.
						'sanitize_callback' => function ( $value ) {
							return $value;
						},
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_model_usage' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// GET|PUT|DELETE /gg-data/v1/models/{id} - Single model operations.
		// Note: Use a more permissive regex to handle model IDs with dots (e.g., rerank-2.5).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w\.\-]+)',
			array(
				'args' => array(
					'id' => array(
						'description'       => 'Model identifier (e.g., gpt-4o-mini).',
						'type'              => 'string',
						'required'          => true,
						// Do not sanitize - preserve dots in model IDs.
						'sanitize_callback' => function ( $value ) {
							return $value;
						},
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_model' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_model' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_model_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_model' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);
	}

	/**
	 * Get all models with optional filtering
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_models( $request ) {
		try {
			$type_filter     = $request->get_param( 'type' );
			$provider_filter = $request->get_param( 'provider' );

			// Use Model Registry to fetch models.
			// Pass type filter to fetch only from the correct category.
			$models = $this->model_registry->get_models( $this->model_scope, $type_filter );

			if ( is_wp_error( $models ) ) {
				return $models;
			}

			// Apply additional filtering by model_type field for robustness.
			// This handles cases where models might have been stored in wrong category.
			if ( $type_filter ) {
				$models = array_filter(
					$models,
					function ( $model ) use ( $type_filter ) {
						// First check explicit model_type field.
						if ( isset( $model['model_type'] ) ) {
							return $model['model_type'] === $type_filter;
						}
						// Fallback to dimension-based detection for legacy data.
						if ( 'embeddings' === $type_filter ) {
							return isset( $model['dimensions'] ) && $model['dimensions'] > 0;
						}
						if ( 'llm' === $type_filter ) {
							return ! isset( $model['dimensions'] ) || 0 === $model['dimensions'];
						}
						if ( 'rerank' === $type_filter ) {
							return false; // Rerank models must have explicit model_type.
						}
						return true;
					}
				);
			}

			if ( $provider_filter ) {
				$models = array_filter(
					$models,
					function ( $model ) use ( $provider_filter ) {
						return isset( $model['provider'] ) && $model['provider'] === $provider_filter;
					}
				);
			}

			// Mask sensitive data.
			foreach ( $models as &$config ) {
				if ( isset( $config['config']['api_key'] ) ) {
					$config['config']['api_key'] = '***';
				}
				// Ensure ID is present for frontend compatibility.
				if ( ! isset( $config['id'] ) && isset( $config['model_key'] ) ) {
					$config['id'] = $config['model_key'];
				}
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array_values( $models ),
					'count'   => count( $models ),
					'filters' => array(
						'type'     => $type_filter,
						'provider' => $provider_filter,
					),
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'get_models_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get available providers
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_providers( $request ) {
		$providers = GG_Data_LLM_Registry::get_providers();
		$data      = array();

		foreach ( $providers as $id => $provider ) {
			$models = $provider->get_llm_models();

			// Include embedding models if available.
			$embedding_models = array();
			if ( method_exists( $provider, 'get_embedding_models' ) ) {
				foreach ( $provider->get_embedding_models() as $model_id => $model_config ) {
					$name                          = isset( $model_config['name'] ) ? $model_config['name'] : $model_id;
					$embedding_models[ $model_id ] = $name;
				}
			}

			// Include rerank models if available.
			$rerank_models = array();
			if ( method_exists( $provider, 'get_rerank_models' ) ) {
				foreach ( $provider->get_rerank_models() as $model_id => $model_config ) {
					$name                       = isset( $model_config['name'] ) ? $model_config['name'] : $model_id;
					$rerank_models[ $model_id ] = $name;
				}
			}

			$data[] = array(
				'id'               => $provider->get_id(),
				'name'             => $provider->get_name(),
				'capabilities'     => $provider->get_capabilities(),
				'llm_models'       => $models,
				'embedding_models' => $embedding_models,
				'rerank_models'    => $rerank_models,
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Create a new model
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function create_model( $request ) {
		$id     = $request->get_param( 'id' );
		$config = $request->get_param( 'config' );

		// Ensure ID starts with 'model_' if not present (legacy support).
		// For new models, we prefer using the ID as provided if it's descriptive.
		// But to maintain consistency, we can enforce a prefix or just use what's given.
		// The plan uses 'openai-3-small-1536' as model_key, which doesn't start with 'model_'.
		// So let's relax the 'model_' prefix requirement for new models, but keep it for legacy LLM models if needed.
		// Actually, let's just use the ID provided.

		// Validate required fields.
		$required = array( 'model_type', 'provider', 'provider_model_id' );
		foreach ( $required as $field ) {
			if ( empty( $config[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					"Missing required field: $field",
					array( 'status' => 400 )
				);
			}
		}

		// Always use provider_model_id as the model key (setting_key)
		// This ensures consistent model identification regardless of user input.
		$id = $config['provider_model_id'];

		// Check if model already exists.
		if ( $this->model_registry->model_exists( $this->model_scope, $id ) ) {
			return new WP_Error(
				'model_exists',
				/* translators: %s: Model ID */
				sprintf( __( 'Model "%s" already exists.', 'gregius-data' ), $id ),
				array( 'status' => 409 )
			);
		}

		// Get provider and validate capabilities.
		$provider = GG_Data_LLM_Registry::get_provider( $config['provider'] );
		if ( is_wp_error( $provider ) ) {
			return new WP_Error(
				'invalid_provider',
				'Provider not found: ' . $config['provider'],
				array( 'status' => 400 )
			);
		}

		$capabilities = $provider->get_capabilities();
		// Map 'embeddings' model type to 'embeddings' capability.
		// Map 'llm' model type to 'llm' capability.
		$required_capability = $config['model_type'];

		if ( ! in_array( $required_capability, $capabilities, true ) ) {
			return new WP_Error(
				'unsupported_capability',
				"Provider {$config['provider']} does not support {$config['model_type']}",
				array( 'status' => 400 )
			);
		}

		// Determine vector table name for embeddings.
		if ( 'embeddings' === $config['model_type'] ) {
			// Get model info from provider.
			$models     = $provider->get_embedding_models();
			$model_info = null;

			if ( isset( $models[ $config['provider_model_id'] ] ) ) {
				$model_info = $models[ $config['provider_model_id'] ];
			}

			if ( ! $model_info ) {
				return new WP_Error(
					'invalid_model',
					'Model not found: ' . $config['provider_model_id'],
					array( 'status' => 400 )
				);
			}

			$config['dimensions'] = $model_info['dimensions'];

			// Set vector table name.
			// For internal TF-IDF, use existing table.
			if ( 'internal' === $config['provider'] && 'tfidf-300' === $config['provider_model_id'] ) {
				$config['vector_table_name'] = 'wp_posts_tfidf_300';
			} else {
				// For API providers, use model-based naming: wp_posts_{provider}_{model_slug}_{dimensions}
				// This ensures each model gets its own dedicated vector table.
				$model_slug                  = str_replace( array( '-', '.' ), array( '_', '' ), $config['provider_model_id'] );
				$config['vector_table_name'] = sprintf(
					'wp_posts_%s_%s_%d',
					$config['provider'],
					$model_slug,
					$config['dimensions']
				);
			}
		}

		// Handle rerank models.
		if ( 'rerank' === $config['model_type'] ) {
			// Get model info from provider.
			$models     = $provider->get_rerank_models();
			$model_info = null;

			// Search for model by key (model ID) in the array.
			// Rerank models are keyed by model ID (e.g., 'rerank-2.5' => array('name' => 'Rerank 2.5', ...)).
			if ( isset( $models[ $config['provider_model_id'] ] ) ) {
				$model_info = $models[ $config['provider_model_id'] ];
			}

			if ( ! $model_info ) {
				return new WP_Error(
					'invalid_model',
					'Rerank model not found: ' . $config['provider_model_id'],
					array( 'status' => 400 )
				);
			}

			// Store rerank model config.
			$config['max_context'] = $model_info['max_context'] ?? 8000;
		}

		// Prevent saving masked API key.
		if ( isset( $config['config']['api_key'] ) && '***' === $config['config']['api_key'] ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid API key provided. Please enter the actual API key.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		try {
			// Add model_key to config as required by Model Registry.
			$config['model_key'] = $id;

			$success = $this->model_registry->add_model( $this->model_scope, $config );

			if ( ! $success ) {
				return new WP_Error( 'create_failed', 'Failed to create model', array( 'status' => 500 ) );
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Model created successfully', 'gregius-data' ),
					'data'    => array(
						'id'        => $id,
						'model_key' => $id,
					),
				),
				201
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'create_model_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get a single model
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_model( $request ) {
		// Extract ID from route directly to preserve dots (WordPress sanitizes URL params).
		$id = $this->extract_model_id_from_route( $request->get_route() );

		try {
			$model = $this->model_registry->get_model( $this->model_scope, $id );

			if ( ! $model ) {
				return new WP_Error( 'model_not_found', 'Model not found', array( 'status' => 404 ) );
			}

			// Ensure ID is present for frontend compatibility.
			if ( ! isset( $model['id'] ) && isset( $model['model_key'] ) ) {
				$model['id'] = $model['model_key'];
			}

			// Mask API key.
			if ( isset( $model['config']['api_key'] ) ) {
				$model['config']['api_key'] = '***';
			} elseif ( isset( $model['api_key'] ) ) {
				// Handle legacy structure if present.
				$model['api_key'] = '***';
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $model,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'get_model_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Update a model
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function update_model( $request ) {
		// Extract ID from route directly to preserve dots (WordPress sanitizes URL params).
		$route = $request->get_route();
		$id    = $this->extract_model_id_from_route( $route );

		$config = $request->get_param( 'config' );

		if ( ! $this->model_registry->model_exists( $this->model_scope, $id ) ) {
			return new WP_Error( 'model_not_found', 'Model not found', array( 'status' => 404 ) );
		}

		// If API key is masked, remove it from config so we don't overwrite the existing key with '***'.
		if ( isset( $config['api_key'] ) && '***' === $config['api_key'] ) {
			unset( $config['api_key'] );
		}

		try {
			$success = $this->model_registry->update_model( $this->model_scope, $id, $config );

			if ( ! $success ) {
				return new WP_Error( 'update_failed', 'Failed to update model', array( 'status' => 500 ) );
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Model updated successfully',
					'data'    => array( 'id' => $id ),
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'update_model_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Delete a model
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function delete_model( $request ) {
		// Extract ID from route directly to preserve dots (WordPress sanitizes URL params).
		$id = $this->extract_model_id_from_route( $request->get_route() );

		if ( ! $this->model_registry->model_exists( $this->model_scope, $id ) ) {
			return new WP_Error( 'model_not_found', 'Model not found', array( 'status' => 404 ) );
		}

		try {
			$success = $this->model_registry->delete_model( $this->model_scope, $id );

			if ( ! $success ) {
				return new WP_Error( 'delete_failed', 'Failed to delete model', array( 'status' => 500 ) );
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Model deleted successfully',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'delete_model_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Reset model usage stats
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function reset_model_usage( $request ) {
		// Extract ID from route directly to preserve dots (WordPress sanitizes URL params).
		$id              = $this->extract_model_id_from_route( $request->get_route() );
		$connection_name = $request->get_param( 'connection_name' );
		if ( empty( $connection_name ) ) {
			$connection_name = 'default';
		}

		if ( ! $this->model_registry->model_exists( $this->model_scope, $id ) ) {
			return new WP_Error( 'model_not_found', 'Model not found', array( 'status' => 404 ) );
		}

		try {
			// Reset by deleting model usage keys in settings.
			$this->settings_manager->delete_with_category( 'model_usage', $connection_name, $id );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Model usage reset successfully',
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'reset_usage_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Test model configuration
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function test_model( $request ) {
		$config   = $request->get_param( 'config' );
		$provider = $config['provider'] ?? 'openai';
		$api_key  = $config['api_key'] ?? '';

		// If API key is masked, we need to fetch the real one if we are editing an existing model.
		// But wait, this endpoint is stateless. If the user sends '***', we can't test it unless we know the ID.
		// The user might be testing a NEW model, so they must provide the key.
		// If they are editing, the frontend should probably send the key if it's changed, or we need to handle the case where they want to test the *existing* key.
		// If the frontend sends '***', we can't test it.
		// However, if we pass the ID in the request, we could look it up.
		// Let's check if we can pass an ID.

		// Ideally, the frontend sends the key if it's new. If it's existing and unchanged (masked), the frontend might not have the key.
		// So we should allow passing 'id' optionally. If 'id' is passed and 'api_key' is '***' or empty, we load the existing key.

		$id = $request->get_param( 'id' );

		if ( ( empty( $api_key ) || '***' === $api_key ) && ! empty( $id ) ) {
			$existing = $this->model_registry->get_model( $this->model_scope, $id );
			if ( $existing && ! empty( $existing['config']['api_key'] ) ) {
				$api_key = $existing['config']['api_key'];
			} elseif ( $existing && ! empty( $existing['api_key'] ) ) {
				$api_key = $existing['api_key'];
			}
		}

		if ( empty( $api_key ) || '***' === $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'Please provide an API key to test.', 'gregius-data' ), array( 'status' => 400 ) );
		}

		try {
			if ( 'openai' === $provider ) {
				$client = new GG_Data_OpenAI_Client( $api_key );
				$result = $client->validate_api_key();

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Connection successful! API key is valid.', 'gregius-data' ),
					),
					200
				);
			} elseif ( 'deepseek' === $provider ) {
				$deepseek_provider = new GG_Data_DeepSeek_Provider();
				$result            = $deepseek_provider->validate_api_key( $api_key );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Connection successful! DeepSeek API key is valid.', 'gregius-data' ),
					),
					200
				);
			} elseif ( 'anthropic' === $provider ) {
				$anthropic_provider = new GG_Data_Anthropic_Provider();
				$result             = $anthropic_provider->validate_api_key( $api_key );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Connection successful! Anthropic API key is valid.', 'gregius-data' ),
					),
					200
				);
			} elseif ( 'gemini' === $provider ) {
				$gemini_provider = new GG_Data_Gemini_Provider();
				$result          = $gemini_provider->validate_api_key( $api_key );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Connection successful! Google AI API key is valid.', 'gregius-data' ),
					),
					200
				);
			} elseif ( 'voyage' === $provider ) {
				$voyage_provider = new GG_Data_Voyage_Provider();
				$result          = $voyage_provider->validate_api_key( $api_key );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Connection successful! Voyage AI API key is valid.', 'gregius-data' ),
					),
					200
				);
			} elseif ( 'cohere' === $provider ) {
				$cohere_provider = new GG_Data_Cohere_Provider();
				$result          = $cohere_provider->validate_api_key( $api_key );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Connection successful! Cohere API key is valid.', 'gregius-data' ),
					),
					200
				);
			} else {
				// Placeholder for other providers.
				return new WP_Error( 'provider_not_supported', __( 'Provider testing not supported yet.', 'gregius-data' ), array( 'status' => 501 ) );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'test_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Check admin permission
	 *
	 * @return bool|WP_Error True if allowed.
	 */
	public function check_admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permissions.', 'gregius-data' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Extract model ID from route path.
	 *
	 * WordPress REST API sanitizes URL params and converts dots to hyphens.
	 * This method extracts the raw model ID directly from the route path
	 * to preserve dots in model IDs like "rerank-2.5".
	 *
	 * @param string $route The REST API route (e.g., /gg-data/v1/models/rerank-2.5).
	 * @return string|null The model ID or null if not found.
	 */
	private function extract_model_id_from_route( $route ) {
		// Match /models/{id} or /models/{id}/reset-usage patterns.
		if ( preg_match( '#/models/([^/]+)(?:/reset-usage)?$#', $route, $matches ) ) {
			return urldecode( $matches[1] );
		}
		return null;
	}

	/**
	 * Sanitize model ID parameter.
	 *
	 * Allows alphanumeric characters, dots, hyphens, and underscores.
	 * This is needed because model IDs like "rerank-2.5" contain dots
	 * which sanitize_text_field() may not preserve correctly.
	 *
	 * @param string $value The model ID to sanitize.
	 * @return string Sanitized model ID.
	 */
	public function sanitize_model_id( $value ) {
		// Remove any characters that aren't alphanumeric, dots, hyphens, or underscores.
		return preg_replace( '/[^a-zA-Z0-9.\-_]/', '', $value );
	}

	/**
	 * Get model creation args
	 *
	 * @return array Args.
	 */
	public function get_model_creation_args() {
		return array(
			'id'     => array(
				'description'       => 'Model ID (optional - auto-generated from provider_model_id if not provided)',
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_title',
			),
			'config' => array(
				'description' => 'Model configuration',
				'type'        => 'object',
				'required'    => true,
				'properties'  => array(
					'model_type'     => array( 'type' => 'string' ),
					'provider'       => array( 'type' => 'string' ),
					'api_key'        => array( 'type' => 'string' ),
					'model_name'     => array( 'type' => 'string' ),
					'max_tokens'     => array( 'type' => 'integer' ),
					'context_window' => array( 'type' => 'integer' ),
				),
			),
		);
	}

	/**
	 * Get model update args
	 *
	 * @return array Args.
	 */
	public function get_model_update_args() {
		$args                   = $this->get_model_creation_args();
		$args['id']['required'] = false;
		return $args;
	}
}
