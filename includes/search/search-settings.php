<?php
/**
 * Search Default Settings
 *
 * Defines default settings for PostgreSQL search functionality.
 * Search settings are GLOBAL (stored under '__global__' connection)
 * rather than per-connection because search is a site-wide feature.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global scope key for search settings
 *
 * Search is a site-wide feature, so settings are stored under the plugin name
 * rather than a specific database connection.
 */
define( 'GG_DATA_SEARCH_SETTINGS_CONNECTION', '__global__' );

/**
 * Get default search settings
 *
 * @return array Default settings.
 */
function gg_data_get_default_search_settings() {
	// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Keep defaults readable with long semantic threshold key.
	return array(
		'enabled'                         => false, // Disabled by default - user must opt-in.
		'connection'                      => '',    // Database connection to use for search queries.
		'embedding_model'                 => 'tfidf-300', // Default to free TF-IDF embeddings.
		'retrieval_mode'                  => 'hybrid_default', // PostgreSQL + MySQL merge by default.
		'observability_enabled'           => false, // Full observability probes are opt-in.
		'description'                     => 'Enable PostgreSQL full-text search with field weighting and stemming',
	);
	// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
}

/**
 * Initialize search settings on plugin activation
 */
function gg_data_init_search_settings() {
	$settings = new GG_Data_Settings_Manager();

	// Migrate legacy search scope rows before reading defaults.
	gg_data_migrate_search_settings_scope();

	// Check if search settings already exist (using global connection name).
	$enabled = $settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'enabled', null );

	// Only set defaults if not already configured.
	if ( null === $enabled ) {
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'enabled', false );
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'connection', '' );
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'embedding_model', 'tfidf-300' );
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'retrieval_mode', 'hybrid_default' );
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'observability_enabled', false );
	}

	// Backfill retrieval mode for existing installs that predate this setting.
	$retrieval_mode = $settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'retrieval_mode', null );
	if ( null === $retrieval_mode ) {
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'retrieval_mode', 'hybrid_default' );
	}

	// Backfill similarity threshold to 0.5 for improved default latency.
	$stored_threshold = $settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', null );
	if ( null === $stored_threshold || (float) $stored_threshold <= 0.3 ) {
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', 0.5 );
	}

	// Backfill expensive telemetry toggle for existing installs.
	$observability_toggle = $settings->get_with_category( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'observability_enabled', null );
	if ( null === $observability_toggle ) {
		$settings->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'observability_enabled', false );
	}

	// Backfill persisted per-connection capability keys used when observability is OFF.
	$connections = $settings->get_all_connections();
	foreach ( array_keys( $connections ) as $connection_name ) {
		$trigram_supported = $settings->get_with_category( 'search', $connection_name, 'trigram_supported', null );
		if ( null === $trigram_supported ) {
			$settings->set_with_category_public( 'search', $connection_name, 'trigram_supported', false );
		}

		$vector_supported = $settings->get_with_category( 'search', $connection_name, 'vector_supported', null );
		if ( null === $vector_supported ) {
			$settings->set_with_category_public( 'search', $connection_name, 'vector_supported', false );
		}
	}
}

/**
 * Migrate legacy search settings scope rows to the canonical global scope.
 *
 * Moves category=search rows from non-global scopes (legacy 'gregius-data'
 * and accidental per-connection rows) into GG_DATA_SEARCH_SETTINGS_CONNECTION.
 * Existing global keys take precedence; missing keys are filled from legacy rows.
 *
 * @return void
 */
function gg_data_migrate_search_settings_scope() {
	if ( get_option( 'gg_data_search_scope_migrated' ) ) {
		return;
	}

	$settings_manager = new GG_Data_Settings_Manager();
	$rows             = $settings_manager->get_by_category( 'search' );

	if ( empty( $rows ) ) {
		update_option( 'gg_data_search_scope_migrated', true );
		return;
	}

	$global_scope = GG_DATA_SEARCH_SETTINGS_CONNECTION;
	$global_keys  = array();

	foreach ( $rows as $row ) {
		if ( isset( $row['connection_name'], $row['setting_key'] ) && $global_scope === $row['connection_name'] ) {
			$global_keys[ (string) $row['setting_key'] ] = true;
		}
	}

	foreach ( $rows as $row ) {
		$source_scope = isset( $row['connection_name'] ) ? (string) $row['connection_name'] : '';
		$key          = isset( $row['setting_key'] ) ? (string) $row['setting_key'] : '';

		if ( '' === $source_scope || '' === $key || $global_scope === $source_scope ) {
			continue;
		}

		$data_type = isset( $row['data_type'] ) ? (string) $row['data_type'] : 'string';
		$value     = isset( $row['setting_value'] ) ? maybe_unserialize( $row['setting_value'] ) : null;

		if ( 'json' === $data_type && is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		if ( empty( $global_keys[ $key ] ) ) {
			$settings_manager->set_with_category( 'search', $global_scope, $key, $value, $data_type );
			$global_keys[ $key ] = true;
		}

		$settings_manager->delete_with_category( 'search', $source_scope, $key );
	}

	update_option( 'gg_data_search_scope_migrated', true );
}
