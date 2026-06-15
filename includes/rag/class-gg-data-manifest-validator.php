<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Manifest Validator
 *
 * Defines and enforces the canonical request manifest contract used by gregius-data.
 * Any client plugin (e.g. gregius-intelligence) that sends a manifest payload to the
 * RAG endpoint must conform to the schema documented here.
 *
 * Schema version: 1.0.0
 *
 * Manifest structure:
 * {
 *   "manifest_version": "1.0.0",      // Required. Semver string.
 *   "state": {                         // Optional. Per-turn conversation state.
 *     "conversation_id":          "",  // UUID of current conversation turn.
 *     "journey_depth_turns":      0,   // Number of completed user turns.
 *     "is_historical_resume":     false,
 *     "active_mode":              "default",
 *     "recent_mixed_initiatives": []
 *   },
 *   "entity": {                        // Optional. Active WP entity at render time.
 *     "id":     0,                     // WP post ID (absint).
 *     "type":   "",                    // Post type slug (sanitize_key).
 *     "status": "",                    // Post status slug.
 *     "title":  { "raw": "", "rendered": "" },
 *     "dates":  { "created_gmt": "", "modified_gmt": "" },
 *     "author": { "id": 0, "name": "" },
 *     "taxonomies": {},                // Keyed by taxonomy label, value = array of term objects.
 *     "meta":   {},                    // Post meta, pre-sanitized by client.
 *     "raw_context": {}
 *   },
 *   "search_capabilities": {           // Optional. Client-advertised retrieval capabilities.
 *     "primary_index":       "wp_posts_clean",
 *     "supported_filters":   [],
 *     "fallback_strategies": []
 *   }
 * }
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/rag
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GG_Data_Manifest_Validator
 *
 * Validates and normalizes a request manifest payload against the canonical schema.
 * Returns a fully-normalized manifest array with safe defaults for missing fields.
 *
 * Usage:
 *   $manifest = GG_Data_Manifest_Validator::normalize( $raw_manifest );
 *   if ( GG_Data_Manifest_Validator::is_supported_version( $manifest['manifest_version'] ) ) { ... }
 *
 * @since 1.0.0
 */
class GG_Data_Manifest_Validator {

