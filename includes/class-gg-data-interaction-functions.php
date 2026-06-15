<?php
/**
 * Interaction helper functions.
 *
 * Provides function wrappers for interaction recording APIs.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Record a search interaction.
 *
 * Wrapper function for GG_Data_Interaction::record_search().
 *
 * @since 1.0.0
 * @param array $args Interaction arguments.
 * @return int|WP_Error Post ID or error.
 */
function gg_data_record_search( array $args ) {
	return GG_Data_Interaction::record_search( $args );
}

/**
 * Record a RAG interaction.
 *
 * Wrapper function for GG_Data_Interaction::record_rag().
 *
 * @since 1.0.0
 * @param string $conversation_id Client-provided conversation UUID.
 * @param array  $args            Turn arguments.
 * @return int|WP_Error Post ID or error.
 */
function gg_data_record_rag( $conversation_id, array $args ) {
	return GG_Data_Interaction::record_rag( $conversation_id, $args );
}
