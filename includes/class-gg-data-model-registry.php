<?php
/**
 * Model Registry for Gregius Data
 *
 * This file contains the GG_Data_Model_Registry class which manages
 * AI model configurations (LLM and embeddings) using the wp_gg_settings table.
 * Provides a thin wrapper around Settings Manager for model-specific operations.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Model Registry for Gregius Data
 *
 * Manages AI model configurations (LLM and embeddings) using category-based
 * storage in wp_gg_settings. Each model is stored as a single JSON row.
 *
 * Storage pattern:
 * - connection_name: Connection name (e.g., 'gregius-data')
 * - category: 'llm_model' or 'embeddings_model'
 * - setting_key: model_key (e.g., 'openai-3-small-1536')
 * - setting_value: JSON config (entire model configuration)
 * - data_type: 'json'
 *
 * @package Gregius_Data
 * @since 1.0.0
 */
class GG_Data_Model_Registry {
	/**
	 * Global scope key for model definitions.
	 */
	const MODEL_SCOPE_GLOBAL = '__global__';

	/**
	 * Settings Manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new GG_Data_Settings_Manager();
		$this->logger           = new GG_Data_Logger();
	}

	/**
	 * Add a new model
	 *
	 * @param string $connection_name Connection name (ignored for model definitions).
	 * @param array  $model_config {
	 *     Model configuration array.
	 *
	 *     @type string $model_key           Unique key (e.g., 'openai-3-small-1536').
	 *     @type string $provider            Provider ID (e.g., 'openai').
	 *     @type string $provider_model_id   Provider's model ID.
	 *     @type int    $dimensions          Vector dimensions (embeddings only).
	 *     @type string $vector_table_name   Table name (embeddings only).
	 *     @type string $status              'active' or 'inactive'.
	 *     @type array  $config              Provider-specific config (API key, etc.).
	 * }
	 * @return bool True on success, false on failure.
	 */
	public function add_model( $connection_name, array $model_config ) {
		unset( $connection_name );

		// Validate required fields.
		if ( empty( $model_config['model_key'] ) || empty( $model_config['provider'] ) ) {
			$this->logger->log( 'Model Registry: Missing required fields (model_key or provider)', 'error' );
			return false;
		}

		// Determine model type based on model_type field or dimensions field.
		if ( isset( $model_config['model_type'] ) && 'rerank' === $model_config['model_type'] ) {
			$model_type = 'rerank_model';
		} elseif ( isset( $model_config['dimensions'] ) ) {
			$model_type = 'embeddings_model';
		} else {
			$model_type = 'llm_model';
		}

		// Set defaults.
		$model_config = array_merge(
			array(
				'status' => 'active',
				'config' => array(),
			),
			$model_config
		);

		$this->logger->log(
			sprintf(
				'Model Registry: Adding %s model "%s" to global scope "%s"',
				$model_type,
				$model_config['model_key'],
				self::MODEL_SCOPE_GLOBAL
			),
			'info'
		);

		// Store entire model config as JSON in single row.
		$result = $this->settings_manager->set_with_category(
			$model_type,
			self::MODEL_SCOPE_GLOBAL,
			$model_config['model_key'],
			$model_config,
			'json'
		);

		if ( ! $result ) {
			$this->logger->log(
				sprintf(
					'Model Registry: Failed to add model "%s"',
					$model_config['model_key']
				),
				'error'
			);
		}

		return $result;
	}

