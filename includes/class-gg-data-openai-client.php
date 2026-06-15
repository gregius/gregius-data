<?php
/**
 * OpenAI API Client
 *
 * Handles all communication with OpenAI's Chat Completions API.
 * Supports GPT-4o and GPT-4o-mini models.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI API Client class.
 *
 * Provides methods for:
 * - Chat completions with customizable parameters
 * - API key validation
 * - Error handling and response parsing
 * - Request filtering for customization
 *
 * @since 1.0.0
 */
class GG_Data_OpenAI_Client {

	/**
	 * OpenAI API key.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $api_key;

	/**
	 * OpenAI API base URL.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $base_url = 'https://api.openai.com/v1';

	/**
	 * Supported GPT models.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $supported_models = array(
		'gpt-4o',
		'gpt-4o-mini',
	);

	/**
	 * Initialize the OpenAI client.
	 *
	 * @since 1.0.0
	 * @param string $api_key Optional. OpenAI API key. If not provided, attempts to load from options.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = ! empty( $api_key ) ? $api_key : get_option( 'gg_data_openai_api_key' );
	}

	/**
	 * Send a chat completion request to OpenAI.
	 *
	 * Constructs a chat completion request with system and user messages,
	 * sends it to OpenAI's API, and returns the formatted response.
	 *
	 * @since 1.0.0
	 * @param string $system_prompt The system prompt that sets the assistant's behavior.
	 * @param string $user_message  The user's question or message.
	 * @param array  $options {
	 *     Optional. Array of parameters for the API request.
	 *
	 *     @type string $model       The GPT model to use. Default 'gpt-4o-mini'.
	 *     @type float  $temperature Sampling temperature (0-2). Default 0.3.
	 *     @type int    $max_tokens  Maximum tokens in response. Default 500.
	 * }
	 * @return array|WP_Error {
	 *     Response data on success, WP_Error on failure.
	 *
	 *     @type string $answer             The generated answer from GPT.
	 *     @type int    $tokens_used        Total tokens consumed (prompt + completion).
	 *     @type int    $prompt_tokens      Tokens used in the prompt.
	 *     @type int    $completion_tokens  Tokens used in the completion.
	 *     @type string $model              The actual model used by OpenAI.
	 * }
	 */
	public function chat_completion( $system_prompt, $user_message, $options = array() ) {
		// Validate API key is set.
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'gg_data_openai_no_api_key',
				__( 'OpenAI API key not configured. Please add your API key in the RAG settings.', 'gregius-data' )
			);
		}

		// Parse options with defaults.
		$defaults = array(
			'model'       => 'gpt-4o-mini',
			'temperature' => 0.3,
			'max_tokens'  => 500,
		);
		$options  = wp_parse_args( $options, $defaults );

		// Validate model.
		if ( ! in_array( $options['model'], $this->supported_models, true ) ) {
			return new WP_Error(
				'gg_data_openai_invalid_model',
				sprintf(
					/* translators: 1: provided model, 2: comma-separated list of supported models */
					__( 'Invalid model "%1$s". Supported models: %2$s', 'gregius-data' ),
					$options['model'],
					implode( ', ', $this->supported_models )
				)
			);
		}

		// Build request payload.
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
		);

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
			'content' => $user_message,
		);

		$request_data = array(
			'model'       => $options['model'],
			'messages'    => $messages,
			'temperature' => (float) $options['temperature'],
			'max_tokens'  => (int) $options['max_tokens'],
		);

		/**
		 * Filter the OpenAI API request data before sending.
		 *
		 * Allows modification of request parameters including model, messages,
		 * temperature, max_tokens, or adding additional parameters like top_p,
		 * frequency_penalty, presence_penalty, etc.
		 *
		 * @since 1.0.0
		 * @param array  $request_data The request payload.
		 * @param string $user_message The user's message.
		 */
		$request_data = apply_filters( 'gg_data_openai_request', $request_data, $user_message );

		// Send request to OpenAI.
		$response = wp_safe_remote_post(
			$this->base_url . '/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 30,
			)
		);

		// Handle connection errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_openai_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to OpenAI API: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		// Check response status code.
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown error', 'gregius-data' );
			$error_type    = $data['error']['type'] ?? 'api_error';

			return new WP_Error(
				'gg_data_openai_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: error type, 3: error message */
					__( 'OpenAI API error (HTTP %1$d, %2$s): %3$s', 'gregius-data' ),
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
				'gg_data_openai_invalid_response',
				__( 'Invalid response structure from OpenAI API.', 'gregius-data' )
			);
		}

		// Extract response data.
		$result = array(
			'answer'            => trim( $data['choices'][0]['message']['content'] ),
			'tokens_used'       => $data['usage']['total_tokens'],
			'prompt_tokens'     => $data['usage']['prompt_tokens'],
			'completion_tokens' => $data['usage']['completion_tokens'],
			'model'             => $data['model'],
			'raw_response'      => $data, // Store complete API response for audit trail.
		);

		/**
		 * Fires after a successful OpenAI API call.
		 *
		 * Useful for logging, analytics, or custom token tracking.
		 *
		 * @since 1.0.0
		 * @param array $request_data The request payload sent to OpenAI.
		 * @param array $data         The full response data from OpenAI.
		 * @param int   $tokens_used  Total tokens consumed.
		 */
		do_action( 'gg_data_openai_call', $request_data, $data, $result['tokens_used'] );

		return $result;
	}

	/**
	 * Validate the API key by making a test request.
	 *
	 * Makes a request to the /models endpoint to verify the API key works.
	 * This is a lightweight operation that doesn't consume tokens.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True if valid, WP_Error if invalid or connection failed.
	 */
	public function validate_api_key() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'gg_data_openai_no_api_key',
				__( 'No API key provided.', 'gregius-data' )
			);
		}

		$response = wp_safe_remote_get(
			$this->base_url . '/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gg_data_openai_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Connection error: %s', 'gregius-data' ),
					$response->get_error_message()
				)
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
			'gg_data_openai_invalid_key',
			$error_message,
			array( 'status' => $status_code )
		);
	}

	/**
	 * Get the list of supported models.
	 *
	 * @since 1.0.0
	 * @return array Array of supported model names.
	 */
	public function get_supported_models() {
		return $this->supported_models;
	}

	/**
	 * Set a new API key.
	 *
	 * @since 1.0.0
	 * @param string $api_key The OpenAI API key.
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}
}
