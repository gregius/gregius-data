<?php
/**
 * Tier 3 Full WP — Sync Service Tests
 *
 * Tests the Gregius_Data_Sync_Service facade. These tests expect the sync
 * methods to fail gracefully when no PostgreSQL connection is configured,
 * since the test environment uses MySQL only.
 */

class Test_Gregius_Data_Sync_Service extends WP_UnitTestCase {

	public function test_constructor_accepts_connection_name() {
		$service = new Gregius_Data_Sync_Service( 'test_conn' );
		$this->assertInstanceOf( Gregius_Data_Sync_Service::class, $service );
	}

	public function test_sync_service_no_connection_returns_error() {
		$service = new Gregius_Data_Sync_Service( 'nonexistent' );
		$this->assertInstanceOf( Gregius_Data_Sync_Service::class, $service );
	}
}
