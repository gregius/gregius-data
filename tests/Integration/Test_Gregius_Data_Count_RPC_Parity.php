<?php
/**
 * Live RPC-vs-SQL parity test.
 *
 * Proves the two Supabase count RPCs in postgrest-schema.sql return the SAME
 * number as the equivalent raw PDO SQL the validator runs for direct PostgreSQL
 * connections. Guards against the RPC and the PHP query drifting apart.
 *
 * SKIPPED unless a live Postgres is configured via:
 *   GREGIUS_DATA_TEST_PG_DSN       (e.g. pgsql:host=127.0.0.1;port=54422;dbname=postgres)
 *   GREGIUS_DATA_TEST_PG_USER      (default: postgres)
 *   GREGIUS_DATA_TEST_PG_PASSWORD  (default: empty)
 *
 * The target database must already have the wp_posts / wp_postmeta / wp_posts_clean
 * tables (i.e. the schema applied). The RPC functions are created in a transaction
 * and rolled back, so the test is non-destructive.
 *
 * @package gregius-data
 */

use PHPUnit\Framework\TestCase;

/**
 * Live RPC-vs-SQL parity tests.
 */
class Test_Gregius_Data_Count_RPC_Parity extends TestCase {

	/**
	 * Connect to the configured test Postgres, or skip.
	 *
	 * @return PDO
	 */
	private function pdo() {
		$dsn = getenv( 'GREGIUS_DATA_TEST_PG_DSN' );
		if ( false === $dsn || '' === $dsn ) {
			$this->markTestSkipped( 'Set GREGIUS_DATA_TEST_PG_DSN to run the live RPC-vs-SQL parity test.' );
		}

		$user = getenv( 'GREGIUS_DATA_TEST_PG_USER' );
		$pass = getenv( 'GREGIUS_DATA_TEST_PG_PASSWORD' );

		// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for the live PostgreSQL connection; $wpdb does not support PostgreSQL.
		return new PDO(
			$dsn,
			false !== $user && '' !== $user ? $user : 'postgres',
			false !== $pass ? $pass : '',
			array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
		);
		// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
	}

	/**
	 * Extract a CREATE OR REPLACE FUNCTION statement from the schema file.
	 *
	 * @param string $sql  Schema file contents.
	 * @param string $name Function name.
	 * @return string|null
	 */
	private function extract_function( $sql, $name ) {
		if ( ! preg_match( '/CREATE OR REPLACE FUNCTION ' . preg_quote( $name, '/' ) . '\b.*?\$\$;/s', $sql, $m ) ) {
			return null;
		}
		return $m[0];
	}

	/**
	 * The postmeta RPC must equal the PDO raw SQL on the same data.
	 */
	public function test_postmeta_count_rpc_matches_pdo_sql() {
		$pdo = $this->pdo();

		// Skip if the schema tables aren't present.
		$has_tables = (int) $pdo->query(
			"SELECT (to_regclass('public.wp_posts') IS NOT NULL AND to_regclass('public.wp_postmeta') IS NOT NULL)::int"
		)->fetchColumn();
		if ( ! $has_tables ) {
			$this->markTestSkipped( 'Schema tables (wp_posts, wp_postmeta) not present.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read (schema file), not a remote URL.
		$schema_sql = file_get_contents( dirname( __DIR__, 2 ) . '/includes/sql/postgrest-schema.sql' );
		$create_rpc = $this->extract_function( $schema_sql, 'gregius_data_count_postmeta' );
		$this->assertNotNull( $create_rpc, 'gregius_data_count_postmeta RPC not found in schema file.' );

		// Reference SQL mirrors Gregius_Data_Sync_Validator::get_postmeta_count() PDO branch.
		$reference_sql = "
			SELECT COUNT(*)
			FROM wp_postmeta pm
			INNER JOIN wp_posts p ON pm.post_id = p.id
			WHERE p.post_type = ANY('{docs}'::text[])
			  AND p.post_status = ANY('{publish}'::text[])
			  AND pm.meta_key NOT IN (
				'_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date',
				'_encloseme', '_pingme', '_wp_trash_meta_time', '_wp_trash_meta_status',
				'_wp_desired_post_slug', '_wp_attached_file', '_wp_attachment_metadata'
			  )
			  AND (
				pm.meta_key NOT LIKE '\\_%'
				OR pm.meta_key IN ('_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_thumbnail_id', '_wp_page_template')
				OR pm.meta_key LIKE 'field_%'
				OR pm.meta_key LIKE '\\_field\\_%'
			  )
		";

		$pdo->beginTransaction();
		try {
			$pdo->exec( $create_rpc );
			$rpc_count = (int) $pdo->query( "SELECT gregius_data_count_postmeta('{docs}'::text[], '{publish}'::text[])" )->fetchColumn();
			$ref_count = (int) $pdo->query( $reference_sql )->fetchColumn();

			$this->assertSame( $ref_count, $rpc_count, 'postmeta RPC and PDO SQL disagree.' );
		} finally {
			$pdo->rollBack();
		}
	}

	/**
	 * The cleaning RPC must equal the PDO raw SQL on the same data.
	 */
	public function test_cleaning_count_rpc_matches_pdo_sql() {
		$pdo = $this->pdo();

		$has_tables = (int) $pdo->query(
			"SELECT (to_regclass('public.wp_posts') IS NOT NULL AND to_regclass('public.wp_posts_clean') IS NOT NULL)::int"
		)->fetchColumn();
		if ( ! $has_tables ) {
			$this->markTestSkipped( 'Schema tables (wp_posts, wp_posts_clean) not present.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read (schema file), not a remote URL.
		$schema_sql = file_get_contents( dirname( __DIR__, 2 ) . '/includes/sql/postgrest-schema.sql' );
		$create_rpc = $this->extract_function( $schema_sql, 'gregius_data_count_cleaning_needed' );
		$this->assertNotNull( $create_rpc, 'gregius_data_count_cleaning_needed RPC not found in schema file.' );

		// Reference SQL mirrors Gregius_Data_Sync_Validator::get_cleaning_count_by_post_type() PDO branch.
		$reference_sql = "
			SELECT COUNT(*)
			FROM wp_posts p
			LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
			WHERE pc.post_id IS NULL
			  AND p.post_type = 'docs'
			  AND p.post_status = ANY('{publish}'::text[])
		";

		$pdo->beginTransaction();
		try {
			$pdo->exec( $create_rpc );
			$rpc_count = (int) $pdo->query( "SELECT gregius_data_count_cleaning_needed('docs', '{publish}'::text[])" )->fetchColumn();
			$ref_count = (int) $pdo->query( $reference_sql )->fetchColumn();

			$this->assertSame( $ref_count, $rpc_count, 'cleaning RPC and PDO SQL disagree.' );
		} finally {
			$pdo->rollBack();
		}
	}
}
