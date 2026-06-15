<?php
/**
 * Token Counter and Usage Tracker
 *
 * Estimates token counts and tracks OpenAI token usage in the wp_gg_settings table.
 * Uses category-scoped settings for per-connection token tracking.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token Counter class.
 *
 * Provides methods for:
 * - Token count estimation
 * - Usage tracking in settings table
 * - Cost estimation per model
 * - Usage statistics retrieval
 *
 * @since 1.0.0
 */
class GG_Data_Token_Counter {

	/**
	 * Settings manager instance.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var    GG_Data_Settings_Manager
	 */
	protected $settings;

	/**
	 * Tracked model IDs for usage summaries.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $tracked_models = array(
		'gpt-4o',
		'gpt-4o-mini',
	);

	/**
	 * Initialize the token counter.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->settings = new GG_Data_Settings_Manager();
	}

	/**
	 * Estimate token count from text.
	 *
	 * Uses a rough approximation of ~4 characters per token for English text.
	 * For more accurate counts, consider using tiktoken library or OpenAI's tokenizer API.
	 *
	 * @since 1.0.0
	 * @param string $text The text to estimate tokens for.
	 * @return int Estimated token count.
	 */
	public function estimate_tokens( $text ) {
		if ( empty( $text ) ) {
			return 0;
		}

		// Rough estimate: ~4 characters per token.
		$char_count = strlen( $text );
		return (int) ceil( $char_count / 4 );
	}

	/**
	 * Track token usage in settings table.
	 *
	 * Stores usage metrics in the rag_tfidf_300_usage category (or other RAG type),
	 * scoped to the specified connection. Updates total tokens, query count,
	 * per-model tokens, and last query timestamp.
	 *
	 * @since 1.0.0
	 * @param int    $tokens_used     Number of tokens consumed.
	 * @param string $model           The GPT model used (e.g., 'gpt-4o-mini').
	 * @param string $query           The user's query (for logging).
	 * @param string $connection_name Connection name for scoping. Default 'default'.
	 * @param string $rag_type        RAG type identifier (e.g., 'rag_tfidf_300'). Default 'rag_tfidf_300'.
	 */
	public function track_usage( $tokens_used, $model, $query, $connection_name = 'default', $rag_type = 'rag_tfidf_300' ) {
		// Build usage category name (e.g., 'rag_tfidf_300_usage').
		$usage_category = $rag_type . '_usage';

		// Get current totals from settings.
		$total_tokens  = (int) $this->settings->get_with_category( $usage_category, $connection_name, 'total_tokens', 0 );
		$total_queries = (int) $this->settings->get_with_category( $usage_category, $connection_name, 'total_queries', 0 );

		// Update totals.
		$this->settings->set_with_category_public( $usage_category, $connection_name, 'total_tokens', $total_tokens + $tokens_used );
		$this->settings->set_with_category_public( $usage_category, $connection_name, 'total_queries', $total_queries + 1 );
		$this->settings->set_with_category_public( $usage_category, $connection_name, 'last_query_at', current_time( 'mysql' ) );

		// Track by model (e.g., 'tokens_gpt_4o_mini').
		$model_key    = 'tokens_' . str_replace( array( '-', '.' ), '_', $model );
		$model_tokens = (int) $this->settings->get_with_category( $usage_category, $connection_name, $model_key, 0 );
		$this->settings->set_with_category_public( $usage_category, $connection_name, $model_key, $model_tokens + $tokens_used );

		/**
		 * Fires after token usage is tracked.
		 *
		 * @since 1.0.0
		 * @param int    $tokens_used     Tokens consumed.
		 * @param string $model           Model used.
		 * @param string $query           User query.
		 * @param string $connection_name Connection name.
		 * @param string $rag_type        RAG type identifier.
		 */
		do_action( 'gg_data_token_usage_tracked', $tokens_used, $model, $query, $connection_name, $rag_type );
	}

