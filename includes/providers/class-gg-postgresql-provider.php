<?php
/**
 * PostgreSQL Database Provider Implementation
 *
 * Implements the GG_Data_DB_Provider interface for PostgreSQL databases.
 * Provides connection management, data synchronization, vector operations,
 * and schema management for PostgreSQL with pgvector extension.
 *
 * @package Gregius_Data
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
 * PostgreSQL provider implementation
 *
 * @since 1.0.0
 */
class GG_Data_PostgreSQL_Provider implements GG_Data_DB_Provider {

	/**
	 * PDO connection instance
	 *
	 * @var PDO|null
	 */
	protected $conn = null;

	/**
	 * Connection name (identifier)
	 *
	 * @var string|null
	 */
	protected $connection_name = null;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	protected $logger;

	/**
	 * Last database error
	 *
	 * @var string
	 */
	protected $last_error = '';

	/**
	 * Database name (from connection config)
	 *
	 * @var string
	 */
	protected $database_name = '';

	/**
	 * Constructor
	 *
	 * @param array       $connection_config Optional connection configuration.
	 * @param string|null $connection_name   Optional connection identifier.
	 */
	public function __construct( $connection_config = array(), $connection_name = null ) {
		$this->logger          = new GG_Data_Logger();
		$this->connection_name = $connection_name;
	}

	/**
	 * Establish connection to PostgreSQL database
	 *
	 * @param array $connection_config Connection configuration array.
	 * @return array {
	 *     Connection result
	 *     @type bool   $success Whether connection succeeded
	 *     @type string $message Human-readable message
	 *     @type PDO    $connection PDO connection instance (on success)
	 * }
	 */
	public function connect( $connection_config ) {
		try {
			// Validate required connection parameters.
			$required_fields = array( 'host', 'port', 'database', 'username', 'password' );
			foreach ( $required_fields as $field ) {
				if ( empty( $connection_config[ $field ] ) ) {
					$message = "Missing required connection field: {$field}";
					$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'field' => $field ) );
					return array(
						'success' => false,
						'message' => $message,
					);
				}
			}

			// Extract connection details.
			$host     = $connection_config['host'];
			$port     = $connection_config['port'];
			$dbname   = $connection_config['database'];
			$user     = $connection_config['username'];
			$password = $connection_config['password'];
			$timeout  = isset( $connection_config['timeout'] ) ? (int) $connection_config['timeout'] : 30;

			// Store database name.
			$this->database_name = $dbname;

