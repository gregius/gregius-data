<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Database Provider Factory
 *
 * Factory class for creating database provider instances based on database type.
 * Implements the Factory design pattern to abstract provider instantiation.
 *
 * @package Gregius_Data
 * @subpackage Gregius_PostgreSQL/includes/providers
 * @since 1.0.0
 */

/**
 * Database Provider Factory Class
 *
 * Creates and returns appropriate database provider instances based on the
 * requested database type. Handles provider validation and error cases.
 *
 * @since 1.0.0
 */
class GG_Data_Provider_Factory {

	/**
	 * Supported database providers
	 *
	 * Maps database type identifiers to their provider class names.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $supported_providers = array(
		'postgresql' => 'GG_Data_PostgreSQL_Provider',
		'postgrest'  => 'GG_Data_PostgREST_Provider',
		// Legacy aliases for backward compatibility.
		'pdo'        => 'GG_Data_PostgreSQL_Provider',
		'supabase'   => 'GG_Data_PostgREST_Provider',
		// 'neon'     => 'GG_Data_PostgREST_Provider', // Future: Neon also uses PostgREST.
		// 'mysql'    => 'GG_MySQL_Provider', // Future: MySQL 9.0+ support.
	);

	/**
	 * Create database provider instance
	 *
	 * Factory method that instantiates and returns the appropriate provider
	 * based on the database type.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $database_type     Database type identifier (e.g., 'postgresql', 'mysql').
	 * @param array       $connection_config Optional. Connection configuration to pass to provider.
	 * @param string|null $connection_name   Optional. Connection name for logging and identification.
	 *
	 * @return GG_Data_DB_Provider Database provider instance.
	 * @throws Exception If database type is not supported or provider class doesn't exist.
	 */
	public static function create_provider( $database_type, $connection_config = array(), $connection_name = null ) {
		// Validate database type parameter.
		if ( empty( $database_type ) || ! is_string( $database_type ) ) {
			throw new Exception( 'Database type must be a non-empty string.' );
		}

		// Normalize database type to lowercase.
		$database_type = strtolower( trim( $database_type ) );

		// Check if database type is supported.
		if ( ! isset( self::$supported_providers[ $database_type ] ) ) {
			$supported_types = implode( ', ', array_keys( self::$supported_providers ) );
			throw new Exception(
				sprintf(
					'Unsupported database type "%s". Supported types: %s',
					esc_html( $database_type ),
					esc_html( $supported_types )
				)
			);
		}

		// Get provider class name.
		$provider_class = self::$supported_providers[ $database_type ];

		// Verify provider class exists.
		if ( ! class_exists( $provider_class ) ) {
			throw new Exception(
				sprintf(
					'Provider class "%s" for database type "%s" does not exist. The provider file may not be loaded.',
					esc_html( $provider_class ),
					esc_html( $database_type )
				)
			);
		}

		// Verify provider implements interface.
		$reflection = new ReflectionClass( $provider_class );
		if ( ! $reflection->implementsInterface( 'GG_Data_DB_Provider' ) ) {
			throw new Exception(
				sprintf(
					'Provider class "%s" must implement GG_Data_DB_Provider interface.',
					esc_html( $provider_class )
				)
			);
		}

		// Instantiate and return provider.
		try {
			$provider = new $provider_class( $connection_config, $connection_name );
			return $provider;
		} catch ( Exception $e ) {
			throw new Exception(
				sprintf(
					'Failed to instantiate provider "%s": %s',
					esc_html( $provider_class ),
					esc_html( $e->getMessage() )
				)
			);
		}
	}

}
