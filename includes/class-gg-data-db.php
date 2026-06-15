<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Database connection handler for PostgreSQL
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'GG_Data_DB' ) ) {

	/**
	 * Database connection class (Wrapper for Provider Architecture)
	 *
	 * This class maintains backward compatibility while delegating operations
	 */
	class GG_Data_DB {

		/**
		 * PDO connection instance (maintained for backward compatibility)
		 *
		 * @var PDO
		 */
		protected $conn = null;

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
		 * Current connection name
		 *
		 * @var string|null
		 */
		protected $current_connection = null;

		/**
		 * Last database error
		 *
		 * @var string
		 */
		protected $last_error = '';

		/**
		 * Database provider instance (new architecture)
		 *
		 * @var GG_Data_PostgreSQL_Provider|null
		 */
		protected $provider = null;

		/**
		 * Class constructor
		 */
		public function __construct() {
			$this->logger           = new GG_Data_Logger();
			$this->settings_manager = new GG_Data_Settings_Manager();

			// Load provider architecture.
			if ( ! class_exists( 'GG_Data_Provider_Factory' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'providers/class-gg-provider-factory.php';
			}
		}

		/**
		 * Set default connection name for this instance
		 *
		 * @param string $connection_name The connection name to use as default.
		 */
		public function set_default_connection( $connection_name ) {
			$this->current_connection = $connection_name;
		}

		/**
		 * Get the current provider instance
		 *
		 * @return GG_Data_DB_Provider|null
		 */
		public function get_provider() {
			return $this->provider;
		}

		/**
		 * Get last database error
		 *
		 * @return string Last error message
		 */
		public function get_last_error() {
			if ( $this->provider ) {
				return $this->provider->get_last_error();
			}
			return $this->last_error;
		}

		/**
		 * Check if a connection is PostgREST type
		 *
		 * @param string $connection_name Connection name to check.
		 * @return bool True if PostgREST, false otherwise.
		 */
		public function is_postgrest_connection( $connection_name = null ) {
			if ( empty( $connection_name ) && ! empty( $this->current_connection ) ) {
				$connection_name = $this->current_connection;
			}

			if ( empty( $connection_name ) ) {
				return false;
			}

			$connection = $this->settings_manager->get_connection( $connection_name );
			if ( ! $connection ) {
				return false;
			}

			// Check for 'type' key (used in connection config).
			$provider_type = isset( $connection['type'] ) ? $connection['type'] : '';
			return 'postgrest' === $provider_type;
		}       /**
				 * Get PDO connection to PostgreSQL
				 *
				 * Uses provider architecture while maintaining backward compatibility.
				 *
				 * @since 1.0.0
				 *
				 * @param string $connection_name Connection name to use. If not provided, uses first active connection.
				 * @return PDO|false PDO connection or false on failure (including PostgREST connections which don't use PDO).
				 */
		public function get_connection( $connection_name = null ) {
			// Use default connection if no name provided and we have one set.
			if ( empty( $connection_name ) && ! empty( $this->current_connection ) ) {
				$connection_name = $this->current_connection;
			}

			// If still no connection name, try to get the first active connection.
			if ( empty( $connection_name ) ) {
				$active_connections = $this->settings_manager->get_active_connections();
				if ( empty( $active_connections ) ) {
					// No connections configured - silently return false (not an error condition).
					return false;
				}
				$connection_name = array_key_first( $active_connections );
			}

			// If requesting different connection, close current and reconnect.
			if ( null !== $this->conn && $connection_name !== $this->current_connection ) {
				$this->close_connection();
			}

			// Return cached connection if same connection requested.
			if ( null !== $this->conn && $connection_name === $this->current_connection ) {
				return $this->conn;
			}

			try {
				// Get connection details from Settings Manager.
				$connection = $this->settings_manager->get_connection( $connection_name );

				if ( ! $connection ) {
					$this->logger->log( "Connection '$connection_name' not found in settings", 'error', 'connection', $connection_name );
					$this->last_error = "Connection '$connection_name' not found in settings";
					return false;
				}

				// Get provider type from connection config (default to postgresql for backward compatibility).
				$provider_type = isset( $connection['type'] ) ? $connection['type'] : 'postgresql';

				// Validate required connection parameters based on provider type.
				if ( 'postgrest' === $provider_type ) {
					$required_fields = array( 'project_url', 'publishable_key', 'secret_key' );
				} else {
					$required_fields = array( 'host', 'port', 'database', 'username', 'password' );
				}

				foreach ( $required_fields as $field ) {
					if ( empty( $connection[ $field ] ) ) {
						$this->logger->log( "Connection '$connection_name' missing required field: $field", 'error', 'connection', $connection_name );
						$this->last_error = "Connection '$connection_name' missing required field: $field";
						return false;
					}
				}

				// Create provider instance using factory.
				$this->provider = GG_Data_Provider_Factory::create_provider( $provider_type, $connection, $connection_name );
				if ( ! $this->provider ) {
					$this->logger->log( "Failed to create PostgreSQL provider for connection: $connection_name", 'error', 'connection', $connection_name );
					$this->last_error = "Failed to create provider for connection: $connection_name";
					return false;
				}

				// Connect using provider.
				$result = $this->provider->connect( $connection );

				if ( ! $result['success'] ) {
					$this->last_error = $result['message'];
					$this->logger->log( "Provider connection failed: {$result['message']}", 'error', 'connection', $connection_name );
					return false;
				}

				// For PostgREST providers, they don't use PDO - they use HTTP API.
				// Return false since callers expect a PDO object for query().
				if ( 'postgrest' === $provider_type ) {
					$this->current_connection = $connection_name;
					return false;
				}

				// Get PDO connection from provider (for PostgreSQL direct connections).
				$pdo = $this->provider->get_connection();

				if ( ! $pdo ) {
					$this->logger->log( 'Provider connected but returned no PDO instance', 'error', 'connection', $connection_name );
					$this->last_error = 'Provider connected but returned no PDO instance';
					return false;
				}

				// Cache the connection for backward compatibility.
				$this->conn               = $pdo;
				$this->current_connection = $connection_name;

				// Log at debug level - successful connections are routine.
				$this->logger->log( "Successfully connected to PostgreSQL using provider for connection '$connection_name'", 'debug', 'connection', $connection_name );

				return $pdo;
			} catch ( Exception $e ) {
				$this->logger->log( "Exception getting connection '$connection_name': " . $e->getMessage(), 'error', 'connection', $connection_name );
				return false;
			}
		}

		/**
		 * Close database connection
		 *
		 * Delegates to provider architecture while maintaining backward compatibility.
		 */
		public function close_connection() {
			if ( $this->provider ) {
				$this->provider->disconnect();
				$this->provider = null;
			}

			$this->conn               = null;
			$this->current_connection = null;
		}

		/**
		 * Insert or update a post in PostgreSQL
		 *
		 * Uses provider architecture for post upsert operations.
		 *
		 * @param WP_Post|array $post_data        Post data or WP_Post object.
		 * @param int           $blog_id          Blog ID for multisite support.
		 * @param string|null   $connection_name  Connection name to use.
		 * @return bool Success or failure.
		 */
		public function upsert_post( $post_data, $blog_id = 1, $connection_name = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for backward compatibility with caller signature.
			if ( ! $this->provider ) {
				$this->last_error = 'Failed to upsert post: provider is not available';
				$this->logger->log( $this->last_error, 'error', 'connection', $connection_name );
				return false;
			}

			$post_id = is_object( $post_data ) ? $post_data->ID : ( isset( $post_data['ID'] ) ? $post_data['ID'] : 0 );
			$result  = $this->provider->sync_post( $post_id, $post_data );

			if ( ! is_array( $result ) || empty( $result['success'] ) ) {
				$this->last_error = is_array( $result ) && ! empty( $result['message'] )
					? $result['message']
					: 'Provider sync_post failed';
				return false;
			}

			return true;
		}

		/**
		 * Delete a post from PostgreSQL
		 *
		 * @param int         $post_id         Post ID.
		 * @param int         $site_id         Site ID.
		 * @param string|null $connection_name Connection name to use (optional).
		 * @return bool Success or failure.
		 */
		public function delete_post( $post_id, $site_id, $connection_name = null ) {
			$conn = $this->get_connection( $connection_name );
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}           try {
				// Begin transaction.
				$conn->beginTransaction();

				// Delete post meta first (due to foreign key constraints).
				$postmeta_table = $this->get_table_name( 'postmeta' );
				$sql_meta       = "DELETE FROM $postmeta_table WHERE post_id = :post_id";
				$stmt_meta      = $conn->prepare( $sql_meta );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_meta->bindParam( ':post_id', $post_id, PDO::PARAM_INT );
				$stmt_meta->execute();

				// Delete vector embeddings (if vector functionality is enabled).
				$sql_vector  = 'DELETE FROM vector_store WHERE post_id = :post_id AND site_id = :site_id';
				$stmt_vector = $conn->prepare( $sql_vector );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_vector->bindParam( ':post_id', $post_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_vector->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
				$stmt_vector->execute();

				// Delete term relationships.
				$term_relationships_table = $this->get_table_name( 'term_relationships' );
				$sql_term_rel             = "DELETE FROM $term_relationships_table WHERE object_id = :post_id";
				$stmt_term_rel            = $conn->prepare( $sql_term_rel );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_term_rel->bindParam( ':post_id', $post_id, PDO::PARAM_INT );
				$stmt_term_rel->execute();

				// Delete post.
				$posts_table = $this->get_table_name( 'posts' );
				$sql_post    = "DELETE FROM $posts_table WHERE ID = :post_id AND site_id = :site_id";
				$stmt_post   = $conn->prepare( $sql_post );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_post->bindParam( ':post_id', $post_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_post->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
				$stmt_post->execute();              // Commit transaction.
				$conn->commit();

				return true;
			} catch ( PDOException $e ) {
				// Rollback transaction on error.
				if ( $conn->inTransaction() ) {
					$conn->rollBack();
				}

				$error_message = 'Error deleting post: ' . $e->getMessage();
				$this->logger->log( $error_message, 'error', 'connection' );

				// Queue for retry if transient error.
				$retry_queue = new GG_Data_Retry_Queue();
				$retry_queue->queue_for_retry(
					'delete_post',
					$post_id,
					$error_message,
					array(
						'post_id' => $post_id,
						'site_id' => $site_id,
					)
				);

				return false;
			}
		}

		/**
		 * Insert or update post meta in PostgreSQL
		 *
		 * @param mixed       $meta_id_or_data Meta ID or meta data array.
		 * @param mixed       $post_id_or_site_id Post ID or site ID depending on first param.
		 * @param string      $meta_key Optional meta key if individual params used.
		 * @param mixed       $meta_value Optional meta value if individual params used.
		 * @param integer     $blog_id Optional blog ID if individual params used.
		 * @param string|null $connection_name Connection name to use (optional).
		 * @return bool Success or failure.
		 */
		public function upsert_post_meta( $meta_id_or_data, $post_id_or_site_id, $meta_key = null, $meta_value = null, $blog_id = 1, $connection_name = null ) {
			$conn = $this->get_connection( $connection_name );
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			// Determine if we're using array format or individual parameters.
			$meta_data = array();
			$site_id   = 1;

			if ( is_array( $meta_id_or_data ) ) {
				// Array format.
				GG_Data_Debug::log( 'Using meta data array directly', $this->logger );
				$meta_data = $meta_id_or_data;
				$site_id   = $post_id_or_site_id;
			} else {
				// Individual parameters.
				GG_Data_Debug::log( 'Converting individual meta parameters to array', $this->logger );

				/*
				 * Note: meta_key and meta_value usage here is safe and performant.
				 * These are function parameters being assigned to an array for processing.
				 * No database query is performed at this point - this is just data preparation.
				 */
				$meta_data = array(
					'meta_id'    => $meta_id_or_data,
					'post_id'    => $post_id_or_site_id,
					'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Safe: Function parameter assignment, not a query.
					'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Safe: Function parameter assignment, not a query.
				);
				$site_id   = $blog_id;
			}

			try {
				// Handle serialized data.
				$meta_value = $meta_data['meta_value'];

				// Skip problematic meta keys that commonly cause errors.
				$problematic_meta_keys = array(
					'_wpml_word_count',
					'_yst_prominent_words_version',
					'_edit_lock',
					'_edit_last',
				);

				if ( in_array( $meta_data['meta_key'], $problematic_meta_keys, true ) ) {
					// For problematic meta keys, use a simple string representation.
					GG_Data_Debug::log( 'Using simple encoding for known problematic meta key: ' . $meta_data['meta_key'], $this->logger );
					$meta_value_json = wp_json_encode( is_string( $meta_value ) ? $meta_value : (string) $meta_value );
				} elseif ( is_serialized( $meta_value ) ) {
					// For serialized data, store as JSON representation of the serialized string.
					$meta_value_json = wp_json_encode( array( 'serialized' => $meta_value ) );
					GG_Data_Debug::log( 'Handling serialized meta value for key: ' . $meta_data['meta_key'], $this->logger );
				} elseif ( is_string( $meta_value ) && strpos( $meta_value, '{' ) !== false && strpos( $meta_value, '}' ) !== false ) {
					// Try to safely encode it.
					$meta_value_json = wp_json_encode( array( 'raw_data' => $meta_value ) );
					GG_Data_Debug::log( 'Handling potentially corrupted data for key: ' . $meta_data['meta_key'], $this->logger );
				} else {
					// Normal JSON encoding.
					$meta_value_json = wp_json_encode( $meta_value );
				}

				if ( false === $meta_value_json ) {
					$this->logger->log(
						'Error encoding meta value to JSON: ' .
						'type=' . gettype( $meta_value ) .
						', key=' . $meta_data['meta_key'] .
						', sample=' . ( is_string( $meta_value ) ? substr( $meta_value, 0, 50 ) : 'not string' ),
						'error',
						'connection'
					);

					// Fallback to string representation for problematic values.
					$meta_value_json = wp_json_encode( array( 'raw_string' => (string) $meta_value ) );
					if ( false === $meta_value_json ) {
						// Last resort fallback for extreme cases.
						$this->logger->log( 'Using last resort fallback for meta key: ' . $meta_data['meta_key'], 'warning', 'connection' );
						$meta_value_json = '{"unprocessable": true}';
					}
				}

				// Prepare SQL query.
				$postmeta_table = $this->get_table_name( 'postmeta' );
				$sql            = "INSERT INTO $postmeta_table (
				meta_id, post_id, meta_key, meta_value
			) VALUES (
				:meta_id, :post_id, :meta_key, :meta_value
			) ON CONFLICT (meta_id) DO UPDATE SET
				post_id = :post_id,
				meta_key = :meta_key,
				meta_value = :meta_value";
				$stmt           = $conn->prepare( $sql );

				// Bind parameters.
				// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
				$stmt->bindParam( ':meta_id', $meta_data['meta_id'], PDO::PARAM_INT );
				$stmt->bindParam( ':post_id', $meta_data['post_id'], PDO::PARAM_INT );
				// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
				$stmt->bindParam( ':meta_key', $meta_data['meta_key'] );
				$stmt->bindParam( ':meta_value', $meta_value_json );

				// Execute statement.
				$result = $stmt->execute();

				if ( false === $result ) {
					$error         = $stmt->errorInfo();
					$error_message = 'PDO statement execution failed for post meta: ' . wp_json_encode( $error );
					$this->logger->log( $error_message, 'error', 'connection' );

					// Queue for retry if transient error.
					$retry_queue = new GG_Data_Retry_Queue();
					$retry_queue->queue_for_retry( 'sync_meta', $meta_data['post_id'], $error_message, $meta_data );
				}

				return $result;
			} catch ( PDOException $e ) {
				$error_message = 'Error upserting post meta: ' . $e->getMessage();
				$this->logger->log( $error_message, 'error', 'connection' );

				// Queue for retry if transient error.
				$retry_queue = new GG_Data_Retry_Queue();
				$retry_queue->queue_for_retry( 'sync_meta', $meta_data['post_id'], $error_message, $meta_data );

				return false;
			} catch ( Exception $e ) {
				$error_message = 'General exception upserting post meta: ' . $e->getMessage();
				$this->logger->log( $error_message, 'error', 'connection' );

				// Queue for retry if transient error.
				$retry_queue = new GG_Data_Retry_Queue();
				$retry_queue->queue_for_retry( 'sync_meta', $meta_data['post_id'], $error_message, $meta_data );

				return false;
			}
		}

		/**
		 * Delete post meta from PostgreSQL
		 *
		 * @param int         $post_id         Post ID.
		 * @param string      $meta_key        Meta key.
		 * @param int         $site_id         Site ID.
		 * @param string|null $connection_name Connection name to use (optional).
		 * @return bool Success or failure.
		 */
		public function delete_post_meta( $post_id, $meta_key, $site_id, $connection_name = null ) {
			$conn = $this->get_connection( $connection_name );
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}           try {
				$postmeta_table = $this->get_table_name( 'postmeta' );
				$sql            = "DELETE FROM $postmeta_table WHERE post_id = :post_id AND meta_key = :meta_key";
				$stmt           = $conn->prepare( $sql );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':post_id', $post_id, PDO::PARAM_INT );
				$stmt->bindParam( ':meta_key', $meta_key );
				return $stmt->execute();
			} catch ( PDOException $e ) {
				$error_message = 'Error deleting post meta: ' . $e->getMessage();
				$this->logger->log( $error_message, 'error', 'connection' );

				// Queue for retry if transient error.
				$retry_queue = new GG_Data_Retry_Queue();
				$retry_queue->queue_for_retry(
					'delete_meta',
					$post_id,
					$error_message,
					array(
						'post_id'  => $post_id,
						'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Safe: Function parameter, not a query.
						'site_id'  => $site_id,
					)
				);

				return false;
			}
		}

		/**
		 * Get WordPress table name with correct prefix
		 *
		 * @param string $table_name Base table name without prefix.
		 * @return string Table name with correct prefix.
		 */
		public function get_table_name( $table_name ) {
			$table = GG_Data_Table_Prefix_Resolver::mirror_table_with_schema( $table_name, 'public' );

			GG_Data_Debug::log( 'Using canonical mirror table: ' . $table . ' for table: ' . $table_name, $this->logger );
			return $table;
		}

		/**
		 * Insert or update a term in PostgreSQL
		 *
		 * @param array|stdClass $term_data        Term data.
		 * @param int            $blog_id          Blog/site ID.
		 * @param string|null    $connection_name  Connection name to use (optional).
		 * @return bool Success or failure.
		 */
		public function upsert_term( $term_data, $blog_id = 1, $connection_name = null ) {
			$conn = $this->get_connection( $connection_name );
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			// Debug what we received.
			$this->logger->log( 'upsert_term received: ' . ( is_object( $term_data ) ? get_class( $term_data ) : gettype( $term_data ) ), 'debug', 'connection' );

			// Convert stdClass object to array if needed.
			$term_arr = array();
			if ( is_object( $term_data ) && ( $term_data instanceof stdClass ) ) {
				$this->logger->log( 'Converting stdClass object to array', 'debug', 'connection' );
				$term_arr            = get_object_vars( $term_data );
				$term_arr['site_id'] = $blog_id;
			} elseif ( is_array( $term_data ) ) {
				$this->logger->log( 'Using term data array directly', 'debug', 'connection' );
				$term_arr = $term_data;
				if ( ! isset( $term_arr['site_id'] ) ) {
					$term_arr['site_id'] = $blog_id;
				}
			} else {
				$this->logger->log( 'Invalid term data type: ' . gettype( $term_data ), 'error', 'connection' );
				return false;
			}

			try {
				// Prepare SQL query.
				$terms_table = $this->get_table_name( 'terms' );
				$sql         = "INSERT INTO $terms_table (
				term_id, site_id, name, slug, term_group
			) VALUES (
				:term_id, :site_id, :name, :slug, :term_group
			) ON CONFLICT (term_id) DO UPDATE SET
				site_id = :site_id,
				name = :name,
				slug = :slug,
				term_group = :term_group";

				$stmt = $conn->prepare( $sql );

				// Bind parameters correctly using $term_arr.
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':term_id', $term_arr['term_id'], PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':site_id', $term_arr['site_id'], PDO::PARAM_INT );
				$stmt->bindParam( ':name', $term_arr['name'] );
				$stmt->bindParam( ':slug', $term_arr['slug'] );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':term_group', $term_arr['term_group'], PDO::PARAM_INT );             // Execute statement.
				return $stmt->execute();
			} catch ( PDOException $e ) {
				$this->logger->log( 'Error upserting term: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Delete a term from PostgreSQL
		 *
		 * @param int $term_id Term ID.
		 * @param int $site_id Site ID.
		 * @return bool Success or failure.
		 */
		public function delete_term( $term_id, $site_id ) {
			$conn = $this->get_connection();
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			try {
				// Begin transaction.
				$conn->beginTransaction();

				// Delete term relationships first.
				$term_relationships_table = $this->get_table_name( 'term_relationships' );
				$sql_rel                  = "DELETE FROM $term_relationships_table WHERE term_taxonomy_id IN (
				SELECT tt.term_taxonomy_id FROM " . $this->get_table_name( 'term_taxonomy' ) . ' tt WHERE tt.term_id = :term_id
			)';
				$stmt_rel                 = $conn->prepare( $sql_rel );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_rel->bindParam( ':term_id', $term_id, PDO::PARAM_INT );
				$stmt_rel->execute();

				// Delete term.
				$terms_table = $this->get_table_name( 'terms' );
				$sql_term    = "DELETE FROM $terms_table WHERE term_id = :term_id AND site_id = :site_id";
				$stmt_term   = $conn->prepare( $sql_term );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_term->bindParam( ':term_id', $term_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt_term->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
				$stmt_term->execute();              // Commit transaction.
				$conn->commit();

				return true;
			} catch ( PDOException $e ) {
				// Rollback transaction on error.
				if ( $conn->inTransaction() ) {
					$conn->rollBack();
				}

				$this->logger->log( 'Error deleting term: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Delete term relationships by term_taxonomy_id from PostgreSQL
		 *
		 * @param int $term_taxonomy_id Term taxonomy ID.
		 * @param int $site_id Site ID.
		 * @return bool Success or failure.
		 */
		public function delete_term_relationships_by_taxonomy_id( $term_taxonomy_id, $site_id = 1 ) {
			$conn = $this->get_connection();
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			try {
				$term_relationships_table = $this->get_table_name( 'term_relationships' );
				$sql                      = "DELETE FROM $term_relationships_table WHERE term_taxonomy_id = :tt_id AND site_id = :site_id";
				$stmt                     = $conn->prepare( $sql );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':tt_id', $term_taxonomy_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
				$stmt->execute();
				return true;
			} catch ( PDOException $e ) {
				$this->logger->log( 'Error deleting term relationships by taxonomy ID: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Delete term taxonomy from PostgreSQL
		 *
		 * @param int $term_taxonomy_id Term taxonomy ID.
		 * @param int $site_id Site ID.
		 * @return bool Success or failure.
		 */
		public function delete_term_taxonomy( $term_taxonomy_id, $site_id = 1 ) {
			$conn = $this->get_connection();
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			try {
				$term_taxonomy_table = $this->get_table_name( 'term_taxonomy' );
				$sql                 = "DELETE FROM $term_taxonomy_table WHERE term_taxonomy_id = :tt_id AND site_id = :site_id";
				$stmt                = $conn->prepare( $sql );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':tt_id', $term_taxonomy_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
				$stmt->execute();
				return true;
			} catch ( PDOException $e ) {
				$this->logger->log( 'Error deleting term taxonomy: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Delete term from PostgreSQL if it's not used by any taxonomies
		 *
		 * @param int $term_id Term ID.
		 * @param int $site_id Site ID.
		 * @return bool Success or failure.
		 */
		public function delete_term_if_unused( $term_id, $site_id = 1 ) {
			$conn = $this->get_connection();
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			try {
				// Check if term is still referenced by any taxonomies.
				$term_taxonomy_table = $this->get_table_name( 'term_taxonomy' );
				$check_sql           = "SELECT COUNT(*) FROM $term_taxonomy_table WHERE term_id = :term_id AND site_id = :site_id";
				$check_stmt          = $conn->prepare( $check_sql );
				// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
				$check_stmt->bindParam( ':term_id', $term_id, PDO::PARAM_INT );
				$check_stmt->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
				// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
				$check_stmt->execute();

				$count = $check_stmt->fetchColumn();

				// Only delete if not referenced by any taxonomies.
				if ( 0 === (int) $count ) {
					$terms_table = $this->get_table_name( 'terms' );
					$delete_sql  = "DELETE FROM $terms_table WHERE term_id = :term_id AND site_id = :site_id";
					$delete_stmt = $conn->prepare( $delete_sql );
					// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
					$delete_stmt->bindParam( ':term_id', $term_id, PDO::PARAM_INT );
					$delete_stmt->bindParam( ':site_id', $site_id, PDO::PARAM_INT );
					// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
					$delete_stmt->execute();

					$this->logger->log( "Deleted unused term #{$term_id}", 'debug', 'connection' );
				} else {
					$this->logger->log( "Term #{$term_id} still referenced by {$count} taxonomies, not deleting", 'debug', 'connection' );
				}

				return true;
			} catch ( PDOException $e ) {
				$this->logger->log( 'Error checking/deleting unused term: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Insert or update a term relationship in PostgreSQL
		 *
		 * @param array|int   $relationship_data Relationship data or object_id.
		 * @param int         $term_taxonomy_id Optional term_taxonomy_id if first param is object_id.
		 * @param int         $term_order       Optional term_order if first param is object_id.
		 * @param int         $site_id          Site ID.
		 * @param string|null $connection_name  Connection name to use (optional).
		 * @return bool Success or failure.
		 */
		public function upsert_term_relationship( $relationship_data, $term_taxonomy_id = null, $term_order = 0, $site_id = 1, $connection_name = null ) {
			$conn = $this->get_connection( $connection_name );
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			$this->logger->log( 'upsert_term_relationship called with: ' . gettype( $relationship_data ), 'debug', 'connection' );

			// Handle both array and individual parameters.
			$object_id = 0;
			$tt_id     = 0;
			$order     = 0;

			if ( is_array( $relationship_data ) ) {
				$object_id = $relationship_data['object_id'];
				$tt_id     = $relationship_data['term_taxonomy_id'];
				$order     = $relationship_data['term_order'];
			} else {
				// Individual parameters mode.
				$object_id = $relationship_data; // First param is object_id.
				$tt_id     = $term_taxonomy_id;
				$order     = $term_order;
			}

			try {
				// First, check if term_taxonomy_id exists in term_taxonomy table.
				$term_taxonomy_table = $this->get_table_name( 'term_taxonomy' );
				$check_sql           = "SELECT COUNT(*) FROM $term_taxonomy_table WHERE term_taxonomy_id = :tt_id";
				$check_stmt          = $conn->prepare( $check_sql );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$check_stmt->bindParam( ':tt_id', $tt_id, PDO::PARAM_INT );
				$check_stmt->execute();             // If term_taxonomy_id doesn't exist, skip this relationship.
				if ( (int) $check_stmt->fetchColumn() === 0 ) {
					$this->logger->log( "Skipping term relationship: term_taxonomy_id {$tt_id} doesn't exist in taxonomy table", 'notice', 'connection' );
					return false;
				}

				// Term taxonomy exists, proceed with insertion.
				$term_relationships_table = $this->get_table_name( 'term_relationships' );
				$sql                      = "INSERT INTO $term_relationships_table (
				object_id, term_taxonomy_id, term_order
			) VALUES (
				:object_id, :term_taxonomy_id, :term_order
			) ON CONFLICT (object_id, term_taxonomy_id) DO UPDATE SET
				term_order = :term_order";

				$stmt = $conn->prepare( $sql );

				// Bind parameters.
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':object_id', $object_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':term_taxonomy_id', $tt_id, PDO::PARAM_INT );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':term_order', $order, PDO::PARAM_INT );              // Execute statement.
				return $stmt->execute();
			} catch ( PDOException $e ) {
				$this->logger->log( 'Error upserting term relationship: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Delete term relationships for a post and taxonomy
		 *
		 * @param int    $post_id  Post ID.
		 * @param string $taxonomy Taxonomy.
		 * @param int    $site_id  Site ID.
		 * @return bool Success or failure.
		 */
		public function delete_term_relationships( $post_id, $taxonomy, $site_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for backward compatibility with caller signature.
			$conn = $this->get_connection();
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			try {
				$term_relationships_table = $this->get_table_name( 'term_relationships' );
				$sql                      = "DELETE FROM $term_relationships_table WHERE object_id = :post_id";
				$stmt                     = $conn->prepare( $sql );
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$stmt->bindParam( ':post_id', $post_id, PDO::PARAM_INT );
				return $stmt->execute();
			} catch ( PDOException $e ) {
				$this->logger->log( 'Error deleting term relationships: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Insert or update a term taxonomy in PostgreSQL
		 *
		 * @param int         $term_taxonomy_id Term taxonomy ID.
		 * @param int         $term_id Term ID.
		 * @param string      $taxonomy Taxonomy name.
		 * @param string      $description Term description.
		 * @param int         $parent_term Parent term ID.
		 * @param int         $count Term count.
		 * @param int         $blog_id Blog/site ID.
		 * @param string|null $connection_name Connection name to use (optional).
		 * @return bool Success or failure.
		 */
		public function upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description, $parent_term, $count, $blog_id = 1, $connection_name = null ) {
			$conn = $this->get_connection( $connection_name );
			if ( ! $conn || ! is_object( $conn ) ) {
				$this->logger->log( 'Failed to get database connection. Connection: ' . gettype( $conn ), 'error', 'connection' );
				return false;
			}

			try {
				// Prepare SQL query.
				$term_taxonomy_table = $this->get_table_name( 'term_taxonomy' );
				$sql                 = "INSERT INTO $term_taxonomy_table (
					term_taxonomy_id, site_id, term_id, taxonomy, description, parent, count
				) VALUES (
					:term_taxonomy_id, :site_id, :term_id, :taxonomy, :description, :parent, :count
				) ON CONFLICT (term_taxonomy_id) DO UPDATE SET
					site_id = :site_id,
					term_id = :term_id,
					taxonomy = :taxonomy,
					description = :description,
					parent = :parent,
					count = :count";

				// Execute query with parameters.
				$stmt = $conn->prepare( $sql );
				$stmt->execute(
					array(
						':term_taxonomy_id' => $term_taxonomy_id,
						':site_id'          => $blog_id,
						':term_id'          => $term_id,
						':taxonomy'         => $taxonomy,
						':description'      => $description,
						':parent'           => $parent_term,
						':count'            => $count,
					)
				);
							return true;
			} catch ( Exception $e ) {
				$this->logger->log( 'Error upserting term taxonomy: ' . $e->getMessage(), 'error', 'connection' );
				return false;
			}
		}

		/**
		 * Get the current sync status from the options table
		 *
		 * @return array The current sync status data
		 */
		public function get_sync_status() {
			return get_option(
				'gg_data_sync_status',
				array(
					'status' => 'not_started',
					'errors' => 0,
				)
			);
		}

		/**
		 * Reset the error count in the sync status
		 *
		 * @return boolean True if the reset was successful
		 */
		public function reset_sync_errors() {
			$sync_status = $this->get_sync_status();
					// Reset error count.
			if ( isset( $sync_status['errors'] ) ) {
				$sync_status['errors'] = 0;
				$this->logger->log(
					'Reset sync error count to zero',
					'info',
					'connection'
				);
				return update_option( 'gg_data_sync_status', $sync_status );
			}

			return false;
		}

		/**
		 * Sync term relationships for a specific post
		 *
		 * @param int    $post_id        Post ID to sync relationships for.
		 * @param string $connection_name Connection name to use.
		 * @param string $sync_context   Context: 'realtime' or 'manual'.
		 * @return bool Success or failure.
		 * @throws Exception If an error occurs during manual sync context.
		 */
		public function sync_post_term_relationships( $post_id, $connection_name = null, $sync_context = 'realtime' ) {
			global $wpdb;

			try {
				// Get all term relationships for this post from WordPress.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all term relationships for a specific post for sync operation
				$relationships = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT object_id, term_taxonomy_id, term_order 
						FROM {$wpdb->term_relationships} 
						WHERE object_id = %d",
						$post_id
					)
				);

				if ( empty( $relationships ) ) {
					// No relationships to sync.
					$this->logger->log( "No term relationships found for post {$post_id}", 'debug', 'connection' );
					return true;
				}

				$success_count = 0;
				$failed_count  = 0;

				// First, sync all terms and taxonomies for these relationships.
				foreach ( $relationships as $rel ) {
					// Get term and taxonomy data from WordPress.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch term and taxonomy data by term_taxonomy_id for relationship sync
					$term_taxonomy = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, t.slug, t.term_group
							FROM {$wpdb->term_taxonomy} tt
							INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
							WHERE tt.term_taxonomy_id = %d",
							$rel->term_taxonomy_id
						)
					);
					if ( ! $term_taxonomy ) {
						continue;
					}

					// Sync the term first.
					$term_result = $this->upsert_term(
						array(
							'term_id'    => $term_taxonomy->term_id,
							'name'       => $term_taxonomy->name,
							'slug'       => $term_taxonomy->slug,
							'term_group' => $term_taxonomy->term_group,
						),
						1, // blog_id.
						$connection_name
					);

					// Then sync the taxonomy.
					$this->upsert_term_taxonomy(
						$term_taxonomy->term_taxonomy_id,
						$term_taxonomy->term_id,
						$term_taxonomy->taxonomy,
						$term_taxonomy->description,
						$term_taxonomy->parent,
						$term_taxonomy->count,
						1, // blog_id.
						$connection_name
					);
				}

				// Now sync the relationships.
				foreach ( $relationships as $rel ) {
					$result = $this->upsert_term_relationship(
						array(
							'object_id'        => $rel->object_id,
							'term_taxonomy_id' => $rel->term_taxonomy_id,
							'term_order'       => $rel->term_order,
						),
						null, // term_taxonomy_id (not used when passing array).
						0,    // term_order (not used when passing array).
						1,    // site_id.
						$connection_name
					);

					if ( $result ) {
						++$success_count;
					} else {
						++$failed_count;
						$error_msg = "Failed to sync term relationship for post {$post_id}, term_taxonomy_id {$rel->term_taxonomy_id}";

						if ( 'realtime' === $sync_context ) {
							// Silent logging for real-time sync.
							$this->logger->log( $error_msg, 'warning', 'connection' );
						} else {
							// More visible error for manual sync.
							$this->logger->log( $error_msg, 'error', 'connection' );
						}
					}
				}

					$total_relationships = count( $relationships );
					$success_msg         = "Synced {$success_count}/{$total_relationships} term relationships for post {$post_id}";

				if ( $failed_count > 0 ) {
					$success_msg .= " ({$failed_count} failed)";
				}

					$this->logger->log( $success_msg, $failed_count > 0 ? 'warning' : 'info', 'connection' );

					// Return true if at least some relationships synced successfully.
					return 0 < $success_count;

			} catch ( Exception $e ) {
				$error_msg = "Exception syncing term relationships for post {$post_id}: " . $e->getMessage();

				if ( 'realtime' === $sync_context ) {
					// Silent logging for real-time sync - don't disrupt user workflow.
					$this->logger->log( $error_msg, 'warning', 'connection' );
					return false; // Fail silently.
				} else {
					// More visible error for manual sync.
					$this->logger->log( $error_msg, 'error', 'connection' );
					throw $e; // Let calling code handle the exception.
				}
			}
		}
	}
}
