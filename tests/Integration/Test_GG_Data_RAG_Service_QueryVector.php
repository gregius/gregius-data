<?php
/**
 * Tier 2 Integration — RAG service query-vector literal.
 *
 * Regression guard for #1116: generate_query_vector_literal() must unwrap the
 * structured provider envelope (`vector`) and return null on an empty vector
 * rather than emitting a malformed pgvector literal.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_RAG_Service_QueryVector extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		gg_data_test_stub_common_functions();

		require_once __DIR__ . '/../../includes/ai/class-gg-data-llm-registry.php';
		require_once __DIR__ . '/../../includes/rag/class-gg-data-rag-service.php';

		// Reset the provider registry static cache so the filter re-applies.
		$reflection = new ReflectionClass( GG_Data_LLM_Registry::class );
		$prop       = $reflection->getProperty( 'providers' );
		$prop->setAccessible( true );
		$prop->setValue( array() );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_instance( array $model ): GG_Data_RAG_Service {
		$reflection = new ReflectionClass( GG_Data_RAG_Service::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		foreach ( array(
			'connection_name'     => 'test',
			'embedding_model_key' => 'text-embedding-3-small',
		) as $name => $value ) {
			$prop = $reflection->getProperty( $name );
			$prop->setAccessible( true );
			$prop->setValue( $instance, $value );
		}

		$model_registry = new class( $model ) {
			private $model;

			public function __construct( $model ) {
				$this->model = $model;
			}

			public function get_model( $connection_name, $model_key ) {
				return $this->model;
			}
		};

		$prop = $reflection->getProperty( 'model_registry' );
		$prop->setAccessible( true );
		$prop->setValue( $instance, $model_registry );

		return $instance;
	}

	private function invoke( GG_Data_RAG_Service $instance, string $query ) {
		$reflection = new ReflectionClass( GG_Data_RAG_Service::class );
		$method     = $reflection->getMethod( 'generate_query_vector_literal' );
		$method->setAccessible( true );

		return $method->invoke( $instance, $query );
	}

	private function register_provider( array $envelope ): void {
		$fake = new class( $envelope ) {
			private $envelope;

			public function __construct( $envelope ) {
				$this->envelope = $envelope;
			}

			public function generate_embedding( $text, $options = array() ) {
				return $this->envelope;
			}
		};

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'gg_data_llm_providers', \Mockery::type( 'array' ) )
			->andReturnUsing(
				function ( $hook, $providers ) use ( $fake ) {
					$providers['openai'] = $fake;

					return $providers;
				}
			);

		Functions\when( 'is_wp_error' )->justReturn( false );
	}

	private function model(): array {
		return array(
			'provider'          => 'openai',
			'provider_model_id' => 'text-embedding-3-small',
			'config'            => array( 'api_key' => 'x' ),
		);
	}

	public function test_unwraps_vector_envelope(): void {
		$this->register_provider(
			array(
				'vector'     => array( 0.1, 0.2, 0.3 ),
				'tokens'     => 3,
				'dimensions' => 3,
				'model'      => 'text-embedding-3-small',
			)
		);

		$instance = $this->make_instance( $this->model() );

		$this->assertSame( '[0.1,0.2,0.3]', $this->invoke( $instance, 'query' ) );
	}

	public function test_empty_vector_envelope_returns_null(): void {
		$this->register_provider( array( 'vector' => array() ) );

		$instance = $this->make_instance( $this->model() );

		$this->assertNull( $this->invoke( $instance, 'query' ) );
	}

	public function test_flat_vector_passthrough(): void {
		$this->register_provider( array( 0.1, 0.2 ) );

		$instance = $this->make_instance( $this->model() );

		$this->assertSame( '[0.1,0.2]', $this->invoke( $instance, 'query' ) );
	}
}
