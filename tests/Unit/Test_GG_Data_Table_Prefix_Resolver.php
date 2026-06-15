<?php

require_once __DIR__ . '/../../includes/class-gg-data-table-prefix-resolver.php';

class Test_GG_Data_Table_Prefix_Resolver extends PHPUnit\Framework\TestCase {

    public function test_runtime_prefix_is_wp_() {
        $this->assertSame( 'wp_', GG_Data_Table_Prefix_Resolver::runtime_prefix() );
    }

    public function test_mirror_table_prefixed() {
        $this->assertSame( 'wp_posts_clean', GG_Data_Table_Prefix_Resolver::mirror_table( 'posts_clean' ) );
    }

    public function test_mirror_table_removes_leading_underscore() {
        $this->assertSame( 'wp_already_prefixed', GG_Data_Table_Prefix_Resolver::mirror_table( '_already_prefixed' ) );
    }

    public function test_mirror_table_with_empty_base() {
        $this->assertSame( 'wp_', GG_Data_Table_Prefix_Resolver::mirror_table( '' ) );
    }

    public function test_mirror_table_with_schema_default() {
        $this->assertSame(
            'public.wp_posts_clean',
            GG_Data_Table_Prefix_Resolver::mirror_table_with_schema( 'posts_clean', 'public' )
        );
    }

    public function test_mirror_table_with_schema_custom() {
        $this->assertSame(
            'custom.wp_chunks',
            GG_Data_Table_Prefix_Resolver::mirror_table_with_schema( 'chunks', 'custom' )
        );
    }

    public function test_mirror_table_with_schema_empty() {
        $this->assertSame(
            '.wp_data',
            GG_Data_Table_Prefix_Resolver::mirror_table_with_schema( 'data', '' )
        );
    }

    public function test_mirror_table_with_schema_omitted() {
        $this->assertSame(
            'public.wp_posts',
            GG_Data_Table_Prefix_Resolver::mirror_table_with_schema( 'posts' )
        );
    }
}
