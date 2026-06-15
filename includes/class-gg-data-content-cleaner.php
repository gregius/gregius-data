<?php
/**
 * Content Cleaner Service
 *
 * Manages the cleaning pipeline for wp_posts_clean table.
 * Strips Gutenberg blocks and formatting for search/vector/AI features.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Cleaner Service
 *
 * Responsible for:
 * - Cleaning content using existing Content Processor
 * - Change detection via MD5 hash
 * - Batch cleaning for initial population
 * - Incremental updates for synced posts
 */
class GG_Data_Content_Cleaner {
	/**
	 * Get the active WordPress table prefix.
	 *
	 * @return string
	 */
	private function get_table_prefix() {
		return GG_Data_Table_Prefix_Resolver::runtime_prefix();
	}

	/**
	 * Content processor instance
	 *
	 * @var GG_Data_Content_Processor
	 */
	private $content_processor;

	/**
	 * Database connection manager
	 *
	 * @var GG_Data_DB
	 */
	private $db;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Cleaning algorithm version
	 *
	 * Increment when cleaning logic changes to trigger re-cleaning
	 *
	 * @var string
	 */
	const CLEANING_VERSION = '1.0';

	/**
	 * Average reading speed (words per minute)
	 *
	 * @var int
	 */
	const READING_SPEED_WPM = 200;

