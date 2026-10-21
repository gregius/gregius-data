<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/class-gregius-data-sanitizer.php';
require_once __DIR__ . '/../../includes/rag/class-gregius-data-manifest-validator.php';

class Test_Gregius_Data_Manifest_Validator extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				$key = strtolower( $key );
				return preg_replace( '/[^a-z0-9_\-]/', '', $key );
			}
		);
		Monkey\Functions\when( 'sanitize_text_field' )->alias(
			function ( $str ) {
				if ( ! is_string( $str ) ) {
					return $str;
				}
				return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $str ) ) );
			}
		);
		Monkey\Functions\when( 'absint' )->alias(
			function ( $maybeint ) {
				return abs( (int) $maybeint );
			}
		);
		Monkey\Functions\when( 'wp_strip_all_tags' )->alias( 'strval' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_normalize_sanitizes_entity_pass_throughs() {
		$raw = array(
			'manifest_version' => '1.0.0',
			'entity'           => array(
				'id'          => 5,
				'type'        => 'post',
				'taxonomies'  => array(
					'Category Name' => array(
						array( 'term_id' => 1, 'term_slug' => 'foo' ),
					),
				),
				'meta'        => array( 'Custom Meta' => 'value' ),
				'raw_context' => array( 'Raw Key' => 'raw' ),
			),
		);

		$result = Gregius_Data_Manifest_Validator::normalize( $raw );

		$this->assertArrayHasKey( 'categoryname', $result['entity']['taxonomies'] );
		$this->assertArrayNotHasKey( 'Category Name', $result['entity']['taxonomies'] );
		$this->assertArrayHasKey( 'custommeta', $result['entity']['meta'] );
		$this->assertArrayHasKey( 'rawkey', $result['entity']['raw_context'] );
	}

	public function test_normalize_sanitizes_title_raw() {
		$raw = array(
			'manifest_version' => '1.0.0',
			'entity'           => array(
				'title' => array( 'raw' => '  <b>Injected</b> Title  ' ),
			),
		);

		$result = Gregius_Data_Manifest_Validator::normalize( $raw );

		$this->assertSame( 'Injected Title', $result['entity']['title']['raw'] );
	}
}
