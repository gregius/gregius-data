<?php
/**
 * Tier 3 Full WP — Plugin Activation Tests
 */

class Test_Gregius_Data_Plugin_Activation extends WP_UnitTestCase {

	protected $plugin_file;

	public function set_up() {
		parent::set_up();
		$this->plugin_file = GREGIUS_DATA_PLUGIN_DIR . 'gregius-data.php';
	}

	public function test_plugin_file_defines_constants() {
		$this->assertTrue( defined( 'GREGIUS_DATA_VERSION' ) );
		$this->assertTrue( defined( 'GREGIUS_DATA_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'GREGIUS_DATA_PLUGIN_URL' ) );
		$this->assertIsString( GREGIUS_DATA_VERSION );
	}

	public function test_activation_sets_version_option() {
		Gregius_Data_Activator::activate();
		$version = get_option( 'gregius_data_db_version' );
		$this->assertNotEmpty( $version );
	}

	public function test_activation_hooks_are_callable() {
		$this->assertTrue( class_exists( 'Gregius_Data_Activator' ) );
		$this->assertTrue( class_exists( 'Gregius_Data' ) );
	}

	public function test_deactivation_clears_cron() {
		wp_schedule_event( time(), 'daily', 'gregius_data_retry_failed' );
		Gregius_Data_Deactivator::deactivate();
		$this->assertFalse( wp_next_scheduled( 'gregius_data_retry_failed' ) );
	}

	public function test_check_version_skips_when_current() {
		update_option( 'gregius_data_db_version', GREGIUS_DATA_VERSION );
		update_option( 'gregius_data_upgrading', false );

		Gregius_Data_Activator::check_version();
		$this->assertFalse( get_option( 'gregius_data_upgrading' ) );
	}
}
