<?php
/**
 * Shared recursive input sanitizer.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gregius_Data_Sanitizer
 *
 * Recursively sanitizes decoded JSON payloads. Array keys and values are both
 * sanitized with the most restrictive WordPress sanitizers available, so that
 * attacker-controlled object keys cannot survive a decode → sanitize cycle.
 *
 * @since 1.0.0
 */
class Gregius_Data_Sanitizer {

	/**
	 * Maximum recursion depth to prevent stack exhaustion on hostile payloads.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_DEPTH = 32;

	/**
	 * Maximum accepted byte size for JSON payloads (messages / manifest).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_BYTES_MESSAGES = 1048576; // 1 MB.

	/**
	 * Maximum accepted byte size for the manifest JSON payload.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_BYTES_MANIFEST = 1048576; // 1 MB.

	/**
	 * Maximum accepted byte size for metadata filter / manifest payloads.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_BYTES_METADATA = 262144; // 256 KB.

	/**
	 * Maximum accepted byte size for the source payload.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_BYTES_SOURCE = 16384; // 16 KB.

	/**
	 * Recursively sanitize a value (arrays, strings, integers, booleans).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Decoded value.
	 * @param int   $depth Current recursion depth.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize( $value, $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return array();
		}

		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$safe_key = is_int( $key ) ? $key : sanitize_key( $key );
				if ( '' === $safe_key ) {
					continue;
				}
				$sanitized[ $safe_key ] = self::sanitize( $item, $depth + 1 );
			}

			return $sanitized;
		}

		if ( is_string( $value ) ) {
			return self::strip_invisible_unicode( sanitize_text_field( $value ) );
		}

		if ( is_int( $value ) ) {
			return absint( $value );
		}

		if ( is_bool( $value ) ) {
			return (bool) $value;
		}

		return $value;
	}

	/**
	 * Strip invisible Unicode characters used for instruction / data smuggling.
	 *
	 * Unconditionally removes ranges that have no legitimate use in this
	 * context (tag block, bidi embeddings/overrides, word joiner, BOM,
	 * supplementary variation selectors). Removes suspicious *runs* of
	 * characters that are legitimate singly (variation selectors and zero-width
	 * joiners) so normal emoji and RTL shaping are preserved.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $text Text to strip.
	 * @return mixed Text without invisible smuggling characters.
	 */
	public static function strip_invisible_unicode( $text ) {
		if ( ! is_string( $text ) ) {
			return $text;
		}

		// Unconditional: tag block, bidi embeddings/overrides, word joiner, BOM,
		// supplementary variation selectors.
		$text = preg_replace( '/[\x{E0000}-\x{E007F}\x{202A}-\x{202E}\x{2060}\x{FEFF}\x{E0100}-\x{E01EF}]/u', '', $text );

		// Run heuristic: variation selectors (2+ consecutive) and zero-width (4+).
		$text = preg_replace( '/[\x{FE00}-\x{FE0F}]{2,}/u', '', $text );
		$text = preg_replace( '/[\x{200B}-\x{200D}]{4,}/u', '', $text );

		return $text;
	}

	/**
	 * Decode a JSON string into a sanitized array, with size and depth guards.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw       Raw JSON string (or any type; non-strings fail safe).
	 * @param int   $max_bytes Maximum accepted byte length.
	 * @param array $fallback  Value returned when input is invalid.
	 * @return array Decoded array (not yet value-sanitized).
	 */
	public static function decode_json( $raw, $max_bytes, $fallback = array() ) {
		if ( ! is_string( $raw ) ) {
			return $fallback;
		}

		if ( strlen( $raw ) > $max_bytes ) {
			return $fallback;
		}

		$decoded = json_decode( $raw, true, self::MAX_DEPTH );
		if ( ! is_array( $decoded ) ) {
			return $fallback;
		}

		return $decoded;
	}

	/**
	 * Sanitize a conversation messages array to a strict role/content schema.
	 *
	 * Only the `user` and `assistant` roles are accepted; the `system` role is
	 * reserved for the server-built system prompt and never accepted from a
	 * client (a client-controlled system message is a prompt-injection vector).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $messages Messages payload.
	 * @return array Re-indexed list of { role, content } entries.
	 */
	public static function sanitize_messages( $messages ) {
		if ( ! is_array( $messages ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $msg ) {
						if ( ! is_array( $msg ) ) {
							return null;
						}

						$role = isset( $msg['role'] ) ? sanitize_text_field( $msg['role'] ) : '';
						if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
							return null;
						}

						return array(
							'role'    => $role,
							'content' => isset( $msg['content'] )
								? self::strip_invisible_unicode( wp_kses_post( $msg['content'] ) )
								: '',
						);
					},
					$messages
				)
			)
		);
	}

	/**
	 * Sanitize a metadata_filter payload and allowlist its operator.
	 *
	 * `filter_operator` is restricted to the values the Postgres search
	 * function understands (`AND` / `OR`). Any other value is normalized to
	 * `AND`, which is the effective default the SQL function already applies
	 * when the key is absent (NULL).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $filter Metadata filter payload.
	 * @return array Sanitized filter.
	 */
	public static function sanitize_metadata_filter( $filter ) {
		if ( ! is_array( $filter ) ) {
			return array();
		}

		$filter = self::sanitize( $filter );

		if ( isset( $filter['filter_operator'] ) ) {
			$operator = strtoupper( (string) $filter['filter_operator'] );
			if ( ! in_array( $operator, array( 'AND', 'OR' ), true ) ) {
				$operator = 'AND';
			}
			$filter['filter_operator'] = $operator;
		}

		return $filter;
	}
}
