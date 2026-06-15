<?php
/**
 * REST API Controller for Interactions
 *
 * Extends WP_REST_Posts_Controller to enforce proper access control
 * for the gg_interaction custom post type.
 *
 * Access rules:
 * - Admins (manage_options) can access all interactions
 * - Logged-in users can access their own interactions
 * - Anonymous users have no access
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST Controller for Interactions CPT.
 *
 * Implements author-based access control.
 *
 * @since 1.0.0
 */
class GG_Data_REST_Interactions_Controller extends WP_REST_Posts_Controller {

	/**
	 * Resolve optional multisite governance context for this request.
	 *
	 * Site context switching is available only to super admins and only when
	 * a valid `site_id` parameter is explicitly provided.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return int|WP_Error Target site ID, or WP_Error on invalid/forbidden context.
	 */
	private function get_target_site_id( $request ) {
		$current_site_id = get_current_blog_id();

		if ( ! is_multisite() ) {
			return $current_site_id;
		}

		$requested_site_id = $request->get_param( 'site_id' );
		if ( null === $requested_site_id || '' === $requested_site_id ) {
			return $current_site_id;
		}

		if ( ! is_super_admin() ) {
			return new WP_Error(
				'gg_data_interaction_multisite_forbidden',
				__( 'Only network administrators can access interactions across sites.', 'gregius-data' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$site_id = absint( $requested_site_id );
		if ( $site_id <= 0 || ! get_site( $site_id ) ) {
			return new WP_Error(
				'gg_data_invalid_site_context',
				__( 'Invalid multisite context for interaction request.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		return $site_id;
	}

	/**
	 * Execute a callback in optional multisite context.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @param callable        $callback Callback executed in resolved site context.
	 * @return mixed|WP_Error Callback result or WP_Error.
	 */
	private function run_in_request_site_context( $request, $callback ) {
		$target_site_id = $this->get_target_site_id( $request );
		if ( is_wp_error( $target_site_id ) ) {
			return $target_site_id;
		}

		$current_site_id = get_current_blog_id();
		if ( $target_site_id === $current_site_id ) {
			return call_user_func( $callback );
		}

		switch_to_blog( $target_site_id );
		try {
			return call_user_func( $callback );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'interactions';
	}

	/**
	 * Checks if a given request has access to read posts.
	 *
	 * Admins can read all, logged-in users can read their own.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access interactions.', 'gregius-data' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$target_site_id = $this->get_target_site_id( $request );
		if ( is_wp_error( $target_site_id ) ) {
			return $target_site_id;
		}

		return true;
	}

	/**
	 * Retrieves a collection of posts.
	 *
	 * Filters results to only show user's own interactions (unless admin).
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				// If not admin, force filter to current user's posts only.
				if ( ! current_user_can( 'manage_options' ) ) {
					$request->set_param( 'author', get_current_user_id() );
				}

				return parent::get_items( $request );
			}
		);
	}

	/**
	 * Retrieves one post from parent controller in request site context.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				return parent::get_item( $request );
			}
		);
	}

	/**
	 * Checks if a given request has access to read a post.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				// Must be logged in.
				if ( ! is_user_logged_in() ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You must be logged in to access interactions.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// Admins can access any interaction.
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				// Check if user owns this interaction.
				$post = $this->get_post( $request['id'] );
				if ( is_wp_error( $post ) ) {
					return $post;
				}

				if ( get_current_user_id() !== (int) $post->post_author ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You do not have permission to access this interaction.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				return true;
			}
		);
	}

	/**
	 * Checks if a given request has access to create a post.
	 *
	 * Direct create is restricted to admins. Non-admin creation must use chat flows.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () {
				if ( ! is_user_logged_in() ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You must be logged in to create interactions.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				return new WP_Error(
					'gg_data_interaction_create_requires_chat_flow',
					__( 'Direct interaction creation is restricted. Use the chat flow to create new interactions.', 'gregius-data' ),
					array( 'status' => 403 )
				);
			}
		);
	}

	/**
	 * Creates one post in parent controller request site context.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				return parent::create_item( $request );
			}
		);
	}

	/**
	 * Checks if a given request has access to update a post.
	 *
	 * Admins can update any, users can update their own.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to update the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				// Must be logged in.
				if ( ! is_user_logged_in() ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You must be logged in to update interactions.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// Admins can update any interaction.
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				// Check if user owns this interaction.
				$post = $this->get_post( $request['id'] );
				if ( is_wp_error( $post ) ) {
					return $post;
				}

				if ( get_current_user_id() !== (int) $post->post_author ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You do not have permission to update this interaction.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				return true;
			}
		);
	}

	/**
	 * Updates one post in parent controller request site context.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				return parent::update_item( $request );
			}
		);
	}

	/**
	 * Checks if a given request has access to delete a post.
	 *
	 * Admins can delete any, users can delete their own.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				// Must be logged in.
				if ( ! is_user_logged_in() ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You must be logged in to delete interactions.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// Admins can delete any interaction.
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				// Check if user owns this interaction.
				$post = $this->get_post( $request['id'] );
				if ( is_wp_error( $post ) ) {
					return $post;
				}

				if ( get_current_user_id() !== (int) $post->post_author ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You do not have permission to delete this interaction.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				return true;
			}
		);
	}

	/**
	 * Deletes one post in parent controller request site context.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				return parent::delete_item( $request );
			}
		);
	}

	/**
	 * Register custom routes for interactions.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Preserve default CPT collection/single-item routes required by Gutenberg hydrate.
		parent::register_routes();

		// POST /gg-data/v1/interactions/{id}/feedback - Submit feedback for an interaction.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/feedback',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_feedback' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id'             => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Interaction post ID.', 'gregius-data' ),
							'sanitize_callback' => 'absint',
						),
						'feedback_type'  => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Feedback type: helpful, unhelpful, rating, or correction.', 'gregius-data' ),
							'enum'              => array( 'helpful', 'unhelpful', 'rating', 'correction' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'feedback_value' => array(
							'required'    => true,
							'description' => __( 'Feedback value (boolean, integer, or string depending on type).', 'gregius-data' ),
						),
						'context'        => array(
							'required'    => false,
							'type'        => 'object',
							'description' => __( 'Additional context for feedback.', 'gregius-data' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Submit feedback for an interaction.
	 *
	 * Stores feedback and fires the gg_data_interaction_feedback_received action.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function submit_feedback( $request ) {
		return $this->run_in_request_site_context(
			$request,
			function () use ( $request ) {
				$interaction_id = (int) $request['id'];
				$feedback_type  = sanitize_text_field( $request['feedback_type'] );
				$feedback_value = $request['feedback_value'];
				$context        = $request['context'] ?? array();

				// Verify interaction exists and user can access it.
				$post = $this->get_post( $interaction_id );
				if ( is_wp_error( $post ) ) {
					return $post;
				}

				// Ensure user has permission to submit feedback (user owns interaction or is admin).
				if ( get_current_user_id() !== (int) $post->post_author && ! current_user_can( 'manage_options' ) ) {
					return new WP_Error(
						'rest_forbidden',
						__( 'You do not have permission to submit feedback for this interaction.', 'gregius-data' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// Type-safe feedback value handling.
				$sanitized_value = $feedback_value;
				if ( 'rating' === $feedback_type && is_numeric( $feedback_value ) ) {
					$sanitized_value = max( 1, min( 5, (int) $feedback_value ) ); // Clamp to 1-5.
				} elseif ( in_array( $feedback_type, array( 'helpful', 'unhelpful' ), true ) ) {
					$sanitized_value = 'helpful' === $feedback_type ? 1 : 0;
				} else {
					$sanitized_value = sanitize_text_field( (string) $feedback_value );
				}

				// Prepare feedback context.
				$feedback_context = array(
					'submitted_by' => get_current_user_id(),
					'submitted_at' => current_time( 'mysql' ),
					'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				);

				// Merge with provided context.
				if ( is_array( $context ) ) {
					$feedback_context = array_merge( $feedback_context, $context );
				}

				// Get the interaction's conversation_id from metadata for context.
				$conversation_id = get_post_meta( $interaction_id, '_gg_interaction_conversation_id', true );
				if ( ! $conversation_id ) {
					// Fallback: try to extract from _gg_interaction_data.
					$interaction_data = get_post_meta( $interaction_id, '_gg_interaction_data', true );
					if ( $interaction_data ) {
						$data            = is_string( $interaction_data ) ? json_decode( $interaction_data, true ) : $interaction_data;
						$conversation_id = $data['conversation_id'] ?? '';
					}
				}

				if ( $conversation_id ) {
					$feedback_context['conversation_id'] = $conversation_id;
				}

				/**
				 * Fires when user feedback is submitted on an interaction.
				 *
				 * Allows integrations to collect RLHF signals, update analytics, trigger reranking,
				 * or send feedback to external evaluation pipelines.
				 *
				 * @since 1.0.0
				 * @param int    $interaction_id Interaction post ID.
				 * @param string $feedback_type  Type of feedback (helpful, unhelpful, rating, correction).
				 * @param mixed  $feedback_value Sanitized feedback value.
				 * @param array  $feedback_context Context including submission details and conversation metadata.
				 */
				do_action( 'gg_data_interaction_feedback_received', $interaction_id, $feedback_type, $sanitized_value, $feedback_context );

				// Store feedback in post meta for audit trail.
				$feedback_record = array(
					'type'  => $feedback_type,
					'value' => $sanitized_value,
					'meta'  => $feedback_context,
				);

				// Store as JSON in meta (allowing multiple feedback submissions).
				$existing_feedback = get_post_meta( $interaction_id, '_gg_interaction_feedback', true );
				$feedback_list     = is_string( $existing_feedback ) ? json_decode( $existing_feedback, true ) : array();
				if ( ! is_array( $feedback_list ) ) {
					$feedback_list = array();
				}
				$feedback_list[] = $feedback_record;

				update_post_meta( $interaction_id, '_gg_interaction_feedback', wp_json_encode( $feedback_list ) );

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Feedback submitted successfully.', 'gregius-data' ),
						'data'    => array(
							'interaction_id' => $interaction_id,
							'feedback_type'  => $feedback_type,
						),
					),
					200
				);
			}
		);
	}
}
