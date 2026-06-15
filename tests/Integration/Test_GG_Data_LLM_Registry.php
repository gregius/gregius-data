<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_LLM_Registry extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();
		require_once __DIR__ . '/../../includes/ai/class-gg-data-llm-registry.php';

		$reflection = new ReflectionClass( GG_Data_LLM_Registry::class );
		$prop       = $reflection->getProperty( 'providers' );
		$prop->setAccessible( true );
		$prop->setValue( [] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_providers_returns_array(): void {
		Functions\expect('apply_filters')
			->once()
			->with( 'gg_data_llm_providers', \Mockery::type('array') )
			->andReturnUsing(function ( $hook, $providers ) {
				return $providers;
			});

		$providers = GG_Data_LLM_Registry::get_providers();
		$this->assertIsArray( $providers );
	}

	public function test_get_providers_includes_default_openai(): void {
		Functions\expect('apply_filters')
			->once()
			->with( 'gg_data_llm_providers', \Mockery::type('array') )
			->andReturnUsing(function ( $hook, $providers ) {
				$providers['openai'] = [
					'name'  => 'OpenAI',
					'models' => [ 'gpt-4', 'gpt-3.5-turbo' ],
				];
				return $providers;
			});

		$providers = GG_Data_LLM_Registry::get_providers();
		$this->assertArrayHasKey( 'openai', $providers );
		$this->assertSame( 'OpenAI', $providers['openai']['name'] );
	}

	public function test_get_provider_returns_single_provider(): void {
		$expected = [
			'name'  => 'Anthropic',
			'models' => [ 'claude-3-opus', 'claude-3-sonnet' ],
		];

		Functions\expect('apply_filters')
			->once()
			->with( 'gg_data_llm_providers', \Mockery::type('array') )
			->andReturnUsing(function ( $hook, $providers ) use ( $expected ) {
				$providers['anthropic'] = $expected;
				return $providers;
			});

		$result = GG_Data_LLM_Registry::get_provider( 'anthropic' );
		$this->assertIsArray( $result );
		$this->assertSame( $expected, $result );
	}

	public function test_get_provider_returns_error_for_unknown(): void {
		Functions\expect('apply_filters')
			->once()
			->with( 'gg_data_llm_providers', \Mockery::type('array') )
			->andReturnUsing(function ( $hook, $providers ) {
				return $providers;
			});

		$result = GG_Data_LLM_Registry::get_provider( 'nonexistent' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_filter_modifies_providers(): void {
		Functions\expect('apply_filters')
			->once()
			->with( 'gg_data_llm_providers', \Mockery::type('array') )
			->andReturnUsing(function ( $hook, $providers ) {
				$providers['custom'] = [
					'name'  => 'Custom Provider',
					'models' => [ 'custom-model' ],
				];
				return $providers;
			});

		$providers = GG_Data_LLM_Registry::get_providers();
		$this->assertArrayHasKey( 'custom', $providers );
	}
}
