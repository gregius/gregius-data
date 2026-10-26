<?php
/**
 * Provider upsert contract tests.
 *
 * Verifies both database providers' upsert() honor the 1.0.1 contract:
 * - PostgREST: `Prefer: resolution=merge-duplicates,return=minimal`; payload omits id/created_at.
 * - PostgreSQL (PDO): `INSERT ... ON CONFLICT (cols) DO UPDATE`, conflict columns never overwritten.
 *
 * @package gregius-data
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-gregius-data-logger.php';
require_once __DIR__ . '/../../includes/providers/class-gregius-data-postgrest-provider.php';
require_once __DIR__ . '/../../includes/providers/class-gregius-data-postgresql-provider.php';

/**
 * Provider upsert contract tests.
 */
class Test_Gregius_Data_Provider_Upsert extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gregius_data_test_stub_common_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_postgrest_upsert_sends_merge_duplicates_payload() {
		$config   = array(
			'project_url'     => 'https://example.supabase.co',
			'publishable_key' => 'pk',
			'secret_key'      => 'sk',
		);
		$provider = new Gregius_Data_PostgREST_Provider( $config, 'conn' );

		$data = array(
			'connection_name' => 'conn',
			'entity_type'     => 'post',
			'entity_id'       => '42',
			'synced_at'       => '2025-01-01 00:00:00',
			'updated_at'      => '2025-01-01 00:00:00',
		);

		$captured = null;

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'trailingslashit' )->alias(
			function ( $s ) {
				return rtrim( $s, '/' ) . '/';
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\expect( 'wp_safe_remote_post' )
			->once()
			->with(
				'https://example.supabase.co/rest/v1/wp_gregius_data_sync_metadata_entities',
				\Mockery::on(
					function ( $args ) use ( &$captured ) {
						$captured = $args;
						return true;
					}
				)
			)
			->andReturn( array() );

		$result = $provider->upsert(
			'wp_gregius_data_sync_metadata_entities',
			$data,
			array( 'connection_name', 'entity_type', 'entity_id' )
		);

		$this->assertTrue( $result );
		$this->assertSame( 'resolution=merge-duplicates,return=minimal', $captured['headers']['Prefer'] );

		$decoded = json_decode( $captured['body'], true );
		$this->assertArrayHasKey( 'synced_at', $decoded );
		$this->assertArrayHasKey( 'updated_at', $decoded );
		$this->assertArrayNotHasKey( 'id', $decoded );
		$this->assertArrayNotHasKey( 'created_at', $decoded );
	}

	public function test_postgrest_upsert_rejects_empty_data() {
		$provider = new Gregius_Data_PostgREST_Provider(
			array(
				'project_url'     => 'https://example.supabase.co',
				'publishable_key' => 'pk',
				'secret_key'      => 'sk',
			),
			'conn'
		);

		$this->assertFalse( $provider->upsert( 'wp_gregius_data_sync_metadata_entities', array() ) );
	}

	public function test_sync_post_payload_excludes_stale_meta_taxonomies() {
		$provider = new Gregius_Data_PostgREST_Provider(
			array(
				'project_url'     => 'https://example.supabase.co',
				'publishable_key' => 'pk',
				'secret_key'      => 'sk',
			),
			'conn'
		);

		$captured_body = null;
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'trailingslashit' )->alias(
			function ( $s ) {
				return rtrim( $s, '/' ) . '/';
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 201 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\expect( 'wp_safe_remote_post' )
			->once()
			->with(
				'https://example.supabase.co/rest/v1/wp_posts',
				\Mockery::on(
					function ( $args ) use ( &$captured_body ) {
						$captured_body = $args['body'];
						return true;
					}
				)
			)
			->andReturn( array() );

		$result = $provider->sync_post(
			42,
			array(
				'post_title'   => 'Test Title',
				'post_content' => 'Test Content',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );

		$decoded = json_decode( $captured_body, true );
		$this->assertArrayNotHasKey( 'meta', $decoded );
		$this->assertArrayNotHasKey( 'taxonomies', $decoded );
		$this->assertSame( 'Test Title', $decoded['post_title'] );
		$this->assertSame( 42, $decoded['id'] );
	}

	public function test_pdo_upsert_generates_on_conflict_sql() {
		$provider = new Gregius_Data_PostgreSQL_Provider( array(), 'conn' );

		$stmt = \Mockery::mock( 'PDOStatement' );
		$stmt->shouldReceive( 'execute' )->andReturn( true );

		$captured_sql = null;
		$conn         = \Mockery::mock( 'PDO' );
		$conn->shouldReceive( 'prepare' )
			->with(
				\Mockery::on(
					function ( $sql ) use ( &$captured_sql ) {
						$captured_sql = $sql;
						return true;
					}
				)
			)
			->andReturn( $stmt );

		$ref = new ReflectionProperty( $provider, 'conn' );
		$ref->setAccessible( true );
		$ref->setValue( $provider, $conn );

		$data = array(
			'connection_name' => 'conn',
			'entity_type'     => 'post',
			'entity_id'       => '42',
			'synced_at'       => '2025-01-01 00:00:00',
			'updated_at'      => '2025-01-01 00:00:00',
		);

		$result = $provider->upsert(
			'wp_gregius_data_sync_metadata_entities',
			$data,
			array( 'connection_name', 'entity_type', 'entity_id' )
		);

		$this->assertTrue( $result );
		$this->assertStringContainsString(
			'ON CONFLICT (connection_name, entity_type, entity_id) DO UPDATE SET',
			$captured_sql
		);
		// Conflict columns must never be overwritten.
		$this->assertStringNotContainsString( 'connection_name = EXCLUDED.connection_name', $captured_sql );
		$this->assertStringNotContainsString( 'entity_id = EXCLUDED.entity_id', $captured_sql );
		// Non-conflict columns must be updated.
		$this->assertStringContainsString( 'synced_at = EXCLUDED.synced_at', $captured_sql );
		$this->assertStringContainsString( 'updated_at = EXCLUDED.updated_at', $captured_sql );
	}
}