	/**
	 * Get model by model_key
	 *
	 * @param string $connection_name Connection name (ignored for model definitions).
	 * @param string $model_key       Model key.
	 * @return array|null Model data or null if not found.
	 */
	public function get_model( $connection_name, $model_key ) {
		unset( $connection_name );

		if ( empty( $model_key ) ) {
			return null;
		}

		// Try embeddings first.
		$setting = $this->settings_manager->get_with_category(
			'embeddings_model',
			self::MODEL_SCOPE_GLOBAL,
			$model_key
		);

		if ( ! $setting ) {
			// Try LLM models.
			$setting = $this->settings_manager->get_with_category(
				'llm_model',
				self::MODEL_SCOPE_GLOBAL,
				$model_key
			);
		}

		if ( ! $setting ) {
			// Try rerank models.
			$setting = $this->settings_manager->get_with_category(
				'rerank_model',
				self::MODEL_SCOPE_GLOBAL,
				$model_key
			);
		}

		if ( ! $setting ) {
			return null;
		}

		// Decode JSON config (models are stored as JSON, not serialized PHP).
		if ( is_string( $setting ) ) {
			$config = json_decode( $setting, true );
		} else {
			$config = $setting;
		}

		// Ensure model_key is included in response.
		if ( is_array( $config ) ) {
			$config['model_key'] = $model_key;
		}

		return $config;
	}

	/**
	 * Get all models of a specific type
	 *
	 * @param string $connection_name Connection name (ignored for model definitions).
	 * @param string $model_type      Optional. 'embeddings', 'llm', or 'rerank'. Null returns all.
	 * @return array Array of models.
	 */
	public function get_models( $connection_name, $model_type = null ) {
		unset( $connection_name );

		$models = array();

		// Determine which categories to query.
		$categories = array();
		if ( 'embeddings' === $model_type || null === $model_type ) {
			$categories[] = 'embeddings_model';
		}
		if ( 'llm' === $model_type || null === $model_type ) {
			$categories[] = 'llm_model';
		}
		if ( 'rerank' === $model_type || null === $model_type ) {
			$categories[] = 'rerank_model';
		}

		// Map category names to model types.
		$category_type_map = array(
			'embeddings_model' => 'embeddings',
			'llm_model'        => 'llm',
			'rerank_model'     => 'rerank',
		);

		foreach ( $categories as $category ) {
			$settings = $this->settings_manager->get_by_category( $category, self::MODEL_SCOPE_GLOBAL );

			if ( empty( $settings ) ) {
				continue;
			}

			foreach ( $settings as $setting ) {
				// Handle JSON data type.
				if ( 'json' === $setting['data_type'] ) {
					$config = json_decode( $setting['setting_value'], true );
				} else {
					$config = maybe_unserialize( $setting['setting_value'] );
				}

				if ( ! is_array( $config ) ) {
					continue;
				}

				// Add metadata.
				$config['model_key']  = $setting['setting_key'];
				$config['model_type'] = $category_type_map[ $category ];

				$models[] = $config;
			}
		}

		return $models;
	}

	/**
	 * Update model usage statistics
	 *
	 * Tracks token usage for a model. Stores usage data in
	 * separate 'model_usage' category.
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_key       Model key.
	 * @param int    $tokens          Tokens used.
	 * @return bool True on success, false on failure.
	 */
	public function update_usage( $connection_name, $model_key, $tokens ) {
		if ( empty( $connection_name ) || empty( $model_key ) ) {
			return false;
		}

		// Get current usage from model_usage category.
		$current = $this->settings_manager->get_with_category(
			'model_usage',
			$connection_name,
			$model_key
		);

		// Initialize or decode existing usage (supports JSON and serialized formats).
		$usage = array(
			'total_tokens'  => 0,
			'total_queries' => 0,
		);

		if ( $current ) {
			// Try JSON decode first (current format).
			if ( is_string( $current ) ) {
				$decoded = json_decode( $current, true );
				if ( is_array( $decoded ) ) {
					$usage = array_merge( $usage, $decoded );
				} else {
					// Fallback to maybe_unserialize for legacy data.
					$unserialized = maybe_unserialize( $current );
					if ( is_array( $unserialized ) ) {
						$usage = array_merge( $usage, $unserialized );
					}
				}
			} elseif ( is_array( $current ) ) {
				$usage = array_merge( $usage, $current );
			}
		}

		// Update counters.
		$usage['total_tokens'] += $tokens;
		++$usage['total_queries'];
		$usage['last_used_at'] = current_time( 'mysql' );

		$this->logger->log(
			sprintf(
				'Model Registry: Updated usage for model "%s": +%d tokens',
				$model_key,
				$tokens
			),
			'debug'
		);

		// Save updated usage as JSON.
		return $this->settings_manager->set_with_category(
			'model_usage',
			$connection_name,
			$model_key,
			$usage,
			'json'
		);
	}

