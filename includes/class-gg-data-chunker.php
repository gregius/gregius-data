<?php
/**
 * Chunker for Embedding Pipeline
 *
 * Creates and manages content chunks for vector embedding generation.
 * Chunks are stored in wp_posts_chunks and used by all embedding strategies.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chunker class for embedding pipeline.
 *
 * Provides methods for:
 * - Sentence-aware content chunking (recursive)
 * - Chunk storage and retrieval
 * - Hash-based change detection
 * - Token counting
 *
 * @since 2.0.0
 */
class GG_Data_Chunker {
	/**
	 * Get the active WordPress table prefix.
	 *
	 * @return string
	 */
	private function get_table_prefix() {
		return GG_Data_Table_Prefix_Resolver::runtime_prefix();
	}

	/**
	 * Default chunk size in tokens.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const DEFAULT_CHUNK_SIZE = 512;

	/**
	 * Minimum chunk size in tokens.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const MIN_CHUNK_SIZE = 100;

	/**
	 * Characters per token (rough estimate).
	 *
	 * @since 2.0.0
	 * @var float
	 */
	const CHARS_PER_TOKEN = 4.0;

	/**
	 * Logger instance.
	 *
	 * @since 2.0.0
	 * @access protected
	 * @var GG_Data_Logger
	 */
	protected $logger;

	/**
	 * Connection manager instance.
	 *
	 * @since 2.0.0
	 * @access protected
	 * @var GG_Data_Connection_Manager
	 */
	protected $connection_manager;

