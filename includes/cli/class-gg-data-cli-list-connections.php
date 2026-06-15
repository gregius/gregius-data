<?php
/**
 * WP-CLI List Connections Command
 *
 * List database connections.
 * Mirrors gregius-data/list-connections ability.
 *
 * @package Gregius_Data
 * @subpackage CLI
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List available database connections.
 *
 * Mirrors the gregius-data/list-connections ability.
 *
 * ## EXAMPLES
 *
 *     # List all connections
 *     $ wp gg-data list-connections
 *
 *     # Output as JSON
 *     $ wp gg-data list-connections --format=json
 *
 * @when after_wp_load
 *
 * @since 1.0.0
 */
class GG_Data_CLI_List_Connections {

	/**
	 * Abilities manager instance
	 *
	 * @var GG_Data_Abilities_Manager|null
	 */
	private $abilities = null;

	/**
	 * Get or create the abilities manager instance (lazy loading).
	 *
	 * @return GG_Data_Abilities_Manager
	 */
	private function get_abilities() {
		if ( null === $this->abilities ) {
			$this->abilities = new GG_Data_Abilities_Manager();
		}
		return $this->abilities;
	}

	/**
	 * List all available database connections.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * [--with-embedding-models]
	 * : Include active embedding model keys per connection.
	 *
	 * [--with-model-details]
	 * : Include safe embedding model metadata per connection. Implies --with-embedding-models.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all connections
	 *     $ wp gg-data list-connections
	 *
	 *     # Output as JSON
	 *     $ wp gg-data list-connections --format=json
	 *
	 *     # Include active embedding model keys
	 *     $ wp gg-data list-connections --with-embedding-models
	 *
	 *     # Include detailed model metadata in JSON output
	 *     $ wp gg-data list-connections --with-embedding-models --with-model-details --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$format                 = $assoc_args['format'] ?? 'table';
		$with_embedding_models  = ! empty( $assoc_args['with-embedding-models'] );
		$with_model_details     = ! empty( $assoc_args['with-model-details'] );
		$ability_request_params = array();

		if ( $with_model_details ) {
			$with_embedding_models = true;
		}

		if ( $with_embedding_models ) {
			$ability_request_params['include_embedding_models'] = true;
		}

		if ( $with_model_details ) {
			$ability_request_params['include_model_details'] = true;
		}

		// Execute via Abilities Manager.
		$result = $this->get_abilities()->execute_list_connections( $ability_request_params );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( sprintf( 'Error: %s', $result->get_error_message() ) );
			return;
		}

		$connections = $result['connections'] ?? array();

		if ( empty( $connections ) ) {
			WP_CLI::warning( 'No connections configured.' );
			return;
		}

		// Format output.
		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $connections, JSON_PRETTY_PRINT ) );
			return;
		}

		// Prepare items for table/csv format.
		$items = array();
		foreach ( $connections as $conn ) {
			$item = array(
				'name'        => $conn['name'] ?? '',
				'type'        => $conn['type'] ?? 'postgresql',
				'description' => $conn['description'] ?? '',
				'is_active'   => ( $conn['is_active'] ?? true ) ? 'Yes' : 'No',
			);

			if ( $with_embedding_models ) {
				$active_keys = $conn['embedding_models']['active_keys'] ?? array();

				if ( ! is_array( $active_keys ) ) {
					$active_keys = array();
				}

				$item['embedding_models'] = implode( ', ', $active_keys );
				$item['model_count']      = isset( $conn['embedding_models']['active_count'] ) ? (int) $conn['embedding_models']['active_count'] : count( $active_keys );
			}

			$items[] = $item;
		}

		$fields = array( 'name', 'type', 'description', 'is_active' );

		if ( $with_embedding_models ) {
			$fields[] = 'model_count';
			$fields[] = 'embedding_models';
		}

		\WP_CLI\Utils\format_items( $format, $items, $fields );

		WP_CLI::success( sprintf( 'Found %d connection(s)', count( $connections ) ) );
	}
}
