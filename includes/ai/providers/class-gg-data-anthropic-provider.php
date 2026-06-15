<?php
/**
 * Anthropic Provider Adapter
 *
 * Provides access to Anthropic Claude models through their Messages API.
 * Supports Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Sonnet, and Claude 3 Haiku.
 *
 * Anthropic API differs from OpenAI:
 * - Uses x-api-key header instead of Authorization Bearer
 * - Uses /messages endpoint instead of /chat/completions
 * - System prompt is a separate parameter, not a message
 * - Response structure uses content blocks
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
 * Class GG_Data_Anthropic_Provider
 *
 * @since 1.0.0
 */
class GG_Data_Anthropic_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * Anthropic API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const BASE_URL = 'https://api.anthropic.com/v1';

	/**
	 * Anthropic API version.
	 *
	 * Claude 3.5 models require API version 2023-06-01 or later.
	 * Using latest stable version for best compatibility.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * Supported Claude models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_models = array(
		'claude-opus-4-20250514',
		'claude-sonnet-4-20250514',
		'claude-haiku-4-20250514',
	);

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'anthropic';
	}

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name.
	 */
	public function get_name(): string {
		return 'Anthropic';
	}

	/**
	 * Get the capabilities supported by this provider.
	 *
	 * Anthropic supports LLM capabilities. Embeddings are not available.
	 *
	 * @since 1.0.0
	 * @return array Capabilities array.
	 */
	public function get_capabilities(): array {
		return array( 'llm' );
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
		$model      = isset( $options['model'] ) ? $options['model'] : 'claude-sonnet-4-20250514';

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
				__( 'Anthropic API Key is missing for this connection.', 'gregius-data' )
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
					__( 'Invalid Anthropic model "%1$s". Supported models: %2$s', 'gregius-data' ),
					$model,
					implode( ', ', $this->supported_models )
				)
			);
		}

		// 3. Build request payload.
		// Anthropic uses a different format - system is separate from messages.
		$messages = array();

		// Insert conversation history if provided.
		if ( ! empty( $options['messages'] ) && is_array( $options['messages'] ) ) {
			foreach ( $options['messages'] as $msg ) {
				if ( isset( $msg['role'] ) && isset( $msg['content'] ) ) {
					// Anthropic uses 'user' and 'assistant' roles (no 'system' in messages).
					$role = sanitize_text_field( $msg['role'] );
					if ( 'system' !== $role ) {
						$messages[] = array(
							'role'    => $role,
							'content' => $msg['content'],
						);
					}
				}
			}
		}

		// Add current user message.
		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		$request_data = array(
			'model'      => $model,
			'messages'   => $messages,
			'max_tokens' => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 500,
		);

		// Add system prompt if provided (Anthropic handles system separately).
		if ( ! empty( $options['system'] ) ) {
			$request_data['system'] = $options['system'];
		}

		// Add temperature if specified (Anthropic default is 1.0).
		if ( isset( $options['temperature'] ) ) {
			$request_data['temperature'] = (float) $options['temperature'];
		}

		/**
		 * Filter the Anthropic API request data before sending.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload.
		 * @param string $prompt       The user prompt.
		 * @param string $model        The model being used.
		 */
		$request_data = apply_filters( 'gg_data_anthropic_request', $request_data, $prompt, $model );

		// 4. Send request to Anthropic.
		$response = wp_safe_remote_post(
			self::BASE_URL . '/messages',
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 60, // Claude can take time for complex responses.
			)
		);

		// Handle connection errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_anthropic_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to Anthropic API: %s', 'gregius-data' ),
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
			$error_type    = $data['error']['type'] ?? 'api_error';

			return new WP_Error(
				'gg_data_anthropic_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error type, 3: error message */
					__( 'Anthropic API error (HTTP %1$d, %2$s): %3$s', 'gregius-data' ),
					$status_code,
					$error_type,
					$error_message
				),
				array(
					'status'     => $status_code,
					'error_type' => $error_type,
				)
			);
		}

		// Validate response structure.
		// Anthropic response: { content: [{ type: "text", text: "..." }], usage: {...} }
		if ( ! isset( $data['content'][0]['text'] ) || ! isset( $data['usage'] ) ) {
			return new WP_Error(
				'gg_data_anthropic_invalid_response',
				__( 'Invalid response structure from Anthropic API.', 'gregius-data' )
			);
		}

		// 5. Extract response data.
		$result = array(
			'text'        => trim( $data['content'][0]['text'] ),
			'tokens_used' => $data['usage']['input_tokens'] + $data['usage']['output_tokens'],
			'model'       => $data['model'],
		);

		/**
		 * Fires after a successful Anthropic API call.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload sent to Anthropic.
		 * @param array  $data         The full response data from Anthropic.
		 * @param int    $tokens_used  Total tokens consumed.
		 * @param string $model        The model used.
		 */
		do_action( 'gg_data_anthropic_call', $request_data, $data, $result['tokens_used'], $model );

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
			'claude-opus-4-20250514'   => 'Claude Opus 4.5',
			'claude-sonnet-4-20250514' => 'Claude Sonnet 4.5',
			'claude-haiku-4-20250514'  => 'Claude Haiku 4.5',
		);
	}

	/**
	 * Get detailed model information and limits.
	 *
	 * @since 1.0.0
	 * @return array Array of model configurations with metadata.
	 */
	public function get_llm_models_detailed(): array {
		return array(
			'claude-opus-4-20250514'   => array(
				'name'           => 'Claude Opus 4.5',

				'name'           => 'Claude Sonnet 4.5',

				'name'           => 'Claude Haiku 4.5',
				'description'    => 'Fast and affordable for simple tasks',
				'context_window' => 200000,
			),
		);
	}

	/**
	 * Generate embedding vector from text.
	 *
	 * Anthropic does not currently offer an embeddings API.
	 * Use OpenAI or another provider for embeddings.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Embedding options.
	 * @return WP_Error Always returns error as Anthropic doesn't support embeddings.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_anthropic_no_embeddings',
			__( 'Anthropic does not provide an embeddings API. Please use OpenAI or another provider for embeddings.', 'gregius-data' )
		);
	}

	/**
	 * Get embedding models (not supported by Anthropic).
	 *
	 * @since 1.0.0
	 * @return array Empty array as Anthropic doesn't support embeddings.
	 */
	public function get_embedding_models(): array {
		return array();
	}

	/**
	 * Validate the Anthropic API key.
	 *
	 * @since 1.0.0
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_anthropic_no_api_key',
				__( 'No API key provided.', 'gregius-data' )
			);
		}

		// Make a minimal request to validate the key.
		// Anthropic doesn't have a /models endpoint, so we send a minimal message.
		$response = wp_safe_remote_post(
			self::BASE_URL . '/messages',
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-3-haiku-20240307',
						'max_tokens' => 1,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Hi',
							),
						),
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_anthropic_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Connection error: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// 200 = success, 401 = invalid key, other codes might be rate limits but key is valid.
		if ( 200 === $status_code ) {
			return true;
		}

		if ( 401 === $status_code ) {
			return new WP_Error(
				'gg_data_anthropic_invalid_key',
				__( 'Invalid API key', 'gregius-data' ),
				array( 'status' => $status_code )
			);
		}

		// For other status codes (like 429 rate limit), the key might still be valid.
		// Check if it's an auth error or something else.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error']['type'] ) && 'authentication_error' === $data['error']['type'] ) {
			$error_message = $data['error']['message'] ?? __( 'Invalid API key', 'gregius-data' );
			return new WP_Error(
				'gg_data_anthropic_invalid_key',
				$error_message,
				array( 'status' => $status_code )
			);
		}

		// Key is likely valid, just hit rate limit or other transient error.
		return true;
	}

	/**
	 * Rerank documents (not supported by Anthropic).
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents.
	 * @param array  $options   Reranking options.
	 * @return WP_Error Always returns error as rerank is not supported.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_anthropic_no_rerank',
			__( 'Anthropic does not provide a rerank API. Use Voyage AI or Cohere for reranking.', 'gregius-data' )
		);
	}

	/**
	 * Get rerank models (not supported by Anthropic).
	 *
	 * @since 1.0.0
	 * @return array Empty array as Anthropic doesn\'t support reranking.
	 */
	public function get_rerank_models(): array {
		return array();
	}
}
