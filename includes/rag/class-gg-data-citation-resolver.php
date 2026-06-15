<?php
/**
 * Citation Resolver for RAG Responses
 *
 * Builds server-side reference lists from citation sources and content markers,
 * mirroring the frontend citation resolution logic for portability across consumers.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Citation Resolver class.
 *
 * Handles server-side reference building and resolution for RAG responses.
 *
 * @since 1.0.0
 */
class GG_Data_Citation_Resolver {

	/**
	 * Resolve references from content and sources.
	 *
	 * Builds an ordered array of citation references aligned with [Source N] markers
	 * in the content. Mirrors frontend getCitationSources + getReferenceEntries logic.
	 *
	 * Prefers citation_sources array (ordered, deduplicated) and falls back to sources.
	 * Filters only cited references (by scanning for [Source N] in content) and
	 * deduplicates by URL.
	 *
	 * @since 1.0.0
	 * @param string $content        Assistant response content with [Source N] markers.
	 * @param array  $citation_sources Optional. Ordered array of citation source objects.
	 * @param array  $sources        Optional. Fallback array of source objects.
	 * @return array Array of reference entries {citationIndex, title, url}.
	 */
	public static function resolve_references( $content, $citation_sources = array(), $sources = array() ) {
		// Extract cited source indices from content.
		$cited_indices        = self::extract_cited_indices( $content );
		$has_inline_citations = ! empty( $cited_indices );

		// Try using citation_sources first (preferred, pre-built order).
		if ( is_array( $citation_sources ) && ! empty( $citation_sources ) ) {
			$references = self::build_references_from_sources(
				$citation_sources,
				$cited_indices,
				$has_inline_citations
			);

			if ( ! empty( $references ) ) {
				return $references;
			}
		}

		// Fall back to sources array if citation_sources unavailable or empty.
		if ( is_array( $sources ) && ! empty( $sources ) ) {
			$references = self::build_references_from_sources(
				$sources,
				$cited_indices,
				$has_inline_citations
			);

			if ( ! empty( $references ) ) {
				return $references;
			}
		}

		// Return empty array if no sources available.
		return array();
	}

	/**
	 * Extract cited source indices from content.
	 *
	 * Scans content for [Source N] pattern (case-insensitive) and returns
	 * the set of 1-based indices found.
	 *
	 * @since 1.0.0
	 * @param string $content Content to scan for citation markers.
	 * @return array Set of cited 1-based indices (array values).
	 */
	private static function extract_cited_indices( $content ) {
		$cited = array();

		if ( ! is_string( $content ) || empty( $content ) ) {
			return $cited;
		}

		// Match [Source N] pattern (case-insensitive, N is digits).
		$pattern = '/\[Source\s+(\d+)\]/i';

		if ( preg_match_all( $pattern, $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$index = intval( $match );
				if ( $index > 0 && ! in_array( $index, $cited, true ) ) {
					$cited[] = $index;
				}
			}
		}

		return array_values( $cited );
	}

	/**
	 * Build references array from source list.
	 *
	 * Converts source objects to reference entries, optionally filtering by
	 * cited indices and deduplicating by URL.
	 *
	 * @since 1.0.0
	 * @param array $sources              Array of source objects {title, url, ...}.
	 * @param array $cited_indices        Array of 1-based cited indices to filter by.
	 * @param bool  $has_inline_citations Whether content has inline citation markers.
	 * @return array Array of {citationIndex, title, url} entries.
	 */
	private static function build_references_from_sources( $sources, $cited_indices, $has_inline_citations ) {
		$entries   = array();
		$seen_urls = array();

		foreach ( $sources as $idx => $source ) {
			// 1-based index for matching [Source N] markers.
			$citation_index = $idx + 1;

			// If content has inline citations, skip sources not cited.
			if ( $has_inline_citations && ! in_array( $citation_index, $cited_indices, true ) ) {
				continue;
			}

			// Extract and validate URL.
			$url = isset( $source['url'] ) ? $source['url'] : '';
			if ( empty( $url ) || in_array( $url, $seen_urls, true ) ) {
				continue;
			}

			// Track seen URL to avoid duplicates.
			$seen_urls[] = $url;

			// Build reference entry.
			$entries[] = array(
				'citationIndex' => $citation_index,
				'title'         => isset( $source['title'] ) ? sanitize_text_field( $source['title'] ) : __( 'Untitled source', 'gregius-data' ),
				'url'           => esc_url_raw( $url ),
			);
		}

		return $entries;
	}
}
