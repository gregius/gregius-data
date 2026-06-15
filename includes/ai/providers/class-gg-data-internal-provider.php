<?php
/**
 * Internal Provider for TF-IDF Embeddings
 *
 * Provides TF-IDF embeddings as a free-tier internal capability.
 * No external API calls, all processing done locally.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/ai/providers
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Internal_Provider
 *
 * @since 1.0.0
 */
class GG_Data_Internal_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'internal';
	}

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name.
	 */
	public function get_name(): string {
		return 'Internal';
	}

	/**
	 * Get the capabilities supported by this provider.
	 *
	 * @since 1.0.0
	 * @return array Capabilities array.
	 */
	public function get_capabilities(): array {
		return array( 'embeddings' );  // TF-IDF only, no LLM
	}

	/**
	 * Generate text from a prompt (not supported for internal provider).
	 *
	 * @since 1.0.0
	 * @param string $prompt  The user prompt.
	 * @param array  $options Generation options.
	 * @return WP_Error Always returns error as LLM is not supported.
	 */
	public function generate_text( string $prompt, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_unsupported_capability',
			__( 'Internal provider does not support text generation. Please use an external LLM provider like OpenAI.', 'gregius-data' )
		);
	}

	/**
	 * Get a list of available LLM models (not supported).
	 *
	 * @since 1.0.0
	 * @return array Empty array as LLM is not supported.
	 */
	public function get_llm_models(): array {
		return array();
	}

	/**
	 * Generate embedding vector from text using TF-IDF.
	 *
	 * Note: This method doesn't actually generate embeddings - that's handled by
	 * GG_Data_TFIDF_300_Embeddings during batch processing. This method exists
	 * to satisfy the interface contract.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Embedding options.
	 * @return WP_Error Returns error directing to batch processing.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_use_batch_generation',
			__( 'TF-IDF embeddings must be generated in batch mode. Please use the Vectors tab to generate embeddings for all posts.', 'gregius-data' )
		);
	}

	/**
	 * Get a list of available embedding models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of embedding model configurations.
	 */
	public function get_embedding_models(): array {
		return array(
			'tfidf-300'              => array(
				'name'             => 'TF-IDF 300D',

				'name'             => 'Hashing TF Murmur3 1024D',
				'dimensions'       => 1024,
				'max_input_tokens' => 0,
				'description'      => 'Free stateless local embeddings via feature hashing. No vocabulary required.',
			),
		);
	}

	/**
	 * Rerank documents (not supported by Internal provider).
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents.
	 * @param array  $options   Reranking options.
	 * @return WP_Error Always returns error as rerank is not supported.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_internal_no_rerank',
			__( 'Internal provider does not support reranking. Use Voyage AI or Cohere for reranking.', 'gregius-data' )
		);
	}

	/**
	 * Get rerank models (not supported by Internal provider).
	 *
	 * @since 1.0.0
	 * @return array Empty array as Internal provider doesn\'t support reranking.
	 */
	public function get_rerank_models(): array {
		return array();
	}
}
