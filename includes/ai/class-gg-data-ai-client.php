<?php
/**
 * AI Client Facade
 *
 * The main entry point for AI operations, mimicking the proposed WordPress PHP AI Client.
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
 * Class GG_Data_Ai_Client
 *
 * @since 1.0.0
 */
class GG_Data_Ai_Client {

	/**
	 * Start a new AI request.
	 *
	 * @since 1.0.0
	 * @param string $prompt The user prompt.
	 * @return GG_Data_Ai_Request The request object for chaining.
	 */
	public static function prompt( string $prompt ): GG_Data_Ai_Request {
		return new GG_Data_Ai_Request( $prompt );
	}

	/**
	 * Get available providers.
	 *
	 * @since 1.0.0
	 * @return array List of providers.
	 */
	public static function get_providers() {
		return GG_Data_LLM_Registry::get_providers();
	}
}
