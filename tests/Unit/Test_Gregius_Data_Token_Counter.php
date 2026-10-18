<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/class-gregius-data-settings-manager.php';
require_once __DIR__ . '/../../includes/class-gregius-data-token-counter.php';

class Test_Gregius_Data_Token_Counter extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gregius_data_test_stub_common_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_estimate_tokens_empty() {
		$counter = new Gregius_Data_Token_Counter();
		$this->assertSame( 0, $counter->estimate_tokens( '' ) );
	}

	public function test_estimate_tokens_short_text() {
		$counter = new Gregius_Data_Token_Counter();
		$this->assertSame( 2, $counter->estimate_tokens( 'hello' ) );
	}

	public function test_estimate_tokens_long_text() {
		$counter = new Gregius_Data_Token_Counter();
		$result  = $counter->estimate_tokens( str_repeat( 'a', 1000 ) );
		$this->assertSame( 250, $result );
	}

	public function test_estimate_tokens_multibyte() {
		$counter  = new Gregius_Data_Token_Counter();
		$text     = 'café résumé';
		$expected = (int) ceil( strlen( $text ) / 4 );
		$this->assertSame( $expected, $counter->estimate_tokens( $text ) );
	}

	public function test_estimate_tokens_null_becomes_empty() {
		$counter = new Gregius_Data_Token_Counter();
		$this->assertSame( 0, $counter->estimate_tokens( null ) );
	}

	public function test_estimate_tokens_whitespace() {
		$counter  = new Gregius_Data_Token_Counter();
		$text     = '   ';
		$expected = (int) ceil( strlen( $text ) / 4 );
		$this->assertSame( $expected, $counter->estimate_tokens( $text ) );
	}
}
