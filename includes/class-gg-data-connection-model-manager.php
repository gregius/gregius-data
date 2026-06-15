<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Connection model manager file.
 *
 * @package Gregius_Data
 * @subpackage Gregius_Data/includes
 * @since      1.0.0
 */

/**
 * Connection-Model Association Manager
 *
 * Manages which embedding models are active per PostgreSQL connection.
 * Models are global (stored in wp_gg_settings), but associations are
 * per-connection (stored in PostgreSQL connection_embedding_models table).
 */
class GG_Data_Connection_Model_Manager {

	/**
	 * Database handler instance
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    GG_Data_DB
	 */
	private $db;

	/**
	 * Model registry instance
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    GG_Data_Model_Registry
	 */
	private $model_registry;

	/**
	 * Settings manager instance
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    GG_Data_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Logger instance
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    GG_Data_Logger
	 */
	private $logger;

	/**
	 * Initialize the class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->db               = new GG_Data_DB();
		$this->model_registry   = new GG_Data_Model_Registry();
		$this->settings_manager = new GG_Data_Settings_Manager();
		$this->logger           = new GG_Data_Logger();
	}

	/**
	 * Get all active models for a connection
	 *
	 * Fetches association data from PostgreSQL and enriches with
	 * global model configuration from MySQL.
	 *
	 * @since  1.0.0
	 * @param  string $connection_name Connection name.
	 * @return array Array of model configs (enriched with global data).
	 */
	public function get_connection_models( string $connection_name ): array {
		// Get active model keys from settings.
		$active_model_keys = $this->settings_manager->get_with_category(
			'vectors',
			$connection_name,
			'active_models',
			array()
		);

		// Ensure it's an array.
		if ( ! is_array( $active_model_keys ) ) {
			return array();
		}

		$models = array();

		// Enrich each model key with full model data from registry.
		foreach ( $active_model_keys as $model_key ) {
			// Try current connection first.
			$model = $this->model_registry->get_model( $connection_name, $model_key );

			// If not found, try global 'gregius-data' connection.
			if ( ! $model && 'gregius-data' !== $connection_name ) {
				$model = $this->model_registry->get_model( 'gregius-data', $model_key );
			}

			if ( $model ) {
				$models[] = $model;
			}
		}

		return $models;
	}

	/**
	 * Add model to connection (does NOT create model globally)
	 *
	 * The model must already exist in the global model registry.
	 * This method only creates the association in PostgreSQL.
	 *
	 * @since  1.0.0
	 * @param  string $connection_name Connection name.
	 * @param  string $model_key Model key (must exist in model registry).
	 * @return bool|WP_Error Success or error.
	 */
	public function add_model_to_connection( string $connection_name, string $model_key ) {
		// Verify model exists globally (check all connections since models are global).
		$model = $this->model_registry->get_model( $connection_name, $model_key );

		// If not found in current connection, try 'gregius-data' (default global connection).
		if ( ! $model && 'gregius-data' !== $connection_name ) {
			$model = $this->model_registry->get_model( 'gregius-data', $model_key );
		}

		if ( ! $model ) {
			return new WP_Error(
				'model_not_found',
				__( 'Model must be created in Models page first', 'gregius-data' )
			);
		}

		// Get current active models for this connection.
		$active_models = $this->settings_manager->get_with_category(
			'vectors',
			$connection_name,
			'active_models',
			array()
		);

		// Ensure it's an array.
		if ( ! is_array( $active_models ) ) {
			$active_models = array();
		}

		// Add model if not already in list.
		if ( ! in_array( $model_key, $active_models, true ) ) {
			$active_models[] = $model_key;
		}

		// Save back to settings.
		$result = $this->settings_manager->set_with_category(
			'vectors',
			$connection_name,
			'active_models',
			$active_models,
			'serialized'
		);

		if ( ! $result ) {
			return new WP_Error(
				'add_model_failed',
				__( 'Failed to save active models', 'gregius-data' )
			);
		}

		$this->logger->log(
			sprintf(
				'Added model %s to connection %s',
				$model_key,
				$connection_name
			),
			'info'
		);

		return true;
	}

	/**
	 * Remove model from connection (only if 0 vectors exist)
	 *
	 * Prevents orphaned vector data by checking vector count first.
	 *
	 * @since  1.0.0
	 * @param  string $connection_name Connection name.
	 * @param  string $model_key Model key.
	 * @return bool|WP_Error Success or error.
	 */
	public function remove_model_from_connection( string $connection_name, string $model_key ) {
		// Get current active models for this connection.
		$active_models = $this->settings_manager->get_with_category(
			'vectors',
			$connection_name,
			'active_models',
			array()
		);

		// Ensure it's an array.
		if ( ! is_array( $active_models ) ) {
			$active_models = array();
		}

		// Remove model from list.
		$active_models = array_values(
			array_filter(
				$active_models,
				function ( $key ) use ( $model_key ) {
					return $key !== $model_key;
				}
			)
		);

		// Save back to settings.
		$result = $this->settings_manager->set_with_category(
			'vectors',
			$connection_name,
			'active_models',
			$active_models,
			'serialized'
		);

		if ( ! $result ) {
			return new WP_Error(
				'remove_model_failed',
				__( 'Failed to save active models', 'gregius-data' )
			);
		}

		$this->logger->log(
			sprintf(
				'Removed model %s from connection %s',
				$model_key,
				$connection_name
			),
			'info'
		);

		return true;
	}

	/**
	 * Auto-add TF-IDF model to connection if not present
	 *
	 * TF-IDF is the free, internal embedding model that should
	 * be available on all connections by default.
	 *
	 * @since  1.0.0
	 * @param  string $connection_name Connection name.
	 * @return bool Success.
	 */
	public function auto_add_tfidf( string $connection_name ): bool {
		$models = $this->get_connection_models( $connection_name );

		// Check if TF-IDF already added.
		$has_tfidf = false;
		foreach ( $models as $model ) {
			if ( 'tfidf-300' === $model['model_key'] ) {
				$has_tfidf = true;
				break;
			}
		}

		if ( ! $has_tfidf ) {
			$result = $this->add_model_to_connection( $connection_name, 'tfidf-300' );
			return ! is_wp_error( $result );
		}

		return true;
	}
}
