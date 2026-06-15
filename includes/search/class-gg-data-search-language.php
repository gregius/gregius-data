<?php
/**
 * Search Language Helper
 *
 * Maps WordPress locales to PostgreSQL text search configurations
 * and provides language-related utilities for full-text search.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Language Helper Class
 */
class GG_Data_Search_Language {

	/**
	 * Map WordPress locale to PostgreSQL text search configuration
	 *
	 * @param string $locale WordPress locale (e.g., 'en_US', 'es_ES', 'fr_FR').
	 * @return string PostgreSQL text search config (e.g., 'english', 'spanish', 'french').
	 */
	public static function map_locale_to_pg_language( $locale = '' ) {
		if ( empty( $locale ) ) {
			$locale = get_locale();
		}

		// Direct locale mappings.
		$locale_map = array(
			// English variants.
			'en_US' => 'english',
			'en_CA' => 'english',
			'en_GB' => 'english',
			'en_AU' => 'english',
			'en_NZ' => 'english',
			'en_ZA' => 'english',

			// Spanish variants.
			'es_ES' => 'spanish',
			'es_MX' => 'spanish',
			'es_AR' => 'spanish',
			'es_CO' => 'spanish',
			'es_CL' => 'spanish',
			'es_VE' => 'spanish',
			'es_PE' => 'spanish',

			// French variants.
			'fr_FR' => 'french',
			'fr_CA' => 'french',
			'fr_BE' => 'french',
			'fr_CH' => 'french',

			// German variants.
			'de_DE' => 'german',
			'de_AT' => 'german',
			'de_CH' => 'german',

			// Italian.
			'it_IT' => 'italian',

			// Portuguese variants.
			'pt_PT' => 'portuguese',
			'pt_BR' => 'portuguese',

			// Russian.
			'ru_RU' => 'russian',

			// Dutch.
			'nl_NL' => 'dutch',
			'nl_BE' => 'dutch',

			// Danish.
			'da_DK' => 'danish',

			// Finnish.
			'fi'    => 'finnish',

			// Norwegian.
			'nb_NO' => 'norwegian',
			'nn_NO' => 'norwegian',

			// Swedish.
			'sv_SE' => 'swedish',

			// Turkish.
			'tr_TR' => 'turkish',

			// Romanian.
			'ro_RO' => 'romanian',
		);

		// Check for direct match.
		if ( isset( $locale_map[ $locale ] ) ) {
			return $locale_map[ $locale ];
		}

		// Extract language code (before underscore).
		$language_code = explode( '_', $locale )[0];

		// Try matching just the language code.
		$language_map = array(
			'en' => 'english',
			'es' => 'spanish',
			'fr' => 'french',
			'de' => 'german',
			'it' => 'italian',
			'pt' => 'portuguese',
			'ru' => 'russian',
			'nl' => 'dutch',
			'da' => 'danish',
			'fi' => 'finnish',
			'no' => 'norwegian',
			'nb' => 'norwegian',
			'nn' => 'norwegian',
			'sv' => 'swedish',
			'tr' => 'turkish',
			'ro' => 'romanian',
		);

		if ( isset( $language_map[ $language_code ] ) ) {
			return $language_map[ $language_code ];
		}

		// Fallback to 'simple' (no stemming, minimal stop words).
		return 'simple';
	}

	/**
	 * Get current WordPress site language for search
	 *
	 * @return string PostgreSQL text search configuration.
	 */
	public static function get_site_search_language() {
		return self::map_locale_to_pg_language( get_locale() );
	}

	/**
	 * Get list of supported PostgreSQL text search configurations
	 *
	 * @return array Array of language codes and names.
	 */
	public static function get_supported_languages() {
		return array(
			'simple'     => __( 'Simple (No Stemming)', 'gregius-data' ),
			'danish'     => __( 'Danish', 'gregius-data' ),
			'dutch'      => __( 'Dutch', 'gregius-data' ),
			'english'    => __( 'English', 'gregius-data' ),
			'finnish'    => __( 'Finnish', 'gregius-data' ),
			'french'     => __( 'French', 'gregius-data' ),
			'german'     => __( 'German', 'gregius-data' ),
			'italian'    => __( 'Italian', 'gregius-data' ),
			'norwegian'  => __( 'Norwegian', 'gregius-data' ),
			'portuguese' => __( 'Portuguese', 'gregius-data' ),
			'romanian'   => __( 'Romanian', 'gregius-data' ),
			'russian'    => __( 'Russian', 'gregius-data' ),
			'spanish'    => __( 'Spanish', 'gregius-data' ),
			'swedish'    => __( 'Swedish', 'gregius-data' ),
			'turkish'    => __( 'Turkish', 'gregius-data' ),
		);
	}

	/**
	 * Check if a language is supported by PostgreSQL
	 *
	 * @param string $language Language code.
	 * @return bool True if supported, false otherwise.
	 */
	public static function is_language_supported( $language ) {
		$supported = array_keys( self::get_supported_languages() );
		return in_array( $language, $supported, true );
	}
}
