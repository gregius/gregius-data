<?php
/**
 * Connection health monitor tests.
 *
 * Verifies perform_health_check() is per-connection: it loops over all active
 * connections when called with no argument (WP-Cron), and checks a single
 * connection when a name is provided (dashboard manual trigger).
 *
 * @package gregius-data
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

// class-gregius-data-connection-health.php guards on WPINC (unlike the ABSPATH guard
// used elsewhere), so define it to avoid the file's `die` in the unit tier.
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', true );
}

require_once __DIR__ . '/../../includes/class-gregius-data-logger.php';
require_once __DIR__ . '/../../includes/class-gregius-data-settings-manager.php';
require_once __DIR__ . '/../../includes/class-gregius-data-connection-health.php';

/**
 * Connection health monitor tests.
 */
class Test_Gregius_Data_Connection_Health extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gregius_data_test_stub_common_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a health monitor whose connection manager returns canned test results.
	 *
	 * @param array $test_results Per-connection test results ('*' = default).
	 * @return array{0: Gregius_Data_Connection_Health, 1: \Mockery\MockInterface}
	 */
	private function make_health( array $test_results ) {
		$cm = Mockery::mock();
		$cm->shouldReceive( 'test_connection' )->andReturnUsing(
			function ( $name ) use ( $test_results ) {
				if ( array_key_exists( $name, $test_results ) ) {
					return $test_results[ $name ];
				}
				return isset( $test_results['*'] ) ? $test_results['*'] : array( 'success' => true );
			}
		);

		$health = new class( $cm ) extends Gregius_Data_Connection_Health {
			private $cm;

			public function __construct( $cm ) {
				parent::__construct();
				$this->cm = $cm;
			}

			protected function create_connection_manager() {
				return $this->cm;
			}
		};

		$settings = Mockery::mock( 'Gregius_Data_Settings_Manager' );
		$ref      = new ReflectionProperty( Gregius_Data_Connection_Health::class, 'settings' );
		$ref->setAccessible( true );
		$ref->setValue( $health, $settings );

		$logger = Mockery::mock( 'Gregius_Data_Logger' );
		$logger->shouldReceive( 'log' )->andReturnNull();
		$ref_log = new ReflectionProperty( Gregius_Data_Connection_Health::class, 'logger' );
		$ref_log->setAccessible( true );
		$ref_log->setValue( $health, $logger );

		return array( $health, $settings );
	}

	/**
	 * Stub get_option/update_option and capture written option names.
	 *
	 * @param array $written Reference to capture written option name/value pairs.
	 * @return void
	 */
	private function stub_options( array &$written ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default_value = false ) {
				return $default_value;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$written ) {
				$written[ $name ] = $value;
				return true;
			}
		);
	}

	public function test_perform_health_check_loops_all_active_connections() {
		list( $health, $settings ) = $this->make_health( array( '*' => array( 'success' => true ) ) );

		$settings->shouldReceive( 'get_active_connections' )
			->andReturn(
				array(
					'conn_a' => array( 'is_active' => 'b:1;' ),
					'conn_b' => array( 'is_active' => 'b:1;' ),
				)
			);

		$written = array();
		$this->stub_options( $written );

		$health->perform_health_check();

		$this->assertArrayHasKey( 'gregius_data_connection_health_status_conn_a', $written );
		$this->assertArrayHasKey( 'gregius_data_connection_health_status_conn_b', $written );
	}

	public function test_perform_health_check_single_connection() {
		list( $health, $settings ) = $this->make_health(
			array(
				'conn_a' => array( 'success' => true ),
				'conn_b' => array(
					'success' => false,
					'message' => 'down',
				),
			)
		);

		// A single-connection check must NOT enumerate active connections.
		$settings->shouldReceive( 'get_active_connections' )->never();

		$written = array();
		$this->stub_options( $written );

		$health->perform_health_check( 'conn_a' );

		$this->assertArrayHasKey( 'gregius_data_connection_health_status_conn_a', $written );
		$this->assertArrayNotHasKey( 'gregius_data_connection_health_status_conn_b', $written );
	}

	public function test_perform_health_check_no_active_connections() {
		list( $health, $settings ) = $this->make_health( array() );

		$settings->shouldReceive( 'get_active_connections' )->andReturn( array() );

		$written = array();
		$this->stub_options( $written );

		$health->perform_health_check();

		// record_failure() with no active connections falls back to 'default'.
		$this->assertArrayHasKey( 'gregius_data_connection_health_status_default', $written );
	}
}
