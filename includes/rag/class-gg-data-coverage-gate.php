<?php
/**
 * Coverage Gate
 *
 * Evaluates evidence coverage before answer synthesis.
 * Implements shared evidence coverage policy for pre-synthesis governance.
 *
 * Decision types:
 * - full:    qualifying evidence found for all required slots.
 * - partial: qualifying evidence found for at least one slot, but not all.
 * - abstain: no qualifying evidence found for any required slot.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coverage Gate class.
 *
 * Evaluates whether retrieved evidence is sufficient to produce a full answer,
 * a partial answer, or an abstention for a given query intent.
 *
 * @since 1.0.0
 */
class GG_Data_Coverage_Gate {

	/**
	 * Minimum relevance score for a chunk to qualify as evidence.
	 *
	 * Chunks with a score below this value are excluded from slot matching.
	 * Defaults to 0.0 so all retrieved chunks count as evidence; callers
	 * may pass a higher threshold when a reranker score is available.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	private $relevance_threshold;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param float $relevance_threshold Minimum relevance score. Default 0.0.
	 */
	public function __construct( $relevance_threshold = 0.0 ) {
		$this->relevance_threshold = (float) $relevance_threshold;
	}

	/**
	 * Core coverage evaluation engine.
	 *
	 * Maps each required slot to qualifying evidence chunks, then applies
	 * the coverage decision rules from the policy spec.
	 *
	 * @since 1.0.0
	 * @param array  $chunks Evidence chunks with 'post_id', 'title', 'content', and optional 'score'.
	 * @param string $intent Intent type: 'compare' | 'single'.
	 * @param array  $slots  Required slots keyed by slot name, value is the entity string.
	 * @return array {
	 *   string   $intent            Intent type.
	 *   string[] $required_slots    Slot names.
	 *   array    $slot_entities     Slot name => entity string map.
	 *   string[] $covered_slots     Slot names that have qualifying evidence.
	 *   string[] $uncovered_slots   Slot names with no qualifying evidence.
	 *   array    $chunks_per_slot   Slot name => array of matching chunks.
	 *   string   $decision          'full' | 'partial' | 'abstain'.
	 *   int      $evidence_count    Total qualifying chunks across all slots (before dedup).
	 *   float    $threshold_used    The relevance threshold applied.
	 *   bool     $fallback_used     Whether a fallback path was triggered.
	 * }
	 */
	public function evaluate( $chunks, $intent, $slots ) {
		$covered_slots           = array();
		$uncovered_slots         = array();
		$chunks_per_slot         = array();
		$primary_chunks_per_slot = array();
		$evidence_total          = 0;

		foreach ( $slots as $slot_name => $entity ) {
			$matched = $this->find_chunks_for_entity( $chunks, $entity );
			$primary = $this->find_primary_chunks_for_entity( $chunks, $entity );

			if ( ! empty( $matched ) ) {
				$covered_slots[]                       = $slot_name;
				$chunks_per_slot[ $slot_name ]         = $matched;
				$primary_chunks_per_slot[ $slot_name ] = $primary;
				$evidence_total                       += count( $matched );
			} else {
				$uncovered_slots[]                     = $slot_name;
				$chunks_per_slot[ $slot_name ]         = array();
				$primary_chunks_per_slot[ $slot_name ] = array();
			}
		}

		$total_slots   = count( $slots );
		$covered_count = count( $covered_slots );

		// Coverage decision rules for full/partial/abstain governance.
		if ( 0 === $covered_count ) {
			$decision = 'abstain';
		} elseif ( $covered_count < $total_slots ) {
			$decision = 'partial';
		} else {
			$decision = 'full';
		}

		// For compare intents, require primary evidence for each entity to keep full
		// decisions robust when slot terms overlap (e.g. notes vs footnotes).
		if ( 'compare' === $intent && 'full' === $decision ) {
			$primary_covered_slots = array();

			foreach ( $slots as $slot_name => $entity ) {
				if ( ! empty( $primary_chunks_per_slot[ $slot_name ] ) ) {
					$primary_covered_slots[] = $slot_name;
				}
			}

			if ( count( $primary_covered_slots ) < $total_slots ) {
				$decision = 'partial';

				if ( ! empty( $primary_covered_slots ) ) {
					$covered_slots   = $primary_covered_slots;
					$uncovered_slots = array_values( array_diff( array_keys( $slots ), $primary_covered_slots ) );

					// Keep context for covered slots focused on primary evidence.
					foreach ( $slots as $slot_name => $entity ) {
						$chunks_per_slot[ $slot_name ] = in_array( $slot_name, $primary_covered_slots, true )
							? $primary_chunks_per_slot[ $slot_name ]
							: array();
					}
				} else {
					// Preserve secondary evidence when no primary title matches exist.
					$covered_slots   = array();
					$uncovered_slots = array();

					foreach ( $slots as $slot_name => $entity ) {
						if ( ! empty( $chunks_per_slot[ $slot_name ] ) ) {
							$covered_slots[] = $slot_name;
						} else {
							$uncovered_slots[] = $slot_name;
						}
					}
				}
			}
		}

		return array(
			'intent'                  => $intent,
			'required_slots'          => array_keys( $slots ),
			'slot_entities'           => $slots,
			'covered_slots'           => $covered_slots,
			'uncovered_slots'         => $uncovered_slots,
			'chunks_per_slot'         => $chunks_per_slot,
			'primary_chunks_per_slot' => $primary_chunks_per_slot,
			'decision'                => $decision,
			'evidence_count'          => $evidence_total,
			'threshold_used'          => $this->relevance_threshold,
			'fallback_used'           => false,
		);
	}

