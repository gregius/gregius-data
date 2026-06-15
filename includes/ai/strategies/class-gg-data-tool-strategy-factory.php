<?php
/**
 * Tool Strategy Factory
 *
 * Creates the appropriate tool calling strategy based on provider and capabilities.
 * Uses native tool calling when supported, falls back to prompt-based.
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
 * Class GG_Data_Tool_Strategy_Factory
 *
 * Factory for creating tool calling strategies.
 *
 * @since 1.0.0
 */
class GG_Data_Tool_Strategy_Factory {

	/**
	 * Providers that support native tool calling.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $native_tool_providers = array(
		'openai',
		'deepseek',
		'anthropic',
		'gemini',
	);

	/**
	 * Create a tool calling strategy based on provider and capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider       Provider ID (openai, anthropic, gemini, deepseek).
	 * @param bool   $supports_tools Whether the model supports native tool calling.
	 *                               If null, auto-detects based on provider.
	 * @return GG_Data_Tool_Calling_Strategy Strategy instance.
	 */
	public static function create( string $provider, ?bool $supports_tools = null ): GG_Data_Tool_Calling_Strategy {
		// Auto-detect tool support if not explicitly specified.
		if ( null === $supports_tools ) {
			$supports_tools = in_array( $provider, self::$native_tool_providers, true );
		}

		// If provider doesn't support tools, use prompt-based fallback.
		if ( ! $supports_tools ) {
			return new GG_Data_Prompt_Tool_Strategy();
		}

		// Create provider-specific strategy.
		switch ( $provider ) {
			case 'openai':
			case 'deepseek':
				return new GG_Data_OpenAI_Tool_Strategy();

			case 'anthropic':
				return new GG_Data_Anthropic_Tool_Strategy();

			case 'gemini':
				return new GG_Data_Gemini_Tool_Strategy();

			default:
				// Unknown provider - use prompt-based fallback.
				return new GG_Data_Prompt_Tool_Strategy();
		}
	}

	/**
	 * Create a strategy for a specific model.
	 *
	 * Looks up model configuration to determine provider and tool support.
	 * Auto-detects tool support based on provider if not explicitly configured.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model ID to get strategy for.
	 * @return GG_Data_Tool_Calling_Strategy Strategy instance.
	 */
	public static function create_for_model( string $model_id ): GG_Data_Tool_Calling_Strategy {
		$model_registry = new GG_Data_Model_Registry();
		$model_data     = $model_registry->get_model( 'gregius-data', $model_id );

		if ( ! $model_data || is_wp_error( $model_data ) ) {
			// Model not found - use prompt-based fallback.
			return new GG_Data_Prompt_Tool_Strategy();
		}

		$model_config = is_array( $model_data['config'] ) ? $model_data['config'] : array();
		$provider     = $model_data['provider'] ?? $model_config['provider'] ?? 'openai';

		// Check for explicit supports_tools setting, otherwise auto-detect.
		$supports_tools = null;
		if ( isset( $model_data['supports_tools'] ) ) {
			$supports_tools = (bool) $model_data['supports_tools'];
		} elseif ( isset( $model_config['supports_tools'] ) ) {
			$supports_tools = (bool) $model_config['supports_tools'];
		}

		return self::create( $provider, $supports_tools );
	}

	/**
	 * Check if a provider supports native tool calling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider ID.
	 * @return bool True if provider supports native tools.
	 */
	public static function provider_supports_tools( string $provider ): bool {
		return in_array( $provider, self::$native_tool_providers, true );
	}

	/**
	 * Get all available strategy types.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of strategy type => description.
	 */
	public static function get_available_strategies(): array {
		return array(
			'openai'    => 'OpenAI/DeepSeek native tool calling',
			'anthropic' => 'Anthropic native tool calling',
			'gemini'    => 'Google Gemini native tool calling',
			'prompt'    => 'Universal prompt-based fallback',
		);
	}
}
