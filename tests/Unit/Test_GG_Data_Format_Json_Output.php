<?php

require_once __DIR__ . '/../../includes/trait-gg-data-format-json-output.php';

class Test_GG_Data_Format_Json_Output extends PHPUnit\Framework\TestCase {

    /**
     * Create a concrete class using the trait for testing.
     */
    private function get_formatter() {
        return new class {
            use GG_Data_Format_Json_Output;

            public function public_format( $result, $duration_ms, $query ) {
                return $this->format_json_output( $result, $duration_ms, $query );
            }
        };
    }

    public function test_empty_chunks_returns_empty_contexts() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format(
            array(
                'chunks' => array(),
                'answer' => 'Test answer',
            ),
            1234,
            'test query'
        );
        $this->assertSame( array(), $result['retrieved_contexts'] );
    }

    public function test_chunks_with_content_added_to_contexts() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format(
            array(
                'chunks' => array(
                    array( 'content' => 'First chunk' ),
                    array( 'content' => 'Second chunk' ),
                ),
                'answer' => 'Test answer',
            ),
            1234,
            'test query'
        );
        $this->assertCount( 2, $result['retrieved_contexts'] );
        $this->assertSame( 'First chunk', $result['retrieved_contexts'][0] );
        $this->assertSame( 'Second chunk', $result['retrieved_contexts'][1] );
    }

    public function test_chunks_without_content_skipped() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format(
            array(
                'chunks' => array(
                    array( 'content' => 'Kept' ),
                    array( 'no_content' => 'Skipped' ),
                    array( 'content' => '' ),
                ),
                'answer' => 'Test',
            ),
            0,
            'query'
        );
        $this->assertCount( 1, $result['retrieved_contexts'] );
        $this->assertSame( 'Kept', $result['retrieved_contexts'][0] );
    }

    public function test_missing_answer_defaults_to_empty() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format( array(), 0, 'query' );
        $this->assertSame( '', $result['answer'] );
    }

    public function test_duration_added_to_metadata() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format( array(), 1234, 'query' );
        $this->assertSame( 1234, $result['metadata']['cli_duration_ms'] );
    }

    public function test_existing_metadata_is_preserved() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format(
            array(
                'metadata' => array(
                    'model' => 'gpt-4o',
                    'tokens' => 100,
                ),
            ),
            5678,
            'query'
        );
        $this->assertSame( 'gpt-4o', $result['metadata']['model'] );
        $this->assertSame( 100, $result['metadata']['tokens'] );
        $this->assertSame( 5678, $result['metadata']['cli_duration_ms'] );
    }

    public function test_null_chunks_handled_as_empty() {
        $formatter = $this->get_formatter();
        $result = $formatter->public_format(
            array(
                'chunks' => null,
                'answer' => 'Test',
            ),
            0,
            'query'
        );
        $this->assertSame( array(), $result['retrieved_contexts'] );
    }

    public function test_sources_passed_through() {
        $formatter = $this->get_formatter();
        $sources = array( 'source_1' => 'Title 1', 'source_2' => 'Title 2' );
        $result = $formatter->public_format(
            array(
                'sources' => $sources,
                'answer'  => 'Test',
            ),
            0,
            'query'
        );
        $this->assertSame( $sources, $result['sources'] );
    }
}