	/**
	 * Get usage statistics from settings table.
	 *
	 * Retrieves token usage for a specific connection and RAG type.
	 *
	 * @since 1.0.0
	 * @param string $connection_name Connection name. Default 'default'.
	 * @param string $rag_type        RAG type identifier. Default 'rag_tfidf_300'.
	 * @return array {
	 *     Usage statistics.
	 *
	 *     @type int    $total_queries Total queries made.
	 *     @type int    $total_tokens  Total tokens consumed.
	 *     @type array  $by_model      Tokens consumed per model.
	 *     @type string $last_query_at Timestamp of last query.
	 * }
	 */
	public function get_usage_stats( $connection_name = 'default', $rag_type = 'rag_tfidf_300' ) {
		$usage_category = $rag_type . '_usage';

		$total_tokens  = (int) $this->settings->get_with_category( $usage_category, $connection_name, 'total_tokens', 0 );
		$total_queries = (int) $this->settings->get_with_category( $usage_category, $connection_name, 'total_queries', 0 );
		$last_query_at = $this->settings->get_with_category( $usage_category, $connection_name, 'last_query_at', null );

		// Calculate by-model usage.
		$by_model = array();

		foreach ( $this->tracked_models as $model ) {
			$model_key    = 'tokens_' . str_replace( array( '-', '.' ), '_', $model );
			$model_tokens = (int) $this->settings->get_with_category( $usage_category, $connection_name, $model_key, 0 );

			if ( $model_tokens > 0 ) {
				$by_model[ $model ] = $model_tokens;
			}
		}

		return array(
			'total_queries' => $total_queries,
			'total_tokens'  => $total_tokens,
			'by_model'      => $by_model,
			'last_query_at' => $last_query_at,
		);
	}

	/**
	/**
	 * Reset usage statistics for a connection.
	 *
	 * Clears all token usage data for the specified connection and RAG type.
	 *
	 * @since 1.0.0
	 * @param string $connection_name Connection name. Default 'default'.
	 * @param string $rag_type        RAG type identifier. Default 'rag_tfidf_300'.
	 * @return bool True on success.
	 */
	public function reset_usage_stats( $connection_name = 'default', $rag_type = 'rag_tfidf_300' ) {
		$usage_category = $rag_type . '_usage';

		// Delete all usage settings for this connection.
		global $wpdb;
		$table = $wpdb->prefix . 'gg_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete usage stats from custom plugin table wp_gg_settings
		$deleted = $wpdb->delete(
			$table,
			array(
				'connection_name' => $connection_name,
				'category'        => $usage_category,
			)
		);

		/**
		 * Fires after usage statistics are reset.
		 *
		 * @since 1.0.0
		 * @param string $connection_name Connection name.
		 * @param string $rag_type        RAG type identifier.
		 */
		do_action( 'gg_data_token_usage_reset', $connection_name, $rag_type );

		return false !== $deleted;
	}

	/**
	 * Track usage specifically for a Model Entity.
	 *
	 * Stores usage metrics in the 'model_usage' category, scoped to the model ID.
	 *
	 * @since 1.0.0
	 * @param string $model_id    The Model ID (e.g., 'model_marketing_gpt').
	 * @param int    $tokens_used Number of tokens consumed.
	 */
	public function track_model_usage( $model_id, $tokens_used ) {
		if ( empty( $model_id ) ) {
			return;
		}

		$category = 'model_usage';

		// Get current totals.
		$total_tokens  = (int) $this->settings->get_with_category( $category, $model_id, 'total_tokens', 0 );
		$total_queries = (int) $this->settings->get_with_category( $category, $model_id, 'total_queries', 0 );

		// Update totals.
		$this->settings->set_with_category_public( $category, $model_id, 'total_tokens', $total_tokens + $tokens_used );
		$this->settings->set_with_category_public( $category, $model_id, 'total_queries', $total_queries + 1 );
		$this->settings->set_with_category_public( $category, $model_id, 'last_used_at', current_time( 'mysql' ) );
	}

	/**
	 * Get usage stats for a Model Entity.
	 *
	 * @since 1.0.0
	 * @param string $model_id The Model ID.
	 * @return array Usage stats.
	 */
	public function get_model_usage_stats( $model_id ) {
		$category = 'model_usage';

		return array(
			'total_tokens'  => (int) $this->settings->get_with_category( $category, $model_id, 'total_tokens', 0 ),
			'total_queries' => (int) $this->settings->get_with_category( $category, $model_id, 'total_queries', 0 ),
			'last_used_at'  => $this->settings->get_with_category( $category, $model_id, 'last_used_at', null ),
		);
	}

	/**
	 * Reset usage stats for a Model Entity.
	 *
	 * @since 1.0.0
	 * @param string $model_id The Model ID.
	 * @return bool True on success.
	 */
	public function reset_model_usage_stats( $model_id ) {
		if ( empty( $model_id ) ) {
			return false;
		}

		$category = 'model_usage';

		// Delete all usage settings for this model.
		global $wpdb;
		$table = $wpdb->prefix . 'gg_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$table,
			array(
				'connection_name' => $model_id,
				'category'        => $category,
			)
		);

		return false !== $deleted;
	}
}
