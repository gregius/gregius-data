<?php
/**
 * Entity Content Tool
 *
 * Registers the `search_entity_content` RAG tool: answers a question grounded
 * in the CURRENT entity's content (the post/page the RAG block is rendered on),
 * resolved from the request manifest. Complements `search_content` (site-wide
 * retrieval) with single-document, grounded retrieval.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entity content tool class.
 *
 * @since 1.0.0
 */
class GG_Data_Entity_Content_Tool {

	/**
	 * Maximum document characters sent to the answer model (overflow guardrail).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_CONTENT_CHARS = 24000;

	/**
	 * Register the tool definition and its handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init() {
		$instance = new self();
		add_filter( 'gg_data_rag_tools', array( $instance, 'register_tools' ) );
		add_filter( 'gg_data_rag_tool_search_entity_content', array( $instance, 'handle_search_entity_content' ), 10, 3 );
	}

	/**
	 * Add the `search_entity_content` tool definition.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tools Existing tool definitions keyed by name.
	 * @return array
	 */
	public function register_tools( $tools ) {
		if ( ! is_array( $tools ) ) {
			return $tools;
		}

		$tools['search_entity_content'] = array(
			'name'        => 'search_entity_content',
			'description' => 'Answer strictly from the single document the user is currently viewing. Use ONLY when the question explicitly refers to "this post", "this page", or asks about THIS document specifically.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'query' => array(
						'type'        => 'string',
						'description' => 'The user question to answer from the current document.',
					),
				),
				'required'   => array( 'query' ),
			),
		);

		return $tools;
	}

	/**
	 * Handle the `search_entity_content` tool.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $result       Previous result (null on first call).
	 * @param string     $tool_name    Tool name.
	 * @param array      $tool_context Context with query, options, connection name.
	 * @return array|WP_Error
	 */
	public function handle_search_entity_content( $result, $tool_name, $tool_context ) {
		if ( null !== $result ) {
			return $result;
		}

		unset( $tool_name );

		$options  = isset( $tool_context['options'] ) && is_array( $tool_context['options'] ) ? $tool_context['options'] : array();
		$manifest = isset( $options['manifest'] ) && is_array( $options['manifest'] ) ? $options['manifest'] : array();
		$entity   = isset( $manifest['entity'] ) && is_array( $manifest['entity'] ) ? $manifest['entity'] : array();

		$entity_id   = isset( $entity['id'] ) ? absint( $entity['id'] ) : 0;
		$entity_type = isset( $entity['type'] ) ? sanitize_key( (string) $entity['type'] ) : '';

		if ( $entity_id <= 0 ) {
			return $this->build_result(
				__( 'I could not determine which content to search.', 'gregius-data' ),
				array(),
				0,
				0,
				'',
				$options,
				$tool_context
			);
		}

		$rag_service = $this->get_rag_service( $tool_context );
		if ( is_wp_error( $rag_service ) ) {
			return $rag_service;
		}

		$content = $rag_service->get_entity_content_full( $entity_id );
		if ( '' === $content ) {
			return $this->build_result(
				__( 'I could not find content for this post. Try asking a general question instead.', 'gregius-data' ),
				array(),
				0,
				$entity_id,
				$entity_type,
				$options,
				$tool_context
			);
		}

		$title = (string) get_the_title( $entity_id );

		$content_for_llm = strlen( $content ) > self::MAX_CONTENT_CHARS
			? mb_substr( $content, 0, self::MAX_CONTENT_CHARS )
			: $content;

		$llm_model_id  = isset( $tool_context['llm_model_id'] ) ? (string) $tool_context['llm_model_id'] : '';
		$system_prompt = __( 'You are a helpful assistant. Answer the user\'s question using ONLY the provided document content. Include inline citations using [Source 1] markers.', 'gregius-data' );
		$answer_prompt = sprintf(
			"Question: %1\$s\n\n[Source 1] %2\$s:\n%3\$s",
			isset( $tool_context['query'] ) ? (string) $tool_context['query'] : '',
			$title,
			$content_for_llm
		);

		$llm_response = $rag_service->stream_llm(
			$answer_prompt,
			$llm_model_id,
			$system_prompt,
			$options['progress_callback'] ?? null,
			array( 'max_tokens' => 900 )
		);

		if ( is_wp_error( $llm_response ) ) {
			return $llm_response;
		}

		$sources = array(
			array(
				'post_id' => $entity_id,
				'type'    => $entity_type,
				'title'   => $title,
				'url'     => get_permalink( $entity_id ),
			),
		);

		$result = $this->build_result(
			trim( (string) ( $llm_response['text'] ?? '' ) ),
			$sources,
			1,
			$entity_id,
			$entity_type,
			$options,
			$tool_context
		);

		$result['metadata']['llm_model'] = $llm_response['model'] ?? $llm_model_id;
		$result['metadata']['usage']     = $llm_response['usage'] ?? array();

		$suggestions = $rag_service->generate_suggestions(
			$content_for_llm,
			$title,
			isset( $tool_context['query'] ) ? (string) $tool_context['query'] : '',
			$llm_model_id,
			$entity_id,
			array( isset( $tool_context['query'] ) ? (string) $tool_context['query'] : '' )
		);

		$result['metadata']['suggestions_title']   = $suggestions['title'];
		$result['metadata']['suggested_questions'] = $suggestions['questions'];

		return $result;
	}

	/**
	 * Build a tool result payload.
	 *
	 * @since 1.0.0
	 *
	 * @param string $answer      Answer text.
	 * @param array  $sources     Source entries.
	 * @param int    $chunks_used Chunk count used.
	 * @param int    $entity_id   Entity id.
	 * @param string $entity_type Entity type.
	 * @param array  $options     RAG options.
	 * @param array  $tool_context Tool context.
	 * @return array
	 */
	private function build_result( $answer, $sources, $chunks_used, $entity_id, $entity_type, $options, $tool_context ) {
		return array(
			'answer'   => $answer,
			'sources'  => $sources,
			'metadata' => array(
				'tool'            => 'search_entity_content',
				'tool_selected'   => 'search_entity_content',
				'chunks_used'     => $chunks_used,
				'connection'      => $tool_context['connection_name'] ?? 'gregius-data',
				'conversation_id' => $options['conversation_id'] ?? null,
				'source'          => $options['source'] ?? array( 'type' => 'rest' ),
				'trigger'         => $tool_context['trigger'] ?? 'unknown',
				'entity_id'       => $entity_id,
				'entity_type'     => $entity_type,
			),
		);
	}

	/**
	 * Resolve a RAG service instance from the tool context.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tool_context Tool context.
	 * @return GG_Data_RAG_Service|WP_Error
	 */
	private function get_rag_service( $tool_context ) {
		$connection_name  = isset( $tool_context['connection_name'] ) ? $tool_context['connection_name'] : 'gregius-data';
		$settings_manager = new GG_Data_Settings_Manager();
		$embedding_model  = $settings_manager->get_with_category( 'embedding', $connection_name, 'embedding_model_key', 'text-embedding-3-small' );

		if ( empty( $embedding_model ) ) {
			return new WP_Error( 'no_embedding_model', __( 'No embedding model configured', 'gregius-data' ) );
		}

		return new GG_Data_RAG_Service( $connection_name, $embedding_model );
	}
}
