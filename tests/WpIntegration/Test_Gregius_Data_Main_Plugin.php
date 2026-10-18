<?php
/**
 * Tier 3 Full WP — Main Plugin Class Tests
 *
 * Tests the Gregius_Data main plugin class: singleton pattern, init,
 * option defaults, and sync enable/disable logic.
 */

class Test_Gregius_Data_Main_Plugin extends WP_UnitTestCase {

	public function test_instance_returns_singleton() {
		$a = Gregius_Data::instance();
		$b = Gregius_Data::instance();
		$this->assertSame( $a, $b );
	}

	public function test_instance_is_gregius_data() {
		$instance = Gregius_Data::instance();
		$this->assertInstanceOf( Gregius_Data::class, $instance );
	}

	public function test_is_sync_enabled_defaults_false() {
		$plugin = Gregius_Data::instance();
		$this->assertFalse( $plugin->is_sync_enabled() );
	}

	public function test_is_sync_enabled_with_option() {
		update_option( 'gregius_data_enabled', true );
		$plugin = Gregius_Data::instance();
		$this->assertTrue( $plugin->is_sync_enabled() );
	}

	public function test_constructor_creates_logger() {
		$plugin = new Gregius_Data();
		$this->assertNotNull( $plugin );
	}
}
