<?php
/**
 * Settings Manager for Gregius Data
 *
 * This file contains the GG_Data_Settings_Manager class which handles
 * all settings operations using the dedicated gg_settings table with
 * multisite support and proper site isolation.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings Manager for Gregius Data
 *
 * Handles all settings operations using the dedicated gg_settings table.
 * Provides multisite support with proper site isolation.
 *
 * @package Gregius_Data
 */
class GG_Data_Settings_Manager {

	/**
	 * The settings table name (with prefix)
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * WordPress database object
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Current site ID (for multisite support)
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'gg_settings';
		$this->site_id    = get_current_blog_id();
		$this->logger     = new GG_Data_Logger();
	}

	/**
	 * Get a setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value if setting doesn't exist.
	 * @return mixed Setting value.
	 */
	public function get( $key, $default_value = null ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$value = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT setting_value FROM {$this->table_name} WHERE setting_key = %s",
				$key
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $value ) {
			return $default_value;
		}

		// Attempt to unserialize if it's a serialized value.
		$unserialized = maybe_unserialize( $value );
		return $unserialized;
	}

	/**
	 * Set a setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value ) {
		$serialized_value = maybe_serialize( $value );

		$result = $this->wpdb->replace(
			$this->table_name,
			array(
				'setting_key'   => $key,
				'setting_value' => $serialized_value,
				'updated_at'    => current_time( 'mysql' ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Delete a setting
	 *
	 * @param string $key Setting key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array(
				'setting_key' => $key,
			),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get all settings for current site
	 *
	 * @return array Associative array of all settings.
	 */
	public function get_all() {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$results = $this->wpdb->get_results(
			"SELECT setting_key, setting_value FROM {$this->table_name}",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$settings = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
			}
		}

		return $settings;
	}

	/**
	 * Get settings by prefix
	 *
	 * @param string $prefix Setting key prefix.
	 * @return array Associative array of matching settings.
	 */
	public function get_by_prefix( $prefix ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT setting_key, setting_value FROM {$this->table_name} WHERE setting_key LIKE %s",
				$prefix . '%'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$settings = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
			}
		}

		return $settings;
	}

	/**
	 * Delete settings by prefix
	 *
	 * @param string $prefix Setting key prefix.
	 * @return bool True on success, false on failure.
	 */
	public function delete_by_prefix( $prefix ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE setting_key LIKE %s",
				$prefix . '%'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Check if a setting exists
	 *
	 * @param string $key Setting key.
	 * @return bool True if setting exists, false otherwise.
	 */
	public function exists( $key ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE setting_key = %s",
				$key
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return $count > 0;
	}

	/**
	 * Get a setting value with category and connection scope
	 *
	 * This method retrieves settings that are scoped to both a category and connection.
	 * Used for multi-connection features where each connection needs independent settings.
	 *
	 * @param string $category The category of the setting (e.g., 'sync', 'connections').
	 * @param string $connection_name The connection name (e.g., 'clri_local', 'default').
	 * @param string $key The setting key.
	 * @param mixed  $default_value Default value if setting doesn't exist.
	 * @return mixed The setting value or default.
	 */
	public function get_with_category( $category, $connection_name, $key, $default_value = null ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$value = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT setting_value FROM {$this->table_name} 
                 WHERE category = %s AND connection_name = %s AND setting_key = %s AND is_active = 1",
				$category,
				$connection_name,
				$key
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $value ) {
			return $default_value;
		}

		// Single-pass deserialization only.
		return maybe_unserialize( $value );
	}

	/**
	 * Set a setting value with category and connection scope (Public wrapper)
	 *
	 * This method stores settings scoped to both a category and connection.
	 * Used for multi-connection features and model registry.
	 *
	 * @param string $category The category of the setting (e.g., 'llm_model', 'embeddings_model').
	 * @param string $connection_name The connection name (e.g., 'gregius-data').
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 * @param string $data_type Optional. Data type ('string', 'integer', 'json', etc.).
	 * @return bool True on success, false on failure.
	 */
	public function set_with_category( $category, $connection_name, $key, $value, $data_type = 'string' ) {
		// Serialize value if needed.
		$serialized_value = 'json' === $data_type && is_array( $value ) ? wp_json_encode( $value ) : maybe_serialize( $value );

		// Use the private set_with_category method (no encryption).
		return $this->set_with_category_internal( $category, $connection_name, $key, $serialized_value, $data_type, 0 );
	}

	/**
	 * Get all settings by category
	 *
	 * @param string $category Category to filter by.
	 * @param string $connection_name Optional. Filter by connection name.
	 * @return array Settings in the category.
	 */
	public function get_by_category( $category, $connection_name = null ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		if ( $connection_name ) {
			return $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT connection_name, setting_key, setting_value, data_type, is_encrypted 
					 FROM {$this->table_name} 
					 WHERE category = %s AND connection_name = %s AND is_active = 1 
					 ORDER BY setting_key",
					$category,
					$connection_name
				),
				ARRAY_A
			);
		} else {
			return $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT connection_name, setting_key, setting_value, data_type, is_encrypted 
					 FROM {$this->table_name} 
					 WHERE category = %s AND is_active = 1 
					 ORDER BY connection_name, setting_key",
					$category
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete a setting with category and connection scope
	 *
	 * @param string $category The category of the setting.
	 * @param string $connection_name The connection name.
	 * @param string $key The setting key.
	 * @return bool True on success, false on failure.
	 */
	public function delete_with_category( $category, $connection_name, $key ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->delete(
			$this->table_name,
			array(
				'category'        => $category,
				'connection_name' => $connection_name,
				'setting_key'     => $key,
			),
			array( '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Set a setting value with category and connection scope
	 *
	 * This method stores settings scoped to both a category and connection.
	 * Used for multi-connection features where each connection needs independent settings.
	 *
	 * @param string $category The category of the setting (e.g., 'sync', 'connections').
	 * @param string $connection_name The connection name (e.g., 'clri_local', 'default').
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 * @return bool True on success, false on failure.
	 */
	public function set_with_category_public( $category, $connection_name, $key, $value ) {
		// Determine data type.
		$data_type = $this->get_data_type( $value );

		// Don't pre-serialize here - let set_with_category handle it to avoid double serialization
		// Use the private set_with_category method (no encryption for sync settings).
		return $this->set_with_category( $category, $connection_name, $key, $value, $data_type );
	}

	/**
	 * Get database connection settings for a specific database
	 *
	 * @param string $db_key Database identifier key.
	 * @return array|null Database connection settings or null if not found.
	 */
	public function get_database_settings( $db_key ) {
		return $this->get( "database_{$db_key}", null );
	}

	/**
	 * Set database connection settings
	 *
	 * @param string $db_key Database identifier key.
	 * @param array  $settings Database connection settings.
	 * @return bool True on success, false on failure.
	 */
	public function set_database_settings( $db_key, $settings ) {
		return $this->set( "database_{$db_key}", $settings );
	}

	/**
	 * Get all database configurations
	 *
	 * @return array All database configurations.
	 */
	public function get_all_databases() {
		return $this->get_by_prefix( 'database_' );
	}

	/**
	 * Delete database configuration
	 *
	 * @param string $db_key Database identifier key.
	 * @return bool True on success, false on failure.
	 */
	public function delete_database_settings( $db_key ) {
		return $this->delete( "database_{$db_key}" );
	}

	/**
	 * Get the table name (useful for direct queries)
	 *
	 * @return string Table name with prefix.
	 */
	public function get_table_name() {
		return $this->table_name;
	}

	/**
	 * Get current site ID
	 *
	 * @return int Current site ID.
	 */
	public function get_site_id() {
		return $this->site_id;
	}

	/**
	 * Set site ID (useful for cross-site operations in multisite)
	 *
	 * @param int $site_id Site ID.
	 */
	public function set_site_id( $site_id ) {
		$this->site_id = $site_id;
	}

	/**
	 * Clean up old settings (for maintenance)
	 *
	 * @param int $days_old Delete settings older than this many days.
	 * @return int Number of settings deleted.
	 */
	public function cleanup_old_settings( $days_old = 30 ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days_old
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return $result;
	}

	/**
	 * Create the settings table if it doesn't exist
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_settings_table() {
		$charset_collate = $this->wpdb->get_charset_collate();

		// Note: Using MySQL syntax since this table goes in WordPress MySQL DB.
		$sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            connection_name varchar(100) NOT NULL DEFAULT 'default',
            category varchar(50) NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            data_type varchar(20) DEFAULT 'string',
            is_encrypted tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            environment varchar(20) DEFAULT 'production',
            provider varchar(50) DEFAULT 'custom',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (connection_name, category, setting_key),
            KEY idx_connection_category (connection_name, category),
            KEY idx_setting_key (setting_key),
            KEY idx_is_active (is_active)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$result = dbDelta( $sql );

		// Log the table creation attempt.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->log( 'GG_Data_Settings_Manager: Table creation result: ' . wp_json_encode( $result ), 'debug', 'system' );
		}

		// Check if table was created successfully.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$table_exists = $this->table_name === $this->wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" );

		if ( $table_exists ) {
			$this->logger->log( "GG_Data_Settings_Manager: Settings table '{$this->table_name}' created successfully", 'info', 'system' );
			return true;
		} else {
			$this->logger->log( "GG_Data_Settings_Manager: Failed to create settings table '{$this->table_name}'", 'error', 'system' );
			return false;
		}
	}

	/**
	 * Create settings tables for all sites in multisite network
	 * Also works for single site installations
	 *
	 * @return array Results of table creation for each site.
	 */
	public function create_settings_tables_for_all_sites() {
		$results = array();

		if ( is_multisite() ) {
			// Get all sites in the network.
			$sites = get_sites( array( 'number' => 0 ) );

			foreach ( $sites as $site ) {
				// Switch to each site and create its settings table.
				switch_to_blog( $site->blog_id );

				// Create new settings manager instance for this site.
				$site_settings_manager = new GG_Data_Settings_Manager();
				$result                = $site_settings_manager->create_settings_table();

				$results[ "site_{$site->blog_id}" ] = array(
					'blog_id'    => $site->blog_id,
					'table_name' => $site_settings_manager->get_table_name(),
					'success'    => $result,
					'url'        => get_site_url(),
				);

				restore_current_blog();
			}
		} else {
			// Single site installation.
			$result                 = $this->create_settings_table();
			$results['single_site'] = array(
				'blog_id'    => 1,
				'table_name' => $this->get_table_name(),
				'success'    => $result,
				'url'        => get_site_url(),
			);
		}

		return $results;
	}

	/**
	 * Save a database connection configuration (PostgreSQL, Supabase, etc.)
	 *
	 * @param string $connection_name User-defined connection name.
	 * @param array  $connection_config Connection configuration array.
	 * @return bool True on success, false on failure.
	 */
	public function save_connection( $connection_name, $connection_config ) {
		// Validate connection name.
		if ( empty( $connection_name ) || ! is_string( $connection_name ) ) {
			$this->logger->log( 'GG_Data_Settings_Manager: Invalid connection name', 'error', 'connection' );
			return false;
		}

		// Debug logging with masked context.
		$this->logger->log(
			"GG_Data_Settings_Manager: Saving connection '{$connection_name}'",
			'debug',
			'connection',
			$connection_name,
			array(
				'config_summary' => $this->mask_connection_config_for_log( $connection_config ),
			)
		);

		// Determine provider type (default to postgresql for backwards compatibility).
		$provider_type = isset( $connection_config['type'] ) ? $connection_config['type'] : 'postgresql';

		// Set provider-specific default values.
		if ( 'postgrest' === $provider_type ) {
			$defaults = array(
				'type'            => 'postgrest',
				'project_url'     => '',
				'publishable_key' => '',
				'secret_key'      => '',
				'connect_timeout' => 30,
				'description'     => '',
				'is_active'       => true,
			);
		} else {
			// PostgreSQL defaults.
			$defaults = array(
				'type'            => 'postgresql',
				'host'            => 'localhost',
				'port'            => 5432,
				'database'        => '',
				'username'        => '',
				'password'        => '',
				'ssl_mode'        => 'prefer',
				'connect_timeout' => 30,
				'description'     => '',
				'is_active'       => true,
			);
		}

		// Merge only relevant fields (don't apply defaults for other provider types).
		$connection_config = array_merge( $defaults, $connection_config );
		$this->logger->log(
			'GG_Data_Settings_Manager: Final connection config prepared',
			'debug',
			'connection',
			$connection_name,
			array(
				'config_summary' => $this->mask_connection_config_for_log( $connection_config ),
			)
		);

		// Save each setting with the connections category.
		$success = true;
		foreach ( $connection_config as $key => $value ) {
			$this->logger->log(
				"GG_Data_Settings_Manager: Saving setting '{$key}'",
				'debug',
				'connection',
				$connection_name,
				array(
					'key' => $key,
				)
			);
			if ( ! $this->set_connection_setting( $connection_name, $key, $value ) ) {
				$success = false;
				$this->logger->log( "GG_Data_Settings_Manager: Failed to save connection setting: {$key}", 'error', 'connection', $connection_name );
			} else {
				$this->logger->log( "GG_Data_Settings_Manager: Successfully saved setting: {$key}", 'debug', 'connection', $connection_name );
			}
		}

		$this->logger->log( 'GG_Data_Settings_Manager: save_connection result: ' . ( $success ? 'SUCCESS' : 'FAILED' ), 'debug', 'connection', $connection_name );
		return $success;
	}

	/**
	 * Mask connection configuration for safe log context output.
	 *
	 * @param array $config Connection config array.
	 * @return array Masked config array.
	 */
	private function mask_connection_config_for_log( $config ) {
		if ( ! is_array( $config ) ) {
			return array();
		}

		$masked         = $config;
		$sensitive_keys = array( 'password', 'publishable_key', 'secret_key', 'access_token', 'api_key', 'token' );

		foreach ( $sensitive_keys as $key ) {
			if ( isset( $masked[ $key ] ) && '' !== (string) $masked[ $key ] ) {
				$masked[ $key ] = '***';
			}
		}

		return $masked;
	}

	/**
	 * Get a PostgreSQL connection configuration
	 *
	 * @param string $connection_name Connection name.
	 * @return array|null Connection configuration or null if not found.
	 */
	public function get_connection( $connection_name ) {
		if ( empty( $connection_name ) ) {
			return null;
		}

		$settings = $this->get_connection_settings( $connection_name );
		if ( empty( $settings ) ) {
			return null;
		}

		return $this->normalize_postgrest_keys( $settings );
	}

	/**
	 * Get all PostgreSQL connections
	 *
	 * @return array Array of all connections indexed by connection name.
	 */
	public function get_all_connections() {
		// Get all settings with 'connections' category.
		$connection_settings = $this->get_by_category_internal( 'connections' );
		$connections         = array();

		foreach ( $connection_settings as $setting ) {
			$connection_name = $setting['connection_name'];
			if ( ! isset( $connections[ $connection_name ] ) ) {
				$connections[ $connection_name ] = array(
					'name' => $connection_name, // Include connection name in the data.
				);
			}
			$connections[ $connection_name ][ $setting['setting_key'] ] = $setting['setting_value'];
		}

		foreach ( $connections as $connection_name => $connection_config ) {
			$connections[ $connection_name ] = $this->normalize_postgrest_keys( $connection_config );
		}

		return $connections;
	}

	/**
	 * Delete a PostgreSQL connection
	 *
	 * @param string $connection_name Connection name to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete_connection( $connection_name ) {
		if ( empty( $connection_name ) ) {
			return false;
		}

		// Delete ALL settings for this connection (all categories: connections, sync, etc.).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE connection_name = %s",
				$connection_name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			$this->logger->log( "GG_Data_Settings_Manager: Failed to delete connection: {$connection_name}", 'error', 'connection', $connection_name );
			return false;
		}

		// Log how many rows were deleted.
		$deleted_count = $this->wpdb->rows_affected;
		$this->logger->log( "GG_Data_Settings_Manager: Deleted {$deleted_count} settings for connection: {$connection_name}", 'info', 'connection', $connection_name );

		// Clean up sync metadata (wp_gg_sync_metadata).
		if ( class_exists( 'GG_Data_Sync_Metadata_Manager' ) ) {
			$metadata_manager = new GG_Data_Sync_Metadata_Manager( $connection_name );
			$metadata_manager->delete_connection_metadata( $connection_name );
			$this->logger->log( "GG_Data_Settings_Manager: Deleted sync metadata for connection: {$connection_name}", 'info', 'connection', $connection_name );
		}

		// Clean up all connection-specific wp_options entries.
		$cleanup_options = array(
			'gg_data_validation_results_' . $connection_name,        // Sync validation cache.
			'gg_data_connection_health_status_' . $connection_name,  // Connection health monitoring.
			'gg_data_last_validation_email_' . $connection_name,     // Validation alert email tracking.
			'gg_data_last_health_email_' . $connection_name,         // Health alert email tracking.
			'gg_data_schema_version_' . $connection_name,            // Schema version tracking.
		);

		foreach ( $cleanup_options as $option_key ) {
			$deleted = delete_option( $option_key );
			if ( $deleted ) {
				$this->logger->log( "GG_Data_Settings_Manager: Deleted cache: {$option_key}", 'debug', 'connection', $connection_name );
			}
		}

		return true;
	}

	/**
	 * Test if a connection name exists
	 *
	 * @param string $connection_name Connection name to check.
	 * @return bool True if exists, false if not.
	 */
	public function connection_exists( $connection_name ) {
		if ( empty( $connection_name ) ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE connection_name = %s AND category = 'connections'",
				$connection_name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return intval( $count ) > 0;
	}

	/**
	 * Update connection active status
	 *
	 * @param string $connection_name Connection name.
	 * @param bool   $is_active Active status.
	 * @return bool True on success, false on failure.
	 */
	public function set_connection_active( $connection_name, $is_active ) {
		if ( empty( $connection_name ) ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_name} SET is_active = %d WHERE connection_name = %s AND category = 'connections'",
				$is_active ? 1 : 0,
				$connection_name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Get only active connections
	 *
	 * @return array Array of active connections.
	 */
	public function get_active_connections() {
		$all_connections    = $this->get_all_connections();
		$active_connections = array();

		foreach ( $all_connections as $name => $config ) {
			if ( isset( $config['is_active'] ) && $config['is_active'] ) {
				$active_connections[ $name ] = $config;
			}
		}

		return $active_connections;
	}

	/**
	 * Get settings by category (Internal - no connection filter)
	 *
	 * @param string $category Category to filter by.
	 * @return array Settings in the category.
	 */
	private function get_by_category_internal( $category ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name constructed from $wpdb->prefix in constructor, safe from SQL injection.
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT connection_name, setting_key, setting_value, data_type, is_encrypted 
                 FROM {$this->table_name} 
                 WHERE category = %s AND is_active = 1 
                 ORDER BY connection_name, setting_key",
				$category
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get data type of a value
	 *
	 * @param mixed $value Value to check.
	 * @return string Data type.
	 */
	private function get_data_type( $value ) {
		if ( is_bool( $value ) ) {
			return 'boolean';
		} elseif ( is_int( $value ) ) {
			return 'integer';
		} elseif ( is_float( $value ) ) {
			return 'float';
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			return 'serialized';
		} else {
			return 'string';
		}
	}

	/**
	 * Set a setting value with category and connection scope (Internal)
	 *
	 * Note: The is_encrypted column is reserved for future use but not currently implemented.
	 * Following WordPress plugin standards, sensitive data (API keys) are stored in plain text
	 * like WooCommerce, Stripe, and other major plugins. Security relies on database access
	 * controls rather than application-level encryption.
	 *
	 * @param string $category Category.
	 * @param string $connection_name Connection name.
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @param string $data_type Data type.
	 * @param int    $is_encrypted Reserved for future use. Always 0.
	 * @return bool True on success.
	 */
	private function set_with_category_internal( $category, $connection_name, $key, $value, $data_type, $is_encrypted ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->replace(
			$this->table_name,
			array(
				'connection_name' => $connection_name,
				'category'        => $category,
				'setting_key'     => $key,
				'setting_value'   => $value,
				'data_type'       => $data_type,
				'is_encrypted'    => $is_encrypted,
				'is_active'       => 1,
				'updated_at'      => current_time( 'mysql' ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Set a connection setting
	 *
	 * @param string $connection_name Connection name.
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success.
	 */
	private function set_connection_setting( $connection_name, $key, $value ) {
		$data_type        = $this->get_data_type( $value );
		$serialized_value = maybe_serialize( $value );

		return $this->set_with_category_internal( 'connections', $connection_name, $key, $serialized_value, $data_type, 0 );
	}

	/**
	 * Get all settings for a connection
	 *
	 * @param string $connection_name Connection name.
	 * @return array Connection settings.
	 */
	private function get_connection_settings( $connection_name ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT setting_key, setting_value, data_type 
				 FROM {$this->table_name} 
				 WHERE connection_name = %s AND category = 'connections' AND is_active = 1",
				$connection_name
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$settings = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$value = $row['setting_value'];
				if ( 'string' !== $row['data_type'] || is_serialized( $value ) ) {
					$value = maybe_unserialize( $value );
				}
				$settings[ $row['setting_key'] ] = $value;
			}
		}

		return $settings;
	}

	/**
	 * Normalize PostgREST/Supabase settings for runtime usage.
	 *
	 * Runtime is canonical-only: publishable_key + secret_key.
	 *
	 * @param array $settings Connection settings.
	 * @return array Normalized settings.
	 */
	private function normalize_postgrest_keys( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		if ( ! isset( $settings['type'] ) || 'postgrest' !== $settings['type'] ) {
			return $settings;
		}

		return $settings;
	}
}
