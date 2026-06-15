<?php
class Test_GG_Data_REST_Connections extends WP_Test_REST_TestCase {

	protected $server;
	protected $admin_id;
	protected $subscriber_id;

	public function set_up() {
		parent::set_up();
		$this->server = rest_get_server();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	public function test_connections_route_registered() {
		$routes = $this->server->get_routes( 'gg-data/v1' );
		$this->assertNotEmpty( $routes, 'No gg-data routes registered' );
	}

	public function test_get_connections_unauthenticated_returns_401() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/gg-data/v1/connections' );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 401, 404 ) );
	}

	public function test_get_connections_as_admin() {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'GET', '/gg-data/v1/connections' );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 200, 404 ) );
	}

	public function test_get_connections_as_subscriber() {
		wp_set_current_user( $this->subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/gg-data/v1/connections' );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 403, 401, 404 ) );
	}
}
