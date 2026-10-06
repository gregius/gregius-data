<?php
/**
 * RAG Security Hooks
 *
 * Provides configurable access control for RAG endpoints.
 * Allows site administrators to control who can use the AI chat feature.
 *
 * Access is governed by a site-wide policy (filter `gg_data_rag_access_level`:
 * public | logged_in | capability) plus a per-block opt-in: a block rendered
 * with `requireLogin = false` grants guests (anonymous users) access to the
 * whitelisted RAG routes for that block's post. `capability` is the only hard
 * ceiling for guests. Default is fail-closed (`logged_in`).
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RAG Security Hooks class.
 *
 * Implements default permission handling for RAG endpoints with a
 * filter-configurable access level and per-block guest opt-in.
 *
 * @since 1.0.0
 */
class GG_Data_RAG_Security_Hooks {

	/**
	 * Access level constants.
	 */
	const ACCESS_PUBLIC     = 'public';      // Anyone can use (default for frontend blocks).
	const ACCESS_LOGGED_IN  = 'logged_in';   // Only logged-in users.
	const ACCESS_CAPABILITY = 'capability';  // Users with specific capability.

	/**
	 * Nonce action prefix for guest (anonymous) block-scoped access.
	 *
	 * Full action: `gg_rag_guest_access:<block_id>:<post_id>`.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const GUEST_NONCE_ACTION = 'gg_rag_guest_access';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Add default permission handling (priority 5 to run before custom filters).
		add_filter( 'gg_data_rag_endpoint_permission', array( $this, 'check_access_permission' ), 5, 2 );
	}

	/**
	 * Check access permission based on the configured policy.
	 *
	 * Anonymous requests are first evaluated for per-block guest access; if not
	 * granted, the site-wide access level applies. Logged-in behavior is
	 * unchanged by the guest path.
	 *
	 * @since 1.0.0
	 * @param bool                        $allowed Whether access is currently allowed.
	 * @param WP_REST_Request|object|null $request Request, or a lightweight context object with
	 *                                             public `route` + `params` (AJAX/SSE).
	 * @return bool|WP_Error True if allowed, false or WP_Error to deny.
	 */
	public function check_access_permission( $allowed, $request ) {
		// Respect explicit errors from earlier filters.
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		// Anonymous access granted by an opted-in block on a whitelisted route.
		if ( ! is_user_logged_in() && $this->guest_allowed_for_request( $request ) ) {
			return true;
		}

		// Get configured access level.
		$access_level = $this->get_access_level();

		switch ( $access_level ) {
			case self::ACCESS_PUBLIC:
					// Allow everyone when explicitly configured.
				return true;

			case self::ACCESS_LOGGED_IN:
				// Require authentication.
				if ( ! is_user_logged_in() ) {
					return new WP_Error(
						'gg_data_login_required',
						__( 'You must be logged in to use the AI assistant.', 'gregius-data' ),
						array( 'status' => 401 )
					);
				}
				return true;

			case self::ACCESS_CAPABILITY:
				// Require specific capability.
				$required_capability = $this->get_required_capability();
				if ( ! current_user_can( $required_capability ) ) {
					return new WP_Error(
						'gg_data_insufficient_permissions',
						__( 'You do not have permission to use the AI assistant.', 'gregius-data' ),
						array( 'status' => 403 )
					);
				}
				return true;

			default:
				return new WP_Error(
					'gg_data_invalid_access_level',
					__( 'RAG access policy is misconfigured.', 'gregius-data' ),
					array( 'status' => 403 )
				);
		}
	}

	/**
	 * Get the configured site-wide access level.
	 *
	 * Fail-closed by default (`logged_in`); overridable via the
	 * `gg_data_rag_access_level` filter.
	 *
	 * @since 1.0.0
	 * @return string Access level constant.
	 */
	public function get_access_level() {
		/**
		 * Filter the RAG access level.
		 *
		 * @since 1.0.0
		 * @param string $access_level One of: 'public', 'logged_in', 'capability'.
		 */
		return apply_filters( 'gg_data_rag_access_level', self::ACCESS_LOGGED_IN );
	}

