<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/class-gg-data-model-catalog.php';

/**
 * Unit tests for the curated model catalog.
 */
class Test_GG_Data_Model_Catalog extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_llm_models_returns_id_to_name_map() {
		$models = GG_Data_Model_Catalog::llm_models( 'gemini' );

		$this->assertArrayHasKey( 'gemini-3.8-flash', $models );
		$this->assertSame( 'Gemini 3.8 Flash', $models['gemini-3.8-flash'] );
	}

	public function test_vision_models_only_include_vision_capable_llms() {
		$vision = GG_Data_Model_Catalog::vision_models( 'openai' );

		$this->assertContains( 'gpt-4o-mini', $vision );
		$this->assertNotContains( 'text-embedding-3-small', $vision );
	}

	public function test_embedding_models_carry_dimensions() {
		$models = GG_Data_Model_Catalog::embedding_models( 'openai' );

		$this->assertArrayHasKey( 'text-embedding-3-small', $models );
		$this->assertSame( 1536, $models['text-embedding-3-small']['dimensions'] );
		$this->assertSame( 3072, $models['text-embedding-3-large']['dimensions'] );
	}

	public function test_voyage_rerank_is_not_corrupted() {
		$models = GG_Data_Model_Catalog::rerank_models( 'voyage' );

		$this->assertArrayHasKey( 'rerank-3', $models );
		$this->assertArrayHasKey( 'rerank-3-lite', $models );
	}

	public function test_deepseek_flash_is_vision_capable_but_pro_is_not() {
		$vision = GG_Data_Model_Catalog::vision_models( 'deepseek' );

		$this->assertContains( 'deepseek-flash', $vision );
		$this->assertNotContains( 'deepseek-v4-pro', $vision );
	}

	public function test_anthropic_keeps_both_model_generations() {
		$models = GG_Data_Model_Catalog::llm_models( 'anthropic' );

		$this->assertArrayHasKey( 'claude-haiku-5-5', $models );
		$this->assertArrayHasKey( 'claude-haiku-4-20250514', $models );
	}

	public function test_embedding_and_rerank_are_absent_from_simple_providers() {
		$this->assertSame( array(), GG_Data_Model_Catalog::embedding_models( 'deepseek' ) );
		$this->assertSame( array(), GG_Data_Model_Catalog::rerank_models( 'openai' ) );
	}

	public function test_context_limits_cover_every_llm_model() {
		$limits = GG_Data_Model_Catalog::context_limits();

		$this->assertSame( 1048576, $limits['gemini-3.8-flash'] );
		$this->assertSame( 128000, $limits['gpt-4o-mini'] );
		$this->assertSame( 200000, $limits['claude-haiku-5-5'] );
	}

	public function test_max_tokens_map_covers_every_llm_model() {
		$max = GG_Data_Model_Catalog::max_tokens_map();

		$this->assertSame( 8192, $max['gemini-3.8-flash'] );
		$this->assertSame( 16384, $max['gpt-4o-mini'] );
	}

	public function test_unknown_provider_returns_empty() {
		$this->assertSame( array(), GG_Data_Model_Catalog::models( 'does-not-exist' ) );
	}
}