	/**
	 * Find primary evidence chunks for an entity.
	 *
	 * Primary evidence is title-weighted and used to decide whether a compare
	 * slot has strong support for full coverage.
	 *
	 * @since 1.0.0
	 * @param array  $chunks Evidence chunks.
	 * @param string $entity Entity name.
	 * @return array Primary evidence chunks.
	 */
	private function find_primary_chunks_for_entity( $chunks, $entity ) {
		$primary_terms = $this->get_primary_entity_terms( $entity );
		$matched       = array();

		if ( empty( $primary_terms ) ) {
			return $matched;
		}

		foreach ( $chunks as $chunk ) {
			if ( isset( $chunk['score'] ) && (float) $chunk['score'] < $this->relevance_threshold ) {
				continue;
			}

			$title = strtolower( $chunk['title'] ?? '' );

			foreach ( $primary_terms as $term ) {
				if ( $this->contains_term( $title, $term ) ) {
					$matched[] = $chunk;
					break;
				}
			}
		}

		return $matched;
	}

	/**
	 * Get primary terms used for strong compare-slot evidence.
	 *
	 * @since 1.0.0
	 * @param string $entity Entity text.
	 * @return string[] Primary normalized terms.
	 */
	private function get_primary_entity_terms( $entity ) {
		$variants = $this->expand_entity_variants( $entity );

		if ( empty( $variants ) ) {
			return array();
		}

		$primary = array();
		foreach ( $variants as $variant ) {
			if ( false !== strpos( $variant, ' block' ) || false !== strpos( $variant, '/' ) ) {
				$primary[] = $variant;

				// Singularize common block labels so "image blocks" can match
				// canonical titles like "Image block" in primary evidence checks.
				if ( preg_match( '/\sblocks$/', $variant ) ) {
					$primary[] = preg_replace( '/\sblocks$/', ' block', $variant );
				}
			}
		}

		if ( empty( $primary ) ) {
			$primary = $variants;
		}

		return array_values( array_unique( array_filter( $primary ) ) );
	}

