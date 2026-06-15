<?php
/**
 * Content Processor for Gregius Data
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Processor for Gregius Data
 *
 * Handles content cleaning for AI/vector processing.
 * Used by GG_Data_Content_Cleaner for batch operations.
 */
class GG_Data_Content_Processor {

	/**
	 * Clean content by removing HTML tags, shortcodes, and extra whitespace
	 *
	 * Strips Gutenberg blocks, HTML markup, and shortcodes to produce plain text
	 * suitable for vector embeddings and semantic search.
	 *
	 * @param string $content           Raw content (Gutenberg blocks + HTML).
	 * @param bool   $enforce_min_length Whether to enforce 10-char minimum (default true).
	 *                                   Set to false for titles which are naturally short.
	 * @return string Cleaned plain text content
	 */
	public function clean_content( $content, $enforce_min_length = true ) {
		if ( empty( $content ) ) {
			return '';
		}

		// Remove HTML tags.
		$content = wp_strip_all_tags( $content );

		// Remove shortcodes.
		$content = strip_shortcodes( $content );

		// Remove extra whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );

		// Trim.
		$content = trim( $content );

		// Remove very short content (less than 10 characters) - for content/excerpts only.
		// Titles are naturally short and should not be filtered by length.
		if ( $enforce_min_length && strlen( $content ) < 10 ) {
			return '';
		}

		return $content;
	}

	/**
	 * Clean a title (no minimum length enforcement)
	 *
	 * @param string $title Raw title.
	 * @return string Cleaned title.
	 */
	public function clean_title( $title ) {
		return $this->clean_content( $title, false );
	}
}
