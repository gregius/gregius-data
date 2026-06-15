<?php
/**
 * Content sync hooks for Gregius Data
 * Handles real-time content synchronization
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GG_Data_Sync_Hooks' ) ) {

	/**
	 * Sync Hooks class
	 * Manages WordPress hooks for automatic content processing
	 */
	class GG_Data_Sync_Hooks {

		/**
		 * Content processor instance
		 *
		 * @var GG_Data_Content_Processor
		 */
		protected $processor;

		/**
		 * Logger instance
		 *
		 * @var GG_Data_Logger
		 */
		protected $logger;

		/**
		 * Class constructor
		 */
		public function __construct() {
			$this->processor = new GG_Data_Content_Processor();
			$this->logger    = new GG_Data_Logger();

			$this->init_hooks();
		}

		/**
		 * Initialize WordPress hooks
		 */
		protected function init_hooks() {
			// Post delete hooks for cleanup logging.
			add_action( 'before_delete_post', array( $this, 'on_delete_post' ), 10, 1 );
			add_action( 'wp_trash_post', array( $this, 'on_trash_post' ), 10, 1 );
		}

		/**
		 * Handle post deletion
		 *
		 * @param int $post_id Post ID.
		 */
		public function on_delete_post( $post_id ) {
			$this->logger->log(
				"Removing vector record for deleted post ID {$post_id}",
				'debug',
				'sync',
				null,
				array( 'post_id' => $post_id )
			);

			// The database foreign key constraint will handle deletion automatically.
			// But we can log it for monitoring.
			$this->logger->log(
				"Vector record for post ID {$post_id} will be removed by foreign key constraint",
				'info',
				'sync',
				null,
				array( 'post_id' => $post_id )
			);
		}

		/**
		 * Handle post being trashed
		 *
		 * @param int $post_id Post ID.
		 */
		public function on_trash_post( $post_id ) {
			$this->logger->log(
				"Post ID {$post_id} trashed, vector record preserved",
				'debug',
				'sync',
				null,
				array( 'post_id' => $post_id )
			);
			// Keep vector record but could mark as inactive if needed.
		}

		/**
		 * Get content processing status for admin display
		 *
		 * @return array Status information.
		 */
		public function get_processing_status() {
			$total_posts     = wp_count_posts( 'post' )->publish + wp_count_posts( 'page' )->publish;
			$processed_posts = $this->get_processed_posts_count();

			return array(
				'total_posts'      => $total_posts,
				'processed_posts'  => $processed_posts,
				'percentage'       => $total_posts > 0 ? round( ( $processed_posts / $total_posts ) * 100, 1 ) : 0,
				'needs_processing' => $total_posts - $processed_posts,
			);
		}

		/**
		 * Get count of processed posts
		 *
		 * @return int Count of processed posts.
		 */
		protected function get_processed_posts_count() {
			try {
				$db   = new GG_Data_DB();
				$conn = $db->get_connection();

				if ( ! $conn ) {
					return 0;
				}

				// Count processed posts matching the same criteria as total posts count.
				$sql = "
					SELECT COUNT(*)
                    FROM WordPress.gg_data_vectors v
                    JOIN WordPress.wp_posts p ON v.gg_data_post_id = p.ID
					WHERE v.gg_data_post_title_clean IS NOT NULL
					AND p.post_status = 'publish'
					AND p.post_type IN ('post', 'page')
				";

				$stmt = $conn->query( $sql );
				return (int) $stmt->fetchColumn();

			} catch ( Exception $e ) {
				$this->logger->log(
					'Error getting processed posts count: ' . $e->getMessage(),
					'error',
					'sync',
					null,
					array( 'exception' => get_class( $e ) )
				);
				return 0;
			}
		}
	}
}