			// Build DSN with timeout.
			$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};connect_timeout={$timeout}";

			// Add SSL mode if specified.
			if ( ! empty( $connection_config['ssl_mode'] ) && 'disable' !== $connection_config['ssl_mode'] ) {
				$dsn .= ';sslmode=' . $connection_config['ssl_mode'];
			}

			// Create PDO instance.
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$pdo = new PDO( $dsn, $user, $password );

			// Set error mode to exceptions.
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

			// Cache the connection.
			$this->conn = $pdo;

			$message = sprintf( 'Successfully connected to PostgreSQL database: %s (%s)', $dbname, $this->connection_name );
			// Log at debug level - successful connections are routine, not noteworthy.
			$this->logger->log( $message, 'debug', 'connection', $this->connection_name, array( 'database' => $dbname ) );

			return array(
				'success'    => true,
				'message'    => $message,
				'connection' => $pdo,
			);

		} catch ( PDOException $e ) {
			$message          = 'PostgreSQL connection error: ' . $e->getMessage();
			$this->last_error = $message;
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
			);
		} catch ( Exception $e ) {
			$message          = 'Unexpected connection error: ' . $e->getMessage();
			$this->last_error = $message;
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Close database connection
	 *
	 * @return array {
	 *     Disconnection result
	 *     @type bool   $success Always true
	 *     @type string $message Human-readable message
	 * }
	 */
	public function disconnect() {
		$this->conn = null;

		$message = 'PostgreSQL connection closed';
		$this->logger->log( $message, 'info', 'connection', $this->connection_name );

		return array(
			'success' => true,
			'message' => $message,
		);
	}

	/**
	 * Test database connection without persisting it
	 *
	 * @param array $connection_config Connection configuration array.
	 * @return array {
	 *     Test result
	 *     @type bool   $success Whether connection test succeeded
	 *     @type string $message Human-readable message
	 *     @type string $version PostgreSQL version (on success)
	 * }
	 */
	public function test_connection( $connection_config ) {
		try {
			// Validate required fields.
			$required_fields = array( 'host', 'port', 'database', 'username', 'password' );
			foreach ( $required_fields as $field ) {
				if ( empty( $connection_config[ $field ] ) ) {
					return array(
						'success' => false,
						'message' => "Missing required field: {$field}",
					);
				}
			}

			// Extract connection details.
			$host     = $connection_config['host'];
			$port     = $connection_config['port'];
			$dbname   = $connection_config['database'];
			$user     = $connection_config['username'];
			$password = $connection_config['password'];
			$timeout  = isset( $connection_config['timeout'] ) ? (int) $connection_config['timeout'] : 10;

			// Build DSN.
			$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};connect_timeout={$timeout}";

			// Add SSL mode if specified.
			if ( ! empty( $connection_config['ssl_mode'] ) && 'disable' !== $connection_config['ssl_mode'] ) {
				$dsn .= ';sslmode=' . $connection_config['ssl_mode'];
			}

			// Create temporary PDO connection.
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$test_conn = new PDO( $dsn, $user, $password );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$test_conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

			// Get PostgreSQL version.
			$version_query = $test_conn->query( 'SELECT version()' );
			$version       = $version_query->fetchColumn();

			// Check for required extensions (vector, pg_trgm).
			$ext_query = $test_conn->query( "SELECT count(*) FROM pg_extension WHERE extname IN ('vector', 'pg_trgm')" );
			$ext_count = $ext_query->fetchColumn();

			// Close test connection.
			$test_conn = null;

			if ( $ext_count < 2 ) {
				return array(
					'success' => false,
					'message' => 'Connection successful, but required extensions (vector, pg_trgm) are missing. Please install them.',
					'version' => $version,
				);
			}

			$this->logger->log(
				"PostgreSQL connection test successful: {$dbname}",
				'info',
				'connection',
				$this->connection_name,
				array(
					'database' => $dbname,
					'version'  => $version,
				)
			);

			return array(
				'success' => true,
				'message' => 'Connection test successful',
				'version' => $version,
			);

		} catch ( PDOException $e ) {
			$message = 'Connection test failed: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Synchronize a WordPress post to PostgreSQL
	 *
	 * @param int   $post_id   WordPress post ID.
	 * @param array $post_data Post data array or WP_Post object.
	 * @return array {
	 *     Sync result
	 *     @type bool   $success Whether sync succeeded
	 *     @type string $message Human-readable message
	 *     @type int    $post_id Synced post ID
	 * }
	 */
	public function sync_post( $post_id, $post_data ) {
		// Require active connection.
		if ( ! $this->conn ) {
			$message = 'No active database connection for sync_post';
			$this->logger->log( $message, 'error', 'connection', $this->connection_name );
			return array(
				'success' => false,
				'message' => $message,
			);
		}

		try {
			// Get blog ID for multisite support.
			$blog_id = get_current_blog_id();

			// Convert WP_Post object to array if needed.
			$post_arr = array();
			if ( is_object( $post_data ) ) {
				// Handle WP_Post object or stdClass with post properties.
				$post_arr = array(
					'ID'                => isset( $post_data->ID ) ? $post_data->ID : 0,
					'post_author'       => isset( $post_data->post_author ) ? $post_data->post_author : 1,
					'post_date'         => isset( $post_data->post_date ) ? $post_data->post_date : '',
					'post_date_gmt'     => isset( $post_data->post_date_gmt ) ? $post_data->post_date_gmt : '',
					'post_content'      => isset( $post_data->post_content ) ? $post_data->post_content : '',
					'post_title'        => isset( $post_data->post_title ) ? $post_data->post_title : '',
					'post_excerpt'      => isset( $post_data->post_excerpt ) ? $post_data->post_excerpt : '',
					'post_status'       => isset( $post_data->post_status ) ? $post_data->post_status : '',
					'post_name'         => isset( $post_data->post_name ) ? $post_data->post_name : '',
					'post_modified'     => isset( $post_data->post_modified ) ? $post_data->post_modified : '',
					'post_modified_gmt' => isset( $post_data->post_modified_gmt ) ? $post_data->post_modified_gmt : '',
					'post_type'         => isset( $post_data->post_type ) ? $post_data->post_type : '',
					'guid'              => isset( $post_data->guid ) ? $post_data->guid : '',
					'site_id'           => $blog_id,
				);
			} elseif ( is_array( $post_data ) ) {
				$post_arr = $post_data;
				if ( ! isset( $post_arr['site_id'] ) ) {
					$post_arr['site_id'] = $blog_id;
				}
			} else {
				return array(
					'success' => false,
					'message' => 'Invalid post data type: ' . gettype( $post_data ),
				);
			}

			// Validate required fields.
			$required_fields = array( 'ID', 'post_author', 'post_date', 'post_content', 'post_title', 'post_status', 'post_name', 'post_type', 'guid' );
			foreach ( $required_fields as $field ) {
				if ( ! isset( $post_arr[ $field ] ) ) {
					if ( 'post_name' === $field ) {
						$post_arr['post_name'] = sanitize_title( $post_arr['post_title'] );
					} else {
						$post_arr[ $field ] = 'ID' === $field ? 0 : ( 'post_author' === $field ? 1 : '' );
					}
				}
			}

			// Ensure date fields exist.
			if ( ! isset( $post_arr['post_date_gmt'] ) && isset( $post_arr['post_date'] ) ) {
				$post_arr['post_date_gmt'] = $post_arr['post_date'];
			}
			if ( ! isset( $post_arr['post_modified'] ) && isset( $post_arr['post_date'] ) ) {
				$post_arr['post_modified'] = $post_arr['post_date'];
			}
			if ( ! isset( $post_arr['post_modified_gmt'] ) && isset( $post_arr['post_modified'] ) ) {
				$post_arr['post_modified_gmt'] = $post_arr['post_modified'];
			}
			if ( ! isset( $post_arr['post_excerpt'] ) ) {
				$post_arr['post_excerpt'] = '';
			}

			// Convert MySQL zero dates to valid PostgreSQL dates.
			$zero_date    = '0000-00-00 00:00:00';
			$default_date = '1970-01-01 00:00:00';

			foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $date_field ) {
				if ( isset( $post_arr[ $date_field ] ) && ( $post_arr[ $date_field ] === $zero_date || empty( $post_arr[ $date_field ] ) ) ) {
					$post_arr[ $date_field ] = $default_date;
				}
			}

			// Get table name.
			$posts_table = $this->get_table_name( 'posts' );

			// Build base columns (always present in v1.0.0).
			$columns = array(
				'id',
				'site_id',
				'post_author',
				'post_date',
				'post_date_gmt',
				'post_content',
				'post_title',
				'post_excerpt',
				'post_status',
				'post_name',
				'post_modified',
				'post_modified_gmt',
				'post_type',
				'guid',
				'synced_at',
			);

			// Build parameter map for base columns.
			$params = array(
				':id'                => $post_arr['ID'],
				':site_id'           => $post_arr['site_id'],
				':post_author'       => $post_arr['post_author'],
				':post_date'         => $post_arr['post_date'],
				':post_date_gmt'     => $post_arr['post_date_gmt'],
				':post_content'      => $post_arr['post_content'],
				':post_title'        => $post_arr['post_title'],
				':post_excerpt'      => $post_arr['post_excerpt'],
				':post_status'       => $post_arr['post_status'],
				':post_name'         => $post_arr['post_name'],
				':post_modified'     => $post_arr['post_modified'],
				':post_modified_gmt' => $post_arr['post_modified_gmt'],
				':post_type'         => $post_arr['post_type'],
				':guid'              => $post_arr['guid'],
			);

			// Future: Add optional columns based on schema capabilities.
			// Example for v1.0.1+:
			// Build dynamic SQL based on available columns.
			$placeholders     = array();
			$conflict_updates = array();
			foreach ( $columns as $col ) {
				if ( 'synced_at' === $col ) {
					$placeholders[]     = 'NOW()';
					$conflict_updates[] = 'synced_at = NOW()';
				} else {
					$placeholders[]     = ':' . $col;
					$conflict_updates[] = "{$col} = :{$col}";
				}
			}

			$sql = sprintf(
				"INSERT INTO {$posts_table} (%s) VALUES (%s) ON CONFLICT (id) DO UPDATE SET %s",
				implode( ', ', $columns ),
				implode( ', ', $placeholders ),
				implode( ', ', $conflict_updates )
			);

			$stmt = $this->conn->prepare( $sql );

			// Execute.
			$result = $stmt->execute( $params );

			if ( ! $result ) {
				$error = $stmt->errorInfo();
				return array(
					'success' => false,
					'message' => 'Failed to sync post: ' . $error[2],
				);
			}

			$this->logger->log( "Successfully synced post ID: {$post_arr['ID']}", 'info', 'connection', $this->connection_name, array( 'post_id' => $post_arr['ID'] ) );

			return array(
				'success' => true,
				'message' => 'Post synced successfully',
				'post_id' => $post_arr['ID'],
			);

		} catch ( PDOException $e ) {
			$message = 'PostgreSQL sync error: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Delete a post from PostgreSQL
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array {
	 *     Deletion result
	 *     @type bool   $success Whether deletion succeeded
	 *     @type string $message Human-readable message
	 *     @type int    $post_id Deleted post ID
	 * }
	 */
	public function delete_post( $post_id ) {
		// Require active connection.
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			$site_id = get_current_blog_id();

			// Begin transaction.
			$this->conn->beginTransaction();

			// Delete post meta first (foreign key constraints).
			$postmeta_table = $this->get_table_name( 'postmeta' );
			$sql_meta       = "DELETE FROM {$postmeta_table} WHERE post_id = :post_id";
			$stmt_meta      = $this->conn->prepare( $sql_meta );
			$stmt_meta->execute( array( ':post_id' => $post_id ) );

			// Delete vector embeddings.
			$vector_table = $this->get_table_name( 'vector_store' );
			$sql_vector   = "DELETE FROM {$vector_table} WHERE post_id = :post_id AND site_id = :site_id";
			$stmt_vector  = $this->conn->prepare( $sql_vector );
			$stmt_vector->execute(
				array(
					':post_id' => $post_id,
					':site_id' => $site_id,
				)
			);

			// Delete term relationships.
			$term_rel_table = $this->get_table_name( 'term_relationships' );
			$sql_term_rel   = "DELETE FROM {$term_rel_table} WHERE object_id = :post_id";
			$stmt_term_rel  = $this->conn->prepare( $sql_term_rel );
			$stmt_term_rel->execute( array( ':post_id' => $post_id ) );

			// Delete post.
			$posts_table = $this->get_table_name( 'posts' );
			$sql_post    = "DELETE FROM {$posts_table} WHERE id = :post_id AND site_id = :site_id";
			$stmt_post   = $this->conn->prepare( $sql_post );
			$stmt_post->execute(
				array(
					':post_id' => $post_id,
					':site_id' => $site_id,
				)
			);

			// Commit transaction.
			$this->conn->commit();

			$this->logger->log( "Successfully deleted post ID: {$post_id}", 'info', 'connection', $this->connection_name, array( 'post_id' => $post_id ) );

			return array(
				'success' => true,
				'message' => 'Post deleted successfully',
				'post_id' => $post_id,
			);

		} catch ( PDOException $e ) {
			// Rollback on error.
			if ( $this->conn->inTransaction() ) {
				$this->conn->rollBack();
			}

			$message = 'Error deleting post: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

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
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			$table = $this->get_table_name( 'term_relationships' );
			$sql   = "DELETE FROM $table WHERE object_id = :object_id AND term_taxonomy_id = :term_taxonomy_id";

			$stmt = $this->conn->prepare( $sql );
			// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$stmt->bindValue( ':object_id', $object_id, PDO::PARAM_INT );
			$stmt->bindValue( ':term_taxonomy_id', $term_taxonomy_id, PDO::PARAM_INT );
			// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO

			$stmt->execute();

			return array(
				'success' => true,
				'message' => 'Relationship deleted',
			);
		} catch ( PDOException $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Generate vector embeddings for a post
	 *
	 * @param int   $post_id          WordPress post ID.
	 * @param array $embedding_config Embedding configuration.
	 * @return array {
	 *     Generation result
	 *     @type bool   $success Whether generation succeeded
	 *     @type string $message Human-readable message
	 *     @type int    $post_id Post ID
	 *     @type int    $dimensions Vector dimensions (on success)
	 * }
	 */
	public function generate_vectors( $post_id, $embedding_config = array() ) {
		// Require active connection.
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			// Use TF-IDF 300 Embeddings class.
			if ( ! class_exists( 'GG_Data_TFIDF_300_Embeddings' ) ) {
				require_once GG_DATA_PLUGIN_DIR . 'includes/vectors/class-gg-data-tfidf-300-embeddings.php';
			}

			$embeddings = new GG_Data_TFIDF_300_Embeddings( $this->connection_name );

			// Note: The TF-IDF generator is batch-oriented because it relies on a global vocabulary.
			// Generating a single vector in isolation without checking vocabulary consistency is risky.
			// For now, we trigger a small batch process which will pick up this post if it needs vectors.
			$result = $embeddings->generate_vectors_batch( 10 );

			if ( $result['success'] ) {
				return array(
					'success'    => true,
					'message'    => 'Vectors generated successfully',
					'post_id'    => $post_id,
					'dimensions' => 300,
				);
			} else {
				return array(
					'success' => false,
					'message' => 'Failed to generate vectors: ' . ( isset( $result['message'] ) ? $result['message'] : 'Unknown error' ),
				);
			}
		} catch ( Exception $e ) {
			$message = 'Vector generation error: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Perform semantic search using vector embeddings
	 *
	 * @param string $query         Search query text.
	 * @param array  $search_config Search configuration.
	 * @return array {
	 *     Search result
	 *     @type bool   $success Whether search succeeded
	 *     @type string $message Human-readable message
	 *     @type array  $results Search results array (on success)
	 *     @type int    $total   Total results count (on success)
	 * }
	 */
	public function search( $query, $search_config = array() ) {
		// Require active connection.
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			// Default search configuration.
			$defaults = array(
				'limit'                => 10,
				'offset'               => 0,
				'post_types'           => array( 'post', 'page' ),
				'site_id'              => get_current_blog_id(),
				'language'             => 'english',
				'similarity_threshold' => 0.3,
				'enable_trigram'       => true,
				'enable_vector'        => false,
				'vector_table'         => 'wp_posts_tfidf_300',
				'vector_column'        => 'embedding',
			);
			$config   = wp_parse_args( $search_config, $defaults );

			// Prepare PostgreSQL array string for post_types (e.g., '{post,page}').
			$post_types_str = '{' . implode(
				',',
				array_map(
					function ( $t ) {
						return '"' . str_replace( '"', '\"', $t ) . '"';
					},
					$config['post_types']
				)
			) . '}';

		// Call search_native_orchestrate function directly.
		// Signature: search_native_orchestrate(search_text, post_types, limit_count, search_language, enable_trigram, similarity_threshold, enable_vector, vector_table, vector_column, rrf_k, precomputed_query_vector).
		$sql = 'SELECT * FROM search_native_orchestrate(
				:search_term, 
				:post_types, 
				:limit, 
				:language, 
				:enable_trigram, 
				:similarity_threshold, 
				:enable_vector, 
				:vector_table, 
				:vector_column
			)';

			$stmt = $this->conn->prepare( $sql );

			// Bind parameters.
			// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections.
			$stmt->bindValue( ':search_term', $query, PDO::PARAM_STR );
			$stmt->bindValue( ':post_types', $post_types_str, PDO::PARAM_STR );
			$stmt->bindValue( ':limit', $config['limit'], PDO::PARAM_INT );
			$stmt->bindValue( ':language', $config['language'], PDO::PARAM_STR );
			$stmt->bindValue( ':enable_trigram', $config['enable_trigram'] ? 'true' : 'false', PDO::PARAM_STR ); // Boolean as string for some PDO drivers/PG versions safety.
			$stmt->bindValue( ':similarity_threshold', $config['similarity_threshold'], PDO::PARAM_STR );
			$stmt->bindValue( ':enable_vector', $config['enable_vector'] ? 'true' : 'false', PDO::PARAM_STR );
			$stmt->bindValue( ':vector_table', $config['vector_table'], PDO::PARAM_STR );
			$stmt->bindValue( ':vector_column', $config['vector_column'], PDO::PARAM_STR );
			// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO

			$stmt->execute();

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$results = $stmt->fetchAll( PDO::FETCH_ASSOC );

			if ( is_array( $results ) ) {
				return array(
					'success' => true,
					'message' => 'Search completed successfully',
					'results' => $results,
					'total'   => count( $results ),
				);
			} else {
				return array(
					'success' => false,
					'message' => 'Search failed',
					'results' => array(),
					'total'   => 0,
				);
			}
		} catch ( Exception $e ) {
			$message = 'Search error: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
				'results' => array(),
				'total'   => 0,
			);
		}
	}

	/**
	 * Get current schema version from database
	 *
	 * @return array {
	 *     Schema version result
	 *     @type bool   $success Whether retrieval succeeded
	 *     @type string $message Human-readable message
	 *     @type string $version Schema version (on success)
	 * }
	 */
	public function get_schema_version() {
		try {
			// Use Schema Manager to get version (stored in MySQL settings).
			if ( ! class_exists( 'GG_Data_Schema_Manager' ) ) {
				require_once GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-schema-manager.php';
			}

			$schema_manager = new GG_Data_Schema_Manager();
			$version        = $schema_manager->get_schema_version( $this->connection_name );

			return array(
				'success' => true,
				'message' => 'Schema version retrieved',
				'version' => $version,
			);

		} catch ( Exception $e ) {
			$message = 'Error retrieving schema version: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
				'version' => '0.0.0',
			);
		}
	}

	/**
	 * Create or update database schema
	 *
	 * @param array $schema_config Schema configuration.
	 * @return array {
	 *     Schema creation result
	 *     @type bool   $success Whether creation succeeded
	 *     @type string $message Human-readable message
	 *     @type array  $tables  Created tables list (on success)
	 * }
	 */
	public function create_schema( $schema_config = array() ) {
		// Require active connection.
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			// Use Schema Manager.
			if ( ! class_exists( 'GG_Data_Schema_Manager' ) ) {
				require_once GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-schema-manager.php';
			}
			$schema_manager = new GG_Data_Schema_Manager();

			// Create schema.
			$result = $schema_manager->create_all_tables( $this->connection_name );

			if ( $result['success'] ) {
				$this->logger->log( 'PostgreSQL schema created successfully', 'info', 'connection', $this->connection_name );

				return array(
					'success' => true,
					'message' => 'Schema created successfully',
					'tables'  => array(
						'wp_posts',
						'wp_posts_clean',
						'wp_postmeta',
						'wp_terms',
						'wp_term_taxonomy',
						'wp_term_relationships',
						'vector_store',
						'schema_version',
					),
				);
			} else {
				return array(
					'success' => false,
					'message' => 'Schema creation failed: ' . $result['message'],
				);
			}
		} catch ( Exception $e ) {
			$message = 'Error creating schema: ' . $e->getMessage();
			$this->logger->log( $message, 'error', 'connection', $this->connection_name, array( 'exception' => $e->getMessage() ) );

			return array(
				'success' => false,
				'message' => $message,
			);
		}
	}

	/**
	 * Check connection health and extensions
	 *
	 * @return array Health status
	 */
	public function check_health() {
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			// Check extensions.
			$sql  = "SELECT extname FROM pg_extension WHERE extname IN ('vector', 'pg_trgm')";
			$stmt = $this->conn->query( $sql );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
			$extensions = $stmt->fetchAll( PDO::FETCH_COLUMN );

			$missing = array_diff( array( 'vector', 'pg_trgm' ), $extensions );

			if ( ! empty( $missing ) ) {
				return array(
					'success' => false,
					'message' => 'Missing extensions: ' . implode( ', ', $missing ),
				);
			}

			return array(
				'success' => true,
				'message' => 'Connection healthy',
			);
		} catch ( PDOException $e ) {
			return array(
				'success' => false,
				'message' => 'Health check failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Check schema health and vocabulary version
	 *
	 * @return array Schema health status
	 */
	public function check_schema_health() {
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		try {
			// Check vocabulary version in gg_meta.
			// We use a try-catch because gg_meta might not exist yet.
			$sql  = "SELECT value FROM gg_meta WHERE key = 'vocabulary_version'";
			$stmt = $this->conn->query( $sql );

			if ( $stmt ) {
				$version = $stmt->fetchColumn();
				if ( '1.0' === $version ) {
					return array(
						'success' => true,
						'message' => 'Schema and vocabulary are up to date',
					);
				} else {
					return array(
						'success' => false,
						'message' => 'Vocabulary version mismatch. Expected 1.0, found ' . ( $version ? $version : 'none' ),
					);
				}
			}

			return array(
				'success' => false,
				'message' => 'Could not read vocabulary version',
			);

		} catch ( PDOException $e ) {
			// If table doesn't exist, it's a schema issue.
			if ( strpos( $e->getMessage(), '42P01' ) !== false ) { // Undefined table.
				return array(
					'success' => false,
					'message' => 'Schema tables missing (gg_meta)',
				);
			}

			return array(
				'success' => false,
				'message' => 'Schema check failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Get WordPress table name with correct prefix
	 *
	 * @param string $table_name Base table name without prefix.
	 * @return string Full table name with schema and prefix
	 */
	protected function get_table_name( $table_name ) {
		return GG_Data_Table_Prefix_Resolver::mirror_table_with_schema( $table_name, 'public' );
	}

	/**
	 * Get last database error
	 *
	 * @return string Last error message
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Get active PDO connection
	 *
	 * @return PDO|null Active connection or null
	 */
	public function get_connection() {
		return $this->conn;
	}

	/**
	 * Get database name
	 *
	 * @return string Database name
	 */
	public function get_database_name() {
		return $this->database_name;
	}

	/**
	 * Get connection status.
	 *
	 * @return bool Whether connected.
	 */
	public function is_connected() {
		return null !== $this->conn;
	}

	/**
	 * Execute RPC function (PostgREST compatibility).
	 *
	 * @param string $function_name RPC function name.
	 * @param array  $params        Function parameters.
	 * @return array Result payload.
	 */
	public function execute_rpc( $function_name, $params = array() ) {
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $function_name ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid function name',
			);
		}

		try {
			if ( empty( $params ) ) {
				$sql  = "SELECT * FROM {$function_name}()";
				$stmt = $this->conn->query( $sql );
			} else {
				$placeholders = array();
				$bindings     = array();
				$index        = 0;

				foreach ( $params as $value ) {
					++$index;
					$key              = ':p' . $index;
					$placeholders[]   = $key;
					$bindings[ $key ] = $value;
				}

				$sql  = sprintf( 'SELECT * FROM %s(%s)', $function_name, implode( ', ', $placeholders ) );
				$stmt = $this->conn->prepare( $sql );
				$stmt->execute( $bindings );
			}

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$rows = $stmt->fetchAll( PDO::FETCH_ASSOC );

			return array(
				'success' => true,
				'data'    => is_array( $rows ) ? $rows : array(),
			);
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			return array(
				'success' => false,
				'message' => $this->last_error,
			);
		}
	}

	/**
	 * Upsert term (GG_Data_DB compatibility).
	 *
	 * @param object $term            Term object from WordPress.
	 * @param int    $site_id         Site ID (unused for wp_terms).
	 * @param string $connection_name Connection name.
	 * @return bool Success status.
	 */
	public function upsert_term( $term, $site_id = 1, $connection_name = null ) {
		if ( ! $this->conn || ! is_object( $term ) || ! isset( $term->term_id ) ) {
			return false;
		}

		$table = $this->get_table_name( 'terms' );
		if ( empty( $table ) ) {
			return false;
		}

		$sql = "INSERT INTO {$table} (term_id, name, slug, term_group)
			VALUES (:term_id, :name, :slug, :term_group)
			ON CONFLICT (term_id) DO UPDATE SET
				name = EXCLUDED.name,
				slug = EXCLUDED.slug,
				term_group = EXCLUDED.term_group";

		$stmt = $this->conn->prepare( $sql );
		return (bool) $stmt->execute(
			array(
				':term_id'    => (int) $term->term_id,
				':name'       => isset( $term->name ) ? (string) $term->name : '',
				':slug'       => isset( $term->slug ) ? (string) $term->slug : '',
				':term_group' => isset( $term->term_group ) ? (int) $term->term_group : 0,
			)
		);
	}

	/**
	 * Bulk upsert terms (batch operation).
	 *
	 * @param array  $terms           Array of term objects.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and count.
	 */
	public function bulk_upsert_terms( $terms, $site_id = 1, $connection_name = null ) {
		if ( empty( $terms ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No terms to upsert',
			);
		}

		$count = 0;
		foreach ( $terms as $term ) {
			if ( $this->upsert_term( $term, $site_id, $connection_name ) ) {
				++$count;
			}
		}

		return array(
			'success' => count( $terms ) === $count,
			'count'   => $count,
		);
	}

	/**
	 * Bulk upsert term taxonomies (batch operation).
	 *
	 * @param array  $taxonomies      Array of taxonomy rows.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and count.
	 */
	public function bulk_upsert_term_taxonomies( $taxonomies, $site_id = 1, $connection_name = null ) {
		if ( empty( $taxonomies ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No taxonomies to upsert',
			);
		}

		$count               = 0;
		$last_taxonomy_error = '';
		foreach ( $taxonomies as $taxonomy_row ) {
			if ( is_object( $taxonomy_row ) ) {
				$taxonomy_row = get_object_vars( $taxonomy_row );
			}

			if ( ! is_array( $taxonomy_row ) ) {
				continue;
			}

			$ok = $this->upsert_term_taxonomy(
				isset( $taxonomy_row['term_taxonomy_id'] ) ? (int) $taxonomy_row['term_taxonomy_id'] : 0,
				isset( $taxonomy_row['term_id'] ) ? (int) $taxonomy_row['term_id'] : 0,
				isset( $taxonomy_row['taxonomy'] ) ? (string) $taxonomy_row['taxonomy'] : '',
				isset( $taxonomy_row['description'] ) ? (string) $taxonomy_row['description'] : '',
				isset( $taxonomy_row['parent'] ) ? (int) $taxonomy_row['parent'] : 0,
				isset( $taxonomy_row['count'] ) ? (int) $taxonomy_row['count'] : 0,
				$site_id,
				$connection_name
			);

			if ( $ok ) {
				++$count;
			} else {
				$last_taxonomy_error = ! empty( $this->last_error ) ? $this->last_error : 'Unknown provider error';
			}
		}

		return array(
			'success' => count( $taxonomies ) === $count,
			'count'   => $count,
			'error'   => count( $taxonomies ) === $count ? null : $last_taxonomy_error,
		);
	}

	/**
	 * Upsert term taxonomy (GG_Data_DB compatibility).
	 *
	 * @param int    $term_taxonomy_id Term taxonomy ID.
	 * @param int    $term_id          Term ID.
	 * @param string $taxonomy         Taxonomy slug.
	 * @param string $description      Description.
	 * @param int    $parent_term      Parent term.
	 * @param int    $count            Term count.
	 * @param int    $site_id          Site ID.
	 * @param string $connection_name  Connection name.
	 * @return bool Success status.
	 */
	public function upsert_term_taxonomy( $term_taxonomy_id, $term_id, $taxonomy, $description = '', $parent_term = 0, $count = 0, $site_id = 1, $connection_name = null ) {
		if ( ! $this->conn ) {
			return false;
		}

		$table = $this->get_table_name( 'term_taxonomy' );
		if ( empty( $table ) ) {
			return false;
		}

		$sql = "INSERT INTO {$table} (term_taxonomy_id, term_id, taxonomy, description, parent, count, site_id)
			VALUES (:term_taxonomy_id, :term_id, :taxonomy, :description, :parent, :count, :site_id)
			ON CONFLICT (term_taxonomy_id) DO UPDATE SET
				term_id = EXCLUDED.term_id,
				taxonomy = EXCLUDED.taxonomy,
				description = EXCLUDED.description,
				parent = EXCLUDED.parent,
				count = EXCLUDED.count,
				site_id = EXCLUDED.site_id";

		$stmt = $this->conn->prepare( $sql );
		return (bool) $stmt->execute(
			array(
				':term_taxonomy_id' => (int) $term_taxonomy_id,
				':term_id'          => (int) $term_id,
				':taxonomy'         => (string) $taxonomy,
				':description'      => (string) $description,
				':parent'           => (int) $parent_term,
				':count'            => (int) $count,
				':site_id'          => (int) $site_id,
			)
		);
	}

	/**
	 * Bulk upsert term relationships (batch operation).
	 *
	 * @param array  $relationships   Array of relationship rows.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and count.
	 */
	public function bulk_upsert_term_relationships( $relationships, $site_id = 1, $connection_name = null ) {
		if ( empty( $relationships ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No relationships to upsert',
			);
		}

		$count                   = 0;
		$last_relationship_error = '';
		foreach ( $relationships as $relationship ) {
			if ( is_object( $relationship ) ) {
				$relationship = get_object_vars( $relationship );
			}

			if ( ! is_array( $relationship ) ) {
				continue;
			}

			$ok = $this->upsert_term_relationship(
				isset( $relationship['object_id'] ) ? (int) $relationship['object_id'] : 0,
				isset( $relationship['term_taxonomy_id'] ) ? (int) $relationship['term_taxonomy_id'] : 0,
				isset( $relationship['term_order'] ) ? (int) $relationship['term_order'] : 0,
				$site_id,
				$connection_name
			);

			if ( $ok ) {
				++$count;
			} else {
				$last_relationship_error = ! empty( $this->last_error ) ? $this->last_error : 'Unknown provider error';
			}
		}

		return array(
			'success' => count( $relationships ) === $count,
			'count'   => $count,
			'error'   => count( $relationships ) === $count ? null : $last_relationship_error,
		);
	}

	/**
	 * Bulk upsert posts.
	 *
	 * @param array  $posts           Array of post objects.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return array Result with success status and count.
	 */
	public function bulk_upsert_posts( $posts, $site_id = 1, $connection_name = null ) {
		if ( empty( $posts ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => 'No posts to upsert',
			);
		}

		$count = 0;
		foreach ( $posts as $post ) {
			if ( $this->upsert_post( $post, $site_id, $connection_name ) ) {
				++$count;
			}
		}

		return array(
			'success' => count( $posts ) === $count,
			'count'   => $count,
		);
	}

	/**
	 * Bulk upsert post meta.
	 *
	 * @param array  $meta_records    Array of meta rows.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
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

		$count           = 0;
		$last_meta_error = '';
		foreach ( $meta_records as $record ) {
			$meta_id    = isset( $record->meta_id ) ? (int) $record->meta_id : ( isset( $record['meta_id'] ) ? (int) $record['meta_id'] : 0 );
			$post_id    = isset( $record->post_id ) ? (int) $record->post_id : ( isset( $record['post_id'] ) ? (int) $record['post_id'] : 0 );
			$meta_key   = isset( $record->meta_key ) ? (string) $record->meta_key : ( isset( $record['meta_key'] ) ? (string) $record['meta_key'] : '' );
			$meta_value = isset( $record->meta_value ) ? $record->meta_value : ( isset( $record['meta_value'] ) ? $record['meta_value'] : '' );

			if ( $this->upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $site_id, $connection_name ) ) {
				++$count;
			} else {
				$last_meta_error = ! empty( $this->last_error ) ? $this->last_error : 'Unknown provider error';
			}
		}

		$success = count( $meta_records ) === $count;

		return array(
			'success' => $success,
			'count'   => $count,
			'error'   => $success ? null : $last_meta_error,
		);
	}

	/**
	 * Upsert term relationship (GG_Data_DB compatibility).
	 *
	 * @param int    $object_id        Object ID.
	 * @param int    $term_taxonomy_id Term taxonomy ID.
	 * @param int    $term_order       Term order.
	 * @param int    $site_id          Site ID.
	 * @param string $connection_name  Connection name.
	 * @return bool Success status.
	 */
	public function upsert_term_relationship( $object_id, $term_taxonomy_id, $term_order = 0, $site_id = 1, $connection_name = null ) {
		if ( ! $this->conn ) {
			$this->last_error = 'No active database connection';
			return false;
		}

		$table = $this->get_table_name( 'term_relationships' );
		if ( empty( $table ) ) {
			$this->last_error = 'Could not resolve term_relationships table name';
			return false;
		}

		$sql = "INSERT INTO {$table} (object_id, term_taxonomy_id, term_order)
			VALUES (:object_id, :term_taxonomy_id, :term_order)
			ON CONFLICT (object_id, term_taxonomy_id) DO UPDATE SET
				term_order = EXCLUDED.term_order";

		$stmt   = $this->conn->prepare( $sql );
		$result = $stmt->execute(
			array(
				':object_id'        => (int) $object_id,
				':term_taxonomy_id' => (int) $term_taxonomy_id,
				':term_order'       => (int) $term_order,
			)
		);

		if ( ! $result ) {
			$error_info       = $stmt->errorInfo();
			$this->last_error = isset( $error_info[2] ) && '' !== (string) $error_info[2] ? (string) $error_info[2] : 'Unknown provider error';
		}

		return (bool) $result;
	}

	/**
	 * Upsert post (GG_Data_DB compatibility).
	 *
	 * @param object $post            Post object.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return bool Success status.
	 */
	public function upsert_post( $post, $site_id = 1, $connection_name = null ) {
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return false;
		}

		$post_data = array(
			'ID'                => $post->ID,
			'post_author'       => isset( $post->post_author ) ? $post->post_author : 1,
			'post_date'         => isset( $post->post_date ) ? $post->post_date : '',
			'post_date_gmt'     => isset( $post->post_date_gmt ) ? $post->post_date_gmt : '',
			'post_content'      => isset( $post->post_content ) ? $post->post_content : '',
			'post_title'        => isset( $post->post_title ) ? $post->post_title : '',
			'post_excerpt'      => isset( $post->post_excerpt ) ? $post->post_excerpt : '',
			'post_status'       => isset( $post->post_status ) ? $post->post_status : '',
			'post_name'         => isset( $post->post_name ) ? $post->post_name : '',
			'post_modified'     => isset( $post->post_modified ) ? $post->post_modified : '',
			'post_modified_gmt' => isset( $post->post_modified_gmt ) ? $post->post_modified_gmt : '',
			'post_type'         => isset( $post->post_type ) ? $post->post_type : '',
			'guid'              => isset( $post->guid ) ? $post->guid : '',
			'site_id'           => $site_id,
		);

		$result = $this->sync_post( (int) $post->ID, $post_data );
		return isset( $result['success'] ) ? (bool) $result['success'] : false;
	}

	/**
	 * Upsert post meta (GG_Data_DB compatibility).
	 *
	 * @param int    $meta_id         Meta ID.
	 * @param int    $post_id         Post ID.
	 * @param string $meta_key        Meta key.
	 * @param mixed  $meta_value      Meta value.
	 * @param int    $site_id         Site ID.
	 * @param string $connection_name Connection name.
	 * @return bool Success status.
	 */
	public function upsert_post_meta( $meta_id, $post_id, $meta_key, $meta_value, $site_id = 1, $connection_name = null ) {
		if ( ! $this->conn ) {
			$this->last_error = 'No active database connection';
			return false;
		}

		$table = $this->get_table_name( 'postmeta' );
		if ( empty( $table ) ) {
			$this->last_error = 'Could not resolve postmeta table name';
			return false;
		}

		$encoded_value = null === $meta_value ? 'null' : wp_json_encode( $meta_value );
		if ( false === $encoded_value ) {
			$encoded_value = wp_json_encode( (string) $meta_value );
		}

		try {
			$update_sql  = "UPDATE {$table} SET meta_value = :meta_value WHERE meta_id = :meta_id";
			$update_stmt = $this->conn->prepare( $update_sql );
			$update_stmt->execute(
				array(
					':meta_value' => $encoded_value,
					':meta_id'    => (int) $meta_id,
				)
			);

			if ( $update_stmt->rowCount() > 0 ) {
				return true;
			}

			$insert_sql  = "INSERT INTO {$table} (meta_id, post_id, meta_key, meta_value) VALUES (:meta_id, :post_id, :meta_key, :meta_value)";
			$insert_stmt = $this->conn->prepare( $insert_sql );
			return (bool) $insert_stmt->execute(
				array(
					':meta_id'    => (int) $meta_id,
					':post_id'    => (int) $post_id,
					':meta_key'   => (string) $meta_key,
					':meta_value' => $encoded_value,
				)
			);
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Count records in a table.
	 *
	 * @param string $table Table name.
	 * @param array  $where Optional WHERE conditions.
	 * @return array Result with success and count/error.
	 */
	public function count_records( $table, $where = array() ) {
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'error'   => 'No active database connection',
			);
		}

		$full_table_name = $this->qualify_table_name( $table );
		if ( empty( $full_table_name ) ) {
			return array(
				'success' => false,
				'error'   => 'Invalid table name',
			);
		}

		try {
			$sql       = "SELECT COUNT(*) FROM {$full_table_name}";
			$bindings  = array();
			$where_sql = $this->build_where_clause( $where, $bindings );

			if ( ! empty( $where_sql ) ) {
				$sql .= ' WHERE ' . $where_sql;
			}

			$stmt = $this->conn->prepare( $sql );
			$stmt->execute( $bindings );

			return array(
				'success' => true,
				'count'   => (int) $stmt->fetchColumn(),
			);
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			return array(
				'success' => false,
				'error'   => $this->last_error,
			);
		}
	}

	/**
	 * Select records from a table.
	 *
	 * @param string      $table      Table name.
	 * @param array       $conditions WHERE conditions.
	 * @param string|null $order      ORDER BY clause.
	 * @param string      $select     Columns to select.
	 * @param int|null    $limit      Optional limit.
	 * @return array|false Array of rows or false on error.
	 */
	public function select( $table, $conditions = array(), $order = null, $select = '*', $limit = null ) {
		if ( ! $this->conn ) {
			return false;
		}

		$full_table_name = $this->qualify_table_name( $table );
		if ( empty( $full_table_name ) || ! $this->is_safe_select_expression( $select ) ) {
			return false;
		}

		try {
			$sql       = "SELECT {$select} FROM {$full_table_name}";
			$bindings  = array();
			$where_sql = $this->build_where_clause( $conditions, $bindings );

			if ( ! empty( $where_sql ) ) {
				$sql .= ' WHERE ' . $where_sql;
			}

			if ( ! empty( $order ) ) {
				if ( ! $this->is_safe_order_clause( $order ) ) {
					return false;
				}
				$sql .= ' ORDER BY ' . $order;
			}

			if ( null !== $limit ) {
				$sql .= ' LIMIT ' . intval( $limit );
			}

			$stmt = $this->conn->prepare( $sql );
			$stmt->execute( $bindings );

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
			return is_array( $result ) ? $result : array();
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Delete records from a table.
	 *
	 * @param string $table      Table name.
	 * @param array  $conditions WHERE conditions.
	 * @return bool True on success, false on error.
	 */
	public function delete( $table, $conditions = array() ) {
		if ( ! $this->conn || empty( $conditions ) ) {
			return false;
		}

		$full_table_name = $this->qualify_table_name( $table );
		if ( empty( $full_table_name ) ) {
			return false;
		}

		try {
			$bindings  = array();
			$where_sql = $this->build_where_clause( $conditions, $bindings );
			if ( empty( $where_sql ) ) {
				return false;
			}

			$sql  = "DELETE FROM {$full_table_name} WHERE {$where_sql}";
			$stmt = $this->conn->prepare( $sql );
			return (bool) $stmt->execute( $bindings );
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Insert a record into a table.
	 *
	 * @param string $table Table name.
	 * @param array  $data  Row data.
	 * @return bool True on success, false on error.
	 */
	public function insert( $table, $data ) {
		if ( ! $this->conn || empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		$full_table_name = $this->qualify_table_name( $table );
		if ( empty( $full_table_name ) ) {
			return false;
		}

		$columns      = array();
		$placeholders = array();
		$bindings     = array();

		foreach ( $data as $column => $value ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $column ) ) {
				return false;
			}

			$columns[]                = $column;
			$placeholder              = ':' . $column;
			$placeholders[]           = $placeholder;
			$bindings[ $placeholder ] = $value;
		}

		try {
			$sql  = sprintf( 'INSERT INTO %s (%s) VALUES (%s)', $full_table_name, implode( ', ', $columns ), implode( ', ', $placeholders ) );
			$stmt = $this->conn->prepare( $sql );
			return (bool) $stmt->execute( $bindings );
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Bulk insert multiple records into a table.
	 *
	 * @param string $table Table name.
	 * @param array  $rows  Rows to insert.
	 * @return bool|int Number of inserted rows or false on failure.
	 */
	public function bulk_insert( $table, $rows ) {
		if ( ! $this->conn ) {
			return false;
		}

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return 0;
		}

		$full_table_name = $this->qualify_table_name( $table );
		if ( empty( $full_table_name ) ) {
			return false;
		}

		$first = reset( $rows );
		if ( ! is_array( $first ) || empty( $first ) ) {
			return false;
		}

		$columns      = array();
		$placeholders = array();
		foreach ( array_keys( $first ) as $column ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $column ) ) {
				return false;
			}
			$columns[]      = $column;
			$placeholders[] = ':' . $column;
		}

		$sql = sprintf( 'INSERT INTO %s (%s) VALUES (%s)', $full_table_name, implode( ', ', $columns ), implode( ', ', $placeholders ) );

		$inserted = 0;
		try {
			$this->conn->beginTransaction();
			$stmt = $this->conn->prepare( $sql );

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$bindings = array();
				foreach ( $columns as $column ) {
					$bindings[ ':' . $column ] = isset( $row[ $column ] ) ? $row[ $column ] : null;
				}

				if ( $stmt->execute( $bindings ) ) {
					++$inserted;
				}
			}

			$this->conn->commit();
			return $inserted;
		} catch ( Exception $e ) {
			if ( $this->conn->inTransaction() ) {
				$this->conn->rollBack();
			}
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Delete post meta.
	 *
	 * @param int $meta_id Meta ID.
	 * @return array Result.
	 */
	public function delete_post_meta( $meta_id ) {
		$success = $this->delete( 'wp_postmeta', array( 'meta_id' => (int) $meta_id ) );
		return array(
			'success' => (bool) $success,
			'message' => $success ? 'Post meta deleted' : 'Failed to delete post meta',
		);
	}

	/**
	 * Delete term.
	 *
	 * @param int $term_id Term ID.
	 * @return array Result.
	 */
	public function delete_term( $term_id ) {
		$success = $this->delete( 'wp_terms', array( 'term_id' => (int) $term_id ) );
		return array(
			'success' => (bool) $success,
			'message' => $success ? 'Term deleted' : 'Failed to delete term',
		);
	}

	/**
	 * Delete term taxonomy.
	 *
	 * @param int $term_taxonomy_id Term taxonomy ID.
	 * @return array Result.
	 */
	public function delete_term_taxonomy( $term_taxonomy_id ) {
		$success = $this->delete( 'wp_term_taxonomy', array( 'term_taxonomy_id' => (int) $term_taxonomy_id ) );
		return array(
			'success' => (bool) $success,
			'message' => $success ? 'Term taxonomy deleted' : 'Failed to delete term taxonomy',
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
		if ( ! $this->conn ) {
			return new WP_Error( 'no_connection', 'No active database connection' );
		}

		try {
			$full_table_name = $this->qualify_table_name( $table );
			if ( empty( $full_table_name ) ) {
				return new WP_Error( 'invalid_table', 'Invalid table name' );
			}

			if ( ! preg_match( '/^[a-zA-Z0-9_,\s]+$/', (string) $select ) ) {
				return new WP_Error( 'invalid_select', 'Invalid select expression' );
			}

			$order_column = trim( explode( ',', (string) $select )[0] );
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $order_column ) ) {
				return new WP_Error( 'invalid_order', 'Invalid order column' );
			}

			$sql = "SELECT $select FROM $full_table_name";

			if ( ! empty( $conditions ) ) {
				$where = array();
				foreach ( $conditions as $key => $value ) {
					if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $key ) ) {
						continue;
					}
					$where[] = "$key = :$key";
				}
				if ( ! empty( $where ) ) {
					$sql .= ' WHERE ' . implode( ' AND ', $where );
				}
			}

			$sql .= " ORDER BY $order_column LIMIT :limit OFFSET :offset";

			$stmt = $this->conn->prepare( $sql );
			// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$stmt->bindValue( ':limit', $limit, PDO::PARAM_INT );
			$stmt->bindValue( ':offset', $offset, PDO::PARAM_INT );
			// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO

			if ( ! empty( $conditions ) ) {
				foreach ( $conditions as $key => $value ) {
					if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $key ) ) {
						continue;
					}

					if ( is_string( $value ) && 0 === strpos( $value, 'eq.' ) ) {
						$value = substr( $value, 3 );
					}

					$stmt->bindValue( ":$key", $value );
				}
			}

			$stmt->execute();

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			return $stmt->fetchAll( PDO::FETCH_ASSOC );

		} catch ( PDOException $e ) {
			return new WP_Error( 'db_error', $e->getMessage() );
		}
	}

	/**
	 * Bulk delete records by ID
	 *
	 * @param string $table     Table name.
	 * @param array  $ids       Array of IDs to delete.
	 * @param string $id_column Column name for ID (default: 'id').
	 * @return array Result.
	 */
	public function delete_ids( $table, $ids, $id_column = 'id' ) {
		if ( ! $this->conn ) {
			return array(
				'success' => false,
				'message' => 'No active database connection',
			);
		}

		if ( empty( $ids ) ) {
			return array(
				'success' => true,
				'count'   => 0,
			);
		}

		try {
			$full_table_name = $this->qualify_table_name( $table );
			if ( empty( $full_table_name ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid table name',
				);
			}

			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $id_column ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid id column',
				);
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '?' ) );
			$sql          = "DELETE FROM $full_table_name WHERE $id_column IN ($placeholders)";

			$stmt = $this->conn->prepare( $sql );
			$stmt->execute( array_values( $ids ) );

			return array(
				'success' => true,
				'count'   => $stmt->rowCount(),
			);
		} catch ( PDOException $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Resolve and validate table name for SQL usage.
	 *
	 * @param string $table Table name.
	 * @return string Empty string if invalid.
	 */
	protected function qualify_table_name( $table ) {
		$table = (string) $table;
		if ( '' === $table ) {
			return '';
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_.]+$/', $table ) ) {
			return '';
		}

		if ( false !== strpos( $table, '.' ) ) {
			return $table;
		}

		if ( 0 === strpos( $table, 'wp_' ) ) {
			return 'public.' . $table;
		}

		return $this->get_table_name( $table );
	}

	/**
	 * Build SQL WHERE clause from conditions.
	 *
	 * Supports simple PostgREST-style operator prefixes in string values:
	 * eq., neq., gt., gte., lt., lte., like., ilike., is.
	 *
	 * @param array $conditions Input conditions.
	 * @param array $bindings   Output PDO bindings.
	 * @return string WHERE expression without leading WHERE.
	 */
	protected function build_where_clause( $conditions, &$bindings ) {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return '';
		}

		$parts = array();
		$index = 0;

		foreach ( $conditions as $column => $value ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', (string) $column ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				if ( empty( $value ) ) {
					$parts[] = '1 = 0';
					continue;
				}

				$in_placeholders = array();
				foreach ( $value as $in_value ) {
					++$index;
					$key               = ':w' . $index;
					$in_placeholders[] = $key;
					$bindings[ $key ]  = $in_value;
				}

				$parts[] = sprintf( '%s IN (%s)', $column, implode( ', ', $in_placeholders ) );
				continue;
			}

			$operator      = '=';
			$parsed        = $value;
			$is_null_check = false;

			if ( is_string( $value ) && preg_match( '/^(eq|neq|gt|gte|lt|lte|like|ilike|is)\.(.*)$/', $value, $matches ) ) {
				$token  = strtolower( $matches[1] );
				$parsed = $matches[2];

				switch ( $token ) {
					case 'eq':
						$operator = '=';
						break;
					case 'neq':
						$operator = '!=';
						break;
					case 'gt':
						$operator = '>';
						break;
					case 'gte':
						$operator = '>=';
						break;
					case 'lt':
						$operator = '<';
						break;
					case 'lte':
						$operator = '<=';
						break;
					case 'like':
						$operator = 'LIKE';
						break;
					case 'ilike':
						$operator = 'ILIKE';
						break;
					case 'is':
						$is_null_check = true;
						break;
				}
			}

			if ( $is_null_check ) {
				$parts[] = ( 'null' === strtolower( (string) $parsed ) )
					? sprintf( '%s IS NULL', $column )
					: sprintf( '%s IS NOT NULL', $column );
				continue;
			}

			++$index;
			$key              = ':w' . $index;
			$parts[]          = sprintf( '%s %s %s', $column, $operator, $key );
			$bindings[ $key ] = $parsed;
		}

		return implode( ' AND ', $parts );
	}

	/**
	 * Validate SELECT column expression.
	 *
	 * @param string $select Select expression.
	 * @return bool True when safe.
	 */
	protected function is_safe_select_expression( $select ) {
		if ( '*' === $select ) {
			return true;
		}

		return (bool) preg_match( '/^[a-zA-Z0-9_,\s.]+$/', (string) $select );
	}

	/**
	 * Validate ORDER BY clause.
	 *
	 * @param string $order Order clause.
	 * @return bool True when safe.
	 */
	protected function is_safe_order_clause( $order ) {
		return (bool) preg_match( '/^[a-zA-Z0-9_,\s.]+$/', (string) $order );
	}
}
