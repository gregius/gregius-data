<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/class-gregius-data-model-catalog.php';

/**
 * Unit tests for the curated model catalog.
 */
class Test_Gregius_Data_Model_Catalog extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_llm_models_returns_id_to_name_map() {
		$models = Gregius_Data_Model_Catalog::llm_models( 'gemini' );

		$this->assertArrayHasKey( 'gemini-2.5-flash', $models );
		$this->assertSame( 'Gemini 2.5 Flash', $models['gemini-2.5-flash'] );
	}

	public function test_vision_models_only_include_vision_capable_llms() {
		$vision = Gregius_Data_Model_Catalog::vision_models( 'openai' );

		$this->assertContains( 'gpt-4o-mini', $vision );
		$this->assertNotContains( 'text-embedding-3-small', $vision );
	}

	public function test_embedding_models_carry_dimensions() {
		$models = Gregius_Data_Model_Catalog::embedding_models( 'openai' );

		$this->assertArrayHasKey( 'text-embedding-3-small', $models );
		$this->assertSame( 1536, $models['text-embedding-3-small']['dimensions'] );
		$this->assertSame( 3072, $models['text-embedding-3-large']['dimensions'] );
	}

	public function test_embedding_dimensions_returns_flat_map() {
		$dims = Gregius_Data_Model_Catalog::embedding_dimensions();

		$this->assertSame( 1536, $dims['text-embedding-3-small'] );
		$this->assertSame( 1024, $dims['hashingtf-murmur3-1024'] );
	}

	public function test_deepseek_models_are_not_vision_capable() {
		$vision = Gregius_Data_Model_Catalog::vision_models( 'deepseek' );

		$this->assertSame( array(), $vision );
	}

	public function test_anthropic_models_match_supported_list() {
		$models = Gregius_Data_Model_Catalog::llm_models( 'anthropic' );

		$this->assertArrayHasKey( 'claude-opus-4-20250514', $models );
		$this->assertArrayHasKey( 'claude-sonnet-4-20250514', $models );
		$this->assertArrayHasKey( 'claude-haiku-4-20250514', $models );
		$this->assertArrayNotHasKey( 'claude-haiku-5-5', $models );
	}

	public function test_embedding_and_rerank_are_absent_from_simple_providers() {
		$this->assertSame( array(), Gregius_Data_Model_Catalog::embedding_models( 'deepseek' ) );
		$this->assertSame( array(), Gregius_Data_Model_Catalog::rerank_models( 'openai' ) );
	}

	public function test_context_limits_cover_every_llm_model() {
		$limits = Gregius_Data_Model_Catalog::context_limits();

		$this->assertSame( 1048576, $limits['gemini-2.5-flash'] );
		$this->assertSame( 128000, $limits['gpt-4o-mini'] );
		$this->assertSame( 200000, $limits['claude-haiku-4-20250514'] );
	}

	public function test_max_tokens_map_covers_every_llm_model() {
		$max = Gregius_Data_Model_Catalog::max_tokens_map();

		$this->assertSame( 8192, $max['gemini-2.5-flash'] );
		$this->assertSame( 16384, $max['gpt-4o-mini'] );
	}

	public function test_unknown_provider_returns_empty() {
		$this->assertSame( array(), Gregius_Data_Model_Catalog::models( 'does-not-exist' ) );
	}
}
