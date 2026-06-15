<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_Content_Cleaner extends PHPUnit\Framework\TestCase {

	private $cleaner;
	private $reflection;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();
		require_once __DIR__ . '/../../includes/class-gg-data-content-cleaner.php';

		$this->reflection = new ReflectionClass( GG_Data_Content_Cleaner::class );
		$this->cleaner    = $this->reflection->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function invoke_private( string $method, array $args = [] ) {
		$rm = $this->reflection->getMethod( $method );
		$rm->setAccessible( true );
		return $rm->invokeArgs( $this->cleaner, $args );
	}

	public function test_count_words_empty(): void {
		$result = $this->invoke_private( 'count_words', [ '' ] );
		$this->assertSame( 0, $result );
	}

	public function test_count_words_simple(): void {
		$result = $this->invoke_private( 'count_words', [ 'hello world' ] );
		$this->assertSame( 2, $result );
	}

	public function test_count_words_multiple_spaces(): void {
		$result = $this->invoke_private( 'count_words', [ 'word1    word2   word3' ] );
		$this->assertSame( 3, $result );
	}

	public function test_count_words_with_html_counts_tags(): void {
		$result = $this->invoke_private( 'count_words', [ '<p>hello world</p>' ] );
		$this->assertSame( 4, $result );
	}

	public function test_calculate_reading_time_zero_returns_one(): void {
		$result = $this->invoke_private( 'calculate_reading_time', [ 0 ] );
		$this->assertSame( 1, $result );
	}

	public function test_calculate_reading_time_positive(): void {
		$result = $this->invoke_private( 'calculate_reading_time', [ 200 ] );
		$this->assertSame( 1, $result );
	}

	public function test_calculate_reading_time_rounds_up(): void {
		$result = $this->invoke_private( 'calculate_reading_time', [ 1 ] );
		$this->assertSame( 1, $result );
	}

	public function test_calculate_content_hash_is_deterministic(): void {
		$h1 = $this->invoke_private( 'calculate_content_hash', [ 'title', 'content', 'excerpt' ] );
		$h2 = $this->invoke_private( 'calculate_content_hash', [ 'title', 'content', 'excerpt' ] );
		$this->assertSame( $h1, $h2 );
	}

	public function test_calculate_content_hash_different_inputs(): void {
		$h1 = $this->invoke_private( 'calculate_content_hash', [ 'title a', 'content', 'excerpt' ] );
		$h2 = $this->invoke_private( 'calculate_content_hash', [ 'title b', 'content', 'excerpt' ] );
		$this->assertNotSame( $h1, $h2 );
	}

	public function test_calculate_content_hash_returns_string(): void {
		$result = $this->invoke_private( 'calculate_content_hash', [ 't', 'c', 'e' ] );
		$this->assertIsString( $result );
	}
}
