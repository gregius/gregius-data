<?php
/**
 * Vector Generator Orchestrator
 *
 * Routes vector generation requests to appropriate strategy implementations
 * based on model configuration (TF-IDF vs API embeddings).
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/vectors
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GG_Data_Vector_Generator
 *
 * Orchestrates vector generation by routing to the appropriate strategy
 * based on model type and provider.
 *
 * @since 1.0.0
 */
class GG_Data_Vector_Generator {

	/**
	 * Registered vector generation strategies
	 *
	 * @var GG_Data_Vector_Strategy_Interface[]
	 */
	private $strategies = array();

	/**
	 * Model registry instance
	 *
	 * @var GG_Data_Model_Registry
	 */
	private $model_registry;

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
		$this->model_registry = new GG_Data_Model_Registry();
		$this->logger         = new GG_Data_Logger();

		// Register built-in strategies.
		$this->register_default_strategies();
	}

	/**
	 * Register default vector generation strategies
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_default_strategies(): void {
		// Register TF-IDF strategy (internal, free).
		require_once GG_DATA_PLUGIN_DIR . 'includes/vectors/strategies/class-gg-data-tfidf-strategy.php';
		$this->register_strategy( new GG_Data_TFIDF_Strategy() );

		// Register API embeddings strategy (OpenAI, Voyage AI, etc).
		require_once GG_DATA_PLUGIN_DIR . 'includes/vectors/strategies/class-gg-data-api-embeddings-strategy.php';
		$this->register_strategy( new GG_Data_API_Embeddings_Strategy() );

		// Register HashingTF strategy (internal, free, stateless).
		require_once GG_DATA_PLUGIN_DIR . 'includes/vectors/strategies/class-gg-data-hashingtf-strategy.php';
		$this->register_strategy( new GG_Data_HashingTF_Strategy() );
	}

	/**
	 * Register a vector generation strategy
	 *
	 * @since 1.0.0
	 * @param GG_Data_Vector_Strategy_Interface $strategy Strategy implementation.
	 * @return void
	 */
	public function register_strategy( GG_Data_Vector_Strategy_Interface $strategy ): void {
		$this->strategies[ $strategy->get_id() ] = $strategy;

		$this->logger->log(
			sprintf(
				'Vector Generator: Registered strategy "%s" (%s)',
				$strategy->get_name(),
				$strategy->get_id()
			),
			'debug',
			'vectors',
			null,
			array(
				'strategy_id'   => $strategy->get_id(),
				'strategy_name' => $strategy->get_name(),
			)
		);
	}

	/**
	 * Generate vectors for a batch of posts
	 *
	 * Main entry point for vector generation. Routes request to appropriate
	 * strategy based on model configuration.
	 *
	 * @since 1.0.0
	 * @param string $model_key       Model identifier from registry.
	 * @param int    $batch_size      Number of posts to process.
	 * @param string $connection_name PostgreSQL connection name.
	 * @return array|WP_Error Generation result or error.
	 */
	public function generate_batch( string $model_key, int $batch_size, string $connection_name ) {
		$this->logger->log(
			sprintf(
				'Vector Generator: Starting batch generation (model=%s, batch_size=%d, connection=%s)',
				$model_key,
				$batch_size,
				$connection_name
			),
			'info',
			'vectors',
			$connection_name,
			array(
				'model_key'  => $model_key,
				'batch_size' => $batch_size,
			)
		);

		// 1. Get model configuration from registry.
		$model = $this->model_registry->get_model( $connection_name, $model_key );

		// Fallback to 'gregius-data' for global models if not found in connection-specific storage.
		if ( ! $model && 'gregius-data' !== $connection_name ) {
			$this->logger->log(
				sprintf(
					'Vector Generator: Model "%s" not found for connection "%s", trying global fallback "gregius-data"',
					$model_key,
					$connection_name
				),
				'debug',
				'vectors',
				$connection_name,
				array( 'model_key' => $model_key )
			);
			$model = $this->model_registry->get_model( 'gregius-data', $model_key );
		}

		if ( is_wp_error( $model ) || empty( $model ) ) {
			return new WP_Error(
				'model_not_found',
				sprintf( 'Model not found: %s', $model_key ),
				array( 'status' => 404 )
			);
		}

		// 2. Get appropriate strategy for this model.
		$strategy = $this->get_strategy( $model );

		if ( is_wp_error( $strategy ) ) {
			return $strategy;
		}

		$this->logger->log(
			sprintf(
				'Vector Generator: Using strategy "%s" for model "%s"',
				$strategy->get_name(),
				$model_key
			),
			'info',
			'vectors',
			$connection_name,
			array(
				'strategy_id' => $strategy->get_id(),
				'model_key'   => $model_key,
			)
		);

		// 3. Execute strategy.
		try {
			$result = $strategy->generate( $model, $batch_size, $connection_name );

			// Log result.
			$log_level = $result['success'] ? 'info' : 'error';
			$this->logger->log(
				sprintf(
					'Vector Generator: Batch completed - %s',
					$result['message']
				),
				$log_level,
				'vectors',
				$connection_name,
				array(
					'model_key' => $model_key,
					'processed' => $result['processed'] ?? 0,
					'failed'    => $result['failed'] ?? 0,
				)
			);

			return $result;
		} catch ( Exception $e ) {
			$error_msg = sprintf(
				'Vector generation failed: %s',
				$e->getMessage()
			);

			$this->logger->log(
				$error_msg,
				'error',
				'vectors',
				$connection_name,
				array(
					'model_key' => $model_key,
					'exception' => get_class( $e ),
				)
			);

			return new WP_Error(
				'generation_failed',
				$error_msg,
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get strategy for a specific model
	 *
	 * Iterates through registered strategies and returns the first one
	 * that supports the given model configuration.
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return GG_Data_Vector_Strategy_Interface|WP_Error Strategy instance or error.
	 */
	private function get_strategy( array $model ) {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->supports_model( $model ) ) {
				return $strategy;
			}
		}

		return new WP_Error(
			'no_strategy',
			sprintf(
				'No strategy found for model (provider=%s, model_key=%s)',
				isset( $model['provider'] ) ? $model['provider'] : 'unknown',
				isset( $model['model_key'] ) ? $model['model_key'] : 'unknown'
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Get all registered strategies
	 *
	 * @since 1.0.0
	 * @return GG_Data_Vector_Strategy_Interface[] Array of strategies.
	 */
	public function get_strategies(): array {
		return $this->strategies;
	}

	/**
	 * Get strategy by ID
	 *
	 * @since 1.0.0
	 * @param string $strategy_id Strategy identifier.
	 * @return GG_Data_Vector_Strategy_Interface|null Strategy or null if not found.
	 */
	public function get_strategy_by_id( string $strategy_id ): ?GG_Data_Vector_Strategy_Interface {
		return isset( $this->strategies[ $strategy_id ] ) ? $this->strategies[ $strategy_id ] : null;
	}

	/**
	 * Check if model has compatible strategy
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return bool True if strategy exists for model.
	 */
	public function has_strategy_for_model( array $model ): bool {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->supports_model( $model ) ) {
				return true;
			}
		}
		return false;
	}
}
