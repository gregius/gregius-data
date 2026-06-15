<?php
/**
 * OpenAI Provider Adapter
 *
 * Adapts the existing GG_Data_OpenAI_Client to the new Provider Interface.
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
 * Class GG_Data_OpenAI_Provider
 *
 * @since 1.0.0
 */
class GG_Data_OpenAI_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * Get the unique identifier for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * Get the display name for the provider.
	 *
	 * @since 1.0.0
	 * @return string Provider Name.
	 */
	public function get_name(): string {
		return 'OpenAI';
	}

	/**
	 * Get the capabilities supported by this provider.
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
		$model      = isset( $options['model'] ) ? $options['model'] : 'gpt-4o-mini';

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
		// The model key (e.g., "gpt-4o-mini") is stored in setting_key with api_key in JSON setting_value.
		if ( empty( $api_key ) ) {
			$model_registry = new GG_Data_Model_Registry();
			$model_data     = $model_registry->get_model( 'gregius-data', $model );
			if ( $model_data && ! is_wp_error( $model_data ) ) {
				// Model Registry returns decoded JSON, so api_key should be accessible.
				$api_key = $model_data['api_key'] ?? '';
				// Also check in config array if present.
				if ( empty( $api_key ) && isset( $model_data['config'] ) && is_array( $model_data['config'] ) ) {
					$api_key = $model_data['config']['api_key'] ?? '';
				}
			}
		}

		// Fallback to legacy RAG settings.
		if ( empty( $api_key ) ) {
			$api_key = $settings->get_with_category( 'rag_tfidf_300', $connection, 'openai_api_key' );
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'OpenAI API Key is missing for this connection.', 'gregius-data' )
			);
		}

		if ( '***' === $api_key ) {
			return new WP_Error(
				'gg_data_invalid_api_key',
				__( 'Invalid API Key (***). Please re-enter your API key in the model settings.', 'gregius-data' )
			);
		}

		// 3. Use existing client.
		$client = new GG_Data_OpenAI_Client( $api_key );

		// 4. Map options.
		$client_options = array(
			'model'       => $model,
			'max_tokens'  => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 500,
			'temperature' => isset( $options['temperature'] ) ? $options['temperature'] : 0.3,
		);

		// Include conversation messages if provided.
		if ( ! empty( $options['messages'] ) && is_array( $options['messages'] ) ) {
			$client_options['messages'] = $options['messages'];
		}

		// 5. Execute.
		// Note: The existing client expects system prompt + user prompt combined if system isn't separate.
		// But wait, chat_completion takes a raw string.
		$full_prompt = $prompt;
		if ( ! empty( $options['system'] ) ) {
			$full_prompt = $options['system'] . "\n\n" . $prompt;
		}

		$response = $client->chat_completion( $full_prompt, $prompt, $client_options );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// 6. Normalize response.
		return array(
			'text'              => $response['answer'],
			'tokens_used'       => $response['tokens_used'],
			'prompt_tokens'     => $response['prompt_tokens'] ?? 0,
			'completion_tokens' => $response['completion_tokens'] ?? 0,
			'model'             => $response['model'],
			'raw_response'      => $response['raw_response'] ?? array(), // Complete API response.
		);
	}

	/**
	 * Get a list of available LLM models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of model identifiers and names.
	 */
	public function get_llm_models(): array {
		return array(
			'gpt-4o'      => 'GPT-4o',
			'gpt-4o-mini' => 'GPT-4o Mini',
		);
	}

	/**
	 * Generate embedding vector from text.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Embedding options.
	 * @return array|WP_Error Response data.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		// 1. Get API key from options or model registry.
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$model   = isset( $options['model'] ) ? $options['model'] : 'text-embedding-3-small';

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'OpenAI API Key is required for embeddings.', 'gregius-data' )
			);
		}

		// 2. Validate model.
		$valid_models = array_keys( $this->get_embedding_models() );
		if ( ! in_array( $model, $valid_models, true ) ) {
			return new WP_Error(
				'gg_data_invalid_model',
				/* translators: %s: Model name */
				sprintf( __( 'Invalid embedding model: %s', 'gregius-data' ), $model )
			);
		}

		// 3. Make API request to OpenAI Embeddings endpoint.
		$api_url = 'https://api.openai.com/v1/embeddings';

		$body = array(
			'input'           => $text,
			'model'           => $model,
			'encoding_format' => isset( $options['encoding_format'] ) ? $options['encoding_format'] : 'float',
		);

		// Add dimensions parameter if specified (text-embedding-3-* models support this).
		if ( isset( $options['dimensions'] ) ) {
			$body['dimensions'] = $options['dimensions'];
		}

		$response = wp_safe_remote_post(
			$api_url,
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
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown OpenAI API error';
			return new WP_Error(
				'gg_data_openai_api_error',
				/* translators: %s: Error message from OpenAI API */
				sprintf( __( 'OpenAI API error: %s', 'gregius-data' ), $error_message )
			);
		}

		if ( ! isset( $data['data'][0]['embedding'] ) ) {
			return new WP_Error(
				'gg_data_invalid_response',
				__( 'Invalid response from OpenAI API: missing embedding data.', 'gregius-data' )
			);
		}

		// 4. Return normalized response.
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
			'text-embedding-3-small' => array(
				'name'             => 'Text Embedding 3 Small',
				'dimensions'       => 1536,
				'max_input_tokens' => 8191,
			),
			'text-embedding-3-large' => array(
				'name'             => 'Text Embedding 3 Large',
				'dimensions'       => 3072,
				'max_input_tokens' => 8191,
			),
			// Ada-002 intentionally excluded - legacy model with inferior quality
			// at 5x the cost of text-embedding-3-small. Use 3-small instead.
		);
	}

	/**
	 * Generate embeddings for multiple texts in a single API call.
	 *
	 * OpenAI supports up to 2048 texts per request. This method handles
	 * batching internally if the input exceeds that limit.
	 *
	 * @since 2.1.0
	 * @param array $texts   Array of texts to embed.
	 * @param array $options Embedding options (api_key, model, dimensions).
	 * @return array|WP_Error Array of results with 'vectors', 'tokens', or error.
	 */
	public function generate_embeddings_batch( array $texts, array $options = array() ): array|WP_Error {
		if ( empty( $texts ) ) {
			return array(
				'vectors' => array(),
				'tokens'  => 0,
				'model'   => $options['model'] ?? 'text-embedding-3-small',
			);
		}

		$api_key = $options['api_key'] ?? '';
		$model   = $options['model'] ?? 'text-embedding-3-small';

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'OpenAI API Key is required for embeddings.', 'gregius-data' )
			);
		}

		// Validate model.
		$valid_models = array_keys( $this->get_embedding_models() );
		if ( ! in_array( $model, $valid_models, true ) ) {
			return new WP_Error(
				'gg_data_invalid_model',
				// Translators: %s is the embedding model name.
				sprintf( __( 'Invalid embedding model: %s', 'gregius-data' ), $model )
			);
		}

		// OpenAI limit is 2048 inputs per request.
		$max_batch_size = 2048;
		$all_vectors    = array();
		$total_tokens   = 0;

		// Process in batches if needed.
		$batches = array_chunk( $texts, $max_batch_size );

		foreach ( $batches as $batch ) {
			$result = $this->send_batch_embedding_request( $batch, $api_key, $model, $options );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$all_vectors   = array_merge( $all_vectors, $result['vectors'] );
			$total_tokens += $result['tokens'];
		}

		return array(
			'vectors' => $all_vectors,
			'tokens'  => $total_tokens,
			'model'   => $model,
		);
	}

	/**
	 * Send a single batch embedding request to OpenAI.
	 *
	 * @since 2.1.0
	 * @param array  $texts   Array of texts (max 2048).
	 * @param string $api_key OpenAI API key.
	 * @param string $model   Model identifier.
	 * @param array  $options Additional options.
	 * @return array|WP_Error Result with vectors, tokens.
	 */
	private function send_batch_embedding_request( array $texts, string $api_key, string $model, array $options ): array|WP_Error {
		$api_url = 'https://api.openai.com/v1/embeddings';

		$body = array(
			'input'           => $texts,
			'model'           => $model,
			'encoding_format' => $options['encoding_format'] ?? 'float',
		);

		// Add dimensions parameter if specified.
		if ( isset( $options['dimensions'] ) ) {
			$body['dimensions'] = $options['dimensions'];
		}

		$response = wp_safe_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120, // Longer timeout for batch requests.
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_json   = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_json, true );

		if ( 200 !== $status_code ) {
			$error_message = $data['error']['message'] ?? 'Unknown OpenAI API error';
			return new WP_Error(
				'gg_data_openai_api_error',
				// Translators: %s is the error message from the OpenAI API.
				sprintf( __( 'OpenAI API error: %s', 'gregius-data' ), $error_message )
			);
		}

		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new WP_Error(
				'gg_data_invalid_response',
				__( 'Invalid response from OpenAI API: missing embedding data.', 'gregius-data' )
			);
		}

		// Extract vectors in order (OpenAI returns them with index).
		$vectors = array();
		foreach ( $data['data'] as $item ) {
			$index             = $item['index'];
			$vectors[ $index ] = $item['embedding'];
		}

		// Sort by index to maintain input order.
		ksort( $vectors );
		$vectors = array_values( $vectors );

		// Extract tokens from usage.
		$tokens = $data['usage']['total_tokens'] ?? 0;

		return array(
			'vectors' => $vectors,
			'tokens'  => $tokens,
		);
	}



	/**
	 * Rerank documents (not supported by OpenAI).
	 *
	 * @since 1.0.0
	 * @param string $query     The query to rank documents against.
	 * @param array  $documents Array of documents.
	 * @param array  $options   Reranking options.
	 * @return WP_Error Always returns error as rerank is not supported.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		return new WP_Error(
			'gg_data_openai_no_rerank',
			__( 'OpenAI does not provide a rerank API. Use Voyage AI or Cohere for reranking.', 'gregius-data' )
		);
	}

	/**
	 * Get rerank models (not supported by OpenAI).
	 *
	 * @since 1.0.0
	 * @return array Empty array as OpenAI doesn\'t support reranking.
	 */
	public function get_rerank_models(): array {
		return array();
	}

	/**
	 * Generate text with streaming support.
	 *
	 * Calls OpenAI API with stream:true and invokes callback for each chunk.
	 * Works with OpenAI-compatible APIs (OpenAI, DeepSeek, etc.).
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
		$model      = isset( $options['model'] ) ? $options['model'] : 'gpt-4o-mini';

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
				if ( empty( $api_key ) && isset( $model_data['config'] ) && is_array( $model_data['config'] ) ) {
					$api_key = $model_data['config']['api_key'] ?? '';
				}
			}
		}

		// Fallback to legacy RAG settings.
		if ( empty( $api_key ) ) {
			$api_key = $settings->get_with_category( 'rag_tfidf_300', $connection, 'openai_api_key' );
		}

		// Fallback to global OpenAI API key option (used by GG_Data_OpenAI_Client).
		if ( empty( $api_key ) ) {
			$api_key = get_option( 'gg_data_openai_api_key', '' );
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_missing_api_key',
				__( 'OpenAI API Key is missing for streaming.', 'gregius-data' )
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
			'model'          => $model,
			'messages'       => $messages,
			'temperature'    => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.3,
			'max_tokens'     => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 500,
			'stream'         => true,
			'stream_options' => array(
				'include_usage' => true, // Request usage data in final chunk.
			),
		);

		/**
		 * Filter the streaming request data.
		 *
		 * @since 1.0.0
		 * @param array  $request_data Request payload.
		 * @param string $prompt       User prompt.
		 */
		$request_data = apply_filters( 'gg_data_openai_stream_request', $request_data, $prompt );

		// Determine API endpoint (support for custom base URLs like DeepSeek).
		$base_url = $this->get_api_base_url( $model );
		$endpoint = $base_url . '/chat/completions';

		// Use WordPress HTTP API with streaming.
		// Note: wp_remote_post doesn't support streaming, so we use cURL directly.
		return $this->execute_streaming_request( $endpoint, $api_key, $request_data, $on_chunk );
	}

	/**
	 * Get the API base URL for a model.
	 *
	 * Allows providers using OpenAI-compatible APIs to override the endpoint.
	 *
	 * @since 1.0.0
	 * @param string $model Model identifier.
	 * @return string API base URL.
	 */
	protected function get_api_base_url( string $model ): string {
		/**
		 * Filter the API base URL for streaming requests.
		 *
		 * DeepSeek provider can filter this to use api.deepseek.com.
		 *
		 * @since 1.0.0
		 * @param string $base_url Default OpenAI URL.
		 * @param string $model    Model identifier.
		 */
		return apply_filters( 'gg_data_openai_api_base_url', 'https://api.openai.com/v1', $model );
	}

	/**
	 * Execute streaming HTTP request with cURL.
	 *
	 * WordPress HTTP API doesn't support streaming, so we use cURL directly.
	 * This handles SSE format from OpenAI-compatible APIs.
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
		$usage_data             = array(); // Capture usage from final chunk.
		$raw_response_final     = array(); // Capture complete final response.

		// cURL write callback to process chunks.
		$buffer         = '';
		$write_callback = function ( $curl_handle, $data ) use ( $on_chunk, &$buffer, &$full_content, &$full_reasoning_content, &$finish_reason, &$model_used, &$usage_data, &$raw_response_final ) {
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

				if ( ! $chunk_data ) {
					continue;
				}

				// Capture usage from final chunk FIRST (before checking choices).
				// OpenAI sends usage in every chunk when stream_options.include_usage is set,
				// but only the final chunk has non-null values.
				// The final chunk may not have choices[], so capture before that check.
				if ( isset( $chunk_data['usage'] ) && is_array( $chunk_data['usage'] ) ) {
					$usage_value = $chunk_data['usage'];

					// Only capture if it has actual token counts (final chunk).
					if ( isset( $usage_value['total_tokens'] ) && $usage_value['total_tokens'] > 0 ) {
						$usage_data         = $usage_value;
						$raw_response_final = $chunk_data; // Capture complete final response with usage.
					}
				}

				// If this chunk has no choices, skip further processing.
				if ( ! isset( $chunk_data['choices'][0] ) ) {
					continue;
				}

				$choice = $chunk_data['choices'][0];
				$delta  = $choice['delta'] ?? array();

				// Extract content (standard field).
				$content = $delta['content'] ?? '';

				// Extract reasoning content (DeepSeek R1 field).
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
		curl_setopt( $ch, CURLOPT_TIMEOUT, 60 ); // 60s to work with most hosting limits.
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
			'tokens_used'       => $usage_data['total_tokens'] ?? 0, // From final chunk if available.
			'prompt_tokens'     => $usage_data['prompt_tokens'] ?? 0,
			'completion_tokens' => $usage_data['completion_tokens'] ?? 0,
			'model'             => $model_used,
			'finish_reason'     => $finish_reason,
			'raw_response'      => ! empty( $raw_response_final ) ? $raw_response_final : array(), // Complete final response with usage.
		);
	}
}
