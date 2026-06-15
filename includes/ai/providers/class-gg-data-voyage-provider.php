<?php
/**
 * Voyage AI Provider
 *
 * Provides embeddings-only capabilities using Voyage AI's API.
 * Voyage AI specializes in RAG-optimized embeddings with excellent retrieval quality.
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
 * Class GG_Data_Voyage_Provider
 *
 * Embeddings-only provider using Voyage AI's API.
 * Optimized for RAG applications with high-quality semantic search.
 *
 * @since 1.0.0
 */
class GG_Data_Voyage_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * Base URL for Voyage AI API.
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://api.voyageai.com/v1';

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'voyage';
	}

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name.
	 */
	public function get_name(): string {
		return 'Voyage AI';
	}

	/**
	 * Get the capabilities supported by this provider.
	 *
	 * Voyage AI provides embeddings and reranking optimized for RAG.
	 *
	 * @since 1.0.0
	 * @return array Capabilities array.
	 */
	public function get_capabilities(): array {
		return array( 'embeddings', 'rerank' );
	}

	/**
	 * Generate text from a prompt.
	 *
	 * Voyage AI does not support LLM text generation.
	 *
	 * @since 1.0.0
	 * @param string $prompt  The user prompt.
	 * @param array  $options Generation options.
	 * @return WP_Error Always returns error as LLM is not supported.
	 */
	public function generate_text( string $prompt, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_voyage_no_llm',
			__( 'Voyage AI does not support text generation. Use embeddings only.', 'gregius-data' )
		);
	}

	/**
	 * Get a list of available LLM models for this provider.
	 *
	 * Voyage AI does not support LLM models.
	 *
	 * @since 1.0.0
	 * @return array Empty array as no LLM models are available.
	 */
	public function get_llm_models(): array {
		return array();
	}

	/**
	 * Generate an embedding vector for the given text.
	 *
	 * Uses Voyage AI's embeddings API which is OpenAI-compatible.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Embedding options.
	 * @return array|WP_Error Embedding vector or error.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$model   = isset( $options['model'] ) ? $options['model'] : 'voyage-4';

		// Get API key from model registry if not provided.
		if ( empty( $api_key ) ) {
			$model_registry = new GG_Data_Model_Registry();
			$model_data     = $model_registry->get_model( 'gregius-data', $model );
			if ( $model_data && ! is_wp_error( $model_data ) ) {
				$api_key = $model_data['api_key'] ?? '';
			}
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'Voyage AI API Key is required for embeddings.', 'gregius-data' )
			);
		}

		// Validate model.
		$valid_models = array_keys( $this->get_embedding_models() );
		if ( ! in_array( $model, $valid_models, true ) ) {
			return new WP_Error(
				'gg_data_invalid_model',
				/* translators: %s: Model name */
				sprintf( __( 'Invalid Voyage AI embedding model: %s', 'gregius-data' ), $model )
			);
		}

		// Make API request to Voyage AI Embeddings endpoint.
		$endpoint = self::BASE_URL . '/embeddings';

		$body = array(
			'input' => $text,
			'model' => $model,
		);

		// Add input_type if specified (document or query).
		if ( isset( $options['input_type'] ) ) {
			$body['input_type'] = $options['input_type'];
		}

		// Add truncation if specified.
		if ( isset( $options['truncation'] ) ) {
			$body['truncation'] = $options['truncation'];
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_json   = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_json, true );

		if ( 200 !== $status_code ) {
			$error_message = isset( $data['detail'] ) ? $data['detail'] : 'Unknown Voyage AI API error';
			return new WP_Error(
				'gg_data_voyage_api_error',
				/* translators: %s: Error message from Voyage AI API */
				sprintf( __( 'Voyage AI API error: %s', 'gregius-data' ), $error_message )
			);
		}

		if ( ! isset( $data['data'][0]['embedding'] ) ) {
			return new WP_Error(
				'gg_data_invalid_response',
				__( 'Invalid response from Voyage AI API: missing embedding data.', 'gregius-data' )
			);
		}

		// Return normalized response.
		$tokens = isset( $data['usage']['total_tokens'] ) ? $data['usage']['total_tokens'] : 0;
		return array(
			'vector'     => $data['data'][0]['embedding'],
			'tokens'     => $tokens,
			'dimensions' => count( $data['data'][0]['embedding'] ),
			'model'      => $model,
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
			'voyage-4' => array(
				'name'             => 'Voyage 4',
				'dimensions'       => 1024,
				'max_input_tokens' => 32000,
				'description'      => 'General-purpose multilingual embeddings. Shared embedding space across the Voyage 4 series.',
			),
		);
	}

	/**
	 * Rerank documents based on relevance to a query.
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents (strings).
	 * @param array  $options   Reranking options.
	 * @return array|WP_Error Reranked results or error.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$model   = isset( $options['model'] ) ? $options['model'] : 'rerank-2.5-lite';
		$top_n   = isset( $options['top_n'] ) ? $options['top_n'] : null;

		// Get API key from model registry if not provided.
		if ( empty( $api_key ) ) {
			$model_registry = new GG_Data_Model_Registry();
			$model_data     = $model_registry->get_model( 'gregius-data', $model );
			if ( $model_data && ! is_wp_error( $model_data ) ) {
				$api_key = $model_data['api_key'] ?? '';
			}
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'Voyage AI API Key is required for reranking.', 'gregius-data' )
			);
		}

		// Validate model.
		$valid_models = array_keys( $this->get_rerank_models() );
		if ( ! in_array( $model, $valid_models, true ) ) {
			return new WP_Error(
				'gg_data_invalid_model',
				/* translators: %s: Model name */
				sprintf( __( 'Invalid Voyage AI rerank model: %s', 'gregius-data' ), $model )
			);
		}

		// Make API request to Voyage AI Rerank endpoint.
		$endpoint = self::BASE_URL . '/rerank';

		$body = array(
			'query'     => $query,
			'documents' => $documents,
			'model'     => $model,
		);

		if ( null !== $top_n ) {
			$body['top_k'] = $top_n;
		}

		if ( isset( $options['return_documents'] ) ) {
			$body['return_documents'] = $options['return_documents'];
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_json   = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_json, true );

		if ( 200 !== $status_code ) {
			$error_message = isset( $data['detail'] ) ? $data['detail'] : 'Unknown Voyage AI API error';
			return new WP_Error(
				'gg_data_voyage_rerank_error',
				/* translators: %s: Error message from Voyage AI API */
				sprintf( __( 'Voyage AI Rerank error: %s', 'gregius-data' ), $error_message )
			);
		}

		if ( ! isset( $data['data'] ) ) {
			return new WP_Error(
				'gg_data_invalid_response',
				__( 'Invalid response from Voyage AI Rerank API.', 'gregius-data' )
			);
		}

		// Normalize response format.
		$results = array();
		foreach ( $data['data'] as $item ) {
			$results[] = array(
				'index'           => $item['index'],
				'relevance_score' => $item['relevance_score'],
				'document'        => isset( $item['document'] ) ? $item['document'] : null,
			);
		}

		return array(
			'results' => $results,
			'model'   => $model,
		);
	}

	/**
	 * Get a list of available rerank models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of rerank model configurations.
	 */
	public function get_rerank_models(): array {
		return array(
			'rerank-2.5'      => array(
				'name'        => 'Rerank 2.5',

				'name'        => 'Rerank 2.5 Lite',
				'max_context' => 32000,
				'description' => 'Budget reranking. 200M free tokens per account.',
			),
		);
	}

	/**
	 * Validate the Voyage AI API key.
	 *
	 * @since 1.0.0
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_voyage_no_api_key',
				__( 'API key is required.', 'gregius-data' )
			);
		}

		// Test with a simple embedding request.
		$response = wp_safe_remote_post(
			self::BASE_URL . '/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'input' => 'test',
						'model' => 'voyage-4',
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
				'gg_data_voyage_invalid_key',
				__( 'Invalid Voyage AI API key.', 'gregius-data' )
			);
		}

		if ( 200 !== $status_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = isset( $body['detail'] ) ? $body['detail'] : 'Unknown error';
			return new WP_Error( 'gg_data_voyage_api_error', $msg );
		}

		return true;
	}
}
