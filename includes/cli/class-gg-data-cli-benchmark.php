<?php
/**
 * WP-CLI benchmark command family.
 *
 * @package Gregius_Data
 * @subpackage CLI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Benchmark commands for gg-data.
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Benchmark {

	/**
	 * Benchmark service.
	 *
	 * @var GG_Data_Benchmark_Service|null
	 */
	private $service = null;

	/**
	 * Get the benchmark service.
	 *
	 * @return GG_Data_Benchmark_Service
	 */
	private function get_service() {
		if ( null === $this->service ) {
			$this->service = new GG_Data_Benchmark_Service();
		}

		return $this->service;
	}

	/**
	 * Run the benchmark prompts.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the benchmark config file.
	 *
	 * [--only=<id>]
	 * : Run a single prompt by ID.
	 *
	 * [--scorecard-scope=<scope>]
	 * : Scorecard row scope.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - selected
	 * ---
	 *
	 * [--output-dir=<path>]
	 * : Absolute output directory for artifacts.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp gg-data benchmark run
	 *     $ wp gg-data benchmark run --only=P1
	 *     $ wp gg-data benchmark run --config=/abs/path/rag-benchmark-quality-config.json
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

			foreach ( $result['prompts_run'] as $prompt ) {
				if ( ! empty( $prompt['failed'] ) ) {
					WP_CLI::warning( sprintf( 'Prompt %1$s failed: %2$s', $prompt['id'], $prompt['error'] ) );
				} else {
					WP_CLI::log( sprintf( 'Completed %1$s -> %2$s', $prompt['id'], $prompt['json_path'] ) );
				}
			}

			WP_CLI::success( 'Benchmark artifacts written to ' . $result['output_dir'] );
			WP_CLI::log( 'Scorecard: ' . $result['scorecard_path'] );
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * List configured benchmark prompts.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the benchmark config file.
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

			\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'slug', 'prompt', 'expected' ) );
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * Validate the benchmark config and output a summary.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the benchmark config file.
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
			WP_CLI::success( 'Benchmark config is valid.' );
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * Generate a scorecard without executing prompts.
	 *
	 * ## OPTIONS
	 *
	 * [--config=<path>]
	 * : Absolute path to the benchmark config file.
	 *
	 * [--only=<id>]
	 * : Limit scorecard rows to one prompt ID when used with selected scope.
	 *
	 * [--scorecard-scope=<scope>]
	 * : Scorecard row scope.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - selected
	 * ---
	 *
	 * [--from-dir=<path>]
	 * : Absolute path to a previous run's artifact directory. When set, reads
	 *   <slug>.json artifacts from that directory and populates the Actual
	 *   Behavior column. The scorecard is written in-place into the from-dir.
	 *
	 * [--output-dir=<path>]
	 * : Absolute output directory for scorecard artifacts. Ignored when
	 *   --from-dir is set.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function scorecard( $args, $assoc_args ) {
		try {
			$result = $this->get_service()->generate_scorecard( $assoc_args );

			$filled_msg = '';
			if ( $result['filled_count'] > 0 ) {
				$filled_msg = sprintf( ' (%d rows populated from artifacts)', $result['filled_count'] );
			}

			WP_CLI::success( 'Benchmark scorecard written to ' . $result['scorecard_path'] . $filled_msg );
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}
}
