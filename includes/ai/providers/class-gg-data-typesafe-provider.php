<?php
/**
 * TypeSafe AI Provider Implementation
 *
 * Provides integration with TypeSafe's System One decision models (Jev)
 * for the evaluator model role.
 *
 * @package    Gregius_Data
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TypeSafe AI Provider Class
 *
 * Implements the AI Provider Interface for TypeSafe's evaluator (decision)
 * models. Only the `evaluate` capability is supported; chat, embedding, and
 * rerank methods return WP_Error and the corresponding model-list methods
 * return empty arrays (there are no such models in the TypeSafe catalog).
 *
 * @since 1.0.0
 */
class GG_Data_Typesafe_Provider implements GG_Data_AI_Provider_Interface {

	/**
	 * TypeSafe API base URL.
	 *
	 * @var string
	 */
	const BASE_URL = 'https://api.typesafe.ai/v1';

	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	const DEFAULT_TIMEOUT = 30;

	/**
	 * Default model id, pinned for threshold stability.
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'jev-1.13.0';

	/**
	 * Jev context budget: 32k tokens for `state` plus the longest question.
	 * Conservative character ceiling (~4 chars/token) to stay well under it.
	 *
	 * @var int
	 */
	const MAX_STATE_CHARS = 120000;

	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Optional. API key for TypeSafe.
	 */
	public function __construct( string $api_key = '' ) {
		$this->api_key = $api_key;
	}

	/**
	 * Get the provider identifier.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'typesafe';
	}

	/**
	 * Get the provider display name.
	 *
	 * @since 1.0.0
	 * @return string Provider name.
	 */
	public function get_name(): string {
		return 'TypeSafe';
	}

	/**
	 * Get provider capabilities.
	 *
	 * @since 1.0.0
	 * @return array List of capabilities.
	 */
	public function get_capabilities(): array {
		return array( 'evaluator' );
	}

	/**
	 * Generate text from a prompt (LLM capability).
	 *
	 * TypeSafe does not support text generation.
	 *
	 * @since 1.0.0
	 * @param string $prompt  The prompt to generate from.
	 * @param array  $options Optional. Generation options.
	 * @return array|WP_Error WP_Error.
	 */
	public function generate_text( string $prompt, array $options = array() ): array|WP_Error {
		unset( $prompt, $options );

		return new WP_Error(
			'gg_data_typesafe_generate_not_supported',
			__( 'TypeSafe provider does not support text generation. Use evaluate() instead.', 'gregius-data' )
		);
	}

	/**
	 * Get available LLM models.
	 *
	 * TypeSafe provides no LLM models.
	 *
	 * @since 1.0.0
	 * @return array Empty array.
	 */
	public function get_llm_models(): array {
		return GG_Data_Model_Catalog::llm_models( 'typesafe' );
	}

	/**
	 * Get vision/document-capable models.
	 *
	 * TypeSafe does not support vision input.
	 *
	 * @since 1.0.0
	 * @return array Empty array.
	 */
	public function get_vision_models(): array {
		return GG_Data_Model_Catalog::vision_models( 'typesafe' );
	}

	/**
	 * Generate an embedding vector from text (Embeddings capability).
	 *
	 * TypeSafe does not support embeddings.
	 *
	 * @since 1.0.0
	 * @param string $text    The text to embed.
	 * @param array  $options Optional. Embedding options.
	 * @return array|WP_Error WP_Error.
	 */
	public function generate_embedding( string $text, array $options = array() ): array|WP_Error {
		unset( $text, $options );

		return new WP_Error(
			'gg_data_typesafe_embedding_not_supported',
			__( 'TypeSafe provider does not support embeddings.', 'gregius-data' )
		);
	}

	/**
	 * Get available embedding models.
	 *
	 * TypeSafe provides no embedding models.
	 *
	 * @since 1.0.0
	 * @return array Empty array.
	 */
	public function get_embedding_models(): array {
		return GG_Data_Model_Catalog::embedding_models( 'typesafe' );
	}

	/**
	 * Rerank documents based on relevance to a query (Rerank capability).
	 *
	 * TypeSafe does not support reranking.
	 *
	 * @since 1.0.0
	 * @param string $query     The search query.
	 * @param array  $documents Array of document strings to rerank.
	 * @param array  $options   Optional. Reranking options.
	 * @return array|WP_Error WP_Error.
	 */
	public function rerank( string $query, array $documents, array $options = array() ): array|WP_Error {
		unset( $query, $documents, $options );

		return new WP_Error(
			'gg_data_typesafe_rerank_not_supported',
			__( 'TypeSafe provider does not support reranking.', 'gregius-data' )
		);
	}

	/**
	 * Get available rerank models.
	 *
	 * TypeSafe provides no rerank models.
	 *
	 * @since 1.0.0
	 * @return array Empty array.
	 */
	public function get_rerank_models(): array {
		return GG_Data_Model_Catalog::rerank_models( 'typesafe' );
	}

	/**
	 * Get available evaluator (decision) models.
	 *
	 * Optional capability method — checked with method_exists() before use.
	 *
	 * @since 1.0.0
	 * @return array Evaluator model config keyed by model id.
	 */
	public function get_evaluator_models(): array {
		return GG_Data_Model_Catalog::evaluator_models( 'typesafe' );
	}

