<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_DB extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();

		require_once __DIR__ . '/../../includes/class-gg-data-logger.php';
		require_once __DIR__ . '/../../includes/class-gg-data-settings-manager.php';
		require_once __DIR__ . '/../../includes/class-gg-data-db.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_is_postgrest_connection_returns_true_with_postgrest_config(): void {
		$reflection = new ReflectionClass( GG_Data_DB::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		$settings_mgr = Mockery::mock( GG_Data_Settings_Manager::class );
		$settings_mgr->shouldReceive( 'get_connection' )
			->with( 'test_conn' )
			->andReturn( [
				'type'   => 'postgrest',
				'host'   => 'localhost',
				'schema' => 'public',
			] );

		$prop = $reflection->getProperty( 'settings_manager' );
		$prop->setAccessible( true );
		$prop->setValue( $instance, $settings_mgr );

		$this->assertTrue( $instance->is_postgrest_connection( 'test_conn' ) );
	}

	public function test_is_postgrest_connection_returns_false_with_mysql_config(): void {
		$reflection = new ReflectionClass( GG_Data_DB::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		$settings_mgr = Mockery::mock( GG_Data_Settings_Manager::class );
		$settings_mgr->shouldReceive( 'get_connection' )
			->with( 'mysql_conn' )
			->andReturn( [
				'type' => 'mysql',
				'host' => 'localhost',
			] );

		$prop = $reflection->getProperty( 'settings_manager' );
		$prop->setAccessible( true );
		$prop->setValue( $instance, $settings_mgr );

		$this->assertFalse( $instance->is_postgrest_connection( 'mysql_conn' ) );
	}

	public function test_is_postgrest_connection_returns_false_with_nonexistent_connection(): void {
		$reflection = new ReflectionClass( GG_Data_DB::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		$settings_mgr = Mockery::mock( GG_Data_Settings_Manager::class );
		$settings_mgr->shouldReceive( 'get_connection' )
			->with( 'missing' )
			->andReturn( [] );

		$prop = $reflection->getProperty( 'settings_manager' );
		$prop->setAccessible( true );
		$prop->setValue( $instance, $settings_mgr );

		$this->assertFalse( $instance->is_postgrest_connection( 'missing' ) );
	}

	public function test_is_postgrest_connection_returns_false_with_variants(): void {
		$variants = [ 'pgrst', 'supabase', '' ];

		foreach ( $variants as $type ) {
			$reflection = new ReflectionClass( GG_Data_DB::class );
			$instance   = $reflection->newInstanceWithoutConstructor();

			$settings_mgr = Mockery::mock( GG_Data_Settings_Manager::class );
			$settings_mgr->shouldReceive( 'get_connection' )
				->with( 'conn_' . $type )
				->andReturn( [ 'type' => $type ] );

			$prop = $reflection->getProperty( 'settings_manager' );
			$prop->setAccessible( true );
			$prop->setValue( $instance, $settings_mgr );

			$this->assertFalse( $instance->is_postgrest_connection( 'conn_' . $type ) );
		}
	}
}
