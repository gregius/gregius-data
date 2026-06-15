<?php
/**
 * REST API: Prompts Controller
 *
 * Provides admin-only prompt CRUD and activation operations.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * REST API controller for prompt operations.
 */
class GG_Data_REST_Prompts_Controller extends WP_REST_Controller {

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
		$this->namespace = 'gg-data/v1';
		$this->rest_base = 'prompts';
		$this->logger    = new GG_Data_Logger();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_item_schema_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_item_schema_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/activate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Admin-only permission check.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function admin_permissions_check( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to manage prompts.', 'gregius-data' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * List prompts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$posts = get_posts(
			array(
				'post_type'      => GG_Data_Prompt::POST_TYPE,
				'post_status'    => array( 'publish' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$items = array_map( array( $this, 'prepare_prompt_output' ), $posts );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array_values( $items ),
			),
			200
		);
	}

	/**
	 * Create prompt.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$title       = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$content     = (string) $request->get_param( 'content' );
		$status      = sanitize_key( (string) $request->get_param( 'status' ) );
		$notes       = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
		$prompt_type = $this->normalize_prompt_type( $request->get_param( 'prompt_type' ) );

		if ( empty( $title ) || '' === trim( $content ) ) {
			return new WP_Error(
				'gg_data_prompt_invalid',
				__( 'Title and content are required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $status, array( 'draft', 'published' ), true ) ) {
			$status = 'draft';
		}

		$version = $this->get_next_version();
		$hash    = $this->hash_content( $content );

		$post_id = wp_insert_post(
			array(
				'post_type'    => GG_Data_Prompt::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->write_prompt_meta( $post_id, $version, $status, $hash, $notes );
		$this->set_prompt_type( $post_id, $prompt_type );

		$post = get_post( $post_id );

		$this->logger->log(
			sprintf( 'Prompt created: %d', $post_id ),
			'info',
			'system',
			null,
			array(
				'prompt_id' => $post_id,
				'version'   => $version,
				'status'    => $status,
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->prepare_prompt_output( $post ),
			),
			201
		);
	}

	/**
	 * Update prompt.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || GG_Data_Prompt::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'gg_data_prompt_not_found',
				__( 'Prompt not found.', 'gregius-data' ),
				array( 'status' => 404 )
			);
		}

		$title       = $request->has_param( 'title' ) ? sanitize_text_field( (string) $request->get_param( 'title' ) ) : $post->post_title;
		$content     = $request->has_param( 'content' ) ? (string) $request->get_param( 'content' ) : $post->post_content;
		$status      = $request->has_param( 'status' ) ? sanitize_key( (string) $request->get_param( 'status' ) ) : get_post_meta( $post_id, '_gg_prompt_status', true );
		$notes       = $request->has_param( 'notes' ) ? sanitize_textarea_field( (string) $request->get_param( 'notes' ) ) : get_post_meta( $post_id, '_gg_prompt_notes', true );
		$prompt_type = $request->has_param( 'prompt_type' )
			? $this->normalize_prompt_type( $request->get_param( 'prompt_type' ) )
			: $this->get_prompt_type( $post_id );

		if ( empty( $title ) || '' === trim( $content ) ) {
			return new WP_Error(
				'gg_data_prompt_invalid',
				__( 'Title and content are required.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $status, array( 'draft', 'published' ), true ) ) {
			$status = 'draft';
		}

		$version = (int) get_post_meta( $post_id, '_gg_prompt_version', true );
		if ( $version <= 0 ) {
			$version = 1;
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$hash = $this->hash_content( $content );
		$this->write_prompt_meta( $post_id, $version, $status, $hash, $notes );
		$this->set_prompt_type( $post_id, $prompt_type );

		if ( 'draft' === $status ) {
			update_post_meta( $post_id, '_gg_prompt_selected', '' );
		}

		$post = get_post( $post_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->prepare_prompt_output( $post ),
			),
			200
		);
	}

	/**
	 * Delete prompt (moves to trash).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || GG_Data_Prompt::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'gg_data_prompt_not_found',
				__( 'Prompt not found.', 'gregius-data' ),
				array( 'status' => 404 )
			);
		}

		update_post_meta( $post_id, '_gg_prompt_selected', '' );
		wp_trash_post( $post_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id' => $post_id,
				),
			),
			200
		);
	}

	/**
	 * Activate a prompt for its key+scope and deactivate others.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_item( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || GG_Data_Prompt::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'gg_data_prompt_not_found',
				__( 'Prompt not found.', 'gregius-data' ),
				array( 'status' => 404 )
			);
		}

		$prompt_status = get_post_meta( $post_id, '_gg_prompt_status', true );
		if ( 'published' !== $prompt_status ) {
			return new WP_Error(
				'gg_data_prompt_not_published',
				__( 'Only published prompts can be selected.', 'gregius-data' ),
				array( 'status' => 400 )
			);
		}

		$prompt_type = $this->get_prompt_type( $post_id );

		$this->deselect_other_prompts( $post_id, $prompt_type );
		update_post_meta( $post_id, '_gg_prompt_selected', '1' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->prepare_prompt_output( get_post( $post_id ) ),
			),
			200
		);
	}

	/**
	 * Prompt schema arguments for create and update endpoints.
	 *
	 * @return array
	 */
	private function get_item_schema_args() {
		return array(
			'title'       => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'     => array(
				'required' => false,
				'type'     => 'string',
			),
			'status'      => array(
				'required'          => false,
				'type'              => 'string',
				'enum'              => array( 'draft', 'published' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'notes'       => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'prompt_type' => array(
				'required'          => false,
				'type'              => 'string',
				'enum'              => array( 'system', 'security' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Normalize prompt output for dashboard use.
	 *
	 * @param WP_Post $post Prompt post.
	 * @return array
	 */
	private function prepare_prompt_output( $post ) {
		return array(
			'id'          => (int) $post->ID,
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'prompt_type' => $this->get_prompt_type( $post->ID ),
			'notes'       => (string) get_post_meta( $post->ID, '_gg_prompt_notes', true ),
			'version'     => (int) get_post_meta( $post->ID, '_gg_prompt_version', true ),
			'status'      => '' !== (string) get_post_meta( $post->ID, '_gg_prompt_status', true ) ? (string) get_post_meta( $post->ID, '_gg_prompt_status', true ) : 'draft',
			'selected'    => '1' === get_post_meta( $post->ID, '_gg_prompt_selected', true ),
			'is_factory'  => '1' === get_post_meta( $post->ID, '_gg_prompt_is_factory', true ),
			'hash'        => '' !== (string) get_post_meta( $post->ID, '_gg_prompt_hash', true ) ? (string) get_post_meta( $post->ID, '_gg_prompt_hash', true ) : $this->hash_content( $post->post_content ),
			'modified'    => mysql_to_rfc3339( '' !== $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified ),
		);
	}

	/**
	 * Write prompt meta set.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $version Prompt version.
	 * @param string $status  Prompt status.
	 * @param string $hash    Prompt hash.
	 * @param string $notes   Prompt notes.
	 */
	private function write_prompt_meta( $post_id, $version, $status, $hash, $notes ) {
		update_post_meta( $post_id, '_gg_prompt_version', (int) $version );
		update_post_meta( $post_id, '_gg_prompt_status', $status );
		update_post_meta( $post_id, '_gg_prompt_hash', $hash );
		update_post_meta( $post_id, '_gg_prompt_notes', $notes );
	}

	/**
	 * Get next version number for a key and scope.
	 *
	 * @return int Sequence number across all prompts.
	 */
	private function get_next_version() {
		$posts = get_posts(
			array(
				'post_type'      => GG_Data_Prompt::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		return count( $posts ) + 1;
	}

	/**
	 * Deactivate active prompts in same key/scope except target.
	 *
	 * @param int    $active_post_id Active prompt ID.
	 * @param string $prompt_type    Prompt type slug.
	 */
	private function deselect_other_prompts( $active_post_id, $prompt_type ) {
		$selected_posts = get_posts(
			array(
				'post_type'      => GG_Data_Prompt::POST_TYPE,
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$active_id      = (int) $active_post_id;
		$selected_posts = array_filter(
			$selected_posts,
			function ( $post_id ) use ( $active_id ) {
				return (int) $post_id !== $active_id && '1' === get_post_meta( (int) $post_id, '_gg_prompt_selected', true );
			}
		);

		if ( taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
			$normalized_prompt_type = $this->normalize_prompt_type( $prompt_type );
			$selected_posts         = array_filter(
				$selected_posts,
				function ( $post_id ) use ( $normalized_prompt_type ) {
					return has_term( $normalized_prompt_type, GG_Data_Prompt::TAXONOMY, (int) $post_id );
				}
			);
		}

		foreach ( $selected_posts as $post_id ) {
			update_post_meta( $post_id, '_gg_prompt_selected', '' );
		}
	}

	/**
	 * Normalize prompt type from request data.
	 *
	 * @param mixed $prompt_type Raw prompt type.
	 * @return string
	 */
	private function normalize_prompt_type( $prompt_type ) {
		$prompt_type = sanitize_key( (string) $prompt_type );

		if ( ! in_array( $prompt_type, array( 'system', 'security' ), true ) ) {
			$prompt_type = 'system';
		}

		return $prompt_type;
	}

	/**
	 * Set prompt type taxonomy term.
	 *
	 * @param int    $post_id     Prompt post ID.
	 * @param string $prompt_type Prompt type slug.
	 */
	private function set_prompt_type( $post_id, $prompt_type ) {
		if ( ! class_exists( 'GG_Data_Prompt' ) || ! taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
			return;
		}

		wp_set_object_terms( (int) $post_id, array( $this->normalize_prompt_type( $prompt_type ) ), GG_Data_Prompt::TAXONOMY, false );
	}

	/**
	 * Get prompt type taxonomy slug.
	 *
	 * @param int $post_id Prompt post ID.
	 * @return string
	 */
	private function get_prompt_type( $post_id ) {
		if ( ! class_exists( 'GG_Data_Prompt' ) || ! taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
			return 'system';
		}

		$terms = wp_get_object_terms( (int) $post_id, GG_Data_Prompt::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 'system';
		}

		return $this->normalize_prompt_type( $terms[0] );
	}

	/**
	 * Hash prompt content deterministically.
	 *
	 * @param string $content Prompt content.
	 * @return string
	 */
	private function hash_content( $content ) {
		$normalized = str_replace( "\r\n", "\n", trim( (string) $content ) );
		return hash( 'sha256', $normalized );
	}
}
