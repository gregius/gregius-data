<?php
/**
 * Google Gemini Provider Adapter
 *
 * Provides access to Google Gemini models through their generateContent API.
 * Supports Gemini 2.0 Flash, Gemini 1.5 Pro, Gemini 1.5 Flash.
 *
 * Gemini API differs from OpenAI:
 * - Uses X-Goog-Api-Key header for authentication
 * - Endpoint: /models/{model}:generateContent
 * - Messages use 'contents' array with 'user'/'model' roles
 * - System prompt via 'systemInstruction' parameter
 * - Response structure uses 'candidates' array
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
 * Class GG_Data_Gemini_Provider
 *
 * @since 1.0.0
 */
class GG_Data_Gemini_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * Google AI API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Supported Gemini models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_models = array(
		'gemini-2.5-flash',
		'gemini-2.5-pro',
		'gemini-2.0-flash-lite',
	);

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'gemini';
	}

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name.
	 */
	public function get_name(): string {
		return 'Gemini';
	}

	/**
	 * Get the capabilities supported by this provider.
	 *
	 * Gemini supports LLM and embeddings capabilities.
	 *
	 * @since 1.0.0
	 * @return array Capabilities array.
	 */
	public function get_capabilities(): array {
		return array( 'llm', 'embeddings' );
	}

	/**
	 * Generate text from a prompt.
	 *
	 * @since 1.0.0
	 * @param string $prompt  The user prompt.
	 * @param array  $options Generation options.
	 * @return array|WP_Error Response data.
	 */
	public function generate_text( string $prompt, array $options = array() ): array|WP_Error {
		// 1. Determine Connection (for settings).
		$connection = isset( $options['connection'] ) ? $options['connection'] : 'default';
		$settings   = new GG_Data_Settings_Manager();
		$api_key    = '';
		$model      = isset( $options['model'] ) ? $options['model'] : 'gemini-2.5-flash';

		// Check if using new Model ID system (model_xxx format).
		if ( strpos( $connection, 'model_' ) === 0 ) {
			$model_config = $settings->get_model( $connection );
			if ( $model_config ) {
				$api_key = isset( $model_config['api_key'] ) ? $model_config['api_key'] : '';
				// Override model if specified in config.
				if ( ! empty( $model_config['model_name'] ) ) {
					$model = $model_config['model_name'];
				}
			}
		}

		// Check for LLM model in settings (llm_model category).
		if ( empty( $api_key ) ) {
			$model_registry = new GG_Data_Model_Registry();
			$model_data     = $model_registry->get_model( 'gregius-data', $model );
			if ( $model_data && ! is_wp_error( $model_data ) ) {
				$api_key = $model_data['api_key'] ?? '';
				// Also check in config array if present.
				if ( empty( $api_key ) && isset( $model_data['config'] ) && is_array( $model_data['config'] ) ) {
					$api_key = $model_data['config']['api_key'] ?? '';
				}
			}
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'Google AI API Key is missing for this connection.', 'gregius-data' )
			);
		}

		if ( '***' === $api_key ) {
			return new WP_Error(
				'gg_data_invalid_api_key',
				__( 'Invalid API Key (***). Please re-enter your API key in the model settings.', 'gregius-data' )
			);
		}

		// 2. Validate model.
		if ( ! in_array( $model, $this->supported_models, true ) ) {
			return new WP_Error(
				'gg_data_invalid_model',
				sprintf(
					/* translators: 1: provided model, 2: list of supported models */
					__( 'Invalid Gemini model "%1$s". Supported models: %2$s', 'gregius-data' ),
					$model,
					implode( ', ', $this->supported_models )
				)
			);
		}

		// 3. Build request payload.
		// Gemini uses 'contents' array with 'user'/'model' roles.
		$contents = array();

		// Insert conversation history if provided.
		if ( ! empty( $options['messages'] ) && is_array( $options['messages'] ) ) {
			foreach ( $options['messages'] as $msg ) {
				if ( isset( $msg['role'] ) && isset( $msg['content'] ) ) {
					// Map OpenAI roles to Gemini roles.
					$role = sanitize_text_field( $msg['role'] );
					if ( 'system' === $role ) {
						// System messages are handled separately in Gemini.
						continue;
					}
					// Gemini uses 'model' instead of 'assistant'.
					if ( 'assistant' === $role ) {
						$role = 'model';
					}
					$contents[] = array(
						'role'  => $role,
						'parts' => array(
							array( 'text' => $msg['content'] ),
						),
					);
				}
			}
		}

		// Add current user message.
		$contents[] = array(
			'role'  => 'user',
			'parts' => array(
				array( 'text' => $prompt ),
			),
		);

		$request_data = array(
			'contents' => $contents,
		);

		// Add system instruction if provided.
		if ( ! empty( $options['system'] ) ) {
			$request_data['systemInstruction'] = array(
				'parts' => array(
					array( 'text' => $options['system'] ),
				),
			);
		}

		// Add generation config.
		$generation_config = array();

		if ( isset( $options['max_tokens'] ) ) {
			$generation_config['maxOutputTokens'] = (int) $options['max_tokens'];
		}

		if ( isset( $options['temperature'] ) ) {
			$generation_config['temperature'] = (float) $options['temperature'];
		}

		if ( ! empty( $generation_config ) ) {
			$request_data['generationConfig'] = $generation_config;
		}

		/**
		 * Filter the Gemini API request data before sending.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload.
		 * @param string $prompt       The user prompt.
		 * @param string $model        The model being used.
		 */
		$request_data = apply_filters( 'gg_data_gemini_request', $request_data, $prompt, $model );

		// 4. Send request to Gemini.
		$endpoint = sprintf(
			'%s/models/%s:generateContent',
			self::BASE_URL,
			$model
		);

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-Goog-Api-Key' => $api_key,
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 60,
			)
		);

		// Handle connection errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_gemini_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to Gemini API: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		// Parse response.
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown error', 'gregius-data' );
			$error_status  = $data['error']['status'] ?? 'UNKNOWN';

			return new WP_Error(
				'gg_data_gemini_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error status, 3: error message */
					__( 'Gemini API error (HTTP %1$d, %2$s): %3$s', 'gregius-data' ),
					$status_code,
					$error_status,
					$error_message
				),
				array(
					'status'       => $status_code,
					'error_status' => $error_status,
				)
			);
		}

		// Validate response structure.
		// Gemini response: { candidates: [{ content: { parts: [{ text: "..." }] } }], usageMetadata: {...} }
		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			// Check for safety block.
			if ( isset( $data['candidates'][0]['finishReason'] ) && 'SAFETY' === $data['candidates'][0]['finishReason'] ) {
				return new WP_Error(
					'gg_data_gemini_safety_block',
					__( 'Response blocked by Gemini safety filters.', 'gregius-data' )
				);
			}

			return new WP_Error(
				'gg_data_gemini_invalid_response',
				__( 'Invalid response structure from Gemini API.', 'gregius-data' )
			);
		}

		// 5. Extract response data.
		$usage  = $data['usageMetadata'] ?? array();
		$result = array(
			'text'        => trim( $data['candidates'][0]['content']['parts'][0]['text'] ),
			'tokens_used' => ( $usage['promptTokenCount'] ?? 0 ) + ( $usage['candidatesTokenCount'] ?? 0 ),
			'model'       => $model,
		);

		/**
		 * Fires after a successful Gemini API call.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload sent to Gemini.
		 * @param array  $data         The full response data from Gemini.
		 * @param int    $tokens_used  Total tokens consumed.
		 * @param string $model        The model used.
		 */
		do_action( 'gg_data_gemini_call', $request_data, $data, $result['tokens_used'], $model );

		return $result;
	}

	/**
	 * Get a list of available LLM models for this provider.
	 *
	 * Returns simple key => name format for dropdown compatibility.
	 *
	 * @since 1.0.0
	 * @return array Array of model identifiers and display names.
	 */
	public function get_llm_models(): array {
		return array(
			'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
			'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
			'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
		);
	}

	/**
	 * Get detailed model information including pricing and limits.
	 *
	 * Note: Free tier available for all Gemini models via Google AI Studio.
	 *
	 * @since 1.0.0
	 * @return array Array of model configurations with metadata.
	 */
	public function get_llm_models_detailed(): array {
		return array(
			'gemini-2.5-flash'      => array(
				'name'           => 'Gemini 2.5 Flash',

				'name'           => 'Gemini 2.5 Pro',

				'name'           => 'Gemini 2.0 Flash Lite',
				'description'    => 'Fastest and most cost-efficient',
				'context_window' => 1048576,
			),
		);
	}

	/**
	 * Generate embedding vector from text.
	 *
	 * Gemini provides embeddings through the embedding-001 model.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Embedding options.
	 * @return array|WP_Error Embedding vector or error.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		$connection = isset( $options['connection'] ) ? $options['connection'] : 'default';
		$api_key    = '';
		$model      = isset( $options['model'] ) ? $options['model'] : 'gemini-embedding-2';

		// Get API key from model registry.
		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $model );
		if ( $model_data && ! is_wp_error( $model_data ) ) {
			$api_key = $model_data['api_key'] ?? '';
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'Google AI API Key is missing for embeddings.', 'gregius-data' )
			);
		}

		$endpoint = sprintf(
			'%s/models/%s:embedContent',
			self::BASE_URL,
			$model
		);

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-Goog-Api-Key' => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'   => "models/{$model}",
						'content' => array(
							'parts' => array(
								array( 'text' => $text ),
							),
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown error', 'gregius-data' );
			return new WP_Error( 'gg_data_gemini_embedding_error', $error_message );
		}

		if ( ! isset( $data['embedding']['values'] ) ) {
			return new WP_Error(
				'gg_data_gemini_invalid_embedding',
				__( 'Invalid embedding response from Gemini API.', 'gregius-data' )
			);
		}

		return array(
			'embedding'  => $data['embedding']['values'],
			'dimensions' => count( $data['embedding']['values'] ),
			'model'      => $model,
		);
	}

	/**
	 * Get embedding models.
	 *
	 * Note: Free tier available for Gemini embeddings via Google AI Studio.
	 *
	 * @since 1.0.0
	 * @return array Array of embedding model configurations.
	 */
	public function get_embedding_models(): array {
		return array(
			'gemini-embedding-2' => array(
				'name'             => 'Gemini Embedding 2',
				'dimensions'       => 3072,
				'max_input_tokens' => 8192,
				'description'      => 'Latest multimodal embeddings with free tier available. Supports text, images, video, audio, and PDF (plugin uses text only).',
			),
		);
	}

	/**
	 * Validate the Google AI API key.
	 *
	 * @since 1.0.0
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_gemini_no_api_key',
				__( 'No API key provided.', 'gregius-data' )
			);
		}

		// Make a minimal request to validate the key.
		$endpoint = sprintf(
			'%s/models/gemini-2.0-flash:generateContent',
			self::BASE_URL,
		);

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-Goog-Api-Key' => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'contents'         => array(
							array(
								'role'  => 'user',
								'parts' => array(
									array( 'text' => 'Hi' ),
								),
							),
						),
						'generationConfig' => array(
							'maxOutputTokens' => 1,
						),
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_gemini_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Connection error: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// 200 = success, 400/403 with API_KEY errors = invalid key.
		if ( 200 === $status_code ) {
			return true;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$error_status = $data['error']['status'] ?? '';

		if ( in_array( $error_status, array( 'INVALID_ARGUMENT', 'PERMISSION_DENIED', 'UNAUTHENTICATED' ), true ) ) {
			$error_message = $data['error']['message'] ?? __( 'Invalid API key', 'gregius-data' );
			return new WP_Error(
				'gg_data_gemini_invalid_key',
				$error_message,
				array( 'status' => $status_code )
			);
		}

		// Key might be valid, other error.
		return true;
	}

	/**
	 * Rerank documents (not supported by Gemini).
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents.
	 * @param array  $options   Reranking options.
	 * @return WP_Error Always returns error as rerank is not supported.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_gemini_no_rerank',
			__( 'Gemini does not provide a rerank API. Use Voyage AI or Cohere for reranking.', 'gregius-data' )
		);
	}

	/**
	 * Get rerank models (not supported by Gemini).
	 *
	 * @since 1.0.0
	 * @return array Empty array as Gemini doesn\'t support reranking.
	 */
	public function get_rerank_models(): array {
		return array();
	}
}
