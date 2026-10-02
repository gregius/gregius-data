<?php
/**
 * RAG Chat Block - Frontend Template
 *
 * @package gregius-data
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get attributes with defaults.
$block_id            = isset( $attributes['blockId'] ) ? $attributes['blockId'] : 'gregius-rag-assistant-' . wp_unique_id();
$connection_id       = isset( $attributes['connectionId'] ) ? $attributes['connectionId'] : '';
$embedding_model_key = isset( $attributes['embeddingModelKey'] ) ? $attributes['embeddingModelKey'] : 'hashingtf-murmur3-1024';
$llm_model_id        = isset( $attributes['llmModelId'] ) ? $attributes['llmModelId'] : '';
$rewrite_model       = isset( $attributes['rewriteModelId'] ) ? $attributes['rewriteModelId'] : '';
$rerank_model        = isset( $attributes['rerankModelId'] ) ? $attributes['rerankModelId'] : '';
$prompt_id           = isset( $attributes['promptId'] ) ? absint( $attributes['promptId'] ) : 0;
$security_prompt_id  = isset( $attributes['securityPromptId'] ) ? absint( $attributes['securityPromptId'] ) : 0;
$placeholder         = isset( $attributes['placeholder'] ) ? $attributes['placeholder'] : __( 'Ask a question...', 'gregius-data' );
$enable_streaming    = isset( $attributes['enableStreaming'] ) ? (bool) $attributes['enableStreaming'] : true;
$require_login = isset( $attributes['requireLogin'] ) ? wp_validate_boolean( $attributes['requireLogin'] ) : true;

// Site-wide policy is filter-driven and fail-closed by default (logged_in).
$access_level = (string) apply_filters( 'gg_data_rag_access_level', 'logged_in' );

// Guests may use a block that opts in (requireLogin = false), unless the
// site-wide policy is `capability` (the only hard ceiling for guests).
$guest_allowed = ( 'capability' !== $access_level )
	&& ( 'public' === $access_level || ! $require_login );

if ( ! is_user_logged_in() && ! $guest_allowed ) {
	$login_url = wp_login_url( get_permalink() );
	echo '<div class="gg-rag-login-required"><p>' . esc_html__( 'Please sign in to use this feature.', 'gregius-data' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Sign in', 'gregius-data' ) . '</a></p></div>';
	return;
}

$guest_post_id      = get_queried_object_id();
$guest_access_nonce = $guest_allowed ? wp_create_nonce( 'gg_rag_guest_access:' . $block_id . ':' . $guest_post_id ) : '';
?>

<div
	<?php
	echo wp_kses_data(
		get_block_wrapper_attributes(
			array(
				'id'                       => $block_id,
				'data-connection-id'       => $connection_id,
				'data-embedding-model-key' => $embedding_model_key,
				'data-llm-model-id'        => $llm_model_id,
				'data-rewrite-model-id'    => $rewrite_model,
				'data-rerank-model-id'     => $rerank_model,
				'data-prompt-id'           => $prompt_id,
				'data-security-prompt-id'  => $security_prompt_id,

				'data-placeholder'           => $placeholder,
				'data-enable-streaming'      => $enable_streaming ? 'true' : 'false',
				'data-gg-block-id'           => $block_id,
				'data-current-post-id'       => $guest_post_id,
				'data-gg-guest-access-nonce' => $guest_access_nonce,
			)
		)
	);
	?>
></div>
