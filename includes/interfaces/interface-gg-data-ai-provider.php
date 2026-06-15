<?php
/**
 * AI Provider Interface
 *
 * Defines the contract that all AI providers must implement to be compatible
 * with the Gregius Data AI Client. Supports both LLM and embeddings capabilities.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/interfaces
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface GG_Data_AI_Provider_Interface
 *
 * Capabilities-based provider interface supporting multiple AI features.
 * Providers implement only the capabilities they support.
 *
 * @since 1.0.0
 */
interface GG_Data_AI_Provider_Interface {

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID (e.g., 'openai', 'anthropic').
	 */
	public function get_id(): string;

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name (e.g., 'OpenAI', 'Anthropic').
	 */
	public function get_name(): string;

	/**
	 * Get the capabilities supported by this provider.
	 *
	 * @since 1.0.0
	 * @return array Capabilities array (e.g., ['llm', 'embeddings']).
	 */
	public function get_capabilities(): array;

	/**
	 * Generate text from a prompt (LLM capability).
	 *
	 * @since 1.0.0
	 * @param string $prompt  The user prompt.
	 * @param array  $options {
	 *     Optional. Generation options.
	 *
	 *     @type string $model       Model identifier.
	 *     @type string $system      System prompt/instruction.
	 *     @type float  $temperature Sampling temperature.
	 *     @type int    $max_tokens  Maximum tokens to generate.
	 * }
	 * @return array|WP_Error {
	 *     Response data on success, WP_Error on failure.
	 *
	 *     @type string $text        Generated text.
	 *     @type int    $tokens_used Total tokens used.
	 *     @type string $model       Model used.
	 * }
	 */
	public function generate_text( string $prompt, array $options = array() ): array|WP_Error;

	/**
	 * Get a list of available LLM models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of model identifiers and names.
	 */
	public function get_llm_models(): array;

	/**
	 * Generate embedding vector from text (Embeddings capability).
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options {
	 *     Optional. Embedding options.
	 *
	 *     @type string $model            Model identifier (e.g., 'text-embedding-3-small').
	 *     @type string $encoding_format  Format ('float' or 'base64').
	 *     @type int    $dimensions       Target dimensions (for models that support it).
	 * }
	 * @return array|WP_Error {
	 *     Response data on success, WP_Error on failure.
	 *
	 *     @type array  $vector      Embedding vector (float array).
	 *     @type int    $tokens      Tokens used.
	 *     @type int    $dimensions  Vector dimensions.
	 *     @type float  $cost        Cost incurred.
	 *     @type string $model       Model used.
	 * }
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error;

	/**
	 * Get a list of available embedding models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of embedding model configurations.
	 */
	public function get_embedding_models(): array;

	/**
	 * Rerank documents based on relevance to a query (Rerank capability).
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents (strings or objects with 'text' key).
	 * @param array  $options   {
	 *     Optional. Reranking options.
	 *
	 *     @type string $model    Model identifier (e.g., 'rerank-2').
	 *     @type int    $top_n    Number of top results to return.
	 *     @type bool   $return_documents Whether to return document text in results.
	 * }
	 * @return array|WP_Error {
	 *     Response data on success, WP_Error on failure.
	 *
	 *     @type array $results Array of {index, relevance_score, document?}.
	 *     @type string $model  Model used.
	 * }
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error;

	/**
	 * Get a list of available rerank models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of rerank model configurations.
	 */
	public function get_rerank_models(): array;

	/**
	 * Generate text from a prompt with streaming support.
	 *
	 * This is an OPTIONAL method - providers that don't support streaming
	 * don't need to implement it. Check with method_exists() before calling.
	 *
	 * The callback receives normalized chunks regardless of provider format:
	 * - OpenAI/DeepSeek: delta.content and delta.reasoning_content
	 * - Anthropic: content_block_delta events
	 * - Gemini: text chunks from streamGenerateContent
	 *
	 * @since 1.0.0
	 * @param string   $prompt   The user prompt.
	 * @param array    $options  {
	 *     Generation options.
	 *
	 *     @type string $model       Model identifier.
	 *     @type string $system      System prompt/instruction.
	 *     @type float  $temperature Sampling temperature.
	 *     @type int    $max_tokens  Maximum tokens to generate.
	 *     @type array  $messages    Conversation history.
	 * }
	 * @param callable $on_chunk Callback called for each chunk. Receives array:
	 *     @type string $content           Text content chunk (may be empty).
	 *     @type string $reasoning_content Reasoning/thinking chunk (may be empty).
	 *     @type string $finish_reason     null or 'stop'|'length' when done.
	 * @return array|WP_Error Final response with aggregated totals:
	 *     @type string $text              Complete generated text.
	 *     @type string $reasoning_content Complete reasoning (if model supports).
	 *     @type int    $tokens_used       Total tokens used.
	 *     @type string $model             Model used.
	 */
	// Note: This method is intentionally NOT in the interface signature.
	// Providers implement it optionally. Check with method_exists().
	// public function generate_text_stream( string $prompt, array $options, callable $on_chunk ): array|WP_Error;

	/**
	 * Generate embeddings for multiple texts in a single API call (OPTIONAL).
	 *
	 * This is an OPTIONAL method - only providers with batch embedding support
	 * implement it (OpenAI, Voyage AI). Check with method_exists() before calling.
	 *
	 * @since 2.0.0
	 * @param array $texts   Array of strings to embed.
	 * @param array $options Embedding options (model, api_key, etc).
	 * @return array|WP_Error {
	 *     Response data on success, WP_Error on failure.
	 *
	 *     @type array $vectors Array of embedding vectors.
	 *     @type int   $tokens  Total tokens used.
	 * }
	 */
	// Note: Intentionally NOT in the interface signature.
	// Only providers that implement batch embeddings declare this method.
	// Consumers must check method_exists() before calling.
	// public function generate_embeddings_batch( array $texts, array $options = array() ): array|WP_Error;
}
