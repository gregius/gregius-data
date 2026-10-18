<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-gregius-data-model-catalog.php';
require_once __DIR__ . '/../../includes/class-gregius-data-model-discovery.php';

/**
 * Fake provider whose remote model list is controllable.
 */
class Gregius_Data_Fake_Discovery_Provider {

	/** @var array */
	public $models = array();

	/** @var WP_Error|null */
	public $error = null;

	/**
	 * @param string $api_key API key.
	 * @return array|WP_Error
	 */
	public function list_remote_models( string $api_key ): array|WP_Error {
		if ( null !== $this->error ) {
			return $this->error;
		}

		return $this->models;
	}
}

/**
 * Unit tests for live model discovery + lifecycle resolution.
 */
class Test_Gregius_Data_Model_Discovery extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gregius_data_test_stub_common_functions();
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param object $provider Fake provider.
	 * @return Gregius_Data_Model_Discovery
	 */
	private function discovery_with( $provider ): Gregius_Data_Model_Discovery {
		return new Gregius_Data_Model_Discovery(
			static function () use ( $provider ) {
				return $provider;
			}
		);
	}

	public function test_present_model_resolves_active() {
		$provider         = new Gregius_Data_Fake_Discovery_Provider();
		$provider->models = array( 'gemini-2.5-flash', 'gemini-2.5-pro' );

		$this->assertSame(
			'active',
			$this->discovery_with( $provider )->resolve_lifecycle( 'gemini', 'key', 'gemini-2.5-flash' )
		);
	}

	public function test_absent_model_of_covered_category_resolves_retired() {
		$provider         = new Gregius_Data_Fake_Discovery_Provider();
		$provider->models = array( 'gpt-4o-mini' ); // Catalog llm present -> category covered.

		$this->assertSame(
			'retired',
			$this->discovery_with( $provider )->resolve_lifecycle( 'openai', 'key', 'gpt-4o' )
		);
	}

	public function test_absent_model_of_uncovered_category_resolves_unknown() {
		$provider         = new Gregius_Data_Fake_Discovery_Provider();
		$provider->models = array( 'command-a-03-2025', 'embed-english-v3.0' ); // No catalog models.

		$this->assertSame(
			'unknown',
			$this->discovery_with( $provider )->resolve_lifecycle( 'cohere', 'key', 'rerank-v4.0-fast' )
		);
	}

	public function test_fetch_failure_resolves_unknown() {
		$provider        = new Gregius_Data_Fake_Discovery_Provider();
		$provider->error = new WP_Error( 'boom', 'network failure' );

		$this->assertSame(
			'unknown',
			$this->discovery_with( $provider )->resolve_lifecycle( 'gemini', 'key', 'gemini-2.5-flash' )
		);
	}

	public function test_uncatalogued_model_resolves_unknown() {
		$provider         = new Gregius_Data_Fake_Discovery_Provider();
		$provider->models = array( 'command-a-03-2025' );

		$this->assertSame(
			'unknown',
			$this->discovery_with( $provider )->resolve_lifecycle( 'cohere', 'key', 'some-unknown-model' )
		);
	}

	public function test_provider_without_remote_list_is_unsupported() {
		$discovery = $this->discovery_with( new stdClass() );
		$result    = $discovery->fetch_live_models( 'internal', 'key', true );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gregius_data_discovery_unsupported', $result->get_error_code() );
	}
}
