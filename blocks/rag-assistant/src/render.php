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
$embedding_model_key = isset( $attributes['embeddingModelKey'] ) ? $attributes['embeddingModelKey'] : 'tfidf-300';
$llm_model_id        = isset( $attributes['llmModelId'] ) ? $attributes['llmModelId'] : '';
$rewrite_model       = isset( $attributes['rewriteModelId'] ) ? $attributes['rewriteModelId'] : '';
$rerank_model        = isset( $attributes['rerankModelId'] ) ? $attributes['rerankModelId'] : '';
$prompt_id           = isset( $attributes['promptId'] ) ? absint( $attributes['promptId'] ) : 0;
$security_prompt_id  = isset( $attributes['securityPromptId'] ) ? absint( $attributes['securityPromptId'] ) : 0;
$placeholder         = isset( $attributes['placeholder'] ) ? $attributes['placeholder'] : __( 'Ask a question...', 'gregius-data' );
$enable_streaming    = isset( $attributes['enableStreaming'] ) ? (bool) $attributes['enableStreaming'] : true;
$require_login       = isset( $attributes['requireLogin'] ) ? wp_validate_boolean( $attributes['requireLogin'] ) : true;
$access_level        = 'logged_in';

if ( class_exists( 'GG_Data_Settings_Manager' ) ) {
	$settings_manager = new GG_Data_Settings_Manager();
	$access_level     = (string) $settings_manager->get( 'rag_access_level', 'logged_in' );
}

$access_level = apply_filters( 'gg_data_rag_access_level', $access_level );
$guest_api_blocked   = ! is_user_logged_in() && 'public' !== $access_level;
$effective_gate      = $require_login || $guest_api_blocked;

if ( $effective_gate && ! is_user_logged_in() ) {
	$login_url = wp_login_url( get_permalink() );
	echo '<div class="gg-rag-login-required"><p>' . esc_html__( 'Please sign in to use this feature.', 'gregius-data' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Sign in', 'gregius-data' ) . '</a></p></div>';
	return;
}
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

				'data-placeholder'         => $placeholder,
				'data-enable-streaming'    => $enable_streaming ? 'true' : 'false',
			)
		)
	);
	?>
></div>
