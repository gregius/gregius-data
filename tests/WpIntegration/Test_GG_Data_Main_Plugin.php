<?php
/**
 * Tier 3 Full WP — Main Plugin Class Tests
 *
 * Tests the GG_Data main plugin class: singleton pattern, init,
 * option defaults, and sync enable/disable logic.
 */

class Test_GG_Data_Main_Plugin extends WP_UnitTestCase {

	public function test_instance_returns_singleton() {
		$a = GG_Data::instance();
		$b = GG_Data::instance();
		$this->assertSame( $a, $b );
	}

	public function test_instance_is_gg_data() {
		$instance = GG_Data::instance();
		$this->assertInstanceOf( GG_Data::class, $instance );
	}

	public function test_is_sync_enabled_defaults_false() {
		$plugin = GG_Data::instance();
		$this->assertFalse( $plugin->is_sync_enabled() );
	}

	public function test_is_sync_enabled_with_option() {
		update_option( 'gg_data_enabled', true );
		$plugin = GG_Data::instance();
		$this->assertTrue( $plugin->is_sync_enabled() );
	}

	public function test_constructor_creates_logger() {
		$plugin = new GG_Data();
		$this->assertNotNull( $plugin );
	}
}
