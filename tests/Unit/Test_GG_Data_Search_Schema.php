<?php

use Brain\Monkey;

require_once __DIR__ . '/../../includes/search/class-gg-data-search-schema.php';

/**
 * Unit tests for the search schema whitelist and the best-match FTS helper.
 *
 * The search functions themselves require a live PostgreSQL connection, which
 * the unit suite does not provide, so these tests pin the static contract: the
 * helper is whitelisted, defined in the canonical schema, extractable by the
 * schema manager's parser, and not regressed to strict-AND plainto_tsquery.
 */
class Test_GG_Data_Search_Schema extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function schema_sql(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the local plugin schema file, not a remote URL.
		return (string) file_get_contents( __DIR__ . '/../../includes/sql/postgrest-schema.sql' );
	}

	public function test_bestmatch_tsquery_helper_is_whitelisted() {
		$reflection = new ReflectionClass( GG_Data_Search_Schema::class );

		$this->assertContains(
			'search_core_bestmatch_tsquery',
			$reflection->getConstant( 'SEARCH_FUNCTIONS' )
		);
	}

	public function test_bestmatch_tsquery_helper_is_defined_in_schema() {
		$this->assertStringContainsString(
			'CREATE OR REPLACE FUNCTION search_core_bestmatch_tsquery',
			$this->schema_sql()
		);
	}

	public function test_bestmatch_tsquery_helper_is_extractable() {
		$found = preg_match(
			'/CREATE\s+OR\s+REPLACE\s+FUNCTION\s+search_core_bestmatch_tsquery.+?\$\$\s*;/is',
			$this->schema_sql(),
			$matches
		);

		$this->assertSame( 1, $found );

		$reflection = new ReflectionClass( GG_Data_Search_Schema::class );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'is_search_function_statement' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $instance, trim( $matches[0] ) ) );
	}

	public function test_schema_does_not_revert_to_strict_and_plainto_tsquery() {
		$this->assertStringNotContainsString( 'plainto_tsquery(', $this->schema_sql() );
	}
}
