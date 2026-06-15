<?php
/**
 * Prompt-Based Tool Calling Strategy
 *
 * Universal fallback strategy using prompt-based JSON routing.
 * Works with any LLM provider - sends tools as text in system prompt
 * and expects JSON response.
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
 * Class GG_Data_Prompt_Tool_Strategy
 *
 * Implements tool selection via prompt-based JSON routing.
 * This is the universal fallback that works with all providers.
 *
 * @since 1.0.0
 */
class GG_Data_Prompt_Tool_Strategy implements GG_Data_Tool_Calling_Strategy {

	/**
	 * Get the strategy identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Strategy ID.
	 */
	public function get_id(): string {
		return 'prompt';
	}

	/**
	 * Select the appropriate tool for the query using prompt-based routing.
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

		// Build conversation context if available.
		$conversation_context = $this->build_conversation_context( $messages );

		// Build system prompt with tools.
		$system_prompt = $this->build_system_prompt( $tools );

		// Build user prompt.
		$prompt = $conversation_context . "User query: \"{$query}\"\n\nSelect tool:";

		// Make API call.
		$response = GG_Data_Ai_Client::prompt( $prompt )
			->setSystemMessage( $system_prompt )
			->usingProvider( $provider_id )
			->usingModel( $model_name )
			->usingConnection( 'gregius-data' )
			->withMaxTokens( 100 )
			->generateText();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse and validate response.
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
	 * Format tools for prompt-based routing (not used for API request).
	 *
	 * For prompt-based strategy, tools are embedded in the system prompt text.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Tool definitions in standard format.
	 * @return array Empty array (tools are in system prompt).
	 */
	public function format_tools( array $tools ): array {
		// Prompt-based doesn't use formatted tools in request body.
		return array();
	}

	/**
	 * Parse the JSON response from prompt-based tool selection.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response Raw response string.
	 * @return array Parsed tool selection.
	 */
	public function parse_response( $response ): array {
		// Clean response.
		$response = trim( $response );

		// Remove markdown code blocks if present.
		$response = preg_replace( '/^```(?:json)?\s*/', '', $response );
		$response = preg_replace( '/\s*```$/', '', $response );

		// Parse JSON.
		$result = json_decode( $response, true );

		// Validate result.
		if ( ! $result || ! isset( $result['tool'] ) ) {
			// Fallback to search_content if parsing fails.
			return array(
				'tool'         => 'search_content',
				'reason'       => 'JSON parsing failed, defaulting to search',
				'search_query' => '',
			);
		}

		return $result;
	}

	/**
	 * Build conversation context string from messages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $messages Conversation history.
	 * @return string Formatted conversation context.
	 */
	private function build_conversation_context( array $messages ): string {
		if ( empty( $messages ) ) {
			return '';
		}

		$context = "Conversation history:\n";
		foreach ( $messages as $msg ) {
			$role     = 'user' === $msg['role'] ? 'User' : 'Assistant';
			$context .= "{$role}: {$msg['content']}\n";
		}
		$context .= "\n";

		return $context;
	}

	/**
	 * Build system prompt with tool descriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Tool definitions.
	 * @return string System prompt for tool selection.
	 */
	private function build_system_prompt( array $tools ): string {
		$current_date = wp_date( 'F j, Y' );
		$current_time = wp_date( 'g:i A T' );

		// Build tools list from definitions.
		$tools_text = $this->build_tools_text( $tools );

		return 'You are a query router. Analyze the user\'s query and select the best tool to handle it.

Available tools:
' . $tools_text . '

Current date: ' . $current_date . '
Current time: ' . $current_time . '

Respond in this exact JSON format:
{"tool": "tool_name", "reason": "brief reason", "search_query": "optimized search terms if tool is search_content", "entities": ["Entity A", "Entity B"] if tool is compare_content, "clarification_type": "simpler|detailed|example|rephrase if tool is clarify_previous"}

CRITICAL for search_query:
- When user says "[topic] page" or "the [topic] page", they are referring to a website page with that name. Extract [topic] as the search_query.
- PRESERVE explicit content names mentioned by the user
- Only resolve ambiguous pronouns (they, it, them) using conversation context
- Remove filler words like "summarize", "tell me about", "what is"

Rules:
- Questions about website pages or content → search_content
- "compare X vs Y", "difference between X and Y", "how does X differ from Y" → compare_content with entities=["X", "Y"]
- "our conversation", "this chat", "what we discussed" → summarize_conversation
- Greetings, time questions → respond_directly
- "explain that", "simpler", "more details", "give me an example", "what do you mean", "elaborate", "in other words" → clarify_previous
- ONLY output the JSON, nothing else';
	}

	/**
	 * Build tools text for system prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Tool definitions.
	 * @return string Formatted tools text.
	 */
	private function build_tools_text( array $tools ): string {
		$lines = array();
		$index = 1;

		foreach ( $tools as $name => $tool ) {
			$description = $tool['description'] ?? '';
			$lines[]     = "{$index}. {$name} - {$description}";
			++$index;
		}

		return implode( "\n", $lines );
	}
}
