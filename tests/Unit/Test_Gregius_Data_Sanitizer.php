<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/class-gregius-data-sanitizer.php';

class Test_Gregius_Data_Sanitizer extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mirror WordPress behavior closely enough to observe sanitization.
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
		Monkey\Functions\when( 'wp_kses_post' )->alias( 'strval' );
		Monkey\Functions\when( 'absint' )->alias(
			function ( $maybeint ) {
				return abs( (int) $maybeint );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_recursively_sanitizes_string_keys() {
		$input  = array( 'Foo' => array( 'Bar Baz' => 'x' ) );
		$result = Gregius_Data_Sanitizer::sanitize( $input );
		$this->assertSame( array( 'foo' => array( 'barbaz' => 'x' ) ), $result );
	}

	public function test_preserves_int_keys() {
		$input  = array( 3 => 'a', 'B' => 'c' );
		$result = Gregius_Data_Sanitizer::sanitize( $input );
		$this->assertSame( array( 3 => 'a', 'b' => 'c' ), $result );
	}

	public function test_strips_malicious_key() {
		$input  = array( '<script>' => 'x' );
		$result = Gregius_Data_Sanitizer::sanitize( $input );
		$this->assertSame( array( 'script' => 'x' ), $result );
	}

	public function test_sanitizes_string_values() {
		$this->assertSame( 'hello', Gregius_Data_Sanitizer::sanitize( '  hello  ' ) );
	}

	public function test_absints_int_values() {
		$this->assertSame( 3, Gregius_Data_Sanitizer::sanitize( -3 ) );
	}

	public function test_bool_null_float_pass_through() {
		$this->assertSame( true, Gregius_Data_Sanitizer::sanitize( true ) );
		$this->assertSame( null, Gregius_Data_Sanitizer::sanitize( null ) );
		$this->assertSame( 1.5, Gregius_Data_Sanitizer::sanitize( 1.5 ) );
	}

	public function test_sanitizes_list_payloads() {
		$input  = array( array( 'A' => 'x' ), array( 'B' => 'y' ) );
		$result = Gregius_Data_Sanitizer::sanitize( $input );
		$this->assertSame( array( array( 'a' => 'x' ), array( 'b' => 'y' ) ), $result );
	}

	public function test_depth_cap_truncates_deep_nesting() {
		$deep = 'leaf';
		for ( $i = 0; $i < 100; $i++ ) {
			$deep = array( 'k' => $deep );
		}

		$result = Gregius_Data_Sanitizer::sanitize( $deep );

		$cursor = $result;
		for ( $i = 0; $i < Gregius_Data_Sanitizer::MAX_DEPTH; $i++ ) {
			$cursor = $cursor['k'];
		}

		$this->assertSame( array(), $cursor['k'] );
	}

	public function test_strips_tag_block_unicode() {
		$this->assertSame( 'hello', Gregius_Data_Sanitizer::strip_invisible_unicode( "hello\xF3\xA0\x80\x80" ) );
	}

	public function test_strips_bidi_override_unicode() {
		$this->assertSame( 'hi', Gregius_Data_Sanitizer::strip_invisible_unicode( "hi\xE2\x80\xAE" ) );
	}

	public function test_preserves_single_zwj_and_variation_selector() {
		$emoji = "\xF0\x9F\x91\xA8\xE2\x80\x8D\xF0\x9F\x91\xA9"; // 👨‍👩 (single ZWJ).
		$this->assertSame( $emoji, Gregius_Data_Sanitizer::strip_invisible_unicode( $emoji ) );
	}

	public function test_strips_variation_selector_run() {
		$vs = "\xEF\xB8\x8F\xEF\xB8\x8F"; // Two consecutive U+FE0F.
		$this->assertSame( 'a', Gregius_Data_Sanitizer::strip_invisible_unicode( 'a' . $vs ) );
	}

	public function test_strips_zero_width_run() {
		$zw = "\xE2\x80\x8D\xE2\x80\x8D\xE2\x80\x8D\xE2\x80\x8D"; // Four consecutive U+200D.
		$this->assertSame( 'a', Gregius_Data_Sanitizer::strip_invisible_unicode( 'a' . $zw ) );
	}

	public function test_drops_keys_that_sanitize_to_empty() {
		$input  = array( '!!!' => 'x', 'ok' => 'y' );
		$result = Gregius_Data_Sanitizer::sanitize( $input );
		$this->assertSame( array( 'ok' => 'y' ), $result );
	}

	public function test_sanitize_text_field_strips_tags_in_values() {
		$this->assertSame( 'hi', Gregius_Data_Sanitizer::sanitize( '<b>hi</b>' ) );
	}

	public function test_decode_json_non_string_returns_default() {
		$this->assertSame( array(), Gregius_Data_Sanitizer::decode_json( array( 'x' ), 1024 ) );
	}

	public function test_decode_json_oversized_returns_default() {
		$result = Gregius_Data_Sanitizer::decode_json( str_repeat( 'a', 200 ), 100, array( 'fallback' => true ) );
		$this->assertSame( array( 'fallback' => true ), $result );
	}

	public function test_decode_json_valid() {
		$this->assertSame( array( 'a' => 1 ), Gregius_Data_Sanitizer::decode_json( '{"a":1}', 1024 ) );
	}

	public function test_decode_json_invalid_returns_default() {
		$this->assertSame( array(), Gregius_Data_Sanitizer::decode_json( 'not json', 1024 ) );
	}

	public function test_sanitize_messages_allowlists_roles() {
		$messages = array(
			array( 'role' => 'user', 'content' => 'hi' ),
			array( 'role' => 'system', 'content' => 'evil' ),
			array( 'role' => 'assistant', 'content' => 'yo' ),
		);
		$result   = Gregius_Data_Sanitizer::sanitize_messages( $messages );
		$this->assertSame(
			array(
				array( 'role' => 'user', 'content' => 'hi' ),
				array( 'role' => 'assistant', 'content' => 'yo' ),
			),
			$result
		);
	}

	public function test_sanitize_messages_reindexes() {
		$messages = array(
			0 => array( 'role' => 'user', 'content' => 'a' ),
			1 => array( 'role' => 'hacker', 'content' => 'x' ),
			2 => array( 'role' => 'assistant', 'content' => 'b' ),
		);
		$result   = Gregius_Data_Sanitizer::sanitize_messages( $messages );
		$this->assertSame( array( 0, 1 ), array_keys( $result ) );
	}

	public function test_sanitize_metadata_filter_allowlists_operator() {
		$filter = array( 'filter_operator' => 'OR', 'post_type' => 'page' );
		$result = Gregius_Data_Sanitizer::sanitize_metadata_filter( $filter );
		$this->assertSame( 'OR', $result['filter_operator'] );
	}

	public function test_sanitize_metadata_filter_invalid_operator_defaults_and() {
		$filter = array( 'filter_operator' => 'evil; DROP', 'post_type' => 'page' );
		$result = Gregius_Data_Sanitizer::sanitize_metadata_filter( $filter );
		$this->assertSame( 'AND', $result['filter_operator'] );
	}

	public function test_sanitize_metadata_filter_absent_operator_untouched() {
		$filter = array( 'post_type' => 'page' );
		$result = Gregius_Data_Sanitizer::sanitize_metadata_filter( $filter );
		$this->assertArrayNotHasKey( 'filter_operator', $result );
	}
}
