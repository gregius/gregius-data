<?php
/**
 * Sync Service
 *
 * Central service for handling synchronization operations.
 * Acts as a facade for specific sync implementations (Post, Postmeta, Taxonomy).
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Sync_Service
 */
class GG_Data_Sync_Service {

	/**
	 * Connection name
	 *
	 * @var string
	 */
	protected $connection_name;

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @param string $connection_name Connection name.
	 */
	public function __construct( $connection_name ) {
		$this->connection_name = $connection_name;
		$this->logger          = new GG_Data_Logger();
	}

	/**
	 * Batch sync posts of a specific type
	 *
	 * @param string $post_type  Post type slug.
	 * @param int    $batch_size Batch size.
	 * @param int    $offset     Offset.
	 * @param int    $site_id    Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function batch_sync_post_type( $post_type, $batch_size, $offset, $site_id = 1 ) {
		$this->logger->log(
			sprintf( 'Sync Service: Starting batch sync for post type "%s"', $post_type ),
			'info',
			'sync',
			$this->connection_name,
			array(
				'post_type'  => $post_type,
				'batch_size' => $batch_size,
				'offset'     => $offset,
				'site_id'    => $site_id,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-post-sync.php';
		$syncer = new GG_Data_Post_Sync( $this->connection_name );
		$result = $syncer->batch_sync_post_type( $post_type, $batch_size, $offset, $site_id );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed batch sync for post type "%s" - %d synced',
				$post_type,
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'post_type' => $post_type,
				'synced'    => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed'    => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'     => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Sync all posts of a specific type (Legacy/Full Sync)
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $site_id   Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function sync_post_type( $post_type, $site_id = 1 ) {
		$this->logger->log(
			sprintf( 'Sync Service: Starting full sync for post type "%s"', $post_type ),
			'info',
			'sync',
			$this->connection_name,
			array(
				'post_type' => $post_type,
				'site_id'   => $site_id,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-post-sync.php';
		$syncer = new GG_Data_Post_Sync( $this->connection_name );
		$result = $syncer->sync_post_type( $post_type, $site_id );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed full sync for post type "%s" - %d synced',
				$post_type,
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'post_type' => $post_type,
				'synced'    => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed'    => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'     => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Batch sync postmeta
	 *
	 * @param int $batch_size Batch size.
	 * @param int $offset     Offset.
	 * @param int $site_id    Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function batch_sync_postmeta( $batch_size, $offset, $site_id = 1 ) {
		$this->logger->log(
			'Sync Service: Starting batch sync for postmeta',
			'info',
			'sync',
			$this->connection_name,
			array(
				'batch_size' => $batch_size,
				'offset'     => $offset,
				'site_id'    => $site_id,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-postmeta-sync.php';
		$syncer = new GG_Data_Postmeta_Sync( $this->connection_name );
		$result = $syncer->batch_sync_postmeta( $batch_size, $offset, $site_id );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed batch sync for postmeta - %d synced',
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'synced' => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'  => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Sync postmeta bulk (Legacy)
	 *
	 * @param int $batch_size Batch size.
	 * @param int $site_id    Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function sync_postmeta_bulk( $batch_size, $site_id = 1 ) {
		$this->logger->log(
			'Sync Service: Starting bulk sync for postmeta',
			'info',
			'sync',
			$this->connection_name,
			array(
				'batch_size' => $batch_size,
				'site_id'    => $site_id,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-postmeta-sync.php';
		$syncer = new GG_Data_Postmeta_Sync( $this->connection_name );
		$result = $syncer->sync_postmeta_bulk( $batch_size, $site_id );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed bulk sync for postmeta - %d synced',
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'synced' => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'  => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Batch sync terms
	 *
	 * @param int $batch_size Batch size.
	 * @param int $offset     Offset.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function batch_sync_terms( $batch_size, $offset ) {
		$this->logger->log(
			'Sync Service: Starting batch sync for terms',
			'info',
			'sync',
			$this->connection_name,
			array(
				'batch_size' => $batch_size,
				'offset'     => $offset,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		$result = $syncer->batch_sync_terms( $batch_size, $offset );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed batch sync for terms - %d synced',
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'synced' => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'  => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Batch sync term taxonomies
	 *
	 * @param int $batch_size Batch size.
	 * @param int $offset     Offset.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function batch_sync_term_taxonomies( $batch_size, $offset ) {
		$this->logger->log(
			'Sync Service: Starting batch sync for term taxonomies',
			'info',
			'sync',
			$this->connection_name,
			array(
				'batch_size' => $batch_size,
				'offset'     => $offset,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		$result = $syncer->batch_sync_term_taxonomies( $batch_size, $offset );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed batch sync for term taxonomies - %d synced',
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'synced' => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'  => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Batch sync term relationships
	 *
	 * @param int $batch_size Batch size.
	 * @param int $offset     Offset.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function batch_sync_term_relationships( $batch_size, $offset ) {
		$this->logger->log(
			'Sync Service: Starting batch sync for term relationships',
			'info',
			'sync',
			$this->connection_name,
			array(
				'batch_size' => $batch_size,
				'offset'     => $offset,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		$result = $syncer->batch_sync_term_relationships( $batch_size, $offset );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed batch sync for term relationships - %d synced',
				isset( $result['synced'] ) ? $result['synced'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'synced' => isset( $result['synced'] ) ? $result['synced'] : 0,
				'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
				'total'  => isset( $result['total'] ) ? $result['total'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Full taxonomy sync (Legacy)
	 *
	 * @param int $site_id Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function sync_taxonomy_full( $site_id = 1 ) {
		$this->logger->log(
			'Sync Service: Starting full taxonomy sync',
			'info',
			'sync',
			$this->connection_name,
			array( 'site_id' => $site_id )
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		$result = $syncer->full_sync( $site_id );

		$this->logger->log(
			'Sync Service: Completed full taxonomy sync',
			'info',
			'sync',
			$this->connection_name,
			array(
				'terms_synced'         => isset( $result['terms']['synced'] ) ? $result['terms']['synced'] : 0,
				'taxonomies_synced'    => isset( $result['taxonomies']['synced'] ) ? $result['taxonomies']['synced'] : 0,
				'relationships_synced' => isset( $result['relationships']['synced'] ) ? $result['relationships']['synced'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Sync terms only (Legacy/Debug)
	 *
	 * @param int $site_id Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function sync_taxonomy_terms_only( $site_id = 1 ) {
		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		return $syncer->sync_terms( $site_id );
	}

	/**
	 * Sync term taxonomies only (Legacy/Debug)
	 *
	 * @param int $site_id Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function sync_taxonomy_taxonomies_only( $site_id = 1 ) {
		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		return $syncer->sync_term_taxonomies( $site_id );
	}

	/**
	 * Sync term relationships only (Legacy/Debug)
	 *
	 * @param int $site_id Site ID.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function sync_taxonomy_relationships_only( $site_id = 1 ) {
		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php';
		$syncer = new GG_Data_Taxonomy_Sync( $this->connection_name );
		return $syncer->sync_term_relationships( $site_id );
	}

	/**
	 * Clean specific post type content
	 *
	 * @param string $post_type  Post type slug.
	 * @param int    $batch_size Batch size.
	 * @param int    $offset     Offset.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function clean_post_type( $post_type, $batch_size, $offset = 0 ) {
		$this->logger->log(
			sprintf( 'Sync Service: Starting clean for post type "%s"', $post_type ),
			'info',
			'sync',
			$this->connection_name,
			array(
				'post_type'  => $post_type,
				'batch_size' => $batch_size,
				'offset'     => $offset,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-clean-batch.php';
		$cleaner = new GG_Data_Clean_Batch( $this->connection_name );

		if ( method_exists( $cleaner, 'batch_clean_post_type' ) ) {
			$result = $cleaner->batch_clean_post_type( $post_type, $batch_size, $offset );
		} else {
			$result = $cleaner->clean_post_type( $post_type, $batch_size );
		}

		$this->logger->log(
			sprintf(
				'Sync Service: Completed clean for post type "%s" - %d deleted',
				$post_type,
				isset( $result['deleted'] ) ? $result['deleted'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'post_type' => $post_type,
				'deleted'   => isset( $result['deleted'] ) ? $result['deleted'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Delete orphan records
	 *
	 * @param string $type       Type of orphan (post, postmeta, term, etc.).
	 * @param int    $batch_size Batch size.
	 * @param int    $offset     Offset.
	 * @param array  $args       Additional arguments.
	 * @return array Result array.
	 * @throws Exception On error.
	 */
	public function delete_orphans( $type, $batch_size, $offset = 0, $args = array() ) {
		$this->logger->log(
			sprintf( 'Sync Service: Starting orphan cleanup for type "%s"', $type ),
			'info',
			'sync',
			$this->connection_name,
			array(
				'orphan_type' => $type,
				'batch_size'  => $batch_size,
				'offset'      => $offset,
			)
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-orphan-manager.php';
		$manager = new GG_Data_Orphan_Manager( $this->connection_name );
		$result  = $manager->process_orphans( $type, $batch_size, $offset, $args );

		$this->logger->log(
			sprintf(
				'Sync Service: Completed orphan cleanup for type "%s" - %d deleted',
				$type,
				isset( $result['deleted'] ) ? $result['deleted'] : 0
			),
			'info',
			'sync',
			$this->connection_name,
			array(
				'orphan_type' => $type,
				'deleted'     => isset( $result['deleted'] ) ? $result['deleted'] : 0,
				'remaining'   => isset( $result['remaining'] ) ? $result['remaining'] : 0,
			)
		);

		return $result;
	}

	/**
	 * Validate sync status
	 *
	 * @return array Validation results.
	 */
	public function validate_sync() {
		$this->logger->log(
			'Sync Service: Starting sync validation',
			'info',
			'sync',
			$this->connection_name
		);

		require_once GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-sync-validator.php';
		$validator = new GG_Data_Sync_Validator();
		$result    = $validator->run_validation( $this->connection_name );

		$this->logger->log(
			'Sync Service: Completed sync validation',
			'info',
			'sync',
			$this->connection_name,
			array(
				'is_valid' => isset( $result['is_valid'] ) ? $result['is_valid'] : false,
				'issues'   => isset( $result['issues'] ) ? count( $result['issues'] ) : 0,
			)
		);

		return $result;
	}
}
