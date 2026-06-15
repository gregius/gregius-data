<?php
/**
 * Server-Sent Events Handler for RAG Streaming
 *
 * Provides real-time progress events during RAG processing using SSE.
 * Uses wp_ajax_* hooks instead of REST API for proper streaming support.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSE Handler class for RAG streaming.
 *
 * Handles real-time progress events via Server-Sent Events.
 *
 * @since 1.0.0
 */
class GG_Data_SSE_Handler {

	/**
	 * Whether output has started.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $output_started = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_ajax_handlers();
	}

	/**
	 * Register AJAX handlers for SSE endpoints.
	 *
	 * Uses wp_ajax_* instead of REST API for proper SSE streaming support.
	 *
	 * @since 1.0.0
	 */
	private function register_ajax_handlers() {
		// Public endpoint (for frontend chat).
		add_action( 'wp_ajax_gg_data_rag_stream', array( $this, 'handle_rag_stream' ) );
		add_action( 'wp_ajax_nopriv_gg_data_rag_stream', array( $this, 'handle_rag_stream' ) );

		// Streaming failure logging endpoint.
		add_action( 'wp_ajax_gg_data_log_streaming_failure', array( $this, 'handle_log_streaming_failure' ) );
		add_action( 'wp_ajax_nopriv_gg_data_log_streaming_failure', array( $this, 'handle_log_streaming_failure' ) );
	}

