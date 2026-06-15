<?php
/**
 * Tier 3 Full WP — Connection Health Tests
 *
 * Tests GG_Data_Connection_Health which tracks connection uptime
 * via WordPress options.
 */

class Test_GG_Data_Connection_Health extends WP_UnitTestCase {

	protected $health;

	public function set_up() {
		parent::set_up();
		$this->health = new GG_Data_Connection_Health();
	}

	public function test_get_health_status_returns_array_with_all_keys() {
		$status = $this->health->get_health_status( 'test_conn' );

		$this->assertIsArray( $status );
		$expected_keys = array(
			'status',
			'last_check',
			'last_success',
			'last_failure',
			'last_error',
			'consecutive_failures',
			'total_checks',
			'total_failures',
			'uptime_percentage',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $status, "Missing expected key: {$key}" );
		}
	}

	public function test_record_success_updates_status() {
		$this->health->record_success( 'test_conn' );

		$status = $this->health->get_health_status( 'test_conn' );
		$this->assertSame( 'healthy', $status['status'] );
		$this->assertSame( 0, $status['consecutive_failures'] );
	}

	public function test_record_failure_increments_fault_count() {
		$this->health->record_failure( 'Connection failed', 'test_conn' );

		$status = $this->health->get_health_status( 'test_conn' );
		$this->assertSame( 'unhealthy', $status['status'] );
		$this->assertGreaterThanOrEqual( 1, $status['consecutive_failures'] );
	}

	public function test_multiple_failures_detected() {
		$this->health->record_failure( 'Error 1', 'test_conn' );
		$this->health->record_failure( 'Error 2', 'test_conn' );
		$this->health->record_failure( 'Error 3', 'test_conn' );

		$status = $this->health->get_health_status( 'test_conn' );
		$this->assertSame( 'unhealthy', $status['status'] );
		$this->assertGreaterThanOrEqual( 3, $status['consecutive_failures'] );
	}

	public function test_recovery_after_failure() {
		$this->health->record_failure( 'Error', 'test_conn' );
		$this->health->record_success( 'test_conn' );

		$status = $this->health->get_health_status( 'test_conn' );
		$this->assertSame( 'healthy', $status['status'] );
	}

	public function test_reset_health_status_clears_data() {
		$this->health->record_failure( 'Error', 'test_conn' );
		$this->health->reset_health_status( 'test_conn' );

		$status = $this->health->get_health_status( 'test_conn' );
		$this->assertSame( 'unknown', $status['status'] );
		$this->assertSame( 0, $status['consecutive_failures'] );
	}

	public function test_get_health_status_no_connection_defaults() {
		$status = $this->health->get_health_status( 'nonexistent' );

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'status', $status );
		$this->assertArrayHasKey( 'consecutive_failures', $status );
	}
}
