<?php
/**
 * RAG Journey Continuation REST Controller.
 *
 * Handles one-time continuation token issue/consume and conversation history
 * hydration for block-scoped journey continuity.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for RAG journey continuity.
 *
 * @since 1.0.0
 */
class GG_Data_REST_RAG_Journey_Controller extends WP_REST_Controller {

	/**
	 * Token transient key prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOKEN_TRANSIENT_PREFIX = 'gg_data_rag_journey_';

	/**
	 * Token transient ttl in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TOKEN_TTL = WEEK_IN_SECONDS;

	/**
	 * REST API namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'gg-data/v1';

	/**
	 * REST API base route.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'rag/journey';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/issue',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'issue_token' ),
					'permission_callback' => array( $this, 'get_permission_callback' ),
					'args'                => $this->get_issue_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/consume',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'consume_token' ),
					'permission_callback' => array( $this, 'get_permission_callback' ),
					'args'                => $this->get_consume_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/history',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_history' ),
					'permission_callback' => array( $this, 'get_permission_callback' ),
					'args'                => $this->get_history_params(),
				),
			)
		);
	}

	/**
	 * Permission callback for journey endpoints.
	 *
	 * Journey endpoints follow the shared chat access policy, while conversation
	 * ownership is enforced in token/history handlers before hydration.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_permission_callback( $request ) {
		$allowed = apply_filters( 'gg_data_rag_endpoint_permission', false, $request );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		if ( true !== $allowed ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'gregius-data' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Issues a continuation token for a conversation and block instance.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function issue_token( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$block_id        = $request->get_param( 'block_id' );

		$validated_conversation_id = GG_Data_Interaction::validate_conversation_id( $conversation_id );
		if ( is_wp_error( $validated_conversation_id ) ) {
			return $validated_conversation_id;
		}

		$validated_block_id = $this->validate_block_id( $block_id );
		if ( is_wp_error( $validated_block_id ) ) {
			return $validated_block_id;
		}

		$hydration = $this->build_hydration_payload( $validated_conversation_id );
		if ( is_wp_error( $hydration ) ) {
			return $hydration;
		}

		$token     = wp_generate_uuid4();
		$token_key = self::TOKEN_TRANSIENT_PREFIX . $token;
		$payload   = array(
			'conversation_id' => $validated_conversation_id,
			'block_id'        => $validated_block_id,
			'user_id'         => get_current_user_id(),
			'guest_session'   => $this->get_current_guest_session_hash(),
			'created_at'      => time(),
		);

		set_transient( $token_key, $payload, self::TOKEN_TTL );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'token' => $token,
				),
			),
			200
		);
	}

	/**
	 * Consumes a continuation token and hydrates conversation payload.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function consume_token( $request ) {
		$token    = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$block_id = $request->get_param( 'block_id' );

		$validated_block_id = $this->validate_block_id( $block_id );
		if ( is_wp_error( $validated_block_id ) ) {
			return $validated_block_id;
		}

		$token_key = self::TOKEN_TRANSIENT_PREFIX . $token;
		$payload   = get_transient( $token_key );

		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'gg_data_rag_journey_token_invalid',
				__( 'Continuation token is invalid or already consumed.', 'gregius-data' ),
				array( 'status' => 404 )
			);
		}

		delete_transient( $token_key );

		if ( ( $payload['block_id'] ?? '' ) !== $validated_block_id ) {
			return new WP_Error(
				'gg_data_rag_journey_block_mismatch',
				__( 'Continuation token does not match this block instance.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		$token_user_id = isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0;
		if ( $token_user_id > 0 ) {
			if ( get_current_user_id() !== $token_user_id ) {
				return new WP_Error(
					'gg_data_rag_journey_user_mismatch',
					__( 'Continuation token is not valid for this user context.', 'gregius-data' ),
					array( 'status' => 403 )
				);
			}
		} else {
			$token_guest_session   = isset( $payload['guest_session'] ) ? sanitize_text_field( (string) $payload['guest_session'] ) : '';
			$current_guest_session = $this->get_current_guest_session_hash();

			if ( '' === $token_guest_session || '' === $current_guest_session || ! hash_equals( $token_guest_session, $current_guest_session ) ) {
				return new WP_Error(
					'gg_data_rag_journey_guest_session_mismatch',
					__( 'Continuation token is not valid for this guest session.', 'gregius-data' ),
					array( 'status' => 403 )
				);
			}
		}

		$conversation_id = (string) ( $payload['conversation_id'] ?? '' );
		$hydration       = $this->build_hydration_payload( $conversation_id );
		if ( is_wp_error( $hydration ) ) {
			return $hydration;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $hydration,
			),
			200
		);
	}

	/**
	 * Returns conversation history payload for explicit or back/forward hydration.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_history( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$hydration       = $this->build_hydration_payload( $conversation_id );

		if ( is_wp_error( $hydration ) ) {
			return $hydration;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $hydration,
			),
			200
		);
	}

	/**
	 * Builds frontend hydration payload from canonical interaction storage.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation UUID.
	 * @return array|WP_Error
	 */
	private function build_hydration_payload( $conversation_id ) {
		$validated_conversation_id = GG_Data_Interaction::validate_conversation_id( $conversation_id );
		if ( is_wp_error( $validated_conversation_id ) ) {
			return $validated_conversation_id;
		}

		$interaction_id = $this->find_rag_interaction_id( $validated_conversation_id );
		if ( ! $interaction_id ) {
			return new WP_Error(
				'gg_data_rag_journey_not_found',
				__( 'Conversation not found.', 'gregius-data' ),
				array( 'status' => 404 )
			);
		}

		$post = get_post( $interaction_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'gg_data_rag_journey_not_found',
				__( 'Conversation not found.', 'gregius-data' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->can_read_interaction_post( $post ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this interaction.', 'gregius-data' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data_json = get_post_meta( $interaction_id, '_gg_interaction_data', true );
		$data      = is_string( $data_json ) ? json_decode( $data_json, true ) : array();
		$turns     = array();

		if ( is_array( $data ) && isset( $data['turns'] ) && is_array( $data['turns'] ) ) {
			$turns = $data['turns'];
		}

		// Ensure citation resolver class is available for building references.
		if ( ! class_exists( 'GG_Data_Citation_Resolver' ) ) {
			require_once __DIR__ . '/../rag/class-gg-data-citation-resolver.php';
		}

		$messages = array();
		foreach ( $turns as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$query_text = '';
			if ( isset( $turn['query']['original'] ) ) {
				$query_text = sanitize_text_field( (string) $turn['query']['original'] );
			}

			if ( '' !== $query_text ) {
				$messages[] = array(
					'role'      => 'user',
					'content'   => $query_text,
					'timestamp' => sanitize_text_field( (string) ( $turn['timestamp'] ?? '' ) ),
				);
			}

			$response_text = isset( $turn['response'] ) ? wp_kses_post( (string) $turn['response'] ) : '';
			if ( '' === $response_text ) {
				continue;
			}

			$sources = array();
			if ( isset( $turn['sources_details'] ) && is_array( $turn['sources_details'] ) ) {
				foreach ( $turn['sources_details'] as $source ) {
					if ( ! is_array( $source ) ) {
						continue;
					}

					$sources[] = array(
						'title' => isset( $source['title'] ) ? sanitize_text_field( (string) $source['title'] ) : '',
						'url'   => isset( $source['url'] ) ? esc_url_raw( (string) $source['url'] ) : '',
					);
				}
			}

			// Build references from stored citation_sources and sources, mirroring REST response format.
			$citation_sources = isset( $turn['citation_sources'] ) && is_array( $turn['citation_sources'] )
				? $turn['citation_sources']
				: array();
			$references       = GG_Data_Citation_Resolver::resolve_references(
				$response_text,
				$citation_sources,
				$sources
			);

			$messages[] = array(
				'role'       => 'assistant',
				'content'    => $response_text,
				'sources'    => $sources,
				'references' => $references,
				'metadata'   => array(
					'conversation_id'  => $validated_conversation_id,
					'citation_sources' => $citation_sources,
				),
				'thinking'   => isset( $turn['reasoning_content'] ) ? sanitize_text_field( (string) $turn['reasoning_content'] ) : '',
				'timestamp'  => sanitize_text_field( (string) ( $turn['timestamp'] ?? '' ) ),
			);
		}

		return array(
			'conversation_id' => $validated_conversation_id,
			'interaction_id'  => $interaction_id,
			'messages'        => $messages,
		);
	}

	/**
	 * Finds the canonical interaction post id for a conversation.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation UUID.
	 * @return int
	 */
	private function find_rag_interaction_id( $conversation_id ) {
		$posts = get_posts(
			array(
				'post_type'      => GG_Data_Interaction::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to resolve canonical conversation interaction row by UUID/type.
				'meta_query'     => array(
					array(
						'key'   => '_gg_interaction_conversation_id',
						'value' => $conversation_id,
					),
					array(
						'key'   => '_gg_interaction_type',
						'value' => 'rag',
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		return absint( $posts[0] );
	}

	/**
	 * Checks whether the current user context can read an interaction post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Interaction post.
	 * @return bool
	 */
	private function can_read_interaction_post( $post ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$current_user_id = get_current_user_id();
		$post_author     = (int) $post->post_author;

		if ( $current_user_id > 0 ) {
			return $current_user_id === $post_author;
		}

		if ( 0 !== $post_author ) {
			return false;
		}

		$stored_guest_session  = (string) get_post_meta( $post->ID, '_gg_interaction_guest_session_hash', true );
		$current_guest_session = $this->get_current_guest_session_hash();

		if ( '' === $current_guest_session ) {
			return false;
		}

		// Transitional first-claim path for legacy guest conversations.
		if ( '' === $stored_guest_session ) {
			update_post_meta( $post->ID, '_gg_interaction_guest_session_hash', $current_guest_session );
			return true;
		}

		return hash_equals( $stored_guest_session, $current_guest_session );
	}

	/**
	 * Get current guest session hash for anonymous continuity checks.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_current_guest_session_hash() {
		return GG_Data_Interaction::get_guest_session_hash( true );
	}

	/**
	 * Validate block id contract for journey continuity.
	 *
	 * @since 1.0.0
	 * @param string $block_id Candidate block id.
	 * @return string|WP_Error
	 */
	private function validate_block_id( $block_id ) {
		$block_id = sanitize_text_field( (string) $block_id );

		if ( '' === $block_id ) {
			return new WP_Error(
				'gg_data_rag_journey_missing_block_id',
				__( 'Block ID is required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		/**
		 * Filters the list of allowed block ID prefixes for RAG journey validation.
		 *
		 * Each prefix must be a non-empty string. The suffix following the prefix
		 * must match [A-Za-z0-9_-]+. Register additional prefixes here to allow
		 * third-party block IDs to pass journey token validation.
		 *
		 * @since 1.1.0
		 * @param string[] $prefixes Allowed block ID prefix strings.
		 */
		$allowed_prefixes = apply_filters( 'gg_data_rag_journey_allowed_block_id_prefixes', array( 'gg-rag-chat-' ) );

		$matched = false;
		foreach ( $allowed_prefixes as $prefix ) {
			$prefix = (string) $prefix;
			if ( '' === $prefix || ! str_starts_with( $block_id, $prefix ) ) {
				continue;
			}
			$suffix = substr( $block_id, strlen( $prefix ) );
			if ( '' !== $suffix && preg_match( '/^[A-Za-z0-9_-]+$/', $suffix ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			return new WP_Error(
				'gg_data_rag_journey_invalid_block_id',
				__( 'Block ID is invalid.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		return $block_id;
	}

	/**
	 * Param schema for issue endpoint.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_issue_params() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Conversation UUID.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'block_id'        => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Block instance identifier.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Param schema for consume endpoint.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_consume_params() {
		return array(
			'token'    => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'One-time continuation token.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'block_id' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Block instance identifier.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Param schema for history endpoint.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_history_params() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Conversation UUID.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
