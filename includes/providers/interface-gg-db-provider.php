<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Database Provider Interface
 *
 * Defines the contract that all database providers must implement.
 * This interface enables support for multiple database backends (PostgreSQL, MySQL 9.0+, etc.)
 * while maintaining a consistent API across the plugin.
 *
 * @package Gregius_Data
 * @subpackage Gregius_PostgreSQL/includes/providers
 * @since 1.0.0
 */

/**
 * Database Provider Interface
 *
 * All database providers (PostgreSQL, MySQL, etc.) must implement this interface.
 * The interface defines standardized methods for connection management, data synchronization,
 * vector generation, and semantic search operations.
 *
 * @since 1.0.0
 */
interface GG_Data_DB_Provider {

	/**
	 * Establish database connection
	 *
	 * Creates and validates a connection to the database using the provided configuration.
	 * Implementations should handle connection pooling, SSL/TLS, and authentication.
	 *
	 * @since 1.0.0
	 *
	 * @param array $connection_config {
	 *     Connection configuration array.
	 *
	 *     @type string $host          Database host (e.g., 'localhost', '127.0.0.1')
	 *     @type int    $port          Database port (e.g., 5432 for PostgreSQL, 3306 for MySQL)
	 *     @type string $database      Database name
	 *     @type string $user          Database user
	 *     @type string $password      Database password
	 *     @type string $ssl_mode      Optional. SSL mode (e.g., 'require', 'prefer', 'disable')
	 *     @type array  $extra_params  Optional. Provider-specific parameters
	 * }
	 *
	 * @return array {
	 *     Connection result.
	 *
	 *     @type bool   $success   Whether connection succeeded
	 *     @type string $message   Human-readable status message
	 *     @type string $version   Database server version (e.g., 'PostgreSQL 14.5', 'MySQL 9.0.1')
	 *     @type mixed  $connection Optional. Connection resource/object for internal use
	 * }
	 */
	public function connect( $connection_config );

	/**
	 * Close database connection
	 *
	 * Cleanly closes the active database connection and releases resources.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Disconnection result.
	 *
	 *     @type bool   $success   Whether disconnection succeeded
	 *     @type string $message   Human-readable status message
	 * }
	 */
	public function disconnect();

	/**
	 * Test database connection
	 *
	 * Validates connection configuration without establishing a persistent connection.
	 * Used during connection creation/editing to verify credentials and accessibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array $connection_config Connection configuration (see connect() for structure).
	 *
	 * @return array {
	 *     Test result.
	 *
	 *     @type bool   $success   Whether test connection succeeded
	 *     @type string $message   Human-readable status message
	 *     @type string $version   Database server version if successful
	 *     @type array  $extensions Optional. Installed extensions (e.g., ['pgvector' => '0.5.0'])
	 * }
	 */
	public function test_connection( $connection_config );

	/**
	 * Synchronize WordPress post to database
	 *
	 * Inserts or updates a WordPress post in the provider's database schema.
	 * Handles post content, metadata, taxonomy terms, and related data.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id   WordPress post ID.
	 * @param array $post_data {
	 *     Post data to synchronize.
	 *
	 *     @type string $post_title    Post title
	 *     @type string $post_content  Post content
	 *     @type string $post_excerpt  Post excerpt
	 *     @type string $post_type     Post type
	 *     @type string $post_status   Post status
	 *     @type int    $post_author   Post author ID
	 *     @type string $post_date     Post date (Y-m-d H:i:s format)
	 *     @type array  $meta          Optional. Post meta fields
	 *     @type array  $taxonomies    Optional. Taxonomy terms
	 * }
	 *
	 * @return array {
	 *     Sync result.
	 *
	 *     @type bool   $success    Whether sync succeeded
	 *     @type int    $post_id    Synced post ID
	 *     @type string $synced_at  Timestamp of sync (ISO 8601 format)
	 *     @type string $message    Human-readable status message
	 * }
	 */
	public function sync_post( $post_id, $post_data );

	/**
	 * Delete post from database
	 *
	 * Removes a post and all associated data (metadata, vectors, etc.) from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id WordPress post ID to delete.
	 *
	 * @return array {
	 *     Deletion result.
	 *
	 *     @type bool   $success   Whether deletion succeeded
	 *     @type int    $post_id   Deleted post ID
	 *     @type string $message   Human-readable status message
	 * }
	 */
	public function delete_post( $post_id );

