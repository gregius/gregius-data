<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Shared JSON output formatting for RAG Benchmark and Evaluation services.
 *
 * @package Gregius_Data
 */

trait GG_Data_Format_Json_Output {

	/**
	 * Normalize a RAG result into a structured JSON payload.
	 *
	 * @param array  $result      RAG result with answer, chunks, sources, metadata.
	 * @param int    $duration_ms Execution duration in milliseconds.
	 * @param string $query       Original query text.
	 * @return array
	 */
	protected function format_json_output( $result, $duration_ms, $query ) {
		$chunks             = isset( $result['chunks'] ) && is_array( $result['chunks'] ) ? $result['chunks'] : array();
		$retrieved_contexts = array();

		foreach ( $chunks as $chunk ) {
			$content = isset( $chunk['content'] ) ? (string) $chunk['content'] : '';
			if ( '' !== $content ) {
				$retrieved_contexts[] = $content;
			}
		}

		return array(
			'query'              => $query,
			'answer'             => isset( $result['answer'] ) ? (string) $result['answer'] : '',
			'retrieved_contexts' => $retrieved_contexts,
			'sources'            => isset( $result['sources'] ) ? $result['sources'] : array(),
			'metadata'           => array_merge(
				isset( $result['metadata'] ) && is_array( $result['metadata'] ) ? $result['metadata'] : array(),
				array(
					'cli_duration_ms' => $duration_ms,
				)
			),
		);
	}
}
