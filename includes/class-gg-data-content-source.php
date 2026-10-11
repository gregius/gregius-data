<?php
/**
 * Content Source Resolver for Gregius Data
 *
 * Resolves the text content used for cleaning/chunking/embedding on a per-post
 * basis. Regular posts use their post_content; attachments use document
 * extraction (vision) of the attached file. This is the single seam where a
 * post type's content source is decided — everything downstream is unchanged.
 *
 * @package Gregius_Data
 * @subpackage GG_Data/includes
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GG_Data_Content_Source
 *
 * @since 1.0.0
 */
class GG_Data_Content_Source {

	/**
	 * Post meta key: sha256 hash of the source file (dedupe).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_SOURCE_HASH = '_gg_asset_source_hash';

	/**
	 * Post meta key: cached extracted text (reused while the file is unchanged).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_EXTRACTED_TEXT = '_gg_asset_extracted_text';

	/**
	 * Post meta key: extraction algorithm version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_EXTRACTION_VERSION = '_gg_asset_extraction_version';

	/**
	 * Post meta key: extraction status (pending|ready|failed|skipped).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_STATUS = '_gg_asset_status';

	/**
	 * Extraction algorithm version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const EXTRACTION_VERSION = '1.1';

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new GG_Data_Logger();
	}

	/**
	 * Determine whether an attachment is eligible for document extraction.
	 *
	 * The eligibility rule is the single source of truth shared by the sync gate,
	 * the content resolver, the validator count, and the orphan pass:
	 *   - attachment post type, 'inherit' status;
	 *   - has a parent whose post type is enabled for sync;
	 *   - parent's status is enabled for sync (parent "published");
	 *   - parent is not itself an attachment (image-edit copies);
	 *   - application/pdf mime (MVP);
	 *   - not the parent's featured image.
	 *
	 * @since 1.0.0
	 * @param int    $post_id         Attachment post ID.
	 * @param string $connection_name Connection name.
	 * @return bool True when eligible.
	 */
	public static function is_eligible_attachment( $post_id, $connection_name = 'default' ) {
		if ( 'attachment' !== get_post_type( $post_id ) ) {
			return false;
		}

		if ( 'inherit' !== get_post_field( 'post_status', $post_id ) ) {
			return false;
		}

		$parent_id = (int) wp_get_post_parent_id( $post_id );

		if ( $parent_id <= 0 || 'attachment' === get_post_type( $parent_id ) ) {
			return false;
		}

		$settings_manager = new GG_Data_Settings_Manager();

		$enabled_types = $settings_manager->get_with_category(
			'sync',
			$connection_name,
			'sync_enabled_post_types',
			array()
		);

		if ( is_string( $enabled_types ) ) {
			$enabled_types = maybe_unserialize( $enabled_types );
		}

		if ( ! is_array( $enabled_types ) || ! in_array( get_post_type( $parent_id ), $enabled_types, true ) ) {
			return false;
		}

		$enabled_statuses = $settings_manager->get_with_category(
			'sync',
			$connection_name,
			'sync_enabled_statuses',
			array( 'publish' )
		);

		if ( is_string( $enabled_statuses ) ) {
			$enabled_statuses = maybe_unserialize( $enabled_statuses );
		}

		if ( ! is_array( $enabled_statuses ) || empty( $enabled_statuses ) ) {
			$enabled_statuses = array( 'publish' );
		}

		if ( ! in_array( get_post_status( $parent_id ), $enabled_statuses, true ) ) {
			return false;
		}

		if ( 'application/pdf' !== get_post_mime_type( $post_id ) ) {
			return false;
		}

		if ( get_post_thumbnail_id( $parent_id ) === (int) $post_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve the title and content for a post before cleaning.
	 *
	 * Regular posts (and every non-attachment post type) are returned unchanged —
	 * byte-identical to their original inputs. Attachments resolve their content
	 * from document extraction of the attached file.
	 *
	 * @since 1.0.0
	 * @param int    $post_id         Post ID.
	 * @param string $title           Original post title.
	 * @param string $content         Original post content.
	 * @param string $connection_name Connection name.
	 * @return array { title: string, content: string }.
	 */
	public function resolve( $post_id, $title, $content, $connection_name = 'default' ) {
		if ( 'attachment' !== get_post_type( $post_id ) ) {
			return array(
				'title'   => $title,
				'content' => $content,
			);
		}

		$should_process = apply_filters(
			'gg_data_asset_process_post',
			self::is_eligible_attachment( $post_id, $connection_name ),
			$post_id
		);

		if ( ! $should_process ) {
			update_post_meta( $post_id, self::META_STATUS, 'skipped' );

			return array(
				'title'   => $title,
				'content' => '',
			);
		}

		return $this->resolve_attachment( $post_id, $title, $connection_name );
	}

	/**
	 * Resolve the content of an eligible attachment.
	 *
	 * @since 1.0.0
	 * @param int    $post_id         Attachment post ID.
	 * @param string $title           Original title (filename).
	 * @param string $connection_name Connection name.
	 * @return array { title: string, content: string }.
	 */
	private function resolve_attachment( $post_id, $title, $connection_name ) {
		$file_path = get_attached_file( $post_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			// The file may not be attached yet (save_post fires before the file
			// is set); mark pending so a later attachment hook re-processes it.
			update_post_meta( $post_id, self::META_STATUS, 'pending' );

			return array(
				'title'   => $title,
				'content' => '',
			);
		}

		$file_hash = hash_file( 'sha256', $file_path );

		if ( false === $file_hash ) {
			$this->set_failed( $post_id, __( 'Could not hash the attachment file.', 'gregius-data' ) );

			return array(
				'title'   => $title,
				'content' => '',
			);
		}

		$stored_hash    = get_post_meta( $post_id, self::META_SOURCE_HASH, true );
		$stored_version = get_post_meta( $post_id, self::META_EXTRACTION_VERSION, true );
		$cached_text    = get_post_meta( $post_id, self::META_EXTRACTED_TEXT, true );

		// Reuse the cached result only when the file AND the extraction version are unchanged.
		if ( hash_equals( (string) $stored_hash, $file_hash ) && self::EXTRACTION_VERSION === (string) $stored_version ) {
			if ( is_string( $cached_text ) && '' !== $cached_text ) {
				update_post_meta( $post_id, self::META_STATUS, 'ready' );

				return array(
					'title'   => $title,
					'content' => $cached_text,
				);
			}

			// Cached failure — do not re-extract an unchanged failing file.
			if ( 'failed' === get_post_meta( $post_id, self::META_STATUS, true ) ) {
				return array(
					'title'   => $title,
					'content' => '',
				);
			}
		}

		$document_model = ( new GG_Data_Model_Registry() )->get_document_model( $connection_name );

		if ( empty( $document_model ) ) {
			$this->set_failed( $post_id, __( 'No vision-capable document model is active.', 'gregius-data' ) );

			return array(
				'title'   => $title,
				'content' => '',
			);
		}

		// Strip the API key before extraction so a fatal stack trace cannot log it;
		// the extractor re-resolves it from the model registry.
		$model_config = $document_model;
		if ( isset( $model_config['api_key'] ) ) {
			unset( $model_config['api_key'] );
		}
		if ( isset( $model_config['config'] ) && is_array( $model_config['config'] ) && isset( $model_config['config']['api_key'] ) ) {
			unset( $model_config['config']['api_key'] );
		}

		$result = GG_Data_Document_Extractor::extract( $file_path, $model_config );

		if ( is_wp_error( $result ) ) {
			// The selected model is gone: retire it so the UI surfaces it and
			// extraction fails loud — never a silent substitution.
			if ( 'gg_data_document_model_unavailable' === $result->get_error_code() && ! empty( $document_model['provider_model_id'] ) ) {
				( new GG_Data_Model_Registry() )->set_model_lifecycle( $document_model['provider_model_id'], 'retired' );
			}

			$this->set_failed( $post_id, $result->get_error_message(), $file_hash );

			return array(
				'title'   => $title,
				'content' => '',
			);
		}

		$extracted_text = (string) $result['text'];

		if ( '' === $extracted_text ) {
			$this->set_failed( $post_id, __( 'Document extraction returned empty text.', 'gregius-data' ), $file_hash );

			return array(
				'title'   => $title,
				'content' => '',
			);
		}

		update_post_meta( $post_id, self::META_SOURCE_HASH, $file_hash );
		update_post_meta( $post_id, self::META_EXTRACTED_TEXT, $extracted_text );
		update_post_meta( $post_id, self::META_EXTRACTION_VERSION, self::EXTRACTION_VERSION );
		update_post_meta( $post_id, self::META_STATUS, 'ready' );

		$this->logger->log(
			sprintf(
				'Extracted %d chars from attachment %d using %s/%s',
				strlen( $extracted_text ),
				$post_id,
				$document_model['provider'],
				$document_model['provider_model_id']
			),
			'info',
			'sync',
			$connection_name,
			array( 'post_id' => $post_id )
		);

		return array(
			'title'   => $title,
			'content' => $extracted_text,
		);
	}

	/**
	 * Mark an attachment as failed and record the reason.
	 *
	 * @since 1.0.0
	 * @param int    $post_id     Attachment post ID.
	 * @param string $reason      Failure reason.
	 * @param string $source_hash Optional. Source file hash (cached so an unchanged failing file isn't re-extracted).
	 */
	private function set_failed( $post_id, $reason, $source_hash = '' ) {
		update_post_meta( $post_id, self::META_STATUS, 'failed' );
		update_post_meta( $post_id, self::META_EXTRACTION_VERSION, self::EXTRACTION_VERSION );

		if ( '' !== $source_hash ) {
			update_post_meta( $post_id, self::META_SOURCE_HASH, $source_hash );
		}

		$this->logger->log(
			sprintf(
				'Attachment %d extraction failed: %s',
				$post_id,
				$reason
			),
			'warning',
			'sync',
			null,
			array( 'post_id' => $post_id )
		);
	}

	/**
	 * Clear the cached extraction state for all eligible attachments.
	 *
	 * Forces re-extraction (e.g. after changing the document model): deletes the
	 * source hash / cached text / version / status so the resolver re-runs.
	 *
	 * @since 1.0.0
	 * @param string $connection_name Connection name.
	 * @return int Number of attachments whose cache was cleared.
	 */
	public static function reset_extraction_cache( $connection_name = 'default' ) {
		$post_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_mime_type' => 'application/pdf',
			)
		);

		$cleared = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! self::is_eligible_attachment( $post_id, $connection_name ) ) {
				continue;
			}

			delete_post_meta( $post_id, self::META_SOURCE_HASH );
			delete_post_meta( $post_id, self::META_EXTRACTED_TEXT );
			delete_post_meta( $post_id, self::META_EXTRACTION_VERSION );
			delete_post_meta( $post_id, self::META_STATUS );
			++$cleared;
		}

		return $cleared;
	}
}
