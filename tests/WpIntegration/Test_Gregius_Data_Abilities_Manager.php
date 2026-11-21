<?php
/**
 * Tier 3 Full WP — Abilities Manager Tests
 *
 * Tests the Gregius_Data_Abilities_Manager class which registers
 * abilities via the WordPress Abilities API (WP 6.9+).
 */

class Test_Gregius_Data_Abilities_Manager extends WP_UnitTestCase {

	protected $manager;

	public function set_up() {
		parent::set_up();
		$this->manager = new Gregius_Data_Abilities_Manager();
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
		// The plugin registers abilities on wp_abilities_api_init during bootstrap.
		$this->assertTrue( function_exists( 'wp_has_ability' ) );
		$this->assertTrue( wp_has_ability( 'gregius-data/answer' ) );
	}

	public function test_categories_registered() {
		$this->assertTrue( function_exists( 'wp_has_ability_category' ) );
		$this->assertTrue( wp_has_ability_category( 'ai' ) );
	}
}
