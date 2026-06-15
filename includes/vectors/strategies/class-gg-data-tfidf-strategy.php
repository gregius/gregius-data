<?php
/**
 * TF-IDF Vector Generation Strategy
 *
 * Strategy implementation for TF-IDF vector generation. Wraps the existing
 * GG_Data_TFIDF_300_Embeddings class to maintain backward compatibility.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/vectors/strategies
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GG_Data_TFIDF_Strategy
 *
 * Implements the vector generation strategy for TF-IDF embeddings.
 * Delegates to the existing GG_Data_TFIDF_300_Embeddings class.
 *
 * @since 1.0.0
 */
class GG_Data_TFIDF_Strategy implements GG_Data_Vector_Strategy_Interface {

	/**
	 * TF-IDF embeddings generator instance
	 *
	 * @var GG_Data_TFIDF_300_Embeddings
	 */
	private $tfidf_generator;

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
		return 'tfidf';
	}

	/**
	 * Get strategy display name
	 *
	 * @since 1.0.0
	 * @return string Strategy name.
	 */
	public function get_name(): string {
		return 'TF-IDF (Internal)';
	}

	/**
	 * Check if strategy supports a specific model
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return bool True if strategy supports this model.
	 */
	public function supports_model( array $model ): bool {
		// Supports models with provider='internal' and model_key containing 'tfidf'.
		return isset( $model['provider'] ) &&
			'internal' === $model['provider'] &&
			isset( $model['model_key'] ) &&
			false !== strpos( $model['model_key'], 'tfidf' );
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
				'TF-IDF Strategy: Starting generation for model "%s" (batch_size=%d)',
				$model['model_key'],
				$batch_size
			),
			'info',
			'vectors',
			$connection_name,
			array(
				'model_key'  => $model['model_key'],
				'batch_size' => $batch_size,
				'strategy'   => 'tfidf',
			)
		);

		// Initialize TF-IDF generator with connection name.
		$this->tfidf_generator = new GG_Data_TFIDF_300_Embeddings( $connection_name );

		// Delegate to existing TF-IDF implementation with batch size support.
		$result = $this->tfidf_generator->generate_all_vectors( $connection_name, $batch_size );

		// Normalize response to match strategy interface.
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

		// TF-IDF does not consume API tokens.
		$result['total_tokens'] = 0;

		$this->logger->log(
			sprintf(
				'TF-IDF Strategy: Completed - %s',
				$result['message']
			),
			$result['success'] ? 'info' : 'error',
			'vectors',
			$connection_name,
			array(
				'model_key' => $model['model_key'],
				'processed' => $result['processed'],
				'failed'    => $result['failed'],
				'strategy'  => 'tfidf',
			)
		);

		return $result;
	}
}
