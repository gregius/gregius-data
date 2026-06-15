<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-gg-data-content-processor.php';

class Test_GG_Data_Content_Processor extends PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_clean_content_empty() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $this->assertSame( '', $processor->clean_content( '' ) );
    }

    public function test_clean_content_strips_html() {
        Functions\when( 'wp_strip_all_tags' )->alias(
            function ( $text ) {
                return strip_tags( $text );
            }
        );
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_content( '<p>Hello <b>world</b></p>' );
        $this->assertSame( 'Hello world', $result );
    }

    public function test_clean_content_strips_shortcodes() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->alias(
            function ( $text ) {
                return preg_replace( '/\[shortcode[^\]]*\].*?\[\/shortcode\]/', '', $text );
            }
        );
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_content( 'Hello [shortcode]content[/shortcode] world' );
        $this->assertSame( 'Hello world', $result );
    }

    public function test_clean_content_removes_extra_whitespace() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_content( 'Hello    world    here' );
        $this->assertSame( 'Hello world here', $result );
    }

    public function test_clean_content_enforces_minimum_length() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_content( 'Hi' );
        $this->assertSame( '', $result );
    }

    public function test_clean_content_skips_minimum_length_when_disabled() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_content( 'Hi', false );
        $this->assertSame( 'Hi', $result );
    }

    public function test_clean_content_trims() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_content( '  Hello world  ' );
        $this->assertSame( 'Hello world', $result );
    }

    public function test_clean_title_delegates_without_minimum() {
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'strip_shortcodes' )->returnArg();
        $processor = new GG_Data_Content_Processor();
        $result = $processor->clean_title( '  Hi  ' );
        $this->assertSame( 'Hi', $result );
    }
}