	/**
	 * Handle streaming failure logging.
	 *
	 * Logs streaming failures for diagnostics without blocking the user.
	 *
	 * @since 1.0.0
	 */
	public function handle_log_streaming_failure() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'gg_data_rag_stream', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		// Get parameters.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$error_type             = isset( $_POST['error_type'] ) ? sanitize_text_field( wp_unslash( $_POST['error_type'] ) ) : 'unknown';
		$elapsed_ms             = isset( $_POST['elapsed_ms'] ) ? absint( $_POST['elapsed_ms'] ) : 0;
		$partial_content_length = isset( $_POST['partial_content_length'] ) ? absint( $_POST['partial_content_length'] ) : 0;
		$model_id               = isset( $_POST['model_id'] ) ? sanitize_text_field( wp_unslash( $_POST['model_id'] ) ) : '';
		$connection_id          = isset( $_POST['connection_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Log the failure.
		$logger = new GG_Data_Logger();
		$logger->log(
			sprintf(
				'Streaming failure: type=%s, elapsed=%dms, partial_content=%d bytes, model=%s',
				$error_type,
				$elapsed_ms,
				$partial_content_length,
				$model_id
			),
			'warning',
			'streaming',
			$connection_id
		);

		/**
		 * Action fired when streaming fails.
		 *
		 * Allows custom handling of streaming failures (e.g., alerting, metrics).
		 *
		 * @since 1.0.0
		 * @param string $error_type             Type of error (timeout, network, http_error, connection_blocked).
		 * @param int    $elapsed_ms             Time elapsed before failure in milliseconds.
		 * @param int    $partial_content_length Length of partial content received.
		 * @param string $model_id               LLM model ID.
		 * @param string $connection_id          Connection ID.
		 */
		do_action( 'gg_data_streaming_failure', $error_type, $elapsed_ms, $partial_content_length, $model_id, $connection_id );

		wp_send_json_success( 'Logged' );
	}

	/**
	 * Handle RAG streaming request.
	 *
	 * @since 1.0.0
	 */
	public function handle_rag_stream() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'gg_data_rag_stream', 'nonce', false ) ) {
			$this->send_error( 'Invalid security token. Please refresh the page and try again.' );
			exit;
		}

		// Apply permission filter (same as REST endpoint).
		// Can return: true (allow), false (deny), or WP_Error (deny with message).
		$allowed = apply_filters( 'gg_data_rag_endpoint_permission', false, null );
		if ( is_wp_error( $allowed ) ) {
			$this->send_error( $allowed->get_error_message() );
			exit;
		}
		if ( ! $allowed ) {
			$this->send_error( 'You do not have permission to access this endpoint.' );
			exit;
		}

		/**
		 * Filter to implement rate limiting for RAG streaming requests.
		 *
		 * Return a WP_Error to block the request, or true to allow.
		 * Developers can use this hook to implement IP-based rate limiting,
		 * user-based quotas, or other throttling mechanisms.
		 *
		 * @since 1.0.0
		 * @param bool|WP_Error $allowed Whether the request is allowed.
		 * @param int|null      $user_id Current user ID (0 for guests).
		 */
		$rate_limit_check = apply_filters( 'gg_data_rag_rate_limit', true, get_current_user_id() );
		if ( is_wp_error( $rate_limit_check ) ) {
			$this->send_error( $rate_limit_check->get_error_message() );
			exit;
		}

		// Get and validate parameters.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$connection_name = isset( $_POST['connection_name'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$embedding_model_key = isset( $_POST['embedding_model_key'] ) ? sanitize_text_field( wp_unslash( $_POST['embedding_model_key'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$llm_model_id = isset( $_POST['llm_model_id'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_model_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$rewrite_model = isset( $_POST['rewrite_model'] ) ? sanitize_text_field( wp_unslash( $_POST['rewrite_model'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$rerank_model_id = isset( $_POST['rerank_model_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rerank_model_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$security_prompt_id = isset( $_POST['security_prompt_id'] ) ? absint( wp_unslash( $_POST['security_prompt_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$source_turn_index = isset( $_POST['source_turn_index'] ) ? absint( wp_unslash( $_POST['source_turn_index'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$conversation_id = isset( $_POST['conversation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conversation_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$messages_json = isset( $_POST['messages'] ) ? wp_unslash( $_POST['messages'] ) : '[]';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.
		$source_json = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$metadata_filter_json = isset( $_POST['metadata_filter'] ) ? wp_unslash( $_POST['metadata_filter'] ) : '{}';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$metadata_manifest_json = isset( $_POST['metadata_manifest'] ) ? wp_unslash( $_POST['metadata_manifest'] ) : '[]';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$manifest_json = isset( $_POST['manifest'] ) ? wp_unslash( $_POST['manifest'] ) : '{}';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$forced_tool = isset( $_POST['forced_tool'] ) ? sanitize_key( wp_unslash( $_POST['forced_tool'] ) ) : '';

		// Decode messages JSON.
		$messages = json_decode( $messages_json, true );
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		// Sanitize conversation messages to prevent prompt injection vectors.
		$messages = array_map(
			function ( $msg ) {
				if ( ! is_array( $msg ) ) {
					return null;
				}
				return array(
					'role'    => isset( $msg['role'] ) ? sanitize_text_field( $msg['role'] ) : '',
					'content' => isset( $msg['content'] ) ? wp_kses_post( $msg['content'] ) : '',
				);
			},
			$messages
		);
		$messages = array_filter( $messages ); // Remove null entries.

		$source_payload = $this->sanitize_source_payload( $source_json );
		$source_payload = array_merge(
			array( 'type' => 'frontend' ),
			$source_payload
		);

		$metadata_filter = json_decode( (string) $metadata_filter_json, true );
		if ( ! is_array( $metadata_filter ) ) {
			$metadata_filter = array();
		}

		$metadata_manifest = json_decode( (string) $metadata_manifest_json, true );
		if ( ! is_array( $metadata_manifest ) ) {
			$metadata_manifest = array();
		}

		$manifest = json_decode( (string) $manifest_json, true );
		if ( ! is_array( $manifest ) ) {
			$manifest = array();
		}

		// Validate required parameters.
		if ( empty( $query ) ) {
			$this->send_error( 'Query parameter is required.' );
			exit;
		}

		if ( empty( $connection_name ) ) {
			$this->send_error( 'Connection name is required.' );
			exit;
		}

		if ( empty( $embedding_model_key ) ) {
			$this->send_error( 'Embedding model is required.' );
			exit;
		}

		if ( empty( $llm_model_id ) ) {
			$this->send_error( 'LLM model is required.' );
			exit;
		}

		// Set up SSE headers.
		$this->setup_sse_headers();

		// Create progress callback.
		$progress_callback = function ( $stage, $data ) {
			$this->send_progress( $stage, $data );
		};

		// Create RAG service instance.
		$rag = new GG_Data_RAG_Service( $connection_name, $embedding_model_key );

		// Build options.
		$options = array(
			'num_chunks'         => 5,
			'temperature'        => 0.7,
			'rewrite_model'      => $rewrite_model,
			'rerank_model_id'    => $rerank_model_id,
			'metadata_filter'    => $metadata_filter,
			'metadata_manifest'  => $metadata_manifest,
			'manifest'           => $manifest,
			'forced_tool'        => $forced_tool,
			'security_prompt_id' => $security_prompt_id,
			'source_turn_index'  => $source_turn_index,
			'conversation_id'    => $conversation_id,
			'source'             => $source_payload,
			'messages'           => $messages,
			'progress_callback'  => $progress_callback,
		);

		$precomputed_result = apply_filters(
			'gg_data_rag_precomputed_response',
			null,
			$query,
			$llm_model_id,
			$options,
			array( 'transport' => 'sse' )
		);

		if ( is_wp_error( $precomputed_result ) ) {
			$this->send_error( $precomputed_result->get_error_message() );
			exit;
		}

		if ( is_array( $precomputed_result ) ) {
			$this->send_complete( $precomputed_result );
			exit;
		}

		// Generate answer with progress events.
		try {
			$result = $rag->generate_answer( $query, $llm_model_id, $options );

			if ( is_wp_error( $result ) ) {
				$this->send_error( $result->get_error_message() );
			} else {
				do_action(
					'gg_data_rag_response_generated',
					$result,
					$query,
					$llm_model_id,
					$options,
					array( 'transport' => 'sse' )
				);

				// Send thinking content if available.
				if ( ! empty( $result['metadata']['reasoning_content'] ) ) {
					$this->send_thinking( $result['metadata']['reasoning_content'] );
				}

				// Send complete event.
				$this->send_complete( $result );
			}
		} catch ( Exception $e ) {
			$this->send_error( $e->getMessage() );
		}

		exit;
	}

	/**
	 * Set up SSE headers.
	 *
	 * @since 1.0.0
	 */
	private function setup_sse_headers() {
		// Disable output buffering at all levels.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Explicitly turn off output buffering.
		// phpcs:ignore WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming.
		ini_set( 'output_buffering', '0' );

		// Set SSE headers.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx buffering.

		// Disable PHP output compression.
		if ( function_exists( 'apache_setenv' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv
			apache_setenv( 'no-gzip', '1' );
		}

		// phpcs:ignore WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming.
		ini_set( 'zlib.output_compression', '0' );

		// Disable implicit flush and enable explicit flushing.
		// phpcs:ignore WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming.
		ini_set( 'implicit_flush', '1' );

		// Note: We intentionally do NOT call set_time_limit() here.
		// Shared hosting often has hardcoded PHP timeout limits (30-60s) that cannot be overridden.
		// Instead, we rely on the frontend to detect timeouts and fall back to REST API.
		// The cURL timeouts in providers (60s) will handle cleanup of zombie connections.

		$this->output_started = true;
	}

	/**
	 * Send a progress event.
	 *
	 * @since 1.0.0
	 * @param string $stage Progress stage identifier.
	 * @param mixed  $data  Additional data for the stage.
	 */
	public function send_progress( $stage, $data = null ) {
		// Handle token streaming events specially.
		if ( 'token' === $stage || 'reasoning_token' === $stage ) {
			$this->send_token( $stage, $data );
			return;
		}

		$event = array(
			'type'  => 'progress',
			'stage' => $stage,
		);

		if ( null !== $data ) {
			$event['data'] = $data;
		}

		$this->send_event( $event );
	}

	/**
	 * Send a token streaming event.
	 *
	 * Used for real-time token-by-token streaming during answer generation.
	 *
	 * @since 1.0.0
	 * @param string $type Either 'token' for content or 'reasoning_token' for reasoning.
	 * @param array  $data Token data with 'content' key.
	 */
	public function send_token( $type, $data ) {
		$this->send_event(
			array(
				'type'    => $type,
				'content' => $data['content'] ?? '',
			)
		);
	}

	/**
	 * Send a thinking event (for models that expose reasoning).
	 *
	 * @since 1.0.0
	 * @param string $content Thinking/reasoning content.
	 */
	public function send_thinking( $content ) {
		$this->send_event(
			array(
				'type'    => 'thinking',
				'content' => $content,
			)
		);
	}

	/**
	 * Send an error event.
	 *
	 * @since 1.0.0
	 * @param string $message Error message.
	 */
	public function send_error( $message ) {
		// Set up headers if not already done.
		if ( ! $this->output_started ) {
			$this->setup_sse_headers();
		}

		$this->send_event(
			array(
				'type'    => 'error',
				'message' => $message,
			)
		);
	}

	/**
	 * Send the complete event with final result.
	 *
	 * @since 1.0.0
	 * @param array $result RAG result data.
	 */
	public function send_complete( $result ) {
		$this->send_event(
			array(
				'type'   => 'complete',
				'result' => $result,
			)
		);
	}

	/**
	 * Send a raw SSE event.
	 *
	 * @since 1.0.0
	 * @param array $data Event data to JSON encode and send.
	 */
	private function send_event( $data ) {
		// Output SSE formatted data.
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		// Aggressively flush all output buffers for real-time streaming.
		// This is critical for environments like Local by Flywheel.
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();

		// Do not call fastcgi_finish_request() during SSE token streaming:
		// it terminates the request immediately. Use flush() so the stream stays open.
	}

	/**
	 * Sanitize JSON source payload from AJAX form data.
	 *
	 * @since 1.0.0
	 * @param string $source_json Source JSON payload.
	 * @return array
	 */
	private function sanitize_source_payload( $source_json ) {
		if ( ! is_string( $source_json ) || '' === trim( $source_json ) ) {
			return array();
		}

		$decoded = json_decode( $source_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}

		$sanitized = array();

		if ( isset( $decoded['type'] ) ) {
			$sanitized['type'] = sanitize_key( (string) $decoded['type'] );
		}

		if ( isset( $decoded['post_id'] ) ) {
			$sanitized['post_id'] = absint( $decoded['post_id'] );
		}

		return $sanitized;
	}

	/**
	 * Get the localized script data for frontend.
	 *
	 * @since 1.0.0
	 * @return array Localized data for SSE support.
	 */
	public static function get_script_data() {
		return array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'gg_data_rag_stream' ),
			'streamEndpoint' => 'gg_data_rag_stream',
		);
	}
}
