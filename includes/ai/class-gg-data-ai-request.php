<?php
/**
 * AI Request Handler
 *
 * Handles the fluent interface for AI requests.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/ai
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Ai_Request
 *
 * @since 1.0.0
 */
class GG_Data_Ai_Request {

	/**
	 * The user prompt.
	 *
	 * @var string
	 */
	protected $prompt;

	/**
	 * The system prompt.
	 *
	 * @var string
	 */
	protected $system_message = '';

	/**
	 * The model to use.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * The provider ID to use.
	 *
	 * @var string
	 */
	protected $provider_id = 'openai';

	/**
	 * The connection name (custom extension).
	 *
	 * @var string
	 */
	protected $connection = 'default';

	/**
	 * Maximum tokens for response.
	 *
	 * @var int|null
	 */
	protected $max_tokens = null;

	/**
	 * Conversation messages for context.
	 *
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Constructor.
	 *
	 * @param string $prompt The user prompt.
	 */
	public function __construct( $prompt ) {
		$this->prompt = $prompt;
	}

	/**
	 * Set the system message.
	 *
	 * @param string $message System instruction.
	 * @return self
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function setSystemMessage( string $message ): self {
		$this->system_message = $message;
		return $this;
	}

	/**
	 * Set the model to use.
	 *
	 * @param string $model Model identifier.
	 * @return self
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function usingModel( string $model ): self {
		$this->model = $model;
		return $this;
	}

	/**
	 * Set the provider to use.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return self
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function usingProvider( string $provider_id ): self {
		$this->provider_id = $provider_id;
		return $this;
	}

	/**
	 * Set the connection to use (Custom Extension).
	 *
	 * @param string $connection_name Connection name.
	 * @return self
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function usingConnection( string $connection_name ): self {
		$this->connection = $connection_name;
		return $this;
	}

	/**
	 * Set the maximum tokens for the response.
	 *
	 * @param int $max_tokens Maximum tokens.
	 * @return self
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function withMaxTokens( int $max_tokens ): self {
		$this->max_tokens = $max_tokens;
		return $this;
	}

	/**
	 * Set conversation messages for context.
	 *
	 * @param array $messages Array of messages with 'role' and 'content'.
	 * @return self
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function withMessages( array $messages ): self {
		$this->messages = $messages;
		return $this;
	}

	/**
	 * Execute the request and generate text.
	 *
	 * @return string|WP_Error Generated text or error.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function generateText() {
		$result = $this->generateTextWithMetadata();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['text'];
	}

	/**
	 * Execute the request and generate text with full metadata.
	 *
	 * Returns the full response including reasoning_content for thinking models.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Array with 'text' and optional 'reasoning_content', or error.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function generateTextWithMetadata() {
		// 1. Get the provider.
		$provider = GG_Data_LLM_Registry::get_provider( $this->provider_id );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// 2. Prepare options.
		$options = array(
			'model'      => $this->model,
			'system'     => $this->system_message,
			'connection' => $this->connection,
		);

		// Add max_tokens if explicitly set.
		if ( null !== $this->max_tokens ) {
			$options['max_tokens'] = $this->max_tokens;
		}

		// Add conversation messages if provided.
		if ( ! empty( $this->messages ) ) {
			$options['messages'] = $this->messages;
		}

		// 3. Execute.
		$result = $provider->generate_text( $this->prompt, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// 4. Build usage array from token fields if available.
		$usage = array();
		if ( isset( $result['prompt_tokens'] ) && isset( $result['completion_tokens'] ) ) {
			$usage = array(
				'prompt_tokens'     => (int) $result['prompt_tokens'],
				'completion_tokens' => (int) $result['completion_tokens'],
				'total_tokens'      => (int) ( $result['tokens_used'] ?? ( $result['prompt_tokens'] + $result['completion_tokens'] ) ),
			);
		} elseif ( isset( $result['tokens_used'] ) ) {
			$usage = array(
				'total_tokens' => (int) $result['tokens_used'],
			);
		}

		// Return full result including all metadata (text, reasoning_content, usage, model, provider).
		return array(
			'text'              => $result['text'] ?? '',
			'reasoning_content' => $result['reasoning_content'] ?? '',
			'usage'             => $usage,
			'model'             => $result['model'] ?? $this->model,
			'provider'          => $this->provider_id,
			'raw_response'      => $result['raw_response'] ?? array(), // Complete API response for audit trail.
		);
	}

	/**
	 * Check if streaming is supported by the current provider.
	 *
	 * @since 1.0.0
	 * @return bool True if provider supports streaming.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function supportsStreaming(): bool {
		$provider = GG_Data_LLM_Registry::get_provider( $this->provider_id );
		if ( is_wp_error( $provider ) ) {
			return false;
		}
		return method_exists( $provider, 'generate_text_stream' );
	}

	/**
	 * Execute the request with streaming and call callback for each chunk.
	 *
	 * The callback receives normalized chunks regardless of provider:
	 * - content: Text content chunk (may be empty).
	 * - reasoning_content: Reasoning/thinking chunk (may be empty).
	 * - finish_reason: null or 'stop'|'length' when done.
	 *
	 * @since 1.0.0
	 * @param callable $on_chunk Callback for each chunk.
	 * @return array|WP_Error Final aggregated response or error.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public function generateTextStream( callable $on_chunk ) {
		// 1. Get the provider.
		$provider = GG_Data_LLM_Registry::get_provider( $this->provider_id );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// 2. Check if provider supports streaming.
		if ( ! method_exists( $provider, 'generate_text_stream' ) ) {
			// Fallback to non-streaming with a single chunk at the end.
			$result = $this->generateTextWithMetadata();
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Emit the full response as a single chunk.
			$on_chunk(
				array(
					'content'           => $result['text'] ?? '',
					'reasoning_content' => $result['reasoning_content'] ?? '',
					'finish_reason'     => 'stop',
				)
			);

			return $result;
		}

		// 3. Prepare options.
		$options = array(
			'model'      => $this->model,
			'system'     => $this->system_message,
			'connection' => $this->connection,
		);

		if ( null !== $this->max_tokens ) {
			$options['max_tokens'] = $this->max_tokens;
		}

		if ( ! empty( $this->messages ) ) {
			$options['messages'] = $this->messages;
		}

		// 4. Execute with streaming.
		$result = $provider->generate_text_stream( $this->prompt, $options, $on_chunk );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// 5. Build usage array from token fields if available.
		$usage = array();
		if ( isset( $result['prompt_tokens'] ) && isset( $result['completion_tokens'] ) ) {
			$usage = array(
				'prompt_tokens'     => (int) $result['prompt_tokens'],
				'completion_tokens' => (int) $result['completion_tokens'],
				'total_tokens'      => (int) ( $result['tokens_used'] ?? ( $result['prompt_tokens'] + $result['completion_tokens'] ) ),
			);
		} elseif ( isset( $result['tokens_used'] ) ) {
			$usage = array(
				'total_tokens' => (int) $result['tokens_used'],
			);
		}

		// Return full result including all metadata (text, reasoning_content, usage, model, provider).
		return array(
			'text'              => $result['text'] ?? '',
			'reasoning_content' => $result['reasoning_content'] ?? '',
			'usage'             => $usage,
			'model'             => $result['model'] ?? $this->model,
			'provider'          => $this->provider_id,
			'raw_response'      => $result['raw_response'] ?? array(), // Complete API response for audit trail.
		);
	}
}
