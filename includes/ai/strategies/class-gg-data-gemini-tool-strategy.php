<?php
/**
 * Gemini Tool Calling Strategy
 *
 * Native tool calling implementation for Google Gemini API.
 * Uses the Gemini function_declarations format.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/ai/strategies
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Gemini_Tool_Strategy
 *
 * Implements native tool calling for Google Gemini API.
 *
 * @since 1.0.0
 */
class GG_Data_Gemini_Tool_Strategy implements GG_Data_Tool_Calling_Strategy {

	/**
	 * Google AI API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Get the strategy identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Strategy ID.
	 */
	public function get_id(): string {
		return 'gemini';
	}

	/**
	 * Select the appropriate tool using Gemini's native tool calling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query    User's query.
	 * @param array  $messages Conversation history.
	 * @param array  $tools    Available tools with descriptions.
	 * @param string $model_id Model ID to use for selection.
	 * @return array|WP_Error Selected tool and parameters.
	 */
	public function select_tool( string $query, array $messages, array $tools, string $model_id ): array|WP_Error {
		// Get model info.
		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $model_id );

		if ( ! $model_data || is_wp_error( $model_data ) ) {
			return new WP_Error( 'invalid_model', 'Tool selection model not found.' );
		}

		$model_config = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$model_name   = $model_config['provider_model_id'] ?? $model_data['provider_model_id'] ?? $model_id;

		// Get API key.
		$api_key = $this->get_api_key( $model_data, $model_config );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', 'API key not found for tool calling.' );
		}

		// Build API request.
		$system_prompt = $this->build_system_prompt();
		$contents      = $this->build_contents( $query, $messages );
		$api_tools     = $this->format_tools( $tools );

		// Make API request with tools.
		$response = $this->make_api_request(
			$api_key,
			$model_name,
			$system_prompt,
			$contents,
			$api_tools
		);

		if ( is_wp_error( $response ) ) {
			// Fall back to prompt-based strategy on error.
			$fallback = new GG_Data_Prompt_Tool_Strategy();
			return $fallback->select_tool( $query, $messages, $tools, $model_id );
		}

		// Parse response.
		$result = $this->parse_response( $response );

		/**
		 * Filter the tool selection result.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $result   Tool selection result.
		 * @param string $query    User query.
		 * @param array  $messages Conversation history.
		 */
		return apply_filters( 'gg_data_rag_tool_selection', $result, $query, $messages );
	}

	/**
	 * Format tools for Gemini's API.
	 *
	 * Gemini uses function_declarations format.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Tool definitions in standard format.
	 * @return array Formatted tools for Gemini API request.
	 */
	public function format_tools( array $tools ): array {
		$function_declarations = array();

		foreach ( $tools as $name => $tool ) {
			$function_declarations[] = array(
				'name'        => $tool['name'] ?? $name,
				'description' => $tool['description'] ?? '',
				'parameters'  => $tool['parameters'] ?? array(
					'type'       => 'object',
					'properties' => array(),
				),
			);
		}

		// Gemini wraps function declarations in a tools array.
		return array(
			array(
				'function_declarations' => $function_declarations,
			),
		);
	}

	/**
	 * Parse the tool selection response from Gemini.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response Gemini API response.
	 * @return array Parsed tool selection.
	 */
	public function parse_response( $response ): array {
		// Navigate to the response content.
		$parts = $response['candidates'][0]['content']['parts'] ?? array();

		// Look for functionCall in parts.
		foreach ( $parts as $part ) {
			if ( isset( $part['functionCall'] ) ) {
				$tool_name = $part['functionCall']['name'] ?? 'search_content';
				$arguments = $part['functionCall']['args'] ?? array();

				return array_merge(
					array( 'tool' => $tool_name ),
					$arguments
				);
			}
		}

		// If model returned text instead of function call, try to parse JSON.
		foreach ( $parts as $part ) {
			if ( isset( $part['text'] ) ) {
				$text   = trim( $part['text'] );
				$result = json_decode( $text, true );

				if ( $result && isset( $result['tool'] ) ) {
					return $result;
				}
			}
		}

		// Fallback to search_content.
		return array(
			'tool'         => 'search_content',
			'reason'       => 'No tool call in response, defaulting to search',
			'search_query' => '',
		);
	}

	/**
	 * Build system prompt for tool selection.
	 *
	 * @since 1.0.0
	 *
	 * @return string System prompt.
	 */
	private function build_system_prompt(): string {
		$current_date = wp_date( 'F j, Y' );
		$current_time = wp_date( 'g:i A T' );

		return 'You are a query router. Analyze the user\'s query and select the best tool to handle it.

Current date: ' . $current_date . '
Current time: ' . $current_time . '

CRITICAL for search_query parameter:
- When user says "[topic] page" or "the [topic] page", extract [topic] as search_query.
- PRESERVE explicit content names mentioned by the user.
- Only resolve ambiguous pronouns (they, it, them) using conversation context.
- Remove filler words like "summarize", "tell me about", "what is".

Rules:
- Questions about website pages or content → search_content
- "compare X vs Y", "difference between X and Y", "how does X differ from Y" → compare_content with entities=[X, Y]
- "our conversation", "this chat", "what we discussed" → summarize_conversation
- Greetings, meta questions, time questions → respond_directly
- "explain that", "simpler", "more details", "elaborate" → clarify_previous';
	}

	/**
	 * Build contents array for API request.
	 *
	 * Gemini uses 'contents' with 'user'/'model' roles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query    User's query.
	 * @param array  $messages Conversation history.
	 * @return array Contents array for API.
	 */
	private function build_contents( string $query, array $messages ): array {
		$contents = array();

		// Add conversation history.
		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				$role = $msg['role'];

				// Skip system messages (handled separately).
				if ( 'system' === $role ) {
					continue;
				}

				// Map 'assistant' to 'model' for Gemini.
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

		// Add current query.
		$contents[] = array(
			'role'  => 'user',
			'parts' => array(
				array( 'text' => $query ),
			),
		);

		return $contents;
	}

	/**
	 * Get API key from model configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param array $model_data   Model data from registry.
	 * @param array $model_config Model configuration.
	 * @return string API key or empty string.
	 */
	private function get_api_key( array $model_data, array $model_config ): string {
		// Try model data first.
		if ( ! empty( $model_data['api_key'] ) ) {
			return $model_data['api_key'];
		}

		// Try config.
		if ( ! empty( $model_config['api_key'] ) ) {
			return $model_config['api_key'];
		}

		return '';
	}

	/**
	 * Make API request with tool calling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key       API key.
	 * @param string $model         Model identifier.
	 * @param string $system_prompt System prompt.
	 * @param array  $contents      Contents array.
	 * @param array  $tools         Formatted tools array.
	 * @return array|WP_Error API response or error.
	 */
	private function make_api_request( string $api_key, string $model, string $system_prompt, array $contents, array $tools ): array|WP_Error {
		$body = array(
			'contents'          => $contents,
			'tools'             => $tools,
			'systemInstruction' => array(
				'parts' => array(
					array( 'text' => $system_prompt ),
				),
			),
			'generationConfig'  => array(
				'maxOutputTokens' => 100,
				'temperature'     => 0.1,
			),
		);

		// Build URL with model and API key.
		$url = sprintf(
			'%s/models/%s:generateContent?key=%s',
			self::BASE_URL,
			$model,
			$api_key
		);

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
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
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
			return new WP_Error(
				'api_error',
				/* translators: %s: Error message from API */
				sprintf( __( 'Gemini tool calling API error: %s', 'gregius-data' ), $error_message )
			);
		}

		return $data;
	}
}
