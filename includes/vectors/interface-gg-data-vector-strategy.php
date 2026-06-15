<?php
/**
 * Vector Generation Strategy Interface
 *
 * Defines the contract for vector generation strategies. Each strategy
 * implements a different approach to generating embeddings (TF-IDF, API-based, etc).
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/vectors
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface GG_Data_Vector_Strategy_Interface
 *
 * Strategy Pattern for vector generation. Allows different generation
 * approaches (TF-IDF, OpenAI, Voyage AI) to be used interchangeably.
 *
 * @since 1.0.0
 */
interface GG_Data_Vector_Strategy_Interface {

	/**
	 * Generate vectors for a batch of posts
	 *
	 * @since 1.0.0
	 * @param array  $model {
	 *     Model configuration from registry.
	 *
	 *     @type string $model_key           Model identifier.
	 *     @type string $provider            Provider ID (e.g., 'openai', 'internal').
	 *     @type string $provider_model_id   Provider's model ID.
	 *     @type int    $dimensions          Vector dimensions.
	 *     @type string $vector_table_name   Target PostgreSQL table.
	 *     @type array  $config              Provider-specific config.
	 * }
	 * @param int    $batch_size      Number of posts to process.
	 * @param string $connection_name PostgreSQL connection name.
	 * @return array {
	 *     Generation result.
	 *
	 *     @type bool   $success      Success status.
	 *     @type string $message      Human-readable message.
	 *     @type int    $processed    Number of posts processed.
	 *     @type int    $failed       Number of posts failed.
	 *     @type int    $total_tokens Total tokens used.
	 * }
	 */
	public function generate( array $model, int $batch_size, string $connection_name ): array;

	/**
	 * Get strategy identifier
	 *
	 * @since 1.0.0
	 * @return string Strategy ID (e.g., 'tfidf', 'api-embeddings').
	 */
	public function get_id(): string;

	/**
	 * Get strategy display name
	 *
	 * @since 1.0.0
	 * @return string Strategy name (e.g., 'TF-IDF', 'API Embeddings').
	 */
	public function get_name(): string;

	/**
	 * Check if strategy supports a specific model
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return bool True if strategy supports this model.
	 */
	public function supports_model( array $model ): bool;
}
