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
 * The interface defines standardized methods for connection management and data synchronization.
 *
 * @since 1.0.0
 */
interface Gregius_Data_DB_Provider {

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

	/**
	 * Count records in a table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table Table name.
	 * @param array  $where Optional WHERE conditions (key => value or PostgREST operator string).
	 * @return array Result with 'success' and 'count' (or 'error') keys.
	 */
	public function count_records( $table, $where = array() );

	/**
	 * Select records from a table.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $table      Table name.
	 * @param array       $conditions WHERE conditions as key => value pairs.
	 * @param string|null $order      ORDER BY clause.
	 * @param string      $select     Columns to select (default: '*').
	 * @param int|null    $limit      LIMIT clause.
	 * @return array|false Array of rows or false on error.
	 */
	public function select( $table, $conditions = array(), $order = null, $select = '*', $limit = null );

	/**
	 * Insert a record into a table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table Table name.
	 * @param array  $data  Row data as key => value pairs.
	 * @return bool True on success, false on error.
	 */
	public function insert( $table, $data );

	/**
	 * Delete records from a table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table      Table name.
	 * @param array  $conditions WHERE conditions as key => value pairs.
	 * @return bool True on success, false on error.
	 */
	public function delete( $table, $conditions = array() );

	/**
	 * Bulk insert multiple records into a table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table Table name.
	 * @param array  $rows  Array of data arrays to insert.
	 * @return bool|int Number of rows inserted on success, false on error.
	 */
	public function bulk_insert( $table, $rows );

	/**
	 * Upsert a record into a table (insert or update on conflict).
	 *
	 * PostgREST implementations MUST use 'Prefer: resolution=merge-duplicates' and
	 * own the HTTP 409 fallback (409 = ambiguous multiple matching rows). They MUST
	 * return true only once the row is actually written, or false on failure.
	 *
	 * @since 1.0.1
	 *
	 * @param string $table            Table name.
	 * @param array  $data             Row data as key => value pairs.
	 * @param array  $conflict_columns Columns forming the unique conflict target (e.g. connection_name, entity_type, entity_id).
	 * @return bool True on success, false on error.
	 */
	public function upsert( $table, $data, $conflict_columns = array() );
}
