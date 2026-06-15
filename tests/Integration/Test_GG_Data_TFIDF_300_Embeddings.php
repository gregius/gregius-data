<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_TFIDF_300_Embeddings extends PHPUnit\Framework\TestCase {

	private $reflection;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();
		require_once __DIR__ . '/../../includes/vectors/class-gg-data-tfidf-300-embeddings.php';
		$this->reflection = new ReflectionClass( GG_Data_TFIDF_300_Embeddings::class );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_instance(): GG_Data_TFIDF_300_Embeddings {
		return $this->reflection->newInstanceWithoutConstructor();
	}

	public function test_build_global_vocabulary_returns_array(): void {
		$instance   = $this->make_instance();
		$connection = [ 'name' => 'test' ];
		$posts      = [
			[
				'ID'           => 1,
				'post_title'   => 'Hello World',
				'post_content' => 'This is some content about WordPress.',
				'post_excerpt' => 'A short excerpt',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			],
		];

		$vocab = $instance->build_global_vocabulary( $connection, $posts );
		$this->assertIsArray( $vocab );
	}

	public function test_build_global_vocabulary_contains_terms(): void {
		$instance   = $this->make_instance();
		$connection = [ 'name' => 'test' ];
		$posts      = [
			[
				'ID'           => 1,
				'post_title'   => 'WordPress plugins',
				'post_content' => 'WordPress is a great platform for plugins.',
				'post_excerpt' => '',
				'post_type'   => 'post',
				'post_status' => 'publish',
			],
		];

		$vocab = $instance->build_global_vocabulary( $connection, $posts );
		$this->assertNotEmpty( $vocab );
	}

	public function test_build_global_vocabulary_with_empty_posts(): void {
		$instance   = $this->make_instance();
		$connection = [ 'name' => 'test' ];

		$vocab = $instance->build_global_vocabulary( $connection, [] );
		$this->assertIsArray( $vocab );
		$this->assertEmpty( $vocab );
	}

	public function test_vocabulary_terms_are_lowercase(): void {
		$instance   = $this->make_instance();
		$connection = [ 'name' => 'test' ];
		$posts      = [
			[
				'ID'           => 1,
				'post_title'   => 'UPPERCASE Title',
				'post_content' => 'Some CONTENT here.',
				'post_excerpt' => '',
				'post_type'   => 'post',
				'post_status' => 'publish',
			],
		];

		$vocab = $instance->build_global_vocabulary( $connection, $posts );
		foreach ( array_keys( $vocab ) as $term ) {
			$this->assertSame( strtolower( $term ), $term, "Term '$term' should be lowercase" );
		}
	}
}