	/**
	 * Evaluate a state against a map of typed questions (Evaluator capability).
	 *
	 * Sends a `POST /v1/systemone` request with the reference content in
	 * `state` and the typed `questions` map, then returns the structured
	 * answers keyed by question id, plus the resolved model and token usage.
	 * The caller is responsible for interpreting the answers (e.g. thresholding
	 * a Noul probability into a verdict).
	 *
	 * Optional capability method — checked with method_exists() before use.
	 *
	 * @since 1.0.0
	 * @param string $state     The content to evaluate (reference context).
	 * @param array  $questions Map of question-id => typed Question object.
	 * @param array  $options   Optional. {model, api_key, timeout}.
	 * @return array|WP_Error {
	 *     On success:
	 *     @type array  $answers Map of question-id => Answer object.
	 *     @type string $model   Model that answered.
	 *     @type array  $usage   {input_tokens, output_tokens}.
	 * }
	 */
	public function evaluate( string $state, array $questions, array $options = array() ): array|WP_Error {
		$api_key = isset( $options['api_key'] ) && '' !== (string) $options['api_key']
			? $options['api_key']
			: $this->api_key;

		if ( empty( $api_key ) ) {
			// Fall back to the stored model config key.
			$model_registry = new GG_Data_Model_Registry();
			$model_data     = $model_registry->get_model( GG_Data_Model_Registry::MODEL_SCOPE_GLOBAL, $options['model'] ?? self::DEFAULT_MODEL );
			if ( $model_data && ! is_wp_error( $model_data ) ) {
				$api_key = $model_data['api_key'] ?? ( $model_data['config']['api_key'] ?? '' );
			}
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_typesafe_missing_api_key',
				__( 'TypeSafe API key is required for evaluation.', 'gregius-data' )
			);
		}

		if ( '' === trim( $state ) ) {
			return new WP_Error(
				'gg_data_typesafe_empty_state',
				__( 'State is required for evaluation.', 'gregius-data' )
			);
		}

		if ( empty( $questions ) ) {
			return new WP_Error(
				'gg_data_typesafe_empty_questions',
				__( 'At least one question is required for evaluation.', 'gregius-data' )
			);
		}

		$model = isset( $options['model'] ) && '' !== (string) $options['model']
			? $options['model']
			: self::DEFAULT_MODEL;

		$body = array(
			'state'     => $this->truncate_state( $state ),
			'model'     => $model,
			'questions' => $questions,
		);

		$response = wp_safe_remote_post(
			self::BASE_URL . '/systemone',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => isset( $options['timeout'] ) ? $options['timeout'] : self::DEFAULT_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_json   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'gg_data_typesafe_unauthorized',
				__( 'Invalid TypeSafe API key.', 'gregius-data' )
			);
		}

		if ( 429 === $status_code || 529 === $status_code ) {
			return new WP_Error(
				'gg_data_typesafe_rate_limited',
				__( 'TypeSafe is rate-limited or temporarily overloaded. Retry with backoff.', 'gregius-data' )
			);
		}

		if ( 200 !== $status_code ) {
			$error_message = is_array( $body_json ) && ! empty( $body_json['message'] )
				? $body_json['message']
				: __( 'Unknown TypeSafe API error', 'gregius-data' );
			return new WP_Error(
				'gg_data_typesafe_api_error',
				/* translators: %s: Error message from the TypeSafe API */
				sprintf( __( 'TypeSafe API error: %s', 'gregius-data' ), $error_message )
			);
		}

		if ( ! is_array( $body_json ) || empty( $body_json['answers'] ) || ! is_array( $body_json['answers'] ) ) {
			return new WP_Error(
				'gg_data_typesafe_invalid_response',
				__( 'Invalid response from the TypeSafe API: missing answers.', 'gregius-data' )
			);
		}

		return array(
			'answers' => $body_json['answers'],
			'model'   => isset( $body_json['model'] ) ? $body_json['model'] : $model,
			'usage'   => isset( $body_json['usage'] ) ? $body_json['usage'] : array(),
		);
	}

	/**
	 * Validate an API key with a trivial evaluation request.
	 *
	 * @since 1.0.0
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'gg_data_typesafe_no_api_key',
				__( 'TypeSafe API key is required.', 'gregius-data' )
			);
		}

		// Test with a trivial Noul evaluation so the actual evaluate() path is exercised.
		$response = wp_safe_remote_post(
			self::BASE_URL . '/systemone',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'state'     => 'This is a connection test.',
						'model'     => self::DEFAULT_MODEL,
						'questions' => array(
							'connection_test' => array(
								'type'         => 'noul',
								'instructions' => 'Is this a connection test?',
							),
						),
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'gg_data_typesafe_invalid_key',
				__( 'Invalid TypeSafe API key.', 'gregius-data' )
			);
		}

		if ( 200 !== $status_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( 'Unknown error', 'gregius-data' );
			return new WP_Error( 'gg_data_typesafe_api_error', $msg );
		}

		return true;
	}

	/**
	 * Truncate state to respect Jev's context budget.
	 *
	 * @since 1.0.0
	 * @param string $state The raw state.
	 * @return string State truncated to a safe character ceiling.
	 */
	private function truncate_state( string $state ): string {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $state ) > self::MAX_STATE_CHARS ) {
			return mb_substr( $state, 0, self::MAX_STATE_CHARS );
		}

		return $state;
	}
}
