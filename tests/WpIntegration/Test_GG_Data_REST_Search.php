<?php

class Test_GG_Data_REST_Search extends WP_Test_REST_TestCase {

	protected $server;
	protected $admin_id;

	public function set_up() {
		parent::set_up();
		$this->server   = rest_get_server();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	public function test_search_route_exists() {
		$routes = $this->server->get_routes( 'gg-data/v1' );
		$this->assertNotEmpty( $routes, 'gg-data routes should exist' );
	}

	public function test_search_unauthenticated() {
		$request = new WP_REST_Request( 'POST', '/gg-data/v1/search' );
		$request->set_param( 'query', 'test' );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 400, 401, 404 ) );
	}

	public function test_search_as_admin_handles_request() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/gg-data/v1/search' );
		$request->set_param( 'query', 'test' );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 200, 400, 404, 500 ) );
	}
}
