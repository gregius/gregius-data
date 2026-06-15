<?php
/**
 * HashingTF Murmur3 1024-Dimensional Embeddings Generator
 *
 * Row-per-embedding architecture with chunk support.
 * Stateless feature hashing — no vocabulary preparation required.
 * Uses PHP-native MurmurHash3 (available since PHP 8.1) for bucket assignment.
 *
 * Schema: wp_posts_hashingtf_murmur3_1024 with (post_id, field_type, chunk_index, embedding)
 * - field_type: 'title', 'excerpt', 'chunk'
 * - chunk_index: NULL for title/excerpt, 0-N for chunks
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HashingTF Murmur3 1024-Dimensional Embeddings Generator
 */
class GG_Data_HashingTF_Embeddings {
	/**
	 * Resolve the hashing vector table for the current site.
	 *
	 * @return string
	 */
	private function get_vector_table_name() {
		return $this->get_table_prefix() . 'posts_hashingtf_murmur3_1024';
	}

	/**
	 * Get the active WordPress table prefix.
	 *
	 * @return string
	 */
	private function get_table_prefix() {
		return GG_Data_Table_Prefix_Resolver::runtime_prefix();
	}

	/**
	 * Vector dimensions (bucket count)
	 */
	const DIMENSIONS = 1024;

	/**
	 * PHP hash algorithm for bucket assignment
	 *
	 * Murmur3a is available natively since PHP 8.1.
	 */
	const HASH_ALGO = 'murmur3a';

	/**
	 * Tokenizer version — bump when tokenization rules change to trigger regeneration
	 */
	const TOKENIZER_VERSION = 1;

	/**
	 * Target vector table name
	 */
	const TABLE_NAME = 'wp_posts_hashingtf_murmur3_1024';

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
	 * No vocabulary check — this model is stateless.
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
			// Get posts needing vectors (those without any embeddings in the hashing table).
			$table        = $this->get_vector_table_name();
			$table_prefix = $this->get_table_prefix();
			$posts_query  = "
				SELECT DISTINCT p.post_id, p.post_title_clean, p.post_excerpt_clean, p.post_content_clean
				FROM public.{$table_prefix}posts_clean p
				LEFT JOIN public.{$table} v ON p.post_id = v.post_id
				WHERE v.post_id IS NULL
				ORDER BY p.post_id
				LIMIT :batch_size
			";

			$stmt = $connection->prepare( $posts_query );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$stmt->bindValue( ':batch_size', $batch_size, PDO::PARAM_INT );
			$stmt->execute();
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
			$posts = $stmt->fetchAll( PDO::FETCH_ASSOC );

			if ( empty( $posts ) ) {
				return array(
					'success'      => true,
					'processed'    => 0,
					'failed'       => 0,
					'total_tokens' => 0,
					'message'      => 'All posts have vectors',
				);
			}

			// Collect post IDs for batch chunk fetch.
			$post_ids = array_column( $posts, 'post_id' );

			// Fetch ALL chunks for these posts in one query.
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

			$processed    = 0;
			$failed       = 0;
			$total_tokens = 0;

			foreach ( $posts as $post ) {
				$result = $this->generate_embeddings_for_post_pdo(
					$connection,
					$post,
					$chunks_by_post
				);

				if ( $result['success'] ) {
					++$processed;
					$total_tokens += $result['tokens'] ?? 0;
				} else {
					++$failed;
				}
			}

