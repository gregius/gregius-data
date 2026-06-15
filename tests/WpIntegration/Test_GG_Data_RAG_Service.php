<?php
/**
 * Tier 3 Full WP — RAG Service Tests
 *
 * Tests GG_Data_RAG_Service retrieve/generate methods.
 * Note: Actual LLM API calls require mocked transport.
 */

class Test_GG_Data_RAG_Service extends WP_UnitTestCase {

	protected $rag;

	public function set_up() {
		parent::set_up();
		$this->rag = new GG_Data_RAG_Service( 'default', 'hashingtf-embeddings' );
	}

	public function test_constructor_accepts_connection_and_model() {
		$rag = new GG_Data_RAG_Service( 'test_conn', 'tfidf-300-embeddings' );
		$this->assertInstanceOf( GG_Data_RAG_Service::class, $rag );
	}

	public function test_get_available_tools_returns_array() {
		$tools = $this->rag->get_available_tools();
		$this->assertIsArray( $tools );
	}

	public function test_retrieve_chunks_returns_array() {
		$options = array(
			'top_k' => 5,
			'mode'  => 'fts',
		);
		$result = $this->rag->retrieve_chunks( 'test query', $options );
		$this->assertIsArray( $result );
	}

	public function test_append_manifest_metadata_returns_array() {
		$input  = array( 'answer' => 'test' );
		$result = $this->rag->append_manifest_metadata(
			$input,
			array( 'query' => 'test', 'llm_model_id' => 'gpt-4' )
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'answer', $result );
	}
}
