<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-gg-data-prompt.php';
require_once __DIR__ . '/../../includes/class-gg-data-prompt-resolver.php';

class Test_GG_Data_Prompt_Resolver extends PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        gg_data_test_stub_common_functions();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_resolve_prompt_with_explicit_id() {
        $resolver = new GG_Data_Prompt_Resolver();

        Functions\when( 'get_post' )->alias(
            function ( $id ) {
                return (object) array(
                    'ID'           => $id,
                    'post_type'    => 'gg_prompt',
                    'post_status'  => 'publish',
                    'post_content' => 'You are a helpful assistant.',
                    'post_title'   => 'Test Prompt',
                );
            }
        );

        Functions\when( 'get_post_meta' )->alias(
            function ( $post_id, $key, $single = false ) {
                $meta = array(
                    '_gg_prompt_status'  => 'published',
                    '_gg_prompt_version' => '1',
                    '_gg_prompt_hash'    => 'abc123',
                );
                $value = $meta[ $key ] ?? '';
                return $single ? $value : array( $value );
            }
        );

        Functions\when( 'taxonomy_exists' )->justReturn( true );
        Functions\when( 'wp_get_object_terms' )->alias(
            function ( $id, $taxonomy, $args = array() ) {
                return array( 'system' );
            }
        );

        $result = $resolver->resolve_prompt( 42, 'system' );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'content', $result );
        $this->assertArrayHasKey( 'metadata', $result );
        $this->assertSame( 42, $result['metadata']['id'] );
    }

    public function test_resolve_prompt_without_explicit_uses_selected() {
        $resolver = new GG_Data_Prompt_Resolver();

        Functions\when( 'get_post' )->alias(
            function ( $id ) {
                return (object) array(
                    'ID'           => $id,
                    'post_type'    => 'gg_prompt',
                    'post_status'  => 'publish',
                    'post_content' => 'Selected prompt content.',
                    'post_title'   => 'Selected',
                );
            }
        );

        Functions\when( 'get_posts' )->alias(
            function ( $args ) {
                return array( 10, 20 );
            }
        );

        Functions\when( 'get_post_meta' )->alias(
            function ( $post_id, $key, $single = false ) {
                $meta = array(
                    '_gg_prompt_status'   => 'published',
                    '_gg_prompt_version'  => '1',
                    '_gg_prompt_hash'     => 'def456',
                    '_gg_prompt_selected' => '1',
                    '_gg_prompt_is_factory' => '',
                );
                $value = $meta[ $key ] ?? '';
                return $single ? $value : array( $value );
            }
        );

        Functions\when( 'taxonomy_exists' )->justReturn( true );
        Functions\when( 'wp_get_object_terms' )->alias(
            function ( $id, $taxonomy, $args = array() ) {
                return array( 'system' );
            }
        );

        $result = $resolver->resolve_prompt( 0, 'system' );
        $this->assertIsArray( $result );
        $this->assertStringContainsString( 'Selected prompt content.', $result['content'] );
    }

    public function test_resolve_prompt_returns_wp_error_when_no_prompt_found() {
        $resolver = new GG_Data_Prompt_Resolver();

        Functions\when( 'get_post' )->justReturn( null );
        Functions\when( 'get_posts' )->justReturn( array() );
        Functions\when( 'taxonomy_exists' )->justReturn( false );

        $result = $resolver->resolve_prompt( 0, 'system' );
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'gg_data_prompt_not_found', $result->get_error_code() );
    }

    public function test_resolve_prompt_skips_draft_status() {
        $resolver = new GG_Data_Prompt_Resolver();

        Functions\when( 'get_post' )->alias(
            function ( $id ) {
                return (object) array(
                    'ID'           => $id,
                    'post_type'    => 'gg_prompt',
                    'post_status'  => 'publish',
                    'post_content' => 'Test.',
                    'post_title'   => 'Test',
                );
            }
        );

        Functions\when( 'get_posts' )->justReturn( array( 1 ) );

        $call_count = 0;
        Functions\when( 'get_post_meta' )->alias(
            function ( $post_id, $key, $single = false ) use ( &$call_count ) {
                $call_count++;
                $meta = array(
                    '_gg_prompt_status'  => 'draft',
                    '_gg_prompt_version' => '1',
                    '_gg_prompt_hash'    => 'hash123',
                );
                $value = $meta[ $key ] ?? '';
                return $single ? $value : array( $value );
            }
        );

        Functions\when( 'taxonomy_exists' )->justReturn( true );
        Functions\when( 'wp_get_object_terms' )->alias(
            function ( $id, $taxonomy, $args = array() ) {
                return array( 'system' );
            }
        );

        $result = $resolver->resolve_prompt( 0, 'system' );
        $this->assertInstanceOf( 'WP_Error', $result );
    }

    public function test_expand_tokens_replaces_date() {
        $resolver = new GG_Data_Prompt_Resolver();

        Functions\when( 'wp_date' )->alias(
            function ( $format ) {
                return date( $format );
            }
        );

        $reflection = new ReflectionMethod( GG_Data_Prompt_Resolver::class, 'expand_tokens' );
        $reflection->setAccessible( true );

        $result = $reflection->invoke( $resolver, 'Today is {{date}}.' );
        $this->assertStringContainsString( date( 'F' ), $result );
        $this->assertStringNotContainsString( '{{date}}', $result );
    }

    public function test_expand_tokens_no_tokens_unchanged() {
        $reflection = new ReflectionMethod( GG_Data_Prompt_Resolver::class, 'expand_tokens' );
        $reflection->setAccessible( true );

        $resolver = new GG_Data_Prompt_Resolver();
        $result = $reflection->invoke( $resolver, 'No tokens here.' );
        $this->assertSame( 'No tokens here.', $result );
    }

    public function test_hash_content_normalizes_newlines() {
        $reflection = new ReflectionMethod( GG_Data_Prompt_Resolver::class, 'hash_content' );
        $reflection->setAccessible( true );

        $resolver = new GG_Data_Prompt_Resolver();
        $hash1 = $reflection->invoke( $resolver, "Hello\nWorld" );
        $hash2 = $reflection->invoke( $resolver, "Hello\r\nWorld" );
        $this->assertSame( $hash1, $hash2 );
    }

    public function test_hash_content_different_content_different_hash() {
        $reflection = new ReflectionMethod( GG_Data_Prompt_Resolver::class, 'hash_content' );
        $reflection->setAccessible( true );

        $resolver = new GG_Data_Prompt_Resolver();
        $hash1 = $reflection->invoke( $resolver, 'Hello' );
        $hash2 = $reflection->invoke( $resolver, 'World' );
        $this->assertNotSame( $hash1, $hash2 );
    }
}
