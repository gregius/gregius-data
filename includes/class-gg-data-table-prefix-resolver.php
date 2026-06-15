<?php
/**
 * Canonical table prefix resolver for external PostgreSQL mirror tables.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves canonical PostgreSQL mirror table prefixes and mirror table names.
 */
class GG_Data_Table_Prefix_Resolver {
	/**
	 * Canonical mirror table prefix shared with PostgREST schema.
	 */
	const MIRROR_PREFIX = 'wp_';

	/**
	 * Resolve runtime mirror table prefix.
	 *
	 * External PostgreSQL mirror tables always use the canonical prefix
	 * defined by the schema contract.
	 *
	 * @return string
	 */
	public static function runtime_prefix() {
		return self::MIRROR_PREFIX;
	}

	/**
	 * Build canonical mirror table name.
	 *
	 * @param string $base_name Table suffix without prefix.
	 * @return string
	 */
	public static function mirror_table( $base_name ) {
		return self::MIRROR_PREFIX . ltrim( (string) $base_name, '_' );
	}

	/**
	 * Build canonical mirror table name with schema qualifier.
	 *
	 * @param string $base_name Table suffix without prefix.
	 * @param string $schema    PostgreSQL schema name.
	 * @return string
	 */
	public static function mirror_table_with_schema( $base_name, $schema = 'public' ) {
		return $schema . '.' . self::mirror_table( $base_name );
	}
}
