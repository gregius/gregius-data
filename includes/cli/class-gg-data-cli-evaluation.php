<?php
/**
 * WP-CLI evaluation command family.
 *
 * @package Gregius_Data
 * @subpackage CLI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluation commands for gg-data.
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Evaluation {

	/**
	 * Evaluation service.
	 *
	 * @var GG_Data_Evaluation_Service|null
	 */
	private $service = null;

	/**
	 * Get service instance.
	 *
	 * @return GG_Data_Evaluation_Service
	 */
	private function get_service() {
		if ( null === $this->service ) {
			$this->service = new GG_Data_Evaluation_Service();
		}

		return $this->service;
	}

	/**
	 * Run evaluation prompts.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the evaluation config file.
	 *
	 * [--only=<id>]
	 * : Run a single sample by ID.
	 *
	 * [--framework=<name>]
	 * : Override the configured framework.
	 *
	 * [--output-dir=<path>]
	 * : Absolute output directory for artifacts.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function run( $args, $assoc_args ) {
		try {
			$result = $this->get_service()->run( $assoc_args );

			foreach ( $result['prompt_results'] as $sample ) {
				WP_CLI::log( sprintf( 'Completed %1$s -> %2$s', $sample['id'], $sample['json_path'] ) );
			}

			WP_CLI::success( 'Evaluation artifacts written to ' . $result['output_dir'] );
			WP_CLI::log( 'Prompts dataset: ' . $result['canonical_path'] );
			foreach ( $result['adapter_paths'] as $framework => $path ) {
				WP_CLI::log( ucfirst( $framework ) . ' adapter: ' . $path );
			}
			WP_CLI::log( 'Manifest: ' . $result['manifest_path'] );

			if ( $result['failed_count'] > 0 ) {
				WP_CLI::warning( $result['failed_count'] . ' sample(s) produced no payload. Review raw artifacts in ' . $result['raw_dir'] );
			}
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * List configured evaluation prompts.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the evaluation config file.
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
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function list_prompts( $args, $assoc_args ) {
		try {
			$items  = $this->get_service()->list_prompts( $assoc_args['config'] ?? '' );
			$format = $assoc_args['format'] ?? 'table';

			\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'user_input', 'reference' ) );
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * Validate evaluation config.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the evaluation config file.
	 *
	 * [--skip-connection-check]
	 * : Skip database connection validation. Useful for CI environments without a live WordPress connection.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function validate_config( $args, $assoc_args ) {
		try {
			$skip_connection = isset( $assoc_args['skip-connection-check'] );
			$summary = $this->get_service()->get_config_summary( $assoc_args['config'] ?? '', $skip_connection );
			$items   = array();

			foreach ( $summary as $key => $value ) {
				$items[] = array(
					'key'   => $key,
					'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
				);
			}

			\WP_CLI\Utils\format_items( 'table', $items, array( 'key', 'value' ) );
			WP_CLI::success( 'Evaluation config is valid.' );
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * List supported evaluation adapters.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function adapters( $args, $assoc_args ) {
		$items = array();

		foreach ( $this->get_service()->get_supported_frameworks() as $framework ) {
			$items[] = array(
				'framework' => $framework,
				'status'    => 'available',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'framework', 'status' ) );
	}
}
