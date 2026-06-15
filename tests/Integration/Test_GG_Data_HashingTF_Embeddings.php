<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_HashingTF_Embeddings extends PHPUnit\Framework\TestCase {

	private $reflection;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();
		require_once __DIR__ . '/../../includes/vectors/class-gg-data-hashingtf-embeddings.php';
		$this->reflection = new ReflectionClass( GG_Data_HashingTF_Embeddings::class );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_instance(): GG_Data_HashingTF_Embeddings {
		return $this->reflection->newInstanceWithoutConstructor();
	}

	public function test_generate_hashingtf_vector_returns_array(): void {
		$instance = $this->make_instance();
		$vector   = $instance->generate_hashingtf_vector( 'hello world', 'post_title' );
		$this->assertIsArray( $vector );
	}

	public function test_same_text_produces_same_vector(): void {
		$instance = $this->make_instance();
		$text     = 'WordPress is a content management system';
		$v1       = $instance->generate_hashingtf_vector( $text, 'post_title' );
		$v2       = $instance->generate_hashingtf_vector( $text, 'post_title' );
		$this->assertSame( $v1, $v2 );
	}

	public function test_different_text_produces_different_vector(): void {
		$instance = $this->make_instance();
		$v1       = $instance->generate_hashingtf_vector( 'hello world', 'post_title' );
		$v2       = $instance->generate_hashingtf_vector( 'goodbye world', 'post_title' );
		$this->assertNotSame( $v1, $v2 );
	}

	public function test_empty_text_returns_zero_vector(): void {
		$instance = $this->make_instance();
		$vector   = $instance->generate_hashingtf_vector( '', 'post_title' );
		$this->assertIsArray( $vector );
		foreach ( $vector as $value ) {
			$this->assertSame( 0.0, $value );
		}
	}

	public function test_generate_query_vector_literal_returns_string(): void {
		$instance = $this->make_instance();
		$literal  = $instance->generate_query_vector_literal( 'search query' );
		$this->assertIsString( $literal );
		$this->assertStringStartsWith( '[', $literal );
		$this->assertStringEndsWith( ']', $literal );
	}

	public function test_vector_dimension_is_1024(): void {
		$instance = $this->make_instance();
		$vector   = $instance->generate_hashingtf_vector( 'test content for dimension check', 'post_title' );
		$this->assertCount( 1024, $vector );
	}

	public function test_vector_values_are_float(): void {
		$instance = $this->make_instance();
		$vector   = $instance->generate_hashingtf_vector( 'numeric check', 'post_content' );
		foreach ( $vector as $value ) {
			$this->assertIsFloat( $value );
		}
	}

	public function test_vector_values_are_normalized(): void {
		$instance  = $this->make_instance();
		$vector    = $instance->generate_hashingtf_vector( 'check normalization of vector output', 'post_title' );
		$magnitude = sqrt( array_sum( array_map( function ( $v ) {
			return $v * $v;
		}, $vector ) ) );
		$this->assertGreaterThan( 0.99, $magnitude );
		$this->assertLessThan( 1.01, $magnitude );
	}
}
