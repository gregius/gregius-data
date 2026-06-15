<?php
/**
 * WP-CLI List Models Command
 *
 * List AI models.
 * Mirrors gregius-data/list-models ability.
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
 * List available AI models.
 *
 * Mirrors the gregius-data/list-models ability.
 *
 * ## EXAMPLES
 *
 *     # List all models
 *     $ wp gg-data list-models
 *
 *     # List only LLM models
 *     $ wp gg-data list-models --type=llm
 *
 *     # List models for specific connection as JSON
 *     $ wp gg-data list-models --connection=gregius-data --format=json
 *
 * @when after_wp_load
 *
 * @since 1.0.0
 */
class GG_Data_CLI_List_Models {

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
	 * List all available AI models.
	 *
	 * ## OPTIONS
	 *
	 * [--connection=<name>]
	 * : Connection to list models for.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--type=<type>]
	 * : Filter by model type: embeddings, llm, or rerank.
	 * ---
	 * options:
	 *   - embeddings
	 *   - llm
	 *   - rerank
	 * ---
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
	 * ## EXAMPLES
	 *
	 *     # List all models
	 *     $ wp gg-data list-models
	 *
	 *     # List only LLM models
	 *     $ wp gg-data list-models --type=llm
	 *
	 *     # List embedding models as JSON
	 *     $ wp gg-data list-models --type=embeddings --format=json
	 *
	 *     # List models for specific connection
	 *     $ wp gg-data list-models --connection=gregius-data
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$connection = $assoc_args['connection'] ?? 'gregius-data';
		$type       = $assoc_args['type'] ?? '';
		$format     = $assoc_args['format'] ?? 'table';

		// Execute via Abilities Manager.
		$result = $this->get_abilities()->execute_list_models(
			array(
				'connection_name' => $connection,
				'type'            => $type,
			)
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( sprintf( 'Error: %s', $result->get_error_message() ) );
			return;
		}

		$models = $result['models'] ?? array();

		if ( empty( $models ) ) {
			$type_msg = $type ? " of type '{$type}'" : '';
			WP_CLI::warning( sprintf( 'No models found%s for connection "%s".', $type_msg, $connection ) );
			return;
		}

		// Format output.
		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $models, JSON_PRETTY_PRINT ) );
			return;
		}

		// Prepare items for table/csv format.
		$items = array();
		foreach ( $models as $model ) {
			$item = array(
				'id'        => $model['id'] ?? '',
				'type'      => $model['type'] ?? 'llm',
				'provider'  => $model['provider'] ?? 'unknown',
				'label'     => $model['label'] ?? $model['id'],
				'is_active' => ( $model['is_active'] ?? true ) ? 'Yes' : 'No',
			);

			// Add dimensions for embedding models.
			if ( 'embeddings' === $model['type'] && isset( $model['dimensions'] ) ) {
				$item['dimensions'] = $model['dimensions'];
			}

			$items[] = $item;
		}

		// Determine columns based on type filter.
		$columns = array( 'id', 'type', 'provider', 'label', 'is_active' );
		if ( 'embeddings' === $type ) {
			$columns[] = 'dimensions';
		}

		\WP_CLI\Utils\format_items( $format, $items, $columns );

		$type_msg = $type ? " of type '{$type}'" : '';
		WP_CLI::success( sprintf( 'Found %d model(s)%s', count( $models ), $type_msg ) );
	}
}