	/**
	 * Connection manager instance
	 *
	 * @var GG_Data_Connection_Manager
	 */
	private $connection_manager;

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings_manager;

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
		$this->content_processor  = new GG_Data_Content_Processor();
		$this->db                 = new GG_Data_DB();
		$this->logger             = new GG_Data_Logger();
		$this->connection_manager = new GG_Data_Connection_Manager();
		$this->settings_manager   = new GG_Data_Settings_Manager();
		$this->chunker            = new GG_Data_Chunker();
	}

	/**
	 * Clean a single post and store in wp_posts_clean
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $title            Original post title.
	 * @param string $content          Original post content.
	 * @param string $excerpt          Original post excerpt.
	 * @param string $connection_name  Connection name (default: 'default').
	 * @param bool   $force            Skip hash check and force cleaning (default: false).
	 * @return bool Success or failure.
	 * @throws Exception If PostgreSQL connection is unavailable or database operations fail.
	 */
	public function clean_post( $post_id, $title, $content, $excerpt, $connection_name = 'default', $force = false ) {
		try {
			// Clean the content using existing processor.
			// Use clean_title() for titles (no min length) and clean_content() for content/excerpt.
			$title_clean   = $this->content_processor->clean_title( $title );
			$content_clean = $this->content_processor->clean_content( $content );
			$excerpt_clean = $this->content_processor->clean_content( $excerpt );

			// Calculate content hash for change detection.
			$content_hash = $this->calculate_content_hash( $title, $content, $excerpt );

			// Calculate word count and reading time.
			$word_count           = $this->count_words( $content_clean );
			$reading_time_minutes = $this->calculate_reading_time( $word_count );

			// Check if cleaning is needed (content changed or version upgraded).
			// Skip check if force=true (batch sync performance optimization).
			if ( ! $force && ! $this->needs_cleaning( $post_id, $content_hash, $connection_name ) ) {
				if ( $this->chunker->needs_rechunking( $post_id, $content_hash, $connection_name ) ) {
					$chunks      = $this->chunker->process_post( $post_id, $content_clean, $content_hash, $connection_name, true );
					$chunk_count = is_array( $chunks ) ? count( $chunks ) : 0;

					if ( false === $chunks ) {
						$this->logger->log(
							sprintf( 'Post %d hash matched but chunk regeneration failed', $post_id ),
							'error',
							'sync',
							$connection_name
						);
						return false;
					}

					$this->logger->log(
						sprintf( 'Post %d hash matched; regenerated %d chunks', $post_id, $chunk_count ),
						'debug',
						'sync',
						$connection_name
					);
				}

				$this->logger->log(
					sprintf( 'Post %d already clean (hash match), skipping', $post_id ),
					'debug',
					'sync',
					$connection_name
				);
				return true;
			}

			// Get connection config to determine provider type.
			$config = $this->settings_manager->get_connection( $connection_name );
			if ( ! $config ) {
				throw new Exception( 'Connection "' . sanitize_text_field( $connection_name ) . '" not found' );
			}

			$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

			// Prepare clean data array.
			$clean_data = array(
				'post_id'              => $post_id,
				'post_title_clean'     => $title_clean,
				'post_content_clean'   => $content_clean,
				'post_excerpt_clean'   => $excerpt_clean,
				'metadata_manifest'    => $this->build_metadata_manifest( $post_id ),
				'content_hash'         => $content_hash,
				'cleaning_version'     => self::CLEANING_VERSION,
				'word_count'           => $word_count,
				'reading_time_minutes' => $reading_time_minutes,
			);

			// Route to appropriate storage method based on provider type.
			if ( 'postgrest' === $provider_type ) {
				// Use REST API for Supabase.
				$result = $this->store_clean_content_supabase( $clean_data, $connection_name );
				if ( ! $result ) {
					throw new Exception( 'Failed to store clean content in Supabase' );
				}
			} else {
				// Use PDO for PostgreSQL.
				$result = $this->store_clean_content_pdo( $clean_data, $connection_name );
				if ( ! $result ) {
					throw new Exception( 'Failed to store clean content in PostgreSQL' );
				}
			}

			// Generate and store chunks for embedding pipeline.
			// Pass $force to skip hash check if we're already in forced mode.
			$chunks      = $this->chunker->process_post( $post_id, $content_clean, $content_hash, $connection_name, $force );
			$chunk_count = is_array( $chunks ) ? count( $chunks ) : 0;

			if ( false === $chunks ) {
				$this->logger->log(
					sprintf( 'Failed to generate chunks for post %d', $post_id ),
					'error',
					'sync',
					$connection_name
				);
				return false;
			}

			$this->logger->log(
				sprintf(
					'Cleaned post %d (%d words, %d min read, %d chunks)',
					$post_id,
					$word_count,
					$reading_time_minutes,
					$chunk_count
				),
				'debug',
				'sync',
				$connection_name
			);

			return true;
		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Failed to clean post %d: %s', $post_id, $e->getMessage() ),
				'error',
				'sync',
				$connection_name
			);
			return false;
		}
	}

	/**
	 * Store clean content in PostgreSQL using PDO
	 *
	 * @param array  $clean_data      Clean content data array.
	 * @param string $connection_name Connection name.
	 * @return bool Success or failure.
	 * @throws Exception If database operations fail.
	 */
	private function store_clean_content_pdo( $clean_data, $connection_name ) {
		$conn = $this->db->get_connection( $connection_name );
		if ( ! $conn ) {
			throw new Exception( 'PostgreSQL connection unavailable' );
		}

		$table = $this->get_table_prefix() . 'posts_clean';

		$sql = "
			INSERT INTO $table (
				post_id,
				post_title_clean,
				post_content_clean,
				post_excerpt_clean,
				metadata_manifest,
				cleaned_at,
				content_hash,
				cleaning_version,
				word_count,
				reading_time_minutes
			) VALUES (
				:post_id,
				:title_clean,
				:content_clean,
				:excerpt_clean,
				:metadata_manifest::jsonb,
				NOW(),
				:content_hash,
				:cleaning_version,
				:word_count,
				:reading_time
			)
			ON CONFLICT (post_id) DO UPDATE SET
				post_title_clean = EXCLUDED.post_title_clean,
				post_content_clean = EXCLUDED.post_content_clean,
				post_excerpt_clean = EXCLUDED.post_excerpt_clean,
				metadata_manifest = EXCLUDED.metadata_manifest,
				cleaned_at = NOW(),
				content_hash = EXCLUDED.content_hash,
				cleaning_version = EXCLUDED.cleaning_version,
				word_count = EXCLUDED.word_count,
				reading_time_minutes = EXCLUDED.reading_time_minutes
		";

		$stmt = $conn->prepare( $sql );
		return $stmt->execute(
			array(
				':post_id'           => $clean_data['post_id'],
				':title_clean'       => $clean_data['post_title_clean'],
				':content_clean'     => $clean_data['post_content_clean'],
				':excerpt_clean'     => $clean_data['post_excerpt_clean'],
				':metadata_manifest' => wp_json_encode( is_array( $clean_data['metadata_manifest'] ) ? $clean_data['metadata_manifest'] : array() ),
				':content_hash'      => $clean_data['content_hash'],
				':cleaning_version'  => $clean_data['cleaning_version'],
				':word_count'        => $clean_data['word_count'],
				':reading_time'      => $clean_data['reading_time_minutes'],
			)
		);
	}

	/**
	 * Store clean content in Supabase using REST API
	 *
	 * @param array  $clean_data      Clean content data array.
	 * @param string $connection_name Connection name.
	 * @return bool Success or failure.
	 * @throws Exception If REST API operations fail.
	 */
	private function store_clean_content_supabase( $clean_data, $connection_name ) {
		// Get Supabase provider.
		$provider = $this->connection_manager->get_provider( $connection_name );
		if ( ! $provider ) {
			throw new Exception( 'Supabase provider unavailable' );
		}

		// Get connection config for REST API credentials.
		$config = $this->settings_manager->get_connection( $connection_name );
		if ( ! $config ) {
			throw new Exception( 'Connection "' . esc_html( $connection_name ) . '" not found' );
		}

		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once __DIR__ . '/providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
		if ( isset( $runtime_config['error'] ) ) {
			throw new Exception( 'Supabase connection credentials missing' );
		}

		$project_url = $runtime_config['project_url'];

		// Prepare data for Supabase (add timestamp and convert to JSON-friendly format).
		$supabase_data = array(
			'post_id'              => intval( $clean_data['post_id'] ),
			'post_title_clean'     => $clean_data['post_title_clean'],
			'post_content_clean'   => $clean_data['post_content_clean'],
			'post_excerpt_clean'   => $clean_data['post_excerpt_clean'],
			'metadata_manifest'    => is_array( $clean_data['metadata_manifest'] ) ? $clean_data['metadata_manifest'] : array(),
			'cleaned_at'           => gmdate( 'Y-m-d\TH:i:s.u\Z' ),
			'content_hash'         => $clean_data['content_hash'],
			'cleaning_version'     => $clean_data['cleaning_version'],
			'word_count'           => intval( $clean_data['word_count'] ),
			'reading_time_minutes' => intval( $clean_data['reading_time_minutes'] ),
		); // Make REST API request using wp_remote_post.
		$url           = $project_url . '/rest/v1/wp_posts_clean';

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
					'Prefer' => 'resolution=merge-duplicates', // Upsert behavior.
				),
				'body'    => wp_json_encode( $supabase_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Supabase REST API error: ' . esc_html( $response->get_error_message() ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			$body = wp_remote_retrieve_body( $response );
			throw new Exception( 'Supabase REST API failed with status ' . intval( $status_code ) . ': ' . esc_html( $body ) );
		}

		return true;
	}

	/**
	 * Batch clean multiple posts
	 *
	 * Used for initial population of wp_posts_clean table.
	 * Legacy method - calls clean_post() individually.
	 *
	 * @param array  $posts            Array of post objects with ID, title, content, excerpt.
	 * @param string $connection_name  Connection name.
	 * @return array Results with success/failure counts.
	 */
	public function batch_clean( $posts, $connection_name = 'default' ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		foreach ( $posts as $post ) {
			$success = $this->clean_post(
				$post->ID,
				$post->post_title ?? '',
				$post->post_content ?? '',
				$post->post_excerpt ?? '',
				$connection_name
			);

			if ( $success ) {
				++$results['success'];
			} else {
				++$results['failed'];
				$results['errors'][] = sprintf( 'Post %d failed', $post->ID );
			}
		}

		$this->logger->log(
			sprintf(
				'Batch cleaning complete: %d success, %d failed, %d skipped',
				$results['success'],
				$results['failed'],
				$results['skipped']
			),
			'info',
			'sync',
			$connection_name
		);

		return $results;
	}

	/**
	 * Bulk clean posts with minimal HTTP requests (PostgREST optimized)
	 *
	 * Processes all posts locally, then:
	 * 1. ONE bulk upsert for all clean content
	 * 2. ONE bulk delete for all existing chunks
	 * 3. ONE bulk insert for all new chunks
	 *
	 * @since 2.0.0
	 * @param array  $posts           Array of post objects with ID, post_title, post_content, post_excerpt.
	 * @param string $connection_name Connection name.
	 * @return array Results with success/failure counts and chunk count.
	 */
	public function bulk_clean_posts( $posts, $connection_name = 'default' ) {
		$results = array(
			'success'     => 0,
			'failed'      => 0,
			'chunk_count' => 0,
			'errors'      => array(),
		);

		if ( empty( $posts ) ) {
			return $results;
		}

		// Get connection config.
		$config = $this->settings_manager->get_connection( $connection_name );
		if ( ! $config || 'postgrest' !== ( $config['type'] ?? '' ) ) {
			// Fall back to individual processing for non-PostgREST connections.
			return $this->batch_clean( $posts, $connection_name );
		}

		if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
			require_once __DIR__ . '/providers/class-gg-postgrest-provider.php';
		}

		$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
		if ( isset( $runtime_config['error'] ) ) {
			$results['errors'][] = 'Missing Supabase credentials';
			return $results;
		}

		$project_url = $runtime_config['project_url'];

		// Step 1: Process all posts locally (no HTTP requests).
		$clean_rows = array();
		$all_chunks = array();
		$post_ids   = array();

		foreach ( $posts as $post ) {
			$post_id    = $post->ID;
			$post_ids[] = $post_id;

			// Clean content locally.
			$title_clean   = $this->content_processor->clean_title( $post->post_title ?? '' );
			$content_clean = $this->content_processor->clean_content( $post->post_content ?? '' );
			$excerpt_clean = $this->content_processor->clean_content( $post->post_excerpt ?? '' );

			// Calculate hash and metrics.
			$content_hash         = $this->calculate_content_hash( $post->post_title ?? '', $post->post_content ?? '', $post->post_excerpt ?? '' );
			$word_count           = $this->count_words( $content_clean );
			$reading_time_minutes = $this->calculate_reading_time( $word_count );

			// Add to clean content batch.
			$clean_rows[] = array(
				'post_id'              => $post_id,
				'post_title_clean'     => $title_clean,
				'post_content_clean'   => $content_clean,
				'post_excerpt_clean'   => $excerpt_clean,
				'metadata_manifest'    => $this->build_metadata_manifest( $post_id ),
				'cleaned_at'           => gmdate( 'Y-m-d\TH:i:s.u\Z' ),
				'content_hash'         => $content_hash,
				'cleaning_version'     => self::CLEANING_VERSION,
				'word_count'           => $word_count,
				'reading_time_minutes' => $reading_time_minutes,
			);

			// Generate chunks locally.
			$chunks = $this->chunker->chunk_content(
				$content_clean,
				GG_Data_Chunker::DEFAULT_CHUNK_SIZE,
				$connection_name,
				array(
					'post_id'      => $post_id,
					'content_hash' => $content_hash,
					'is_bulk_sync' => true,
				)
			);
			foreach ( $chunks as $index => $chunk ) {
				$all_chunks[] = array(
					'post_id'     => $post_id,
					'chunk_index' => $index,
					'chunk_text'  => $chunk['text'],
					'chunk_hash'  => $chunk['hash'],
					'source_hash' => $content_hash,
					'token_count' => $chunk['token_count'],
				);
			}

			++$results['success'];
		}

		// Step 2: Bulk upsert all clean content (ONE HTTP request).
		$clean_url      = $project_url . '/rest/v1/wp_posts_clean';
		$clean_response = wp_safe_remote_post(
			$clean_url,
			array(
				'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
					'Prefer' => 'resolution=merge-duplicates,return=minimal',
				),
				'body'    => wp_json_encode( $clean_rows ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $clean_response ) ) {
			$results['errors'][] = 'Clean content bulk upsert failed: ' . $clean_response->get_error_message();
			$results['failed']   = $results['success'];
			$results['success']  = 0;
			return $results;
		}

		$clean_status = wp_remote_retrieve_response_code( $clean_response );
		if ( ! in_array( $clean_status, array( 200, 201 ), true ) ) {
			$body                = wp_remote_retrieve_body( $clean_response );
			$results['errors'][] = 'Clean content bulk upsert HTTP ' . $clean_status . ': ' . $body;
			$results['failed']   = $results['success'];
			$results['success']  = 0;
			return $results;
		}

		// Step 3: Delete existing chunks for all posts (ONE HTTP request).
		if ( ! empty( $post_ids ) ) {
			$post_ids_param = implode( ',', array_map( 'intval', $post_ids ) );
			$delete_url     = $project_url . '/rest/v1/wp_posts_chunks?post_id=in.(' . $post_ids_param . ')';

			$delete_response = wp_remote_request(
				$delete_url,
				array(
					'method'  => 'DELETE',
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'timeout' => 60,
				)
			);

			// Ignore delete errors (might be no existing chunks).
		}

		// Step 4: Bulk insert all chunks (ONE HTTP request).
		if ( ! empty( $all_chunks ) ) {
			$chunks_url      = $project_url . '/rest/v1/wp_posts_chunks';
			$chunks_response = wp_safe_remote_post(
				$chunks_url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
						'Prefer' => 'return=minimal',
					),
					'body'    => wp_json_encode( $all_chunks ),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $chunks_response ) ) {
				$results['errors'][] = 'Chunks bulk insert failed: ' . $chunks_response->get_error_message();
			} else {
				$chunks_status = wp_remote_retrieve_response_code( $chunks_response );
				if ( in_array( $chunks_status, array( 200, 201 ), true ) ) {
					$results['chunk_count'] = count( $all_chunks );
				} else {
					$body                = wp_remote_retrieve_body( $chunks_response );
					$results['errors'][] = 'Chunks bulk insert HTTP ' . $chunks_status . ': ' . $body;
				}
			}
		}

		$this->logger->log(
			sprintf(
				'Bulk clean complete: %d posts, %d chunks (3 HTTP requests)',
				$results['success'],
				$results['chunk_count']
			),
			'info',
			'sync',
			$connection_name
		);

		return $results;
	}

	/**
	 * Build metadata manifest for deterministic JSONB filtering.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function build_metadata_manifest( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return array();
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return array();
		}

		$taxonomies = get_object_taxonomies( $post_type, 'names' );
		if ( ! is_array( $taxonomies ) ) {
			$taxonomies = array();
		}

		$taxonomy_manifest = array();
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' === $taxonomy ) {
				continue;
			}

			$terms = wp_get_post_terms( $post_id, $taxonomy );
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				$term_id   = absint( $term->term_id );
				$term_slug = sanitize_title( (string) $term->slug );
				if ( $term_id <= 0 || '' === $term_slug ) {
					continue;
				}

				$taxonomy_manifest[] = array(
					'taxonomy'  => $taxonomy,
					'term_id'   => $term_id,
					'term_slug' => $term_slug,
				);
			}
		}

		$manifest = array(
			'post_id'           => $post_id,
			'post_type'         => sanitize_key( $post_type ),
			'taxonomy_manifest' => array_values( $taxonomy_manifest ),
		);

		/**
		 * Filter the metadata manifest assembled during PostgreSQL sync.
		 *
		 * Allows external plugins to enrich the manifest with additional fields
		 * (e.g. meta_manifest, manifest_hash, manifest_version) before it is
		 * written to the sync table. Return an array of the same shape or a
		 * superset; never return a different type.
		 *
		 * @since 1.0.0
		 *
		 * @param array $manifest { post_id, post_type, taxonomy_manifest }
		 * @param int   $post_id  Post ID.
		 * @return array
		 */
		return apply_filters( 'gg_data_metadata_manifest', $manifest, $post_id );
	}

	/**
	 * Check if post needs cleaning
	 *
	 * Compares content hash and cleaning version to determine if re-cleaning is needed.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $content_hash     Current content hash.
	 * @param string $connection_name  Connection name.
	 * @return bool True if cleaning is needed.
	 */
	private function needs_cleaning( $post_id, $content_hash, $connection_name ) {
		try {
			// Check if this is a PostgREST connection.
			if ( $this->db->is_postgrest_connection( $connection_name ) ) {
				return $this->needs_cleaning_postgrest( $post_id, $content_hash, $connection_name );
			}

			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return true; // No connection, assume cleaning needed.
			}

			$table = $this->get_table_prefix() . 'posts_clean';

			$sql  = "SELECT content_hash, cleaning_version FROM $table WHERE post_id = :post_id";
			$stmt = $conn->prepare( $sql );
			$stmt->execute( array( ':post_id' => $post_id ) );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$row = $stmt->fetch( PDO::FETCH_ASSOC );

			if ( ! $row ) {
				return true; // No existing record, cleaning needed.
			}

			// Check if content changed or version upgraded.
			if ( $content_hash !== $row['content_hash'] ) {
				return true; // Content changed.
			}

			if ( self::CLEANING_VERSION !== $row['cleaning_version'] ) {
				return true; // Cleaning algorithm upgraded.
			}

			return false; // No cleaning needed.

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Error checking cleaning status for post %d: %s', $post_id, $e->getMessage() ),
				'error',
				'sync',
				$connection_name
			);
			return true; // On error, assume cleaning needed.
		}
	}

	/**
	 * Check if post needs cleaning (PostgREST version)
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $content_hash     Current content hash.
	 * @param string $connection_name  Connection name.
	 * @return bool True if cleaning is needed.
	 */
	private function needs_cleaning_postgrest( $post_id, $content_hash, $connection_name ) {
		try {
			// Get connection config.
			$config = $this->settings_manager->get_connection( $connection_name );
			if ( ! $config ) {
				return true; // No config, assume cleaning needed.
			}

			if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
				require_once __DIR__ . '/providers/class-gg-postgrest-provider.php';
			}

			$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
			if ( isset( $runtime_config['error'] ) ) {
				return true; // No credentials, assume cleaning needed.
			}

			$project_url = $runtime_config['project_url'];

			// Query PostgREST API for existing record.
			$url = $project_url . '/rest/v1/' . $this->get_table_prefix() . 'posts_clean?post_id=eq.' . intval( $post_id ) . '&select=content_hash,cleaning_version';

			$response = wp_safe_remote_get(
				$url,
				array(
					'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ),
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->logger->log(
					sprintf( 'PostgREST API error checking clean status for post %d: %s', $post_id, $response->get_error_message() ),
					'warning',
					'sync',
					$connection_name
				);
				return true; // On error, assume cleaning needed.
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code ) {
				return true; // Not found or error, assume cleaning needed.
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( empty( $data ) || ! is_array( $data ) ) {
				return true; // No existing record, cleaning needed.
			}

			$row = $data[0];

			// Check if content changed or version upgraded.
			if ( isset( $row['content_hash'] ) && $row['content_hash'] !== $content_hash ) {
				return true; // Content changed.
			}

			if ( isset( $row['cleaning_version'] ) && self::CLEANING_VERSION !== $row['cleaning_version'] ) {
				return true; // Cleaning algorithm upgraded.
			}

			return false; // No cleaning needed.

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Error checking cleaning status (PostgREST) for post %d: %s', $post_id, $e->getMessage() ),
				'error',
				'sync',
				$connection_name
			);
			return true; // On error, assume cleaning needed.
		}
	}

	/**
	 * Calculate MD5 hash of original content
	 *
	 * Used for change detection to avoid unnecessary re-cleaning.
	 *
	 * @param string $title   Original title.
	 * @param string $content Original content.
	 * @param string $excerpt Original excerpt.
	 * @return string MD5 hash.
	 */
	private function calculate_content_hash( $title, $content, $excerpt ) {
		return md5( $title . $content . $excerpt );
	}

	/**
	 * Count words in cleaned content
	 *
	 * More accurate than WordPress str_word_count() for clean text.
	 *
	 * @param string $text Cleaned text.
	 * @return int Word count.
	 */
	private function count_words( $text ) {
		// Remove extra whitespace.
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );

		if ( empty( $text ) ) {
			return 0;
		}

		return str_word_count( $text );
	}

	/**
	 * Calculate reading time in minutes
	 *
	 * Based on average reading speed of 200 WPM.
	 *
	 * @param int $word_count Word count.
	 * @return int Reading time in minutes (minimum 1).
	 */
	private function calculate_reading_time( $word_count ) {
		if ( 0 === $word_count ) {
			return 1;
		}

		return max( 1, (int) ceil( $word_count / self::READING_SPEED_WPM ) );
	}

	/**
	 * Clean all posts for a connection
	 *
	 * Used for initial population when enabling search features.
	 *
	 * @param string $connection_name Connection name.
	 * @param int    $batch_size      Number of posts to process per batch.
	 * @return array Results summary.
	 * @throws Exception If PostgreSQL connection is unavailable or database operations fail.
	 */
	public function clean_all_posts( $connection_name = 'default', $batch_size = 100 ) {
		$total_results = array(
			'success' => 0,
			'failed'  => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		try {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				throw new Exception( 'PostgreSQL connection unavailable' );
			}

			$table = 'wp_posts';

			// Get all posts from PostgreSQL in batches.
			$offset = 0;

			while ( true ) {
				$sql = "
					SELECT ID, post_title, post_content, post_excerpt
					FROM $table
					WHERE post_status = 'publish'
					ORDER BY ID
					LIMIT :limit OFFSET :offset
				";

				$stmt = $conn->prepare( $sql );
				$stmt->execute(
					array(
						':limit'  => $batch_size,
						':offset' => $offset,
					)
				);
				// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
				$posts = $stmt->fetchAll( PDO::FETCH_OBJ );

				if ( empty( $posts ) ) {
					break; // No more posts.
				}               // Clean this batch.
				$batch_results = $this->batch_clean( $posts, $connection_name );

				// Accumulate results.
				$total_results['success'] += $batch_results['success'];
				$total_results['failed']  += $batch_results['failed'];
				$total_results['skipped'] += $batch_results['skipped'];
				$total_results['errors']   = array_merge( $total_results['errors'], $batch_results['errors'] );

				$offset += $batch_size;

				// Prevent memory exhaustion.
				unset( $posts, $batch_results );
			}

			$this->logger->log(
				sprintf(
					'Cleaned all posts for connection "%s": %d success, %d failed',
					$connection_name,
					$total_results['success'],
					$total_results['failed']
				),
				'info',
				'sync',
				$connection_name
			);

			return $total_results;

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Failed to clean all posts: %s', $e->getMessage() ),
				'error',
				'sync',
				$connection_name
			);
			return array(
				'success' => 0,
				'failed'  => 0,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Delete cleaned content for a post
	 *
	 * Called when post is deleted from wp_posts (ON DELETE CASCADE handles this automatically).
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $connection_name Connection name.
	 * @return bool Success or failure.
	 */
	public function delete_cleaned_post( $post_id, $connection_name = 'default' ) {
		try {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return false;
			}

			$table = 'wp_posts_clean';

			$sql  = "DELETE FROM $table WHERE post_id = :post_id";
			$stmt = $conn->prepare( $sql );
			$stmt->execute( array( ':post_id' => $post_id ) );

			$this->logger->log(
				sprintf( 'Deleted cleaned content for post %d', $post_id ),
				'debug',
				'sync',
				$connection_name
			);

			return true;

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Failed to delete cleaned content for post %d: %s', $post_id, $e->getMessage() ),
				'error',
				'sync',
				$connection_name
			);
			return false;
		}
	}

	/**
	 * Reconcile orphaned posts - find posts in wp_posts without wp_posts_clean entries
	 *
	 * This handles the edge case where a post synced successfully but cleaning failed.
	 *
	 * @param string $connection_name Connection name.
	 * @param int    $batch_size      Number of orphaned posts to clean per batch.
	 * @return array Reconciliation results with counts and errors.
	 * @throws Exception If PostgreSQL connection is unavailable or database operations fail.
	 */
	public function reconcile_orphaned_posts( $connection_name = 'default', $batch_size = 100 ) {
		$results = array(
			'found'   => 0,
			'cleaned' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		try {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				throw new Exception( 'PostgreSQL connection unavailable' );
			}

			// Find posts in wp_posts that don't have wp_posts_clean entries.
			$sql = "
				SELECT p.id, p.post_title, p.post_content, p.post_excerpt
				FROM wp_posts p
				LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
				WHERE pc.post_id IS NULL
				  AND p.post_type = 'post'
				  AND p.post_status = 'publish'
				LIMIT :batch_size
			";

			$stmt = $conn->prepare( $sql );
			$stmt->execute( array( ':batch_size' => $batch_size ) );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$orphaned_posts = $stmt->fetchAll( PDO::FETCH_OBJ );

			$results['found'] = count( $orphaned_posts );           if ( empty( $orphaned_posts ) ) {
				$this->logger->log( 'No orphaned posts found - wp_posts and wp_posts_clean are in sync', 'info', 'sync', $connection_name );
				return $results;
			}

			$this->logger->log(
				sprintf( 'Found %d orphaned posts (in wp_posts but not wp_posts_clean)', $results['found'] ),
				'info',
				'sync',
				$connection_name
			);

			// Clean each orphaned post.
			foreach ( $orphaned_posts as $post ) {
				try {
					$clean_result = $this->clean_post(
						$post->id,
						$post->post_title,
						$post->post_content,
						$post->post_excerpt,
						$connection_name
					);

					if ( $clean_result ) {
						++$results['cleaned'];
					} else {
						++$results['failed'];
						$results['errors'][] = sprintf( 'Failed to clean post %d (unknown error)', $post->id );
					}
				} catch ( Exception $e ) {
					++$results['failed'];
					$results['errors'][] = sprintf( 'Post %d: %s', $post->id, $e->getMessage() );
				}
			}

			$this->logger->log(
				sprintf(
					'Reconciliation complete: %d found, %d cleaned, %d failed',
					$results['found'],
					$results['cleaned'],
					$results['failed']
				),
				'info',
				'sync',
				$connection_name
			);

			return $results;

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Reconciliation failed: %s', $e->getMessage() ),
				'error',
				'sync',
				$connection_name
			);
			$results['errors'][] = $e->getMessage();
			return $results;
		}
	}

	/**
	 * Clean posts by type respecting user's status settings
	 *
	 * This is the settings-aware version for dashboard Clean buttons.
	 * Processes posts that are in wp_posts but not in wp_posts_clean,
	 * filtered by post_type and enabled statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type         Post type to clean (post, page, etc.).
	 * @param array  $enabled_statuses  Post statuses to clean.
	 * @param string $connection_name   Connection name.
	 * @param int    $batch_size        Batch size.
	 * @return array Results with found/cleaned/failed counts.
	 * @throws Exception If PostgreSQL connection is unavailable or database operations fail.
	 */
	public function clean_post_type( $post_type, $enabled_statuses, $connection_name = 'default', $batch_size = 100 ) {
		$results = array(
			'found'   => 0,
			'cleaned' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		try {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				throw new Exception( 'PostgreSQL connection unavailable' );
			}

			// Validate we have statuses to filter.
			if ( empty( $enabled_statuses ) ) {
				$this->logger->log(
					sprintf( 'No enabled statuses for %s - skipping clean', $post_type ),
					'warning',
					'sync',
					$connection_name
				);
				return $results;
			}

			// Build status placeholders for IN clause.
			$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );

			// Dynamic SQL with post_type and statuses.
			$sql = "
				SELECT p.id, p.post_title, p.post_content, p.post_excerpt
				FROM wp_posts p
				LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
				WHERE pc.post_id IS NULL
				  AND p.post_type = ?
				  AND p.post_status IN ($status_placeholders)
				LIMIT ?
			";

			$stmt = $conn->prepare( $sql );

			// Bind parameters: post_type, then each status, then batch_size.
			$params = array_merge( array( $post_type ), $enabled_statuses, array( $batch_size ) );
			$stmt->execute( $params );
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$orphaned_posts   = $stmt->fetchAll( PDO::FETCH_OBJ );
			$results['found'] = count( $orphaned_posts );           if ( empty( $orphaned_posts ) ) {
				$this->logger->log(
					sprintf( 'No orphaned %s posts found - all are cleaned', $post_type ),
					'info',
					'sync',
					$connection_name
				);
				return $results;
			}

			$this->logger->log(
				sprintf(
					'Found %d orphaned %s posts to clean (statuses: %s)',
					$results['found'],
					$post_type,
					implode( ', ', $enabled_statuses )
				),
				'info',
				'sync',
				$connection_name
			);

			// Clean each orphaned post.
			foreach ( $orphaned_posts as $post ) {
				try {
					$clean_result = $this->clean_post(
						$post->id,
						$post->post_title,
						$post->post_content,
						$post->post_excerpt,
						$connection_name
					);

					if ( $clean_result ) {
						++$results['cleaned'];
					} else {
						++$results['failed'];
						$results['errors'][] = sprintf( 'Failed to clean post %d', $post->id );
					}
				} catch ( Exception $e ) {
					++$results['failed'];
					$results['errors'][] = sprintf( 'Post %d: %s', $post->id, $e->getMessage() );
				}
			}

			$this->logger->log(
				sprintf(
					'Cleaning complete for %s: %d found, %d cleaned, %d failed',
					$post_type,
					$results['found'],
					$results['cleaned'],
					$results['failed']
				),
				'info'
			);

			return $results;

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Cleaning failed for %s: %s', $post_type, $e->getMessage() ),
				'error'
			);
			$results['errors'][] = $e->getMessage();
			return $results;
		}
	}
}