			return array(
				'success'      => true,
				'processed'    => $processed,
				'failed'       => $failed,
				'total_tokens' => $total_tokens,
				'message'      => sprintf( 'Processed %d posts (%d failed)', $processed, $failed ),
			);

		} catch ( Exception $e ) {
			return array(
				'success'      => false,
				'processed'    => 0,
				'failed'       => 0,
				'total_tokens' => 0,
				'message'      => $e->getMessage(),
			);
		}
	}

	/**
	 * Generate all embeddings for a single post (PDO)
	 *
	 * Creates rows for: title, excerpt, and each content chunk.
	 *
	 * @param PDO   $connection     Database connection.
	 * @param array $post           Post data with post_id, post_title_clean, etc.
	 * @param array $chunks_by_post Pre-fetched chunks indexed by post_id.
	 * @return array Result with success status and token count.
	 */
	private function generate_embeddings_for_post_pdo( $connection, $post, $chunks_by_post = array() ) {
		try {
			$connection->beginTransaction();

			$post_id = $post['post_id'];
			$title   = $post['post_title_clean'] ?? '';
			$excerpt = $post['post_excerpt_clean'] ?? '';
			$tokens  = 0;

			// Delete any existing embeddings for this post.
			$table       = $this->get_vector_table_name();
			$delete_stmt = $connection->prepare(
				"DELETE FROM public.{$table} WHERE post_id = :post_id"
			);
			$delete_stmt->execute( array( ':post_id' => $post_id ) );

			// Generate and store title embedding.
			if ( ! empty( $title ) ) {
				$title_vector = $this->generate_hashingtf_vector( $title, 'title' );
				$this->insert_embedding_pdo( $connection, $post_id, 'title', null, $title_vector, $title );
				$tokens += count( $this->extract_terms( $title ) );
			}

			// Generate and store excerpt embedding.
			if ( ! empty( $excerpt ) ) {
				$excerpt_vector = $this->generate_hashingtf_vector( $excerpt, 'excerpt' );
				$this->insert_embedding_pdo( $connection, $post_id, 'excerpt', null, $excerpt_vector, $excerpt );
				$tokens += count( $this->extract_terms( $excerpt ) );
			}

			// Chunk embeddings from pre-fetched wp_posts_chunks rows.
			if ( isset( $chunks_by_post[ $post_id ] ) ) {
				foreach ( $chunks_by_post[ $post_id ] as $chunk ) {
					$chunk_text  = $chunk['chunk_text'] ?? '';
					$chunk_index = $chunk['chunk_index'] ?? 0;

					if ( ! empty( $chunk_text ) ) {
						$chunk_vector = $this->generate_hashingtf_vector( $chunk_text, 'chunk' );
						$this->insert_embedding_pdo( $connection, $post_id, 'chunk', $chunk_index, $chunk_vector, $chunk_text );
						$tokens += count( $this->extract_terms( $chunk_text ) );
					}
				}
			}

			$connection->commit();

			return array(
				'success' => true,
				'tokens'  => $tokens,
			);

		} catch ( Exception $e ) {
			try {
				$connection->rollback();
			} catch ( Exception $rollback_e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'HashingTF rollback failed: ' . $rollback_e->getMessage() );
			}

			return array(
				'success' => false,
				'tokens'  => 0,
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
	 * @param string   $source_text       Original text (for content_hash and token_count).
	 * @param int      $tokenizer_version Tokenizer version.
	 */
	private function insert_embedding_pdo( $connection, $post_id, $field_type, $chunk_index, $vector, $source_text, $tokenizer_version = self::TOKENIZER_VERSION ) {
		$content_hash = md5( $source_text );
		$token_count  = count( $this->extract_terms( $source_text ) );
		$vector_str   = '[' . implode( ',', $vector ) . ']';
		$table        = $this->get_vector_table_name();

		$insert_sql = "
			INSERT INTO public.{$table} (
				post_id,
				field_type,
				chunk_index,
				embedding,
				content_hash,
				token_count,
				status,
				generated_at,
				tokenizer_version
			) VALUES (
				:post_id,
				:field_type,
				:chunk_index,
				:embedding::vector,
				:content_hash,
				:token_count,
				'completed',
				NOW(),
				:tokenizer_version
			)
			ON CONFLICT (post_id, field_type, chunk_index) DO UPDATE SET
				embedding         = EXCLUDED.embedding,
				content_hash      = EXCLUDED.content_hash,
				token_count       = EXCLUDED.token_count,
				status            = 'completed',
				generated_at      = NOW(),
				tokenizer_version = EXCLUDED.tokenizer_version
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
		$stmt->bindValue( ':token_count', $token_count, PDO::PARAM_INT );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections
		$stmt->bindValue( ':tokenizer_version', $tokenizer_version, PDO::PARAM_INT );
		$stmt->execute();
	}

	/**
	 * Generate vectors batch for Supabase (REST API)
	 *
	 * Batch pattern:
	 * 1. Fetch posts needing vectors (1 RPC call)
	 * 2. Fetch ALL chunks for those posts (1 REST call)
	 * 3. Generate all vectors locally (CPU only)
	 * 4. Bulk insert all vectors (1 INSERT call)
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
					'success'      => false,
					'processed'    => 0,
					'failed'       => 0,
					'total_tokens' => 0,
					'message'      => $runtime_config['error'],
				);
			}

			$project_url = $runtime_config['project_url'];

			// STEP 1: Get posts needing vectors (1 RPC call).
			$rpc_url  = $project_url . '/rest/v1/rpc/get_posts_needing_vectors';
			$payload  = array(
				'target_model' => 'hashingtf_murmur3_1024',
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
					'success'      => false,
					'processed'    => 0,
					'failed'       => 0,
					'total_tokens' => 0,
					'message'      => 'RPC error: ' . $response->get_error_message(),
				);
			}

			$body  = wp_remote_retrieve_body( $response );
			$posts = json_decode( $body, true );

			if ( empty( $posts ) ) {
				return array(
					'success'      => true,
					'processed'    => 0,
					'failed'       => 0,
					'total_tokens' => 0,
					'message'      => 'All posts have vectors',
				);
			}

			$post_ids = array_column( $posts, 'post_id' );
			$post_map = array();
			foreach ( $posts as $post ) {
				$post_map[ $post['post_id'] ] = $post;
			}

			// STEP 2: Fetch ALL chunks for these posts (1 REST call).
			$table           = $this->get_vector_table_name();
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

			// STEP 3: Generate ALL vectors locally (CPU only, no HTTP).
			$all_embeddings = array();
			$processed      = 0;
			$total_tokens   = 0;

			foreach ( $posts as $post ) {
				$post_id = $post['post_id'];
				$title   = $post['post_title_clean'] ?? '';
				$excerpt = $post['post_excerpt_clean'] ?? '';

				if ( ! empty( $title ) ) {
					$title_terms      = $this->extract_terms( $title );
					$title_vector     = $this->generate_hashingtf_vector( $title, 'title' );
					$all_embeddings[] = array(
						'post_id'           => $post_id,
						'field_type'        => 'title',
						'chunk_index'       => null,
						'embedding'         => '[' . implode( ',', $title_vector ) . ']',
						'content_hash'      => md5( $title ),
						'token_count'       => count( $title_terms ),
						'status'            => 'completed',
						'generated_at'      => gmdate( 'Y-m-d H:i:s' ),
						'tokenizer_version' => self::TOKENIZER_VERSION,
					);
					$total_tokens    += count( $title_terms );
				}

				if ( ! empty( $excerpt ) ) {
					$excerpt_terms    = $this->extract_terms( $excerpt );
					$excerpt_vector   = $this->generate_hashingtf_vector( $excerpt, 'excerpt' );
					$all_embeddings[] = array(
						'post_id'           => $post_id,
						'field_type'        => 'excerpt',
						'chunk_index'       => null,
						'embedding'         => '[' . implode( ',', $excerpt_vector ) . ']',
						'content_hash'      => md5( $excerpt ),
						'token_count'       => count( $excerpt_terms ),
						'status'            => 'completed',
						'generated_at'      => gmdate( 'Y-m-d H:i:s' ),
						'tokenizer_version' => self::TOKENIZER_VERSION,
					);
					$total_tokens    += count( $excerpt_terms );
				}

				if ( isset( $chunks_by_post[ $post_id ] ) ) {
					foreach ( $chunks_by_post[ $post_id ] as $chunk ) {
						$chunk_text  = $chunk['chunk_text'] ?? '';
						$chunk_index = $chunk['chunk_index'] ?? 0;
						$chunk_hash  = $chunk['chunk_hash'] ?? md5( $chunk_text );

						if ( ! empty( $chunk_text ) ) {
							$chunk_terms      = $this->extract_terms( $chunk_text );
							$chunk_vector     = $this->generate_hashingtf_vector( $chunk_text, 'chunk' );
							$all_embeddings[] = array(
								'post_id'           => $post_id,
								'field_type'        => 'chunk',
								'chunk_index'       => $chunk_index,
								'embedding'         => '[' . implode( ',', $chunk_vector ) . ']',
								'content_hash'      => $chunk_hash,
								'token_count'       => count( $chunk_terms ),
								'status'            => 'completed',
								'generated_at'      => gmdate( 'Y-m-d H:i:s' ),
								'tokenizer_version' => self::TOKENIZER_VERSION,
							);
							$total_tokens    += count( $chunk_terms );
						}
					}
				}

				++$processed;
			}

			// STEP 4: Bulk insert ALL embeddings (1 INSERT call).
			if ( ! empty( $all_embeddings ) ) {
				$insert_url      = $project_url . '/rest/v1/' . $table;
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
						'success'      => false,
						'processed'    => 0,
						'failed'       => 0,
						'total_tokens' => 0,
						'message'      => 'Bulk insert error: ' . $insert_response->get_error_message(),
					);
				}

				$status_code = wp_remote_retrieve_response_code( $insert_response );
				if ( $status_code < 200 || $status_code >= 300 ) {
					$error_body = wp_remote_retrieve_body( $insert_response );
					return array(
						'success'      => false,
						'processed'    => 0,
						'failed'       => 0,
						'total_tokens' => 0,
						'message'      => 'Bulk insert failed (HTTP ' . $status_code . '): ' . $error_body,
					);
				}
			}

			return array(
				'success'      => true,
				'processed'    => $processed,
				'failed'       => 0,
				'total_tokens' => $total_tokens,
				'message'      => sprintf( 'Processed %d posts, created %d embeddings', $processed, count( $all_embeddings ) ),
			);

		} catch ( Exception $e ) {
			return array(
				'success'      => false,
				'processed'    => 0,
				'failed'       => 0,
				'total_tokens' => 0,
				'message'      => $e->getMessage(),
			);
		}
	}

	/**
	 * Generate a hashing TF vector for text
	 *
	 * Algorithm:
	 * 1. Tokenize text into terms.
	 * 2. For each term, compute MurmurHash3 bucket index via modulo.
	 * 3. Use the sign bit of the hash to determine +1 or -1 accumulation
	 *    (signed hashing reduces collision bias).
	 * 4. Apply field-specific weight.
	 * 5. L2 normalize the final vector.
	 *
	 * @param string $text       Input text.
	 * @param string $field_type Field type for weighting ('title', 'excerpt', 'chunk').
	 * @return array Vector array of floats.
	 */
	public function generate_hashingtf_vector( $text, $field_type = 'chunk' ) {
		if ( empty( $text ) ) {
			return array_fill( 0, self::DIMENSIONS, 0.0 );
		}

		$terms  = $this->extract_terms( $text );
		$vector = array_fill( 0, self::DIMENSIONS, 0.0 );
		$weight = $this->get_field_weight( $field_type );

		foreach ( $terms as $term ) {
			// Get the raw unsigned 32-bit hex hash.
			$hash_hex = hash( self::HASH_ALGO, $term );

			// Interpret as unsigned integer (supports 32-bit murmur3a output).
			$hash_int = hexdec( substr( $hash_hex, 0, 8 ) );

			// Bucket index via modulo.
			$bucket = (int) ( $hash_int % self::DIMENSIONS );

			// Sign bit from the next byte to reduce collision bias.
			$sign_int = hexdec( substr( $hash_hex, 8, 2 ) );
			$sign     = ( $sign_int & 0x80 ) ? -1.0 : 1.0;

			$vector[ $bucket ] += $sign * $weight;
		}

		return $this->normalize_vector( $vector );
	}

	/**
	 * Generate a PostgreSQL vector literal for a query string.
	 *
	 * Query vectors use the neutral chunk weight so they do not inherit
	 * document field boosts before retrieval scoring is applied.
	 *
	 * @param string $text Query text.
	 * @return string PostgreSQL vector literal.
	 */
	public function generate_query_vector_literal( $text ) {
		$vector = $this->generate_hashingtf_vector( $text, 'chunk' );

		return '[' . implode( ',', $vector ) . ']';
	}

	/**
	 * Extract terms from text
	 *
	 * Unicode-aware tokenization. Uses mb_strtolower and preg_replace with
	 * Unicode word boundary to handle non-ASCII content better than the
	 * ASCII-only TF-IDF approach.
	 *
	 * @param string $text Input text.
	 * @return array Array of terms.
	 */
	private function extract_terms( $text ) {
		// Lowercase using multibyte-safe function.
		$text = mb_strtolower( $text, 'UTF-8' );

		// Replace non-word characters (Unicode-aware) with space.
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

		// Collapse whitespace.
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( $text );

		$words = explode( ' ', $text );

		// Minimal stop words — intentionally lightweight to preserve signal across languages.
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
			// Minimum 2 characters; skip stop words.
			if ( mb_strlen( $word, 'UTF-8' ) >= 2 && ! in_array( $word, $stop_words, true ) ) {
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

		if ( 0.0 === $magnitude ) {
			return $vector;
		}

		return array_map(
			function ( $x ) use ( $magnitude ) {
				return $x / $magnitude;
			},
			$vector
		);
	}
}
