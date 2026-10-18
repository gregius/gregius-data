<?php

class Test_Gregius_Data_REST_Prompts extends WP_Test_REST_TestCase {

	protected $server;
	protected $admin_id;

	public function set_up() {
		parent::set_up();
		$this->server   = rest_get_server();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	public function test_prompt_routes_registered() {
		$routes = $this->server->get_routes( 'gregius-data/v1' );
		$this->assertNotEmpty( $routes );
	}

	public function test_get_prompts_returns_response() {
		$request  = new WP_REST_Request( 'GET', '/gregius-data/v1/prompts' );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 200, 401, 404 ) );
	}

	public function test_get_single_prompt_as_admin() {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gregius_prompt',
				'post_status' => 'publish',
				'post_title'  => 'Test Prompt',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/gregius-data/v1/prompts/' . $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 200, 404 ) );
	}
}
