<?php
/**
 * TF-IDF 300-Dimensional Embeddings Generator v2.0
 *
 * Row-per-embedding architecture with chunk support.
 * Generates semantic vectors using PostgreSQL native functions.
 * No external dependencies, completely free implementation.
 *
 * Schema: wp_posts_tfidf_300 with (post_id, field_type, chunk_index, embedding)
 * - field_type: 'title', 'excerpt', 'chunk'
 * - chunk_index: NULL for title/excerpt, 0-N for chunks
 *
 * @package Gregius_Data
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TF-IDF 300-Dimensional Embeddings Generator
 */
class GG_Data_TFIDF_300_Embeddings {
	/**
	 * Get the active WordPress table prefix.
	 *
	 * @return string
	 */
	private function get_table_prefix() {
		return GG_Data_Table_Prefix_Resolver::runtime_prefix();
	}

	/**
	 * Vector dimensions
	 */
	const DIMENSIONS = 300;

	/**
	 * Database connection handler
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
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Connection name
	 *
	 * @var string|null
	 */
	private $connection_name;

	/**
	 * Constructor
	 *
	 * @param string|null $connection_name PostgreSQL connection name.
	 */
	public function __construct( $connection_name = null ) {
		$this->db       = new GG_Data_DB();
		$this->settings = new GG_Data_Settings_Manager();
		$this->chunker  = new GG_Data_Chunker();
		$this->logger   = new GG_Data_Logger();

		// Auto-detect connection if not provided.
		if ( empty( $connection_name ) ) {
			$connections = $this->settings->get_all_connections();
			if ( ! empty( $connections ) ) {
				$connection_name = array_key_first( $connections );
			}
		}

		$this->connection_name = $connection_name;
	}

	/**
	 * Generate vectors for a batch of posts
	 *
	 * Routes to PDO or Supabase implementation based on provider type.
	 *
	 * @param string $connection_name Connection name.
	 * @param int    $batch_size      Batch size.
	 * @return array Status information.
	 */
	public function generate_all_vectors( $connection_name = 'default', $batch_size = 100 ) {
		$config        = $this->settings->get_connection( $connection_name );
		$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
			return $this->generate_vectors_batch_supabase( $connection_name, $batch_size );
		}

		return $this->generate_vectors_batch_pdo( $connection_name, $batch_size );
	}

	/**
	 * Generate vectors batch for PDO (direct PostgreSQL)
	 *
	 * Row-per-embedding architecture:
	 * - 1 row for title (field_type='title')
	 * - 1 row for excerpt (field_type='excerpt')
	 * - N rows for chunks (field_type='chunk', chunk_index=0,1,2...)
	 *
	 * @param string $connection_name Connection name.
	 * @param int    $batch_size      Batch size.
	 * @return array Status information.
	 */
	private function generate_vectors_batch_pdo( $connection_name, $batch_size = 100 ) {
		$connection = $this->db->get_connection( $connection_name );
		if ( ! $connection ) {
			return array(
				'success' => false,
				'message' => 'Database connection failed',
			);
		}

		try {
			// Get cached vocabulary.
			$table_prefix       = $this->get_table_prefix();
			$vocabulary_manager = new GG_Data_Vocabulary_Manager();
			$vocab              = $vocabulary_manager->get_cached_vocabulary( $connection_name );

			if ( empty( $vocab ) ) {
				return array(
					'success' => false,
					'message' => 'Vocabulary not prepared. Click "Prepare Vocabulary" before generating vectors.',
				);
			}

			// Validate vocabulary isn't severely stale.
			$status = $vocabulary_manager->validate_vocabulary_status( $connection_name );
			if ( isset( $status['status'] ) && 'error' === $status['status'] ) {
				return array(
					'success' => false,
					'message' => sprintf(
						'Critical vocabulary drift (%.1f%%). Regenerate vocabulary before generating vectors.',
						$status['drift_percentage']
					),
				);
			}

			$vector_table = $table_prefix . 'posts_tfidf_300';

			// Get posts needing vectors (those without any embeddings in wp_posts_tfidf_300).
			$posts_query = '
				SELECT DISTINCT p.post_id, p.post_title_clean, p.post_excerpt_clean, p.post_content_clean
				FROM public.' . $table_prefix . 'posts_clean p
				LEFT JOIN public.' . $vector_table . ' v ON p.post_id = v.post_id
				WHERE v.post_id IS NULL
				ORDER BY p.post_id
				LIMIT :batch_size
			';

			$stmt = $connection->prepare( $posts_query );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$stmt->bindValue( ':batch_size', $batch_size, PDO::PARAM_INT );
			$stmt->execute();
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$posts = $stmt->fetchAll( PDO::FETCH_ASSOC );

			if ( empty( $posts ) ) {
				return array(
					'success'   => true,
					'processed' => 0,
					'message'   => 'All posts have vectors',
				);
			}

			// Collect post IDs for batch chunk fetch.
			$post_ids = array_column( $posts, 'post_id' );

			// STEP 2: Fetch ALL chunks for these posts in one query (mirrors PostgREST path).
			$chunks_by_post = array();
			$placeholders   = implode( ',', array_fill( 0, count( $post_ids ), '?' ) );
			$chunks_query   = "
				SELECT post_id, chunk_index, chunk_text, chunk_hash
				FROM public.{$table_prefix}posts_chunks
				WHERE post_id IN ( $placeholders )
				ORDER BY post_id ASC, chunk_index ASC
			";
			$chunks_stmt    = $connection->prepare( $chunks_query );
			$chunks_stmt->execute( $post_ids );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$all_chunks = $chunks_stmt->fetchAll( PDO::FETCH_ASSOC );

			foreach ( $all_chunks as $chunk ) {
				$pid = $chunk['post_id'];
				if ( ! isset( $chunks_by_post[ $pid ] ) ) {
					$chunks_by_post[ $pid ] = array();
				}
				$chunks_by_post[ $pid ][] = $chunk;
			}

			$processed = 0;
			$failed    = 0;

			foreach ( $posts as $post ) {
				$result = $this->generate_embeddings_for_post_pdo(
					$connection,
					$post,
					$vocab,
					$chunks_by_post
				);

				if ( $result['success'] ) {
					++$processed;
				} else {
					++$failed;
				}
			}

			return array(
				'success'   => true,
				'processed' => $processed,
				'failed'    => $failed,
				'message'   => sprintf( 'Processed %d posts (%d failed)', $processed, $failed ),
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Generate all embeddings for a single post (PDO)
	 *
	 * Creates rows for: title, excerpt, and each content chunk.
	 *
	 * @param PDO   $connection Database connection.
	 * @param array $post       Post data with post_id, post_title_clean, etc.
	 * @param array $vocab      Vocabulary with IDF scores.
	 * @param array $chunks_by_post Pre-fetched chunks indexed by post_id.
	 * @return array Result with success status.
	 */
	private function generate_embeddings_for_post_pdo( $connection, $post, $vocab, $chunks_by_post = array() ) {
		try {
			$connection->beginTransaction();

			$post_id = $post['post_id'];
			$title   = $post['post_title_clean'] ?? '';
			$excerpt = $post['post_excerpt_clean'] ?? '';

			// Get vocabulary version for tracking.
			$vocab_version = isset( $vocab['_version'] ) ? (int) $vocab['_version'] : 1;

			$table_prefix = $this->get_table_prefix();
			$vector_table = $table_prefix . 'posts_tfidf_300';

			// Delete any existing embeddings for this post.
			$delete_stmt = $connection->prepare(
				'DELETE FROM public.' . $vector_table . ' WHERE post_id = :post_id'
			);
			$delete_stmt->execute( array( ':post_id' => $post_id ) );

			// Generate and store title embedding.
			if ( ! empty( $title ) ) {
				$title_vector = $this->generate_tfidf_vector( $title, $vocab, 'title' );
				$this->insert_embedding_pdo( $connection, $post_id, 'title', null, $title_vector, $title, $vocab_version );
			}

			// Generate and store excerpt embedding.
			if ( ! empty( $excerpt ) ) {
				$excerpt_vector = $this->generate_tfidf_vector( $excerpt, $vocab, 'excerpt' );
				$this->insert_embedding_pdo( $connection, $post_id, 'excerpt', null, $excerpt_vector, $excerpt, $vocab_version );
			}

			// Chunk embeddings from pre-fetched wp_posts_chunks rows (mirrors PostgREST path).
			if ( isset( $chunks_by_post[ $post_id ] ) ) {
				foreach ( $chunks_by_post[ $post_id ] as $chunk ) {
					$chunk_text  = $chunk['chunk_text'] ?? '';
					$chunk_index = $chunk['chunk_index'] ?? 0;
					$chunk_hash  = $chunk['chunk_hash'] ?? md5( $chunk_text );

					if ( ! empty( $chunk_text ) ) {
						$chunk_vector = $this->generate_tfidf_vector( $chunk_text, $vocab, 'chunk' );
						$this->insert_embedding_pdo( $connection, $post_id, 'chunk', $chunk_index, $chunk_vector, $chunk_text, $vocab_version );
					}
				}
			}

			$connection->commit();

			return array( 'success' => true );

		} catch ( Exception $e ) {
			try {
				$connection->rollback();
			} catch ( Exception $rollback_e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'TF-IDF rollback failed: ' . $rollback_e->getMessage() );
			}

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Insert a single embedding row (PDO)
	 *
	 * @param PDO      $connection        Database connection.
	 * @param int      $post_id           Post ID.
	 * @param string   $field_type        Field type: 'title', 'excerpt', 'chunk'.
	 * @param int|null $chunk_index       Chunk index (NULL for title/excerpt).
	 * @param array    $vector            Embedding vector.
	 * @param string   $source_text       Original text (for content_hash).
	 * @param int      $vocabulary_version Vocabulary version.
	 */
	private function insert_embedding_pdo( $connection, $post_id, $field_type, $chunk_index, $vector, $source_text, $vocabulary_version = 1 ) {
		$content_hash = md5( $source_text );
		$vector_str   = '[' . implode( ',', $vector ) . ']';
		$vector_table = $this->get_table_prefix() . 'posts_tfidf_300';

		$insert_sql = "
			INSERT INTO public.{$vector_table} (
				post_id,
				field_type,
				chunk_index,
				embedding,
				content_hash,
				status,
				generated_at,
				vocabulary_version
			) VALUES (
				:post_id,
				:field_type,
				:chunk_index,
				:embedding::vector,
				:content_hash,
				'completed',
				NOW(),
				:vocabulary_version
			)
			ON CONFLICT (post_id, field_type, chunk_index) DO UPDATE SET
				embedding = EXCLUDED.embedding,
				content_hash = EXCLUDED.content_hash,
				status = 'completed',
				generated_at = NOW(),
				vocabulary_version = EXCLUDED.vocabulary_version
		";

		$stmt = $connection->prepare( $insert_sql );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':post_id', $post_id, PDO::PARAM_INT );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':field_type', $field_type, PDO::PARAM_STR );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':chunk_index', $chunk_index, null === $chunk_index ? PDO::PARAM_NULL : PDO::PARAM_INT );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':embedding', $vector_str, PDO::PARAM_STR );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':content_hash', $content_hash, PDO::PARAM_STR );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':vocabulary_version', $vocabulary_version, PDO::PARAM_INT );
		$stmt->execute();
	}

	/**
	 * Generate vectors batch for Supabase (REST API)
	 *
	 * TRUE BATCH PROCESSING:
	 * 1. Fetch posts needing vectors (1 RPC call)
	 * 2. Fetch ALL chunks for those posts (1 REST call)
	 * 3. Delete existing vectors for batch (1 DELETE call)
	 * 4. Generate all vectors locally (CPU only)
	 * 5. Bulk insert all vectors (1 INSERT call)
	 *
	 * Total: 4 HTTP requests per batch (vs 3 per post before)
	 *
	 * @param string $connection_name Connection name.
	 * @param int    $batch_size      Batch size.
	 * @return array Status information.
	 */
	private function generate_vectors_batch_supabase( $connection_name, $batch_size = 10 ) {
		try {
			$config = $this->settings->get_connection( $connection_name );

			if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
				require_once dirname( __DIR__, 1 ) . '/providers/class-gg-postgrest-provider.php';
			}

			$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
			if ( isset( $runtime_config['error'] ) ) {
				return array(
					'success' => false,
					'message' => $runtime_config['error'],
				);
			}

			$project_url = $runtime_config['project_url'];

			// Get cached vocabulary.
			$vocabulary_manager = new GG_Data_Vocabulary_Manager();
			$vocab              = $vocabulary_manager->get_cached_vocabulary( $connection_name );

			if ( empty( $vocab ) ) {
				return array(
					'success' => false,
					'message' => 'Vocabulary not prepared. Click "Prepare Vocabulary" first.',
				);
			}

			$vocab_version = isset( $vocab['_version'] ) ? (int) $vocab['_version'] : 1;

			// STEP 1: Get posts needing vectors (1 RPC call).
			$rpc_url  = $project_url . '/rest/v1/rpc/get_posts_needing_vectors';
			$payload  = array(
				'target_model' => 'tfidf_300',
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
				return array(
					'success' => false,
					'message' => 'RPC error: ' . $response->get_error_message(),
				);
			}

			$body  = wp_remote_retrieve_body( $response );
			$posts = json_decode( $body, true );

			if ( empty( $posts ) ) {
				return array(
					'success'   => true,
					'processed' => 0,
					'message'   => 'All posts have vectors',
				);
			}

			// Collect post IDs for batch operations.
			$post_ids = array_column( $posts, 'post_id' );
			$post_map = array();
			foreach ( $posts as $post ) {
				$post_map[ $post['post_id'] ] = $post;
			}

			// STEP 2: Fetch ALL chunks for these posts (1 REST call).
			$chunks_url      = $project_url . '/rest/v1/wp_posts_chunks?post_id=in.(' . implode( ',', $post_ids ) . ')&order=post_id.asc,chunk_index.asc';
			$chunks_response = wp_safe_remote_get(
				$chunks_url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'timeout' => 60,
				)
			);

			$chunks_by_post = array();
			if ( ! is_wp_error( $chunks_response ) ) {
				$chunks_body = wp_remote_retrieve_body( $chunks_response );
				$all_chunks  = json_decode( $chunks_body, true );

				if ( ! empty( $all_chunks ) && is_array( $all_chunks ) ) {
					foreach ( $all_chunks as $chunk ) {
						$pid = $chunk['post_id'];
						if ( ! isset( $chunks_by_post[ $pid ] ) ) {
							$chunks_by_post[ $pid ] = array();
						}
						$chunks_by_post[ $pid ][] = $chunk;
					}
				}
			}

			// NOTE: No DELETE here - RPC already returns only posts without vectors.
			// DELETE is only used in "Regenerate All Vectors" which truncates the table.

			// STEP 3: Generate ALL vectors locally (CPU only, no HTTP).
			$all_embeddings = array();
			$processed      = 0;

			foreach ( $posts as $post ) {
				$post_id = $post['post_id'];
				$title   = $post['post_title_clean'] ?? '';
				$excerpt = $post['post_excerpt_clean'] ?? '';

				// Title embedding.
				if ( ! empty( $title ) ) {
					$title_vector     = $this->generate_tfidf_vector( $title, $vocab, 'title' );
					$all_embeddings[] = array(
						'post_id'            => $post_id,
						'field_type'         => 'title',
						'chunk_index'        => null,
						'embedding'          => '[' . implode( ',', $title_vector ) . ']',
						'content_hash'       => md5( $title ),
						'status'             => 'completed',
						'generated_at'       => gmdate( 'Y-m-d H:i:s' ),
						'vocabulary_version' => $vocab_version,
					);
				}

				// Excerpt embedding.
				if ( ! empty( $excerpt ) ) {
					$excerpt_vector   = $this->generate_tfidf_vector( $excerpt, $vocab, 'excerpt' );
					$all_embeddings[] = array(
						'post_id'            => $post_id,
						'field_type'         => 'excerpt',
						'chunk_index'        => null,
						'embedding'          => '[' . implode( ',', $excerpt_vector ) . ']',
						'content_hash'       => md5( $excerpt ),
						'status'             => 'completed',
						'generated_at'       => gmdate( 'Y-m-d H:i:s' ),
						'vocabulary_version' => $vocab_version,
					);
				}

				// Chunk embeddings from pre-fetched chunks.
				if ( isset( $chunks_by_post[ $post_id ] ) ) {
					foreach ( $chunks_by_post[ $post_id ] as $chunk ) {
						$chunk_text  = $chunk['chunk_text'] ?? '';
						$chunk_index = $chunk['chunk_index'] ?? 0;
						$chunk_hash  = $chunk['chunk_hash'] ?? md5( $chunk_text );

						if ( ! empty( $chunk_text ) ) {
							$chunk_vector     = $this->generate_tfidf_vector( $chunk_text, $vocab, 'chunk' );
							$all_embeddings[] = array(
								'post_id'            => $post_id,
								'field_type'         => 'chunk',
								'chunk_index'        => $chunk_index,
								'embedding'          => '[' . implode( ',', $chunk_vector ) . ']',
								'content_hash'       => $chunk_hash,
								'status'             => 'completed',
								'generated_at'       => gmdate( 'Y-m-d H:i:s' ),
								'vocabulary_version' => $vocab_version,
							);
						}
					}
				}

				++$processed;
			}

			// STEP 5: Bulk insert ALL embeddings (1 INSERT call).
			if ( ! empty( $all_embeddings ) ) {
				$insert_url      = $project_url . '/rest/v1/wp_posts_tfidf_300';
				$insert_response = wp_safe_remote_post(
					$insert_url,
					array(
						'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
							'Prefer' => 'return=minimal',
						),
						'body'    => wp_json_encode( $all_embeddings ),
						'timeout' => 120,
					)
				);

				if ( is_wp_error( $insert_response ) ) {
					return array(
						'success' => false,
						'message' => 'Bulk insert error: ' . $insert_response->get_error_message(),
					);
				}

				$status_code = wp_remote_retrieve_response_code( $insert_response );
				if ( $status_code < 200 || $status_code >= 300 ) {
					$error_body = wp_remote_retrieve_body( $insert_response );
					return array(
						'success' => false,
						'message' => 'Bulk insert failed (HTTP ' . $status_code . '): ' . $error_body,
					);
				}
			}

			return array(
				'success'    => true,
				'processed'  => $processed,
				'embeddings' => count( $all_embeddings ),
				'message'    => sprintf( 'Processed %d posts, created %d embeddings', $processed, count( $all_embeddings ) ),
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Build global vocabulary from all posts
	 *
	 * Called by Vocabulary Manager during vocabulary preparation.
	 *
	 * @param PDO|null $connection Database connection (null for Supabase).
	 * @param array    $posts      Array of posts with post_title, post_content, post_excerpt.
	 * @return array Vocabulary with term => IDF scores.
	 */
	public function build_global_vocabulary( $connection, $posts ) {
		if ( empty( $posts ) ) {
			return array();
		}

		try {
			// Build document frequency for all terms.
			$vocabulary = array();
			$total_docs = count( $posts );

			foreach ( $posts as $post ) {
				// Combine all text fields.
				$text = implode(
					' ',
					array(
						$post['post_title'] ?? '',
						$post['post_excerpt'] ?? '',
						$post['post_content'] ?? '',
					)
				);

				// Get unique terms for this document.
				$terms        = $this->extract_terms( $text );
				$unique_terms = array_unique( $terms );

				// Increment document frequency for each unique term.
				foreach ( $unique_terms as $term ) {
					if ( ! isset( $vocabulary[ $term ] ) ) {
						$vocabulary[ $term ] = 0;
					}
					++$vocabulary[ $term ];
				}
			}

			// Calculate IDF and filter vocabulary.
			$filtered_vocabulary = array();

			if ( $total_docs <= 5 ) {
				// With few documents, use all terms.
				foreach ( $vocabulary as $term => $doc_freq ) {
					$idf                          = log( max( 2, $total_docs ) / max( 1, $doc_freq ) );
					$filtered_vocabulary[ $term ] = $idf;
				}
			} else {
				// Normal filtering.
				$min_docs = max( 1, intval( $total_docs * 0.02 ) );
				$max_docs = intval( $total_docs * 0.8 );

				foreach ( $vocabulary as $term => $doc_freq ) {
					if ( $doc_freq >= $min_docs && $doc_freq <= $max_docs ) {
						$idf                          = log( $total_docs / $doc_freq );
						$filtered_vocabulary[ $term ] = $idf;
					}
				}
			}

			// Sort by IDF and take top terms.
			arsort( $filtered_vocabulary );
			return array_slice( $filtered_vocabulary, 0, self::DIMENSIONS, true );

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Generate TF-IDF vector for text
	 *
	 * @param string $text       Input text.
	 * @param array  $vocabulary Vocabulary with IDF scores.
	 * @param string $field_type Field type for weighting ('title', 'excerpt', 'chunk').
	 * @return array Vector array of floats.
	 */
	private function generate_tfidf_vector( $text, $vocabulary, $field_type = 'chunk' ) {
		if ( empty( $text ) || empty( $vocabulary ) ) {
			return array_fill( 0, self::DIMENSIONS, 0.0 );
		}

		// Extract terms and calculate term frequency.
		$terms       = $this->extract_terms( $text );
		$term_freq   = array_count_values( $terms );
		$total_terms = count( $terms );

		// Build TF-IDF vector.
		$vector = array();
		$i      = 0;

		foreach ( $vocabulary as $term => $idf ) {
			if ( $i >= self::DIMENSIONS ) {
				break;
			}

			$tf    = isset( $term_freq[ $term ] ) ? $term_freq[ $term ] / $total_terms : 0;
			$tfidf = $tf * $idf;

			// Apply field-specific weighting.
			$weight   = $this->get_field_weight( $field_type );
			$vector[] = $tfidf * $weight;

			++$i;
		}

		// Pad to exact dimensions.
		$vector_count = count( $vector );
		while ( $vector_count < self::DIMENSIONS ) {
			$vector[] = 0.0;
			++$vector_count;
		}

		// L2 normalize.
		return $this->normalize_vector( $vector );
	}

	/**
	 * Extract terms from text
	 *
	 * @param string $text Input text.
	 * @return array Array of terms.
	 */
	private function extract_terms( $text ) {
		// Normalize text.
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9\s]/', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		$words = explode( ' ', $text );

		// Stop words.
		$stop_words = array(
			'the',
			'a',
			'an',
			'and',
			'or',
			'but',
			'in',
			'on',
			'at',
			'to',
			'for',
			'of',
			'with',
			'by',
			'from',
			'is',
			'are',
			'was',
			'were',
			'be',
			'been',
			'being',
			'have',
			'has',
			'had',
			'do',
			'does',
			'did',
			'will',
			'would',
			'could',
			'should',
			'may',
			'might',
			'must',
			'can',
			'this',
			'that',
			'these',
			'those',
			'it',
			'its',
			'they',
			'them',
			'their',
			'we',
			'us',
			'our',
			'you',
			'your',
			'he',
			'she',
			'him',
			'her',
			'his',
			'who',
			'which',
			'what',
			'when',
			'where',
			'why',
			'how',
			'all',
			'each',
			'every',
			'both',
			'few',
			'more',
			'most',
			'other',
			'some',
			'such',
			'no',
			'nor',
			'not',
			'only',
			'own',
			'same',
			'so',
			'than',
			'too',
			'very',
			'just',
			'as',
			'if',
			'into',
			'about',
			'after',
			'before',
		);

		$terms = array();
		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( strlen( $word ) >= 2 && ! in_array( $word, $stop_words, true ) ) {
				$terms[] = $word;
			}
		}

		return $terms;
	}

	/**
	 * Get field-specific weight
	 *
	 * @param string $field_type Field type.
	 * @return float Weight factor.
	 */
	private function get_field_weight( $field_type ) {
		switch ( $field_type ) {
			case 'title':
				return 1.5;
			case 'excerpt':
				return 1.2;
			case 'chunk':
			default:
				return 1.0;
		}
	}

	/**
	 * L2 normalize vector
	 *
	 * @param array $vector Input vector.
	 * @return array Normalized vector.
	 */
	private function normalize_vector( $vector ) {
		$magnitude = sqrt(
			array_sum(
				array_map(
					function ( $x ) {
						return $x * $x;
					},
					$vector
				)
			)
		);

		if ( 0 === $magnitude ) {
			return $vector;
		}

		return array_map(
			function ( $x ) use ( $magnitude ) {
				return $x / $magnitude;
			},
			$vector
		);
	}

	/**
	 * Get status of TF-IDF embeddings
	 *
	 * @return array Status information.
	 */
	public function get_status() {
		$config        = $this->settings->get_connection( $this->connection_name );
		$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
			return $this->get_status_supabase();
		}

		return $this->get_status_pdo();
	}

	/**
	 * Get status via PDO
	 *
	 * @return array Status information.
	 */
	private function get_status_pdo() {
		$connection = $this->db->get_connection( $this->connection_name );
		if ( ! $connection ) {
			return array(
				'enabled'   => false,
				'total'     => 0,
				'processed' => 0,
			);
		}

		try {
			$table_prefix = $this->get_table_prefix();
			$vector_table = $table_prefix . 'posts_tfidf_300';

			// Count posts in wp_posts_clean.
			$total_stmt = $connection->query( 'SELECT COUNT(*) FROM public.' . $table_prefix . 'posts_clean' );
			$total      = (int) $total_stmt->fetchColumn();

			// Count posts with at least one embedding.
			$processed_stmt = $connection->query(
				'SELECT COUNT(DISTINCT post_id) FROM public.' . $vector_table
			);
			$processed      = (int) $processed_stmt->fetchColumn();

			return array(
				'enabled'    => $processed > 0,
				'total'      => $total,
				'processed'  => $processed,
				'percentage' => $total > 0 ? round( ( $processed / $total ) * 100, 1 ) : 0,
			);

		} catch ( Exception $e ) {
			return array(
				'enabled'   => false,
				'total'     => 0,
				'processed' => 0,
			);
		}
	}

	/**
	 * Get status via Supabase
	 *
	 * @return array Status information.
	 */
	private function get_status_supabase() {
		$config = $this->settings->get_connection( $this->connection_name );

		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once dirname( __DIR__, 1 ) . '/providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
		if ( isset( $runtime_config['error'] ) ) {
			return array(
				'enabled'   => false,
				'total'     => 0,
				'processed' => 0,
			);
		}

		$project_url = $runtime_config['project_url'];

		try {
			// Get total posts count.
			$total_url      = $project_url . '/rest/v1/wp_posts_clean?select=post_id';
			$total_response = wp_safe_remote_get(
				$total_url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'] ) + array(
						'Prefer' => 'count=exact',
						'Range'  => '0-0',
					),
					'timeout' => 15,
				)
			);

			$total = 0;
			if ( ! is_wp_error( $total_response ) ) {
				$content_range = wp_remote_retrieve_header( $total_response, 'content-range' );
				if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
					$total = (int) $matches[1];
				}
			}

			// Get processed posts count.
			$processed_url      = $project_url . '/rest/v1/rpc/get_tfidf_status';
			$processed_response = wp_safe_remote_post(
				$processed_url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'] ),
					'body'    => '{}',
					'timeout' => 15,
				)
			);

			$processed = 0;
			if ( ! is_wp_error( $processed_response ) ) {
				$body   = wp_remote_retrieve_body( $processed_response );
				$result = json_decode( $body, true );
				if ( isset( $result['processed_posts'] ) ) {
					$processed = (int) $result['processed_posts'];
				}
			}

			return array(
				'enabled'    => $processed > 0,
				'total'      => $total,
				'processed'  => $processed,
				'percentage' => $total > 0 ? round( ( $processed / $total ) * 100, 1 ) : 0,
			);

		} catch ( Exception $e ) {
			return array(
				'enabled'   => false,
				'total'     => 0,
				'processed' => 0,
			);
		}
	}

	/**
	 * Clear all TF-IDF vectors
	 *
	 * @return array Result information.
	 */
	public function clear_vectors() {
		$config        = $this->settings->get_connection( $this->connection_name );
		$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

		if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
			return $this->clear_vectors_supabase();
		}

		return $this->clear_vectors_pdo();
	}

	/**
	 * Clear vectors via PDO
	 *
	 * @return array Result.
	 */
	private function clear_vectors_pdo() {
		$connection = $this->db->get_connection( $this->connection_name );
		if ( ! $connection ) {
			return array(
				'success' => false,
				'message' => 'Database connection failed',
			);
		}

		try {
			$table_name = $this->get_table_prefix() . 'posts_tfidf_300';
			$connection->exec( 'TRUNCATE TABLE public.' . $table_name );

			return array(
				'success' => true,
				'message' => 'TF-IDF vectors cleared successfully',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Clear vectors via Supabase
	 *
	 * @return array Result.
	 */
	private function clear_vectors_supabase() {
		$config = $this->settings->get_connection( $this->connection_name );

		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once dirname( __DIR__, 1 ) . '/providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
		if ( isset( $runtime_config['error'] ) ) {
			return array(
				'success' => false,
				'message' => $runtime_config['error'],
			);
		}

		$project_url = $runtime_config['project_url'];

		try {
			// Use RPC to truncate.
			$rpc_url  = $project_url . '/rest/v1/rpc/truncate_tfidf_vectors';
			$response = wp_safe_remote_post(
				$rpc_url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'body'    => '{}',
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => $response->get_error_message(),
				);
			}

			return array(
				'success' => true,
				'message' => 'TF-IDF vectors cleared successfully',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}
}
