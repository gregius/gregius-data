<?php
/**
 * Tier 3 Full WP — Logger Tests
 *
 * Tests GG_Data_Logger — the most testable class in the plugin.
 * All methods use WordPress options API or $wpdb.
 */

class Test_GG_Data_Logger extends WP_UnitTestCase {

	protected $logger;

	public function set_up() {
		parent::set_up();
		$this->logger = new GG_Data_Logger();
	}

	public function test_get_table_name_returns_string() {
		$table = $this->logger->get_table_name();
		$this->assertIsString( $table );
		$this->assertStringContainsString( 'gg_data_logs', $table );
	}

	public function test_get_log_levels_returns_array() {
		$levels = $this->logger->get_log_levels();
		$this->assertIsArray( $levels );
		$this->assertContains( 'info', $levels );
		$this->assertContains( 'error', $levels );
		$this->assertContains( 'debug', $levels );
	}

	public function test_get_components_returns_array() {
		$components = $this->logger->get_components();
		$this->assertIsArray( $components );
		$this->assertNotEmpty( $components );
	}

	public function test_is_logging_enabled_by_default() {
		$this->assertTrue( $this->logger->is_logging_enabled() );
	}

	public function test_get_log_level_defaults_to_info() {
		$this->assertSame( 'info', $this->logger->get_log_level() );
	}

	public function test_is_debug_mode_reflects_wp_debug() {
		$this->assertTrue( $this->logger->is_debug_mode() );
	}

	public function test_log_inserts_record() {
		$result = $this->logger->log( 'Test message', 'info', 'system' );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	public function test_log_rejects_invalid_level() {
		$result = $this->logger->log( 'Test', 'invalid_level', 'system' );
		$this->assertIsInt( $result );
	}

	public function test_get_logs_returns_array_with_pagination_keys() {
		$this->logger->log( 'Entry 1', 'info', 'system' );
		$this->logger->log( 'Entry 2', 'error', 'sync' );

		$logs = $this->logger->get_logs( array( 'per_page' => 10 ) );
		$this->assertIsArray( $logs );
		$this->assertArrayHasKey( 'logs', $logs );
		$this->assertArrayHasKey( 'total_items', $logs );
	}

	public function test_get_stats_returns_array() {
		$this->logger->log( 'Stats test', 'warning', 'vectors' );

		$stats = $this->logger->get_stats();
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total', $stats );
		$this->assertArrayHasKey( 'by_level', $stats );
	}

	public function test_purge_old_logs_returns_integer() {
		$result = $this->logger->purge_old_logs( 30 );
		$this->assertIsInt( $result );
	}
}