	/**
	 * Get the required capability for capability-based access.
	 *
	 * @since 1.0.0
	 * @return string WordPress capability string.
	 */
	public function get_required_capability() {
		/**
		 * Filter the required capability for RAG access.
		 *
		 * @since 1.0.0
		 * @param string $capability WordPress capability required.
		 */
		return apply_filters( 'gg_data_rag_required_capability', 'read' );
	}

	/**
	 * Whether an anonymous request is authorized by an opted-in block.
	 *
	 * The nonce is issued server-side at render (post content or block-theme
	 * template/part) only when the block is guest-enabled, so a valid nonce
	 * bound to (block_id, post_id) is the credential. `capability` remains the
	 * hard ceiling; `public` still admits guests without a nonce.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request|object|null $request Request or lightweight context object.
	 * @return bool True if the request is an authorized guest request.
	 */
	private function guest_allowed_for_request( $request ) {
		if ( is_user_logged_in() ) {
			return false;
		}

		// `capability` is the only hard ceiling for guests.
		if ( self::ACCESS_CAPABILITY === $this->get_access_level() ) {
			return false;
		}

		$context = $this->request_context( $request );
		if ( ! $this->is_guest_route( $context['route'] ) ) {
			return false;
		}

		$params   = $context['params'];
		$nonce    = isset( $params['guest_access_nonce'] ) ? sanitize_text_field( (string) $params['guest_access_nonce'] ) : '';
		$block_id = isset( $params['guest_access_block_id'] ) ? sanitize_text_field( (string) $params['guest_access_block_id'] ) : '';
		$post_id  = isset( $params['guest_access_post_id'] ) ? absint( $params['guest_access_post_id'] ) : 0;

		if ( '' === $nonce || 0 === $post_id ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, self::GUEST_NONCE_ACTION . ':' . $block_id . ':' . $post_id );
	}

	/**
	 * Normalize a request into a `route` + `params` context.
	 *
	 * Accepts a WP_REST_Request, or a lightweight object exposing public
	 * `route` (string) and `params` (array) properties (used for the
	 * admin-ajax SSE path).
	 *
	 * @since 1.0.0
	 * @param mixed $request Request or context object.
	 * @return array{route:string,params:array}
	 */
	private function request_context( $request ) {
		if ( $request instanceof WP_REST_Request ) {
			return array(
				'route'  => (string) $request->get_route(),
				'params' => (array) $request->get_params(),
			);
		}

		if ( is_object( $request ) ) {
			return array(
				'route'  => isset( $request->route ) ? (string) $request->route : '',
				'params' => ( isset( $request->params ) && is_array( $request->params ) ) ? $request->params : array(),
			);
		}

		return array(
			'route'  => '',
			'params' => array(),
		);
	}

	/**
	 * Whether a route is allowed for guest (anonymous) access.
	 *
	 * @since 1.0.0
	 * @param string $route Route (with or without a leading slash).
	 * @return bool True if guests may use the route.
	 */
	private function is_guest_route( $route ) {
		/**
		 * Filter the RAG routes that guests may access when a block opts in.
		 *
		 * @since 1.0.0
		 * @param string[] $routes Allowed route names (no leading slash).
		 */
		$allowed = apply_filters(
			'gg_data_rag_guest_allowed_routes',
			array(
				'gg-data/v1/rag/chat',
				'gg-data/v1/rag/journey/issue',
				'gg-data/v1/rag/journey/consume',
				'gg-data/v1/rag/journey/history',
				'gg-intelligence/v1/rag/conversations/nba-event',
				'gg-intelligence/v1/rag/conversations/nba-lifecycle-event',
				'gg-intelligence/v1/rag/conversations/pill-feedback',
				'gg-intelligence/v1/rag/conversations/turn-feedback',
				'gg-data/rag/stream',
			)
		);

		return in_array( ltrim( (string) $route, '/' ), $allowed, true );
	}
}
