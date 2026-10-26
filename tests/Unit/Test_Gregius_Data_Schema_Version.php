<?php
/**
 * Schema version consistency test.
 *
 * Asserts the version stamp embedded in `postgrest-schema.sql` matches the
 * `GREGIUS_DATA_SCHEMA_VERSION` constant, so a schema change cannot ship without
 * a matching version bump (and vice versa).
 *
 * @package gregius-data
 */

use PHPUnit\Framework\TestCase;

/**
 * Schema version consistency tests.
 */
class Test_Gregius_Data_Schema_Version extends TestCase {

	/**
	 * Plugin root path.
	 *
	 * @return string
	 */
	private function plugin_root() {
		return dirname( __DIR__, 2 );
	}

	/**
	 * The schema SQL stamp must equal the GREGIUS_DATA_SCHEMA_VERSION constant.
	 */
	public function test_schema_stamp_matches_constant() {
		$plugin_file = $this->plugin_root() . '/gregius-data.php';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read (plugin file), not a remote URL.
		$php = file_get_contents( $plugin_file );
		$this->assertNotFalse( $php, 'Failed to read gregius-data.php.' );

		// Extract the constant without loading WordPress (parse the file directly).
		$constant_version = $this->extract_constant( $php );
		$this->assertNotNull( $constant_version, 'GREGIUS_DATA_SCHEMA_VERSION constant not found in gregius-data.php.' );

		$sql_file = $this->plugin_root() . '/includes/sql/postgrest-schema.sql';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read (schema file), not a remote URL.
		$sql = file_get_contents( $sql_file );
		$this->assertNotFalse( $sql, 'Failed to read postgrest-schema.sql.' );

		// Header comment version.
		if ( preg_match( '/^--\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/m', $sql, $m_header ) ) {
			$this->assertSame(
				$constant_version,
				$m_header[1],
				'postgrest-schema.sql header version must match GREGIUS_DATA_SCHEMA_VERSION.'
			);
		} else {
			$this->fail( 'postgrest-schema.sql is missing the "-- Version: x.y.z" header.' );
		}

		// End-of-file version stamp.
		if ( preg_match( "/INSERT\s+INTO\s+gregius_data_schema_meta[^;]*?VALUES\s*\(\s*'version'\s*,\s*'([^']+)'\s*\)/is", $sql, $m_stamp ) ) {
			$this->assertSame(
				$constant_version,
				$m_stamp[1],
				'postgrest-schema.sql version stamp must match GREGIUS_DATA_SCHEMA_VERSION.'
			);
		} else {
			$this->fail( 'postgrest-schema.sql is missing the gregius_data_schema_meta version stamp.' );
		}
	}

	/**
	 * The two Supabase count RPCs must exist in the schema file.
	 */
	public function test_count_rpcs_are_declared() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read (schema file), not a remote URL.
		$sql = file_get_contents( $this->plugin_root() . '/includes/sql/postgrest-schema.sql' );
		$this->assertNotFalse( $sql, 'Failed to read postgrest-schema.sql.' );

		$this->assertStringContainsString(
			'CREATE OR REPLACE FUNCTION gregius_data_count_postmeta(',
			$sql,
			'gregius_data_count_postmeta RPC is missing.'
		);
		$this->assertStringContainsString(
			'CREATE OR REPLACE FUNCTION gregius_data_count_cleaning_needed(',
			$sql,
			'gregius_data_count_cleaning_needed RPC is missing.'
		);
	}

	/**
	 * The count RPCs must implement the same filters as the PDO raw SQL (parity).
	 *
	 * Guards against the RPC SQL drifting from the PHP-side count semantics.
	 */
	public function test_count_rpc_sql_matches_expected_filters() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read (schema file), not a remote URL.
		$sql = file_get_contents( $this->plugin_root() . '/includes/sql/postgrest-schema.sql' );
		$this->assertNotFalse( $sql, 'Failed to read postgrest-schema.sql.' );

		// postmeta RPC: meta_key blacklist + post type/status join (matches validate_postmeta()).
		$this->assertStringContainsString( 'pm.meta_key NOT IN (', $sql );
		$this->assertStringContainsString( "'_edit_lock', '_edit_last'", $sql );
		$this->assertStringContainsString( 'p.post_type = ANY(post_types)', $sql );
		$this->assertStringContainsString( 'p.post_status = ANY(statuses)', $sql );
		$this->assertStringContainsString( "pm.meta_key NOT LIKE '\\_%'", $sql );

		// cleaning RPC: LEFT JOIN posts vs posts_clean, missing-clean predicate.
		$this->assertStringContainsString( 'LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id', $sql );
		$this->assertStringContainsString( 'WHERE pc.post_id IS NULL', $sql );
	}

	/**
	 * Extract a constant value from the plugin bootstrap file.
	 *
	 * @param string $php File contents.
	 * @return string|null
	 */
	private function extract_constant( $php ) {
		if ( preg_match( "/define\(\s*'GREGIUS_DATA_SCHEMA_VERSION'\s*,\s*'([^']+)'\s*\)/", $php, $m ) ) {
			return $m[1];
		}

		return null;
	}
}