	/**
	 * Registered chunking strategies.
	 *
	 * @since 2.1.0
	 * @access protected
	 * @var array
	 */
	protected $chunking_strategies = array();

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$this->logger             = new GG_Data_Logger();
		$this->connection_manager = new GG_Data_Connection_Manager();
		$this->register_default_chunking_strategy();
	}

	/**
	 * Register the built-in default chunking strategy.
	 *
	 * @since 2.1.0
	 * @access protected
	 * @return void
	 */
	protected function register_default_chunking_strategy() {
		$this->chunking_strategies['default'] = array(
			'key'         => 'default',
			'label'       => 'Default',
			'description' => 'Sentence-aware chunking with paragraph-first splitting and fixed-size contingency splitting.',
			'callback'    => array( $this, 'chunk_content_default' ),
		);
	}

	/**
	 * Get registered chunking strategies.
	 *
	 * @since 2.1.0
	 * @param string $connection_name Connection name.
	 * @return array Strategy definitions keyed by strategy key.
	 */
	public function get_chunking_strategies( $connection_name = 'default' ) {
		$strategies = apply_filters( 'gg_data_chunking_strategies', $this->chunking_strategies, $connection_name );

		if ( ! is_array( $strategies ) ) {
			$strategies = $this->chunking_strategies;
		}

		// Always guarantee an available default strategy.
		if ( ! isset( $strategies['default'] ) || ! is_callable( $strategies['default']['callback'] ?? null ) ) {
			$strategies['default'] = $this->chunking_strategies['default'];
		}

		return $strategies;
	}

	/**
	 * Resolve active chunking strategy key for the current run.
	 *
	 * @since 2.1.0
	 * @param string $connection_name Connection name.
	 * @param array  $context         Optional execution context.
	 * @return string Strategy key.
	 */
	protected function get_active_chunking_strategy_key( $connection_name, $context = array() ) {
		$strategy_key = apply_filters( 'gg_data_chunking_strategy', 'default', $connection_name, $context );

		if ( ! is_string( $strategy_key ) || '' === trim( $strategy_key ) ) {
			return 'default';
		}

		return trim( $strategy_key );
	}

	/**
	 * Normalize and validate strategy chunk output.
	 *
	 * @since 2.1.0
	 * @param mixed  $chunks          Raw chunk output from strategy callback.
	 * @param string $strategy_key    Strategy key used for generation.
	 * @param string $connection_name Connection name.
	 * @return array Normalized chunk arrays with keys: text, token_count, hash.
	 */
	protected function normalize_strategy_chunks( $chunks, $strategy_key, $connection_name ) {
		if ( ! is_array( $chunks ) ) {
			$this->logger->log(
				sprintf( 'Chunk strategy "%s" returned non-array output, falling back to empty chunks', $strategy_key ),
				'warning',
				'chunker',
				$connection_name
			);
			return array();
		}

		$normalized = array();

		foreach ( $chunks as $chunk ) {
			if ( is_string( $chunk ) ) {
				$chunk = array( 'text' => $chunk );
			}

			if ( ! is_array( $chunk ) ) {
				continue;
			}

			$text = $chunk['text'] ?? ( $chunk['chunk_text'] ?? '' );
			$text = is_string( $text ) ? trim( $text ) : '';

			if ( '' === $text ) {
				continue;
			}

			$token_count = isset( $chunk['token_count'] ) ? (int) $chunk['token_count'] : $this->estimate_tokens( $text );
			$hash        = $chunk['hash'] ?? ( $chunk['chunk_hash'] ?? md5( $text ) );

			$normalized[] = array(
				'text'        => $text,
				'token_count' => max( 0, $token_count ),
				'hash'        => is_string( $hash ) && '' !== $hash ? $hash : md5( $text ),
			);
		}

		return $normalized;
	}

	/**
	 * Chunk content using recursive sentence-aware splitting.
	 *
	 * Algorithm:
	 * 1. Try to split on paragraph boundaries first
	 * 2. If paragraph too large, split on sentence boundaries
	 * 3. If sentence too large, split on fixed character count
	 *
	 * @since 2.0.0
	 * @param string $content         The content to chunk.
	 * @param int    $target_tokens   Target tokens per chunk. Default 512.
	 * @param string $connection_name Connection name.
	 * @param array  $context         Optional execution context.
	 * @return array Array of chunk arrays with 'text', 'token_count', 'hash'.
	 */
	public function chunk_content( $content, $target_tokens = self::DEFAULT_CHUNK_SIZE, $connection_name = 'default', $context = array() ) {
		if ( empty( $content ) ) {
			return array();
		}

		$strategies   = $this->get_chunking_strategies( $connection_name );
		$strategy_key = $this->get_active_chunking_strategy_key( $connection_name, $context );

		if ( ! isset( $strategies[ $strategy_key ] ) || ! is_callable( $strategies[ $strategy_key ]['callback'] ?? null ) ) {
			$this->logger->log(
				sprintf( 'Unknown chunk strategy "%s" for connection "%s", falling back to default', $strategy_key, $connection_name ),
				'warning',
				'chunker',
				$connection_name
			);
			$strategy_key = 'default';
		}

		do_action( 'gg_data_chunking_strategy_resolved', $strategy_key, $connection_name, $context );

		$callback   = $strategies[ $strategy_key ]['callback'];
		$raw_chunks = call_user_func( $callback, $content, (int) $target_tokens, $connection_name, $context );

		return $this->normalize_strategy_chunks( $raw_chunks, $strategy_key, $connection_name );
	}

	/**
	 * Default chunking algorithm implementation.
	 *
	 * @since 2.1.0
	 * @param string $content         The content to chunk.
	 * @param int    $target_tokens   Target tokens per chunk. Default 512.
	 * @param string $connection_name Connection name.
	 * @param array  $context         Optional execution context.
	 * @return array Array of chunk arrays with 'text', 'token_count', 'hash'.
	 */
	protected function chunk_content_default( $content, $target_tokens = self::DEFAULT_CHUNK_SIZE, $connection_name = 'default', $context = array() ) {
		if ( empty( $content ) ) {
			return array();
		}

		$target_chars = (int) ( $target_tokens * self::CHARS_PER_TOKEN );
		$min_chars    = (int) ( self::MIN_CHUNK_SIZE * self::CHARS_PER_TOKEN );

		// First, split on paragraph boundaries (double newlines).
		$paragraphs = preg_split( '/\n\s*\n/', $content );
		$chunks     = array();
		$current    = '';

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( empty( $paragraph ) ) {
				continue;
			}

			$para_len    = strlen( $paragraph );
			$current_len = strlen( $current );

			// If adding paragraph keeps us under target, add it.
			if ( $current_len + $para_len + 2 <= $target_chars ) {
				$current .= ( $current ? "\n\n" : '' ) . $paragraph;
			} else {
				// Save current chunk if it's not empty.
				if ( ! empty( $current ) ) {
					$chunks[] = $this->create_chunk_data( $current );
				}

				// If paragraph itself exceeds target, split it recursively.
				if ( $para_len > $target_chars ) {
					$sub_chunks = $this->split_paragraph( $paragraph, $target_chars, $min_chars );
					$chunks     = array_merge( $chunks, $sub_chunks );
					$current    = '';
				} else {
					$current = $paragraph;
				}
			}
		}

		// Don't forget the last chunk.
		if ( ! empty( $current ) && strlen( $current ) >= $min_chars ) {
			$chunks[] = $this->create_chunk_data( $current );
		} elseif ( ! empty( $current ) && ! empty( $chunks ) ) {
			// Merge small last chunk with previous.
			$last_chunk = array_pop( $chunks );
			$merged     = $last_chunk['text'] . "\n\n" . $current;
			$chunks[]   = $this->create_chunk_data( $merged );
		} elseif ( ! empty( $current ) ) {
			// Single small chunk is better than nothing.
			$chunks[] = $this->create_chunk_data( $current );
		}

		/**
		 * Filter the generated chunks.
		 *
		 * @since 2.0.0
		 * @param array  $chunks        Array of chunk data.
		 * @param string $content       Original content.
		 * @param int    $target_tokens Target tokens per chunk.
		 */
		return apply_filters( 'gg_data_embedding_chunks', $chunks, $content, $target_tokens, $connection_name, $context );
	}

	/**
	 * Split a paragraph into sentence-aware chunks.
	 *
	 * @since 2.0.0
	 * @access protected
	 * @param string $paragraph   The paragraph to split.
	 * @param int    $target_chars Target characters per chunk.
	 * @param int    $min_chars   Minimum characters per chunk.
	 * @return array Array of chunk data arrays.
	 */
	protected function split_paragraph( $paragraph, $target_chars, $min_chars ) {
		// Split on sentence boundaries.
		$sentences = preg_split( '/(?<=[.!?])\s+/', $paragraph );
		$chunks    = array();
		$current   = '';

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( empty( $sentence ) ) {
				continue;
			}

			$sentence_len = strlen( $sentence );
			$current_len  = strlen( $current );

			// If adding sentence keeps us under target, add it.
			if ( $current_len + $sentence_len + 1 <= $target_chars ) {
				$current .= ( $current ? ' ' : '' ) . $sentence;
			} else {
				// Save current chunk if it meets minimum.
				if ( strlen( $current ) >= $min_chars ) {
					$chunks[] = $this->create_chunk_data( $current );
				} elseif ( ! empty( $current ) && ! empty( $chunks ) ) {
					// Merge small chunk with previous.
					$last_chunk = array_pop( $chunks );
					$merged     = $last_chunk['text'] . ' ' . $current;
					$chunks[]   = $this->create_chunk_data( $merged );
				}

				// If sentence itself exceeds target, split on fixed boundaries.
				if ( $sentence_len > $target_chars ) {
					$fixed_chunks = $this->split_fixed( $sentence, $target_chars );
					$chunks       = array_merge( $chunks, $fixed_chunks );
					$current      = '';
				} else {
					$current = $sentence;
				}
			}
		}

		// Handle remaining content.
		if ( strlen( $current ) >= $min_chars ) {
			$chunks[] = $this->create_chunk_data( $current );
		} elseif ( ! empty( $current ) && ! empty( $chunks ) ) {
			$last_chunk = array_pop( $chunks );
			$merged     = $last_chunk['text'] . ' ' . $current;
			$chunks[]   = $this->create_chunk_data( $merged );
		} elseif ( ! empty( $current ) ) {
			$chunks[] = $this->create_chunk_data( $current );
		}

		return $chunks;
	}

	/**
	 * Split content on fixed character boundaries (last resort).
	 *
	 * @since 2.0.0
	 * @access protected
	 * @param string $content      The content to split.
	 * @param int    $target_chars Target characters per chunk.
	 * @return array Array of chunk data arrays.
	 */
	protected function split_fixed( $content, $target_chars ) {
		$chunks  = array();
		$words   = preg_split( '/\s+/', $content );
		$current = '';

		foreach ( $words as $word ) {
			if ( strlen( $current ) + strlen( $word ) + 1 <= $target_chars ) {
				$current .= ( $current ? ' ' : '' ) . $word;
			} else {
				if ( ! empty( $current ) ) {
					$chunks[] = $this->create_chunk_data( $current );
				}
				$current = $word;
			}
		}

		if ( ! empty( $current ) ) {
			$chunks[] = $this->create_chunk_data( $current );
		}

		return $chunks;
	}

	/**
	 * Create chunk data array with text, token count, and hash.
	 *
	 * @since 2.0.0
	 * @access protected
	 * @param string $text The chunk text.
	 * @return array Chunk data with 'text', 'token_count', 'hash'.
	 */
	protected function create_chunk_data( $text ) {
		$text = trim( $text );
		return array(
			'text'        => $text,
			'token_count' => $this->estimate_tokens( $text ),
			'hash'        => md5( $text ),
		);
	}

	/**
	 * Estimate token count from text.
	 *
	 * @since 2.0.0
	 * @param string $text The text to estimate tokens for.
	 * @return int Estimated token count.
	 */
	public function estimate_tokens( $text ) {
		if ( empty( $text ) ) {
			return 0;
		}
		return (int) ceil( strlen( $text ) / self::CHARS_PER_TOKEN );
	}

	/**
	 * Store chunks for a post in the database.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param array  $chunks          Array of chunk data from chunk_content().
	 * @param string $source_hash     Hash from wp_posts_clean.content_hash.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return bool|int Number of chunks stored, or false on failure.
	 */
	public function store_chunks( $post_id, $chunks, $source_hash, $connection_name = 'default' ) {
		$provider = $this->connection_manager->get_provider( $connection_name );
		if ( ! $provider ) {
			$this->logger->log(
				"Failed to get provider for connection: {$connection_name}",
				'error',
				'chunker',
				$connection_name
			);
			return false;
		}

		if ( ! method_exists( $provider, 'bulk_insert' ) ) {
			$this->logger->log(
				sprintf( 'Provider contract violation for connection %s: missing bulk_insert()', $connection_name ),
				'error',
				'chunker',
				$connection_name
			);
			return false;
		}

		// Delete existing chunks for this post first.
		$this->delete_chunks_for_post( $post_id, $connection_name );

		if ( empty( $chunks ) ) {
			return 0;
		}

		// Prepare all chunk rows for bulk insert.
		$rows = array();
		foreach ( $chunks as $index => $chunk ) {
			$rows[] = array(
				'post_id'     => $post_id,
				'chunk_index' => $index,
				'chunk_text'  => $chunk['text'],
				'chunk_hash'  => $chunk['hash'],
				'source_hash' => $source_hash,
				'token_count' => $chunk['token_count'],
			);
		}

		// Enforce contract parity: all providers must support bulk_insert.
		$table_name = $this->get_table_prefix() . 'posts_chunks';
		$result     = $provider->bulk_insert( $table_name, $rows );
		$stored     = is_int( $result ) ? $result : ( false !== $result ? count( $rows ) : 0 );
		$expected   = count( $rows );

		if ( $stored !== $expected ) {
			$this->logger->log(
				sprintf( 'Chunk persistence mismatch for post %d: expected %d, stored %d', $post_id, $expected, $stored ),
				'error',
				'chunker',
				$connection_name
			);
			return false;
		}

		$this->logger->log(
			sprintf( 'Stored %d chunks for post %d', $stored, $post_id ),
			'debug',
			'chunker',
			$connection_name
		);

		return $stored;
	}

	/**
	 * Get chunks for a post from the database.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return array Array of chunk rows, or empty array on failure.
	 */
	public function get_chunks( $post_id, $connection_name = 'default' ) {
		$provider = $this->connection_manager->get_provider( $connection_name );
		if ( ! $provider ) {
			return array();
		}

		$result = $provider->select(
			$this->get_table_prefix() . 'posts_chunks',
			array( 'post_id' => $post_id ),
			'chunk_index ASC'
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Get the source hash for existing chunks.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return string|null Source hash or null if no chunks exist.
	 */
	public function get_chunk_source_hash( $post_id, $connection_name = 'default' ) {
		$chunks = $this->get_chunks( $post_id, $connection_name );
		if ( empty( $chunks ) ) {
			return null;
		}
		return $chunks[0]['source_hash'] ?? null;
	}

	/**
	 * Delete all chunks for a post.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return bool True on success, false on failure.
	 */
	public function delete_chunks_for_post( $post_id, $connection_name = 'default' ) {
		$provider = $this->connection_manager->get_provider( $connection_name );
		if ( ! $provider ) {
			return false;
		}

		return $provider->delete( $this->get_table_prefix() . 'posts_chunks', array( 'post_id' => $post_id ) );
	}

	/**
	 * Check if chunks need regeneration based on content hash.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $clean_hash      Current content_hash from wp_posts_clean.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return bool True if chunks need regeneration.
	 */
	public function needs_rechunking( $post_id, $clean_hash, $connection_name = 'default' ) {
		$stored_hash = $this->get_chunk_source_hash( $post_id, $connection_name );
		return $stored_hash !== $clean_hash;
	}

	/**
	 * Process a post: generate and store chunks if needed.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $content         The cleaned content to chunk.
	 * @param string $content_hash    Hash of the clean content.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @param bool   $force           Skip hash check and force rechunking. Default false.
	 * @return array|false Array of chunk data, or false on failure.
	 */
	public function process_post( $post_id, $content, $content_hash, $connection_name = 'default', $force = false ) {
		// Check if rechunking is needed (skip check if force=true for batch sync performance).
		if ( ! $force && ! $this->needs_rechunking( $post_id, $content_hash, $connection_name ) ) {
			// Return existing chunks.
			return $this->get_chunks( $post_id, $connection_name );
		}

		// Generate new chunks.
		$chunks = $this->chunk_content(
			$content,
			self::DEFAULT_CHUNK_SIZE,
			$connection_name,
			array(
				'post_id'      => $post_id,
				'content_hash' => $content_hash,
				'force'        => $force,
			)
		);

		if ( empty( $chunks ) ) {
			$this->logger->log(
				sprintf( 'No chunks generated for post %d', $post_id ),
				'warning',
				'chunker',
				$connection_name
			);
			return array();
		}

		// Store chunks.
		$stored = $this->store_chunks( $post_id, $chunks, $content_hash, $connection_name );

		if ( false === $stored ) {
			return false;
		}

		// Return the chunks with index added.
		$result = array();
		foreach ( $chunks as $index => $chunk ) {
			$result[] = array_merge( $chunk, array( 'chunk_index' => $index ) );
		}

		return $result;
	}

	/**
	 * Get chunk statistics for a post.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return array Chunk statistics.
	 */
	public function get_chunk_stats( $post_id, $connection_name = 'default' ) {
		$chunks = $this->get_chunks( $post_id, $connection_name );

		if ( empty( $chunks ) ) {
			return array(
				'count'        => 0,
				'total_tokens' => 0,
				'avg_tokens'   => 0,
				'min_tokens'   => 0,
				'max_tokens'   => 0,
			);
		}

		$token_counts = array_column( $chunks, 'token_count' );

		return array(
			'count'        => count( $chunks ),
			'total_tokens' => array_sum( $token_counts ),
			'avg_tokens'   => round( array_sum( $token_counts ) / count( $token_counts ), 2 ),
			'min_tokens'   => min( $token_counts ),
			'max_tokens'   => max( $token_counts ),
		);
	}

	/**
	 * Get combined hash of all chunk hashes for a post.
	 *
	 * Used for vector change detection.
	 *
	 * @since 2.0.0
	 * @param int    $post_id         The post ID.
	 * @param string $connection_name Connection name. Default 'default'.
	 * @return string|null Combined hash or null if no chunks.
	 */
	public function get_combined_chunk_hash( $post_id, $connection_name = 'default' ) {
		$chunks = $this->get_chunks( $post_id, $connection_name );
		if ( empty( $chunks ) ) {
			return null;
		}

		$hashes = array_column( $chunks, 'chunk_hash' );
		return md5( implode( '', $hashes ) );
	}
}
