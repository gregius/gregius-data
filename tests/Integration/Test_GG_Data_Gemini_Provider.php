<?php
/**
 * Tier 2 Integration — Gemini provider embedding contract.
 *
 * Locks the generate_embedding() return envelope to the `vector` key (per
 * GG_Data_AI_Provider_Interface). Regression guard for #1116: Gemini returned
 * `embedding` instead of `vector`, breaking both the ingest strategy and the
 * RAG query-vector literal.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_Gemini_Provider extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();

		require_once __DIR__ . '/../../includes/interfaces/interface-gg-data-ai-provider.php';
		require_once __DIR__ . '/../../includes/ai/providers/class-gg-data-gemini-provider.php';

		Functions\when( 'wp_safe_remote_post' )->justReturn( array( 'fake' ) );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"embedding":{"values":[0.1,0.2,0.3]}}' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_generate_embedding_returns_vector_key(): void {
		$provider = new GG_Data_Gemini_Provider();
		$result   = $provider->generate_embedding(
			'hello',
			array(
				'model'   => 'gemini-embedding-2',
				'api_key' => 'test-key',
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'vector', $result );
		$this->assertEquals( array( 0.1, 0.2, 0.3 ), $result['vector'] );
		$this->assertArrayNotHasKey( 'embedding', $result );
	}
}
