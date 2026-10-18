<?php
/**
 * Main plugin class for Gregius Data
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gregius_Data' ) ) {

	/**
	 * Main plugin class
	 */
	class Gregius_Data {

		/**
		 * The single instance of the class
		 *
		 * @var Gregius_Data
		 */
		protected static $instance = null;

		/**
		 * Database connection handler
		 *
		 * @var Gregius_Data_DB
		 */
		public $db;

		/**
		 * Logger instance
		 *
		 * @var Gregius_Data_Logger
		 */
		public $logger;

		/**
		 * Content hooks instance
		 *
		 * @var Gregius_Data_Sync_Hooks
		 */
		public $content_hooks;

		/**
		 * Content processor instance
		 *
		 * @var Gregius_Data_Content_Processor
		 */
		public $content_processor;

		/**
		 * REST API instance
		 *
		 * @var Gregius_Data_REST_API
		 */
		public $rest_api;

		/**
		 * Connection manager instance
		 *
		 * @var Gregius_Data_Connection_Manager
		 */
		public $connection_manager;

		/**
		 * Abilities manager instance
		 *
		 * @var Gregius_Data_Abilities_Manager
		 */
		public $abilities_manager;

		/**
		 * Lifecycle hooks instance
		 *
		 * @var Gregius_Data_Lifecycle_Hooks
		 */
		public $lifecycle_hooks;

		/**
		 * Class constructor
		 */
		public function __construct() {
			$this->db     = new Gregius_Data_DB();
			$this->logger = new Gregius_Data_Logger();
		}

		/**
		 * Initialize the plugin
		 */
		public function init() {
			// Initialize content processing hooks.
			$this->init_content_processing();

			// Initialize REST API.
			$this->init_rest_api();

			// Initialize connection manager.
			$this->init_connection_manager();

			// Initialize abilities manager.
			$this->init_abilities_manager();

			// Initialize lifecycle hooks.
			$this->init_lifecycle_hooks();
		}

		/**
		 * Initialize content processing
		 */
		protected function init_content_processing() {
			// Only initialize if the gregius_data_vectors table exists.
			if ( $this->is_vectors_table_available() ) {
				$this->content_processor = new Gregius_Data_Content_Processor();
				$this->content_hooks     = new Gregius_Data_Sync_Hooks();
				$this->logger->log( 'Content processing initialized', 'debug', 'system' );
			} else {
				$this->logger->log( 'Content processing skipped - vectors table not available', 'debug', 'system' );
			}
		}

		/**
		 * Initialize REST API
		 */
		protected function init_rest_api() {
			if ( class_exists( 'Gregius_Data_REST_API' ) ) {
				$this->rest_api = new Gregius_Data_REST_API();
				$this->rest_api->init();
				$this->logger->log( 'REST API initialized', 'debug', 'system' );
			} else {
				$this->logger->log( 'REST API class not found', 'warning', 'system' );
			}
		}

		/**
		 * Initialize connection manager
		 *
		 * @since 1.0.0
		 */
		protected function init_connection_manager() {
			if ( class_exists( 'Gregius_Data_Connection_Manager' ) ) {
				$this->connection_manager = new Gregius_Data_Connection_Manager();
				$this->logger->log( 'Connection manager initialized', 'debug', 'system' );
			} else {
				$this->logger->log( 'Connection manager class not found', 'warning', 'system' );
			}
		}

		/**
		 * Initialize abilities manager
		 *
		 * @since 1.0.0
		 */
		protected function init_abilities_manager() {
			if ( class_exists( 'Gregius_Data_Abilities_Manager' ) ) {
				$this->abilities_manager = new Gregius_Data_Abilities_Manager();
				$this->abilities_manager->init();
				$this->logger->log( 'Abilities manager initialized', 'debug', 'system' );
			} else {
				$this->logger->log( 'Abilities manager class not found', 'warning', 'system' );
			}
		}

		/**
		 * Initialize lifecycle hooks
		 *
		 * @since 1.0.0
		 */
		protected function init_lifecycle_hooks() {
			if ( class_exists( 'Gregius_Data_Lifecycle_Hooks' ) ) {
				if ( ! isset( $this->connection_manager ) ) {
					$this->logger->log( 'Cannot initialize lifecycle hooks: Connection manager not initialized', 'warning', 'system' );
					return;
				}

				$settings_manager      = new Gregius_Data_Settings_Manager();
				$this->lifecycle_hooks = new Gregius_Data_Lifecycle_Hooks( $this->connection_manager, $settings_manager );

				// Schedule orphan cleanup task.
				Gregius_Data_Lifecycle_Hooks::schedule_orphan_cleanup();

				$this->logger->log( 'Lifecycle hooks initialized', 'debug', 'system' );
			} else {
				$this->logger->log( 'Lifecycle hooks class not found', 'warning', 'system' );
			}
		}

		/**
		 * Check if vectors table is available
		 *
		 * @return bool True if table exists and accessible.
		 */
		protected function is_vectors_table_available() {
			try {
				// get_connection() now auto-detects the first active connection.
				$conn = $this->db->get_connection();
				if ( ! $conn ) {
					// No PDO connection available (PostgREST or no connections configured).
					return false;
				}

				$stmt   = $conn->query( "SELECT to_regclass('WordPress.gregius_data_vectors') AS exists" );
				$result = $stmt->fetchColumn();

				return ! empty( $result );
			} catch ( Exception $e ) {
				$this->logger->log( 'Error checking vectors table availability: ' . $e->getMessage(), 'error', 'system', null, array( 'error' => $e->getMessage() ) );
				return false;
			}
		}

		/**
		 * Check if a meta key should be skipped for syncing
		 *
		 * @param string $meta_key Meta key.
		 * @return bool Whether to skip this meta key.
		 */
		protected function should_skip_meta_key( $meta_key ) {
			// List of meta keys to skip.
			$skip_keys = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_trash_meta_status',
				'_wp_trash_meta_time',
				'_wp_desired_post_slug',
			);

			// Skip keys starting with underscore by default.
			$skip_underscore = get_option( 'gregius_data_skip_underscore_meta', false );

			// Let users filter the list of keys to skip.
			$skip_keys = apply_filters( 'gregius_data_skip_meta_keys', $skip_keys );

			return in_array( $meta_key, $skip_keys, true ) || ( $skip_underscore && strpos( $meta_key, '_' ) === 0 );
		}

		/**
		 * Check if sync is enabled
		 *
		 * @return bool Whether sync is enabled.
		 */     public function is_sync_enabled() {
			return get_option( 'gregius_data_enabled', false );
}

		/**
		 * Main instance of the plugin
		 *
		 * @return Gregius_Data Main instance
		 */
public static function instance() {
	if ( is_null( self::$instance ) ) {
		self::$instance = new self();
	}
	return self::$instance;
}
	}
}
