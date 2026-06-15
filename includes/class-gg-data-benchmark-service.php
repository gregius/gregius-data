<?php
/**
 * Benchmark orchestration service.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception messages are not HTML output.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Throws are intentionally localized in this CLI service layer.
// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Preserve local readability for grouped assignments.

/**
 * Benchmark orchestration service for shipped WP-CLI commands.
 *
 * @since 1.0.0
 */
class GG_Data_Benchmark_Service {
	use GG_Data_Format_Json_Output;

	/**
	 * Abilities manager instance.
	 *
	 * @var GG_Data_Abilities_Manager|null
	 */
	private $abilities = null;

	/**
	 * Settings manager instance.
	 *
	 * @var GG_Data_Settings_Manager|null
	 */
	private $settings_manager = null;

	/**
	 * Constructor.
	 *
	 * @param GG_Data_Abilities_Manager|null $abilities_manager Optional injected abilities manager.
	 * @param GG_Data_Settings_Manager|null  $settings_manager  Optional injected settings manager.
	 */
	public function __construct( $abilities_manager = null, $settings_manager = null ) {
		if ( null !== $abilities_manager ) {
			$this->abilities = $abilities_manager;
		}
		if ( null !== $settings_manager ) {
			$this->settings_manager = $settings_manager;
		}
	}

	/**
	 * Get the abilities manager.
	 *
	 * @return GG_Data_Abilities_Manager
	 */
	private function get_abilities_manager() {
		if ( null === $this->abilities ) {
			$this->abilities = new GG_Data_Abilities_Manager();
		}

		return $this->abilities;
	}

	/**
	 * Get the settings manager.
	 *
	 * @return GG_Data_Settings_Manager
	 */
	private function get_settings_manager() {
		if ( null === $this->settings_manager ) {
			$this->settings_manager = new GG_Data_Settings_Manager();
		}

		return $this->settings_manager;
	}

	/**
	 * Get the shipped config template path.
	 *
	 * @return string
	 */
	public function get_template_path() {
		return GG_DATA_PLUGIN_DIR . 'includes/cli/resources/benchmark/rag-benchmark-quality-config.example.json';
	}

	/**
	 * Get the default local config path.
	 *
	 * @return string
	 */
	public function get_default_config_path() {
		return trailingslashit( $this->get_runtime_config_dir() ) . 'rag-benchmark-quality-config.json';
	}

	/**
	 * Get the default artifact root.
	 *
	 * @return string
	 */
	public function get_default_artifact_root() {
		return trailingslashit( $this->get_runtime_artifact_dir() ) . 'benchmark';
	}

