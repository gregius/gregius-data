<?php

class Test_Gregius_Data_REST_Sync extends WP_Test_REST_TestCase {

	protected $server;
	protected $admin_id;

	public function set_up() {
		parent::set_up();
		$this->server   = rest_get_server();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	public function test_sync_routes_exist() {
		$routes = $this->server->get_routes( 'gregius-data/v1' );
		$this->assertNotEmpty( $routes, 'gregius-data routes should exist' );
	}

	public function test_sync_status_as_admin() {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'GET', '/gregius-data/v1/sync/status' );
		$response = $this->server->dispatch( $request );
		$this->assertIsInt( $response->get_status() );
	}
}
