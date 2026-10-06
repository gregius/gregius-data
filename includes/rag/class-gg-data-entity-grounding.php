<?php
/**
 * Entity Grounding Hooks
 *
 * Grounds RAG query routing in the "current entity" — the post/page the RAG
 * block is rendered on. The entity is supplied by the block request manifest,
 * captured on `gg_data_rag_request`, then injected into the tool-selection
 * router prompt so that questions about "this post" / "this page" resolve to
 * the current content.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entity grounding hooks class.
 *
 * @since 1.0.0
 */
class GG_Data_Entity_Grounding {

	/**
	 * The current request's entity (from the block manifest), or empty.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $current_entity = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'gg_data_rag_request', array( $this, 'capture_entity' ), 10, 3 );
		add_filter( 'gg_data_rag_tool_selection_system_prompt', array( $this, 'inject_routing_grounding' ), 20, 1 );
	}

	/**
	 * Capture the current entity from the request manifest.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query   User query.
	 * @param array  $options RAG options (includes the normalized manifest).
	 * @param int    $user_id Current user id.
	 * @return void
	 */
	public function capture_entity( $query, $options, $user_id ) {
		unset( $query, $user_id );

		$this->current_entity = array();

		if (
			isset( $options['manifest'] ) && is_array( $options['manifest'] )
			&& isset( $options['manifest']['entity'] ) && is_array( $options['manifest']['entity'] )
		) {
			$this->current_entity = $options['manifest']['entity'];
		}
	}

	/**
	 * Build the current-entity descriptor line, or '' when there is no entity.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function describe_entity() {
		$entity_id   = isset( $this->current_entity['id'] ) ? absint( $this->current_entity['id'] ) : 0;
		$entity_type = isset( $this->current_entity['type'] ) ? sanitize_key( (string) $this->current_entity['type'] ) : '';

		$title = '';
		if ( isset( $this->current_entity['title'] ) ) {
			if ( is_array( $this->current_entity['title'] ) ) {
				$title = isset( $this->current_entity['title']['rendered'] )
					? (string) $this->current_entity['title']['rendered']
					: ( isset( $this->current_entity['title']['raw'] ) ? (string) $this->current_entity['title']['raw'] : '' );
			} else {
				$title = (string) $this->current_entity['title'];
			}
		}

		if ( $entity_id <= 0 || '' === $title ) {
			return '';
		}

		return sprintf(
			/* translators: 1: entity title, 2: entity id, 3: entity type */
			__( 'The user is currently viewing: "%1$s" (id %2$d, type %3$s).', 'gregius-data' ),
			$title,
			$entity_id,
			$entity_type
		);
	}

	/**
	 * Inject the current-entity grounding into the tool-selection router prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt Existing router system prompt.
	 * @return string
	 */
	public function inject_routing_grounding( $system_prompt ) {
		$descriptor = $this->describe_entity();
		if ( '' === $descriptor ) {
			return $system_prompt;
		}

		return $system_prompt . "\n\n" . $descriptor . ' ' . __( "Use the search_entity_content tool ONLY when the user asks a question specifically about this document's content (for example \"what does this page say about X\"). For all other questions — including topics or terms that may span multiple documents — use search_content.", 'gregius-data' );
	}
}
