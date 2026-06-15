<?php
/**
 * RAG Assistant Block Registration
 *
 * Registers the RAG Assistant block and handles asset loading.
 *
 * @package gregius-data
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the RAG Assistant block using metadata from block.json.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function gg_data_rag_assistant_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'gg_data_rag_assistant_block_init' );

/**
 * Sets translations for the RAG Assistant block.
 */
function gg_data_rag_assistant_block_set_translations() {
	wp_set_script_translations(
		'gregius-data-rag-assistant-editor-script',
		'gregius-data',
		plugin_dir_path( __FILE__ ) . 'languages'
	);
}
add_action( 'init', 'gg_data_rag_assistant_block_set_translations' );

/**
 * Enqueue SSE configuration for RAG Assistant frontend.
 *
 * Provides AJAX URL and nonce for SSE streaming requests.
 */
function gg_data_rag_assistant_enqueue_sse_config() {
	// Only enqueue on frontend when block is likely present.
	if ( is_admin() ) {
		return;
	}

	// Check if any RAG assistant blocks are in the content.
	global $post;
	if ( ! $post || ! has_block( 'gregius-data/rag-assistant', $post ) ) {
		return;
	}

	// Get the view script handle from block.json metadata.
	$script_handle = 'gregius-data-rag-assistant-view-script';

	// Localize SSE config for the frontend script.
	wp_localize_script(
		$script_handle,
		'ggDataRagSSE',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gg_data_rag_stream' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gg_data_rag_assistant_enqueue_sse_config' );

// Translations are automatically loaded by WordPress.org for plugins since WP 4.6.
