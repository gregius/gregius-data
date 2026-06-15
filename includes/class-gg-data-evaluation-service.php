<?php
/**
 * Evaluation orchestration service.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception messages are not HTML output.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Throws are intentionally localized in this CLI service layer.
// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- Preserve local readability for grouped arrays.

/**
 * Evaluation orchestration service for shipped WP-CLI commands.
 *
 * @since 1.0.0
 */
class GG_Data_Evaluation_Service {
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
	 * Get supported adapters.
	 *
	 * @return array
	 */
	public function get_supported_frameworks() {
		return array( 'ragas' );
	}

	/**
	 * Get the shipped config template path.
	 *
	 * @return string
	 */
	public function get_template_path() {
		return GG_DATA_PLUGIN_DIR . 'includes/cli/resources/evaluation/rag-evaluation-config.example.json';
	}

	/**
	 * Get the default local config path.
	 *
	 * @return string
	 */
	public function get_default_config_path() {
		return trailingslashit( $this->get_runtime_config_dir() ) . 'rag-evaluation-config.json';
	}

	/**
	 * Get the default artifact root.
	 *
	 * @return string
	 */
	public function get_default_artifact_root() {
		return trailingslashit( $this->get_runtime_artifact_dir() ) . 'evaluation';
	}

	/**
	 * Get the config directory.
	 *
	 * @return string
	 */
	private function get_runtime_config_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'gregius-data/config';
	}

	/**
	 * Get the artifact directory.
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
	 * Load and normalize the evaluation config.
	 *
	 * @param string $config_path Config path override.
	 * @return array
	 */
	public function load_config( $config_path = '' ) {
		$config_path = '' !== $config_path ? $config_path : $this->get_default_config_path();
		$this->validate_config_path( $config_path );

		if ( ! file_exists( $config_path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
			throw new RuntimeException(
				sprintf(
					'Evaluation config not found at %1$s. Copy %2$s to %3$s and update the connection/models before running.',
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
			throw new RuntimeException( 'Invalid JSON in evaluation config: ' . $config_path );
		}

		$this->assert_required_fields( $spec );
		$this->assert_required_models( $spec );
		$this->assert_prompt_definitions( $spec );
		$this->assert_supported_framework( trim( (string) $spec['framework'] ) );

		$artifact_root = isset( $spec['artifact_root'] ) && '' !== trim( (string) $spec['artifact_root'] )
			? trim( (string) $spec['artifact_root'] )
			: $this->get_default_artifact_root();

		return array(
			'config_path'     => $config_path,
			'wp_url'          => isset( $spec['url'] ) && '' !== trim( (string) $spec['url'] ) ? trim( (string) $spec['url'] ) : '',
			'artifact_root'   => $artifact_root,
			'template_path'   => $this->get_template_path(),
			'connection'      => trim( (string) $spec['connection'] ),
			'framework'       => trim( (string) $spec['framework'] ),
			'sample_type'     => isset( $spec['sample_type'] ) && '' !== trim( (string) $spec['sample_type'] ) ? trim( (string) $spec['sample_type'] ) : 'single-turn',
			'embedding_model' => trim( (string) $spec['models']['embedding'] ),
			'agentic_model'   => isset( $spec['models']['agentic'] ) ? trim( (string) $spec['models']['agentic'] ) : '',
			'rerank_model'    => isset( $spec['models']['rerank'] ) ? trim( (string) $spec['models']['rerank'] ) : '',
			'answer_model'    => trim( (string) $spec['models']['answer'] ),
			'prompts'         => $spec['prompts'],
		);
	}

	/**
	 * Validate connection exists.
	 *
	 * @param string $connection Connection name.
	 * @return void
	 */
	public function assert_connection_exists( $connection ) {
		$connections = $this->get_settings_manager()->get_all_connections();

		if ( isset( $connections[ $connection ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
		throw new RuntimeException(
			sprintf(
				'Invalid connection: %s. Use "wp gg-data list-connections" to see available connections.',
				$connection
			)
		);
	}

	/**
	 * Get configured prompt rows.
	 *
	 * @param string $config_path Config path override.
	 * @return array
	 */
	public function list_prompts( $config_path = '' ) {
		$config = $this->load_config( $config_path );

		$items = array();
		foreach ( $config['prompts'] as $prompt ) {
			$items[] = array(
				'id'         => $prompt['id'],
				'user_input' => $prompt['user_input'],
				'reference'  => isset( $prompt['reference'] ) ? $prompt['reference'] : '',
			);
		}

		return $items;
	}

	/**
	 * Get config summary.
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
			'wp_url'          => $config['wp_url'],
			'template_path'   => $config['template_path'],
			'artifact_root'   => $config['artifact_root'],
			'connection'      => $config['connection'],
			'framework'       => $config['framework'],
			'sample_type'     => $config['sample_type'],
			'embedding_model' => $config['embedding_model'],
			'agentic_model'   => $config['agentic_model'],
			'rerank_model'    => $config['rerank_model'],
			'answer_model'    => $config['answer_model'],
			'prompt_count'    => count( $config['prompts'] ),
		);
	}

	/**
	 * Run evaluation.
	 *
	 * @param array $assoc_args CLI associative args.
	 * @return array
	 */
	public function run( $assoc_args ) {
		$config = $this->load_config( $assoc_args['config'] ?? '' );
		$this->assert_connection_exists( $config['connection'] );

		$only_id            = isset( $assoc_args['only'] ) ? trim( (string) $assoc_args['only'] ) : '';
		$framework_override = isset( $assoc_args['framework'] ) ? trim( (string) $assoc_args['framework'] ) : '';
		$output_root        = isset( $assoc_args['output-dir'] ) ? trim( (string) $assoc_args['output-dir'] ) : '';

		if ( '' !== $framework_override ) {
			$this->assert_supported_framework( $framework_override );
			$config['framework'] = $framework_override;
		}

		if ( '' !== $only_id && ! $this->prompt_id_exists( $config['prompts'], $only_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
			throw new RuntimeException( 'Unknown sample ID for --only: ' . $only_id );
		}

		$execution_samples = $this->filter_prompts( $config['prompts'], $only_id );
		$scope             = '' !== $only_id ? 'selected' : 'all';
		$timestamp         = gmdate( 'Ymd-His' );
		$output_dir        = '' !== $output_root ? trailingslashit( $output_root ) . $timestamp : trailingslashit( $config['artifact_root'] ) . $timestamp;
		$raw_dir           = trailingslashit( $output_dir ) . 'raw';

		$this->ensure_directory( $raw_dir );

		$rag_service       = new GG_Data_RAG_Service( $config['connection'], $config['embedding_model'] );
		$canonical_records = array();
		$error_records     = array();
		$results           = array();
		$failed_count      = 0;

		foreach ( $execution_samples as $index => $sample ) {
			$execution = $this->run_sample( $sample, $index + 1, $raw_dir, $config, $rag_service );
			$results[] = $execution;

			if ( empty( $execution['payload'] ) ) {
				++$failed_count;
				$error_records[] = array(
					'id'            => $sample['id'],
					'user_input'    => $sample['user_input'],
					'error_message' => isset( $execution['error_message'] ) ? $execution['error_message'] : 'Unknown error',
				);
			} else {
				$canonical_records[] = $this->build_canonical_sample( $sample, $execution['payload'] );
			}
		}

		$canonical_path = $this->write_canonical( $output_dir, $canonical_records );
		$canonical_sha  = file_exists( $canonical_path ) ? hash_file( 'sha256', $canonical_path ) : '';

		$error_path = '';
		if ( ! empty( $error_records ) ) {
			$error_path = $this->write_errors( $output_dir, $error_records );
		}
		$error_sha = '' !== $error_path && file_exists( $error_path ) ? hash_file( 'sha256', $error_path ) : '';

		$failure_breakdown = array();
		foreach ( $error_records as $rec ) {
			$msg = isset( $rec['error_message'] ) ? $rec['error_message'] : 'Unknown error';
			if ( ! isset( $failure_breakdown[ $msg ] ) ) {
				$failure_breakdown[ $msg ] = 0;
			}
			++$failure_breakdown[ $msg ];
		}

		$adapter_paths = $this->dispatch_framework( $output_dir, $config['framework'], $canonical_records );
		$this->write_manifest(
			$output_dir,
			$timestamp,
			$config['framework'],
			$config['sample_type'],
			count( $config['prompts'] ),
			$scope,
			count( $execution_samples ),
			$failed_count,
			$failure_breakdown,
			$canonical_path,
			$canonical_sha,
			$error_sha,
			$error_path,
			$adapter_paths,
			$config['config_path'],
			$config['wp_url']
		);

		return array(
			'output_dir'      => $output_dir,
			'raw_dir'         => $raw_dir,
			'canonical_path'  => $canonical_path,
			'adapter_paths'   => $adapter_paths,
			'manifest_path'   => trailingslashit( $output_dir ) . 'manifest.json',
			'failed_count'    => $failed_count,
			'executed_count'  => count( $execution_samples ),
			'framework'       => $config['framework'],
			'prompt_results'  => $results,
		);
	}

	/**
	 * Run one evaluation sample.
	 *
	 * @param array                 $sample      Sample configuration.
	 * @param int                   $index       1-based index.
	 * @param string                $raw_dir     Raw output directory.
	 * @param array                 $config      Normalized config.
	 * @param GG_Data_RAG_Service|null $rag_service Pre-built RAG service instance.
	 * @return array
	 */
	private function run_sample( $sample, $index, $raw_dir, $config, $rag_service = null ) {
		$prefix    = sprintf( '%02d', $index );
		$raw_path  = trailingslashit( $raw_dir ) . $prefix . '.raw.txt';
		$json_path = trailingslashit( $raw_dir ) . $prefix . '.json';
		$start     = microtime( true );

		$result = $this->get_abilities_manager()->execute_rag_answer(
			array(
				'query'           => $sample['user_input'],
				'connection_name' => $config['connection'],
				'embedding_model' => $config['embedding_model'],
				'agentic_model'   => $config['agentic_model'],
				'rerank_model'    => $config['rerank_model'],
				'answer_model'    => $config['answer_model'],
				'prompt_id'       => isset( $sample['prompt_id'] ) ? absint( $sample['prompt_id'] ) : 0,
				'conversation_id' => '',
				'source'          => array(
					'type' => 'evaluation-cli',
				),
				'messages'        => array(),
			),
			$rag_service
		);

		$payload = array();
		if ( ! is_wp_error( $result ) ) {
			$duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );
			$payload     = $this->format_json_output( $result, $duration_ms, $sample['user_input'] );
			$json        = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			$raw_output  = $this->format_raw_output( $sample, $config, $duration_ms, false !== $json ? $json : '' );
			$this->put_contents( $raw_path, $raw_output );
			$this->put_contents( $json_path, false !== $json ? $json : '' );

			return array(
				'id'        => $sample['id'],
				'raw_path'  => $raw_path,
				'json_path' => $json_path,
				'payload'   => $payload,
				'failed'    => false,
			);
		}

		$raw_output = $this->format_raw_output( $sample, $config, 0, $result->get_error_message() );
		$this->put_contents( $raw_path, $raw_output );
		$this->put_contents( $json_path, '' );

		return array(
			'id'        => $sample['id'],
			'raw_path'  => $raw_path,
			'json_path' => $json_path,
			'payload'   => array(),
			'failed'    => true,
			'error_message' => $result->get_error_message(),
		);
	}

	/**
	 * Write canonical JSONL dataset.
	 *
	 * @param string $output_dir Run output dir.
	 * @param array  $records    Canonical records.
	 * @return string
	 */
	private function write_canonical( $output_dir, $records ) {
		$path  = trailingslashit( $output_dir ) . 'prompts.jsonl';
		$lines = array();

		foreach ( $records as $record ) {
			$lines[] = wp_json_encode( $record, JSON_UNESCAPED_SLASHES );
		}

		$this->put_contents( $path, implode( PHP_EOL, $lines ) . PHP_EOL );

		return $path;
	}

	/**
	 * Write errors JSONL dataset.
	 *
	 * @param string $output_dir Run output dir.
	 * @param array  $records    Error records with id, user_input, error_message.
	 * @return string
	 */
	private function write_errors( $output_dir, $records ) {
		$path  = trailingslashit( $output_dir ) . 'errors.jsonl';
		$lines = array();

		foreach ( $records as $record ) {
			$lines[] = wp_json_encode( $record, JSON_UNESCAPED_SLASHES );
		}

		$this->put_contents( $path, implode( PHP_EOL, $lines ) . PHP_EOL );

		return $path;
	}

	/**
	 * Dispatch framework adapters.
	 *
	 * @param string $output_dir Run output dir.
	 * @param string $framework  Framework name.
	 * @param array  $records    Canonical records.
	 * @return array
	 */
	private function dispatch_framework( $output_dir, $framework, $records ) {
		$adapter_paths = array();

		if ( 'ragas' === $framework ) {
			$ragas_dir = trailingslashit( $output_dir ) . 'frameworks/ragas';
			$this->ensure_directory( $ragas_dir );
			$path  = trailingslashit( $ragas_dir ) . 'dataset.jsonl';
			$lines = array();

			foreach ( $records as $record ) {
				$adapter_record = array(
					'user_input'         => $record['user_input'],
					'response'           => $record['response'],
					'retrieved_contexts' => $record['retrieved_contexts'],
				);

				foreach ( array( 'reference', 'reference_contexts', 'rubric' ) as $optional ) {
					if ( isset( $record[ $optional ] ) && ! empty( $record[ $optional ] ) ) {
						$adapter_record[ $optional ] = $record[ $optional ];
					}
				}

				$lines[] = wp_json_encode( $adapter_record, JSON_UNESCAPED_SLASHES );
			}

			$this->put_contents( $path, implode( PHP_EOL, $lines ) . PHP_EOL );
			$adapter_paths['ragas'] = $path;
		}

		/**
		 * Extend evaluation framework adapter dispatch.
		 *
		 * Add a custom framework adapter by hooking into this filter.
		 * The callback receives the current adapter paths, output directory,
		 * framework name, and canonical records. Return the merged adapter paths.
		 *
		 * @param array  $adapter_paths Framework adapter paths.
		 * @param string $output_dir    Run output directory.
		 * @param string $framework     Framework name.
		 * @param array  $records       Canonical records.
		 */
		$adapter_paths = apply_filters( 'gg_data_rag_evaluation_framework_adapter', $adapter_paths, $output_dir, $framework, $records );

		return $adapter_paths;
	}

	/**
	 * Write the manifest.
	 *
	 * @param string $output_dir        Run output dir.
	 * @param string $timestamp         Run timestamp.
	 * @param string $framework         Framework name.
	 * @param string $sample_type       Sample type.
	 * @param int    $sample_count      Total prompt count.
	 * @param string $scope             Scope.
	 * @param int    $executed_count    Executed sample count.
	 * @param int    $failed_count      Failed sample count.
	 * @param array  $failure_breakdown Failure reason counts.
	 * @param string $prompts_path      Canonical dataset path.
	 * @param string $prompts_sha       Canonical dataset SHA-256.
	 * @param string $error_sha         Errors dataset SHA-256 (empty if none).
	 * @param string $error_path        Errors dataset file path (empty if none).
	 * @param array  $adapter_paths     Framework adapter paths.
	 * @param string $config_path       Evaluation config file path.
	 * @return void
	 */
	private function write_manifest( $output_dir, $timestamp, $framework, $sample_type, $sample_count, $scope, $executed_count, $failed_count, $failure_breakdown, $prompts_path, $prompts_sha, $error_sha, $error_path, $adapter_paths, $config_path, $wp_url = '' ) {
		$adapters = array();

		$relative_path = function ( $full_path ) use ( $output_dir ) {
			$rel = str_replace( trailingslashit( $output_dir ), '', $full_path );
			return ltrim( $rel, '/' );
		};

		foreach ( $adapter_paths as $adapter => $path ) {
			$adapter_sha = file_exists( $path ) ? hash_file( 'sha256', $path ) : '';
			$adapters[ $adapter ] = array(
				'path'   => 'frameworks/' . $adapter . '/' . basename( $path ),
				'sha256' => $adapter_sha,
			);
		}

		$manifest = array(
			'run_id'         => $timestamp,
			'created_at'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'schema_version' => 1,
			'framework'      => $framework,
			'sample_type'    => $sample_type,
			'sample_count'   => $sample_count,
			'scope'          => $scope,
			'executed_count' => $executed_count,
			'failed_count'   => $failed_count,
			'config'         => array(
				'path' => $config_path,
				'url'  => $wp_url,
			),
			'prompts'        => array(
				'path'   => $relative_path( $prompts_path ),
				'sha256' => $prompts_sha,
			),
			'errors'         => array(
				'path'   => '' !== $error_sha ? $relative_path( $error_path ) : '',
				'sha256' => $error_sha,
			),
			'scope_detail'   => array(
				'total'             => $sample_count,
				'executed'          => $executed_count,
				'failed'            => $failed_count,
				'failure_breakdown' => $failure_breakdown,
			),
			'adapters'       => $adapters,
		);

		$this->put_contents( trailingslashit( $output_dir ) . 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
	}

	/**
	 * Build canonical record.
	 *
	 * @param array $sample  Config sample.
	 * @param array $payload Normalized pipeline payload.
	 * @return array
	 */
	private function build_canonical_sample( $sample, $payload ) {
		$record = array(
			'id'                 => $sample['id'],
			'user_input'         => $sample['user_input'],
			'response'           => isset( $payload['answer'] ) ? $payload['answer'] : '',
			'retrieved_contexts' => isset( $payload['retrieved_contexts'] ) ? $payload['retrieved_contexts'] : array(),
		);

		foreach ( array( 'reference', 'reference_contexts', 'rubric' ) as $optional ) {
			if ( isset( $sample[ $optional ] ) && ! empty( $sample[ $optional ] ) ) {
				$record[ $optional ] = $sample[ $optional ];
			}
		}

		return $record;
	}

	/**
	 * Format raw artifact text.
	 *
	 * @param array  $sample       Sample config.
	 * @param array  $config       Normalized config.
	 * @param int    $duration_ms  Duration in milliseconds.
	 * @param string $payload_body JSON payload or error text.
	 * @return string
	 */
	private function format_raw_output( $sample, $config, $duration_ms, $payload_body ) {
		$content  = 'Sample ID: ' . $sample['id'] . PHP_EOL;
		$content .= 'Connection: ' . $config['connection'] . PHP_EOL;
		$content .= 'Framework: ' . $config['framework'] . PHP_EOL;
		$content .= 'Embedding Model: ' . $config['embedding_model'] . PHP_EOL;
		$content .= 'Answer Model: ' . $config['answer_model'] . PHP_EOL;
		$content .= 'Duration: ' . $duration_ms . 'ms' . PHP_EOL . PHP_EOL;
		$content .= $payload_body;

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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
			throw new RuntimeException( 'Failed to write file: ' . $path );
		}
	}

	/**
	 * Validate required fields.
	 *
	 * @param array $spec Raw config spec.
	 * @return void
	 */
	private function assert_required_fields( $spec ) {
		$missing = array();

		foreach ( array( 'connection', 'framework' ) as $field ) {
			if ( ! isset( $spec[ $field ] ) || '' === trim( (string) $spec[ $field ] ) ) {
				$missing[] = $field;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
		throw new RuntimeException( 'Missing required evaluation config fields: ' . implode( ', ', $missing ) );
	}

	/**
	 * Validate required models.
	 *
	 * @param array $spec Raw config spec.
	 * @return void
	 */
	private function assert_required_models( $spec ) {
		$models  = isset( $spec['models'] ) && is_array( $spec['models'] ) ? $spec['models'] : array();
		$missing = array();

		foreach ( array( 'embedding', 'answer' ) as $field ) {
			if ( ! isset( $models[ $field ] ) || '' === trim( (string) $models[ $field ] ) ) {
				$missing[] = 'models.' . $field;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
		throw new RuntimeException( 'Missing required evaluation model fields: ' . implode( ', ', $missing ) );
	}

	/**
	 * Validate prompts.
	 *
	 * @param array $spec Raw config spec.
	 * @return void
	 */
	private function assert_prompt_definitions( $spec ) {
		if ( ! isset( $spec['prompts'] ) || ! is_array( $spec['prompts'] ) || empty( $spec['prompts'] ) ) {
			throw new RuntimeException( 'Evaluation config must define a non-empty prompts array.' );
		}

		$seen_ids = array();

		foreach ( $spec['prompts'] as $index => $sample ) {
			foreach ( array( 'id', 'user_input' ) as $field ) {
				if ( ! isset( $sample[ $field ] ) || '' === trim( (string) $sample[ $field ] ) ) {
					throw new RuntimeException(
						sprintf( 'Evaluation prompt %1$d is missing required field "%2$s".', $index, $field )
					);
				}
			}

			if ( isset( $seen_ids[ $sample['id'] ] ) ) {
				throw new RuntimeException( 'Duplicate evaluation prompt id: ' . $sample['id'] );
			}

			$seen_ids[ $sample['id'] ] = true;
		}
	}

	/**
	 * Validate supported framework.
	 *
	 * @param string $framework Framework name.
	 * @return void
	 */
	private function assert_supported_framework( $framework ) {
		if ( in_array( $framework, $this->get_supported_frameworks(), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exception message, not HTML output.
		throw new RuntimeException(
			sprintf(
				'Unsupported evaluation framework: "%s". Supported frameworks: %s',
				$framework,
				implode( ', ', $this->get_supported_frameworks() )
			)
		);
	}

	/**
	 * Filter prompts by optional ID.
	 *
	 * @param array  $prompts Prompt list.
	 * @param string $only_id Optional ID.
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

// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped