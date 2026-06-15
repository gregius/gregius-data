<?php
/**
 * API Embeddings Vector Generation Strategy v2.0
 *
 * Row-per-embedding architecture with chunk support.
 * Strategy for API-based embedding generation (OpenAI, Voyage AI, etc).
 *
 * Schema: embedding tables with (post_id, field_type, chunk_index, embedding)
 * - field_type: 'title', 'excerpt', 'chunk'
 * - chunk_index: NULL for title/excerpt, 0-N for chunks
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes/vectors/strategies
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_API_Embeddings_Strategy
 *
 * Implements the vector generation strategy for external API embeddings.
 * Supports OpenAI, Voyage AI, and other API-based embedding providers.
 *
 * @since 2.0.0
 */
class GG_Data_API_Embeddings_Strategy implements GG_Data_Vector_Strategy_Interface {

	/**
	 * Connection manager instance
	 *
	 * @var GG_Data_Connection_Manager
	 */
	private $connection_manager;

	/**
	 * Model registry instance
	 *
	 * @var GG_Data_Model_Registry
	 */
	private $model_registry;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * DB instance for PostgreSQL operations
	 *
	 * @var GG_Data_DB
	 */
	private $db;

	/**
	 * Settings manager
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings;

	/**
	 * Chunker instance
	 *
	 * @var GG_Data_Chunker
	 */
	private $chunker;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->connection_manager = new GG_Data_Connection_Manager();
		$this->model_registry     = new GG_Data_Model_Registry();
		$this->logger             = new GG_Data_Logger();
		$this->db                 = new GG_Data_DB();
		$this->settings           = new GG_Data_Settings_Manager();
		$this->chunker            = new GG_Data_Chunker();
	}

	/**
	 * Get API key from model configuration.
	 *
	 * Supports both legacy (root-level api_key) and nested (config.api_key) formats.
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return string API key or empty string if not found.
	 */
	private function get_model_api_key( array $model ): string {
		// Check nested config.api_key first (preferred format).
		if ( ! empty( $model['config']['api_key'] ) ) {
			return $model['config']['api_key'];
		}

		// Fallback to root-level api_key (legacy format from DB).
		if ( ! empty( $model['api_key'] ) ) {
			return $model['api_key'];
		}

		return '';
	}

	/**
	 * Get strategy identifier
	 *
	 * @since 1.0.0
	 * @return string Strategy ID.
	 */
	public function get_id(): string {
		return 'api-embeddings';
	}

	/**
	 * Get strategy display name
	 *
	 * @since 1.0.0
	 * @return string Strategy name.
	 */
	public function get_name(): string {
		return 'API Embeddings (OpenAI, Voyage AI, etc)';
	}

	/**
	 * Check if strategy supports a specific model
	 *
	 * @since 1.0.0
	 * @param array $model Model configuration.
	 * @return bool True if strategy supports this model.
	 */
	public function supports_model( array $model ): bool {
		return isset( $model['provider'] ) &&
			'internal' !== $model['provider'] &&
			isset( $model['dimensions'] ) &&
			$model['dimensions'] > 0;
	}

	/**
	 * Generate vectors for a batch of posts
	 *
	 * Routes to PDO or Supabase implementation based on provider type.
	 *
	 * @since 2.0.0
	 * @param array  $model           Model configuration from registry.
	 * @param int    $batch_size      Number of posts to process.
	 * @param string $connection_name PostgreSQL connection name.
	 * @return array Generation result.
	 */
	public function generate( array $model, int $batch_size, string $connection_name ): array {
		$this->logger->log(
			sprintf(
				'API Embeddings Strategy v2: Starting generation for model "%s" (batch_size=%d)',
				$model['model_key'],
				$batch_size
			),
			'info',
			'vectors',
			$connection_name,
			array(
				'model_key'  => $model['model_key'],
				'batch_size' => $batch_size,
				'provider'   => $model['provider'] ?? 'unknown',
				'strategy'   => 'api-embeddings-v2',
			)
		);

		// Detect provider type.
		$config        = $this->settings->get_connection( $connection_name );
		$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
			return $this->generate_batch_supabase( $model, $batch_size, $connection_name );
		}

		return $this->generate_batch_pdo( $model, $batch_size, $connection_name );
	}

	/**
	 * Generate vectors batch via PDO (direct PostgreSQL)
	 *
	 * Uses true batch embedding API calls when provider supports it.
	 *
	 * @param array  $model           Model configuration.
	 * @param int    $batch_size      Batch size.
	 * @param string $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_batch_pdo( array $model, int $batch_size, string $connection_name ): array {
		try {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return $this->error_response( 'Failed to connect to PostgreSQL database' );
			}

			// Get posts needing vectors.
			$posts = $this->get_posts_needing_vectors_pdo( $conn, $model['vector_table_name'], $batch_size );

			if ( empty( $posts ) ) {
				return array(
					'success'      => true,
					'message'      => 'No posts need vectors',
					'processed'    => 0,
					'failed'       => 0,
					'total_tokens' => 0,
				);
			}

			// Get AI provider.
			$provider = GG_Data_LLM_Registry::get_provider( $model['provider'] );
			if ( is_wp_error( $provider ) ) {
				return $this->error_response( 'Provider not found: ' . $model['provider'] );
			}

			$capabilities = $provider->get_capabilities();
			if ( ! in_array( 'embeddings', $capabilities, true ) ) {
				return $this->error_response( 'Provider does not support embeddings' );
			}

			// Check if provider supports batch embeddings.
			$supports_batch = method_exists( $provider, 'generate_embeddings_batch' );

			if ( $supports_batch ) {
				return $this->generate_batch_pdo_batched( $conn, $posts, $provider, $model, $connection_name );
			}

			// Fallback to sequential processing for providers without batch support.
			return $this->generate_batch_pdo_sequential( $conn, $posts, $provider, $model, $connection_name );

		} catch ( Exception $e ) {
			return $this->error_response( 'Generation error: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate vectors using true batch API calls (PDO).
	 *
	 * Collects all texts first, sends one API call, maps results back.
	 *
	 * @param PDO                           $conn            Database connection.
	 * @param array                         $posts           Posts to process.
	 * @param GG_Data_AI_Provider_Interface $provider        AI provider.
	 * @param array                         $model           Model configuration.
	 * @param string                        $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_batch_pdo_batched( $conn, array $posts, $provider, array $model, string $connection_name ): array {
		$table_name  = $model['vector_table_name'];
		$api_options = array(
			'model'   => $model['provider_model_id'],
			'api_key' => $this->get_model_api_key( $model ),
		);

		// Step 1: Collect all texts with metadata for mapping back.
		$texts    = array();
		$metadata = array(); // Maps index to post_id, field_type, chunk_index, source_text.

		foreach ( $posts as $post ) {
			// Skip invalid post entries.
			if ( ! is_array( $post ) || ! isset( $post['post_id'] ) ) {
				continue;
			}

			$title   = $post['post_title_clean'] ?? '';
			$excerpt = $post['post_excerpt_clean'] ?? '';
			$content = $post['post_content_clean'] ?? '';

			// Title.
			if ( ! empty( $title ) ) {
				$idx              = count( $texts );
				$texts[]          = $title;
				$metadata[ $idx ] = array(
					'post_id'     => $post_id,
					'field_type'  => 'title',
					'chunk_index' => null,
					'source_text' => $title,
				);
			}

			// Excerpt.
			if ( ! empty( $excerpt ) ) {
				$idx              = count( $texts );
				$texts[]          = $excerpt;
				$metadata[ $idx ] = array(
					'post_id'     => $post_id,
					'field_type'  => 'excerpt',
					'chunk_index' => null,
					'source_text' => $excerpt,
				);
			}

			// Chunks.
			if ( ! empty( $content ) ) {
				$chunks = $this->chunker->chunk_content(
					$content,
					GG_Data_Chunker::DEFAULT_CHUNK_SIZE,
					$connection_name,
					array(
						'post_id' => $post_id,
					)
				);
				foreach ( $chunks as $chunk_index => $chunk_data ) {
					$chunk_text       = is_array( $chunk_data ) ? $chunk_data['text'] : $chunk_data;
					$idx              = count( $texts );
					$texts[]          = $chunk_text;
					$metadata[ $idx ] = array(
						'post_id'     => $post_id,
						'field_type'  => 'chunk',
						'chunk_index' => $chunk_index,
						'source_text' => $chunk_text,
					);
				}
			}
		}

		if ( empty( $texts ) ) {
			return array(
				'success'      => true,
				'message'      => 'No texts to embed',
				'processed'    => 0,
				'failed'       => 0,
				'total_tokens' => 0,
			);
		}

		$this->logger->log(
			sprintf(
				'API Embeddings: Sending batch of %d texts for %d posts',
				count( $texts ),
				count( $posts )
			),
			'info',
			'vectors',
			$connection_name
		);

		// Step 2: Call batch embedding API.
		$result = $provider->generate_embeddings_batch( $texts, $api_options );

		if ( is_wp_error( $result ) ) {
			$this->logger->log(
				'Batch embedding failed: ' . $result->get_error_message(),
				'error',
				'vectors',
				$connection_name
			);
			return $this->error_response( $result->get_error_message() );
		}

		$vectors      = $result['vectors'];
		$total_tokens = $result['tokens'];

		if ( count( $vectors ) !== count( $texts ) ) {
			return $this->error_response(
				sprintf( 'Vector count mismatch: expected %d, got %d', count( $texts ), count( $vectors ) )
			);
		}

		// Step 3: Delete existing embeddings for all posts in batch.
		$post_ids     = array_unique( array_column( $posts, 'post_id' ) );
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '?' ) );
		$delete_stmt  = $conn->prepare( "DELETE FROM {$table_name} WHERE post_id IN ({$placeholders})" );
		$delete_stmt->execute( $post_ids );

		// Step 4: Insert all embeddings in a transaction.
		$conn->beginTransaction();

		try {
			$insert_sql  = "
				INSERT INTO {$table_name} (
					post_id, field_type, chunk_index, embedding, content_hash, status, generated_at
				) VALUES (
					:post_id, :field_type, :chunk_index, :embedding::vector, :content_hash, 'completed', NOW()
				)
			";
			$insert_stmt = $conn->prepare( $insert_sql );

			foreach ( $vectors as $idx => $vector ) {
				$meta       = $metadata[ $idx ];
				$vector_str = '[' . implode( ',', $vector ) . ']';

				// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
				$insert_stmt->bindValue( ':post_id', $meta['post_id'], \PDO::PARAM_INT );
				$insert_stmt->bindValue( ':field_type', $meta['field_type'], \PDO::PARAM_STR );
				$insert_stmt->bindValue( ':chunk_index', $meta['chunk_index'], null === $meta['chunk_index'] ? \PDO::PARAM_NULL : \PDO::PARAM_INT );
				$insert_stmt->bindValue( ':embedding', $vector_str, \PDO::PARAM_STR );
				$insert_stmt->bindValue( ':content_hash', md5( $meta['source_text'] ), \PDO::PARAM_STR );
				// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
				$insert_stmt->execute();
			}

			$conn->commit();

		} catch ( Exception $e ) {
			$conn->rollback();
			return $this->error_response( 'Insert failed: ' . $e->getMessage() );
		}

		// Update model usage stats.
		$this->model_registry->update_usage(
			$connection_name,
			$model['model_key'],
			$total_tokens
		);

		$this->logger->log(
			sprintf(
				'API Embeddings: Batch completed - %d embeddings for %d posts, %d tokens',
				count( $vectors ),
				count( $posts ),
				$total_tokens
			),
			'info',
			'vectors',
			$connection_name
		);

		return array(
			'success'      => true,
			'message'      => sprintf(
				'Batch processed %d posts, %d embeddings, %d tokens',
				count( $posts ),
				count( $vectors ),
				$total_tokens
			),
			'processed'    => count( $posts ),
			'failed'       => 0,
			'total_tokens' => $total_tokens,
		);
	}

	/**
	 * Generate vectors sequentially (fallback for providers without batch support).
	 *
	 * @param PDO                           $conn            Database connection.
	 * @param array                         $posts           Posts to process.
	 * @param GG_Data_AI_Provider_Interface $provider        AI provider.
	 * @param array                         $model           Model configuration.
	 * @param string                        $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_batch_pdo_sequential( $conn, array $posts, $provider, array $model, string $connection_name ): array {
		$processed    = 0;
		$failed       = 0;
		$total_tokens = 0;

		foreach ( $posts as $post ) {
			// Skip invalid post entries.
			if ( ! is_array( $post ) || ! isset( $post['post_id'] ) ) {
				continue;
			}

			$result = $this->generate_embeddings_for_post_pdo(
				$conn,
				$post,
				$provider,
				$model,
				$connection_name
			);

			if ( $result['success'] ) {
				++$processed;
				$total_tokens += $result['tokens'];
			} else {
				++$failed;
			}
		}

		// Update model usage stats.
		$this->model_registry->update_usage(
			$connection_name,
			$model['model_key'],
			$total_tokens
		);

		return array(
			'success'      => $processed > 0,
			'message'      => sprintf(
				'Processed %d/%d posts, %d tokens',
				$processed,
				count( $posts ),
				$total_tokens
			),
			'processed'    => $processed,
			'failed'       => $failed,
			'total_tokens' => $total_tokens,
		);
	}

	/**
	 * Get posts needing vectors (PDO)
	 *
	 * @param PDO    $conn       Database connection.
	 * @param string $table_name Vector table name.
	 * @param int    $batch_size Batch size.
	 * @return array Posts array.
	 */
	private function get_posts_needing_vectors_pdo( $conn, string $table_name, int $batch_size ): array {
		$sql = "
			SELECT DISTINCT
				pc.post_id,
				pc.post_title_clean,
				pc.post_excerpt_clean,
				pc.post_content_clean
			FROM wp_posts_clean pc
			LEFT JOIN {$table_name} v ON pc.post_id = v.post_id
			WHERE v.post_id IS NULL
			ORDER BY pc.post_id
			LIMIT :batch_size
		";

		$stmt = $conn->prepare( $sql );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO required for PostgreSQL
		$stmt->bindValue( ':batch_size', $batch_size, \PDO::PARAM_INT );
		$stmt->execute();

		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO required for PostgreSQL
		return $stmt->fetchAll( \PDO::FETCH_ASSOC );
	}

	/**
	 * Generate all embeddings for a single post (PDO)
	 *
	 * Creates rows for: title, excerpt, and each content chunk.
	 *
	 * @param PDO                           $conn     Database connection.
	 * @param array                         $post     Post data.
	 * @param GG_Data_AI_Provider_Interface $provider AI provider.
	 * @param array                         $model    Model configuration.
	 * @return array Result with success and tokens.
	 */
	private function generate_embeddings_for_post_pdo( $conn, array $post, $provider, array $model, string $connection_name ): array {
		$post_id = $post['post_id'];
		$title   = $post['post_title_clean'] ?? '';
		$excerpt = $post['post_excerpt_clean'] ?? '';
		$content = $post['post_content_clean'] ?? '';

		$table_name  = $model['vector_table_name'];
		$api_options = array(
			'model'   => $model['provider_model_id'],
			'api_key' => $this->get_model_api_key( $model ),
		);

		$total_tokens = 0;

		try {
			$conn->beginTransaction();

			// Delete existing embeddings for this post.
			$delete_stmt = $conn->prepare( "DELETE FROM {$table_name} WHERE post_id = :post_id" );
			$delete_stmt->execute( array( ':post_id' => $post_id ) );

			// Generate and store title embedding.
			if ( ! empty( $title ) ) {
				$result = $provider->generate_embedding( $title, $api_options );
				if ( ! is_wp_error( $result ) && ! empty( $result['vector'] ) ) {
					$this->insert_embedding_pdo( $conn, $table_name, $post_id, 'title', null, $result['vector'], $title );
					$total_tokens += $result['tokens'] ?? 0;
				}
			}

			// Generate and store excerpt embedding.
			if ( ! empty( $excerpt ) ) {
				$result = $provider->generate_embedding( $excerpt, $api_options );
				if ( ! is_wp_error( $result ) && ! empty( $result['vector'] ) ) {
					$this->insert_embedding_pdo( $conn, $table_name, $post_id, 'excerpt', null, $result['vector'], $excerpt );
					$total_tokens += $result['tokens'] ?? 0;
				}
			}

			// Generate chunks and store each chunk embedding.
			if ( ! empty( $content ) ) {
				$chunks = $this->chunker->chunk_content(
					$content,
					GG_Data_Chunker::DEFAULT_CHUNK_SIZE,
					$connection_name,
					array(
						'post_id' => $post_id,
					)
				);

				foreach ( $chunks as $index => $chunk_data ) {
					$chunk_text = is_array( $chunk_data ) ? $chunk_data['text'] : $chunk_data;
					$result     = $provider->generate_embedding( $chunk_text, $api_options );
					if ( ! is_wp_error( $result ) && ! empty( $result['vector'] ) ) {
						$this->insert_embedding_pdo( $conn, $table_name, $post_id, 'chunk', $index, $result['vector'], $chunk_text );
						$total_tokens += $result['tokens'] ?? 0;
					}
				}
			}

			$conn->commit();

			return array(
				'success' => true,
				'tokens'  => $total_tokens,
			);

		} catch ( Exception $e ) {
			try {
				$conn->rollback();
			} catch ( Exception $rollback_e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'API Embeddings rollback failed: ' . $rollback_e->getMessage() );
			}

			$this->logger->log(
				sprintf( 'Embedding generation failed for post %d: %s', $post_id, $e->getMessage() ),
				'error',
				'vectors',
				null,
				array( 'post_id' => $post_id )
			);

			return array(
				'success' => false,
				'tokens'  => $total_tokens,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Insert a single embedding row (PDO)
	 *
	 * @param PDO      $conn        Database connection.
	 * @param string   $table_name  Vector table name.
	 * @param int      $post_id     Post ID.
	 * @param string   $field_type  Field type: 'title', 'excerpt', 'chunk'.
	 * @param int|null $chunk_index Chunk index (NULL for title/excerpt).
	 * @param array    $vector      Embedding vector.
	 * @param string   $source_text Original text (for content_hash).
	 */
	private function insert_embedding_pdo( $conn, string $table_name, int $post_id, string $field_type, $chunk_index, array $vector, string $source_text ): void {
		$content_hash = md5( $source_text );
		$vector_str   = '[' . implode( ',', $vector ) . ']';

		$sql = "
			INSERT INTO {$table_name} (
				post_id,
				field_type,
				chunk_index,
				embedding,
				content_hash,
				status,
				generated_at
			) VALUES (
				:post_id,
				:field_type,
				:chunk_index,
				:embedding::vector,
				:content_hash,
				'completed',
				NOW()
			)
			ON CONFLICT (post_id, field_type, chunk_index) DO UPDATE SET
				embedding = EXCLUDED.embedding,
				content_hash = EXCLUDED.content_hash,
				status = 'completed',
				generated_at = NOW()
		";

		$stmt = $conn->prepare( $sql );
		// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- PDO required for PostgreSQL
		$stmt->bindValue( ':post_id', $post_id, \PDO::PARAM_INT );
		$stmt->bindValue( ':field_type', $field_type, \PDO::PARAM_STR );
		$stmt->bindValue( ':chunk_index', $chunk_index, null === $chunk_index ? \PDO::PARAM_NULL : \PDO::PARAM_INT );
		$stmt->bindValue( ':embedding', $vector_str, \PDO::PARAM_STR );
		$stmt->bindValue( ':content_hash', $content_hash, \PDO::PARAM_STR );
		// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
		$stmt->execute();
	}

	/**
	 * Generate vectors batch via Supabase (REST API)
	 *
	 * Uses true batch embedding API calls when provider supports it.
	 *
	 * @param array  $model           Model configuration.
	 * @param int    $batch_size      Batch size.
	 * @param string $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_batch_supabase( array $model, int $batch_size, string $connection_name ): array {
		try {
			$config = $this->settings->get_connection( $connection_name );

			if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
				require_once dirname( __DIR__, 2 ) . '/providers/class-gg-postgrest-provider.php';
			}

			$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
			if ( isset( $runtime_config['error'] ) ) {
				return $this->error_response( 'Missing Supabase connection details' );
			}

			$project_url = $runtime_config['project_url'];

			// Get posts needing vectors via RPC.
			// Extract model name from vector_table_name (remove wp_posts_ prefix).
			$model_name = preg_replace( '/^wp_posts_/', '', $model['vector_table_name'] );

			$rpc_url  = $project_url . '/rest/v1/rpc/get_posts_needing_vectors';
			$payload  = array(
				'target_model' => $model_name,
				'batch_size'   => $batch_size,
			);
			$response = wp_safe_remote_post(
				$rpc_url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'body'    => wp_json_encode( $payload ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $this->error_response( 'RPC error: ' . $response->get_error_message() );
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );

			// Check for HTTP errors.
			if ( $status_code >= 400 ) {
				return $this->error_response( 'RPC failed (HTTP ' . $status_code . '): ' . $body );
			}

			$posts = json_decode( $body, true );

			// Validate that $posts is an array (not a string or error).
			if ( ! is_array( $posts ) ) {
				return $this->error_response( 'Invalid RPC response: expected array, got ' . gettype( $posts ) );
			}

			if ( empty( $posts ) ) {
				return array(
					'success'      => true,
					'message'      => 'No posts need vectors',
					'processed'    => 0,
					'failed'       => 0,
					'total_tokens' => 0,
				);
			}

			// Get AI provider.
			$provider = GG_Data_LLM_Registry::get_provider( $model['provider'] );
			if ( is_wp_error( $provider ) ) {
				return $this->error_response( 'Provider not found: ' . $model['provider'] );
			}

			// Check if provider supports batch embeddings.
			$supports_batch = method_exists( $provider, 'generate_embeddings_batch' );

			if ( $supports_batch ) {
				return $this->generate_batch_supabase_batched( $project_url, $runtime_config, $posts, $provider, $model, $connection_name );
			}

			// Fallback to sequential processing.
			return $this->generate_batch_supabase_sequential( $project_url, $runtime_config, $posts, $provider, $model, $connection_name );

		} catch ( Exception $e ) {
			return $this->error_response( 'Generation error: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate vectors using true batch API calls (Supabase).
	 *
	 * @param string                        $project_url     Supabase project URL.
	 * @param array                         $runtime_config  Supabase runtime config.
	 * @param array                         $posts           Posts to process.
	 * @param GG_Data_AI_Provider_Interface $provider        AI provider.
	 * @param array                         $model           Model configuration.
	 * @param string                        $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_batch_supabase_batched( string $project_url, array $runtime_config, array $posts, $provider, array $model, string $connection_name ): array {
		$table_name  = $model['vector_table_name'];
		$api_options = array(
			'model'   => $model['provider_model_id'],
			'api_key' => $this->get_model_api_key( $model ),
		);

		// Step 1: Collect all texts with metadata.
		$texts    = array();
		$metadata = array();

		foreach ( $posts as $post ) {
			// Skip invalid post entries.
			if ( ! is_array( $post ) || ! isset( $post['post_id'] ) ) {
				continue;
			}

			$post_id = $post['post_id'];
			$title   = $post['post_title_clean'] ?? '';
			$excerpt = $post['post_excerpt_clean'] ?? '';
			$content = $post['post_content_clean'] ?? '';

			if ( ! empty( $title ) ) {
				$idx              = count( $texts );
				$texts[]          = $title;
				$metadata[ $idx ] = array(
					'post_id'     => $post_id,
					'field_type'  => 'title',
					'chunk_index' => null,
					'source_text' => $title,
				);
			}

			if ( ! empty( $excerpt ) ) {
				$idx              = count( $texts );
				$texts[]          = $excerpt;
				$metadata[ $idx ] = array(
					'post_id'     => $post_id,
					'field_type'  => 'excerpt',
					'chunk_index' => null,
					'source_text' => $excerpt,
				);
			}

			if ( ! empty( $content ) ) {
				$chunks = $this->chunker->chunk_content(
					$content,
					GG_Data_Chunker::DEFAULT_CHUNK_SIZE,
					$connection_name,
					array(
						'post_id' => $post_id,
					)
				);
				foreach ( $chunks as $chunk_index => $chunk_data ) {
					$chunk_text       = is_array( $chunk_data ) ? $chunk_data['text'] : $chunk_data;
					$idx              = count( $texts );
					$texts[]          = $chunk_text;
					$metadata[ $idx ] = array(
						'post_id'     => $post_id,
						'field_type'  => 'chunk',
						'chunk_index' => $chunk_index,
						'source_text' => $chunk_text,
					);
				}
			}
		}

		if ( empty( $texts ) ) {
			return array(
				'success'      => true,
				'message'      => 'No texts to embed',
				'processed'    => 0,
				'failed'       => 0,
				'total_tokens' => 0,
			);
		}

		$this->logger->log(
			sprintf( 'API Embeddings (Supabase): Sending batch of %d texts for %d posts', count( $texts ), count( $posts ) ),
			'info',
			'vectors',
			$connection_name
		);

		// Step 2: Call batch embedding API.
		$result = $provider->generate_embeddings_batch( $texts, $api_options );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message() );
		}

		$vectors      = $result['vectors'];
		$total_tokens = $result['tokens'];

		if ( count( $vectors ) !== count( $texts ) ) {
			return $this->error_response(
				sprintf( 'Vector count mismatch: expected %d, got %d', count( $texts ), count( $vectors ) )
			);
		}

		// Step 3: Delete existing embeddings for all posts.
		$post_ids = array_unique( array_column( $posts, 'post_id' ) );
		foreach ( $post_ids as $post_id ) {
			$delete_url = $project_url . '/rest/v1/' . $table_name . '?post_id=eq.' . $post_id;
			wp_remote_request(
				$delete_url,
				array(
					'method'  => 'DELETE',
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'timeout' => 15,
				)
			);
		}

		// Step 4: Build embeddings array for batch insert.
		$embeddings = array();
		foreach ( $vectors as $idx => $vector ) {
			$meta         = $metadata[ $idx ];
			$embeddings[] = array(
				'post_id'      => $meta['post_id'],
				'field_type'   => $meta['field_type'],
				'chunk_index'  => $meta['chunk_index'],
				'embedding'    => $vector,
				'content_hash' => md5( $meta['source_text'] ),
				'status'       => 'completed',
				'generated_at' => gmdate( 'Y-m-d H:i:s' ),
			);
		}

		// Step 5: Batch insert via Supabase REST API.
		$insert_url      = $project_url . '/rest/v1/' . $table_name;
		$insert_response = wp_safe_remote_post(
			$insert_url,
			array(
				'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
					'Prefer' => 'return=minimal',
				),
				'body'    => wp_json_encode( $embeddings ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $insert_response ) ) {
			return $this->error_response( 'Insert failed: ' . $insert_response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $insert_response );
		if ( $status_code >= 400 ) {
			$body = wp_remote_retrieve_body( $insert_response );
			return $this->error_response( 'Insert failed (HTTP ' . $status_code . '): ' . $body );
		}

		// Update model usage stats.
		$this->model_registry->update_usage(
			$connection_name,
			$model['model_key'],
			$total_tokens
		);

		$this->logger->log(
			sprintf(
				'API Embeddings (Supabase): Batch completed - %d embeddings for %d posts, %d tokens',
				count( $vectors ),
				count( $posts ),
				$total_tokens
			),
			'info',
			'vectors',
			$connection_name
		);

		return array(
			'success'      => true,
			'message'      => sprintf(
				'Batch processed %d posts, %d embeddings, %d tokens',
				count( $posts ),
				count( $vectors ),
				$total_tokens
			),
			'processed'    => count( $posts ),
			'failed'       => 0,
			'total_tokens' => $total_tokens,
		);
	}

	/**
	 * Generate vectors sequentially (Supabase fallback).
	 *
	 * @param string                        $project_url     Supabase project URL.
	 * @param array                         $runtime_config  Supabase runtime config.
	 * @param array                         $posts           Posts to process.
	 * @param GG_Data_AI_Provider_Interface $provider        AI provider.
	 * @param array                         $model           Model configuration.
	 * @param string                        $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_batch_supabase_sequential( string $project_url, array $runtime_config, array $posts, $provider, array $model, string $connection_name ): array {
		$processed    = 0;
		$failed       = 0;
		$total_tokens = 0;

		foreach ( $posts as $post ) {
			// Skip invalid post entries.
			if ( ! is_array( $post ) || ! isset( $post['post_id'] ) ) {
				continue;
			}

			$result = $this->generate_embeddings_for_post_supabase(
				$project_url,
				$runtime_config,
				$post,
				$provider,
				$model,
				$connection_name
			);

			if ( $result['success'] ) {
				++$processed;
				$total_tokens += $result['tokens'];
			} else {
				++$failed;
			}
		}

		// Update model usage stats.
		$this->model_registry->update_usage(
			$connection_name,
			$model['model_key'],
			$total_tokens
		);

		return array(
			'success'      => $processed > 0,
			'message'      => sprintf(
				'Processed %d/%d posts, %d tokens',
				$processed,
				count( $posts ),
				$total_tokens
			),
			'processed'    => $processed,
			'failed'       => $failed,
			'total_tokens' => $total_tokens,
		);
	}

	/**
	 * Generate all embeddings for a single post (Supabase)
	 *
	 * @param string                        $project_url Supabase project URL.
	 * @param array                         $runtime_config Supabase runtime config.
	 * @param array                         $post        Post data.
	 * @param GG_Data_AI_Provider_Interface $provider    AI provider.
	 * @param array                         $model       Model configuration.
	 * @param string                        $connection_name Connection name.
	 * @return array Result.
	 */
	private function generate_embeddings_for_post_supabase( string $project_url, array $runtime_config, array $post, $provider, array $model, string $connection_name ): array {
		$post_id = $post['post_id'];
		$title   = $post['post_title_clean'] ?? '';
		$excerpt = $post['post_excerpt_clean'] ?? '';
		$content = $post['post_content_clean'] ?? '';

		$table_name  = $model['vector_table_name'];
		$api_options = array(
			'model'   => $model['provider_model_id'],
			'api_key' => $this->get_model_api_key( $model ),
		);

		$total_tokens = 0;
		$embeddings   = array();

		try {
			// Delete existing embeddings for this post.
			$delete_url = $project_url . '/rest/v1/' . $table_name . '?post_id=eq.' . $post_id;
			wp_remote_request(
				$delete_url,
				array(
					'method'  => 'DELETE',
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'timeout' => 15,
				)
			);

			// Generate title embedding.
			if ( ! empty( $title ) ) {
				$result = $provider->generate_embedding( $title, $api_options );
				if ( ! is_wp_error( $result ) && ! empty( $result['vector'] ) ) {
					$embeddings[]  = array(
						'post_id'      => $post_id,
						'field_type'   => 'title',
						'chunk_index'  => null,
						'embedding'    => $result['vector'],
						'content_hash' => md5( $title ),
						'status'       => 'completed',
						'generated_at' => gmdate( 'Y-m-d H:i:s' ),
					);
					$total_tokens += $result['tokens'] ?? 0;
				}
			}

			// Generate excerpt embedding.
			if ( ! empty( $excerpt ) ) {
				$result = $provider->generate_embedding( $excerpt, $api_options );
				if ( ! is_wp_error( $result ) && ! empty( $result['vector'] ) ) {
					$embeddings[]  = array(
						'post_id'      => $post_id,
						'field_type'   => 'excerpt',
						'chunk_index'  => null,
						'embedding'    => $result['vector'],
						'content_hash' => md5( $excerpt ),
						'status'       => 'completed',
						'generated_at' => gmdate( 'Y-m-d H:i:s' ),
					);
					$total_tokens += $result['tokens'] ?? 0;
				}
			}

			// Generate chunk embeddings.
			if ( ! empty( $content ) ) {
				$chunks = $this->chunker->chunk_content(
					$content,
					GG_Data_Chunker::DEFAULT_CHUNK_SIZE,
					$connection_name,
					array(
						'post_id' => $post_id,
					)
				);

				foreach ( $chunks as $index => $chunk_data ) {
					$chunk_text = is_array( $chunk_data ) ? $chunk_data['text'] : $chunk_data;
					$result     = $provider->generate_embedding( $chunk_text, $api_options );
					if ( ! is_wp_error( $result ) && ! empty( $result['vector'] ) ) {
						$embeddings[]  = array(
							'post_id'      => $post_id,
							'field_type'   => 'chunk',
							'chunk_index'  => $index,
							'embedding'    => $result['vector'],
							'content_hash' => md5( $chunk_text ),
							'status'       => 'completed',
							'generated_at' => gmdate( 'Y-m-d H:i:s' ),
						);
						$total_tokens += $result['tokens'] ?? 0;
					}
				}
			}

			// Batch insert all embeddings.
			if ( ! empty( $embeddings ) ) {
				$insert_url      = $project_url . '/rest/v1/' . $table_name;
				$insert_response = wp_safe_remote_post(
					$insert_url,
					array(
						'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
							'Prefer' => 'return=minimal',
						),
						'body'    => wp_json_encode( $embeddings ),
						'timeout' => 60,
					)
				);

				if ( is_wp_error( $insert_response ) ) {
					return array(
						'success' => false,
						'tokens'  => $total_tokens,
						'message' => $insert_response->get_error_message(),
					);
				}

				$status_code = wp_remote_retrieve_response_code( $insert_response );
				if ( $status_code < 200 || $status_code >= 300 ) {
					return array(
						'success' => false,
						'tokens'  => $total_tokens,
						'message' => 'Insert failed with status ' . $status_code,
					);
				}
			}

			return array(
				'success' => true,
				'tokens'  => $total_tokens,
			);

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Supabase embedding generation failed for post %d: %s', $post_id, $e->getMessage() ),
				'error',
				'vectors',
				null,
				array( 'post_id' => $post_id )
			);

			return array(
				'success' => false,
				'tokens'  => $total_tokens,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Create error response
	 *
	 * @param string $message Error message.
	 * @return array Error response.
	 */
	private function error_response( string $message ): array {
		return array(
			'success'      => false,
			'message'      => $message,
			'processed'    => 0,
			'failed'       => 0,
			'total_tokens' => 0,
		);
	}
}