	/**
	 * Get usage statistics for a model
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_key       Model key.
	 * @return array|null Usage statistics or null if no usage recorded.
	 */
	public function get_usage( $connection_name, $model_key ) {
		if ( empty( $connection_name ) || empty( $model_key ) ) {
			return null;
		}

		$usage = $this->settings_manager->get_with_category(
			'model_usage',
			$connection_name,
			$model_key
		);

		return $usage ? maybe_unserialize( $usage ) : null;
	}

	/**
	 * Delete model
	 *
	 * Removes model from both embeddings_model and llm_model categories,
	 * as well as associated usage data.
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_key       Model key.
	 * @return bool True on success, false on failure.
	 */
	public function delete_model( $connection_name, $model_key ) {
		if ( empty( $model_key ) ) {
			return false;
		}

		$this->logger->log(
			sprintf(
				'Model Registry: Deleting model "%s" from global scope "%s"',
				$model_key,
				self::MODEL_SCOPE_GLOBAL
			),
			'info'
		);

		// Delete from both categories (in case model type changed).
		$this->settings_manager->delete_with_category(
			'embeddings_model',
			self::MODEL_SCOPE_GLOBAL,
			$model_key
		);

		$this->settings_manager->delete_with_category(
			'llm_model',
			self::MODEL_SCOPE_GLOBAL,
			$model_key
		);

		$this->settings_manager->delete_with_category(
			'rerank_model',
			self::MODEL_SCOPE_GLOBAL,
			$model_key
		);

		// Delete usage data only for concrete connection scopes.
		if ( ! empty( $connection_name ) && self::MODEL_SCOPE_GLOBAL !== $connection_name ) {
			$this->settings_manager->delete_with_category(
				'model_usage',
				$connection_name,
				$model_key
			);
		}

		return true;
	}

	/**
	 * Check if model exists
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_key       Model key.
	 * @return bool True if model exists, false otherwise.
	 */
	public function model_exists( $connection_name, $model_key ) {
		return null !== $this->get_model( $connection_name, $model_key );
	}

	/**
	 * Update model configuration
	 *
	 * Updates specific fields in a model's configuration without replacing
	 * the entire config.
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_key       Model key.
	 * @param array  $updates         Fields to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_model( $connection_name, $model_key, array $updates ) {
		// Get existing model.
		$existing = $this->get_model( $connection_name, $model_key );

		if ( ! $existing ) {
			$this->logger->log(
				sprintf(
					'Model Registry: Cannot update non-existent model "%s"',
					$model_key
				),
				'error'
			);
			return false;
		}

		// Merge updates with existing config.
		$updated_config = array_merge( $existing, $updates );

		// Preserve model_key.
		$updated_config['model_key'] = $model_key;

		// Re-add using add_model (will replace existing).
		return $this->add_model( $connection_name, $updated_config );
	}

	/**
	 * Get models by provider
	 *
	 * @param string $connection_name Connection name.
	 * @param string $provider        Provider ID (e.g., 'openai').
	 * @param string $model_type      Optional. Filter by type ('embeddings', 'llm').
	 * @return array Array of models from the specified provider.
	 */
	public function get_models_by_provider( $connection_name, $provider, $model_type = null ) {
		$all_models = $this->get_models( $connection_name, $model_type );

		return array_filter(
			$all_models,
			function ( $model ) use ( $provider ) {
				return isset( $model['provider'] ) && $model['provider'] === $provider;
			}
		);
	}

	/**
	 * Get active models only
	 *
	 * @param string $connection_name Connection name.
	 * @param string $model_type      Optional. Filter by type ('embeddings', 'llm').
	 * @return array Array of active models.
	 */
	public function get_active_models( $connection_name, $model_type = null ) {
		$all_models = $this->get_models( $connection_name, $model_type );

		return array_filter(
			$all_models,
			function ( $model ) {
				return isset( $model['status'] ) && 'active' === $model['status'];
			}
		);
	}
}
