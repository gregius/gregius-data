<?php
/**
 * PostgREST Database Provider
 *
 * Implements the GG_Data_DB_Provider interface for PostgreSQL via PostgREST API.
 * Provides connection management, data synchronization, vector operations,
 * and schema management using the PostgREST protocol.
 *
 * Compatible with Supabase, Neon, and any PostgREST-compatible endpoint.
 *
 * @package Gregius_Data
 * @subpackage Gregius_PostgreSQL/includes/providers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load provider interface if not already loaded.
if ( ! interface_exists( 'GG_Data_DB_Provider' ) ) {
	require_once __DIR__ . '/interface-gg-db-provider.php';
}

/**
 * PostgREST provider implementation
 *
 * @since 1.0.0
 */
class GG_Data_PostgREST_Provider implements GG_Data_DB_Provider {

	/**
	 * Supabase project URL
	 *
	 * @var string
	 */
	protected $project_url;

	/**
	 * Supabase API key (anon key)
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Supabase service role key (for admin operations)
	 *
	 * @var string
	 */
	protected $service_role_key;

	/**
	 * REST API base URL
	 *
	 * @var string
	 */
	protected $rest_url;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	protected $logger;

	/**
	 * Last API error
	 *
	 * @var string
	 */
	protected $last_error = '';

	/**
	 * Connection status
	 *
	 * @var bool
	 */
	protected $connected = false;

	/**
	 * Connection configuration
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Connection name (identifier)
	 *
	 * @var string|null
	 */
	protected $connection_name = null;

	/**
	 * Constructor
	 *
	 * @param array  $connection_config Optional connection configuration.
	 * @param string $connection_name   Optional connection identifier.
	 */
	public function __construct( $connection_config = array(), $connection_name = null ) {
		$this->logger          = new GG_Data_Logger();
		$this->connection_name = $connection_name;

		if ( ! empty( $connection_config ) ) {
			$connection_config = $this->normalize_connection_config( $connection_config );

			// Store config but don't test connection yet (lazy connection).
			// This saves 300-400ms during provider initialization.
			// Connection will be tested on first actual operation if needed.
			$this->config           = $connection_config;
			$this->project_url      = rtrim( $connection_config['project_url'], '/' );
			$this->api_key          = $connection_config['publishable_key'];
			$this->service_role_key = $connection_config['secret_key'];
			$this->rest_url         = $this->project_url . '/rest/v1';
			$this->connected        = true; // Assume connected - will fail on first operation if not.
		}
	}

	/**
	 * Establish connection to Supabase
	 *
	 * @param array $connection_config Connection configuration array.
	 * @return array Connection result.
	 */
	public function connect( $connection_config ) {
		try {
			$connection_config = $this->normalize_connection_config( $connection_config );

			// Validate required fields.
			$required_fields = array( 'project_url', 'publishable_key', 'secret_key' );
			foreach ( $required_fields as $field ) {
				if ( empty( $connection_config[ $field ] ) ) {
					$message = "Missing required field: {$field}";
					$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'field' => $field ) );
					return array(
						'success' => false,
						'message' => $message,
					);
				}
			}

			// Store configuration.
			$this->config = $connection_config;

			// Set connection properties.
			$this->project_url      = rtrim( $connection_config['project_url'], '/' );
			$this->api_key          = $connection_config['publishable_key'];
			$this->service_role_key = $connection_config['secret_key'];
			$this->rest_url         = $this->project_url . '/rest/v1';

