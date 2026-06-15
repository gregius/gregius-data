<?php
/**
 * RAG Security Hooks
 *
 * Provides configurable access control for RAG endpoints.
 * Allows site administrators to control who can use the AI chat feature.
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
 * Implements default permission handling for RAG endpoints with
 * configurable access levels via settings and filter hooks.
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
	 * Settings manager instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Settings_Manager
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->settings = new GG_Data_Settings_Manager();
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

		// Add settings registration for admin UI.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Check access permission based on configured settings.
	 *
	 * This is the default permission handler. Site admins can:
	 * 1. Configure via settings (gg_data_rag_access_level)
	 * 2. Override via filter hooks at higher priority
	 *
	 * @since 1.0.0
	 * @param bool                 $allowed Whether access is currently allowed.
	 * @param WP_REST_Request|null $request Request object (null for AJAX). Reserved for future use.
	 * @return bool|WP_Error True if allowed, false or WP_Error to deny.
	 */
	public function check_access_permission( $allowed, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for future per-request logic.
		// Respect explicit errors from earlier filters.
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
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
	 * Get the configured access level.
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
		return apply_filters(
			'gg_data_rag_access_level',
			$this->settings->get( 'rag_access_level', self::ACCESS_LOGGED_IN )
		);
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
		return apply_filters(
			'gg_data_rag_required_capability',
			$this->settings->get( 'rag_required_capability', 'read' )
		);
	}

	/**
	 * Register settings for the admin UI.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'gg_data_rag_settings',
			'gg_data_rag_access_level',
			array(
				'type'              => 'string',
				'default'           => self::ACCESS_LOGGED_IN,
				'sanitize_callback' => array( $this, 'sanitize_access_level' ),
			)
		);

		register_setting(
			'gg_data_rag_settings',
			'gg_data_rag_required_capability',
			array(
				'type'              => 'string',
				'default'           => 'read',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Sanitize access level setting.
	 *
	 * @since 1.0.0
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_access_level( $value ) {
		$valid_levels = array( self::ACCESS_PUBLIC, self::ACCESS_LOGGED_IN, self::ACCESS_CAPABILITY );
		return in_array( $value, $valid_levels, true ) ? $value : self::ACCESS_LOGGED_IN;
	}

	/**
	 * Get available access level options for UI.
	 *
	 * @since 1.0.0
	 * @return array Associative array of value => label.
	 */
	public static function get_access_level_options() {
		return array(
			self::ACCESS_PUBLIC     => __( 'Public (anyone can use)', 'gregius-data' ),
			self::ACCESS_LOGGED_IN  => __( 'Logged-in users only', 'gregius-data' ),
			self::ACCESS_CAPABILITY => __( 'Users with specific capability', 'gregius-data' ),
		);
	}
}
