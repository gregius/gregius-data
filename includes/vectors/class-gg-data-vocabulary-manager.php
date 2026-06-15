<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Vocabulary Manager for TF-IDF Caching
 *
 * Manages vocabulary cache for TF-IDF vector generation to avoid
 * rebuilding global vocabulary for every batch.
 *
 * Architecture:
 * - Metadata: MySQL wp_gg_settings (lightweight, fast queries)
 * - Vocabulary Data: PostgreSQL wp_posts_vocabulary_cache (JSONB storage)
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! class_exists( 'GG_Data_Vocabulary_Manager' ) ) {

	/**
	 * Vocabulary Manager class
	 */
	class GG_Data_Vocabulary_Manager {

		/**
		 * Database connection handler
		 *
		 * @var GG_Data_DB
		 */
		protected $db;

		/**
		 * Logger instance
		 *
		 * @var GG_Data_Logger
		 */
		protected $logger;

		/**
		 * Settings manager instance
		 *
		 * @var GG_Data_Settings_Manager
		 */
		protected $settings;

		/**
		 * TF-IDF embeddings instance for vocabulary building
		 *
		 * @var GG_Data_TFIDF_300_Embeddings
		 */
		protected $embeddings;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->db         = new GG_Data_DB();
			$this->logger     = new GG_Data_Logger();
			$this->settings   = new GG_Data_Settings_Manager();
			$this->embeddings = new GG_Data_TFIDF_300_Embeddings();
		}

		/**
		 * Prepare vocabulary from wp_posts_clean and cache it
		 *
		 * Builds global TF-IDF vocabulary from ALL posts and stores:
		 * - Metadata in MySQL wp_gg_settings
		 * - Vocabulary data in PostgreSQL wp_posts_vocabulary_cache
		 *
		 * @param string $connection_name PostgreSQL connection name.
		 * @return array Result array with success status and message.
		 */
		public function prepare_vocabulary( $connection_name = 'default' ) {
			$start_time = microtime( true );

			try {
				// Detect provider type.
				$config = $this->settings->get_connection( $connection_name );
				if ( ! $config ) {
					return array(
						'success' => false,
						'message' => __( 'Connection configuration not found.', 'gregius-data' ),
					);
				}

				$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

				// Route to appropriate method based on provider.
				if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
					return $this->prepare_vocabulary_supabase( $connection_name, $start_time );
				} else {
					return $this->prepare_vocabulary_pdo( $connection_name, $start_time );
				}
			} catch ( Exception $e ) {
				$this->logger->log(
					'Vocabulary preparation error: ' . $e->getMessage(),
					'error',
					'vectors',
					$connection_name,
					array( 'exception' => get_class( $e ) )
				);
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Error: %s', 'gregius-data' ),
						$e->getMessage()
					),
				);
			}
		}

		/**
		 * Prepare vocabulary using PDO (PostgreSQL direct connection)
		 *
		 * @param string $connection_name Connection name.
		 * @param float  $start_time      Start timestamp.
		 * @return array Result array.
		 */
		private function prepare_vocabulary_pdo( $connection_name, $start_time ) {
			$conn = $this->db->get_connection( $connection_name );
			if ( ! $conn ) {
				return array(
					'success' => false,
					'message' => __( 'Could not connect to PostgreSQL database.', 'gregius-data' ),
				);
			}

			// Get all posts from wp_posts_clean.
			$sql  = 'SELECT * FROM wp_posts_clean WHERE 1=1 ORDER BY post_id';
			$stmt = $conn->prepare( $sql );
			$stmt->execute();
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
			$all_posts = $stmt->fetchAll( PDO::FETCH_ASSOC );           if ( empty( $all_posts ) ) {
				return array(
					'success' => false,
					'message' => __( 'No posts found in wp_posts_clean. Run sync and clean first.', 'gregius-data' ),
				);
			}

			return $this->process_vocabulary_build( $all_posts, $connection_name, $start_time, $conn );
		}

		/**
		 * Prepare vocabulary using Supabase REST API
		 *
		 * @param string $connection_name Connection name.
		 * @param float  $start_time      Start timestamp.
		 * @return array Result array.
		 */
		private function prepare_vocabulary_supabase( $connection_name, $start_time ) {
			// Get connection config.
			$config = $this->settings->get_connection( $connection_name );
			if ( ! $config ) {
				return array(
					'success' => false,
					'message' => __( 'Connection configuration not found.', 'gregius-data' ),
				);
			}

			if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
				require_once dirname( __DIR__ ) . '/providers/class-gg-postgrest-provider.php';
			}

			$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
			if ( isset( $runtime_config['error'] ) ) {
				return array(
					'success' => false,
					'message' => $runtime_config['error'],
				);
			}

			$project_url = $runtime_config['project_url'];

			// Query wp_posts_clean via REST API with pagination.
			$all_posts = array();
			$offset    = 0;
			$limit     = 1000;
			$more      = true;

			while ( $more ) {
				$url = $project_url . '/rest/v1/wp_posts_clean?select=*&order=post_id.asc&offset=' . $offset . '&limit=' . $limit;

				$response = wp_safe_remote_get(
					$url,
					array(
						'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'] ),
						'timeout' => 60,
					)
				);

				if ( is_wp_error( $response ) ) {
					return array(
						'success' => false,
						'message' => __( 'Supabase API error: ', 'gregius-data' ) . $response->get_error_message(),
					);
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $status_code ) {
					$body = wp_remote_retrieve_body( $response );
					return array(
						'success' => false,
						/* translators: 1: HTTP status code, 2: error message */
						'message' => sprintf( __( 'Supabase API returned status %1$d: %2$s', 'gregius-data' ), $status_code, $body ),
					);
				}

				$body  = wp_remote_retrieve_body( $response );
				$batch = json_decode( $body, true );

				if ( empty( $batch ) || ! is_array( $batch ) ) {
					$more = false;
				} else {
					$all_posts = array_merge( $all_posts, $batch );
					$count     = count( $batch );

					if ( $count < $limit ) {
						$more = false;
					} else {
						$offset += $limit;
					}
				}
			}

			if ( empty( $all_posts ) || ! is_array( $all_posts ) ) {
				return array(
					'success' => false,
					'message' => __( 'No posts found in wp_posts_clean. Run sync and clean first.', 'gregius-data' ),
				);
			}

			// Use null for conn since Supabase doesn't use PDO.
			return $this->process_vocabulary_build( $all_posts, $connection_name, $start_time, null );
		}

		/**
		 * Process vocabulary building (shared logic for PDO and Supabase)
		 *
		 * @param array    $all_posts       Posts from wp_posts_clean.
		 * @param string   $connection_name Connection name.
		 * @param float    $start_time      Start timestamp.
		 * @param PDO|null $conn            PDO connection or null for Supabase.
		 * @return array Result array.
		 */
		private function process_vocabulary_build( $all_posts, $connection_name, $start_time, $conn ) {
			$post_count = count( $all_posts );

			// Transform wp_posts_clean column names to match embeddings class expectations.
			$transformed_posts = array_map(
				function ( $post ) {
					return array(
						'post_title'   => $post['post_title_clean'] ?? '',
						'post_content' => $post['post_content_clean'] ?? '',
						'post_excerpt' => $post['post_excerpt_clean'] ?? '',
					);
				},
				$all_posts
			);

			// Build global vocabulary using existing TF-IDF embeddings class.
			$vocabulary = $this->embeddings->build_global_vocabulary( $conn, $transformed_posts );

			if ( empty( $vocabulary ) ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to build vocabulary from posts.', 'gregius-data' ),
				);
			}

			$unique_terms = count( $vocabulary );

			// Get next vocabulary version.
			$current_version = $this->get_current_vocabulary_version( $connection_name );
			$new_version     = $current_version + 1;

			// Get site_id for multi-site support.
			$site_id = get_current_blog_id();

			// Store vocabulary data in PostgreSQL/Supabase.
			$vocabulary_json = wp_json_encode( $vocabulary );

			if ( $conn ) {
				// PDO version.
				$insert_sql = '
				INSERT INTO wp_posts_vocabulary_cache 
				(vocabulary_version, connection_name, site_id, vocabulary_data, post_count, unique_terms)
				VALUES (:version, :connection_name, :site_id, :vocabulary_data::jsonb, :post_count, :unique_terms)
				ON CONFLICT (vocabulary_version) 
				DO UPDATE SET 
					vocabulary_data = EXCLUDED.vocabulary_data,
					post_count = EXCLUDED.post_count,
					unique_terms = EXCLUDED.unique_terms,
					created_at = NOW()
			';
				$stmt       = $conn->prepare( $insert_sql );
				$stmt->execute(
					array(
						':version'         => $new_version,
						':connection_name' => $connection_name,
						':site_id'         => $site_id,
						':vocabulary_data' => $vocabulary_json,
						':post_count'      => $post_count,
						':unique_terms'    => $unique_terms,
					)
				);
			} else {
				// Supabase REST API version.
				$config = $this->settings->get_connection( $connection_name );

				if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
					require_once dirname( __DIR__ ) . '/providers/class-gg-postgrest-provider.php';
				}

				$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
				if ( isset( $runtime_config['error'] ) ) {
					return array(
						'success' => false,
						'message' => $runtime_config['error'],
					);
				}

				$project_url = $runtime_config['project_url'];

				$url = $project_url . '/rest/v1/wp_posts_vocabulary_cache';

				$response = wp_safe_remote_post(
					$url,
					array(
						'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'], $runtime_config['secret_key'], true ) + array(
							'Prefer' => 'resolution=merge-duplicates',
						),
						'body'    => wp_json_encode(
							array(
								'vocabulary_version' => $new_version,
								'connection_name'    => $connection_name,
								'site_id'            => $site_id,
								'vocabulary_data'    => json_decode( $vocabulary_json ),
								'post_count'         => $post_count,
								'unique_terms'       => $unique_terms,
							)
						),
						'timeout' => 60,
					)
				);

				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					$this->logger->log(
						'Supabase vocabulary storage error: ' . $error_message,
						'error',
						'vectors',
						$connection_name,
						array( 'error' => $error_message )
					);
					return array(
						'success' => false,
						'message' => __( 'Failed to store vocabulary in Supabase: ', 'gregius-data' ) . $error_message,
					);
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
					$body = wp_remote_retrieve_body( $response );
					$this->logger->log(
						"Supabase vocabulary storage failed with status {$status_code}: {$body}",
						'error',
						'vectors',
						$connection_name,
						array(
							'status_code' => $status_code,
							'response'    => $body,
						)
					);
					return array(
						'success' => false,
						/* translators: 1: HTTP status code, 2: error message */
						'message' => sprintf( __( 'Failed to store vocabulary in Supabase (HTTP %1$d): %2$s', 'gregius-data' ), $status_code, $body ),
					);
				}
			}

			// Store metadata in MySQL wp_gg_settings table.
			global $wpdb;
			$settings_table = $wpdb->prefix . 'gg_settings';
			$current_time   = current_time( 'mysql' );

			$settings = array(
				array(
					'setting_key'   => 'version',
					'setting_value' => (string) $new_version,
					'data_type'     => 'integer',
				),
				array(
					'setting_key'   => 'post_count',
					'setting_value' => (string) $post_count,
					'data_type'     => 'integer',
				),
				array(
					'setting_key'   => 'unique_terms',
					'setting_value' => (string) $unique_terms,
					'data_type'     => 'integer',
				),
				array(
					'setting_key'   => 'generated_at',
					'setting_value' => $current_time,
					'data_type'     => 'string',
				),
				array(
					'setting_key'   => 'pg_cached',
					'setting_value' => 'b:1;',
					'data_type'     => 'boolean',
				),
			);

			foreach ( $settings as $setting ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to store vocabulary metadata in custom plugin table wp_gg_settings
				$wpdb->replace(
					$settings_table,
					array(
						'connection_name' => $connection_name,
						'category'        => 'vocabulary',
						'setting_key'     => $setting['setting_key'],
						'setting_value'   => $setting['setting_value'],
						'data_type'       => $setting['data_type'],
						'is_encrypted'    => 0,
						'is_active'       => 1,
						'environment'     => 'production',
						'provider'        => 'custom',
						'created_at'      => $current_time,
						'updated_at'      => $current_time,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
				);
			}

			$processing_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			$this->logger->log(
				sprintf(
					'Vocabulary prepared: v%d, %d posts, %d terms, %s ms',
					$new_version,
					$post_count,
					$unique_terms,
					$processing_time
				),
				'info',
				'vectors',
				$connection_name,
				array(
					'version'         => $new_version,
					'post_count'      => $post_count,
					'unique_terms'    => $unique_terms,
					'processing_time' => $processing_time,
				)
			);

			return array(
				'success'         => true,
				'message'         => sprintf(
					/* translators: 1: number of posts, 2: number of unique terms */
					__( 'Vocabulary prepared successfully (%1$d posts, %2$d terms)', 'gregius-data' ),
					$post_count,
					$unique_terms
				),
				'vocabulary'      => array(
					'version'      => $new_version,
					'post_count'   => $post_count,
					'unique_terms' => $unique_terms,
					'generated_at' => current_time( 'mysql' ),
					'cached_in_pg' => true,
				),
				'processing_time' => $processing_time . ' ms',
			);
		}

		/**
		 * Get cached vocabulary from PostgreSQL
		 *
		 * @param string $connection_name PostgreSQL connection name.
		 * @return array|null Vocabulary array or null if not cached.
		 */
		public function get_cached_vocabulary( $connection_name = 'default' ) {
			try {
				// Check if vocabulary is cached (metadata in MySQL).
				$version = $this->get_vocabulary_setting( $connection_name, 'version' );
				if ( empty( $version ) ) {
					$this->logger->log(
						'No cached vocabulary found in settings',
						'debug',
						'vectors',
						$connection_name
					);
					return null;
				}

				// Detect provider type.
				$settings      = new GG_Data_Settings_Manager();
				$config        = $settings->get_connection( $connection_name );
				$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

				if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
					// Get vocabulary data from PostgREST API.
					if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
						require_once dirname( __DIR__ ) . '/providers/class-gg-postgrest-provider.php';
					}

					$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
					if ( isset( $runtime_config['error'] ) ) {
						$this->logger->log(
							$runtime_config['error'],
							'error',
							'vectors',
							$connection_name
						);
						return null;
					}

					$project_url = $runtime_config['project_url'];

					$site_id        = get_current_blog_id();
					$vocabulary_url = $project_url . '/rest/v1/wp_posts_vocabulary_cache?select=vocabulary_data&vocabulary_version=eq.' . $version . '&connection_name=eq.' . rawurlencode( $connection_name ) . '&site_id=eq.' . $site_id . '&limit=1';

					$response = wp_safe_remote_get(
						$vocabulary_url,
						array(
							'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'] ),
							'timeout' => 30,
						)
					);

					if ( is_wp_error( $response ) ) {
						$this->logger->log(
							'Failed to fetch vocabulary from Supabase: ' . $response->get_error_message(),
							'error',
							'vectors',
							$connection_name,
							array( 'error' => $response->get_error_message() )
						);
						return null;
					}

						$body   = wp_remote_retrieve_body( $response );
						$result = json_decode( $body, true );

					if ( empty( $result ) || ! is_array( $result ) || empty( $result[0]['vocabulary_data'] ) ) {
						$this->logger->log(
							'Vocabulary not found in Supabase cache',
							'warning',
							'vectors',
							$connection_name,
							array( 'version' => $version )
						);
						return null;
					}

						// The vocabulary_data is already a JSON string in Supabase, decode it.
						$vocabulary_json = $result[0]['vocabulary_data'];

						// If it's already an array, return it directly.
					if ( is_array( $vocabulary_json ) ) {
						$vocabulary = $vocabulary_json;
					} else {
						// Otherwise decode the JSON string.
						$vocabulary = json_decode( $vocabulary_json, true );
						if ( json_last_error() !== JSON_ERROR_NONE ) {
							$this->logger->log(
								'Failed to decode vocabulary JSON: ' . json_last_error_msg(),
								'error',
								'vectors',
								$connection_name,
								array( 'json_error' => json_last_error_msg() )
							);
							return null;
						}
					}

						$this->logger->log(
							sprintf( 'Retrieved cached vocabulary v%d with %d terms from Supabase', $version, count( $vocabulary ) ),
							'debug',
							'vectors',
							$connection_name,
							array(
								'version'    => $version,
								'term_count' => count( $vocabulary ),
							)
						);

						// Add version metadata to vocabulary for vector generation tracking.
						$vocabulary['_version'] = (int) $version;

						return $vocabulary;

				} else {
					// Get vocabulary data from PostgreSQL via PDO.
					$conn = $this->db->get_connection( $connection_name );
					if ( ! $conn ) {
						$this->logger->log(
							'Could not connect to PostgreSQL for vocabulary retrieval',
							'error',
							'vectors',
							$connection_name
						);
						return null;
					}

					$site_id = get_current_blog_id();

					$sql  = '
					SELECT vocabulary_data 
					FROM wp_posts_vocabulary_cache 
					WHERE vocabulary_version = :version 
					AND connection_name = :connection_name 
					AND site_id = :site_id
					LIMIT 1
				';
					$stmt = $conn->prepare( $sql );
					$stmt->execute(
						array(
							':version'         => $version,
							':connection_name' => $connection_name,
							':site_id'         => $site_id,
						)
					);
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
					$result = $stmt->fetch( PDO::FETCH_ASSOC );                 if ( empty( $result ) || empty( $result['vocabulary_data'] ) ) {
						$this->logger->log(
							'Vocabulary not found in PostgreSQL cache',
							'warning',
							'vectors',
							$connection_name,
							array( 'version' => $version )
						);
						return null;
					}

					// Decode JSONB data.
					$vocabulary = json_decode( $result['vocabulary_data'], true );

					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$this->logger->log(
							'Failed to decode vocabulary JSON: ' . json_last_error_msg(),
							'error',
							'vectors',
							$connection_name,
							array( 'json_error' => json_last_error_msg() )
						);
						return null;
					}

					$this->logger->log(
						sprintf( 'Retrieved cached vocabulary v%d with %d terms', $version, count( $vocabulary ) ),
						'debug',
						'vectors',
						$connection_name,
						array(
							'version'    => $version,
							'term_count' => count( $vocabulary ),
						)
					);

					// Add version metadata to vocabulary for vector generation tracking.
					$vocabulary['_version'] = (int) $version;

					return $vocabulary;
				}
			} catch ( Exception $e ) {
				$this->logger->log(
					'Error retrieving cached vocabulary: ' . $e->getMessage(),
					'error',
					'vectors',
					$connection_name,
					array( 'exception' => get_class( $e ) )
				);
				return null;
			}
		}       /**
				 * Validate vocabulary status and detect drift
				 *
				 * Compares cached post count with current wp_posts_clean count
				 * to determine if vocabulary needs regeneration.
				 *
				 * @param string $connection_name PostgreSQL connection name.
				 * @return array Status array with drift information.
				 */
		public function validate_vocabulary_status( $connection_name = 'default' ) {
			try {
				// Check if vocabulary exists.
				$version           = $this->get_vocabulary_setting( $connection_name, 'version' );
				$cached_post_count = (int) $this->get_vocabulary_setting( $connection_name, 'post_count' );

				// If no version OR version is 0, vocabulary doesn't exist.
				if ( empty( $version ) || '0' === $version || 0 === $cached_post_count ) {
					// Get current post count even when vocabulary doesn't exist.
					$current_post_count = $this->get_current_post_count( $connection_name );

					// Calculate drift: (cached - processed) / processed * 100
					// When cached=0 and processed>0, drift is -100% (vocabulary completely behind).
					$drift_percentage = $current_post_count > 0
					? ( ( 0 - $current_post_count ) / $current_post_count ) * 100
					: 0;

					return array(
						'exists'             => false,
						'version'            => 0,
						'cached_post_count'  => 0,
						'current_post_count' => $current_post_count,
						'posts_added'        => $current_post_count,
						'drift_percentage'   => round( $drift_percentage, 2 ),
						'status'             => 'not_prepared',
						'needs_regeneration' => true,
						'message'            => __( 'Vocabulary not prepared. Click "Prepare Vocabulary" to begin.', 'gregius-data' ),
					);
				}           // Get cached metadata.
				$cached_post_count = (int) $this->get_vocabulary_setting( $connection_name, 'post_count' );
				$unique_terms      = (int) $this->get_vocabulary_setting( $connection_name, 'unique_terms' );
				$generated_at      = $this->get_vocabulary_setting( $connection_name, 'generated_at' );

				// Get current post count using helper method.
				$current_post_count = $this->get_current_post_count( $connection_name );

				// Calculate drift: (cached - processed) / processed * 100
				// Negative = vocabulary behind (needs regeneration), Positive = vocabulary ahead (shouldn't happen).
				$posts_added      = $current_post_count - $cached_post_count;
				$drift_percentage = $current_post_count > 0
				? ( ( $cached_post_count - $current_post_count ) / $current_post_count ) * 100
				: 0;

				// Determine status (matching Sync Integrity thresholds).
				// success: <2% drift, warning: 2-5% drift, error: >5% drift.
				$status             = 'success';  // healthy.
				$message            = __( 'Vocabulary is current (drift < 2%)', 'gregius-data' );
				$needs_regeneration = false;

				if ( abs( $drift_percentage ) >= 5 ) {
					$status  = 'error';  // critical.
					$message = sprintf(
						/* translators: %.1f: drift percentage */
						__( 'Critical vocabulary drift (%.1f%%). Regeneration required before vector generation.', 'gregius-data' ),
						$drift_percentage
					);
						$needs_regeneration = true;
				} elseif ( abs( $drift_percentage ) >= 2 ) {
					$status  = 'warning';
					$message = sprintf(
						/* translators: %.1f: drift percentage */
						__( 'Minor vocabulary drift detected (%.1f%%). Monitor and consider regeneration.', 'gregius-data' ),
						abs( $drift_percentage )
					);
						$needs_regeneration = false;
				}

				return array(
					'exists'             => true,
					'version'            => (int) $version,
					'cached_post_count'  => $cached_post_count,
					'unique_terms'       => $unique_terms,
					'generated_at'       => $generated_at,
					'current_post_count' => $current_post_count,
					'posts_added'        => $posts_added,
					'drift_percentage'   => round( $drift_percentage, 2 ),
					'status'             => $status,
					'needs_regeneration' => $needs_regeneration,
					'message'            => $message,
				);

			} catch ( Exception $e ) {
				$this->logger->log(
					'Error validating vocabulary status: ' . $e->getMessage(),
					'error',
					'vectors',
					$connection_name,
					array( 'exception' => get_class( $e ) )
				);
				return array(
					'success' => false,
					'message' => __( 'Error validating vocabulary status: ', 'gregius-data' ) . $e->getMessage(),
				);
			}
		}

		/**
		 * Get current post count from wp_posts_clean
		 *
		 * Helper method to fetch the current number of posts in wp_posts_clean.
		 * Supports both PostgreSQL (PDO) and PostgREST/Supabase connections.
		 *
		 * @param string $connection_name Connection name.
		 * @return int Current post count.
		 */
		private function get_current_post_count( $connection_name = 'default' ) {
			try {
				// Detect provider type (Supabase vs PDO).
				$config        = $this->settings->get_connection( $connection_name );
				$provider_type = isset( $config['type'] ) ? $config['type'] : 'postgresql';

				if ( 'postgrest' === $provider_type || 'supabase' === $provider_type ) {
					// Use REST API for Supabase.
					if ( ! class_exists( 'GG_Data_PostgREST_Provider' ) ) {
						require_once dirname( __DIR__ ) . '/providers/class-gg-postgrest-provider.php';
					}

					$runtime_config = GG_Data_PostgREST_Provider::get_runtime_supabase_config( $config );
					if ( isset( $runtime_config['error'] ) ) {
						$this->logger->log(
							$runtime_config['error'],
							'error',
							'vectors',
							$connection_name
						);
						return 0;
					}

					$project_url = $runtime_config['project_url'];

					// Count rows via REST API HEAD request with Count header.
					$url      = $project_url . '/rest/v1/wp_posts_clean?select=post_id&post_content_clean=not.is.null&limit=0';
					$response = wp_safe_remote_head(
						$url,
						array(
							'headers' => GG_Data_PostgREST_Provider::build_supabase_headers( $runtime_config['publishable_key'] ) + array(
								'Prefer' => 'count=exact',
							),
							'timeout' => 30,
						)
					);

					if ( is_wp_error( $response ) ) {
						$this->logger->log(
							'Failed to fetch post count from Supabase: ' . $response->get_error_message(),
							'error',
							'vectors',
							$connection_name,
							array( 'error' => $response->get_error_message() )
						);
						return 0;
					}

					// Get count from Content-Range header.
					$content_range = wp_remote_retrieve_header( $response, 'content-range' );
					if ( preg_match( '/\/(\d+)$/', $content_range, $matches ) ) {
						return (int) $matches[1];
					}

					return 0;

				} else {
					// Use PDO for PostgreSQL.
					$conn = $this->db->get_connection( $connection_name );
					if ( ! $conn ) {
						$this->logger->log(
							'Could not connect to PostgreSQL database',
							'error',
							'vectors',
							$connection_name
						);
						return 0;
					}

					$count_sql = 'SELECT COUNT(*) as count FROM wp_posts_clean WHERE post_content_clean IS NOT NULL';
					$stmt      = $conn->prepare( $count_sql );
					$stmt->execute();
					// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL
					$result = $stmt->fetch( PDO::FETCH_ASSOC );
					return (int) $result['count'];
				}
			} catch ( Exception $e ) {
				$this->logger->log(
					'Error fetching current post count: ' . $e->getMessage(),
					'error',
					'vectors',
					$connection_name,
					array( 'exception' => get_class( $e ) )
				);
				return 0;
			}
		}

		/**
		 * Clear vocabulary cache
		 *
		 * Removes vocabulary data from PostgreSQL and metadata from MySQL.
		 *
		 * @param string $connection_name PostgreSQL connection name.
		 * @return array Result array with success status and message.
		 */
		public function clear_vocabulary_cache( $connection_name = 'default' ) {
			try {
				$version = $this->get_vocabulary_setting( $connection_name, 'version' );
				if ( empty( $version ) ) {
					return array(
						'success' => false,
						'message' => __( 'No vocabulary cache to clear.', 'gregius-data' ),
					);
				}

				// Delete from PostgreSQL.
				$conn = $this->db->get_connection( $connection_name );
				if ( $conn ) {
					$site_id    = get_current_blog_id();
					$delete_sql = '
						DELETE FROM wp_posts_vocabulary_cache 
						WHERE vocabulary_version = :version 
						AND connection_name = :connection_name 
						AND site_id = :site_id
					';
					$stmt       = $conn->prepare( $delete_sql );
					$stmt->execute(
						array(
							':version'         => $version,
							':connection_name' => $connection_name,
							':site_id'         => $site_id,
						)
					);
				}

				// Delete from MySQL wp_gg_settings.
				global $wpdb;
				$settings_table = $wpdb->prefix . 'gg_settings';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete vocabulary metadata from custom plugin table wp_gg_settings
				$wpdb->delete(
					$settings_table,
					array(
						'connection_name' => $connection_name,
						'category'        => 'vocabulary',
					),
					array( '%s', '%s' )
				);
				$this->logger->log(
					sprintf( 'Cleared vocabulary cache v%d for connection %s', $version, $connection_name ),
					'info',
					'vectors',
					$connection_name,
					array( 'version' => $version )
				);

				return array(
					'success'         => true,
					'message'         => __( 'Vocabulary cache cleared successfully.', 'gregius-data' ),
					'cleared_version' => (int) $version,
				);

			} catch ( Exception $e ) {
				$this->logger->log(
					'Error clearing vocabulary cache: ' . $e->getMessage(),
					'error',
					'vectors',
					$connection_name,
					array( 'exception' => get_class( $e ) )
				);
				return array(
					'success' => false,
					'message' => __( 'Error clearing vocabulary cache: ', 'gregius-data' ) . $e->getMessage(),
				);
			}
		}

		/**
		 * Get vocabulary setting from wp_gg_settings table
		 *
		 * @param string $connection_name PostgreSQL connection name.
		 * @param string $setting_key Setting key to retrieve.
		 * @return string|null Setting value or null if not found.
		 */
		protected function get_vocabulary_setting( $connection_name, $setting_key ) {
			global $wpdb;
			$settings_table = $wpdb->prefix . 'gg_settings';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name constructed from $wpdb->prefix, safe from SQL injection. Required to fetch vocabulary setting from custom plugin table wp_gg_settings
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT setting_value FROM {$settings_table} WHERE connection_name = %s AND category = %s AND setting_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$connection_name,
					'vocabulary',
					$setting_key
				)
			);
			return $result;
		}

		/**
		 * Get current vocabulary version from settings
		 *
		 * @param string $connection_name PostgreSQL connection name.
		 * @return int Current vocabulary version (0 if not set).
		 */
		protected function get_current_vocabulary_version( $connection_name = 'default' ) {
			$version = $this->get_vocabulary_setting( $connection_name, 'version' );
			return empty( $version ) ? 0 : (int) $version;
		}
	}
}
