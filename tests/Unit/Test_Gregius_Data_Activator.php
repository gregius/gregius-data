<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

if ( ! defined( 'GREGIUS_DATA_VERSION' ) ) {
	define( 'GREGIUS_DATA_VERSION', '1.0.0' );
}

require_once __DIR__ . '/../../includes/class-gregius-data-activator.php';

class Test_Gregius_Data_Activator extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gregius_data_test_stub_common_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constant_version_is_defined() {
		$this->assertTrue( defined( 'GREGIUS_DATA_VERSION' ) );
		$this->assertSame( '1.0.0', GREGIUS_DATA_VERSION );
	}

	public function test_constant_version_option_is_correct() {
		$this->assertSame( 'gregius_data_db_version', Gregius_Data_Activator::VERSION_OPTION );
	}

	public function test_check_version_runs_upgrade_when_older_installed() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				if ( 'gregius_data_db_version' === $name ) {
					return '0.0.0';
				}
				return true;
			}
		);

		Functions\expect( 'update_option' )
			->once()
			->with( 'gregius_data_db_version', '1.0.0' );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_notices', \Mockery::type( 'Closure' ) );

		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'taxonomy_exists' )->justReturn( true );

		Gregius_Data_Activator::check_version();
		$this->assertTrue( true );
	}

	public function test_check_version_skips_upgrade_when_same_version() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				if ( 'gregius_data_db_version' === $name ) {
					return '1.0.0';
				}
				return true;
			}
		);

		Functions\expect( 'update_option' )->never();
		Functions\expect( 'add_action' )->never();

		Functions\when( 'taxonomy_exists' )->justReturn( false );

		Gregius_Data_Activator::check_version();
		$this->assertTrue( true );
	}

	public function test_init_default_sync_settings_sets_defaults() {
		$called = array();

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) use ( &$called ) {
				$called[] = $name;
				return $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$called ) {
				$called[] = $name;
			}
		);

		$reflection = new ReflectionMethod( Gregius_Data_Activator::class, 'init_default_sync_settings' );
		$reflection->setAccessible( true );
		$reflection->invoke( null );

		$this->assertContains( 'gregius_data_sync_enabled_post_types', $called );
		$this->assertContains( 'gregius_data_sync_enabled_statuses', $called );
		$this->assertContains( 'gregius_data_sync_real_time_sync', $called );
		$this->assertContains( 'gregius_data_sync_sync_meta', $called );
		$this->assertContains( 'gregius_data_sync_sync_terms', $called );
	}

	public function test_init_default_sync_settings_is_idempotent() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				if ( str_starts_with( $name, 'gregius_data_sync_' ) ) {
					return 'already_set';
				}
				return $default;
			}
		);

		Functions\expect( 'update_option' )->never();

		$reflection = new ReflectionMethod( Gregius_Data_Activator::class, 'init_default_sync_settings' );
		$reflection->setAccessible( true );
		$reflection->invoke( null );

		$this->assertTrue( true );
	}
}
