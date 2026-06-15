<?php
/**
 * OpenAI Tool Calling Strategy
 *
 * Native tool calling implementation for OpenAI and DeepSeek APIs.
 * Uses the OpenAI tools API format with function calling.
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
 * Class GG_Data_OpenAI_Tool_Strategy
 *
 * Implements native tool calling for OpenAI and DeepSeek APIs.
 *
 * @since 1.0.0
 */
class GG_Data_OpenAI_Tool_Strategy implements GG_Data_Tool_Calling_Strategy {

	/**
	 * Get the strategy identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Strategy ID.
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * Select the appropriate tool using OpenAI's native tool calling.
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
		$provider_id  = $model_data['provider'] ?? $model_config['provider'] ?? 'openai';

		// Get API key.
		$api_key = $this->get_api_key( $model_data, $model_config, $provider_id );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', 'API key not found for tool calling.' );
		}

		// Build API request.
		$api_messages = $this->build_messages( $query, $messages );
		$api_tools    = $this->format_tools( $tools );

		// Get API endpoint based on provider.
		$api_url = $this->get_api_url( $provider_id );

		// Make API request with tools.
		$response = $this->make_api_request(
			$api_url,
			$api_key,
			$model_name,
			$api_messages,
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
	 * Format tools for OpenAI's API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Tool definitions in standard format.
	 * @return array Formatted tools for OpenAI API request.
	 */
	public function format_tools( array $tools ): array {
		$formatted = array();

		foreach ( $tools as $name => $tool ) {
			$formatted[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool['name'] ?? $name,
					'description' => $tool['description'] ?? '',
					'parameters'  => $tool['parameters'] ?? array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			);
		}

		return $formatted;
	}

	/**
	 * Parse the tool selection response from OpenAI.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response OpenAI API response.
	 * @return array Parsed tool selection.
	 */
	public function parse_response( $response ): array {
		// Check for tool_calls in response.
		$message = $response['choices'][0]['message'] ?? array();

		// If model used tool calling.
		if ( isset( $message['tool_calls'][0] ) ) {
			$tool_call = $message['tool_calls'][0];
			$tool_name = $tool_call['function']['name'] ?? 'search_content';
			$arguments = json_decode( $tool_call['function']['arguments'] ?? '{}', true );

			return array_merge(
				array( 'tool' => $tool_name ),
				$arguments
			);
		}

		// If model returned regular content (didn't use tools).
		if ( isset( $message['content'] ) ) {
			// Try to parse as JSON in case model returned JSON without using tools.
			$content = trim( $message['content'] );
			$result  = json_decode( $content, true );

			if ( $result && isset( $result['tool'] ) ) {
				return $result;
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
	 * Build conversation messages for API request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query    User's query.
	 * @param array  $messages Conversation history.
	 * @return array Messages array for API.
	 */
	private function build_messages( string $query, array $messages ): array {
		$current_date = wp_date( 'F j, Y' );
		$current_time = wp_date( 'g:i A T' );

		$api_messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a query router. Analyze the user\'s query and select the best tool to handle it.

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
- "explain that", "simpler", "more details", "elaborate" → clarify_previous',
			),
		);

		// Add conversation history.
		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				$api_messages[] = array(
					'role'    => $msg['role'],
					'content' => $msg['content'],
				);
			}
		}

		// Add current query.
		$api_messages[] = array(
			'role'    => 'user',
			'content' => $query,
		);

		return $api_messages;
	}

	/**
	 * Get API URL based on provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string API endpoint URL.
	 */
	private function get_api_url( string $provider_id ): string {
		switch ( $provider_id ) {
			case 'deepseek':
				return 'https://api.deepseek.com/chat/completions';
			case 'openai':
			default:
				return 'https://api.openai.com/v1/chat/completions';
		}
	}

	/**
	 * Get API key from model configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $model_data   Model data from registry.
	 * @param array  $model_config Model configuration.
	 * @param string $provider_id  Provider identifier.
	 * @return string API key or empty string.
	 */
	private function get_api_key( array $model_data, array $model_config, string $provider_id ): string {
		// Try model data first.
		if ( ! empty( $model_data['api_key'] ) ) {
			return $model_data['api_key'];
		}

		// Try config.
		if ( ! empty( $model_config['api_key'] ) ) {
			return $model_config['api_key'];
		}

		// Try settings manager as fallback.
		$settings = new GG_Data_Settings_Manager();
		$api_key  = $settings->get_with_category( 'rag_tfidf_300', 'gregius-data', 'openai_api_key' );

		return ! empty( $api_key ) ? $api_key : '';
	}

	/**
	 * Make API request with tool calling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_url  API endpoint URL.
	 * @param string $api_key  API key.
	 * @param string $model    Model identifier.
	 * @param array  $messages Messages array.
	 * @param array  $tools    Formatted tools array.
	 * @return array|WP_Error API response or error.
	 */
	private function make_api_request( string $api_url, string $api_key, string $model, array $messages, array $tools ): array|WP_Error {
		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'tools'       => $tools,
			'tool_choice' => 'auto',
			'max_tokens'  => 100,
			'temperature' => 0.1,
		);

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
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
			return new WP_Error(
				'api_error',
				/* translators: %s: Error message from API */
				sprintf( __( 'Tool calling API error: %s', 'gregius-data' ), $error_message )
			);
		}

		return $data;
	}
}
