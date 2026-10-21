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

	public function test_activation_produces_no_output() {
		ob_start();
		Gregius_Data_Activator::activate();
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'Activation must not print anything.' );
	}

	public function test_activation_creates_required_tables() {
		Gregius_Data_Activator::activate();

		global $wpdb;
		foreach ( array( 'gregius_data_settings', 'gregius_data_sync_metadata', 'gregius_data_logs' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$this->assertSame(
				$table,
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
				"Expected table {$table} to exist."
			);
		}
	}

	public function test_activation_leaves_no_db_error() {
		global $wpdb;
		Gregius_Data_Activator::activate();

		$this->assertEmpty( $wpdb->last_error, 'Activation must not trigger a database error.' );
	}

	public function test_activation_is_idempotent() {
		Gregius_Data_Activator::activate();
		Gregius_Data_Activator::activate();

		global $wpdb;
		$table = $wpdb->prefix . 'gregius_data_settings';
		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'Reactivating must leave the settings table in place.'
		);
	}

	public function test_activation_produces_no_schema_churn() {
		Gregius_Data_Activator::activate();
		$before = $this->snapshot_schema();

		Gregius_Data_Activator::activate();
		$after = $this->snapshot_schema();

		$this->assertSame( $before, $after, 'A second activation must not alter the schema.' );
	}

	public function test_multisite_sync_metadata_tables_for_all_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite (WP_TESTS_MULTISITE).' );
		}

		$manager = new Gregius_Data_Schema_Manager();
		$results = $manager->create_sync_metadata_tables_for_all_sites();

		$this->assertNotEmpty( $results );

		global $wpdb;
		foreach ( $results as $entry ) {
			if ( empty( $entry['table_name'] ) ) {
				continue;
			}
			$this->assertSame(
				$entry['table_name'],
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $entry['table_name'] ) ),
				"Expected per-blog sync metadata table {$entry['table_name']} to exist."
			);
		}
	}

	private function snapshot_schema() {
		global $wpdb;

		$snapshot = array();
		foreach ( array( 'gregius_data_settings', 'gregius_data_sync_metadata', 'gregius_data_logs' ) as $suffix ) {
			$table   = $wpdb->prefix . $suffix;
			$create  = $wpdb->get_var( "SHOW CREATE TABLE {$table}", 1 );
			$create  = preg_replace( '/AUTO_INCREMENT=\d+\s*/', '', (string) $create );
			$snapshot[ $suffix ] = $create;
		}

		return $snapshot;
	}
}
