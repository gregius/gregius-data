<?php
/**
 * DeepSeek Provider Adapter
 *
 * Provides access to DeepSeek AI models through their OpenAI-compatible API.
 * Supports deepseek-chat and deepseek-reasoner (R1) models.
 *
 * DeepSeek R1 provides a unique "reasoning_content" field that exposes the
 * model's thinking process, enabling rich UX features like thinking display.
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
 * Class GG_Data_DeepSeek_Provider
 *
 * @since 1.0.0
 */
class GG_Data_DeepSeek_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * DeepSeek API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const BASE_URL = 'https://api.deepseek.com/v1';

	/**
	 * Supported DeepSeek models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_models = array(
		'deepseek-chat',
		'deepseek-reasoner',
	);

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'deepseek';
	}

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name.
	 */
	public function get_name(): string {
		return 'DeepSeek';
	}

	/**
	 * Get the capabilities supported by this provider.
	 *
	 * DeepSeek supports LLM capabilities. Embeddings are not available.
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
		$model      = isset( $options['model'] ) ? $options['model'] : 'deepseek-chat';

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
				__( 'DeepSeek API Key is missing for this connection.', 'gregius-data' )
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
					__( 'Invalid DeepSeek model "%1$s". Supported models: %2$s', 'gregius-data' ),
					$model,
					implode( ', ', $this->supported_models )
				)
			);
		}

		// 3. Build request payload.
		$messages = array();

		// Add system message if provided.
		if ( ! empty( $options['system'] ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $options['system'],
			);
		}

		// Insert conversation history if provided.
		if ( ! empty( $options['messages'] ) && is_array( $options['messages'] ) ) {
			foreach ( $options['messages'] as $msg ) {
				if ( isset( $msg['role'] ) && isset( $msg['content'] ) ) {
					$messages[] = array(
						'role'    => sanitize_text_field( $msg['role'] ),
						'content' => $msg['content'],
					);
				}
			}
		}

		// Add current user message.
		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		$request_data = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.3,
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 500,
		);

		/**
		 * Filter the DeepSeek API request data before sending.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload.
		 * @param string $prompt       The user prompt.
		 * @param string $model        The model being used.
		 */
		$request_data = apply_filters( 'gg_data_deepseek_request', $request_data, $prompt, $model );

		// 4. Send request to DeepSeek.
		$response = wp_safe_remote_post(
			self::BASE_URL . '/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 60, // DeepSeek R1 can take longer due to reasoning.
			)
		);

		// Handle connection errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_deepseek_connection_error',
				/* translators: %s: error message */
				sprintf( __( 'Failed to connect to DeepSeek API: %s', 'gregius-data' ), $response->get_error_message() )
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
				'gg_data_deepseek_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error type, 3: error message */
					__( 'DeepSeek API error (HTTP %1$d, %2$s): %3$s', 'gregius-data' ),
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
		if ( ! isset( $data['choices'][0]['message']['content'] ) || ! isset( $data['usage'] ) ) {
			return new WP_Error(
				'gg_data_deepseek_invalid_response',
				__( 'Invalid response structure from DeepSeek API.', 'gregius-data' )
			);
		}

		// 5. Extract response data.
		$message = $data['choices'][0]['message'];
		$result  = array(
			'text'        => trim( $message['content'] ),
			'tokens_used' => $data['usage']['total_tokens'],
			'model'       => $data['model'],
		);

		// 6. Extract reasoning_content for DeepSeek R1 (deepseek-reasoner).
		// This enables the "thinking" display feature in the UI.
		if ( isset( $message['reasoning_content'] ) && ! empty( $message['reasoning_content'] ) ) {
			$result['reasoning_content'] = $message['reasoning_content'];
		}

		/**
		 * Fires after a successful DeepSeek API call.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload sent to DeepSeek.
		 * @param array  $data         The full response data from DeepSeek.
		 * @param int    $tokens_used  Total tokens consumed.
		 * @param string $model        The model used.
		 */
		do_action( 'gg_data_deepseek_call', $request_data, $data, $result['tokens_used'], $model );

		return $result;
	}

	/**
	 * Generate text with streaming support.
	 *
	 * Calls DeepSeek API with stream:true and invokes callback for each chunk.
	 * DeepSeek R1 provides reasoning_content in streaming mode for thinking display.
	 *
	 * @since 1.0.0
	 * @param string   $prompt   The user prompt.
	 * @param array    $options  Generation options.
	 * @param callable $on_chunk Callback for each chunk.
	 * @return array|WP_Error Final aggregated response.
	 */
	public function generate_text_stream( string $prompt, array $options, callable $on_chunk ): array|WP_Error {
		// Get API key (same logic as generate_text).
		$connection = isset( $options['connection'] ) ? $options['connection'] : 'default';
		$settings   = new GG_Data_Settings_Manager();
		$api_key    = '';
		$model      = isset( $options['model'] ) ? $options['model'] : 'deepseek-chat';

		// Check if using new Model ID system (model_xxx format).
		if ( strpos( $connection, 'model_' ) === 0 ) {
			$model_config = $settings->get_model( $connection );
			if ( $model_config ) {
				$api_key = isset( $model_config['api_key'] ) ? $model_config['api_key'] : '';
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
				if ( empty( $api_key ) && isset( $model_data['config'] ) && is_array( $model_data['config'] ) ) {
					$api_key = $model_data['config']['api_key'] ?? '';
				}
			}
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'DeepSeek API Key is missing for streaming.', 'gregius-data' )
			);
		}

		if ( '***' === $api_key ) {
			return new WP_Error(
				'gg_data_invalid_api_key',
				__( 'Invalid API Key. Please re-enter your API key in the model settings.', 'gregius-data' )
			);
		}

		// Build messages array.
		$messages = array();

		if ( ! empty( $options['system'] ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $options['system'],
			);
		}

		// Add conversation history if provided.
		if ( ! empty( $options['messages'] ) && is_array( $options['messages'] ) ) {
			foreach ( $options['messages'] as $msg ) {
				if ( isset( $msg['role'] ) && isset( $msg['content'] ) ) {
					$messages[] = array(
						'role'    => sanitize_text_field( $msg['role'] ),
						'content' => $msg['content'],
					);
				}
			}
		}

		// Add current user message.
		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		// Build request data.
		$request_data = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.3,
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 500,
			'stream'      => true,
		);

		/**
		 * Filter the streaming request data.
		 *
		 * @since 1.0.0
		 * @param array  $request_data Request payload.
		 * @param string $prompt       User prompt.
		 */
		$request_data = apply_filters( 'gg_data_deepseek_stream_request', $request_data, $prompt );

		// Use cURL for streaming (wp_remote_post doesn't support streaming).
		return $this->execute_streaming_request( self::BASE_URL . '/chat/completions', $api_key, $request_data, $on_chunk );
	}

	/**
	 * Execute streaming HTTP request with cURL.
	 *
	 * Handles SSE format from DeepSeek's OpenAI-compatible API.
	 * Extracts both content and reasoning_content for R1 models.
	 *
	 * @since 1.0.0
	 * @param string   $endpoint     API endpoint URL.
	 * @param string   $api_key      API key.
	 * @param array    $request_data Request payload.
	 * @param callable $on_chunk     Callback for each chunk.
	 * @return array|WP_Error Final response.
	 */
	private function execute_streaming_request( string $endpoint, string $api_key, array $request_data, callable $on_chunk ): array|WP_Error {
		// Aggregated response data.
		$full_content           = '';
		$full_reasoning_content = '';
		$finish_reason          = null;
		$model_used             = $request_data['model'];

		// cURL write callback to process chunks.
		$buffer         = '';
		$write_callback = function ( $curl_handle, $data ) use ( $on_chunk, &$buffer, &$full_content, &$full_reasoning_content, &$finish_reason, &$model_used ) {
			$buffer .= $data;

			// Process complete lines (SSE format: "data: {...}\n\n").
					$pos = strpos( $buffer, "\n" );
			while ( false !== $pos ) {
				$line   = substr( $buffer, 0, $pos );
				$buffer = substr( $buffer, $pos + 1 );
				$pos    = strpos( $buffer, "\n" );

				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}

				// Skip SSE comments.
				if ( strpos( $line, ':' ) === 0 ) {
					continue;
				}

				// Parse "data: " prefix.
				if ( strpos( $line, 'data: ' ) !== 0 ) {
					continue;
				}

				$json_str = substr( $line, 6 );

				// Handle stream end marker.
				if ( '[DONE]' === $json_str ) {
					continue;
				}

				// Parse JSON.
				$chunk_data = json_decode( $json_str, true );
				if ( ! $chunk_data || ! isset( $chunk_data['choices'][0] ) ) {
					continue;
				}

				$choice = $chunk_data['choices'][0];
				$delta  = $choice['delta'] ?? array();

				// Extract content (standard field).
				$content = $delta['content'] ?? '';

				// Extract reasoning content (DeepSeek R1 specific field).
				$reasoning = $delta['reasoning_content'] ?? '';

				// Check for finish reason.
				if ( isset( $choice['finish_reason'] ) && $choice['finish_reason'] ) {
					$finish_reason = $choice['finish_reason'];
				}

				// Track model if provided.
				if ( isset( $chunk_data['model'] ) ) {
					$model_used = $chunk_data['model'];
				}

				// Aggregate content.
				$full_content           .= $content;
				$full_reasoning_content .= $reasoning;

				// Call user callback with normalized chunk.
				$normalized_chunk = array(
					'content'           => $content,
					'reasoning_content' => $reasoning,
					'finish_reason'     => $choice['finish_reason'] ?? null,
				);

				$on_chunk( $normalized_chunk );

				// Force output flush for real-time streaming.
				if ( ob_get_level() > 0 ) {
					ob_flush();
				}
				flush();
			}

			return strlen( $data );
		};

		// Initialize cURL.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for streaming.
		$ch = curl_init();

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Required for streaming.
		curl_setopt( $ch, CURLOPT_URL, $endpoint );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $request_data ) );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer ' . $api_key,
				'Content-Type: application/json',
				'Accept: text/event-stream',
			)
		);
		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, $write_callback );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 60 ); // 60s to work with most hosting limits. Frontend handles fallback.
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		// phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_setopt

		// Execute request.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- Required for streaming.
		$result = curl_exec( $ch );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno -- Required for error handling.
		$curl_errno = curl_errno( $ch );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- Required for error handling.
		$curl_error = curl_error( $ch );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Required for status check.
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- Required for cleanup.
		curl_close( $ch );

		// Handle cURL errors.
		if ( $curl_errno ) {
			return new WP_Error(
				'gg_data_stream_curl_error',
				// Translators: %s is the cURL error message.
				sprintf( __( 'Streaming connection failed: %s', 'gregius-data' ), $curl_error )
			);
		}

		// Handle HTTP errors.
		if ( $http_code >= 400 ) {
			return new WP_Error(
				'gg_data_stream_http_error',
				// Translators: %d is the HTTP status code.
				sprintf( __( 'Streaming request failed with HTTP %d', 'gregius-data' ), $http_code )
			);
		}

		// Return aggregated response.
		return array(
			'text'              => $full_content,
			'reasoning_content' => $full_reasoning_content,
			'tokens_used'       => 0, // Not available in streaming responses.
			'model'             => $model_used,
			'finish_reason'     => $finish_reason,
		);
	}

	/**
	 * Get a list of available LLM models for this provider.
	 *
	 * Returns simple key => name format for dropdown compatibility.
	 * Use get_llm_models_detailed() for full model metadata.
	 *
	 * @since 1.0.0
	 * @return array Array of model identifiers and display names.
	 */
	public function get_llm_models(): array {
		return array(
			'deepseek-chat'     => 'DeepSeek Chat',
			'deepseek-reasoner' => 'DeepSeek R1',
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
			'deepseek-chat'     => array(
				'name'           => 'DeepSeek Chat',

				'name'              => 'DeepSeek R1',
				'description'       => 'Advanced reasoning with visible thinking process',
				'context_window'    => 64000,
				'supports_thinking' => true,
			),
		);
	}

	/**
	 * Generate embedding vector from text.
	 *
	 * DeepSeek does not currently offer an embeddings API.
	 * Use OpenAI or another provider for embeddings.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Embedding options.
	 * @return WP_Error Always returns error as DeepSeek doesn't support embeddings.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_deepseek_no_embeddings',
			__( 'DeepSeek does not provide an embeddings API. Please use OpenAI or another provider for embeddings.', 'gregius-data' )
		);
	}

	/**
	 * Get embedding models (not supported by DeepSeek).
	 *
	 * @since 1.0.0
	 * @return array Empty array as DeepSeek doesn't support embeddings.
	 */
	public function get_embedding_models(): array {
		return array();
	}

	/**
	 * Validate the DeepSeek API key.
	 *
	 * @since 1.0.0
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_deepseek_no_api_key',
				__( 'No API key provided.', 'gregius-data' )
			);
		}

		// Make a lightweight request to validate the key.
		$response = wp_safe_remote_get(
			self::BASE_URL . '/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_deepseek_connection_error',
				/* translators: %s: error message */
				sprintf( __( 'Connection error: %s', 'gregius-data' ), $response->get_error_message() )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			return true;
		}

		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );
		$error_message = $data['error']['message'] ?? __( 'Invalid API key', 'gregius-data' );

		return new WP_Error(
			'gg_data_deepseek_invalid_key',
			$error_message,
			array( 'status' => $status_code )
		);
	}

	/**
	 * Check if a model supports reasoning/thinking display.
	 *
	 * @since 1.0.0
	 * @param string $model Model identifier.
	 * @return bool True if model supports thinking display.
	 */
	public function supports_thinking( string $model ): bool {
		return 'deepseek-reasoner' === $model;
	}

	/**
	 * Rerank documents (not supported by DeepSeek).
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents.
	 * @param array  $options   Reranking options.
	 * @return WP_Error Always returns error as rerank is not supported.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_deepseek_no_rerank',
			__( 'DeepSeek does not provide a rerank API. Use Voyage AI or Cohere for reranking.', 'gregius-data' )
		);
	}

	/**
	 * Get rerank models (not supported by DeepSeek).
	 *
	 * @since 1.0.0
	 * @return array Empty array as DeepSeek doesn\'t support reranking.
	 */
	public function get_rerank_models(): array {
		return array();
	}
}
