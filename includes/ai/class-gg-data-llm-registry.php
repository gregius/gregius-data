<?php
/**
 * LLM Provider Registry
 *
 * Manages the registration and retrieval of AI providers.
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
 * Class GG_Data_LLM_Registry
 *
 * @since 1.0.0
 */
class GG_Data_LLM_Registry {

	/**
	 * Array of registered providers.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected static $providers = array();

	/**
	 * Get all registered providers.
	 *
	 * @since 1.0.0
	 * @return array Array of GG_Data_LLM_Provider_Interface objects.
	 */
	public static function get_providers() {
		if ( empty( self::$providers ) ) {
			/**
			 * Filter to register AI providers.
			 *
			 * @since 1.0.0
			 * @param array $providers Array of provider instances.
			 */
			self::$providers = apply_filters( 'gg_data_llm_providers', array() );
		}

		return self::$providers;
	}

	/**
	 * Get a specific provider by ID.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID (e.g., 'openai').
	 * @return GG_Data_LLM_Provider_Interface|WP_Error Provider instance or error.
	 */
	public static function get_provider( $provider_id ) {
		$providers = self::get_providers();

		if ( isset( $providers[ $provider_id ] ) ) {
			return $providers[ $provider_id ];
		}

		return new WP_Error(
			'gg_data_provider_not_found',
			/* translators: %s: Provider ID */
			sprintf( __( 'AI Provider "%s" not found.', 'gregius-data' ), $provider_id )
		);
	}
}
