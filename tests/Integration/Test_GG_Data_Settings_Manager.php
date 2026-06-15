<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_Settings_Manager extends PHPUnit\Framework\TestCase {

	private $reflection;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();

		require_once __DIR__ . '/../../includes/class-gg-data-logger.php';
		require_once __DIR__ . '/../../includes/class-gg-data-settings-manager.php';
		$this->reflection = new ReflectionClass( GG_Data_Settings_Manager::class );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_instance(): GG_Data_Settings_Manager {
		return $this->reflection->newInstanceWithoutConstructor();
	}

	public function test_get_data_type_boolean_true(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'boolean', $method->invoke( $instance, true ) );
		$this->assertSame( 'boolean', $method->invoke( $instance, false ) );
	}

	public function test_get_data_type_integer(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'integer', $method->invoke( $instance, 42 ) );
		$this->assertSame( 'integer', $method->invoke( $instance, 0 ) );
		$this->assertSame( 'integer', $method->invoke( $instance, -1 ) );
	}

	public function test_get_data_type_float(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'float', $method->invoke( $instance, 3.14 ) );
	}

	public function test_get_data_type_string(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'string', $method->invoke( $instance, 'hello' ) );
		$this->assertSame( 'string', $method->invoke( $instance, '' ) );
	}

	public function test_get_data_type_serialized_array(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'serialized', $method->invoke( $instance, [ 1, 2, 3 ] ) );
		$this->assertSame( 'serialized', $method->invoke( $instance, [] ) );
	}

	public function test_get_data_type_null_is_string(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'string', $method->invoke( $instance, null ) );
	}

	public function test_get_data_type_serialized_object(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'get_data_type' );
		$method->setAccessible( true );

		$this->assertSame( 'serialized', $method->invoke( $instance, new stdClass() ) );
	}

	public function test_mask_connection_config_for_log(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'mask_connection_config_for_log' );
		$method->setAccessible( true );

		$config = [
			'host'     => 'localhost',
			'password' => 'super-secret',
			'api_key'  => 'sk-xxxx',
			'database' => 'test_db',
		];

		$masked = $method->invoke( $instance, $config );

		$this->assertSame( 'localhost', $masked['host'] );
		$this->assertSame( '***', $masked['password'] );
		$this->assertSame( '***', $masked['api_key'] );
		$this->assertSame( 'test_db', $masked['database'] );
	}

	public function test_mask_connection_config_no_sensitive_keys(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'mask_connection_config_for_log' );
		$method->setAccessible( true );

		$config = [
			'host'     => 'localhost',
			'database' => 'test_db',
		];

		$masked = $method->invoke( $instance, $config );
		$this->assertSame( $config, $masked );
	}

	public function test_normalize_postgrest_keys_with_type_postgrest(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'normalize_postgrest_keys' );
		$method->setAccessible( true );

		$settings = [
			'type'       => 'postgrest',
			'pg_host'    => 'localhost',
			'pg_port'    => '5432',
			'regular'    => 'value',
		];

		$result = $method->invoke( $instance, $settings );

		$this->assertArrayHasKey( 'type', $result );
		$this->assertSame( 'postgrest', $result['type'] );
	}

	public function test_normalize_postgrest_keys_non_postgrest_unchanged(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'normalize_postgrest_keys' );
		$method->setAccessible( true );

		$settings = [
			'type'    => 'mysql',
			'host'    => 'localhost',
			'port'    => '3306',
		];

		$result = $method->invoke( $instance, $settings );
		$this->assertSame( $settings, $result );
	}

	public function test_normalize_postgrest_keys_no_type_unchanged(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'normalize_postgrest_keys' );
		$method->setAccessible( true );

		$settings = [ 'host' => 'localhost', 'port' => '5432' ];
		$result   = $method->invoke( $instance, $settings );
		$this->assertSame( $settings, $result );
	}

	public function test_normalize_postgrest_keys_empty(): void {
		$instance = $this->make_instance();
		$method   = $this->reflection->getMethod( 'normalize_postgrest_keys' );
		$method->setAccessible( true );

		$this->assertSame( [], $method->invoke( $instance, [] ) );
	}
}
