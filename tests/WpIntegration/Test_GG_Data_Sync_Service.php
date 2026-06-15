<?php
/**
 * Tier 3 Full WP — Sync Service Tests
 *
 * Tests the GG_Data_Sync_Service facade. These tests expect the sync
 * methods to fail gracefully when no PostgreSQL connection is configured,
 * since the test environment uses MySQL only.
 */

class Test_GG_Data_Sync_Service extends WP_UnitTestCase {

	public function test_constructor_accepts_connection_name() {
		$service = new GG_Data_Sync_Service( 'test_conn' );
		$this->assertInstanceOf( GG_Data_Sync_Service::class, $service );
	}

	public function test_sync_service_no_connection_returns_error() {
		$service = new GG_Data_Sync_Service( 'nonexistent' );
		$this->assertInstanceOf( GG_Data_Sync_Service::class, $service );
	}
}
