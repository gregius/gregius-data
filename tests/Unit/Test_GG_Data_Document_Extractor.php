<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/ai/class-gg-data-document-extractor.php';

/**
 * Unit tests for the deprecation detection used by the reactive retire path.
 */
class Test_GG_Data_Document_Extractor extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Invoke the private is_model_unavailable() helper.
	 *
	 * @param string $message Provider error message.
	 * @return bool
	 */
	private function is_unavailable( string $message ): bool {
		$method = new ReflectionMethod( 'GG_Data_Document_Extractor', 'is_model_unavailable' );
		$method->setAccessible( true );

		return (bool) $method->invoke( null, $message );
	}

	public function test_detects_gemini_no_longer_available() {
		$this->assertTrue(
			$this->is_unavailable( 'This model models/gemini-2.5-flash is no longer available to new users.' )
		);
	}

	public function test_detects_deprecated_phrasing() {
		$this->assertTrue( $this->is_unavailable( 'The model has been deprecated.' ) );
	}

	public function test_detects_model_not_found() {
		$this->assertTrue( $this->is_unavailable( 'model_not_found: unknown model' ) );
	}

	public function test_ignores_transient_errors() {
		$this->assertFalse( $this->is_unavailable( 'Rate limit exceeded. Please retry.' ) );
		$this->assertFalse( $this->is_unavailable( 'Internal server error' ) );
		$this->assertFalse( $this->is_unavailable( 'Invalid request body' ) );
	}
}