	/**
	 * Find chunks that contain evidence for a given entity.
	 *
	 * Matching strategy (in order):
	 * 1. Full entity string — case-insensitive substring match against title or content.
	 * 2. Word-level match — all significant words of the entity appear in title or content.
	 *    Words shorter than 3 characters are ignored (avoids matching "of", "in", etc.).
	 *
	 * Chunks below the relevance threshold are excluded before matching.
	 *
	 * @since 1.0.0
	 * @param array  $chunks Evidence chunks.
	 * @param string $entity Entity name to find.
	 * @return array Matching chunks (subset of $chunks).
	 */
	private function find_chunks_for_entity( $chunks, $entity ) {
		$entity_variants = $this->expand_entity_variants( $entity );

		$matched = array();

		foreach ( $chunks as $chunk ) {
			// Skip chunks below relevance threshold.
			if ( isset( $chunk['score'] ) && (float) $chunk['score'] < $this->relevance_threshold ) {
				continue;
			}

			$title   = strtolower( $chunk['title'] ?? '' );
			$content = strtolower( wp_strip_all_tags( $chunk['content'] ?? '' ) );

			$chunk_matches = false;

			foreach ( $entity_variants as $variant ) {
				// Phrase/term match with word boundaries to avoid token collisions.
				if ( $this->contains_term( $title, $variant ) || $this->contains_term( $content, $variant ) ) {
					$chunk_matches = true;
					break;
				}

				// Word-level match: every significant word must appear somewhere.
				$variant_words = array_values(
					array_filter(
						preg_split( '/\s+/', $variant ),
						function ( $word ) {
							return strlen( $word ) > 2;
						}
					)
				);

				if ( empty( $variant_words ) ) {
					continue;
				}

				$all_words_match = true;
				foreach ( $variant_words as $word ) {
					if ( ! $this->contains_term( $title, $word ) && ! $this->contains_term( $content, $word ) ) {
						$all_words_match = false;
						break;
					}
				}

				if ( $all_words_match ) {
					$chunk_matches = true;
					break;
				}
			}

			if ( $chunk_matches ) {
				$matched[] = $chunk;
			}
		}

		return $matched;
	}

	/**
	 * Check whether a text contains a term using word-boundary aware matching.
	 *
	 * This prevents false positives like matching "notes" inside "footnotes".
	 *
	 * @since 1.0.0
	 * @param string $text Text to search within (already normalized to lowercase).
	 * @param string $term Search term.
	 * @return bool True when the term is present with token boundaries.
	 */
	private function contains_term( $text, $term ) {
		$term = trim( $term );

		if ( '' === $term ) {
			return false;
		}

		if ( false === strpos( $term, ' ' ) ) {
			$pattern = '/\b' . preg_quote( $term, '/' ) . '\b/u';
			return 1 === preg_match( $pattern, $text );
		}

		// Multi-word phrase: allow flexible internal whitespace while preserving boundaries.
		$phrase  = preg_quote( $term, '/' );
		$phrase  = preg_replace( '/\\\s+/', '\\s+', $phrase );
		$pattern = '/\b' . $phrase . '\b/u';

		return 1 === preg_match( $pattern, $text );
	}

	/**
	 * Expand an entity into normalized variants used for slot matching.
	 *
	 * @since 1.0.0
	 * @param string $entity Entity text from tool selection.
	 * @return string[] Normalized variants (lowercase, deduplicated).
	 */
	private function expand_entity_variants( $entity ) {
		$base = $this->normalize_entity_text( $entity );

		if ( '' === $base ) {
			return array();
		}

		$variants = array( $base );

		// Strip common suffixes that often over-constrain matching.
		$without_suffix = preg_replace( '/\s+(feature|features|block|blocks)$/', '', $base );
		if ( ! empty( $without_suffix ) && $without_suffix !== $base ) {
			$variants[] = trim( $without_suffix );
		}

		// Curated aliases for known WordPress vocabulary used in compare queries.
		$aliases = array(
			'notes feature'   => array( 'notes' ),
			'footnotes block' => array( 'footnotes' ),
		);

		if ( isset( $aliases[ $base ] ) ) {
			$variants = array_merge( $variants, $aliases[ $base ] );
		}

		return array_values( array_unique( array_filter( $variants ) ) );
	}

	/**
	 * Normalize raw entity text for matching.
	 *
	 * @since 1.0.0
	 * @param string $entity Raw entity text.
	 * @return string Normalized entity text.
	 */
	private function normalize_entity_text( $entity ) {
		$entity = strtolower( trim( wp_strip_all_tags( $entity ) ) );
		$entity = preg_replace( '/\s+/', ' ', $entity );

		return trim( $entity, " \t\n\r\0\x0B?.,!\"'" );
	}
}
