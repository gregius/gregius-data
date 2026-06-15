<?php
/**
 * Tool Calling Strategy Interface
 *
 * Defines the contract for tool calling strategies. Implementations can use
 * native tool calling APIs (OpenAI, Anthropic, Gemini) or prompt-based fallback.
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
 * Interface GG_Data_Tool_Calling_Strategy
 *
 * Strategy pattern interface for tool selection. Enables provider-specific
 * native tool calling while maintaining a universal prompt-based fallback.
 *
 * @since 1.0.0
 */
interface GG_Data_Tool_Calling_Strategy {

	/**
	 * Select the appropriate tool for the query.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query    User's query.
	 * @param array  $messages Conversation history.
	 * @param array  $tools    Available tools with descriptions.
	 * @param string $model_id Model ID to use for selection.
	 * @return array|WP_Error {
	 *     Selected tool and parameters on success, WP_Error on failure.
	 *
	 *     @type string $tool               Tool name (search_content, respond_directly, etc.).
	 *     @type string $reason             Brief reason for selection (optional).
	 *     @type string $search_query       Optimized search terms (for search_content).
	 *     @type string $clarification_type Clarification type (for clarify_previous).
	 * }
	 */
	public function select_tool( string $query, array $messages, array $tools, string $model_id ): array|WP_Error;

	/**
	 * Format tools for the provider's API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Tool definitions in standard format.
	 * @return array Formatted tools for API request.
	 */
	public function format_tools( array $tools ): array;

	/**
	 * Parse the tool selection response.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response Provider's raw response.
	 * @return array {
	 *     Parsed tool selection.
	 *
	 *     @type string $tool               Tool name.
	 *     @type string $reason             Selection reason (optional).
	 *     @type string $search_query       Search terms (optional).
	 *     @type string $clarification_type Clarification type (optional).
	 * }
	 */
	public function parse_response( $response ): array;

	/**
	 * Get the strategy identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Strategy ID (e.g., 'openai', 'anthropic', 'prompt').
	 */
	public function get_id(): string;
}
