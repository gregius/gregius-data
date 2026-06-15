<?php
/**
 * WP-CLI Answer Command
 *
 * Answer questions using RAG pipeline.
 * Mirrors the gregius-data/answer ability.
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
 * Answer questions using RAG pipeline.
 *
 * Mirrors the gregius-data/answer ability for CLI automation.
 *
 * ## EXAMPLES
 *
 *     # Simple question with defaults
 *     $ wp gg-data answer "What is dementia care?"
 *
 *     # With specific models
 *     $ wp gg-data answer "What are best practices?" --answer-model=gpt-4
 *
 *     # Output as JSON
 *     $ wp gg-data answer "What services?" --format=json
 *
 * @when after_wp_load
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Answer {

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
	 * Answer a question using RAG pipeline.
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : The question to answer.
	 *
	 * [--connection=<name>]
	 * : Database connection name.
	 * ---
	 * default: gregius-data
	 * ---
	 *
	 * [--embedding-model=<model>]
	 * : Model for semantic search.
	 * ---
	 * default: tfidf-300
	 * ---
	 *
	 * [--agentic-model=<model>]
	 * : Model for query routing (optional).
	 *
	 * [--rerank-model=<model>]
	 * : Model for reranking (optional).
	 *
	 * [--answer-model=<model>]
	 * : Model for generating response.
	 * ---
	 * default: gpt-4o-mini
	 * ---
	 *
	 * [--prompt-id=<id>]
	 * : Use a specific gg_prompt post ID.
	 *
	 * [--prompt=<identifier>]
	 * : Use a prompt by identifier (post ID, slug, or exact title).
	 *
	 * [--no-track]
	 * : Skip interaction recording (dev/testing).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Simple question with defaults
	 *     $ wp gg-data answer "What is dementia care?"
	 *
	 *     # Full model specification
	 *     $ wp gg-data answer "What are best practices?" \
	 *         --connection=gregius-data \
	 *         --embedding-model=tfidf-300 \
	 *         --answer-model=gpt-4
	 *
	 *     # Dev/testing - skip interaction recording
	 *     $ wp gg-data answer "test query" --no-track
	 *
	 *     # Output as JSON for automation
	 *     $ wp gg-data answer "What services do you offer?" --format=json
	 *
	 *     # Force a specific prompt by post ID
	 *     $ wp gg-data answer "What services?" --prompt-id=123
	 *
	 *     # Force a specific prompt by slug
	 *     $ wp gg-data answer "What services?" --prompt=factory-default
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		// Get query from positional argument.
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Query is required. Usage: wp gg-data answer "Your question here"' );
			return;
		}

		$query           = $args[0];
		$connection      = $assoc_args['connection'] ?? 'gregius-data';
		$embedding_model = $assoc_args['embedding-model'] ?? 'tfidf-300';
		$agentic_model   = $assoc_args['agentic-model'] ?? '';
		$rerank_model    = $assoc_args['rerank-model'] ?? '';
		$answer_model    = $assoc_args['answer-model'] ?? 'gpt-4o-mini';
		$no_track        = isset( $assoc_args['no-track'] );
		$format          = $assoc_args['format'] ?? 'table';
		$prompt_id       = $this->resolve_prompt_id( $assoc_args );
		$conversation_id = '';
		$source          = array(
			'type' => 'wpcli',
		);

		if ( is_wp_error( $prompt_id ) ) {
			WP_CLI::error( $prompt_id->get_error_message() );
			return;
		}

		// Validate connection exists.
		if ( ! $this->validate_connection( $connection ) ) {
			return;
		}

		$start = microtime( true );

		WP_CLI::log( sprintf( 'Processing query: "%s"', $query ) );
		WP_CLI::log( sprintf( 'Using connection: %s, embedding: %s, answer model: %s', $connection, $embedding_model, $answer_model ) );

		if ( $prompt_id > 0 ) {
			WP_CLI::log( sprintf( 'Using prompt override: #%d', $prompt_id ) );
		}

		// Optionally disable tracking for dev/testing.
		if ( $no_track ) {
			add_filter( 'gg_data_track_interaction', '__return_false' );
		} else {
			$conversation_id = wp_generate_uuid4();
		}

		// Execute RAG answer via Abilities Manager.
		$result = $this->get_abilities()->execute_rag_answer(
			array(
				'query'           => $query,
				'connection_name' => $connection,
				'embedding_model' => $embedding_model,
				'agentic_model'   => $agentic_model,
				'rerank_model'    => $rerank_model,
				'answer_model'    => $answer_model,
				'prompt_id'       => $prompt_id,
				'conversation_id' => $conversation_id,
				'source'          => $source,
				'messages'        => array(), // No conversation history in CLI.
			)
		);

		$duration = round( ( microtime( true ) - $start ) * 1000 );

		// Handle errors.
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( sprintf( 'RAG error: %s', $result->get_error_message() ) );
			return;
		}

		// Output results.
		$this->output_result( $result, $format, $duration, $query );
	}

	/**
	 * Validate connection exists
	 *
	 * @param string $connection Connection name.
	 * @return bool True if valid.
	 */
	private function validate_connection( $connection ) {
		$settings    = new GG_Data_Settings_Manager();
		$connections = $settings->get_all_connections();

		if ( ! isset( $connections[ $connection ] ) ) {
			WP_CLI::error(
				sprintf(
					'Invalid connection: %s. Use "wp gg-data list-connections" to see available connections.',
					$connection
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Resolve optional prompt override flags to a prompt post ID.
	 *
	 * @param array $assoc_args Associative CLI arguments.
	 * @return int|WP_Error
	 */
	private function resolve_prompt_id( $assoc_args ) {
		$raw_prompt_id = $assoc_args['prompt-id'] ?? null;
		$identifier    = $assoc_args['prompt'] ?? null;

		if ( null !== $raw_prompt_id && null !== $identifier ) {
			return new WP_Error(
				'gg_data_cli_prompt_conflict',
				__( 'Use either --prompt-id or --prompt, not both.', 'gregius-data' )
			);
		}

		if ( null === $raw_prompt_id && null === $identifier ) {
			return 0;
		}

		if ( null !== $raw_prompt_id ) {
			$prompt_id = absint( $raw_prompt_id );
			if ( $prompt_id <= 0 ) {
				return new WP_Error(
					'gg_data_cli_prompt_invalid_id',
					__( 'Invalid --prompt-id value. Provide a numeric gg_prompt post ID.', 'gregius-data' )
				);
			}

			return $this->validate_prompt_post_id( $prompt_id );
		}

		$identifier = trim( (string) $identifier );
		if ( '' === $identifier ) {
			return new WP_Error(
				'gg_data_cli_prompt_invalid_identifier',
				__( 'Invalid --prompt value. Provide a post ID, slug, or exact title.', 'gregius-data' )
			);
		}

		if ( ctype_digit( $identifier ) ) {
			return $this->validate_prompt_post_id( absint( $identifier ) );
		}

		$slug = sanitize_title( $identifier );
		$post = get_page_by_path( $slug, OBJECT, 'gg_prompt' );

		if ( $post instanceof WP_Post ) {
			return $this->validate_prompt_post_id( (int) $post->ID );
		}

		$matches = get_posts(
			array(
				'post_type'      => 'gg_prompt',
				'post_status'    => array( 'publish', 'draft' ),
				's'              => $identifier,
				'posts_per_page' => 20,
			)
		);

		$exact_title_ids = array();
		foreach ( $matches as $match ) {
			if ( 0 === strcasecmp( $match->post_title, $identifier ) ) {
				$exact_title_ids[] = (int) $match->ID;
			}
		}

		if ( 1 === count( $exact_title_ids ) ) {
			return $this->validate_prompt_post_id( $exact_title_ids[0] );
		}

		if ( count( $exact_title_ids ) > 1 ) {
			return new WP_Error(
				'gg_data_cli_prompt_ambiguous_title',
				__( 'Prompt title is ambiguous. Use --prompt-id with a specific post ID.', 'gregius-data' )
			);
		}

		return new WP_Error(
			'gg_data_cli_prompt_not_found',
			/* translators: %s: Prompt identifier (ID or title fragment). */
			sprintf( __( 'Prompt "%s" was not found.', 'gregius-data' ), $identifier )
		);
	}

	/**
	 * Validate that a prompt ID exists and belongs to gg_prompt post type.
	 *
	 * @param int $prompt_id Prompt post ID.
	 * @return int|WP_Error
	 */
	private function validate_prompt_post_id( $prompt_id ) {
		$post = get_post( $prompt_id );

		if ( ! $post || 'gg_prompt' !== $post->post_type ) {
			return new WP_Error(
				'gg_data_cli_prompt_not_found',
				/* translators: %d: Prompt post ID. */
				sprintf( __( 'Prompt #%d was not found.', 'gregius-data' ), $prompt_id )
			);
		}

		return (int) $post->ID;
	}

	/**
	 * Output RAG result
	 *
	 * @param array  $result   RAG response.
	 * @param string $format   Output format.
	 * @param int    $duration Duration in ms.
	 * @param string $query    Original query.
	 */
	private function output_result( $result, $format, $duration, $query ) {
		$answer   = $result['answer'] ?? '';
		$sources  = $result['sources'] ?? array();
		$metadata = $result['metadata'] ?? array();

		if ( 'json' === $format ) {
			$chunks = $result['chunks'] ?? array();

			$retrieved_contexts = array();
			foreach ( $chunks as $chunk ) {
				$content = isset( $chunk['content'] ) ? (string) $chunk['content'] : '';
				if ( '' !== $content ) {
					$retrieved_contexts[] = $content;
				}
			}

			$output = array(
				'query'              => $query,
				'answer'             => $answer,
				'retrieved_contexts' => $retrieved_contexts,
				'sources'            => $sources,
				'metadata'           => array_merge(
					$metadata,
					array(
						'cli_duration_ms' => $duration,
					)
				),
			);
			WP_CLI::log( wp_json_encode( $output, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format (default) - human readable output.
		WP_CLI::log( '' );
		WP_CLI::log( '=== Answer ===' );
		WP_CLI::log( $answer );
		WP_CLI::log( '' );

		// Display sources if available.
		if ( ! empty( $sources ) ) {
			WP_CLI::log( '=== Sources ===' );
			$source_items = array();
			foreach ( $sources as $source ) {
				$source_items[] = array(
					'title' => $source['title'] ?? 'Untitled',
					'url'   => $source['url'] ?? '',
				);
			}
			\WP_CLI\Utils\format_items( 'table', $source_items, array( 'title', 'url' ) );
			WP_CLI::log( '' );
		}

		// Display metadata.
		WP_CLI::log( '=== Metadata ===' );
		$meta_items = array(
			array(
				'key'   => 'Tool Used',
				'value' => $metadata['tool'] ?? 'search_content',
			),
			array(
				'key'   => 'Chunks Used',
				'value' => $metadata['chunks_used'] ?? 0,
			),
			array(
				'key'   => 'Embedding Model',
				'value' => $metadata['embedding_model'] ?? '',
			),
			array(
				'key'   => 'Answer Model',
				'value' => $metadata['answer_model'] ?? '',
			),
			array(
				'key'   => 'Connection',
				'value' => $metadata['connection'] ?? '',
			),
			array(
				'key'   => 'Execution Time',
				'value' => sprintf( '%dms', $metadata['execution_time'] ?? $duration ),
			),
		);

		// Add optional models if used.
		if ( ! empty( $metadata['agentic_model'] ) ) {
			$meta_items[] = array(
				'key'   => 'Agentic Model',
				'value' => $metadata['agentic_model'],
			);
		}
		if ( ! empty( $metadata['rerank_model'] ) ) {
			$meta_items[] = array(
				'key'   => 'Rerank Model',
				'value' => $metadata['rerank_model'],
			);
		}

		if ( ! empty( $metadata['prompt'] ) && is_array( $metadata['prompt'] ) ) {
			$prompt_meta = $metadata['prompt'];
			$prompt_desc = sprintf(
				'#%d (%s)',
				absint( $prompt_meta['id'] ?? 0 ),
				sanitize_text_field( (string) ( $prompt_meta['source'] ?? 'unknown' ) )
			);

			$meta_items[] = array(
				'key'   => 'Prompt',
				'value' => $prompt_desc,
			);
		}

		\WP_CLI\Utils\format_items( 'table', $meta_items, array( 'key', 'value' ) );

		WP_CLI::success( sprintf( 'Query completed in %dms', $duration ) );
	}
}