	/**
	 * Get the benchmark config directory.
	 *
	 * @return string
	 */
	private function get_runtime_config_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'gregius-data/config';
	}

	/**
	 * Get the benchmark artifact directory.
	 *
	 * @return string
	 */
	private function get_runtime_artifact_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'gregius-data/artifacts';
	}

	/**
	 * Validate config path against path traversal.
	 *
	 * @param string $config_path The config path to validate.
	 * @return void
	 */
	private function validate_config_path( $config_path ) {
		$resolved = realpath( $config_path );

		if ( false === $resolved ) {
			return;
		}

		$allowed = realpath( $this->get_runtime_config_dir() );

		if ( false !== $allowed && 0 !== strpos( $resolved, $allowed ) ) {
			throw new RuntimeException(
				sprintf(
					'Config path %1$s resolves outside the allowed config directory %2$s.',
					$config_path,
					$allowed
				)
			);
		}
	}

	/**
	 * Load and normalize benchmark config.
	 *
	 * @param string $config_path Config path override.
	 * @return array
	 */
	public function load_config( $config_path = '' ) {
		$config_path = '' !== $config_path ? $config_path : $this->get_default_config_path();
		$this->validate_config_path( $config_path );

		if ( ! file_exists( $config_path ) ) {
			throw new RuntimeException(
				sprintf(
					'Benchmark config not found at %1$s. Copy %2$s to %3$s and update the connection/models before running.',
					$config_path,
					$this->get_template_path(),
					$this->get_default_config_path()
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI config file read.
		$raw_config = file_get_contents( $config_path );
		$spec       = json_decode( $raw_config, true );

		if ( ! is_array( $spec ) ) {
			throw new RuntimeException( 'Invalid JSON in benchmark config: ' . $config_path );
		}

		$this->assert_required_fields( $spec );
		$this->assert_required_models( $spec );
		$this->assert_prompt_definitions( $spec );

		$artifact_root = isset( $spec['artifact_root'] ) && '' !== trim( (string) $spec['artifact_root'] )
			? trim( (string) $spec['artifact_root'] )
			: $this->get_default_artifact_root();

		return array(
			'config_path'      => $config_path,
			'plugin_root'      => GG_DATA_PLUGIN_DIR,
			'artifact_root'    => $artifact_root,
			'wp_url'           => isset( $spec['url'] ) && '' !== trim( (string) $spec['url'] ) ? trim( (string) $spec['url'] ) : '',
			'connection'       => trim( (string) $spec['connection'] ),
			'embedding_model'  => trim( (string) $spec['models']['embedding'] ),
			'agentic_model'    => trim( (string) $spec['models']['agentic'] ),
			'rerank_model'     => trim( (string) $spec['models']['rerank'] ),
			'answer_model'     => trim( (string) $spec['models']['answer'] ),
			'prompts'          => $spec['prompts'],
			'template_path'    => $this->get_template_path(),
			'default_run_root' => $this->get_default_artifact_root(),
		);
	}

	/**
	 * Validate the connection exists.
	 *
	 * @param string $connection Connection name.
	 * @return void
	 */
	public function assert_connection_exists( $connection ) {
		$connections = $this->get_settings_manager()->get_all_connections();

		if ( isset( $connections[ $connection ] ) ) {
			return;
		}

		throw new RuntimeException(
			sprintf(
				'Invalid connection: %s. Use "wp gg-data list-connections" to see available connections.',
				$connection
			)
		);
	}

	/**
	 * List prompts from config.
	 *
	 * @param string $config_path Config path override.
	 * @return array
	 */
	public function list_prompts( $config_path = '' ) {
		$config = $this->load_config( $config_path );

		$items = array();
		foreach ( $config['prompts'] as $prompt ) {
			$items[] = array(
				'id'       => $prompt['id'],
				'slug'     => $prompt['slug'],
				'prompt'   => $prompt['prompt'],
				'expected' => $prompt['expected'],
			);
		}

		return $items;
	}

	/**
	 * Run the benchmark suite.
	 *
	 * @param array $assoc_args CLI associative arguments.
	 * @return array
	 */
	public function run( $assoc_args ) {
		$config = $this->load_config( $assoc_args['config'] ?? '' );
		$this->assert_connection_exists( $config['connection'] );

		$only_id         = isset( $assoc_args['only'] ) ? trim( (string) $assoc_args['only'] ) : '';
		$scorecard_scope = isset( $assoc_args['scorecard-scope'] ) ? trim( (string) $assoc_args['scorecard-scope'] ) : 'all';
		$output_root     = isset( $assoc_args['output-dir'] ) ? trim( (string) $assoc_args['output-dir'] ) : '';

		if ( '' !== $only_id && ! $this->prompt_id_exists( $config['prompts'], $only_id ) ) {
			throw new RuntimeException( 'Unknown prompt ID for --only: ' . $only_id );
		}

		if ( ! in_array( $scorecard_scope, array( 'all', 'selected' ), true ) ) {
			throw new RuntimeException( 'Invalid value for --scorecard-scope: ' . $scorecard_scope );
		}

		$execution_prompts = $this->filter_prompts( $config['prompts'], $only_id );
		$scorecard_prompts = 'selected' === $scorecard_scope ? $execution_prompts : $config['prompts'];
		$timestamp         = gmdate( 'Ymd-His' );
		$output_dir        = '' !== $output_root ? trailingslashit( $output_root ) . $timestamp : trailingslashit( $config['artifact_root'] ) . $timestamp;

		$this->ensure_directory( $output_dir );
		$scorecard_path = trailingslashit( $output_dir ) . 'scorecard.md';

		$rag_service = new GG_Data_RAG_Service( $config['connection'], $config['embedding_model'] );
		$results     = array();
		foreach ( $execution_prompts as $prompt ) {
			$results[] = $this->run_prompt( $prompt, $output_dir, $config, $rag_service );
		}

		$this->write_scorecard( $scorecard_path, $output_dir, $timestamp, $config, $scorecard_prompts, $scorecard_scope );

		return array(
			'output_dir'      => $output_dir,
			'scorecard_path'  => $scorecard_path,
			'timestamp'       => $timestamp,
			'prompts_run'     => $results,
			'scorecard_scope' => $scorecard_scope,
			'config'          => $config,
		);
	}

	/**
	 * Generate a scorecard without running prompts.
	 *
	 * @param array $assoc_args CLI associative arguments.
	 * @return array
	 */
	public function generate_scorecard( $assoc_args ) {
		$config          = $this->load_config( $assoc_args['config'] ?? '' );
		$scorecard_scope = isset( $assoc_args['scorecard-scope'] ) ? trim( (string) $assoc_args['scorecard-scope'] ) : 'all';
		$only_id         = isset( $assoc_args['only'] ) ? trim( (string) $assoc_args['only'] ) : '';
		$output_root     = isset( $assoc_args['output-dir'] ) ? trim( (string) $assoc_args['output-dir'] ) : '';
		$from_dir        = isset( $assoc_args['from-dir'] ) ? trim( (string) $assoc_args['from-dir'] ) : '';

		if ( ! in_array( $scorecard_scope, array( 'all', 'selected' ), true ) ) {
			throw new RuntimeException( 'Invalid value for --scorecard-scope: ' . $scorecard_scope );
		}

		$execution_prompts = $this->filter_prompts( $config['prompts'], $only_id );
		$scorecard_prompts = 'selected' === $scorecard_scope ? $execution_prompts : $config['prompts'];

		$actual_behaviors = array();
		if ( '' !== $from_dir ) {
			if ( ! is_dir( $from_dir ) ) {
				throw new RuntimeException( '--from-dir does not exist or is not a directory: ' . $from_dir );
			}

			$from_dir = trailingslashit( $from_dir );

			foreach ( $scorecard_prompts as $prompt ) {
				$json_file = $from_dir . $prompt['slug'] . '.json';
				if ( ! file_exists( $json_file ) ) {
					continue;
				}

				$json_content = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
				if ( false === $json_content ) {
					continue;
				}

				$data = json_decode( $json_content, true );
				if ( ! is_array( $data ) || ! isset( $data['answer'] ) ) {
					continue;
				}

				$actual_behaviors[ $prompt['slug'] ] = $data['answer'];
			}
		}

		$timestamp  = gmdate( 'Ymd-His' );
		$output_dir = '' !== $from_dir ? $from_dir : ( '' !== $output_root ? trailingslashit( $output_root ) . $timestamp : trailingslashit( $config['artifact_root'] ) . $timestamp );

		$this->ensure_directory( $output_dir );
		$scorecard_path = trailingslashit( $output_dir ) . 'scorecard.md';
		$this->write_scorecard( $scorecard_path, $output_dir, $timestamp, $config, $scorecard_prompts, $scorecard_scope, $actual_behaviors );

		return array(
			'output_dir'       => $output_dir,
			'scorecard_path'   => $scorecard_path,
			'timestamp'        => $timestamp,
			'prompt_count'     => count( $scorecard_prompts ),
			'from_dir'         => $from_dir,
			'filled_count'     => count( $actual_behaviors ),
		);
	}

	/**
	 * Validate config and return a summary.
	 *
	 * @param string $config_path          Config path override.
	 * @param bool   $skip_connection_check Skip database connection validation.
	 * @return array
	 */
	public function get_config_summary( $config_path = '', $skip_connection_check = false ) {
		$config = $this->load_config( $config_path );

		if ( ! $skip_connection_check ) {
			$this->assert_connection_exists( $config['connection'] );
		}

		return array(
			'config_path'     => $config['config_path'],
			'template_path'   => $config['template_path'],
			'artifact_root'   => $config['artifact_root'],
			'connection'      => $config['connection'],
			'wp_url'          => $config['wp_url'],
			'embedding_model' => $config['embedding_model'],
			'agentic_model'   => $config['agentic_model'],
			'rerank_model'    => $config['rerank_model'],
			'answer_model'    => $config['answer_model'],
			'prompt_count'    => count( $config['prompts'] ),
		);
	}

	/**
	 * Run an individual prompt.
	 *
	 * @param array  $prompt      Prompt configuration.
	 * @param string               $output_dir  Output directory.
	 * @param array                 $config      Normalized config.
	 * @param GG_Data_RAG_Service|null $rag_service Pre-built RAG service instance.
	 * @return array
	 */
	private function run_prompt( $prompt, $output_dir, $config, $rag_service = null ) {
		$raw_path  = trailingslashit( $output_dir ) . $prompt['slug'] . '.raw.txt';
		$json_path = trailingslashit( $output_dir ) . $prompt['slug'] . '.json';
		$start     = microtime( true );

		$result = $this->get_abilities_manager()->execute_rag_answer(
			array(
				'query'           => $prompt['prompt'],
				'connection_name' => $config['connection'],
				'embedding_model' => $config['embedding_model'],
				'agentic_model'   => $config['agentic_model'],
				'rerank_model'    => $config['rerank_model'],
				'answer_model'    => $config['answer_model'],
				'prompt_id'       => isset( $prompt['prompt_id'] ) ? absint( $prompt['prompt_id'] ) : 0,
				'conversation_id' => '',
				'source'          => array(
					'type' => 'benchmark-cli',
				),
				'messages'        => array(),
			),
			$rag_service
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'id'          => $prompt['id'],
				'slug'        => $prompt['slug'],
				'failed'      => true,
				'error'       => $result->get_error_message(),
				'json_path'   => '',
				'raw_path'    => '',
				'duration_ms' => 0,
			);
		}

		$duration_ms   = (int) round( ( microtime( true ) - $start ) * 1000 );
		$json_payload  = $this->format_json_output( $result, $duration_ms, $prompt['prompt'] );
		$raw_artifact  = $this->format_raw_output( $result, $duration_ms, $prompt, $config );
		$json_encoded  = wp_json_encode( $json_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$this->put_contents( $raw_path, $raw_artifact );
		$this->put_contents( $json_path, false !== $json_encoded ? $json_encoded : '' );

		return array(
			'id'        => $prompt['id'],
			'slug'      => $prompt['slug'],
			'raw_path'  => $raw_path,
			'json_path' => $json_path,
			'duration'  => $duration_ms,
			'answer'    => $json_payload['answer'],
		);
	}

	/**
	 * Build scorecard markdown.
	 *
	 * @param string $scorecard_path   Scorecard file path.
	 * @param string $output_dir       Output directory.
	 * @param string $timestamp        Run timestamp.
	 * @param array  $config           Normalized config.
	 * @param array  $scorecard_prompts Scorecard prompt rows.
	 * @param string $scorecard_scope  Scorecard scope.
	 * @param array  $actual_behaviors Map of slug to answer text (from artifact re-reading).
	 * @return void
	 */
	private function write_scorecard( $scorecard_path, $output_dir, $timestamp, $config, $scorecard_prompts, $scorecard_scope, $actual_behaviors = array() ) {
		$rows = array();

		foreach ( $scorecard_prompts as $prompt ) {
			$slug            = $prompt['slug'];
			$actual_behavior = isset( $actual_behaviors[ $slug ] ) ? $actual_behaviors[ $slug ] : '';

			$rows[] = sprintf(
				'| %1$s | `%2$s` | %3$s | [%4$s](%5$s) | [%6$s](%7$s) | %8$s |  |  |',
				$prompt['id'],
				$prompt['prompt'],
				$prompt['expected'],
				$slug . '.raw.txt',
				'./' . $slug . '.raw.txt',
				$slug . '.json',
				'./' . $slug . '.json',
				$actual_behavior
			);
		}

		$scope_summary = sprintf( '%1$s (%2$d/%3$d prompts)', $scorecard_scope, count( $scorecard_prompts ), count( $config['prompts'] ) );

		$content  = "# RAG Quality Benchmark Scorecard\n\n";
		$content .= '**Run Timestamp:** ' . $timestamp . "  \n";
		$content .= '**Config Path:** ' . $config['config_path'] . "  \n";
		$content .= '**Site URL:** ' . $config['wp_url'] . "  \n";
		$content .= '**Artifact Root:** ' . $config['artifact_root'] . "  \n";
		$content .= "**Command:** `wp gg-data benchmark run`  \n";
		$content .= '**Scorecard Scope:** ' . $scope_summary . "\n\n";
		$content .= "## Manual Scoring Matrix\n\n";
		$content .= "| ID | Prompt | Expected Outcome | Raw Output | JSON Artifact | Actual Behavior | Verdict | Notes |\n";
		$content .= "|---|---|---|---|---|---|---|---|\n";
		$content .= implode( PHP_EOL, $rows ) . "\n\n";
		$content .= "## Notes\n\n";
		$content .= "1. Benchmark artifacts are stored outside the plugin directory to survive plugin updates.\n";
		$content .= "2. Each prompt writes a raw audit file and a normalized JSON artifact.\n";
		$content .= "3. Use `wp gg-data benchmark validate-config` before running if the config changes.\n";

		$this->put_contents( $scorecard_path, $content );
	}

	/**
	 * Format a human-readable raw audit artifact.
	 *
	 * @param array $result      RAG result.
	 * @param int   $duration_ms Duration in milliseconds.
	 * @param array $prompt      Prompt configuration.
	 * @param array $config      Normalized config.
	 * @return string
	 */
	private function format_raw_output( $result, $duration_ms, $prompt, $config ) {
		$payload      = $this->format_json_output( $result, $duration_ms, $prompt['prompt'] );
		$json_encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$content  = 'Prompt ID: ' . $prompt['id'] . PHP_EOL;
		$content .= 'Prompt Slug: ' . $prompt['slug'] . PHP_EOL;
		$content .= 'Connection: ' . $config['connection'] . PHP_EOL;
		$content .= 'Embedding Model: ' . $config['embedding_model'] . PHP_EOL;
		$content .= 'Agentic Model: ' . $config['agentic_model'] . PHP_EOL;
		$content .= 'Rerank Model: ' . $config['rerank_model'] . PHP_EOL;
		$content .= 'Answer Model: ' . $config['answer_model'] . PHP_EOL;
		$content .= 'Duration: ' . $duration_ms . 'ms' . PHP_EOL . PHP_EOL;
		$content .= false !== $json_encoded ? $json_encoded : '';

		return $content;
	}

	/**
	 * Ensure a directory exists.
	 *
	 * @param string $path Directory path.
	 * @return void
	 */
	private function ensure_directory( $path ) {
		if ( is_dir( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local artifact directory creation.
		if ( ! mkdir( $path, 0755, true ) && ! is_dir( $path ) ) {
			throw new RuntimeException( 'Failed to create directory: ' . $path );
		}
	}

	/**
	 * Write file contents.
	 *
	 * @param string $path    File path.
	 * @param string $content File contents.
	 * @return void
	 */
	private function put_contents( $path, $content ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local CLI artifact write.
		$result = file_put_contents( $path, $content );

		if ( false === $result ) {
			throw new RuntimeException( 'Failed to write file: ' . $path );
		}
	}

	/**
	 * Validate top-level required fields.
	 *
	 * @param array $spec Raw config spec.
	 * @return void
	 */
	private function assert_required_fields( $spec ) {
		$missing = array();

		foreach ( array( 'connection' ) as $field ) {
			if ( ! isset( $spec[ $field ] ) || '' === trim( (string) $spec[ $field ] ) ) {
				$missing[] = $field;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		throw new RuntimeException( 'Missing required benchmark config fields: ' . implode( ', ', $missing ) );
	}

	/**
	 * Validate required model fields.
	 *
	 * @param array $spec Raw config spec.
	 * @return void
	 */
	private function assert_required_models( $spec ) {
		$models  = isset( $spec['models'] ) && is_array( $spec['models'] ) ? $spec['models'] : array();
		$missing = array();

		foreach ( array( 'embedding', 'agentic', 'rerank', 'answer' ) as $field ) {
			if ( ! isset( $models[ $field ] ) || '' === trim( (string) $models[ $field ] ) ) {
				$missing[] = 'models.' . $field;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		throw new RuntimeException( 'Missing required benchmark model fields: ' . implode( ', ', $missing ) );
	}

	/**
	 * Validate prompt definitions.
	 *
	 * @param array $spec Raw config spec.
	 * @return void
	 */
	private function assert_prompt_definitions( $spec ) {
		if ( ! isset( $spec['prompts'] ) || ! is_array( $spec['prompts'] ) || empty( $spec['prompts'] ) ) {
			throw new RuntimeException( 'Benchmark config must define a non-empty prompts array.' );
		}

		$seen_ids = array();

		foreach ( $spec['prompts'] as $index => $prompt ) {
			foreach ( array( 'id', 'slug', 'prompt', 'expected' ) as $field ) {
				if ( ! isset( $prompt[ $field ] ) || '' === trim( (string) $prompt[ $field ] ) ) {
					throw new RuntimeException(
						sprintf( 'Benchmark prompt %1$d is missing required field "%2$s".', $index, $field )
					);
				}
			}

			if ( isset( $seen_ids[ $prompt['id'] ] ) ) {
				throw new RuntimeException( 'Duplicate benchmark prompt id: ' . $prompt['id'] );
			}

			$seen_ids[ $prompt['id'] ] = true;
		}
	}

	/**
	 * Filter prompts by optional prompt ID.
	 *
	 * @param array  $prompts  Prompt list.
	 * @param string $only_id  Optional prompt ID.
	 * @return array
	 */
	private function filter_prompts( $prompts, $only_id ) {
		if ( '' === $only_id ) {
			return $prompts;
		}

		$filtered = array();
		foreach ( $prompts as $prompt ) {
			if ( $prompt['id'] === $only_id ) {
				$filtered[] = $prompt;
			}
		}

		return $filtered;
	}

	/**
	 * Check whether a prompt ID exists.
	 *
	 * @param array  $prompts  Prompt list.
	 * @param string $prompt_id Prompt ID.
	 * @return bool
	 */
	private function prompt_id_exists( $prompts, $prompt_id ) {
		foreach ( $prompts as $prompt ) {
			if ( $prompt['id'] === $prompt_id ) {
				return true;
			}
		}

		return false;
	}
}

// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped