<?php
/**
 * Cohere AI Provider Implementation
 *
 * Provides integration with Cohere's rerank API.
 *
 * @package    Gregius_Data
 * @subpackage suspended_developer/includes/ai/providers
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cohere AI Provider Class
 *
 * Implements the AI Provider Interface for Cohere's rerank models.
 *
 * @since 1.2.0
 */
class GG_Data_Cohere_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * Cohere API base URL.
	 *
	 * @var string
	 */
	const BASE_URL = 'https://api.cohere.com/v2';

	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	const DEFAULT_TIMEOUT = 60;

	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Optional. API key for Cohere.
	 */
	public function __construct( string $api_key = '' ) {
		$this->api_key = $api_key;
	}

	/**
	 * Get the provider identifier.
	 *
	 * @since 1.2.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'cohere';
	}

	/**
	 * Get the provider display name.
	 *
	 * @since 1.2.0
	 * @return string Provider name.
	 */
	public function get_name(): string {
		return 'Cohere';
	}

	/**
	 * Get provider capabilities.
	 *
	 * @since 1.2.0
	 * @return array List of capabilities (e.g., 'llm', 'embeddings', 'rerank').
	 */
	public function get_capabilities(): array {
		return array( 'embeddings', 'rerank' );
	}

	/**
	 * Send a chat completion request.
	 *
	 * Cohere provider does not support chat completions.
	 *
	 * @since 1.2.0
	 * @param array $messages Array of message objects with 'role' and 'content'.
	 * @param array $options  Optional. Additional options like 'model', 'temperature', etc.
	 * @return array|WP_Error Response array with 'content', 'usage', etc., or WP_Error on failure.
	 */
	public function chat( array $messages, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_cohere_chat_not_supported',
			__( 'Cohere provider does not support chat completions. Use rerank() instead.', 'gregius-data' )
		);
	}

	/**
	 * Generate text from a prompt (simple completion).
	 *
	 * Cohere provider does not support text generation.
	 *
	 * @since 1.2.0
	 * @param string $prompt  The prompt to generate from.
	 * @param array  $options Optional. Generation options.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function generate_text( string $prompt, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_cohere_generate_not_supported',
			__( 'Cohere provider does not support text generation. Use rerank() instead.', 'gregius-data' )
		);
	}

	/**
	 * Get available chat/LLM models.
	 *
	 * Cohere provider does not support LLM models.
	 *
	 * @since 1.2.0
	 * @return array Associative array of model_id => model_name.
	 */
	public function get_models(): array {
		return array();
	}

	/**
	 * Get available LLM models.
	 *
	 * Cohere provider does not support LLM models.
	 *
	 * @since 1.2.0
	 * @return array Array of model identifiers and names.
	 */
	public function get_llm_models(): array {
		return array();
	}

	/**
	 * Generate embeddings for given text(s).
	 *
	 * Cohere provider does not support embeddings in this implementation.
	 *
	 * @since 1.2.0
	 * @param string|array $input   Text or array of texts to embed.
	 * @param array        $options Optional. Options like 'model'.
	 * @return array|WP_Error Array of embedding vectors or WP_Error on failure.
	 */
	public function embed( string|array $input, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_cohere_embed_not_supported',
			__( 'Cohere embeddings not implemented in this provider. Use rerank() instead.', 'gregius-data' )
		);
	}

	/**
	 * Generate embedding vector from text.
	 *
	 * Cohere provider does not support embeddings in this implementation.
	 *
	 * @since 1.2.0
	 * @param string $text    The text to embed.
	 * @param array  $options Optional. Embedding options.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : $this->api_key;

		if ( empty( $api_key ) ) {
			$model_registry = new GG_Data_Model_Registry();
			$model_data     = $model_registry->get_model( 'gregius-data', $options['model'] ?? 'embed-v4.0' );
			if ( $model_data && ! is_wp_error( $model_data ) ) {
				$api_key = $model_data['api_key'] ?? '';
			}
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_cohere_missing_api_key',
				__( 'Cohere API Key is required for embeddings.', 'gregius-data' )
			);
		}

		$model = isset( $options['model'] ) ? $options['model'] : 'embed-v4.0';

		$body = array(
			'model'           => $model,
			'texts'           => array( $text ),
			'input_type'      => 'search_document',
			'embedding_types' => array( 'float' ),
		);

		if ( isset( $options['output_dimension'] ) ) {
			$body['output_dimension'] = (int) $options['output_dimension'];
		}

		$response = wp_safe_remote_post(
			self::BASE_URL . '/embed',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_json   = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_json, true );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'gg_data_cohere_unauthorized',
				__( 'Invalid Cohere API key.', 'gregius-data' )
			);
		}

		if ( 200 !== $status_code ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown Cohere API error', 'gregius-data' );
			return new WP_Error(
				'gg_data_cohere_embedding_error',
				/* translators: %s: Error message from Cohere API */
				sprintf( __( 'Cohere API error: %s', 'gregius-data' ), $error_message )
			);
		}

		if ( ! isset( $data['embeddings'][0] ) ) {
			return new WP_Error(
				'gg_data_cohere_invalid_response',
				__( 'Invalid response from Cohere Embed API: missing embedding data.', 'gregius-data' )
			);
		}

		return array(
			'vector'     => $data['embeddings'][0],
			'dimensions' => count( $data['embeddings'][0] ),
			'model'      => $model,
		);
	}

	/**
	 * Get available embedding models.
	 *
	 * Cohere embed models support 100+ languages.
	 *
	 * @since 1.2.0
	 * @return array Array of embedding model info.
	 */
	public function get_embedding_models(): array {
		return array(
			'embed-v4.0' => array(
				'name'             => 'Embed v4.0',
				'dimensions'       => 1536,
				'max_input_tokens' => 128000,
				'description'      => 'Latest multilingual embeddings (100+ languages). Supports MRL dimensions: 256, 512, 1024, 1536.',
			),
		);
	}

	/**
	 * Rerank documents based on relevance to a query.
	 *
	 * @since 1.2.0
	 * @param string $query     The search query.
	 * @param array  $documents Array of document strings to rerank.
	 * @param array  $options   Optional. Options like 'model', 'top_n', 'max_tokens_per_doc'.
	 * @return array|WP_Error Array of reranked results with 'index' and 'relevance_score', or WP_Error.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'gg_data_cohere_no_api_key',
				__( 'Cohere API key is required for reranking.', 'gregius-data' )
			);
		}

		if ( empty( $query ) ) {
			return new WP_Error(
				'gg_data_cohere_empty_query',
				__( 'Query is required for reranking.', 'gregius-data' )
			);
		}

		if ( empty( $documents ) ) {
			return new WP_Error(
				'gg_data_cohere_empty_documents',
				__( 'Documents array is required for reranking.', 'gregius-data' )
			);
		}

		$model              = $options['model'] ?? 'rerank-v3.5';
		$top_n              = $options['top_n'] ?? null;
		$max_tokens_per_doc = $options['max_tokens_per_doc'] ?? 4096;

		$body = array(
			'model'              => $model,
			'query'              => $query,
			'documents'          => array_values( $documents ),
			'max_tokens_per_doc' => $max_tokens_per_doc,
		);

		if ( null !== $top_n ) {
			$body['top_n'] = (int) $top_n;
		}

		$response = wp_safe_remote_post(
			self::BASE_URL . '/rerank',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'gg_data_cohere_unauthorized',
				__( 'Invalid Cohere API key.', 'gregius-data' )
			);
		}

		if ( 200 !== $status_code ) {
			$error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
			return new WP_Error(
				'gg_data_cohere_api_error',
				sprintf(
					/* translators: %s: Error message from API */
					__( 'Cohere API error: %s', 'gregius-data' ),
					$error_message
				)
			);
		}

		if ( ! isset( $body['results'] ) || ! is_array( $body['results'] ) ) {
			return new WP_Error(
				'gg_data_cohere_invalid_response',
				__( 'Invalid response format from Cohere API.', 'gregius-data' )
			);
		}

		// Normalize the response to our standard format.
		$results = array();
		foreach ( $body['results'] as $result ) {
			$results[] = array(
				'index'           => $result['index'],
				'relevance_score' => $result['relevance_score'],
			);
		}

		return array(
			'results' => $results,
			'model'   => $model,
			'usage'   => isset( $body['meta']['billed_units'] ) ? $body['meta']['billed_units'] : array(),
		);
	}

	/**
	 * Get available rerank models.
	 *
	 * Only the fast model is included as Voyage AI is the primary reranking provider.
	 * Cohere serves as a fallback/alternative for provider diversity.
	 *
	 * @since 1.2.0
	 * @return array Array of rerank model info keyed by model ID.
	 */
	public function get_rerank_models(): array {
		return array(
			'rerank-v4.0-fast' => array(
				'name'        => 'Rerank v4.0 Fast',
				'max_context' => 4096,
				'description' => 'Fast multilingual reranking. Alternative to Voyage AI.',
			),
		);
	}

	/**
	 * Validate API key by making a test request.
	 *
	 * @since 1.2.0
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_cohere_no_api_key',
				__( 'API key is required.', 'gregius-data' )
			);
		}

		// Test with a simple rerank request.
		$response = wp_safe_remote_post(
			self::BASE_URL . '/rerank',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'     => 'rerank-v3.5',
						'query'     => 'test',
						'documents' => array( 'test document' ),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'gg_data_cohere_invalid_key',
				__( 'Invalid Cohere API key.', 'gregius-data' )
			);
		}

		if ( 200 !== $status_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
			return new WP_Error( 'gg_data_cohere_api_error', $msg );
		}

		return true;
	}
}
