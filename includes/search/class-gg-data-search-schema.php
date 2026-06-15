<?php
/**
 * Search Schema Manager
 *
 * Manages PostgreSQL search functions and indexes.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Search_Schema
 *
 * Handles creation and management of PostgreSQL search functions.
 */
class GG_Data_Search_Schema {

	/**
	 * Database instance
	 *
	 * @var GG_Data_DB
	 */
	private $db;

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
		$this->db     = new GG_Data_DB();
		$this->logger = new GG_Data_Logger();
	}

	/**
	 * Create search function in PostgreSQL
	 *
	 * @param PDO|null    $connection      Optional PDO connection.
	 * @param string|null $connection_name Connection name for storing language setting.
	 * @return bool True on success, false on failure.
	 * @throws Exception If SQL file is not found or cannot be read, or if function creation fails.
	 */
	public function create_search_function( $connection = null, $connection_name = null ) {
		if ( ! $connection ) {
			$connection = $this->db->get_connection();
		}

		if ( ! $connection ) {
			$this->logger->log(
				'No PostgreSQL connection available for search function creation',
				'error',
				'search',
				$connection_name
			);
			return false;
		}

		try {
			// Detect site language and store in settings.
			require_once plugin_dir_path( __FILE__ ) . 'class-gg-data-search-language.php';
			$site_language = GG_Data_Search_Language::get_site_search_language();

			// Store default settings globally (search is a site-wide feature).
			$settings_manager = new GG_Data_Settings_Manager();

			// Language setting - store globally.
			$settings_manager->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'language', $site_language );

			// Typo tolerance settings - Default to disabled for backwards compatibility.
			$settings_manager->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'typo_tolerance', false );
			$settings_manager->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'similarity_threshold', 0.5 );
			$settings_manager->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'retrieval_mode', 'hybrid_default' );
			$settings_manager->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'observability_enabled', false );

			if ( $connection_name ) {
				$trigram_supported = false;
				$vector_supported  = false;

				try {
					$trigram_stmt      = $connection->query( "SELECT COUNT(*) FROM pg_extension WHERE extname = 'pg_trgm'" );
					$trigram_supported = $trigram_stmt && $trigram_stmt->fetchColumn() > 0;
				} catch ( Exception $e ) {
					$trigram_supported = false;
				}

				try {
					$vector_stmt      = $connection->query( "SELECT COUNT(*) FROM pg_extension WHERE extname = 'vector'" );
					$vector_supported = $vector_stmt && $vector_stmt->fetchColumn() > 0;
				} catch ( Exception $e ) {
					$vector_supported = false;
				}

				$settings_manager->set_with_category_public( 'search', $connection_name, 'trigram_supported', (bool) $trigram_supported );
				$settings_manager->set_with_category_public( 'search', $connection_name, 'vector_supported', (bool) $vector_supported );
			}

			// If connection name provided, store it as the search connection.
			if ( $connection_name ) {
				$settings_manager->set_with_category_public( 'search', GG_DATA_SEARCH_SETTINGS_CONNECTION, 'connection', $connection_name );
			}

			$this->logger->log(
				'Stored global search settings',
				'info',
				'search',
				GG_DATA_SEARCH_SETTINGS_CONNECTION,
				// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Keep settings payload readable with long telemetry key names.
				array(
					'language'             => $site_language,
					'locale'               => get_locale(),
					'typo_tolerance'       => false,
					'similarity_threshold' => 0.5,
				'retrieval_mode'       => 'hybrid_default',
					'observability_enabled' => false,
					'connection'           => $connection_name,
				)
				// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
			);

			// Read SQL file.
			$sql_file = plugin_dir_path( __FILE__ ) . 'sql/create-search-function.sql';

			if ( ! file_exists( $sql_file ) ) {
				throw new Exception( "SQL file not found: {$sql_file}" );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local plugin SQL file from disk, not a remote URL.
			$sql = file_get_contents( $sql_file );

			if ( ! $sql ) {
				throw new Exception( "Failed to read SQL file: {$sql_file}" );
			}

			// PDO doesn't handle multiple statements in one call well.
			// Split the SQL into logical blocks and execute separately.

			// First, remove all comments from SQL (but preserve content inside $$ delimiters).
			$sql_clean = preg_replace( '/--[^\n]*$/m', '', $sql );

			// Extract individual statements.
			$statements = array();

			// 1. Extract all DROP FUNCTION statements.
			preg_match_all( '/DROP\s+FUNCTION\s+IF\s+EXISTS[^;]+;/is', $sql_clean, $drop_matches );
			foreach ( $drop_matches[0] as $drop_stmt ) {
				$statements[] = trim( $drop_stmt );
			}

			// 2. Extract all CREATE OR REPLACE FUNCTION statements.
			// Match from CREATE FUNCTION start through dollar-quoted function terminator ($$;),
			// independent of whether LANGUAGE appears before or after AS $$ in SQL formatting.
			preg_match_all( '/CREATE\s+OR\s+REPLACE\s+FUNCTION.+?\$\$\s*;/is', $sql_clean, $create_matches );
			foreach ( $create_matches[0] as $create_stmt ) {
				$statements[] = trim( $create_stmt );
			}

			// 3. Skip CREATE INDEX - it should already exist from schema setup.
			// The SQL file notes: "This is a safety check - the index should have been created during schema setup".

			try {
				// Don't start a new transaction if we're already in one (e.g., during schema creation).
				$already_in_transaction = $connection->inTransaction();

				if ( ! $already_in_transaction ) {
					$connection->beginTransaction();
				}

				foreach ( $statements as $index => $statement ) {
					if ( empty( trim( $statement ) ) ) {
						continue;
					}

					// Execute each statement.
					$stmt = $connection->query( $statement );
				}

				// Only commit if we started the transaction.
				if ( ! $already_in_transaction ) {
					$connection->commit();
				}
			} catch ( PDOException $pdo_e ) {
				// Only rollback if we started the transaction.
				if ( ! $already_in_transaction && $connection->inTransaction() ) {
					$connection->rollBack();
				}
				throw new Exception( 'Failed to create search function: ' . $pdo_e->getMessage() );
			}

			$this->logger->log(
				'PostgreSQL search function created successfully',
				'info',
				'search',
				$connection_name,
				array( 'sql_file' => $sql_file )
			);

			return true;

		} catch ( Exception $e ) {
			$this->logger->log(
				'Failed to create search function: ' . $e->getMessage(),
				'error',
				'search',
				$connection_name,
				array(
					'exception' => get_class( $e ),
				)
			);

			return false;
		}
	}

	/**
	 * Check if search function exists
	 *
	 * @param PDO|null $connection Optional PDO connection.
	 * @return bool True if function exists, false otherwise.
	 */
	public function search_function_exists( $connection = null ) {
		if ( ! $connection ) {
			$connection = $this->db->get_connection();
		}

		if ( ! $connection ) {
			return false;
		}

		$sql = "
			SELECT EXISTS (
				SELECT 1 
				FROM pg_proc p
				JOIN pg_namespace n ON p.pronamespace = n.oid
				WHERE n.nspname = 'public'
				AND p.proname = 'search_native_orchestrate'
			) as function_exists
		";

		try {
			$stmt = $connection->query( $sql );
			if ( ! $stmt ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$row = $stmt->fetch( PDO::FETCH_ASSOC );
			return $row && 't' === $row['function_exists'];
		} catch ( Exception $e ) {
			$this->logger->log(
				'Failed to check if search function exists: ' . $e->getMessage(),
				'error',
				'search'
			);
			return false;
		}
	}

	/**
	 * Test search function
	 *
	 * Runs a simple test query to verify the search function works.
	 *
	 * @param string   $search_text Test search query.
	 * @param PDO|null $connection Optional PDO connection.
	 * @return array|false Test results or false on failure.
	 * @throws Exception If statement preparation or execution fails.
	 */
	public function test_search_function( $search_text = 'test', $connection = null ) {
		if ( ! $connection ) {
			$connection = $this->db->get_connection();
		}

		if ( ! $connection ) {
			return false;
		}

		$sql = "SELECT * FROM search_native_orchestrate(:search_text, ARRAY['post', 'page'], 5)";

		try {
			$stmt = $connection->prepare( $sql );
			if ( ! $stmt ) {
				$error_info = $connection->errorInfo();
				throw new Exception( 'Failed to prepare statement: ' . $error_info[2] );
			}

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$stmt->bindParam( ':search_text', $search_text, PDO::PARAM_STR );
			$success = $stmt->execute();

			if ( ! $success ) {
				$error_info = $stmt->errorInfo();
				throw new Exception( 'Query execution failed: ' . $error_info[2] );
			}

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$results = $stmt->fetchAll( PDO::FETCH_ASSOC );

			$this->logger->log(
				'Search function test successful',
				'info',
				'search',
				null,
				array(
					'search_text'  => $search_text,
					'result_count' => count( $results ),
				)
			);

			return $results;

		} catch ( Exception $e ) {
			$this->logger->log(
				'Search function test failed: ' . $e->getMessage(),
				'error',
				'search',
				null,
				array( 'search_text' => $search_text )
			);
			return false;
		}
	}

	/**
	 * Get search function status
	 *
	 * @param string $connection_name Connection name (default: 'default').
	 * @return array Status information.
	 */
	public function get_status( $connection_name = 'default' ) {
		// Check if this is a PostgREST connection - search functions require direct PDO access.
		if ( $this->db->is_postgrest_connection( $connection_name ) ) {
			return array(
				'function_exists' => false,
				'connection'      => true,
				'status'          => 'Search functions not supported via PostgREST',
				'connection_name' => $connection_name,
				'requires_pdo'    => true,
			);
		}

		$connection = $this->db->get_connection( $connection_name );

		if ( ! $connection ) {
			return array(
				'function_exists' => false,
				'connection'      => false,
				'status'          => 'No PostgreSQL connection',
				'connection_name' => $connection_name,
			);
		}

		$function_exists = $this->search_function_exists( $connection );

		return array(
			'function_exists' => $function_exists,
			'connection'      => true,
			'status'          => $function_exists ? 'Ready' : 'Function not created',
			'connection_name' => $connection_name,
		);
	}
}
