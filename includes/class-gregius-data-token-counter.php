<?php
/**
 * Token Counter
 *
 * Estimates token counts for text used by the RAG pipeline.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token Counter class.
 *
 * Estimates token counts from text.
 *
 * @since 1.0.0
 */
class Gregius_Data_Token_Counter {

	/**
	 * Estimate token count from text.
	 *
	 * Uses a rough approximation of ~4 characters per token for English text.
	 * For more accurate counts, consider using tiktoken library or OpenAI's tokenizer API.
	 *
	 * @since 1.0.0
	 * @param string $text The text to estimate tokens for.
	 * @return int Estimated token count.
	 */
	public function estimate_tokens( $text ) {
		if ( empty( $text ) ) {
			return 0;
		}

		// Rough estimate: ~4 characters per token.
		$char_count = strlen( $text );
		return (int) ceil( $char_count / 4 );
	}
}
