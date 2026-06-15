<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-gg-data-table-prefix-resolver.php';
require_once __DIR__ . '/../../includes/class-gg-data-logger.php';
require_once __DIR__ . '/../../includes/class-gg-data-connection-manager.php';
require_once __DIR__ . '/../../includes/class-gg-data-chunker.php';

class Test_GG_Data_Chunker extends PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        gg_data_test_stub_common_functions();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function create_chunker() {
        Functions\when( 'apply_filters' )->alias(
            function ( $hook, $value ) {
                return $value;
            }
        );
        return new GG_Data_Chunker();
    }

    public function test_estimate_tokens_empty() {
        $chunker = $this->create_chunker();
        $this->assertSame( 0, $chunker->estimate_tokens( '' ) );
    }

    public function test_estimate_tokens_short_text() {
        $chunker = $this->create_chunker();
        $this->assertSame( 2, $chunker->estimate_tokens( 'hello' ) );
    }

    public function test_estimate_tokens_long_text() {
        $chunker = $this->create_chunker();
        $text = str_repeat( 'a', 1000 );
        $this->assertSame( 250, $chunker->estimate_tokens( $text ) );
    }

    public function test_chunk_content_empty_returns_empty_array() {
        $chunker = $this->create_chunker();
        $this->assertSame( array(), $chunker->chunk_content( '' ) );
    }

    public function test_chunk_content_short_content_single_chunk() {
        $chunker = $this->create_chunker();
        $content = 'Hello world. This is a short content.';
        $chunks = $chunker->chunk_content( $content );
        $this->assertCount( 1, $chunks );
        $this->assertArrayHasKey( 'text', $chunks[0] );
        $this->assertArrayHasKey( 'token_count', $chunks[0] );
        $this->assertArrayHasKey( 'hash', $chunks[0] );
    }

    public function test_chunk_content_paragraph_boundaries() {
        $chunker = $this->create_chunker();
        $content = str_repeat( 'This is a sentence. ', 20 ) . "\n\n" . str_repeat( 'Another paragraph here. ', 20 );
        $chunks = $chunker->chunk_content( $content );
        $this->assertGreaterThanOrEqual( 1, count( $chunks ) );
        $this->assertNotEmpty( $chunks[0]['text'] );
    }

    public function test_normalize_strategy_chunks_string_input() {
        $chunker = $this->create_chunker();
        $reflection = new ReflectionMethod( GG_Data_Chunker::class, 'normalize_strategy_chunks' );
        $reflection->setAccessible( true );

        $raw = array( 'simple string chunk' );
        $result = $reflection->invoke( $chunker, $raw, 'default', 'default' );

        $this->assertCount( 1, $result );
        $this->assertSame( 'simple string chunk', $result[0]['text'] );
    }

    public function test_normalize_strategy_chunks_filters_empty() {
        $chunker = $this->create_chunker();
        $reflection = new ReflectionMethod( GG_Data_Chunker::class, 'normalize_strategy_chunks' );
        $reflection->setAccessible( true );

        $raw = array(
            array( 'text' => 'valid' ),
            array( 'text' => '' ),
            null,
        );
        $result = $reflection->invoke( $chunker, $raw, 'default', 'default' );

        $this->assertCount( 1, $result );
        $this->assertSame( 'valid', $result[0]['text'] );
    }

    public function test_normalize_strategy_chunks_falls_back_to_md5_hash() {
        $chunker = $this->create_chunker();
        $reflection = new ReflectionMethod( GG_Data_Chunker::class, 'normalize_strategy_chunks' );
        $reflection->setAccessible( true );

        $raw = array( array( 'text' => 'hello' ) );
        $result = $reflection->invoke( $chunker, $raw, 'default', 'default' );

        $this->assertSame( md5( 'hello' ), $result[0]['hash'] );
    }

    public function test_create_chunk_data_trims() {
        $chunker = $this->create_chunker();
        $reflection = new ReflectionMethod( GG_Data_Chunker::class, 'create_chunk_data' );
        $reflection->setAccessible( true );

        $result = $reflection->invoke( $chunker, '  hello world  ' );
        $this->assertSame( 'hello world', $result['text'] );
    }

    public function test_get_active_chunking_strategy_key_returns_default_when_empty() {
        $chunker = $this->create_chunker();

        Functions\when( 'apply_filters' )->alias(
            function ( $hook, $value ) {
                return '';
            }
        );

        $reflection = new ReflectionMethod( GG_Data_Chunker::class, 'get_active_chunking_strategy_key' );
        $reflection->setAccessible( true );

        $result = $reflection->invoke( $chunker, 'default', array() );
        $this->assertSame( 'default', $result );
    }

    public function test_get_active_chunking_strategy_key_passes_through() {
        $chunker = $this->create_chunker();

        Functions\when( 'apply_filters' )->alias(
            function ( $hook, $value ) {
                return 'custom_strategy';
            }
        );

        $reflection = new ReflectionMethod( GG_Data_Chunker::class, 'get_active_chunking_strategy_key' );
        $reflection->setAccessible( true );

        $result = $reflection->invoke( $chunker, 'default', array() );
        $this->assertSame( 'custom_strategy', $result );
    }
}
