<?php
/**
 * AI Client Facade
 *
 * The main entry point for AI operations, mimicking the proposed WordPress PHP AI Client.
 *
 * @package Gregius_Data
 * @subpackage Gregius_Data/includes/ai
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gregius_Data_Ai_Client
 *
 * @since 1.0.0
 */
class Gregius_Data_Ai_Client {

	/**
	 * Start a new AI request.
	 *
	 * @since 1.0.0
	 * @param string $prompt The user prompt.
	 * @return Gregius_Data_Ai_Request The request object for chaining.
	 */
	public static function prompt( string $prompt ): Gregius_Data_Ai_Request {
		return new Gregius_Data_Ai_Request( $prompt );
	}

	/**
	 * Get available providers.
	 *
	 * @since 1.0.0
	 * @return array List of providers.
	 */
	public static function get_providers() {
		return Gregius_Data_LLM_Registry::get_providers();
	}
}