	/**
	 * Current manifest schema version supported by this plugin.
	 *
	 * Bump this constant when making breaking schema changes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Minimum manifest version accepted without degraded-mode fallback.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MIN_VERSION = '1.0.0';

	/**
	 * Normalize and validate a raw manifest payload.
	 *
	 * Accepts any value. Returns a fully-normalized array conforming to the
	 * canonical schema, with safe defaults applied for all missing fields.
	 * Does not throw — always returns a usable array.
	 *
	 * @since 1.0.0
	 * @param mixed $raw Raw manifest value (should be array; other types return empty manifest).
	 * @return array Normalized manifest.
	 */
	public static function normalize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return self::empty_manifest();
		}

		return array(
			'manifest_version'    => self::normalize_version( $raw['manifest_version'] ?? '' ),
			'state'               => self::normalize_state( $raw['state'] ?? array() ),
			'entity'              => self::normalize_entity( $raw['entity'] ?? array() ),
			'search_capabilities' => self::normalize_search_capabilities( $raw['search_capabilities'] ?? array() ),
		);
	}

	/**
	 * Check whether a manifest version string is supported.
	 *
	 * A version is supported when it is >= MIN_VERSION and <= SCHEMA_VERSION.
	 * Returns false for empty or unparseable strings.
	 *
	 * @since 1.0.0
	 * @param string $version Manifest version string.
	 * @return bool
	 */
	public static function is_supported_version( $version ) {
		if ( '' === $version ) {
			return false;
		}

		return version_compare( $version, self::MIN_VERSION, '>=' )
			&& version_compare( $version, self::SCHEMA_VERSION, '<=' );
	}

	/**
	 * Return an empty manifest with all schema defaults applied.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function empty_manifest() {
		return array(
			'manifest_version'    => '',
			'state'               => self::normalize_state( array() ),
			'entity'              => self::normalize_entity( array() ),
			'search_capabilities' => self::normalize_search_capabilities( array() ),
		);
	}

	/**
	 * Normalize the manifest_version field.
	 *
	 * @since 1.0.0
	 * @param mixed $version Raw value.
	 * @return string Sanitized semver string or empty string.
	 */
	private static function normalize_version( $version ) {
		if ( ! is_string( $version ) || '' === trim( $version ) ) {
			return '';
		}

		// Allow only semver-compatible strings: digits and dots, max 32 chars.
		$sanitized = preg_replace( '/[^0-9.]/', '', $version );

		return '' !== $sanitized ? substr( $sanitized, 0, 32 ) : '';
	}

	/**
	 * Normalize the state block.
	 *
	 * @since 1.0.0
	 * @param mixed $state Raw state value.
	 * @return array
	 */
	private static function normalize_state( $state ) {
		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return array(
			'conversation_id'          => isset( $state['conversation_id'] ) ? sanitize_text_field( (string) $state['conversation_id'] ) : '',
			'journey_depth_turns'      => isset( $state['journey_depth_turns'] ) ? absint( $state['journey_depth_turns'] ) : 0,
			'is_historical_resume'     => isset( $state['is_historical_resume'] ) ? (bool) $state['is_historical_resume'] : false,
			'active_mode'              => isset( $state['active_mode'] ) ? sanitize_key( (string) $state['active_mode'] ) : 'default',
			'recent_mixed_initiatives' => isset( $state['recent_mixed_initiatives'] ) && is_array( $state['recent_mixed_initiatives'] )
				? array_values( $state['recent_mixed_initiatives'] )
				: array(),
		);
	}

	/**
	 * Normalize the entity block.
	 *
	 * @since 1.0.0
	 * @param mixed $entity Raw entity value.
	 * @return array
	 */
	private static function normalize_entity( $entity ) {
		if ( ! is_array( $entity ) || empty( $entity ) ) {
			return array();
		}

		$normalized = array(
			'id'     => isset( $entity['id'] ) ? absint( $entity['id'] ) : 0,
			'type'   => isset( $entity['type'] ) ? sanitize_key( (string) $entity['type'] ) : '',
			'status' => isset( $entity['status'] ) ? sanitize_key( (string) $entity['status'] ) : '',
		);

		// Title.
		if ( isset( $entity['title'] ) && is_array( $entity['title'] ) ) {
			$normalized['title'] = array(
				'raw'      => isset( $entity['title']['raw'] ) ? (string) $entity['title']['raw'] : '',
				'rendered' => isset( $entity['title']['rendered'] ) ? wp_strip_all_tags( (string) $entity['title']['rendered'] ) : '',
			);
		}

		// Dates.
		if ( isset( $entity['dates'] ) && is_array( $entity['dates'] ) ) {
			$normalized['dates'] = array(
				'created_gmt'  => isset( $entity['dates']['created_gmt'] ) ? sanitize_text_field( (string) $entity['dates']['created_gmt'] ) : '',
				'modified_gmt' => isset( $entity['dates']['modified_gmt'] ) ? sanitize_text_field( (string) $entity['dates']['modified_gmt'] ) : '',
			);
		}

		// Author.
		if ( isset( $entity['author'] ) && is_array( $entity['author'] ) ) {
			$normalized['author'] = array(
				'id'   => isset( $entity['author']['id'] ) ? absint( $entity['author']['id'] ) : 0,
				'name' => isset( $entity['author']['name'] ) ? sanitize_text_field( (string) $entity['author']['name'] ) : '',
			);
		}

		// Taxonomies — pass through as-is (client-sourced, array of term objects).
		if ( isset( $entity['taxonomies'] ) && is_array( $entity['taxonomies'] ) ) {
			$normalized['taxonomies'] = $entity['taxonomies'];
		}

		// Meta — pass through as-is (client-sourced, already sanitized by render.php).
		if ( isset( $entity['meta'] ) && is_array( $entity['meta'] ) ) {
			$normalized['meta'] = $entity['meta'];
		}

		// Raw context — pass through as-is.
		if ( isset( $entity['raw_context'] ) && is_array( $entity['raw_context'] ) ) {
			$normalized['raw_context'] = $entity['raw_context'];
		}

		return $normalized;
	}

	/**
	 * Normalize the search_capabilities block.
	 *
	 * @since 1.0.0
	 * @param mixed $caps Raw capabilities value.
	 * @return array
	 */
	private static function normalize_search_capabilities( $caps ) {
		if ( ! is_array( $caps ) || empty( $caps ) ) {
			return array(
				'primary_index'       => 'wp_posts_clean',
				'supported_filters'   => array(),
				'fallback_strategies' => array(),
			);
		}

		return array(
			'primary_index'       => isset( $caps['primary_index'] ) ? sanitize_key( (string) $caps['primary_index'] ) : 'wp_posts_clean',
			'supported_filters'   => isset( $caps['supported_filters'] ) && is_array( $caps['supported_filters'] )
				? array_values( array_map( 'sanitize_key', $caps['supported_filters'] ) )
				: array(),
			'fallback_strategies' => isset( $caps['fallback_strategies'] ) && is_array( $caps['fallback_strategies'] )
				? array_values( array_map( 'sanitize_key', $caps['fallback_strategies'] ) )
				: array(),
		);
	}
}