	/**
	 * Generate vector embeddings for post
	 *
	 * Creates and stores vector embeddings for post content using specified embedding model.
	 * Supports multiple embedding types (TF-IDF, word2vec, transformer models, etc.)
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id          WordPress post ID.
	 * @param array $embedding_config {
	 *     Embedding configuration.
	 *
	 *     @type string $model       Embedding model (e.g., 'tfidf_300', 'word2vec', 'sentence_transformers')
	 *     @type int    $dimensions  Vector dimensions (e.g., 300, 768, 1536)
	 *     @type array  $fields      Fields to vectorize (e.g., ['title', 'content', 'excerpt'])
	 *     @type array  $options     Optional. Model-specific options
	 * }
	 *
	 * @return array {
	 *     Vector generation result.
	 *
	 *     @type bool   $success      Whether generation succeeded
	 *     @type int    $post_id      Post ID
	 *     @type int    $dimensions   Vector dimensions
	 *     @type string $model        Embedding model used
	 *     @type string $generated_at Timestamp (ISO 8601 format)
	 *     @type string $message      Human-readable status message
	 * }
	 */
	public function generate_vectors( $post_id, $embedding_config );

	/**
	 * Perform semantic search
	 *
	 * Executes vector similarity search to find semantically related content.
	 * Supports multiple distance metrics and result filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query         Search query string.
	 * @param array  $search_config {
	 *     Search configuration.
	 *
	 *     @type string $model         Embedding model to use for query vectorization
	 *     @type string $distance      Distance metric ('cosine', 'l2', 'inner_product')
	 *     @type int    $limit         Maximum results to return (default 10)
	 *     @type float  $threshold     Optional. Similarity threshold (0.0-1.0)
	 *     @type array  $post_types    Optional. Filter by post types
	 *     @type array  $post_status   Optional. Filter by post status (default ['publish'])
	 *     @type array  $fields        Optional. Vector fields to search (['title', 'content', 'excerpt'])
	 * }
	 *
	 * @return array {
	 *     Search results.
	 *
	 *     @type bool   $success   Whether search succeeded
	 *     @type array  $results   Array of result objects with post_id, score, and post data
	 *     @type int    $total     Total matching results (before limit)
	 *     @type float  $latency   Query execution time in milliseconds
	 *     @type string $message   Human-readable status message
	 * }
	 */
	public function search( $query, $search_config );

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
	public function get_ids( $table, $limit = 100, $offset = 0, $conditions = array(), $select = 'id' );

	/**
	 * Bulk delete records by ID
	 *
	 * @param string $table     Table name.
	 * @param array  $ids       Array of IDs to delete.
	 * @param string $id_column Column name for ID (default: 'id').
	 * @return array Result.
	 */
	public function delete_ids( $table, $ids, $id_column = 'id' );

	/**
	 * Delete term relationship
	 *
	 * @param int $object_id        Object ID.
	 * @param int $term_taxonomy_id Term Taxonomy ID.
	 * @return array Result.
	 */
	public function delete_term_relationship( $object_id, $term_taxonomy_id );

	/**
	 * Get database schema version
	 *
	 * Returns the current schema version for migration and compatibility checks.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Schema version information.
	 *
	 *     @type bool   $success   Whether version retrieval succeeded
	 *     @type string $version   Schema version (e.g., '1.0.0', '2.1.0')
	 *     @type string $message   Human-readable status message
	 * }
	 */
	public function get_schema_version();

	/**
	 * Create or update database schema
	 *
	 * Creates necessary tables, indexes, and database objects for the provider.
	 * Handles schema migrations and updates safely.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema_config {
	 *     Optional schema configuration.
	 *
	 *     @type string $version       Target schema version (default: latest)
	 *     @type bool   $force         Force recreation of existing schema
	 *     @type array  $extensions    Required extensions (e.g., ['pgvector', 'pg_trgm'])
	 *     @type array  $options       Provider-specific schema options
	 * }
	 *
	 * @return array {
	 *     Schema creation result.
	 *
	 *     @type bool   $success      Whether schema creation succeeded
	 *     @type string $version      Created schema version
	 *     @type array  $created      List of created objects (tables, indexes, etc.)
	 *     @type array  $extensions   Installed extensions with versions
	 *     @type string $message      Human-readable status message
	 * }
	 */
	public function create_schema( $schema_config = array() );
}
