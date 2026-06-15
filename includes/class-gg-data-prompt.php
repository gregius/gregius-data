<?php
/**
 * Prompt management for Gregius Data.
 *
 * Registers the gg_prompt custom post type and meta schema.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Prompt.
 *
 * Manages prompt definitions via custom post type.
 */
class GG_Data_Prompt {

	/**
	 * Prompt post type name.
	 *
	 * @var string
	 */
	const POST_TYPE = 'gg_prompt';

	/**
	 * Prompt type taxonomy name.
	 *
	 * @var string
	 */
	const TAXONOMY = 'gg_prompt_type';

	/**
	 * Prompt meta key prefix.
	 *
	 * @var string
	 */
	const META_PREFIX = '_gg_prompt_';

	/**
	 * Logger instance.
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new GG_Data_Logger();
	}

	/**
	 * Initialize prompt system.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_prompt_type_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
	}

	/**
	 * Register gg_prompt custom post type.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Prompts', 'post type general name', 'gregius-data' ),
			'singular_name'      => _x( 'Prompt', 'post type singular name', 'gregius-data' ),
			'menu_name'          => _x( 'Prompts', 'admin menu', 'gregius-data' ),
			'name_admin_bar'     => _x( 'Prompt', 'add new on admin bar', 'gregius-data' ),
			'add_new'            => _x( 'Add New', 'prompt', 'gregius-data' ),
			'add_new_item'       => __( 'Add New Prompt', 'gregius-data' ),
			'new_item'           => __( 'New Prompt', 'gregius-data' ),
			'edit_item'          => __( 'Edit Prompt', 'gregius-data' ),
			'view_item'          => __( 'View Prompt', 'gregius-data' ),
			'all_items'          => __( 'All Prompts', 'gregius-data' ),
			'search_items'       => __( 'Search Prompts', 'gregius-data' ),
			'not_found'          => __( 'No prompts found.', 'gregius-data' ),
			'not_found_in_trash' => __( 'No prompts found in Trash.', 'gregius-data' ),
		);

		$args = array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,

			// Keep REST enabled on CPT for architecture parity; custom endpoints use /gg-data/v1/prompts.
			'show_in_rest'        => true,
			'rest_namespace'      => 'gg-data/v1',
			'rest_base'           => 'prompts-cpt',

			'capability_type'     => 'post',
			'capabilities'        => array(
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
			),
			'map_meta_cap'        => false,

			'supports'            => array( 'title', 'editor', 'custom-fields', 'revisions' ),
			'can_export'          => true,
			'labels'              => $labels,
		);

		register_post_type( self::POST_TYPE, $args );

		$this->logger->log( 'Prompt post type registered', 'debug', 'prompt' );
	}

	/**
	 * Register gg_prompt_type taxonomy for prompt classification.
	 *
	 * @since 1.0.0
	 */
	public function register_prompt_type_taxonomy() {
		$labels = array(
			'name'          => _x( 'Prompt Types', 'taxonomy general name', 'gregius-data' ),
			'singular_name' => _x( 'Prompt Type', 'taxonomy singular name', 'gregius-data' ),
			'search_items'  => __( 'Search Prompt Types', 'gregius-data' ),
			'all_items'     => __( 'All Prompt Types', 'gregius-data' ),
			'edit_item'     => __( 'Edit Prompt Type', 'gregius-data' ),
			'update_item'   => __( 'Update Prompt Type', 'gregius-data' ),
			'add_new_item'  => __( 'Add New Prompt Type', 'gregius-data' ),
			'new_item_name' => __( 'New Prompt Type Name', 'gregius-data' ),
			'menu_name'     => __( 'Prompt Types', 'gregius-data' ),
		);

		$args = array(
			'hierarchical'       => false,
			'labels'             => $labels,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'query_var'          => false,
			'show_in_quick_edit' => false,
			'rewrite'            => false,
			'show_in_rest'       => true,
			'rest_namespace'     => 'gg-data/v1',
			'rest_base'          => 'prompt-types',
			'public'             => false,
			'publicly_queryable' => false,
			'show_tagcloud'      => false,
			'show_in_nav_menus'  => false,
			'meta_box_cb'        => false,
		);

		register_taxonomy( self::TAXONOMY, array( self::POST_TYPE ), $args );
	}

	/**
	 * Register prompt meta fields.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_fields() {
		$meta_fields = array(
			'_gg_prompt_version'    => array(
				'type'              => 'integer',
				'description'       => __( 'Prompt version number.', 'gregius-data' ),
				'sanitize_callback' => 'absint',
			),
			'_gg_prompt_status'     => array(
				'type'              => 'string',
				'description'       => __( 'Prompt lifecycle status.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'_gg_prompt_hash'       => array(
				'type'              => 'string',
				'description'       => __( 'SHA256 hash of prompt content.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_gg_prompt_notes'      => array(
				'type'              => 'string',
				'description'       => __( 'Optional admin notes.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'_gg_prompt_selected'   => array(
				'type'              => 'string',
				'description'       => __( 'Whether this prompt is currently selected as active for its key+scope.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'_gg_prompt_is_factory' => array(
				'type'              => 'string',
				'description'       => __( 'Whether this prompt was created on plugin activation as the default prompt.', 'gregius-data' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);

		foreach ( $meta_fields as $meta_key => $args ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => $args['type'],
					'description'       => $args['description'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $args['sanitize_callback'],
					'auth_callback'     => function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}
}
