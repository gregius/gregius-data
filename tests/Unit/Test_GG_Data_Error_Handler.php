<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/class-gg-data-error-handler.php';

class Test_GG_Data_Error_Handler extends PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        gg_data_test_stub_common_functions();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_classify_transient_connection_timeout() {
        $result = GG_Data_Error_Handler::classify_error( 'connection timeout' );
        $this->assertSame( 'transient', $result['type'] );
        $this->assertTrue( $result['retry_safe'] );
    }

    public function test_classify_transient_deadlock() {
        $result = GG_Data_Error_Handler::classify_error( 'DEADLOCK DETECTED WHILE updating' );
        $this->assertSame( 'transient', $result['type'] );
        $this->assertTrue( $result['retry_safe'] );
    }

    public function test_classify_permanent_table_does_not_exist() {
        $result = GG_Data_Error_Handler::classify_error( 'table does not exist' );
        $this->assertSame( 'permanent', $result['type'] );
        $this->assertFalse( $result['retry_safe'] );
    }

    public function test_classify_permanent_unique_violation() {
        $result = GG_Data_Error_Handler::classify_error( 'unique violation on column id' );
        $this->assertSame( 'permanent', $result['type'] );
        $this->assertFalse( $result['retry_safe'] );
    }

    public function test_classify_unknown_error() {
        $result = GG_Data_Error_Handler::classify_error( 'some random unknown error text' );
        $this->assertSame( 'unknown', $result['type'] );
        $this->assertTrue( $result['retry_safe'] );
    }

    public function test_classify_empty_string() {
        $result = GG_Data_Error_Handler::classify_error( '' );
        $this->assertSame( 'unknown', $result['type'] );
        $this->assertTrue( $result['retry_safe'] );
    }

    public function test_classify_substring_matching() {
        $result = GG_Data_Error_Handler::classify_error( 'a deadlock detected while updating the record' );
        $this->assertSame( 'transient', $result['type'] );
    }

    public function test_calculate_retry_delay_attempt_1() {
        $this->assertSame( 5, GG_Data_Error_Handler::calculate_retry_delay( 1 ) );
    }

    public function test_calculate_retry_delay_attempt_2() {
        $this->assertSame( 30, GG_Data_Error_Handler::calculate_retry_delay( 2 ) );
    }

    public function test_calculate_retry_delay_attempt_3() {
        $this->assertSame( 300, GG_Data_Error_Handler::calculate_retry_delay( 3 ) );
    }

    public function test_calculate_retry_delay_capped_at_300() {
        $this->assertSame( 300, GG_Data_Error_Handler::calculate_retry_delay( 10 ) );
    }

    public function test_get_retry_schedule_text_three_attempts() {
        $result = GG_Data_Error_Handler::get_retry_schedule_text( 3 );
        $this->assertStringContainsString( 'Attempt 1: 5s', $result );
        $this->assertStringContainsString( 'Attempt 2: 30s', $result );
        $this->assertStringContainsString( 'Attempt 3: 300s', $result );
    }

    public function test_get_retry_schedule_text_zero() {
        $result = GG_Data_Error_Handler::get_retry_schedule_text( 0 );
        $this->assertSame( '', $result );
    }
}
