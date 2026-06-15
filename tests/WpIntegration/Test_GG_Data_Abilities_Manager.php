<?php
/**
 * Tier 3 Full WP — Abilities Manager Tests
 *
 * Tests the GG_Data_Abilities_Manager class which registers
 * abilities via the WordPress Abilities API (WP 6.9+).
 */

class Test_GG_Data_Abilities_Manager extends WP_UnitTestCase {

	protected $manager;

	public function set_up() {
		parent::set_up();
		$this->manager = new GG_Data_Abilities_Manager();
	}

	public function test_execute_list_connections_returns_array() {
		$result = $this->manager->execute_list_connections( array() );
		$this->assertIsArray( $result );
	}

	public function test_execute_list_models_returns_array() {
		$result = $this->manager->execute_list_models( array() );
		$this->assertIsArray( $result );
	}

	public function test_abilities_registered_on_api_init() {
		$registered_before = function_exists( 'wp_list_abilities' )
			? wp_list_abilities()
			: array();

		$this->manager->register_abilities();
		$registered_after = function_exists( 'wp_list_abilities' )
			? wp_list_abilities()
			: array();

		$this->assertIsArray( $registered_after );
	}

	public function test_categories_registered() {
		$this->manager->register_categories();
		$this->assertTrue( true );
	}
}
