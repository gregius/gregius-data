<?php
/**
 * HashingTF Vector Generation Strategy
 *
 * Strategy implementation for HashingTF Murmur3 1024D vector generation.
 * Delegates to GG_Data_HashingTF_Embeddings. Stateless — no vocabulary
 * preparation is required before generation can run.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/vectors/strategies
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GG_Data_HashingTF_Strategy
 *
 * Implements the vector generation strategy for HashingTF Murmur3 embeddings.
 * Delegates to GG_Data_HashingTF_Embeddings.
 *
 * @since 1.0.0
 */
class GG_Data_HashingTF_Strategy implements GG_Data_Vector_Strategy_Interface {

	/**
	 * HashingTF embeddings generator instance
	 *
	 * @var GG_Data_HashingTF_Embeddings
	 */
	private $hashingtf_generator;

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
		$this->logger = new GG_Data_Logger();
	}

	/**
	 * Get strategy identifier
	 *
	 * @since 1.0.0
	 * @return string Strategy ID.
	 */
	public function get_id(): string {
		return 'hashingtf';
	}

	/**
	 * Get strategy display name
	 *
	 * @since 1.0.0
	 * @return string Strategy name.
	 */
	public function get_name(): string {
		return 'Hashing TF (Internal)';
	}

	/**
	 * Check if strategy supports a specific model
	 *
	 * Matches models with provider='internal' and model_key containing 'hashingtf'.
	 * Intentionally does not match 'tfidf' keys — each internal strategy is narrowly scoped.
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return bool True if strategy supports this model.
	 */
	public function supports_model( array $model ): bool {
		return isset( $model['provider'] ) &&
			'internal' === $model['provider'] &&
			isset( $model['model_key'] ) &&
			false !== strpos( $model['model_key'], 'hashingtf' );
	}

	/**
	 * Generate vectors for a batch of posts
	 *
	 * @since 1.0.0
	 * @param array  $model           Model configuration from registry.
	 * @param int    $batch_size      Number of posts to process.
	 * @param string $connection_name PostgreSQL connection name.
	 * @return array Generation result.
	 */
	public function generate( array $model, int $batch_size, string $connection_name ): array {
		$this->logger->log(
			sprintf(
				'HashingTF Strategy: Starting generation for model "%s" (batch_size=%d)',
				$model['model_key'],
				$batch_size
			),
			'info',
			'vectors',
			$connection_name,
			array(
				'model_key'  => $model['model_key'],
				'batch_size' => $batch_size,
				'strategy'   => 'hashingtf',
			)
		);

		// Initialize HashingTF generator with connection name.
		require_once GG_DATA_PLUGIN_DIR . 'includes/vectors/class-gg-data-hashingtf-embeddings.php';
		$this->hashingtf_generator = new GG_Data_HashingTF_Embeddings( $connection_name );

		// Delegate to the HashingTF implementation.
		$result = $this->hashingtf_generator->generate_all_vectors( $connection_name, $batch_size );

		// Normalize response to match strategy interface contract.
		if ( ! isset( $result['success'] ) ) {
			$result['success'] = false;
		}

		if ( ! isset( $result['message'] ) ) {
			$result['message'] = 'Unknown error';
		}

		if ( ! isset( $result['processed'] ) ) {
			$result['processed'] = 0;
		}

		if ( ! isset( $result['failed'] ) ) {
			$result['failed'] = 0;
		}

		// HashingTF does not consume API tokens.
		$result['total_tokens'] = isset( $result['total_tokens'] ) ? $result['total_tokens'] : 0;

		$this->logger->log(
			sprintf(
				'HashingTF Strategy: Completed - %s',
				$result['message']
			),
			$result['success'] ? 'info' : 'error',
			'vectors',
			$connection_name,
			array(
				'model_key'    => $model['model_key'],
				'processed'    => $result['processed'],
				'failed'       => $result['failed'],
				'total_tokens' => $result['total_tokens'],
				'strategy'     => 'hashingtf',
			)
		);

		return $result;
	}
}