			// Test connection using the elevated key.
			$response = wp_safe_remote_get(
				$this->rest_url . '/',
				array(
					'headers' => $this->get_headers( true ),
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message          = 'Supabase connection error: ' . $response->get_error_message();
				$this->last_error = $message;
				$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'error' => $response->get_error_message() ) );
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 201 !== $status_code ) {
				$body             = wp_remote_retrieve_body( $response );
				$message          = "Supabase connection failed with status {$status_code}: {$body}";
				$this->last_error = $message;
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'status_code' => $status_code,
						'body'        => $body,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$this->connected = true;

			$message = sprintf( 'Successfully connected to Supabase project (%s)', $this->connection_name );
			// Log at debug level - successful connections are routine, not noteworthy.
			$this->logger->log( $message, 'debug', 'connection', $this->connection_name );

			return array(
				'success' => true,
				'message' => $message,
				'version' => 'Supabase PostgreSQL (REST API)',
			);

		} catch ( Exception $e ) {
			$message          = 'Supabase connection error: ' . $e->getMessage();
			$this->last_error = $message;
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Close Supabase connection
	 *
	 * @return array Disconnection result.
	 */
	public function disconnect() {
		$this->connected = false;
		$this->logger->log( 'Disconnected from Supabase', 'info', 'connection', $this->connection_name );

		return array(
			'success' => true,
			'message' => 'Disconnected successfully',
		);
	}

	/**
	 * Test Supabase connection
	 *
	 * @param array $connection_config Connection configuration.
	 * @return array Test result.
	 */
	public function test_connection( $connection_config ) {
		// Use connect method for testing.
		$result = $this->connect( $connection_config );

		if ( $result['success'] ) {
			// Try to query schema version.
			$schema_result = $this->get_schema_version();
			if ( isset( $schema_result['version'] ) ) {
				$result['version'] = $schema_result['version'];
			}

			// Check for pgvector extension.
			$extensions_check = $this->execute_rpc( 'check_extensions', array() );
			if ( ! empty( $extensions_check ) ) {
				$result['extensions'] = $extensions_check;
			}
		}

		return $result;
	}

	/**
	 * Synchronize WordPress post to Supabase
	 *
	 * @param int   $post_id   WordPress post ID.
	 * @param array $post_data Post data to synchronize.
	 * @return array Sync result.
	 */
	public function sync_post( $post_id, $post_data ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			// Prepare data for Supabase.
			$supabase_data = $this->prepare_post_data( $post_id, $post_data );

			// Use upsert (insert or update).
			$response = wp_safe_remote_post(
				$this->rest_url . '/wp_posts',
				array(
					'method'  => 'POST',
					'headers' => array_merge(
						$this->get_headers( true ), // Use service role for writes.
						array(
							'Content-Type' => 'application/json',
							'Prefer'       => 'resolution=merge-duplicates,return=representation',
						)
					),
					'body'    => wp_json_encode( $supabase_data ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message = 'Supabase sync error: ' . $response->get_error_message();
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'post_id' => $post_id,
						'error'   => $response->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 201 !== $status_code ) {
				$body    = wp_remote_retrieve_body( $response );
				$message = "Supabase sync failed with status {$status_code}: {$body}";
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'post_id'     => $post_id,
						'status_code' => $status_code,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$synced_at = gmdate( 'c' );

			return array(
				'success'   => true,
				'post_id'   => $post_id,
				'synced_at' => $synced_at,
				'message'   => 'Post synchronized successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Supabase sync exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'post_id'   => $post_id,
					'exception' => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Delete post from Supabase
	 *
	 * @param int $post_id WordPress post ID to delete.
	 * @return array Deletion result.
	 */
	public function delete_post( $post_id ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			$response = wp_remote_request(
				$this->rest_url . '/wp_posts?id=eq.' . intval( $post_id ),
				array(
					'method'  => 'DELETE',
					'headers' => $this->get_headers( true ), // Use service role for deletes.
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message = 'Supabase delete error: ' . $response->get_error_message();
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'post_id' => $post_id,
						'error'   => $response->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 204 !== $status_code ) {
				$body    = wp_remote_retrieve_body( $response );
				$message = "Supabase delete failed with status {$status_code}: {$body}";
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'post_id'     => $post_id,
						'status_code' => $status_code,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			return array(
				'success' => true,
				'post_id' => $post_id,
				'message' => 'Post deleted successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Supabase delete exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'post_id'   => $post_id,
					'exception' => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Generate vector embeddings for post
	 *
	 * @param int   $post_id          WordPress post ID.
	 * @param array $embedding_config Embedding configuration.
	 * @return array Vector generation result.
	 */
	public function generate_vectors( $post_id, $embedding_config ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			// Prepare vector data.
			$vector_data = array(
				'post_id'      => $post_id,
				'model'        => isset( $embedding_config['model'] ) ? $embedding_config['model'] : 'tfidf_300',
				'dimensions'   => isset( $embedding_config['dimensions'] ) ? $embedding_config['dimensions'] : 300,
				'generated_at' => gmdate( 'c' ),
			);

			// Call RPC function to generate vectors (assumes stored procedure exists).
			$result = $this->execute_rpc(
				'generate_post_vectors',
				array(
					'p_post_id' => $post_id,
					'p_config'  => wp_json_encode( $embedding_config ),
				)
			);

			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => 'Vector generation failed: ' . $result->get_error_message(),
				);
			}

			return array(
				'success'      => true,
				'post_id'      => $post_id,
				'dimensions'   => $vector_data['dimensions'],
				'model'        => $vector_data['model'],
				'generated_at' => $vector_data['generated_at'],
				'message'      => 'Vectors generated successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Vector generation exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'post_id'   => $post_id,
					'exception' => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Perform semantic search
	 *
	 * @param string $query         Search query string.
	 * @param array  $search_config Search configuration.
	 * @return array Search results.
	 */
	public function search( $query, $search_config ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			$start_time = microtime( true );

			// Call RPC function for vector search (assumes stored procedure exists).
			$results = $this->execute_rpc(
				'search_posts',
				array(
					'p_query'  => $query,
					'p_config' => wp_json_encode( $search_config ),
				)
			);

			if ( is_wp_error( $results ) ) {
				return array(
					'success' => false,
					'message' => 'Search failed: ' . $results->get_error_message(),
				);
			}

			$latency = ( microtime( true ) - $start_time ) * 1000; // Convert to milliseconds.

			return array(
				'success' => true,
				'results' => $results,
				'total'   => count( $results ),
				'latency' => round( $latency, 2 ),
				'message' => 'Search completed successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Search exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'query'     => $query,
					'exception' => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Get database schema version
	 *
	 * @return array Schema version information.
	 */
	public function get_schema_version() {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			// Query schema version from metadata table.
			// Use service role key to bypass RLS.
			$response = wp_safe_remote_get(
				$this->rest_url . '/gg_schema_meta?key=eq.version&select=value&limit=1',
				array(
					'headers' => $this->get_headers( true ),
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to get schema version: ' . $response->get_error_message(),
				);
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! empty( $data ) && isset( $data[0]['value'] ) ) {
				return array(
					'success' => true,
					'version' => $data[0]['value'],
					'message' => 'Schema version retrieved successfully',
				);
			}

			return array(
				'success' => false,
				'version' => '0.0.0',
				'message' => 'Schema version not found',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Error retrieving schema version: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Create or update database schema
	 *
	 * NOTE: Supabase REST API cannot execute DDL statements (CREATE TABLE, etc.).
	 * Schema must be created manually in Supabase SQL Editor.
	 *
	 * @param array $schema_config Optional schema configuration.
	 * @return array Schema creation result with manual setup instructions.
	 */
	public function create_schema( $schema_config = array() ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			// Check if schema already exists.
			$version_check = $this->get_schema_version();

			if ( $version_check['success'] && '0.0.0' !== $version_check['version'] ) {
				return array(
					'success' => true,
					'version' => $version_check['version'],
					'message' => 'Schema already exists (version ' . $version_check['version'] . ')',
				);
			}

			// Extract project reference from URL for dashboard link.
			$project_ref = '';
			if ( preg_match( '/https:\/\/([^\.]+)\.supabase\.co/', $this->project_url, $matches ) ) {
				$project_ref = $matches[1];
			}

			// Schema doesn't exist - return manual setup instructions.
			return array(
				'success'               => false,
				'requires_manual_setup' => true,
				'sql_file'              => plugin_dir_path( __FILE__ ) . '../sql/postgrest-schema.sql',
				'setup_url'             => $project_ref ? "https://supabase.com/dashboard/project/{$project_ref}/sql" : '',
				'instructions'          => array(
					'Copy the SQL file contents from: includes/sql/postgrest-schema.sql',
					'Go to your Supabase project → SQL Editor → New Query',
					'Paste the SQL and click "Run"',
					'Wait for completion (30-60 seconds)',
					'Return to WordPress and test connection',
				),
				'message'               => 'Manual schema creation required. PostgREST API cannot execute DDL statements.',
				'reason'                => 'Supabase REST API is for data operations (CRUD) only, not schema management (DDL). This is a fundamental limitation of PostgREST.',
			);

		} catch ( Exception $e ) {
			$message = 'Schema check error: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Resolve runtime Supabase config to canonical keys.
	 *
	 * Runtime requires canonical key names only.
	 *
	 * @param array $connection_config Raw connection configuration.
	 * @return array|WP_Error Canonical runtime config or error when required fields are missing.
	 */
	public static function get_runtime_supabase_config( $connection_config ) {
		$project_url     = isset( $connection_config['project_url'] ) ? trim( (string) $connection_config['project_url'] ) : '';
		$publishable_key = isset( $connection_config['publishable_key'] ) ? (string) $connection_config['publishable_key'] : '';
		$secret_key      = isset( $connection_config['secret_key'] ) ? (string) $connection_config['secret_key'] : '';

		if ( '' === $project_url || '' === $publishable_key ) {
			return new WP_Error(
				'gg_data_supabase_config_missing',
				__( 'Supabase connection missing project_url or publishable_key.', 'gregius-data' )
			);
		}

		return array(
			'project_url'     => $project_url,
			'publishable_key' => $publishable_key,
			'secret_key'      => $secret_key,
		);
	}

	/**
	 * Build Supabase REST headers from canonical keys.
	 *
	 * @param string $publishable_key Publishable key for standard operations.
	 * @param string $secret_key      Optional secret key for elevated operations.
	 * @param bool   $use_secret      Whether to use secret key when available.
	 * @return array Supabase headers.
	 */
	public static function build_supabase_headers( $publishable_key, $secret_key = '', $use_secret = false ) {
		$key     = ( $use_secret && '' !== (string) $secret_key ) ? (string) $secret_key : (string) $publishable_key;
		$headers = array(
			'apikey'       => $key,
			'Content-Type' => 'application/json',
		);

		// Supabase publishable/secret keys are not JWTs and should not be sent as Bearer tokens.
		if ( '' !== $key && false !== strpos( $key, '.' ) ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		return $headers;
	}

	/**
	 * Get HTTP headers for API requests
	 *
	 * @param bool $use_service_role Whether to use service role key for admin operations.
	 * @return array HTTP headers.
	 */
	protected function get_headers( $use_service_role = false ) {
		return self::build_supabase_headers( $this->api_key, $this->service_role_key, $use_service_role );
	}

	/**
	 * Normalize Supabase key names for runtime use.
	 *
	 * @param array $connection_config Raw connection configuration.
	 * @return array Normalized configuration.
	 */
	protected function normalize_connection_config( $connection_config ) {
		$publishable_key = isset( $connection_config['publishable_key'] ) ? $connection_config['publishable_key'] : '';
		$secret_key      = isset( $connection_config['secret_key'] ) ? $connection_config['secret_key'] : '';

		$connection_config['publishable_key'] = $publishable_key;
		$connection_config['secret_key']      = $secret_key;

		return $connection_config;
	}

	/**
	 * Execute Supabase RPC function
	 *
	 * @param string $function_name RPC function name.
	 * @param array  $params        Function parameters.
	 * @return mixed Function result or WP_Error on failure.
	 */
	public function execute_rpc( $function_name, $params = array() ) {
		$response = wp_safe_remote_post(
			$this->project_url . '/rest/v1/rpc/' . $function_name,
			array(
				'headers' => $this->get_headers( true ),
				'body'    => wp_json_encode( $params ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			return new WP_Error( 'rpc_error', "RPC call failed with status {$status_code}: {$body}" );
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Sanitize date for PostgreSQL
	 *
	 * Handles WordPress "0000-00-00 00:00:00" dates and ensures valid ISO format.
	 *
	 * @param string $date Date string.
	 * @return string|null Valid ISO date or null.
	 */
	protected function sanitize_date( $date ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date || '0000-00-00' === $date ) {
			return null;
		}
		return $date;
	}

	/**
	 * Prepare post data for Supabase format
	 *
	 * @param int           $post_id   Post ID.
	 * @param array|WP_Post $post_data Post data (array or WP_Post object).
	 * @return array Formatted post data.
	 */
	protected function prepare_post_data( $post_id, $post_data ) {
		// Convert WP_Post object to array if needed.
		if ( is_object( $post_data ) && $post_data instanceof WP_Post ) {
			$post_data = (array) $post_data;
		}

		return array(
			'id'           => $post_id,
			'post_title'   => isset( $post_data['post_title'] ) ? $post_data['post_title'] : '',
			'post_content' => isset( $post_data['post_content'] ) ? $post_data['post_content'] : '',
			'post_excerpt' => isset( $post_data['post_excerpt'] ) ? $post_data['post_excerpt'] : '',
			'post_type'    => isset( $post_data['post_type'] ) ? $post_data['post_type'] : 'post',
			'post_status'  => isset( $post_data['post_status'] ) ? $post_data['post_status'] : 'publish',
			'post_author'  => isset( $post_data['post_author'] ) ? $post_data['post_author'] : 0,
			'post_date'    => isset( $post_data['post_date'] ) ? $this->sanitize_date( $post_data['post_date'] ) : gmdate( 'Y-m-d H:i:s' ),
			'meta'         => isset( $post_data['meta'] ) ? wp_json_encode( $post_data['meta'] ) : '{}',
			'taxonomies'   => isset( $post_data['taxonomies'] ) ? wp_json_encode( $post_data['taxonomies'] ) : '{}',
			'synced_at'    => gmdate( 'c' ),
		);
	}

	/**
	 * Get last error message
	 *
	 * @return string Last error message.
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Get connection status
	 *
	 * @return bool Whether connected.
	 */
	public function is_connected() {
		return $this->connected;
	}

	/**
	 * Get connection instance (for compatibility)
	 *
	 * @return null HTTP-based provider doesn't have connection object.
	 */
	public function get_connection() {
		return null; // HTTP-based provider doesn't maintain persistent connection.
	}

	/**
	 * Upsert term (for GG_Data_DB compatibility)
	 *
	 * @param object $term            Term object from WordPress.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name (unused for HTTP).
	 * @return bool Success status.
	 */
	public function upsert_term( $term, $site_id = 1, $connection_name = null ) {
		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_terms';

		$data = array(
			'term_id'    => $term->term_id,
			'name'       => $term->name,
			'slug'       => $term->slug,
			'term_group' => isset( $term->term_group ) ? $term->term_group : 0,
		);

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => isset( $this->config['connect_timeout'] ) ? $this->config['connect_timeout'] : 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			$this->last_error = "HTTP $code: $body";
			return false;
		}

		return true;
	}

	/**
	 * Bulk upsert terms (batch operation)
	 *
	 * @param array  $terms           Array of term objects.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and details.
	 */
	public function bulk_upsert_terms( $terms, $site_id = 1, $connection_name = null ) {
		if ( empty( $terms ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No terms to upsert',
			);
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_terms';

		// Convert term objects to data arrays.
		$data = array();
		foreach ( $terms as $term ) {
			$data[] = array(
				'term_id'    => $term->term_id,
				'name'       => $term->name,
				'slug'       => $term->slug,
				'term_group' => isset( $term->term_group ) ? $term->term_group : 0,
			);
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			$this->last_error = "HTTP $code: $body";
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		return array(
			'success' => true,
			'count'   => count( $terms ),
		);
	}

	/**
	 * Bulk upsert term taxonomies (batch operation)
	 *
	 * @param array  $taxonomies      Array of taxonomy data arrays.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and details.
	 */
	public function bulk_upsert_term_taxonomies( $taxonomies, $site_id = 1, $connection_name = null ) {
		if ( empty( $taxonomies ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No taxonomies to upsert',
			);
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_term_taxonomy';

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $taxonomies ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			$this->last_error = "HTTP $code: $body";
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		return array(
			'success' => true,
			'count'   => count( $taxonomies ),
		);
	}

	/**
	 * Upsert term taxonomy (for GG_Data_DB compatibility)
	 *
	 * @param int    $term_taxonomy_id Term taxonomy ID.
	 * @param int    $term_id          Term ID.
	 * @param string $taxonomy         Taxonomy name.
	 * @param string $description      Description.
	 * @param int    $parent_term_id   Parent term ID.
	 * @param int    $count            Term count.
	 * @param int    $site_id          Site ID.
	 * @param string $connection_name  Connection name (unused for HTTP).
	 * @return bool Success status.
	 */
	public function upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description = '', $parent_term_id = 0, $count = 0, $site_id = 1, $connection_name = null ) {
		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_term_taxonomy';

		$data = array(
			'term_taxonomy_id' => $term_taxonomy_id,
			'term_id'          => $term_id,
			'taxonomy'         => $taxonomy,
			'description'      => $description,
			'parent'           => $parent_term_id,
			'count'            => $count,
		);

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => isset( $this->config['connect_timeout'] ) ? $this->config['connect_timeout'] : 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 201 ), true );
	}

	/**
	 * Bulk upsert term relationships (batch operation)
	 *
	 * @param array  $relationships   Array of relationship data arrays.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and details.
	 */
	public function bulk_upsert_term_relationships( $relationships, $site_id = 1, $connection_name = null ) {
		if ( empty( $relationships ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No relationships to upsert',
			);
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_term_relationships';

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $relationships ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			$this->last_error = "HTTP $code: $body";
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		return array(
			'success' => true,
			'count'   => count( $relationships ),
		);
	}

	/**
	 * Bulk upsert posts
	 *
	 * @param array  $posts           Array of post objects.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name (unused for HTTP).
	 * @return array Result with success status and count.
	 */
	public function bulk_upsert_posts( $posts, $site_id = 1, $connection_name = null ) {
		if ( empty( $this->config ) || empty( $this->config['project_url'] ) ) {
			return array(
				'success' => false,
				'error'   => 'PostgREST provider config not initialized',
			);
		}
		if ( empty( $posts ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No posts to upsert',
			);
		}

		// Convert post objects to data arrays.
		$data_array = array();
		foreach ( $posts as $post ) {
			$data_array[] = array(
				'id'                    => $post->ID,
				'post_author'           => (int) $post->post_author,
				'post_date'             => $this->sanitize_date( $post->post_date ),
				'post_date_gmt'         => $this->sanitize_date( $post->post_date_gmt ),
				'post_content'          => $post->post_content,
				'post_title'            => $post->post_title,
				'post_excerpt'          => $post->post_excerpt,
				'post_status'           => $post->post_status,
				'comment_status'        => $post->comment_status,
				'ping_status'           => $post->ping_status,
				'post_password'         => $post->post_password,
				'post_name'             => $post->post_name,
				'to_ping'               => $post->to_ping,
				'pinged'                => $post->pinged,
				'post_modified'         => $this->sanitize_date( $post->post_modified ),
				'post_modified_gmt'     => $this->sanitize_date( $post->post_modified_gmt ),
				'post_content_filtered' => $post->post_content_filtered,
				'post_parent'           => (int) $post->post_parent,
				'guid'                  => $post->guid,
				'menu_order'            => (int) $post->menu_order,
				'post_type'             => $post->post_type,
				'post_mime_type'        => $post->post_mime_type,
				'comment_count'         => (int) $post->comment_count,
			);
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_posts';

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data_array ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			$this->last_error = "HTTP $code: $body";
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		return array(
			'success' => true,
			'count'   => count( $posts ),
		);
	}

	/**
	 * Bulk upsert post meta
	 *
	 * @param array  $meta_records    Array of meta records with meta_id, post_id, meta_key, meta_value.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name (unused for HTTP).
	 * @return array Result with success status and count.
	 */
	public function bulk_upsert_postmeta( $meta_records, $site_id = 1, $connection_name = null ) {
		if ( empty( $meta_records ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No postmeta to upsert',
			);
		}

		/*
		 * Convert records to data arrays (no site_id in Supabase schema).
		 * Note: meta_key and meta_value usage here is safe - reading from PHP objects/arrays.
		 * Not performing database queries.
		 */
		$data_array = array();
		foreach ( $meta_records as $record ) {
			$data_array[] = array(
				'meta_id'    => isset( $record->meta_id ) ? (int) $record->meta_id : (int) $record['meta_id'],
				'post_id'    => isset( $record->post_id ) ? (int) $record->post_id : (int) $record['post_id'],
				'meta_key'   => isset( $record->meta_key ) ? $record->meta_key : $record['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Safe: Reading from object/array, not a query.
				'meta_value' => isset( $record->meta_value ) ? $record->meta_value : $record['meta_value'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Safe: Reading from object/array, not a query.
			);
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_postmeta';

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data_array ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			$this->last_error = "HTTP $code: $body";
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		return array(
			'success' => true,
			'count'   => count( $meta_records ),
		);
	}

	/**
	 * Upsert term relationship (for GG_Data_DB compatibility)
	 *
	 * @param int    $object_id        Object ID (post ID).
	 * @param int    $term_taxonomy_id Term taxonomy ID.
	 * @param int    $term_order        Term order.
	 * @param int    $site_id          Site ID.
	 * @param string $connection_name  Connection name (unused for HTTP).
	 * @return bool Success status.
	 */
	public function upsert_term_relationship( $object_id, $term_taxonomy_id, $term_order = 0, $site_id = 1, $connection_name = null ) {
		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_term_relationships';

		$data = array(
			'object_id'        => $object_id,
			'term_taxonomy_id' => $term_taxonomy_id,
			'term_order'       => $term_order,
		);

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => isset( $this->config['connect_timeout'] ) ? $this->config['connect_timeout'] : 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 201 ), true );
	}

	/**
	 * Upsert post (for GG_Data_DB compatibility)
	 *
	 * @param object $post            Post object from WordPress.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name (unused for HTTP).
	 * @return bool Success status.
	 */
	public function upsert_post( $post, $site_id = 1, $connection_name = null ) {
		// Convert post object to array for sync_post.
		$post_data = array(
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_author'  => $post->post_author,
			'post_date'    => $post->post_date,
		);

		$result = $this->sync_post( $post->ID, $post_data );
		return $result['success'];
	}

	/**
	 * Upsert post meta (for GG_Data_DB compatibility)
	 *
	 * @param int    $meta_id         Meta ID.
	 * @param int    $post_id         Post ID.
	 * @param string $meta_key        Meta key.
	 * @param mixed  $meta_value      Meta value.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name (unused for HTTP).
	 * @return bool Success status.
	 */
	public function upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $site_id = 1, $connection_name = null ) {
		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/wp_postmeta';

		/*
		 * Note: meta_key and meta_value usage here is safe - function parameters.
		 * Not performing database queries, just preparing API request data.
		 */
		$data = array(
			'meta_id'    => $meta_id,
			'post_id'    => $post_id,
			'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Safe: Function parameter, not a query.
			'meta_value' => is_scalar( $meta_value ) ? $meta_value : wp_json_encode( $meta_value ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Safe: Function parameter, not a query.
		);

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'resolution=merge-duplicates',
					)
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => isset( $this->config['connect_timeout'] ) ? $this->config['connect_timeout'] : 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 201 ), true );
	}

	/**
	 * Count records in a table
	 *
	 * @param string $table Table name.
	 * @param array  $where Optional WHERE conditions (key => value).
	 * @return array Result with 'success' and 'count' keys.
	 */
	public function count_records( $table, $where = array() ) {
		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

		// Build query string for filters.
		if ( ! empty( $where ) ) {
			$query_params = array();
			foreach ( $where as $key => $value ) {
				$values = is_array( $value ) ? $value : array( $value );

				foreach ( $values as $v ) {
					// Check if value starts with a known PostgREST operator.
					$is_operator = preg_match( '/^(eq|gt|gte|lt|lte|neq|like|ilike|match|imatch|in|is|fts|plfts|phfts|wfts|cs|cd|ov|sl|sr|nxr|nxl|adj|not)\./', $v );

					if ( $is_operator ) {
						$query_params[] = $key . '=' . $v; // Assume caller handled encoding.
					} else {
						$query_params[] = $key . '=' . rawurlencode( $v );
					}
				}
			}
			$url .= '?' . implode( '&', $query_params );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ),
					array(
						'Prefer' => 'count=exact',
					)
				),
				'timeout' => isset( $this->config['connect_timeout'] ) ? $this->config['connect_timeout'] : 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $code, array( 200, 206 ), true ) ) {
			return array(
				'success' => false,
				'error'   => "HTTP $code",
			);
		}

		// Extract count from Content-Range header.
		$headers       = wp_remote_retrieve_headers( $response );
		$content_range = isset( $headers['content-range'] ) ? $headers['content-range'] : '';

		// Content-Range format: "0-999/1234" where 1234 is the total count.
		if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
			$count = (int) $matches[1];
		} else {
			$count = 0;
		}

		return array(
			'success' => true,
			'count'   => $count,
		);
	}

	/**
	 * Get list of IDs from a table (for orphan detection)
	 *
	 * @param string $table      Table name.
	 * @param int    $limit      Limit.
	 * @param int    $offset     Offset.
	 * @param array  $conditions Optional WHERE conditions.
	 * @param string $select     Columns to select (default: 'id').
	 * @return array|WP_Error Array of IDs or error.
	 */
	public function get_ids( $table, $limit = 100, $offset = 0, $conditions = array(), $select = 'id' ) {
		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

		// Build query params.
		$params = array(
			'select' => $select,
			'order'  => 'id' === $select ? 'id.asc' : ( false === strpos( $select, ',' ) ? $select . '.asc' : null ),
			'limit'  => $limit,
			'offset' => $offset,
		);

		// Add conditions.
		foreach ( $conditions as $key => $value ) {
			$params[ $key ] = $value;
		}

		$url = add_query_arg( $params, $url );

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => $this->get_headers( true ), // Use service role to see all records.
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_response', 'Invalid response from Supabase' );
		}

		// Extract IDs if selecting only 'id'.
		if ( 'id' === $select ) {
			$ids = array_column( $data, 'id' );
			return $ids;
		}

		return $data;
	}

	/**
	 * Select records from a table
	 *
	 * Generic select method for querying data from Supabase tables.
	 *
	 * @since 2.0.0
	 * @param string      $table      Table name.
	 * @param array       $conditions WHERE conditions as key => value pairs. Values are matched with 'eq.' operator.
	 * @param string|null $order      ORDER BY clause (e.g., 'chunk_index.asc').
	 * @param string      $select     Columns to select (default: '*').
	 * @param int|null    $limit      LIMIT clause.
	 * @return array|false Array of rows or false on error.
	 */
	public function select( $table, $conditions = array(), $order = null, $select = '*', $limit = null ) {
		if ( empty( $this->config['project_url'] ) ) {
			return false;
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

		// Build query params.
		$params = array(
			'select' => $select,
		);

		// Add order if provided (convert 'column ASC' to 'column.asc' format).
		if ( $order ) {
			// Convert SQL-style "column ASC" to PostgREST "column.asc".
			$order           = preg_replace( '/\s+ASC$/i', '.asc', $order );
			$order           = preg_replace( '/\s+DESC$/i', '.desc', $order );
			$params['order'] = $order;
		}

		// Add limit if provided.
		if ( null !== $limit ) {
			$params['limit'] = intval( $limit );
		}

		// Add conditions using PostgREST filter format.
		foreach ( $conditions as $key => $value ) {
			$params[ $key ] = 'eq.' . $value;
		}

		$url = add_query_arg( $params, $url );

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => $this->get_headers( true ), // Use service role to see all records.
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return false;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Delete records from a table
	 *
	 * Generic delete method for removing data from Supabase tables.
	 *
	 * @since 2.0.0
	 * @param string $table      Table name.
	 * @param array  $conditions WHERE conditions as key => value pairs. Values are matched with 'eq.' operator.
	 * @return bool True on success, false on error.
	 */
	public function delete( $table, $conditions = array() ) {
		if ( empty( $this->config['project_url'] ) ) {
			return false;
		}

		if ( empty( $conditions ) ) {
			return false;
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

		// Build query params for conditions using PostgREST filter format.
		$params = array();
		foreach ( $conditions as $key => $value ) {
			$params[ $key ] = 'eq.' . $value;
		}

		$url = add_query_arg( $params, $url );

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'DELETE',
				'headers' => $this->get_headers( true ), // Use service role for delete operations.
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 200 or 204 indicates success for DELETE.
		if ( 200 !== $code && 204 !== $code ) {
			return false;
		}

		return true;
	}

	/**
	 * Insert a record into a table
	 *
	 * Generic insert method for adding data to Supabase tables.
	 *
	 * @since 2.0.0
	 * @param string $table Table name.
	 * @param array  $data  Data to insert as key => value pairs.
	 * @return bool True on success, false on error.
	 */
	public function insert( $table, $data ) {
		if ( empty( $this->config['project_url'] ) ) {
			return false;
		}

		if ( empty( $data ) ) {
			return false;
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ), // Use service role for insert operations.
					array(
						'Prefer' => 'return=minimal', // Don't return the inserted row.
					)
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 201 indicates successful creation.
		if ( 201 !== $code && 200 !== $code ) {
			return false;
		}

		return true;
	}

	/**
	 * Bulk insert multiple records into a table
	 *
	 * PostgREST supports bulk inserts by sending an array of objects.
	 * This is MUCH faster than individual inserts (single HTTP request vs N requests).
	 *
	 * @since 2.0.0
	 * @param string $table Table name.
	 * @param array  $rows  Array of data arrays to insert.
	 * @return bool|int Number of rows inserted on success, false on error.
	 */
	public function bulk_insert( $table, $rows ) {
		if ( empty( $this->config['project_url'] ) ) {
			return false;
		}

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return 0;
		}

		$url = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->get_headers( true ), // Use service role for insert operations.
					array(
						'Prefer' => 'return=minimal', // Don't return the inserted rows.
					)
				),
				'body'    => wp_json_encode( array_values( $rows ) ), // Ensure numeric array.
				'timeout' => 60, // Longer timeout for bulk operations.
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 201 indicates successful creation.
		if ( 201 !== $code && 200 !== $code ) {
			return false;
		}

		return count( $rows );
	}

	/**
	 * Delete post meta
	 *
	 * @param int $meta_id Meta ID.
	 * @return array Result.
	 */
	public function delete_post_meta( $meta_id ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			$response = wp_remote_request(
				$this->rest_url . '/wp_postmeta?meta_id=eq.' . intval( $meta_id ),
				array(
					'method'  => 'DELETE',
					'headers' => $this->get_headers( true ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message = 'Supabase delete error: ' . $response->get_error_message();
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'meta_id' => $meta_id,
						'error'   => $response->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 204 !== $status_code ) {
				$body    = wp_remote_retrieve_body( $response );
				$message = "Supabase delete failed with status {$status_code}: {$body}";
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'meta_id'     => $meta_id,
						'status_code' => $status_code,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			return array(
				'success' => true,
				'meta_id' => $meta_id,
				'message' => 'Post meta deleted successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Supabase delete exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'meta_id'   => $meta_id,
					'exception' => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Delete term
	 *
	 * @param int $term_id Term ID.
	 * @return array Result.
	 */
	public function delete_term( $term_id ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			$response = wp_remote_request(
				$this->rest_url . '/wp_terms?term_id=eq.' . intval( $term_id ),
				array(
					'method'  => 'DELETE',
					'headers' => $this->get_headers( true ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message = 'Supabase delete error: ' . $response->get_error_message();
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'term_id' => $term_id,
						'error'   => $response->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 204 !== $status_code ) {
				$body    = wp_remote_retrieve_body( $response );
				$message = "Supabase delete failed with status {$status_code}: {$body}";
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'term_id'     => $term_id,
						'status_code' => $status_code,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			return array(
				'success' => true,
				'term_id' => $term_id,
				'message' => 'Term deleted successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Supabase delete exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'term_id'   => $term_id,
					'exception' => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Delete term taxonomy
	 *
	 * @param int $term_taxonomy_id Term Taxonomy ID.
	 * @return array Result.
	 */
	public function delete_term_taxonomy( $term_taxonomy_id ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			$response = wp_remote_request(
				$this->rest_url . '/wp_term_taxonomy?term_taxonomy_id=eq.' . intval( $term_taxonomy_id ),
				array(
					'method'  => 'DELETE',
					'headers' => $this->get_headers( true ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message = 'Supabase delete error: ' . $response->get_error_message();
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'term_taxonomy_id' => $term_taxonomy_id,
						'error'            => $response->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 204 !== $status_code ) {
				$body    = wp_remote_retrieve_body( $response );
				$message = "Supabase delete failed with status {$status_code}: {$body}";
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'term_taxonomy_id' => $term_taxonomy_id,
						'status_code'      => $status_code,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			return array(
				'success'          => true,
				'term_taxonomy_id' => $term_taxonomy_id,
				'message'          => 'Term taxonomy deleted successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Supabase delete exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'term_taxonomy_id' => $term_taxonomy_id,
					'exception'        => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Delete term relationship
	 *
	 * @param int $object_id        Object ID.
	 * @param int $term_taxonomy_id Term Taxonomy ID.
	 * @return array Result.
	 */
	public function delete_term_relationship( $object_id, $term_taxonomy_id ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		try {
			$url = $this->rest_url . '/wp_term_relationships?object_id=eq.' . intval( $object_id ) . '&term_taxonomy_id=eq.' . intval( $term_taxonomy_id );

			$response = wp_remote_request(
				$url,
				array(
					'method'  => 'DELETE',
					'headers' => $this->get_headers( true ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$message = 'Supabase delete error: ' . $response->get_error_message();
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'object_id'        => $object_id,
						'term_taxonomy_id' => $term_taxonomy_id,
						'error'            => $response->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code && 204 !== $status_code ) {
				$body    = wp_remote_retrieve_body( $response );
				$message = "Supabase delete failed with status {$status_code}: {$body}";
				$this->logger->log(
					$message,
					'error',
					'connection',
					$this->connection_name,
					array(
						'object_id'        => $object_id,
						'term_taxonomy_id' => $term_taxonomy_id,
						'status_code'      => $status_code,
					)
				);
				return array(
					'success' => false,
					'message' => $message,
				);
			}

			return array(
				'success' => true,
				'message' => 'Term relationship deleted successfully',
			);

		} catch ( Exception $e ) {
			$message = 'Supabase delete exception: ' . $e->getMessage();
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'object_id'        => $object_id,
					'term_taxonomy_id' => $term_taxonomy_id,
					'exception'        => $e->getMessage(),
				)
			);
			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Bulk delete records by ID
	 *
	 * Automatically chunks large requests to avoid URL length limits.
	 *
	 * @param string $table     Table name.
	 * @param array  $ids       Array of IDs to delete.
	 * @param string $id_column Column name for ID (default: 'id').
	 * @return array Result.
	 */
	public function delete_ids( $table, $ids, $id_column = 'id' ) {
		if ( ! $this->connected ) {
			return array(
				'success' => false,
				'message' => 'Not connected to Supabase',
			);
		}

		if ( empty( $ids ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No IDs to delete',
			);
		}

		// Chunk size to keep URL length safe (approx 200 IDs * 7 chars = 1400 chars).
		$chunk_size    = 200;
		$chunks        = array_chunk( $ids, $chunk_size );
		$total_deleted = 0;
		$errors        = array();

		foreach ( $chunks as $chunk ) {
			try {
				// Format IDs for PostgREST "in" filter: id=in.(1,2,3).
				$ids_string = implode( ',', array_map( 'intval', $chunk ) );
				$url        = trailingslashit( $this->config['project_url'] ) . 'rest/v1/' . $table;

				// Manually append query arg to avoid encoding parentheses in PostgREST filter.
				// add_query_arg() encodes '(' and ')' which breaks PostgREST array syntax.
				$url .= '?' . $id_column . '=in.(' . $ids_string . ')';

				$this->logger->log(
					sprintf( 'DELETE %s - Column: %s, IDs: %s', $table, $id_column, implode( ', ', array_slice( $chunk, 0, 5 ) ) . ( count( $chunk ) > 5 ? '...' : '' ) ),
					'debug',
					'sync',
					$this->connection_name,
					array(
						'table'      => $table,
						'id_column'  => $id_column,
						'chunk_size' => count( $chunk ),
					)
				);

				// Get headers and add Prefer header to get affected row count.
				$headers           = $this->get_headers( true ); // Use service role.
				$headers['Prefer'] = 'count=exact';

				$response = wp_remote_request(
					$url,
					array(
						'method'  => 'DELETE',
						'headers' => $headers,
						'timeout' => 30,
					)
				);

				if ( is_wp_error( $response ) ) {
					$this->logger->log(
						sprintf( 'DELETE error: %s', $response->get_error_message() ),
						'error',
						'sync',
						$this->connection_name,
						array(
							'table' => $table,
							'error' => $response->get_error_message(),
						)
					);
					$errors[] = $response->get_error_message();
					continue;
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $status_code && 204 !== $status_code ) {
					$body = wp_remote_retrieve_body( $response );
					$this->logger->log(
						sprintf( 'DELETE failed - Status: %d', $status_code ),
						'error',
						'sync',
						$this->connection_name,
						array(
							'table'       => $table,
							'status_code' => $status_code,
							'body'        => $body,
						)
					);
					$errors[] = "Status {$status_code}: {$body}";
					continue;
				}

				// Parse Content-Range header to get actual deleted count.
				// Format: "0-9/10" means 10 rows affected (start-end/total).
				// For DELETE, this tells us how many rows were actually deleted.
				$headers       = wp_remote_retrieve_headers( $response );
				$content_range = isset( $headers['content-range'] ) ? $headers['content-range'] : '';
				$chunk_deleted = 0;

				if ( preg_match( '/\/(\d+|\*)$/', $content_range, $matches ) ) {
					if ( '*' !== $matches[1] ) {
						$chunk_deleted = (int) $matches[1];
					} else {
						// PostgREST returns "*" when count is unknown - fall back to chunk size.
						$chunk_deleted = count( $chunk );
					}
				} else {
					// No Content-Range header - PostgREST might not support it for DELETE.
					// Fall back to assuming all were deleted (old behavior).
					$chunk_deleted = count( $chunk );
				}

				$this->logger->log(
					sprintf( 'DELETE completed - Deleted: %d rows', $chunk_deleted ),
					'debug',
					'sync',
					$this->connection_name,
					array(
						'table'         => $table,
						'status_code'   => $status_code,
						'content_range' => $content_range ? $content_range : 'none',
						'deleted'       => $chunk_deleted,
					)
				);

				$total_deleted += $chunk_deleted;

			} catch ( Exception $e ) {
				$errors[] = $e->getMessage();
			}
		}

		if ( ! empty( $errors ) ) {
			$message = 'Partial delete failure: ' . implode( '; ', array_unique( $errors ) );
			$this->logger->log(
				$message,
				'error',
				'connection',
				$this->connection_name,
				array(
					'table'         => $table,
					'errors'        => $errors,
					'deleted_count' => $total_deleted,
				)
			);

			// If we deleted some, return success with warning.
			if ( $total_deleted > 0 ) {
				return array(
					'success' => true,
					'count'   => $total_deleted,
					'message' => "Deleted {$total_deleted} records (some failed)",
					'warning' => $message,
				);
			}

			return array(
				'success' => false,
				'message' => $message,
			);
		}

		return array(
			'success' => true,
			'count'   => $total_deleted,
			'message' => 'Records deleted successfully',
		);
	}
}
