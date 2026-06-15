<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_Vector_Generator extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();
		require_once __DIR__ . '/../../includes/class-gg-data-logger.php';
		require_once __DIR__ . '/../../includes/class-gg-data-model-registry.php';
		require_once __DIR__ . '/../../includes/vectors/interface-gg-data-vector-strategy.php';
		require_once __DIR__ . '/../../includes/vectors/class-gg-data-vector-generator.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_instance(): GG_Data_Vector_Generator {
		$reflection = new ReflectionClass( GG_Data_Vector_Generator::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		$logger = Mockery::mock( GG_Data_Logger::class );
		$logger->shouldReceive( 'log' )->byDefault();
		$prop = $reflection->getProperty( 'logger' );
		$prop->setAccessible( true );
		$prop->setValue( $instance, $logger );

		return $instance;
	}

	public function test_register_and_get_strategies(): void {
		$instance         = $this->make_instance();
		$mock_strategy    = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$mock_strategy->shouldReceive( 'get_id' )->andReturn( 'mock_test' );
		$mock_strategy->shouldReceive( 'get_name' )->andReturn( 'Mock Test' );

		$instance->register_strategy( $mock_strategy );
		$strategies = $instance->get_strategies();

		$this->assertIsArray( $strategies );
		$this->assertArrayHasKey( 'mock_test', $strategies );
	}

	public function test_register_multiple_strategies(): void {
		$instance = $this->make_instance();

		$s1 = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s1->shouldReceive( 'get_id' )->andReturn( 'alpha' );
		$s1->shouldReceive( 'get_name' )->andReturn( 'Alpha' );
		$s2 = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s2->shouldReceive( 'get_id' )->andReturn( 'beta' );
		$s2->shouldReceive( 'get_name' )->andReturn( 'Beta' );

		$instance->register_strategy( $s1 );
		$instance->register_strategy( $s2 );

		$strategies = $instance->get_strategies();
		$this->assertCount( 2, $strategies );
	}

	public function test_get_strategy_by_id_returns_correct(): void {
		$instance = $this->make_instance();

		$s1 = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s1->shouldReceive( 'get_id' )->andReturn( 'alpha' );
		$s1->shouldReceive( 'get_name' )->andReturn( 'Alpha' );
		$s1->shouldReceive( 'some_method' )->andReturn( 'from_alpha' );
		$s2 = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s2->shouldReceive( 'get_id' )->andReturn( 'beta' );
		$s2->shouldReceive( 'get_name' )->andReturn( 'Beta' );

		$instance->register_strategy( $s1 );
		$instance->register_strategy( $s2 );

		$found = $instance->get_strategy_by_id( 'alpha' );
		$this->assertNotNull( $found );
		$this->assertSame( 'from_alpha', $found->some_method() );
	}

	public function test_get_strategy_by_id_unknown(): void {
		$instance = $this->make_instance();

		$s = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s->shouldReceive( 'get_id' )->andReturn( 'known' );
		$s->shouldReceive( 'get_name' )->andReturn( 'Known' );
		$instance->register_strategy( $s );

		$this->assertNull( $instance->get_strategy_by_id( 'unknown' ) );
	}

	public function test_overwrite_strategy(): void {
		$instance = $this->make_instance();

		$s1 = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s1->shouldReceive( 'get_id' )->andReturn( 'duplicate' );
		$s1->shouldReceive( 'get_name' )->andReturn( 'Dup V1' );
		$s1->shouldReceive( 'get_version' )->andReturn( 'v1' );

		$s2 = Mockery::mock( 'GG_Data_Vector_Strategy_Interface' );
		$s2->shouldReceive( 'get_id' )->andReturn( 'duplicate' );
		$s2->shouldReceive( 'get_name' )->andReturn( 'Dup V2' );
		$s2->shouldReceive( 'get_version' )->andReturn( 'v2' );

		$instance->register_strategy( $s1 );
		$instance->register_strategy( $s2 );

		$strategies = $instance->get_strategies();
		$this->assertCount( 1, $strategies );
		$this->assertSame( 'v2', $strategies['duplicate']->get_version() );
	}

	public function test_empty_strategies(): void {
		$instance   = $this->make_instance();
		$strategies = $instance->get_strategies();
		$this->assertIsArray( $strategies );
		$this->assertEmpty( $strategies );
	}
}
