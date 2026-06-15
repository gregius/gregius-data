<?php
/**
 * Prompt resolver service for Gregius Data.
 *
 * Resolves prompts from stored gg_prompt posts.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Prompt_Resolver.
 */
class GG_Data_Prompt_Resolver {

	/**
	 * Resolve effective prompt content and metadata.
	 *
	 * @since 1.0.0
	 * @param int    $prompt_id   Explicit prompt post ID.
	 * @param string $prompt_type Prompt type taxonomy slug.
	 * @return array {
	 *     Prompt resolution result.
	 *
	 *     @type string $content  Effective prompt content.
	 *     @type array  $metadata Prompt metadata.
	 * }
	 */
	public function resolve_prompt( $prompt_id = 0, $prompt_type = 'system' ) {
		$prompt_id   = absint( $prompt_id );
		$prompt_type = sanitize_key( $prompt_type );

		if ( '' === $prompt_type ) {
			$prompt_type = 'system';
		}

		if ( $prompt_id > 0 ) {
			$resolved = $this->get_prompt_resolution( $prompt_id, 'explicit', $prompt_type );

			if ( is_array( $resolved ) ) {
				return $resolved;
			}
		}

		$selected_prompt_id = $this->find_prompt_id_by_meta( '_gg_prompt_selected', '1', $prompt_type );
		if ( $selected_prompt_id > 0 ) {
			$resolved = $this->get_prompt_resolution( $selected_prompt_id, 'selected', $prompt_type );

			if ( is_array( $resolved ) ) {
				return $resolved;
			}
		}

		$factory_prompt_id = $this->find_prompt_id_by_meta( '_gg_prompt_is_factory', '1', $prompt_type );
		if ( $factory_prompt_id > 0 ) {
			$resolved = $this->get_prompt_resolution( $factory_prompt_id, 'factory', $prompt_type );

			if ( is_array( $resolved ) ) {
				return $resolved;
			}
		}

		return new WP_Error(
			'gg_data_prompt_not_found',
			__( 'No published prompt is available for the RAG pipeline.', 'gregius-data' )
		);
	}

	/**
	 * Resolve a single prompt post.
	 *
	 * @since 1.0.0
	 * @param int    $prompt_id   Prompt post ID.
	 * @param string $source      Resolution source label.
	 * @param string $prompt_type Prompt type taxonomy slug.
	 * @return array|null
	 */
	private function get_prompt_resolution( $prompt_id, $source, $prompt_type ) {
		$post = get_post( $prompt_id );
		if ( ! $post || GG_Data_Prompt::POST_TYPE !== $post->post_type ) {
			return null;
		}

		if ( ! $this->prompt_has_type( $prompt_id, $prompt_type ) ) {
			return null;
		}

		$prompt_content = trim( (string) $post->post_content );
		if ( '' === $prompt_content ) {
			return null;
		}

		$prompt_status = get_post_meta( $prompt_id, '_gg_prompt_status', true );
		if ( 'draft' === $prompt_status ) {
			return null;
		}

		$meta_version = (int) get_post_meta( $prompt_id, '_gg_prompt_version', true );
		$meta_hash    = get_post_meta( $prompt_id, '_gg_prompt_hash', true );

		if ( empty( $meta_hash ) ) {
			$meta_hash = $this->hash_content( $prompt_content );
			update_post_meta( $prompt_id, '_gg_prompt_hash', $meta_hash );
		}

		return array(
			'content'  => $this->expand_tokens( $prompt_content ),
			'metadata' => array(
				'id'          => $prompt_id,
				'version'     => $meta_version > 0 ? $meta_version : 1,
				'hash'        => sanitize_text_field( $meta_hash ),
				'source'      => $source,
				'prompt_type' => $prompt_type,
			),
		);
	}

	/**
	 * Find the first published prompt matching a meta value.
	 *
	 * @since 1.0.0
	 * @param string $meta_key    Meta key.
	 * @param string $meta_value  Meta value.
	 * @param string $prompt_type Prompt type taxonomy slug.
	 * @return int
	 */
	private function find_prompt_id_by_meta( $meta_key, $meta_value, $prompt_type ) {
		$posts = get_posts(
			array(
				'post_type'      => GG_Data_Prompt::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$normalized_prompt_type = sanitize_key( $prompt_type );

		foreach ( $posts as $post_id ) {
			if ( ! $this->prompt_has_type( (int) $post_id, $normalized_prompt_type ) ) {
				continue;
			}

			$record_meta_value = (string) get_post_meta( (int) $post_id, $meta_key, true );
			if ( (string) $meta_value !== $record_meta_value ) {
				continue;
			}

			$record_status = (string) get_post_meta( (int) $post_id, '_gg_prompt_status', true );
			if ( 'published' !== $record_status ) {
				continue;
			}

			return (int) $post_id;
		}

		return 0;
	}

	/**
	 * Check whether a prompt post has a specific prompt type term.
	 *
	 * @since 1.0.0
	 * @param int    $prompt_id   Prompt post ID.
	 * @param string $prompt_type Prompt type taxonomy slug.
	 * @return bool
	 */
	private function prompt_has_type( $prompt_id, $prompt_type ) {
		if ( ! taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
			return 'system' === $prompt_type;
		}

		$terms = wp_get_object_terms( (int) $prompt_id, GG_Data_Prompt::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 'system' === $prompt_type;
		}

		return in_array( $prompt_type, $terms, true );
	}

	/**
	 * Replace {{date}}, {{time}}, and {{datetime}} tokens with live WordPress values.
	 *
	 * @since 1.0.0
	 * @param string $content Raw prompt content.
	 * @return string Content with tokens expanded.
	 */
	private function expand_tokens( $content ) {
		$tokens = array(
			'{{date}}'     => wp_date( 'F j, Y' ),
			'{{time}}'     => wp_date( 'g:i A T' ),
			'{{datetime}}' => wp_date( 'F j, Y' ) . ' ' . wp_date( 'g:i A T' ),
		);

		return str_replace( array_keys( $tokens ), array_values( $tokens ), $content );
	}

	/**
	 * Build deterministic prompt hash.
	 *
	 * @since 1.0.0
	 * @param string $content Prompt content.
	 * @return string SHA256 hash.
	 */
	private function hash_content( $content ) {
		$normalized = str_replace( "\r\n", "\n", trim( (string) $content ) );
		return hash( 'sha256', $normalized );
	}
}
