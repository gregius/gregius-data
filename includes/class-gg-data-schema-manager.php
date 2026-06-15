<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Schema manager for Gregius Data
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'GG_Data_Schema_Manager' ) ) {

	// Require search language helper for schema creation.
	require_once plugin_dir_path( __FILE__ ) . 'search/class-gg-data-search-language.php';

	/**
	 * Schema Manager class
	 * Handles creation and management of the PostgreSQL schema
	 */
	class GG_Data_Schema_Manager {

		/**
		 * Database connection handler (legacy PDO)
		 *
		 * @var GG_Data_DB
		 */
		protected $db;

		/**
		 * Connection manager (provider-based)
		 *
		 * @var GG_Data_Connection_Manager
		 */
		protected $connection_manager;

		/**
		 * Logger instance
		 *
		 * @var GG_Data_Logger
		 */
		protected $logger;

		/**
		 * Settings manager instance
		 *
		 * @var GG_Data_Settings_Manager
		 */
		protected $settings_manager;

		/**
		 * Class constructor
		 */
		public function __construct() {
			$this->db                 = new GG_Data_DB();
			$this->connection_manager = new GG_Data_Connection_Manager();
			$this->logger             = new GG_Data_Logger();
			$this->settings_manager   = new GG_Data_Settings_Manager();
		}   /**
			 * Get the WordPress table prefix
			 * This handles both single-site and multisite installations
			 *
			 * @return string The current WordPress table prefix
			 */
		protected function get_table_prefix() {
			return GG_Data_Table_Prefix_Resolver::runtime_prefix();
		}

		/**
		 * Get schema version for a connection
		 *
		 * @param string $connection_name Connection identifier.
		 * @return string Schema version (e.g., '1.0.0') or '0.0.0' if not found.
		 */
		public function get_schema_version( $connection_name ) {
			// Try wp_gg_settings first (new storage).
			$version = $this->settings_manager->get_with_category( 'schema', $connection_name, 'version', null );

			if ( null !== $version ) {
				return $version;
			}

			// Fallback to wp_options for backward compatibility (migration path).
			$legacy_version = get_option( 'gg_data_schema_version_' . $connection_name, null );

			if ( null !== $legacy_version ) {
				// Migrate from wp_options to wp_gg_settings.
				$this->set_schema_version( $connection_name, $legacy_version );
				delete_option( 'gg_data_schema_version_' . $connection_name );
				return $legacy_version;
			}

			return '0.0.0';
		}

		/**
		 * Set schema version for a connection
		 *
		 * @param string $connection_name Connection identifier.
		 * @param string $version Schema version (e.g., '1.0.0').
		 * @return bool True on success, false on failure.
		 */
		public function set_schema_version( $connection_name, $version ) {
			// Store in wp_gg_settings.
			$success = $this->settings_manager->set_with_category_public( 'schema', $connection_name, 'version', $version );

			if ( $success ) {
				// Also store timestamps.
				$current_time = current_time( 'mysql' );
				$this->settings_manager->set_with_category_public( 'schema', $connection_name, 'updated_at', $current_time );

				$this->logger->log( "Schema version set to {$version} for connection '{$connection_name}'", 'info', 'system', $connection_name );
			}

			return $success;
		}

		/**
		 * Initialize the schema manager
		 */
		public function init() {
			// Legacy AJAX handlers - DEPRECATED: React dashboard uses REST API
			// Commented out 2025-10-24 after confirming React uses /wp-json/gg-data/v1/schema endpoints
			// add_action( 'wp_ajax_gg_pg_initialize_schema', array( $this, 'ajax_initialize_schema' ) );
			// add_action( 'wp_ajax_gg_pg_check_schema', array( $this, 'ajax_check_schema' ) );
			// add_action( 'wp_ajax_gg_pg_get_schema_status', array( $this, 'ajax_get_schema_status' ) );.
		}

		/**
		 * Create all database tables for WordPress schema
		 *
		 * @param string $connection_name Connection name to use (default: 'default').
		 * @return array Result array with success status and message.
		 */
		public function create_all_tables( $connection_name = 'default' ) {
			// Get connection config to determine provider type.
			$config = $this->settings_manager->get_connection( $connection_name );
			if ( ! $config ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: connection name */
						__( 'Connection "%s" not found.', 'gregius-data' ),
						$connection_name
					),
				);
			}

			$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

			// Route to appropriate schema creation method based on provider.
			if ( 'postgrest' === $provider_type ) {
				return $this->create_supabase_schema( $connection_name );
			}

			// PostgreSQL direct connection (legacy PDO method).
			// Force close any existing connection to get a fresh one.
			$this->db->close_connection();

			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return array(
					'success' => false,
					'message' => sprintf(
					/* translators: %s: connection name */
						__( 'Could not connect to PostgreSQL database using connection "%s".', 'gregius-data' ),
						$connection_name
					),
				);
			}           try {
				// 1. Create plugin settings table FIRST (in WordPress MySQL, not PostgreSQL).
				// This must be created before PostgreSQL schema because create_search_function needs it.
				$this->create_settings_table( $conn );
				$this->logger->log( 'Created wp_gg_settings table in MySQL', 'info', 'system', $connection_name );

				// 2. Create sync metadata tracking tables in both MySQL and PostgreSQL
				// MySQL: Tracks what SHOULD be synced, PostgreSQL: Tracks what HAS BEEN synced
				$this->create_sync_metadata_table( $conn );
				$this->logger->log( 'Created wp_gg_sync_metadata tables in both databases', 'info', 'system', $connection_name );

				// Start transaction for atomic schema creation.
				$conn->beginTransaction();              // 2. Create WordPress schema.
				$this->create_schema( $conn );
				$this->logger->log( 'Created WordPress schema', 'info', 'system', $connection_name );

				// 3. Install PostgreSQL extensions.
				$this->install_pgvector_extension( $conn );
				$this->logger->log( 'Installed pgvector extension', 'info', 'system', $connection_name );

				$this->install_pg_trgm_extension( $conn );
				$this->logger->log( 'Installed pg_trgm extension for typo tolerance', 'info', 'system', $connection_name );

				// 4. Create core WordPress tables in dependency order.
				$this->create_posts_table( $conn );
				$this->logger->log( 'Created wp_posts table (original Gutenberg content - true WordPress mirror)', 'info', 'system', $connection_name );

				$this->create_posts_clean_table( $conn, $connection_name );
				$this->logger->log( 'Created wp_posts_clean table (stripped content for search/vectors/AI)', 'info', 'system', $connection_name );

				// Create search function.
				// Note: This requires wp_gg_settings table to exist (created above).
				$this->create_search_function( $conn, $connection_name );
				$this->logger->log( 'Created PostgreSQL full-text search function with language detection', 'info', 'system', $connection_name );

				$this->create_postmeta_table( $conn );
				$this->create_terms_table( $conn );
				$this->create_term_taxonomy_table( $conn );
				$this->create_term_relationships_table( $conn );
				$this->create_comments_table( $conn );
				$this->create_commentmeta_table( $conn );
				$this->create_users_table( $conn );
				$this->create_usermeta_table( $conn );
				$this->create_options_table( $conn );
				$this->logger->log( 'Created core WordPress tables', 'info', 'system', $connection_name );

				// 5. Chunks table (shared, model-agnostic).
				$this->create_chunks_table( $conn );
				$this->logger->log( 'Created wp_posts_chunks table for content chunking', 'info', 'system', $connection_name );

				// 6. TF-IDF vector table (row-per-embedding architecture).
				$this->create_tfidf_vectors_table( $conn );
				$this->logger->log( 'Created wp_posts_tfidf_300 table with row-per-embedding schema (free tier)', 'info', 'system', $connection_name );

				// 7. Vocabulary cache table.
				$this->create_vocabulary_cache_table( $conn );
				$this->logger->log( 'Created wp_posts_vocabulary_cache table for TF-IDF vocabulary caching', 'info', 'system', $connection_name );

				// 8. OpenAI embedding vector tables (row-per-embedding architecture).
				$this->create_openai_embedding_tables( $conn );
				$this->logger->log( 'Created OpenAI embedding tables with row-per-embedding schema', 'info', 'system', $connection_name );

				// 9. HashingTF Murmur3 1024D embedding table (stateless internal model).
				$this->create_hashingtf_embedding_table( $conn );
				$this->logger->log( 'Created wp_posts_hashingtf_murmur3_1024 table (stateless internal, no vocabulary required)', 'info', 'system', $connection_name );

				// Commit transaction.
				$conn->commit();
				$this->logger->log( 'PostgreSQL schema creation transaction committed successfully', 'info', 'system', $connection_name );

				// Update schema version (per-connection) in wp_gg_settings.
				$this->set_schema_version( $connection_name, GG_DATA_VERSION );
					return array(
						'success' => true,
						'message' => sprintf(
							/* translators: %s: connection name */
							__( 'Successfully created PostgreSQL schema for connection "%s".', 'gregius-data' ),
							$connection_name
						),
						'version' => GG_DATA_VERSION,
					);
			} catch ( Exception $e ) {
				// Rollback on error.
				if ( $conn->inTransaction() ) {
					$conn->rollBack();
				}

				$error_message = sprintf(
					/* translators: %s: error message */
					__( 'Failed to create schema: %s', 'gregius-data' ),
					$e->getMessage()
				);
				$this->logger->log( $error_message, 'error', 'system', $connection_name );

				return array(
					'success' => false,
					'message' => $error_message,
					'error'   => $e->getMessage(),
				);
			}
		}

		/**
		 * Create schema for Supabase connections
		 *
		 * For Supabase connections, schema creation must be done manually via Supabase SQL Editor
		 * because the REST API doesn't support executing arbitrary SQL commands for security reasons.
		 *
		 * This method provides instructions and SQL for users to copy/paste.
		 *
		 * @param string $connection_name Connection name.
		 * @return array Result array with success status and message.
		 */
		protected function create_supabase_schema( $connection_name ) {
			// Get connection config.
			$config = $this->settings_manager->get_connection( $connection_name );
			if ( ! $config ) {
				$this->logger->log( "Connection '{$connection_name}' not found in settings", 'error', 'system', $connection_name );
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: connection name */
						__( 'Connection "%s" not found', 'gregius-data' ),
						$connection_name
					),
				);
			}

			$this->logger->log( "Starting Supabase schema creation for connection: {$connection_name}", 'info', 'system', $connection_name );

			// Supabase schema must be applied manually via SQL Editor.
			// The schema file is located at: includes/sql/postgrest-schema.sql
			// Users should:
			// 1. Go to Supabase Dashboard > SQL Editor
			// 2. Copy the contents of postgrest-schema.sql
			// 3. Run it to create all required tables, functions, and indexes.

			$this->logger->log( 'Supabase schema must be applied manually via SQL Editor', 'info', 'system', $connection_name );

			// Create WordPress-side tables (settings, sync metadata).
			try {
				// Create plugin settings table in WordPress MySQL.
				$this->create_settings_table( null );
				$this->logger->log( 'Created wp_gg_settings table in MySQL', 'info', 'system', $connection_name );

				// Create sync metadata tracking tables in WordPress MySQL.
				$this->create_sync_metadata_table( null );
				$this->logger->log( 'Created wp_gg_sync_metadata table in MySQL', 'info', 'system', $connection_name );
			} catch ( Exception $e ) {
				$this->logger->log( 'Failed to create WordPress-side tables: ' . $e->getMessage(), 'warning', 'system', $connection_name );
				return array(
					'success' => false,
					'message' => 'Error creating WordPress-side tables: ' . $e->getMessage(),
				);
			}

			return array(
				'success' => true,
				'message' => __( 'WordPress tables created successfully. Next: Copy the SQL from includes/sql/postgrest-schema.sql to your PostgREST SQL Editor and run it to complete setup.', 'gregius-data' ),
			);
		}   /**
			 * Get schema status for Supabase connections
			 *
			 * Queries the gg_schema_meta table directly via REST API (same approach as PDO PostgreSQL).
			 *
			 * @param string $connection_name Connection name.
			 * @return array Schema status array.
			 */
		protected function get_supabase_schema_status( $connection_name ) {
			// Get Supabase provider.
			$provider = $this->connection_manager->get_provider( $connection_name );

			if ( ! $provider ) {
				return array(
					'connected'       => false,
					'connection_name' => $connection_name,
					'message'         => 'Could not get Supabase provider',
				);
			}

			try {
				if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
					require_once __DIR__ . '/providers/class-gg-postgrest-provider.php';
				}

				$config         = $this->settings_manager->get_connection( $connection_name );
				$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );

				if ( is_wp_error( $runtime_config ) ) {
					return array(
						'connected'       => false,
						'connection_name' => $connection_name,
						'message'         => $runtime_config->get_error_message(),
					);
				}

				// Query gg_schema_meta table directly to get version (like PDO PostgreSQL does).
				$response = wp_safe_remote_get(
					rtrim( $runtime_config['project_url'], '/' ) . '/rest/v1/gg_schema_meta?key=eq.version&select=value',
					array(
						'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
						'timeout' => 10,
					)
				);

				if ( is_wp_error( $response ) ) {
					return array(
						'connected'       => false,
						'connection_name' => $connection_name,
						'message'         => 'Supabase connection error: ' . $response->get_error_message(),
					);
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				$body        = wp_remote_retrieve_body( $response );

				if ( 200 !== $status_code ) {
					return array(
						'connected'       => false,
						'connection_name' => $connection_name,
						'message'         => "Schema check failed (HTTP {$status_code}). Did you run the SQL in Supabase? Error: {$body}",
					);
				}

				$result = json_decode( $body, true );

				// Check if gg_schema_meta table exists and has version.
				if ( ! is_array( $result ) || empty( $result ) ) {
					return array(
						'connected'         => true,
						'connection_name'   => $connection_name,
						'complete'          => false,
						'schema_exists'     => false,
						'tables'            => array(
							'complete' => false,
							'missing'  => array( 'gg_schema_meta' ),
						),
						'vector_extension'  => false,
						'pg_trgm_extension' => false,
						'schema_version'    => '0.0.0',
						'version'           => '0.0.0',
						'plugin_version'    => GG_DATA_VERSION,
						'requires_update'   => false,
						'update_available'  => false,
						'post_count'        => 0,
						'vector_count'      => 0,
					);
				}

				// Get schema version from result.
				$schema_version = isset( $result[0]['value'] ) ? $result[0]['value'] : '0.0.0';
				$schema_exists  = '0.0.0' !== $schema_version && 'unknown' !== $schema_version;

				// If schema exists, assume extensions are installed (they're in the SQL file).
				// Extensions can't be queried via REST API without exposing system tables.
				$has_vector  = $schema_exists;
				$has_pg_trgm = $schema_exists;

				return array(
					'connected'         => true,
					'connection_name'   => $connection_name,
					'complete'          => $schema_exists,
					'schema_exists'     => $schema_exists,
					'tables'            => array(
						'complete' => $schema_exists,
						'missing'  => array(),
					),
					'vector_extension'  => $has_vector,
					'pg_trgm_extension' => $has_pg_trgm,
					'schema_version'    => $schema_version,
					'version'           => $schema_version,
					'plugin_version'    => GG_DATA_VERSION,
					'requires_update'   => $schema_exists && version_compare( $schema_version, GG_DATA_VERSION, '<' ),
					'update_available'  => $schema_exists && version_compare( $schema_version, GG_DATA_VERSION, '<' ),
					'post_count'        => 0,  // Can be queried separately if needed.
					'vector_count'      => 0,  // Can be queried separately if needed.
				);

			} catch ( Exception $e ) {
				return array(
					'connected'       => false,
					'connection_name' => $connection_name,
					'error'           => true,
					'message'         => 'Exception checking Supabase schema: ' . $e->getMessage(),
				);
			}
		}

		/**
		 * Upgrade existing schema to latest version
		 *
		 * @param string $connection_name Connection name to use (default: 'default').
		 * @return array Result array with success status and message.
		 */
		public function upgrade_schema_to_latest( $connection_name = 'default' ) {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: connection name */
						__( 'Could not connect to PostgreSQL database using connection "%s".', 'gregius-data' ),
						$connection_name
					),
				);
			}

			try {
				$current_version = $this->get_schema_version( $connection_name );
				$target_version  = GG_DATA_VERSION;             // Check if upgrade is needed.
				if ( version_compare( $current_version, $target_version, '>=' ) ) {
					return array(
						'success'         => true,
						'message'         => __( 'Schema is already up to date.', 'gregius-data' ),
						'current_version' => $current_version,
						'target_version'  => $target_version,
						'upgraded'        => false,
					);
				}

				// Start transaction for atomic upgrade.
				$conn->beginTransaction();

				// Run migration steps.
				$this->migrate_postmeta_table( $conn );
				$this->migrate_posts_table_for_sync( $conn ); // Add sync tracking columns.
				$this->migrate_taxonomy_tables( $conn );
				$this->logger->log( 'Completed schema migrations', 'info', 'system', $connection_name ); // Ensure pgvector extension is installed (for upgrades from older versions).
				$this->install_pgvector_extension( $conn );

				// Commit transaction.
				$conn->commit();

				// Update schema version (per-connection) in wp_gg_settings.
				$this->set_schema_version( $connection_name, $target_version );
				return array(
					'success'          => true,
					'message'          => sprintf(
						/* translators: 1: previous version, 2: new version, 3: connection name */
						__( 'Successfully upgraded schema from %1$s to %2$s for connection "%3$s".', 'gregius-data' ),
						$current_version,
						$target_version,
						$connection_name
					),
					'previous_version' => $current_version,
					'current_version'  => $target_version,
					'upgraded'         => true,
				);
			} catch ( Exception $e ) {
				// Rollback on error.
				if ( $conn->inTransaction() ) {
					$conn->rollBack();
				}

				$error_message = sprintf(
					/* translators: %s: error message */
					__( 'Failed to upgrade schema: %s', 'gregius-data' ),
					$e->getMessage()
				);
				$this->logger->log( $error_message, 'error', 'system', $connection_name );

				return array(
					'success' => false,
					'message' => $error_message,
					'error'   => $e->getMessage(),
				);
			}
		}

		/**
		 * Check the status of the PostgreSQL schema
		 *
		 * @param string $connection_name Connection name to check (default: 'default').
		 * @return array Schema status information
		 */
		public function get_schema_status( $connection_name = 'default' ) {
			// Check connection config to determine provider type.
			$config = $this->settings_manager->get_connection( $connection_name );
			if ( ! $config ) {
				return array(
					'connected'       => false,
					'connection_name' => $connection_name,
					'message'         => sprintf(
						/* translators: %s: connection name */
						__( 'Connection "%s" not found.', 'gregius-data' ),
						$connection_name
					),
				);
			}

			$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

			// Route to appropriate status check based on provider.
			if ( 'postgrest' === $provider_type ) {
				return $this->get_supabase_schema_status( $connection_name );
			}

			// PostgreSQL direct connection (legacy PDO method).
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return array(
					'connected'       => false,
					'connection_name' => $connection_name,
					'message'         => sprintf(
						/* translators: %s: connection name */
						__( 'Could not connect to PostgreSQL database using connection "%s".', 'gregius-data' ),
						$connection_name
					),
				);
			}           try {
				// Get the WordPress table prefix.
				$prefix = $this->get_table_prefix();

				// Check if required tables exist.
				$tables = array(
					$prefix . 'posts',        // Original Gutenberg content (WordPress mirror).
					$prefix . 'posts_clean',  // Cleaned content for search/vectors/AI.
					$prefix . 'postmeta',
					$prefix . 'terms',
					$prefix . 'term_taxonomy',
					$prefix . 'term_relationships',
					$prefix . 'comments',
					$prefix . 'commentmeta',
					$prefix . 'users',
					$prefix . 'usermeta',
					$prefix . 'options',
				// Note: post_vectors_tfidf_300 deferred until Advanced Features enabled.
				);

				$missing_tables = array();
				foreach ( $tables as $table ) {
					// Check tables in public schema (PostgreSQL standard).
					$stmt   = $conn->query( "SELECT to_regclass('public.$table') AS exists" );
					$exists = $stmt->fetchColumn();
					if ( ! $exists ) {
						$missing_tables[] = $table;
					}
				}

				// Check for pgvector extension.
				$stmt             = $conn->query( "SELECT * FROM pg_extension WHERE extname = 'vector'" );
				$vector_installed = (bool) $stmt->fetchColumn();

				// Check for pg_trgm extension (required for typo tolerance).
				$stmt              = $conn->query( "SELECT * FROM pg_extension WHERE extname = 'pg_trgm'" );
				$pg_trgm_installed = (bool) $stmt->fetchColumn();

				// Get current schema version from wp_gg_settings.
				// If tables don't exist, version is 0.0.0.
				$schema_version = '0.0.0';
				if ( empty( $missing_tables ) ) {
					$schema_version = $this->get_schema_version( $connection_name );
					if ( empty( $schema_version ) || '0.0.0' === $schema_version ) {
						// Fallback to plugin version for newly created schemas.
						$schema_version = GG_DATA_VERSION;
					}
				}

				// Schema exists if all required tables exist.
				$schema_exists = empty( $missing_tables );

				// Schema is complete if all tables exist.
				$complete = $schema_exists;

				// Check if update is available (plugin version > schema version).
				$update_available = $schema_exists && version_compare( $schema_version, GG_DATA_VERSION, '<' );

				return array(
					'connected'         => true,
					'connection_name'   => $connection_name,
					'complete'          => $complete,
					'schema_exists'     => $schema_exists,
					'tables'            => array(
						'complete' => empty( $missing_tables ),
						'missing'  => $missing_tables,
					),
					'vector_extension'  => $vector_installed,  // Keep this name for JS compatibility.
					'pg_trgm_extension' => $pg_trgm_installed,  // For typo tolerance.
					'schema_version'    => $schema_version,  // Current schema version.
					'version'           => $schema_version,  // Keep for backward compatibility.
					'plugin_version'    => GG_DATA_VERSION,  // Target version.
					'requires_update'   => version_compare( $schema_version, GG_DATA_VERSION, '<' ),
					'update_available'  => $update_available,  // UI flag for upgrade banner.
				);
			} catch ( Exception $e ) {
				return array(
					'connected'       => true,
					'connection_name' => $connection_name,
					'error'           => true,
					'message'         => $e->getMessage(),
				);
			}
		}

		/**
		 * Create WordPress schema
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_schema( $conn ) {
			unset( $conn );
			// No custom schema needed - using PostgreSQL default 'public' schema.
			// Tables are created directly in public schema without prefix.
			return true;
		}

		/**
		 * Install pgvector extension
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 * @throws Exception If pgvector extension cannot be installed.
		 */
		protected function install_pgvector_extension( $conn ) {
			try {
				// Check if pgvector extension is already installed.
				$stmt             = $conn->query( "SELECT * FROM pg_extension WHERE extname = 'vector'" );
				$vector_installed = $stmt->fetchColumn();

				if ( $vector_installed ) {
					$this->logger->log( 'pgvector extension already installed', 'info', 'system' );
					return true;
				}

				// Try to install pgvector extension.
				$conn->exec( 'CREATE EXTENSION IF NOT EXISTS vector' );
				$this->logger->log( 'pgvector extension installed successfully', 'info', 'system' );

				// Verify installation.
				$stmt             = $conn->query( "SELECT * FROM pg_extension WHERE extname = 'vector'" );
				$vector_installed = $stmt->fetchColumn();

				if ( ! $vector_installed ) {
					throw new Exception( 'pgvector extension installation failed - extension not found after installation attempt' );
				}

				return true;
			} catch ( Exception $e ) {
				$error_message = 'Failed to install pgvector extension: ' . esc_html( $e->getMessage() );
				$this->logger->log( $error_message, 'error', 'system' );

				// Provide helpful error message.
				if ( strpos( $e->getMessage(), 'permission denied' ) !== false ) {
					throw new Exception( 'pgvector extension installation failed: Insufficient permissions. Please contact your database administrator to install the pgvector extension.' );
				} elseif ( strpos( $e->getMessage(), 'could not open extension control file' ) !== false ) {
					throw new Exception( 'pgvector extension installation failed: Extension not available on this PostgreSQL server. Please contact your database administrator to install pgvector.' );
				} else {
					throw new Exception( 'pgvector extension installation failed: ' . esc_html( $e->getMessage() ) . '. Please contact your database administrator.' );
				}
			}
		}

		/**
		 * Install pg_trgm extension for typo tolerance (trigram similarity)
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 * @throws Exception If pg_trgm extension cannot be installed.
		 */
		protected function install_pg_trgm_extension( $conn ) {
			try {
				// Check if pg_trgm extension is already installed.
				$stmt           = $conn->query( "SELECT * FROM pg_extension WHERE extname = 'pg_trgm'" );
				$trgm_installed = $stmt->fetchColumn();

				if ( $trgm_installed ) {
					$this->logger->log( 'pg_trgm extension already installed', 'info', 'system' );
					return true;
				}

				// Try to install pg_trgm extension.
				$conn->exec( 'CREATE EXTENSION IF NOT EXISTS pg_trgm' );
				$this->logger->log( 'pg_trgm extension installed successfully', 'info', 'system' );

				// Verify installation.
				$stmt           = $conn->query( "SELECT * FROM pg_extension WHERE extname = 'pg_trgm'" );
				$trgm_installed = $stmt->fetchColumn();

				if ( ! $trgm_installed ) {
					throw new Exception( 'pg_trgm extension installation failed - extension not found after installation attempt' );
				}

				return true;
			} catch ( Exception $e ) {
				$error_message = 'Failed to install pg_trgm extension: ' . esc_html( $e->getMessage() );
				$this->logger->log( $error_message, 'error', 'system' );

				// Provide helpful error message.
				if ( strpos( $e->getMessage(), 'permission denied' ) !== false ) {
					throw new Exception( 'pg_trgm extension installation failed: Insufficient permissions. Please contact your database administrator to install the pg_trgm extension.' );
				} elseif ( strpos( $e->getMessage(), 'could not open extension control file' ) !== false ) {
					throw new Exception( 'pg_trgm extension installation failed: Extension not available on this PostgreSQL server. Please contact your database administrator to install pg_trgm.' );
				} else {
					throw new Exception( 'pg_trgm extension installation failed: ' . esc_html( $e->getMessage() ) . '. Please contact your database administrator.' );
				}
			}
		}

		/**
		 * Create posts table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_posts_table( $conn ) {
			$prefix     = $this->get_table_prefix();
			$table_name = $prefix . 'posts';

			// Exact WordPress mirror table with original Gutenberg content.
			// This is a TRUE mirror - post_content contains ORIGINAL content from MySQL.
			// For cleaned/stripped content, see wp_posts_clean table.
			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			ID BIGINT PRIMARY KEY,
			site_id BIGINT DEFAULT 1,
			post_author BIGINT,
			post_date TIMESTAMP WITH TIME ZONE,
			post_date_gmt TIMESTAMP WITH TIME ZONE,
			post_content TEXT,             -- ORIGINAL Gutenberg content (NOT cleaned)
			post_title TEXT,               -- ORIGINAL title (NOT cleaned)
			post_excerpt TEXT,             -- ORIGINAL excerpt (NOT cleaned)
			post_status VARCHAR(20),
			comment_status VARCHAR(20),
			ping_status VARCHAR(20),
			post_password VARCHAR(255),
			post_name VARCHAR(200),
			to_ping TEXT,
			pinged TEXT,
			post_modified TIMESTAMP WITH TIME ZONE,
			post_modified_gmt TIMESTAMP WITH TIME ZONE,
			post_content_filtered TEXT,
			post_parent BIGINT,
			guid VARCHAR(255),
			menu_order INTEGER,
			post_type VARCHAR(20),
			post_mime_type VARCHAR(100),
			comment_count BIGINT DEFAULT 0,
			
			-- Sync tracking columns
			synced_at TIMESTAMP WITH TIME ZONE,
			modified_at TIMESTAMP WITH TIME ZONE
		)";

			$conn->exec( $sql );

			// Create indexes (traditional WordPress indexes only).
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_post_name_idx ON $table_name (post_name)",
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_post_type_status_date_idx ON $table_name (post_type, post_status, post_date)",
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_post_parent_idx ON $table_name (post_parent)",
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_post_author_idx ON $table_name (post_author)",
				// Composite index for main query performance (Status + Type + Date + Included fields).
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_status_type_date_include_idx ON $table_name (post_status, post_type, post_date DESC) INCLUDE (post_title, post_excerpt)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create posts_clean table for search and vector features
		 *
		 * This table stores cleaned/stripped content for:
		 * - Full-text search quality (no Gutenberg noise)
		 * - TF-IDF vector generation (accurate term frequency)
		 * - AI features (keywords, sentiment, reading time)
		 * - External APIs (OpenAI, etc - saves tokens)
		 *
		 * @param PDO    $conn            Database connection.
		 * @param string $connection_name Connection name for settings storage.
		 * @return bool Success or failure.
		 */
		protected function create_posts_clean_table( $conn, $connection_name = 'default' ) {
			$prefix      = $this->get_table_prefix();
			$table_name  = $prefix . 'posts_clean';
			$posts_table = $prefix . 'posts';

			// Detect WordPress language for search index optimization.
			$detected_language = GG_Data_Search_Language::get_site_search_language();
			$this->logger->log(
				sprintf(
					'Creating search indexes with detected language: %s (WordPress locale: %s)',
					$detected_language,
					get_locale()
				),
				'info',
				'system',
				$connection_name
			);

			// Cleaned content table for search/vectors/AI features.
			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			post_id BIGINT PRIMARY KEY REFERENCES $posts_table(ID) ON DELETE CASCADE,
			post_title_clean TEXT,
			post_content_clean TEXT,
			post_excerpt_clean TEXT,
				metadata_manifest JSONB DEFAULT '{}'::jsonb,
			cleaned_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
			content_hash VARCHAR(64),        -- MD5 hash for change detection
			cleaning_version VARCHAR(10) DEFAULT '1.0',  -- Track cleaning algorithm version
			word_count INTEGER DEFAULT 0,    -- Accurate word count from clean content
			reading_time_minutes INTEGER DEFAULT 0,  -- Calculated reading time
			search_vector_weighted tsvector  -- Precomputed weighted tsvector for fast FTS (139x faster!)
		)";

			$conn->exec( $sql );

			// Add search_vector_weighted column if it doesn't exist (for existing installations).
			try {
				$conn->exec(
					"
					DO \$\$
					BEGIN
						IF NOT EXISTS (
							SELECT 1 
							FROM information_schema.columns 
							WHERE table_name = '$table_name' 
							AND column_name = 'search_vector_weighted'
						) THEN
							ALTER TABLE $table_name ADD COLUMN search_vector_weighted tsvector;
						END IF;
					END \$\$;
				"
				);
			} catch ( PDOException $e ) {
				$this->logger->log(
					'Warning: Could not add search_vector_weighted column (may already exist): ' . $e->getMessage(),
					'warning',
					'system',
					$connection_name
				);
			}

			// Add metadata_manifest column if it doesn't exist (for existing installations).
			try {
				$conn->exec(
					"
						DO \$\$
						BEGIN
							IF NOT EXISTS (
								SELECT 1
								FROM information_schema.columns
								WHERE table_name = '$table_name'
								AND column_name = 'metadata_manifest'
							) THEN
								ALTER TABLE $table_name ADD COLUMN metadata_manifest JSONB DEFAULT '{}'::jsonb;
							END IF;
						END \$\$;
					"
				);
			} catch ( PDOException $e ) {
				$this->logger->log(
					'Warning: Could not add metadata_manifest column (may already exist): ' . $e->getMessage(),
					'warning',
					'system',
					$connection_name
				);
			}

			// Apply filter hooks for customizable search field weights.
			// Title weight: 'A' (1.0), 'B' (0.4), 'C' (0.2), 'D' (0.1).
			// Content weight: 'A' (1.0), 'B' (0.4), 'C' (0.2), 'D' (0.1).
			$title_weight   = apply_filters( 'gg_data_search_title_weight', 'A', $connection_name );
			$content_weight = apply_filters( 'gg_data_search_content_weight', 'B', $connection_name );

			// Validate weights (must be A, B, C, or D).
			$valid_weights = array( 'A', 'B', 'C', 'D' );
			if ( ! in_array( $title_weight, $valid_weights, true ) ) {
				$title_weight = 'A';
			}
			if ( ! in_array( $content_weight, $valid_weights, true ) ) {
				$content_weight = 'B';
			}

			// Create trigger to auto-update search_vector_weighted on INSERT/UPDATE.
			$conn->exec(
				"
				CREATE OR REPLACE FUNCTION {$prefix}posts_clean_search_vector_update() 
				RETURNS trigger AS \$trigger\$
				BEGIN
					NEW.search_vector_weighted := 
						setweight(to_tsvector('$detected_language', COALESCE(NEW.post_title_clean, '')), '$title_weight') ||
						setweight(to_tsvector('$detected_language', COALESCE(NEW.post_content_clean, '')), '$content_weight');
					RETURN NEW;
					END;
				\$trigger\$ LANGUAGE plpgsql
				SET search_path = public, pg_temp;

				DROP TRIGGER IF EXISTS {$prefix}tsvector_update ON $table_name;
				CREATE TRIGGER {$prefix}tsvector_update 
				BEFORE INSERT OR UPDATE ON $table_name
				FOR EACH ROW EXECUTE FUNCTION {$prefix}posts_clean_search_vector_update();
			"
			);            // Populate search_vector_weighted for existing rows (uses filtered weights).
			$conn->exec(
				"
				UPDATE $table_name 
				SET search_vector_weighted = 
					setweight(to_tsvector('$detected_language', COALESCE(post_title_clean, '')), '$title_weight') ||
					setweight(to_tsvector('$detected_language', COALESCE(post_content_clean, '')), '$content_weight')
				WHERE search_vector_weighted IS NULL;
			"
			);

			// Create full-text search indexes on cleaned content.
			$indexes = array(
				// FAST: GIN index on precomputed search_vector_weighted (used by search function).
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_clean_search_vector_idx 
				ON $table_name 
				USING GIN (search_vector_weighted)",

				// Legacy: GIN index for PostgreSQL full-text search with detected language.
				// Kept for compatibility with direct queries, but search function uses search_vector_weighted.
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_clean_content_fts_idx 
				ON $table_name 
				USING GIN (to_tsvector('$detected_language', COALESCE(post_content_clean, '')))",

				// Legacy: GIN index for title full-text search with detected language.
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_clean_title_fts_idx 
				ON $table_name 
				USING GIN (to_tsvector('$detected_language', COALESCE(post_title_clean, '')))",

				// B-tree index on content_hash for change detection.
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_clean_content_hash_idx 
				ON $table_name (content_hash)",

				// Composite index for faster joins (excludes content to save space).
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_clean_post_id_include_idx 
				ON $table_name (post_id) 
				INCLUDE (post_title_clean, post_excerpt_clean)",

				// GIN index for deterministic JSONB metadata containment filters.
				"CREATE INDEX IF NOT EXISTS {$prefix}posts_clean_metadata_manifest_gin_idx
				ON $table_name
				USING GIN (metadata_manifest jsonb_path_ops)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			// Store the detected language in settings for future reference.
			$settings_manager = new GG_Data_Settings_Manager();
			$settings_manager->set_with_category_public(
				'search',
				$connection_name,
				'index_language',
				$detected_language
			);
			$this->logger->log(
				sprintf(
					'Stored index language "%s" in settings for connection "%s"',
					$detected_language,
					$connection_name
				),
				'info',
				'system',
				$connection_name
			);

			return true;
		}

		/**
		 * Create postmeta table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_postmeta_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$table_name  = $prefix . 'postmeta';
			$posts_table = $prefix . 'posts';

			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			meta_id BIGINT PRIMARY KEY,
			post_id BIGINT REFERENCES $posts_table(ID) ON DELETE CASCADE,
			meta_key VARCHAR(255),
			meta_value JSONB
		)";
			$conn->exec( $sql ); // Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}postmeta_post_id_idx ON $table_name (post_id)",
				"CREATE INDEX IF NOT EXISTS {$prefix}postmeta_meta_key_idx ON $table_name (meta_key)",
				"CREATE INDEX IF NOT EXISTS {$prefix}postmeta_meta_value_idx ON $table_name USING gin (meta_value)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Migrate existing postmeta table to new schema
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function migrate_postmeta_table( $conn ) {
			$prefix     = $this->get_table_prefix();
			$table_name = $prefix . 'postmeta';

			try {
				// Check if migration is needed by checking if the unique constraint exists.
				$stmt = $conn->query(
					"
					SELECT constraint_name 
					FROM information_schema.table_constraints 
					WHERE table_name = '$table_name' 
					AND constraint_type = 'UNIQUE' 
                    AND table_schema = 'WordPress'
				"
				);
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
				$constraints = $stmt->fetchAll( PDO::FETCH_COLUMN );

				$has_unique_constraint = false;
				foreach ( $constraints as $constraint ) {
					if ( strpos( $constraint, 'post_id' ) !== false || strpos( $constraint, 'meta_key' ) !== false ) {
						$has_unique_constraint = true;
						break;
					}
				}

				// Check if column is already JSONB.
				$stmt      = $conn->query(
					"
					SELECT data_type 
					FROM information_schema.columns 
					WHERE table_name = '$table_name' 
					AND column_name = 'meta_value' 
                    AND table_schema = 'WordPress'
				"
				);
				$data_type = $stmt->fetchColumn();
				$is_jsonb  = ( 'jsonb' === $data_type );

				// Only migrate if needed.
				if ( ! $has_unique_constraint || ! $is_jsonb ) {
					$this->logger->log( 'Migrating postmeta table schema...', 'info', 'system' );

					// First, drop any existing constraint that might conflict.
					try {
						$conn->exec( "ALTER TABLE $table_name DROP CONSTRAINT IF EXISTS unique_post_id_meta_key" );
					} catch ( Exception $e ) {
						// Ignore if constraint doesn't exist.
						unset( $e );
					}

					// Convert TEXT to JSONB if needed.
					if ( ! $is_jsonb ) {
						$this->logger->log( 'Converting meta_value column to JSONB...', 'info', 'system' );
						// First, wrap existing text values in JSON format.
						$conn->exec(
							"
							UPDATE $table_name 
							SET meta_value = ('\"' || replace(meta_value, '\"', '\\\"') || '\"')::jsonb 
							WHERE meta_value::text !~ '^[\\[\\{]'
						"
						);

						// Now change the column type.
						$conn->exec( "ALTER TABLE $table_name ALTER COLUMN meta_value TYPE JSONB USING meta_value::jsonb" );
					}

					// Remove unique constraint if it exists (WordPress allows duplicate meta_keys per post).
					if ( $has_unique_constraint ) {
						$this->logger->log( 'Removing unique constraint from postmeta table (WordPress allows duplicate meta_keys)...', 'info', 'system' );
						try {
							foreach ( $constraints as $constraint ) {
								if ( strpos( $constraint, 'post_id' ) !== false || strpos( $constraint, 'meta_key' ) !== false ) {
									$conn->exec( "ALTER TABLE $table_name DROP CONSTRAINT IF EXISTS $constraint" );
								}
							}
						} catch ( Exception $e ) {
							// Ignore if constraint doesn't exist.
							unset( $e );
						}
					}

					$this->logger->log( 'Postmeta table migration completed', 'info', 'system' );
				}

				return true;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error migrating postmeta table: ' . $e->getMessage(), 'error', 'system' );
				return false;
			}
		}

		/**
		 * Migrate posts table to add sync tracking columns
		 *
		 * Adds synced_at and modified_at columns if they don't exist.
		 * Note: post_content contains cleaned content (Gutenberg stripped during sync)
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function migrate_posts_table_for_sync( $conn ) {
			$prefix     = $this->get_table_prefix();
			$table_name = $prefix . 'posts';

			try {
				// Check which columns exist.
				$stmt = $conn->query(
					"
				SELECT column_name 
				FROM information_schema.columns 
				WHERE table_name = '$table_name' 
				AND table_schema = 'public'
				AND column_name IN ('synced_at', 'modified_at')
			"
				);
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
				$existing_columns = $stmt->fetchAll( PDO::FETCH_COLUMN );

				$columns_to_add = array();

				if ( ! in_array( 'synced_at', $existing_columns, true ) ) {
					$columns_to_add[] = 'ADD COLUMN synced_at TIMESTAMP WITH TIME ZONE';
				}

				if ( ! in_array( 'modified_at', $existing_columns, true ) ) {
					$columns_to_add[] = 'ADD COLUMN modified_at TIMESTAMP WITH TIME ZONE';
				}

				// Add missing columns.
				if ( ! empty( $columns_to_add ) ) {
					$this->logger->log( 'Adding sync tracking columns to posts table...', 'info', 'system' );

					$alter_sql = 'ALTER TABLE ' . $table_name . ' ' . implode( ', ', $columns_to_add );
					$conn->exec( $alter_sql );

					$this->logger->log( 'Posts table migration completed - added ' . count( $columns_to_add ) . ' columns', 'info', 'system' );
				} else {
					$this->logger->log( 'Posts table already has all columns', 'debug', 'system' );
				}

				return true;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error migrating posts table: ' . $e->getMessage(), 'error', 'system' );
				return false;
			}
		}       /**
				 * Create terms table
				 *
				 * @param PDO $conn Database connection.
				 * @return bool Success or failure.
				 */
		protected function create_terms_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$table_name  = $prefix . 'terms';
					$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			term_id BIGINT PRIMARY KEY,
			site_id BIGINT DEFAULT 1,
			name VARCHAR(200),
			slug VARCHAR(200),
			term_group BIGINT DEFAULT 0
		)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}terms_slug_idx ON $table_name (slug)",
				"CREATE INDEX IF NOT EXISTS {$prefix}terms_name_idx ON $table_name (name)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create term taxonomy table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_term_taxonomy_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$table_name  = $prefix . 'term_taxonomy';
			$terms_table = $prefix . 'terms';
					$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			term_taxonomy_id BIGINT PRIMARY KEY,
			site_id BIGINT DEFAULT 1,
			term_id BIGINT REFERENCES $terms_table(term_id) ON DELETE CASCADE,
			taxonomy VARCHAR(32),
			description TEXT,
			parent BIGINT DEFAULT 0,
			count BIGINT DEFAULT 0
		)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}term_taxonomy_term_id_idx ON $table_name (term_id)",
				"CREATE INDEX IF NOT EXISTS {$prefix}term_taxonomy_taxonomy_idx ON $table_name (taxonomy)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create term relationships table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_term_relationships_table( $conn ) {
			$prefix              = $this->get_table_prefix();
			$table_name          = $prefix . 'term_relationships';
			$term_taxonomy_table = $prefix . 'term_taxonomy';
			$posts_table         = $prefix . 'posts';

			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			object_id BIGINT,
			term_taxonomy_id BIGINT,
			term_order INTEGER DEFAULT 0,
			PRIMARY KEY (object_id, term_taxonomy_id),
			FOREIGN KEY (object_id) REFERENCES $posts_table(ID) ON DELETE CASCADE,
			FOREIGN KEY (term_taxonomy_id) REFERENCES $term_taxonomy_table(term_taxonomy_id) ON DELETE CASCADE
		)";
			$conn->exec( $sql ); // Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}term_relationships_term_taxonomy_id_idx ON $table_name (term_taxonomy_id)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create comments table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_comments_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$table_name  = $prefix . 'comments';
			$posts_table = $prefix . 'posts';

			$sql = "
			CREATE TABLE IF NOT EXISTS $table_name (
				comment_ID BIGINT PRIMARY KEY,
				comment_post_ID BIGINT REFERENCES $posts_table(ID) ON DELETE CASCADE,
				comment_author TEXT,
				comment_author_email VARCHAR(100),
				comment_author_url VARCHAR(200),
				comment_author_IP VARCHAR(100),
				comment_date TIMESTAMP WITH TIME ZONE,
				comment_date_gmt TIMESTAMP WITH TIME ZONE,
				comment_content TEXT,
				comment_karma INTEGER DEFAULT 0,
				comment_approved VARCHAR(20),
				comment_agent VARCHAR(255),
				comment_type VARCHAR(20),
				comment_parent BIGINT DEFAULT 0,
				user_id BIGINT DEFAULT 0
			)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}comments_comment_post_id_idx ON $table_name (comment_post_ID)",
				"CREATE INDEX IF NOT EXISTS {$prefix}comments_comment_approved_date_gmt_idx ON $table_name (comment_approved, comment_date_gmt)",
				"CREATE INDEX IF NOT EXISTS {$prefix}comments_comment_parent_idx ON $table_name (comment_parent)",
				"CREATE INDEX IF NOT EXISTS {$prefix}comments_comment_author_email_idx ON $table_name (comment_author_email)",
			);

			foreach ( $indexes as $index_sql ) {
				// PostgreSQL doesn't support length-limited indexes like MySQL, so modify the query.
				$index_sql = str_replace( 'comment_author_email(100)', 'comment_author_email', $index_sql );
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create commentmeta table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_commentmeta_table( $conn ) {
			$prefix         = $this->get_table_prefix();
			$table_name     = $prefix . 'commentmeta';
			$comments_table = $prefix . 'comments';

			$sql = "
			CREATE TABLE IF NOT EXISTS $table_name (
				meta_id BIGINT PRIMARY KEY,
				comment_id BIGINT REFERENCES $comments_table(comment_ID) ON DELETE CASCADE,
				meta_key VARCHAR(255),
				meta_value TEXT
			)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}commentmeta_comment_id_idx ON $table_name (comment_id)",
				"CREATE INDEX IF NOT EXISTS {$prefix}commentmeta_meta_key_idx ON $table_name (meta_key)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create users table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_users_table( $conn ) {
			$prefix     = $this->get_table_prefix();
			$table_name = $prefix . 'users';

			$sql = "
			CREATE TABLE IF NOT EXISTS $table_name (
				ID BIGINT PRIMARY KEY,
				user_login VARCHAR(60),
				user_pass VARCHAR(255),
				user_nicename VARCHAR(50),
				user_email VARCHAR(100),
				user_url VARCHAR(100),
				user_registered TIMESTAMP WITH TIME ZONE,
				user_activation_key VARCHAR(255),
				user_status INTEGER DEFAULT 0,
				display_name VARCHAR(250)
			)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}users_user_login_key_idx ON $table_name (user_login)",
				"CREATE INDEX IF NOT EXISTS {$prefix}users_user_nicename_idx ON $table_name (user_nicename)",
				"CREATE INDEX IF NOT EXISTS {$prefix}users_user_email_idx ON $table_name (user_email)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create usermeta table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_usermeta_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$table_name  = $prefix . 'usermeta';
			$users_table = $prefix . 'users';

			$sql = "
			CREATE TABLE IF NOT EXISTS $table_name (
				umeta_id BIGINT PRIMARY KEY,
				user_id BIGINT REFERENCES $users_table(ID) ON DELETE CASCADE,
				meta_key VARCHAR(255),
				meta_value TEXT
			)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}usermeta_user_id_idx ON $table_name (user_id)",
				"CREATE INDEX IF NOT EXISTS {$prefix}usermeta_meta_key_idx ON $table_name (meta_key)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Create options table
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_options_table( $conn ) {
			$prefix     = $this->get_table_prefix();
			$table_name = $prefix . 'options';

			$sql = "
			CREATE TABLE IF NOT EXISTS $table_name (
				option_id BIGINT PRIMARY KEY,
				option_name VARCHAR(191) UNIQUE,
				option_value TEXT,
				autoload VARCHAR(20) DEFAULT 'yes'
			)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$prefix}options_option_name_idx ON $table_name (option_name)",
				"CREATE INDEX IF NOT EXISTS {$prefix}options_autoload_idx ON $table_name (autoload)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			return true;
		}

		/**
		 * Manage vector indexes with auto-upgrade logic
		 * Starts with IVFFlat for simplicity, upgrades to HNSW when dataset is large enough
		 *
		 * @param PDO    $conn Database connection.
		 * @param string $table_name Vector table name.
		 * @return bool Success or failure.
		 */
		public function manage_vector_indexes( $conn, $table_name ) {
			try {
				// Count existing vectors to determine appropriate index strategy.
				$count_stmt   = $conn->query( "SELECT COUNT(*) FROM WordPress.$table_name WHERE content_vector IS NOT NULL" );
				$vector_count = (int) $count_stmt->fetchColumn();

				// Get current index type if any exists.
				$current_index_type = $this->get_current_vector_index_type( $conn, $table_name );

				// Determine optimal index type based on dataset size.
				$optimal_index_type = $this->determine_optimal_index_type( $vector_count );

				// If no index exists or we need to upgrade, create/replace the index.
				if ( ! $current_index_type || $current_index_type !== $optimal_index_type ) {
					$this->create_or_upgrade_vector_index( $conn, $table_name, $current_index_type, $optimal_index_type, $vector_count );
				}

				return true;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error managing vector indexes: ' . $e->getMessage(), 'error', 'system' );
				return false;
			}
		}

		/**
		 * Get the current vector index type for a table
		 *
		 * @param PDO    $conn Database connection.
		 * @param string $table_name Vector table name.
		 * @return string|null Current index type or null if no vector index exists.
		 */
		protected function get_current_vector_index_type( $conn, $table_name ) {
			try {
				$stmt = $conn->query(
					"
				SELECT 
					i.indexname,
					am.amname as access_method
				FROM pg_indexes i
				JOIN pg_class c ON c.relname = i.indexname
				JOIN pg_am am ON am.oid = c.relam
                WHERE i.schemaname = 'WordPress' 
				AND i.tablename = '$table_name'
				AND i.indexname LIKE '%content_vector%'
			"
				);

				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
				$result = $stmt->fetch( PDO::FETCH_ASSOC );

				if ( $result ) {
					return $result['access_method'];
				}

				return null;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error checking current vector index type: ' . $e->getMessage(), 'error', 'system' );
				return null;
			}
		}

		/**
		 * Determine optimal index type based on dataset size and PostgreSQL capabilities
		 *
		 * @param int $vector_count Number of vectors in the dataset.
		 * @return string Optimal index type ('ivfflat' or 'hnsw').
		 */
		protected function determine_optimal_index_type( $vector_count ) {
			// For public plugin, start conservative with IVFFlat.
			// HNSW is better for large datasets but requires more memory and setup.

			if ( $vector_count < 1000 ) {
				// For small datasets, IVFFlat is sufficient and faster to build.
				return 'ivfflat';
			} elseif ( $vector_count < 10000 ) {
				// Medium datasets - still prefer IVFFlat for simplicity.
				return 'ivfflat';
			} else {
				// Large datasets - HNSW provides better query performance.
				// But only if PostgreSQL version supports it.
				try {
					$conn = $this->db->get_connection();
					if ( ! $conn ) {
						return 'ivfflat'; // Fallback if no connection.
					}

					$version_query   = $conn->query( 'SHOW server_version' );
					$pg_version_full = $version_query->fetchColumn();

					// Extract major version number.
					preg_match( '/^(\d+)\./', $pg_version_full, $matches );
					$pg_major_version = isset( $matches[1] ) ? (int) $matches[1] : 11;

					// HNSW requires PostgreSQL 14+ and pgvector 0.5.0+.
					if ( $pg_major_version >= 14 ) {
						return 'hnsw';
					}
				} catch ( Exception $e ) {
					$this->logger->log( 'Error checking PostgreSQL version for index selection: ' . $e->getMessage(), 'warning', 'system' );
				}

				// Fallback to IVFFlat if version check fails.
				return 'ivfflat';
			}
		}

		/**
		 * Create or upgrade vector index
		 *
		 * @param PDO         $conn Database connection.
		 * @param string      $table_name Vector table name.
		 * @param string|null $current_type Current index type.
		 * @param string      $target_type Target index type.
		 * @param int         $vector_count Number of vectors for optimization.
		 * @return bool Success or failure.
		 */
		protected function create_or_upgrade_vector_index( $conn, $table_name, $current_type, $target_type, $vector_count ) {
			$index_name = "{$table_name}_embedding_idx";

			try {
				// Drop existing vector index if it's different type.
				if ( $current_type && $current_type !== $target_type ) {
					$this->logger->log( "Upgrading vector index from $current_type to $target_type for better performance", 'info', 'system' );
					$conn->exec( "DROP INDEX IF EXISTS WordPress.$index_name" );
				}

				if ( 'hnsw' === $target_type ) {
					// HNSW index with optimized parameters.
					$m               = min( 64, max( 16, (int) ( $vector_count / 1000 ) ) ); // Adaptive M parameter.
					$ef_construction = min( 400, max( 200, (int) ( $vector_count / 100 ) ) ); // Adaptive ef_construction.

					$sql = "CREATE INDEX IF NOT EXISTS $index_name ON WordPress.$table_name 
						USING hnsw (content_vector vector_cosine_ops) 
						WITH (m = $m, ef_construction = $ef_construction)";

					$this->logger->log( "Creating HNSW index with m=$m, ef_construction=$ef_construction", 'info', 'system' );
				} else {
					// IVFFlat index with optimized parameters.
					$lists = max( 10, min( 1000, (int) sqrt( $vector_count ) ) ); // Square root rule, capped.

					$sql = "CREATE INDEX IF NOT EXISTS $index_name ON WordPress.$table_name 
						USING ivfflat (content_vector vector_cosine_ops) 
						WITH (lists = $lists)";

					$this->logger->log( "Creating IVFFlat index with lists=$lists", 'info', 'system' );
				}

				// Create the index.
				$conn->exec( $sql );

				// Update index statistics.
				$conn->exec( "ANALYZE WordPress.$table_name" );

				$this->logger->log( "Successfully created/upgraded vector index to $target_type", 'info', 'system' );

				// Store index metadata for future reference.
				update_option( 'gg_data_vector_index_type', $target_type );
				update_option( 'gg_data_vector_index_created', time() );
				update_option( 'gg_data_vector_count_at_index', $vector_count );

				return true;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error creating/upgrading vector index: ' . $e->getMessage(), 'error', 'system' );
				return false;
			}
		}

		/**
		 * Migrate taxonomy tables to add site_id column if missing
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function migrate_taxonomy_tables( $conn ) {
			$prefix = $this->get_table_prefix();

			try {
				// Check and add site_id to wp_terms table.
				$terms_table     = $prefix . 'terms';
				$check_terms_sql = "SELECT column_name FROM information_schema.columns 
                                    WHERE table_schema = 'WordPress' 
									AND table_name = '$terms_table' 
									AND column_name = 'site_id'";

				$result = $conn->query( $check_terms_sql );
				if ( $result->rowCount() === 0 ) {
					// Add site_id column.
					$sql = "ALTER TABLE WordPress.$terms_table ADD COLUMN site_id BIGINT DEFAULT 1";
					$conn->exec( $sql );
					$this->logger->log( "Added site_id column to $terms_table table", 'info', 'system' );
				}

				// Check and add site_id to wp_term_taxonomy table.
				$term_taxonomy_table = $prefix . 'term_taxonomy';
				$check_taxonomy_sql  = "SELECT column_name FROM information_schema.columns 
                                       WHERE table_schema = 'WordPress' 
									   AND table_name = '$term_taxonomy_table' 
									   AND column_name = 'site_id'";

				$result = $conn->query( $check_taxonomy_sql );
				if ( $result->rowCount() === 0 ) {
					// Add site_id column.
					$sql = "ALTER TABLE WordPress.$term_taxonomy_table ADD COLUMN site_id BIGINT DEFAULT 1";
					$conn->exec( $sql );
					$this->logger->log( "Added site_id column to $term_taxonomy_table table", 'info', 'system' );
				}

				return true;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error migrating taxonomy tables: ' . $e->getMessage(), 'error', 'system' );
				return false;
			}
		}

		/**
		 * Create chunks table for content chunking (shared, model-agnostic)
		 *
		 * All embedding models read from this table. Chunks are created during
		 * content sync and used by all vector generation strategies.
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_chunks_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$posts_table = $prefix . 'posts';
			$table_name  = $prefix . 'posts_chunks';

			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			-- Primary identification
			chunk_id SERIAL PRIMARY KEY,
			post_id BIGINT NOT NULL,
			chunk_index INTEGER NOT NULL,
			
			-- Chunk content
			chunk_text TEXT NOT NULL,
			chunk_hash VARCHAR(32) NOT NULL,
			source_hash VARCHAR(32) NOT NULL,  -- Hash from wp_posts_clean.content_hash
			token_count INTEGER NOT NULL DEFAULT 0,
			
			-- Timestamps
			created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
			
			-- Constraints
			UNIQUE(post_id, chunk_index),
			FOREIGN KEY (post_id) REFERENCES $posts_table(ID) ON DELETE CASCADE
		)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$table_name}_post_id_idx ON $table_name (post_id)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_hash_idx ON $table_name (chunk_hash)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_source_hash_idx ON $table_name (source_hash)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			$this->logger->log( 'Created posts_chunks table for model-agnostic content chunking', 'info', 'system' );

			return true;
		}

		/**
		 * Create TF-IDF vectors table (row-per-embedding architecture v2.0)
		 *
		 * Each embedding is stored as a separate row with field_type discriminator.
		 * This enables chunk-level embeddings and single HNSW index per table.
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_tfidf_vectors_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$posts_table = $prefix . 'posts';
			$table_name  = $prefix . 'posts_tfidf_300';

			// Row-per-embedding schema (v2.0).
			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			-- Primary identification
			id SERIAL PRIMARY KEY,
			post_id BIGINT NOT NULL,
			
			-- Field discriminator
			field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
			chunk_index INTEGER,  -- NULL for title/excerpt, 0-N for chunks
			
			-- Single embedding column (300-dimensional TF-IDF)
			embedding VECTOR(300),
			
			-- Metadata
			content_hash VARCHAR(32) NOT NULL,
			token_count INTEGER NOT NULL DEFAULT 0,
			status VARCHAR(20) DEFAULT 'pending',
			generated_at TIMESTAMP DEFAULT NOW(),
			vocabulary_version INTEGER DEFAULT 1,
			error_message TEXT,
			
			-- Constraints
			UNIQUE(post_id, field_type, chunk_index),
			FOREIGN KEY (post_id) REFERENCES $posts_table(ID) ON DELETE CASCADE,
			CHECK (
				(field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
				(field_type = 'chunk' AND chunk_index IS NOT NULL)
			)
		)";
			$conn->exec( $sql );

			// Create indexes.
			$indexes = array(
				// Single HNSW index for all embeddings (not 3 separate indexes).
				"CREATE INDEX IF NOT EXISTS {$table_name}_embedding_hnsw ON $table_name USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)",
				// Utility indexes.
				"CREATE INDEX IF NOT EXISTS {$table_name}_post_id_idx ON $table_name (post_id)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_field_type_idx ON $table_name (field_type)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_status_idx ON $table_name (status)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_vocab_version_idx ON $table_name (vocabulary_version)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			$this->logger->log( 'Created posts_tfidf_300 table with row-per-embedding schema and single HNSW index', 'info', 'system' );

			return true;
		}

		/**
		 * Create vocabulary cache table for TF-IDF vocabulary caching
		 *
		 * Stores cached TF-IDF vocabulary to avoid rebuilding for every batch.
		 * Metadata stored in MySQL wp_gg_settings, vocabulary data in PostgreSQL.
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_vocabulary_cache_table( $conn ) {
			$prefix     = $this->get_table_prefix();
			$table_name = $prefix . 'posts_vocabulary_cache';

			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			-- Primary identification
			vocabulary_version INT PRIMARY KEY,
			
			-- Multi-connection support
			connection_name VARCHAR(255) NOT NULL DEFAULT 'default',
			site_id BIGINT NOT NULL DEFAULT 1,
			
			-- Vocabulary data (JSONB for efficient storage and querying)
			vocabulary_data JSONB NOT NULL,
			
			-- Metadata
			post_count INT NOT NULL,
			unique_terms INT NOT NULL,
			created_at TIMESTAMP DEFAULT NOW()
		)";
			$conn->exec( $sql );

			// Create indexes for multi-connection queries.
			$indexes = array(
				"CREATE INDEX IF NOT EXISTS {$table_name}_connection_idx ON $table_name (connection_name, site_id)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_created_at_idx ON $table_name (created_at)",
				"CREATE INDEX IF NOT EXISTS {$table_name}_version_connection_idx ON $table_name (vocabulary_version, connection_name, site_id)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			$this->logger->log( 'Created posts_vocabulary_cache table for TF-IDF vocabulary caching ', 'info', 'system' );

			return true;
		}

		/**
		 * Create OpenAI embedding vector tables (row-per-embedding architecture v2.0)
		 *
		 * Creates tables for storing API-generated embeddings:
		 * - wp_posts_openai_text_embedding_3_small_1536: 1536 dimensions
		 * - wp_posts_openai_text_embedding_3_large_3072: 3072 dimensions (halfvec)
		 * - wp_posts_gemini_gemini_embedding_2_3072: 3072 dimensions
		 * - wp_posts_voyage_voyage_4_1024: 1024 dimensions
		 * - wp_posts_cohere_embed_v40_1536: 1536 dimensions
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_openai_embedding_tables( $conn ) {
			$prefix      = $this->get_table_prefix();
			$posts_table = $prefix . 'posts';

			// Create connection-model association table first.
			$this->create_connection_embedding_models_table( $conn );

			// Define embedding table configurations (row-per-embedding schema).
			$tables = array(
				array(
					'name'        => $prefix . 'posts_openai_text_embedding_3_small_1536',
					'dimensions'  => 1536,
					'model'       => 'text-embedding-3-small',
					'vector_type' => 'vector',
				),
				array(
					'name'        => $prefix . 'posts_openai_text_embedding_3_large_3072',
					'dimensions'  => 3072,
					'model'       => 'text-embedding-3-large',
					'vector_type' => 'halfvec',
				),
				array(
					'name'        => $prefix . 'posts_gemini_gemini_embedding_2_3072',
					'dimensions'  => 3072,
					'model'       => 'gemini-embedding-2',
					'vector_type' => 'halfvec',
				),
				array(
					'name'        => $prefix . 'posts_voyage_voyage_4_1024',
					'dimensions'  => 1024,
					'model'       => 'voyage-4',
					'vector_type' => 'vector',
				),
				array(
					'name'        => $prefix . 'posts_cohere_embed_v40_1536',
					'dimensions'  => 1536,
					'model'       => 'embed-v4.0',
					'vector_type' => 'vector',
				),
			);

			foreach ( $tables as $table_config ) {
				$table_name  = $table_config['name'];
				$dimensions  = $table_config['dimensions'];
				$model_label = $table_config['model'];
				$vector_type = $table_config['vector_type'];

				// Row-per-embedding schema (v2.0).
				$sql = "
			CREATE TABLE IF NOT EXISTS $table_name (
				-- Primary identification
				id SERIAL PRIMARY KEY,
				post_id BIGINT NOT NULL,
				
				-- Field discriminator
				field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
				chunk_index INTEGER,  -- NULL for title/excerpt, 0-N for chunks
				
				-- Single embedding column ({$dimensions}-dimensional)
				embedding {$vector_type}({$dimensions}),
				
				-- Metadata
				content_hash VARCHAR(32) NOT NULL,
				token_count INTEGER NOT NULL DEFAULT 0,
				cost DECIMAL(10, 8) DEFAULT 0,
				status VARCHAR(20) DEFAULT 'pending',
				generated_at TIMESTAMP DEFAULT NOW(),
				model_used VARCHAR(100) DEFAULT '{$model_label}',
				error_message TEXT,
				
				-- Constraints
				UNIQUE(post_id, field_type, chunk_index),
				FOREIGN KEY (post_id) REFERENCES $posts_table(ID) ON DELETE CASCADE,
				CHECK (
					(field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
					(field_type = 'chunk' AND chunk_index IS NOT NULL)
				)
			)";
				$conn->exec( $sql );

				// Determine operator class based on vector type.
				$ops_class = ( 'halfvec' === $vector_type ) ? 'halfvec_cosine_ops' : 'vector_cosine_ops';

				// Create indexes.
				$indexes = array(
					// Single HNSW index for all embeddings (not 3 separate indexes).
					"CREATE INDEX IF NOT EXISTS {$table_name}_embedding_hnsw ON $table_name USING hnsw (embedding {$ops_class}) WITH (m = 16, ef_construction = 64)",
					// Utility indexes.
					"CREATE INDEX IF NOT EXISTS {$table_name}_post_id_idx ON $table_name (post_id)",
					"CREATE INDEX IF NOT EXISTS {$table_name}_field_type_idx ON $table_name (field_type)",
					"CREATE INDEX IF NOT EXISTS {$table_name}_status_idx ON $table_name (status)",
					"CREATE INDEX IF NOT EXISTS {$table_name}_hash_idx ON $table_name (content_hash)",
				);

				foreach ( $indexes as $index_sql ) {
					$conn->exec( $index_sql );
				}

				$this->logger->log( "Created $table_name with row-per-embedding schema and single HNSW index ({$vector_type}, {$dimensions}d)", 'info', 'system' );
			}

			return true;
		}

		/**
		 * Create HashingTF Murmur3 1024D embedding table
		 *
		 * Stateless internal model — uses tokenizer_version (not vocabulary_version).
		 * The search function detects absence of vocabulary_version at runtime
		 * and automatically uses the dense/stateless path.
		 *
		 * @param PDO $conn Database connection.
		 * @return bool True on success.
		 */
		protected function create_hashingtf_embedding_table( $conn ) {
			$prefix      = $this->get_table_prefix();
			$posts_table = $prefix . 'posts';
			$table_name  = $prefix . 'posts_hashingtf_murmur3_1024';

			$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			id SERIAL PRIMARY KEY,
			post_id BIGINT NOT NULL,
			field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
			chunk_index INTEGER,
			embedding vector(1024),
			content_hash VARCHAR(32) NOT NULL,
			token_count INTEGER NOT NULL DEFAULT 0,
			status VARCHAR(20) DEFAULT 'pending',
			generated_at TIMESTAMP DEFAULT NOW(),
			tokenizer_version INTEGER NOT NULL DEFAULT 1,
			error_message TEXT,
			UNIQUE(post_id, field_type, chunk_index),
			FOREIGN KEY (post_id) REFERENCES $posts_table(ID) ON DELETE CASCADE,
			CHECK (
				(field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
				(field_type = 'chunk' AND chunk_index IS NOT NULL)
			)
		)";
			$conn->exec( $sql );

			$indexes = array(
				"CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_embedding_hnsw ON $table_name USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)",
				"CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_post_id ON $table_name (post_id)",
				"CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_field_type ON $table_name (field_type)",
				"CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_status ON $table_name (status)",
				"CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_tokenizer_version ON $table_name (tokenizer_version)",
			);

			foreach ( $indexes as $index_sql ) {
				$conn->exec( $index_sql );
			}

			$this->logger->log( "Created $table_name with row-per-embedding schema and HNSW index (vector, 1024d, stateless)", 'info', 'system' );

			return true;
		}

		/**
		 * Create connection-model association table
		 *
		 * Tracks which embedding models are active for this PostgreSQL connection.
		 * Each connection independently manages its list of active models.
		 *
		 * @since  1.0.0
		 * @param  PDO $conn PostgreSQL connection.
		 * @return bool Success.
		 */
		protected function create_connection_embedding_models_table( $conn ) {
			$sql = "
				CREATE TABLE IF NOT EXISTS connection_embedding_models (
					-- Model identifier (references wp_gg_settings.setting_key where category='embeddings_model')
					model_key VARCHAR(100) PRIMARY KEY,
					
					-- Metadata
					added_at TIMESTAMP DEFAULT NOW(),
					is_active BOOLEAN DEFAULT true
				)
			";

			$conn->exec( $sql );

			// Create index for active models query.
			$conn->exec( 'CREATE INDEX IF NOT EXISTS connection_embedding_models_active_idx ON connection_embedding_models (is_active)' );

			$this->logger->log( 'Created connection_embedding_models table', 'info', 'system' );

			return true;
		}

		/**
		 * Create sync metadata tables for all sites in a multisite installation
		 * Also works for single site installations
		 *
		 * @return array Results of table creation for each site
		 */
		public function create_sync_metadata_tables_for_all_sites() {
			$results = array();

			if ( is_multisite() ) {
				// Get all sites in the network.
				$sites = get_sites( array( 'number' => 0 ) );

				foreach ( $sites as $site ) {
					// Switch to each site and create its sync metadata table.
					switch_to_blog( $site->blog_id );

					// Create sync metadata table for this site (MySQL only).
					$result = $this->create_sync_metadata_mysql_table();

					global $wpdb;
					$results[ "site_{$site->blog_id}" ] = array(
						'blog_id'    => $site->blog_id,
						'table_name' => $wpdb->prefix . 'gg_sync_metadata',
						'success'    => $result,
						'url'        => get_site_url(),
					);

					restore_current_blog();
				}
			} else {
				// Single site installation.
				$result = $this->create_sync_metadata_mysql_table();

				global $wpdb;
				$results['single_site'] = array(
					'blog_id'    => 1,
					'table_name' => $wpdb->prefix . 'gg_sync_metadata',
					'success'    => $result,
					'url'        => get_site_url(),
				);
			}

			return $results;
		}

		/**
		 * Create gg_pg_settings table for dedicated plugin settings
		 *
		 * @param PDO $conn Database connection.
		 * @return bool Success or failure.
		 */
		protected function create_settings_table( $conn ) {
			unset( $conn );
			// Create settings table in WordPress MySQL database, not PostgreSQL.
			// This allows direct access via $wpdb for REST API and React dashboard.

			global $wpdb;
			$table_name = $wpdb->prefix . 'gg_settings';

			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
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
				KEY connection_category (connection_name, category),
				KEY active_settings (is_active),
				KEY environment (environment),
				KEY provider (provider),
				KEY updated_at (updated_at)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			$this->logger->log( 'Created wp_gg_settings table in WordPress MySQL database for plugin settings management (WordPress multisite compatible)', 'info', 'system' );

			return true;
		}

		/**
		 * Create sync metadata tracking tables in both MySQL and PostgreSQL
		 *
		 * MySQL table: Tracks what SHOULD be synced (based on settings)
		 * PostgreSQL table: Tracks what HAS BEEN synced (actual state)
		 *
		 * This enables instant validation (~100ms) instead of full table scans (3-13s)
		 *
		 * @param PDO $conn PostgreSQL connection.
		 * @return bool True on success.
		 * @throws Exception If table creation fails.
		 */
		protected function create_sync_metadata_table( $conn ) {
			// 1. Create MySQL metadata table (aggregate tracking for fast UI queries).
			$this->create_sync_metadata_mysql_table();

			// 2. Create PostgreSQL metadata table (individual entity tracking).
			$this->create_sync_metadata_postgresql_table( $conn );

			return true;
		}

		/**
		 * Create MySQL sync metadata table (aggregate tracking)
		 * This method can be called independently during plugin activation for multisite support
		 *
		 * @return bool True on success.
		 */
		public function create_sync_metadata_mysql_table() {
			global $wpdb;

			$mysql_table     = $wpdb->prefix . 'gg_sync_metadata';
			$charset_collate = $wpdb->get_charset_collate();

			$mysql_sql = "CREATE TABLE IF NOT EXISTS $mysql_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			connection_name VARCHAR(255) NOT NULL,
			entity_type VARCHAR(50) NOT NULL COMMENT 'Top-level entity types: term, post',
			post_type VARCHAR(100) DEFAULT NULL COMMENT 'Post type slug (only for entity_type=post)',
			wp_count INT DEFAULT 0 COMMENT 'Count from WordPress (source of truth)',
			pg_count INT DEFAULT 0 COMMENT 'Count from PostgreSQL',
			drift INT DEFAULT 0 COMMENT 'Calculated: pg_count - wp_count (negative = PG behind)',
			last_sync_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Last successful sync timestamp',
			sync_status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, syncing, completed, error',
			enabled TINYINT(1) DEFAULT 1 COMMENT 'Whether sync is enabled for this entity type',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_entity (connection_name, entity_type, post_type),
			KEY idx_connection (connection_name),
			KEY idx_entity_type (entity_type),
			KEY idx_sync_status (sync_status),
			KEY idx_enabled (enabled)
		) $charset_collate COMMENT='Aggregate sync metadata tracking for fast UI queries';";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $mysql_sql );

			$this->logger->log( 'Created ' . $wpdb->prefix . 'gg_sync_metadata table in MySQL with aggregate tracking schema (multisite-aware)', 'info', 'system' );

			return true;
		}

		/**
		 * Create PostgreSQL sync metadata entities table (individual entity tracking)
		 * Called by create_sync_metadata_table() during schema initialization
		 *
		 * @param PDO $conn PostgreSQL connection.
		 * @return bool True on success.
		 */
		protected function create_sync_metadata_postgresql_table( $conn ) {
			global $wpdb;

			// 2. Create PostgreSQL metadata table (individual entity tracking for modification detection).
			$pg_table = $wpdb->prefix . 'gg_sync_metadata_entities';

			$pg_sql = "CREATE TABLE IF NOT EXISTS $pg_table (
			id BIGSERIAL PRIMARY KEY,
			connection_name VARCHAR(100) NOT NULL DEFAULT 'default',
			entity_type VARCHAR(50) NOT NULL, -- post, postmeta, term, term_taxonomy, term_relationship
			entity_id VARCHAR(255) NOT NULL, -- Entity ID (integer for posts/terms, composite for term_relationships)
			source_id BIGINT NULL, -- Parent entity ID (post_id for postmeta, term_id for term_taxonomy)
			post_type VARCHAR(20) NULL, -- For posts only
			source_modified_at TIMESTAMP NULL, -- Source entity modified timestamp (post_modified_gmt, term updated, etc)
			synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When was this synced?
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			CONSTRAINT unique_pg_entity UNIQUE (connection_name, entity_type, entity_id)
		);

		CREATE INDEX IF NOT EXISTS idx_pg_connection ON $pg_table(connection_name);
		CREATE INDEX IF NOT EXISTS idx_pg_entity_type ON $pg_table(entity_type);
		CREATE INDEX IF NOT EXISTS idx_pg_post_type ON $pg_table(post_type);
		CREATE INDEX IF NOT EXISTS idx_pg_source_id ON $pg_table(source_id);
		CREATE INDEX IF NOT EXISTS idx_pg_source_modified ON $pg_table(source_modified_at);
		CREATE INDEX IF NOT EXISTS idx_pg_synced_at ON $pg_table(synced_at);
		CREATE INDEX IF NOT EXISTS idx_pg_updated ON $pg_table(updated_at);

		COMMENT ON TABLE $pg_table IS 'Tracks individual entities synced from WordPress to PostgreSQL for modification detection';
		COMMENT ON COLUMN $pg_table.entity_type IS 'Type: post, postmeta, term, term_taxonomy, term_relationship';
		COMMENT ON COLUMN $pg_table.entity_id IS 'Entity ID (integer for posts/terms, composite string for term_relationships like 123-456)';
		COMMENT ON COLUMN $pg_table.source_id IS 'Parent entity (post_id for postmeta, term_id for term_taxonomy)';
		COMMENT ON COLUMN $pg_table.source_modified_at IS 'Source entity modification timestamp for change detection';
		COMMENT ON COLUMN $pg_table.synced_at IS 'Timestamp when entity was synced to PostgreSQL';";

			$conn->exec( $pg_sql );

			$this->logger->log( 'Created ' . $wpdb->prefix . 'gg_sync_metadata_entities table in PostgreSQL for entity modification tracking', 'info', 'system' );

			return true;
		}

		/**
		 * Create PostgreSQL full-text search function
		 *
		 * @param PDO    $conn PostgreSQL connection.
		 * @param string $connection_name Connection name for storing language setting.
		 * @throws Exception If function creation fails.
		 */
		protected function create_search_function( $conn, $connection_name = 'default' ) {
			$search_schema = new GG_Data_Search_Schema();           // Get raw PDO connection for pg_query.
			$pg_connection = $this->db->get_connection();

			if ( ! $pg_connection ) {
				throw new Exception( 'No PostgreSQL connection available for search function creation' );
			}

			// Check if search function already exists.
			if ( $search_schema->search_function_exists( $pg_connection ) ) {
				$this->logger->log( 'PostgreSQL full-text search function already exists, skipping creation', 'info', 'system', $connection_name );
				return;
			}

			// Pass connection name so language can be stored .
			$result = $search_schema->create_search_function( $pg_connection, $connection_name );
			if ( ! $result ) {
				$this->logger->log( 'Failed to create search function', 'error', 'system', $connection_name );
				throw new Exception( 'Failed to create search function. Check dashboard logs for details.' );
			}

			$this->logger->log( 'PostgreSQL full-text search function created successfully with language detection', 'info', 'system', $connection_name );
		}
	}
}
