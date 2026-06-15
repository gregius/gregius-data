<?php
/**
 * Sync Validator
 *
 * Validates WordPress-PostgreSQL data consistency by comparing row counts.
 * Runs daily automated checks and provides manual validation capability.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Sync Validator Class
 */
class GG_Data_Sync_Validator {

	/**
	 * Logger instance
	 *
	 * @var GG_Data_Logger
	 */
	private $logger;

	/**
	 * Settings manager instance
	 *
	 * @var GG_Data_Settings_Manager
	 */
	private $settings;

	/**
	 * Validation results option key prefix
	 *
	 * @var string
	 */
	const VALIDATION_RESULTS_KEY_PREFIX = 'gg_data_validation_results_';

	/**
	 * Last validation email option key prefix
	 *
	 * @var string
	 */
	const LAST_EMAIL_KEY_PREFIX = 'gg_data_last_validation_email_';

	/**
	 * Drift threshold for alerts (percentage)
	 *
	 * @var float
	 */
	const DRIFT_THRESHOLD = 5.0;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger   = new GG_Data_Logger();
		$this->settings = new GG_Data_Settings_Manager();
	}

	/**
	 * Run comprehensive row count validation
	 *
	 * @param string $connection_name Connection name to validate.
	 * @return array Validation results.
	 */
	public function run_validation( $connection_name = null ) {
		// Get connection name from active connections if not provided.
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$this->logger->log( sprintf( 'Starting sync validation for connection: %s', $connection_name ), 'info', 'sync', $connection_name );

		$posts_validation = $this->validate_posts();

		$validation_results = array(
			'timestamp'          => current_time( 'mysql' ),
			'posts'              => $posts_validation,
			'postmeta'           => $this->validate_postmeta(),
			'terms'              => $this->validate_terms(),
			'term_taxonomy'      => $this->validate_term_taxonomy(),
			'term_relationships' => $this->validate_term_relationships(),
			'overall_status'     => 'unknown',
		);

		// Determine overall status.
		$has_critical_drift = false;
		$has_minor_drift    = false;

		foreach ( $validation_results as $key => $result ) {
			if ( 'posts' === $key ) {
				// Handle nested post type structure.
				foreach ( $result as $post_type => $post_data ) {
					if ( is_array( $post_data ) && isset( $post_data['drift_percentage'] ) ) {
						if ( $post_data['drift_percentage'] > self::DRIFT_THRESHOLD ) {
							$has_critical_drift = true;
						} elseif ( $post_data['drift_percentage'] > 0 ) {
							$has_minor_drift = true;
						}
					}
				}
			} elseif ( is_array( $result ) && isset( $result['drift_percentage'] ) ) {
				// Handle other validation results (postmeta, terms, etc.).
				if ( $result['drift_percentage'] > self::DRIFT_THRESHOLD ) {
					$has_critical_drift = true;
				} elseif ( $result['drift_percentage'] > 0 ) {
					$has_minor_drift = true;
				}
			}
		}

		if ( $has_critical_drift ) {
			$validation_results['overall_status'] = 'critical';
			$this->send_drift_alert( $validation_results, $connection_name );
		} elseif ( $has_minor_drift ) {
			$validation_results['overall_status'] = 'warning';
		} else {
			$validation_results['overall_status'] = 'healthy';
		}

		// Store validation results for dashboard (per-connection).
		$option_key = self::VALIDATION_RESULTS_KEY_PREFIX . $connection_name;
		update_option( $option_key, $validation_results );

		$this->logger->log(
			sprintf( 'Validation complete for %s: %s', $connection_name, $validation_results['overall_status'] ),
			$has_critical_drift ? 'error' : 'info',
			'sync',
			$connection_name,
			array( 'overall_status' => $validation_results['overall_status'] )
		);

		return $validation_results;
	}

	/**
	 * Get stored validation results
	 *
	 * @param string $connection_name Connection name to get results for.
	 * @return array|null Validation results or null if never run.
	 */
	public function get_validation_results( $connection_name = null ) {
		// Get connection name from active connections if not provided.
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$option_key = self::VALIDATION_RESULTS_KEY_PREFIX . $connection_name;
		$results    = get_option( $option_key, null );

		// Return null if never run.
		if ( ! $results ) {
			return null;
		}

		return $results;
	}

	/**
	 * Validate posts table counts
	 *
	 * @return array Validation result for posts by post type.
	 */
	private function validate_posts() {
		$active_connections = $this->settings->get_active_connections();

		// Get the first active connection to pull sync settings from.
		$connection_name = ! empty( $active_connections ) ? key( $active_connections ) : 'default';

		// Pull enabled post types and statuses dynamically from sync settings.
		// These are stored per connection in the settings table with category 'sync'.
		$enabled_post_types = $this->settings->get_with_category( 'sync', $connection_name, 'sync_enabled_post_types', array( 'post', 'page' ) );
		$enabled_statuses   = $this->settings->get_with_category( 'sync', $connection_name, 'sync_enabled_statuses', array( 'publish', 'draft', 'private', 'pending', 'future' ) );

		$results = array();

		// Validate each post type individually.
		foreach ( $enabled_post_types as $post_type ) {
			// WordPress count (MySQL) for this post type.
			$wp_count = 0;
			$counts   = wp_count_posts( $post_type );
			foreach ( $enabled_statuses as $status ) {
				$wp_count += isset( $counts->$status ) ? $counts->$status : 0;
			}

			// PostgreSQL count for this post type.
			$pg_count = 0;
			if ( ! empty( $active_connections ) ) {
				$pg_count = $this->get_postgresql_count_by_post_type( $post_type, $enabled_statuses, $connection_name );
			}

			// Get cleaning count (posts needing content processing).
			$cleaning_needed = 0;
			if ( ! empty( $active_connections ) ) {
				$cleaning_needed = $this->get_cleaning_count_by_post_type( $post_type, $enabled_statuses, $connection_name );
			}

			$drift = $wp_count - $pg_count;

			// Calculate drift percentage with special handling for orphan scenario.
			// Signed drift: negative = PostgreSQL behind, positive = PostgreSQL ahead.
			if ( 0 === $wp_count && $pg_count > 0 ) {
				// Special case: All PostgreSQL records are orphans (100% drift).
				$drift_percentage = 100.0;
			} elseif ( $wp_count > 0 ) {
				// Standard case: Calculate drift relative to WordPress count.
				$drift_percentage = ( ( $pg_count - $wp_count ) / $wp_count * 100 );
			} else {
				// Both zero: No drift.
				$drift_percentage = 0;
			}

			$results[ $post_type ] = array(
				'wordpress_count'  => $wp_count,
				'postgresql_count' => $pg_count,
				'drift'            => $drift,
				'drift_percentage' => round( $drift_percentage, 2 ),
				'status'           => $this->get_drift_status( abs( $drift_percentage ) ),
				'cleaning_needed'  => $cleaning_needed,
			);
		}

		return $results;
	}

	/**
	 * Validate postmeta table counts
	 *
	 * @return array Validation result for postmeta.
	 */
	private function validate_postmeta() {
		global $wpdb;

		$active_connections = $this->settings->get_active_connections();

		if ( empty( $active_connections ) ) {
			return array(
				'wordpress_count'  => 0,
				'postgresql_count' => 0,
				'drift'            => 0,
				'drift_percentage' => 0,
				'status'           => 'healthy',
			);
		}

		$connection_name = key( $active_connections );

		// Get user-configured post types and statuses from sync settings.
		$enabled_post_types = $this->settings->get_with_category(
			'sync',
			$connection_name,
			'sync_enabled_post_types',
			array()
		);

		$enabled_statuses = $this->settings->get_with_category(
			'sync',
			$connection_name,
			'sync_enabled_statuses',
			array( 'publish' )
		);

		// Handle serialized arrays.
		if ( is_string( $enabled_post_types ) ) {
			$enabled_post_types = maybe_unserialize( $enabled_post_types );
		}
		if ( is_string( $enabled_statuses ) ) {
			$enabled_statuses = maybe_unserialize( $enabled_statuses );
		}

		// Default to empty arrays if not set.
		if ( ! is_array( $enabled_post_types ) ) {
			$enabled_post_types = array();
		}
		if ( ! is_array( $enabled_statuses ) ) {
			$enabled_statuses = array( 'publish' );
		}

		// If no post types configured, return zero count.
		if ( empty( $enabled_post_types ) ) {
			return array(
				'wordpress_count'  => 0,
				'postgresql_count' => 0,
				'drift'            => 0,
				'drift_percentage' => 0,
				'status'           => 'healthy',
			);
		}

		// Build SQL IN clauses with proper escaping.
		$post_types_placeholders = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
		$statuses_placeholders   = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

		// WordPress count (MySQL) - only for user-configured post types and statuses.
		// Matches the bulk sync implementation meta key filtering in GG_Data_Postmeta_Sync.
		// This is a simplified SQL version of should_skip_meta_key() logic.
		$query = "SELECT COUNT(DISTINCT pm.meta_id)
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type IN ($post_types_placeholders)
			AND p.post_status IN ($statuses_placeholders)
			AND pm.meta_key NOT IN (
				'_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date',
				'_encloseme', '_pingme', '_wp_trash_meta_time', '_wp_trash_meta_status',
				'_wp_desired_post_slug', '_wp_attached_file', '_wp_attachment_metadata'
			)
			AND (
				pm.meta_key NOT LIKE '\_%'
				OR pm.meta_key IN ('_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_thumbnail_id', '_wp_page_template')
				OR pm.meta_key LIKE 'field_%'
				OR pm.meta_key LIKE '\_field\_%'
			)";

		// Prepare query with all parameters.
		$prepare_args = array_merge( $enabled_post_types, $enabled_statuses );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Required to count postmeta records for enabled post types/statuses for validation. Query is prepared.
		$wp_count = $wpdb->get_var( $wpdb->prepare( $query, $prepare_args ) );

		// PostgreSQL count.
		$pg_count = $this->get_postgresql_count( 'postmeta', $connection_name );

		$drift = $wp_count - $pg_count;

		// Calculate drift percentage with special handling for orphan scenario.
		if ( 0 === $wp_count && $pg_count > 0 ) {
			// Special case: All PostgreSQL records are orphans (100% drift).
			$drift_percentage = 100.0;
		} elseif ( $wp_count > 0 ) {
			// Standard case: Use absolute value for unsigned drift.
			$drift_percentage = abs( $drift / $wp_count * 100 );
		} else {
			// Both zero: No drift.
			$drift_percentage = 0;
		}

		return array(
			'wordpress_count'  => (int) $wp_count,
			'postgresql_count' => $pg_count,
			'drift'            => $drift,
			'drift_percentage' => round( $drift_percentage, 2 ),
			'status'           => $this->get_drift_status( $drift_percentage ),
		);
	}

	/**
	 * Validate terms table counts
	 *
	 * @return array Validation result for terms.
	 */
	private function validate_terms() {
		global $wpdb;

		$active_connections = $this->settings->get_active_connections();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all terms from WordPress core table for validation
		$wp_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );

		// PostgreSQL count.
		$pg_count = 0;
		if ( ! empty( $active_connections ) ) {
			$connection_name = key( $active_connections );
			$pg_count        = $this->get_postgresql_count( 'terms', $connection_name );
		}

		$drift = $wp_count - $pg_count;

		// Calculate drift percentage with special handling for orphan scenario.
		if ( 0 === $wp_count && $pg_count > 0 ) {
			// Special case: All PostgreSQL records are orphans (100% drift).
			$drift_percentage = 100.0;
		} elseif ( $wp_count > 0 ) {
			// Standard case: Use absolute value for unsigned drift.
			$drift_percentage = abs( $drift / $wp_count * 100 );
		} else {
			// Both zero: No drift.
			$drift_percentage = 0;
		}

		return array(
			'wordpress_count'  => (int) $wp_count,
			'postgresql_count' => $pg_count,
			'drift'            => $drift,
			'drift_percentage' => round( $drift_percentage, 2 ),
			'status'           => $this->get_drift_status( $drift_percentage ),
		);
	}

	/**
	 * Validate term_taxonomy table counts
	 *
	 * @return array Validation result for term_taxonomy.
	 */
	private function validate_term_taxonomy() {
		global $wpdb;

		$active_connections = $this->settings->get_active_connections();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to count all term_taxonomy records from WordPress core table for validation
		$wp_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy}" );

		// PostgreSQL count.
		$pg_count = 0;
		if ( ! empty( $active_connections ) ) {
			$connection_name = key( $active_connections );
			$pg_count        = $this->get_postgresql_count( 'term_taxonomy', $connection_name );
		}

		$drift = $wp_count - $pg_count;

		// Calculate drift percentage with special handling for orphan scenario.
		if ( 0 === $wp_count && $pg_count > 0 ) {
			// Special case: All PostgreSQL records are orphans (100% drift).
			$drift_percentage = 100.0;
		} elseif ( $wp_count > 0 ) {
			// Standard case: Use absolute value for unsigned drift.
			$drift_percentage = abs( $drift / $wp_count * 100 );
		} else {
			// Both zero: No drift.
			$drift_percentage = 0;
		}

		return array(
			'wordpress_count'  => (int) $wp_count,
			'postgresql_count' => $pg_count,
			'drift'            => $drift,
			'drift_percentage' => round( $drift_percentage, 2 ),
			'status'           => $this->get_drift_status( $drift_percentage ),
		);
	}

	/**
	 * Validate term_relationships table counts
	 *
	 * @return array Validation result for term_relationships.
	 */
	private function validate_term_relationships() {
		global $wpdb;

		$active_connections = $this->settings->get_active_connections();

		// Get the first active connection to pull sync settings from.
		$connection_name = ! empty( $active_connections ) ? key( $active_connections ) : 'default';

		// Pull enabled post types and statuses dynamically from sync settings.
		$enabled_post_types = $this->settings->get_with_category( 'sync', $connection_name, 'sync_enabled_post_types', array( 'post', 'page' ) );
		$enabled_statuses   = $this->settings->get_with_category( 'sync', $connection_name, 'sync_enabled_statuses', array( 'publish', 'draft', 'private', 'pending', 'future' ) );

		// Build placeholders for post types and statuses.
		$type_placeholders   = implode( ',', array_fill( 0, count( $enabled_post_types ), '%s' ) );
		$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '%s' ) );

		// WordPress count - only for enabled post types and statuses.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Required to count term relationships for enabled post types/statuses for validation. Placeholders are dynamic.
		$wp_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE p.post_type IN ($type_placeholders)
				AND p.post_status IN ($status_placeholders)",
				array_merge( $enabled_post_types, $enabled_statuses )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// PostgreSQL count.
		$pg_count = 0;
		if ( ! empty( $active_connections ) ) {
			$connection_name = key( $active_connections );
			$pg_count        = $this->get_postgresql_count( 'term_relationships', $connection_name );
		}

		$drift = $wp_count - $pg_count;

		// Calculate drift percentage with special handling for orphan scenario.
		if ( 0 === $wp_count && $pg_count > 0 ) {
			// Special case: All PostgreSQL records are orphans (100% drift).
			$drift_percentage = 100.0;
		} elseif ( $wp_count > 0 ) {
			// Standard case: Use absolute value for unsigned drift.
			$drift_percentage = abs( $drift / $wp_count * 100 );
		} else {
			// Both zero: No drift.
			$drift_percentage = 0;
		}

		return array(
			'wordpress_count'  => (int) $wp_count,
			'postgresql_count' => $pg_count,
			'drift'            => $drift,
			'drift_percentage' => round( $drift_percentage, 2 ),
			'status'           => $this->get_drift_status( $drift_percentage ),
		);
	}

	/**
	 * Get count of posts needing cleaning for specific post type
	 *
	 * Counts posts in wp_posts that don't have corresponding wp_posts_clean entries,
	 * filtered by post_type and enabled statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type         Post type to check.
	 * @param array  $enabled_statuses  Enabled post statuses.
	 * @param string $connection_name   Connection name.
	 * @return int Count of posts needing cleaning.
	 */
	private function get_cleaning_count_by_post_type( $post_type, $enabled_statuses, $connection_name ) {
		try {
			$db   = new GG_Data_DB();
			$conn = $db->get_connection( $connection_name );

			if ( ! $conn ) {
				return 0;
			}

			// Validate we have statuses.
			if ( empty( $enabled_statuses ) ) {
				return 0;
			}

			// Build status placeholders for IN clause.
			$status_placeholders = implode( ',', array_fill( 0, count( $enabled_statuses ), '?' ) );

			$sql = "
				SELECT COUNT(*) as cleaning_needed
				FROM wp_posts p
				LEFT JOIN wp_posts_clean pc ON p.id = pc.post_id
				WHERE pc.post_id IS NULL
				  AND p.post_type = ?
				  AND p.post_status IN ($status_placeholders)
			";

			$stmt = $conn->prepare( $sql );

			// Bind parameters: post_type, then each status.
			$params = array_merge( array( $post_type ), $enabled_statuses );
			$stmt->execute( $params );

			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- PDO is required for PostgreSQL connections, $wpdb does not support PostgreSQL.
			$row = $stmt->fetch( PDO::FETCH_ASSOC );
			return (int) $row['cleaning_needed'];

		} catch ( Exception $e ) {
			$this->logger->log(
				sprintf( 'Error getting cleaning count for %s: %s', $post_type, $e->getMessage() ),
				'error',
				'sync',
				$connection_name,
				array(
					'post_type' => $post_type,
					'error'     => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Get PostgreSQL table row count
	 *
	 * @param string $table_name Table name to count.
	 * @param string $connection_name Connection name.
	 * @return int Row count.
	 */
	private function get_postgresql_count( $table_name, $connection_name ) {
		$db   = new GG_Data_DB();
		$conn = $db->get_connection( $connection_name );

		if ( ! $conn ) {
			$this->logger->log( "Failed to get PostgreSQL connection for validation: $table_name", 'error', 'sync', $connection_name, array( 'table_name' => $table_name ) );
			return 0;
		}

		try {
			$stmt  = $conn->query( "SELECT COUNT(*) FROM wp_$table_name" );
			$count = $stmt->fetchColumn();
			return (int) $count;
		} catch ( PDOException $e ) {
			$this->logger->log(
				"PostgreSQL count query failed for $table_name: " . $e->getMessage(),
				'error',
				'sync',
				$connection_name,
				array(
					'table_name' => $table_name,
					'error'      => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Get PostgreSQL post count by post type and statuses
	 *
	 * @param string $post_type Post type to count.
	 * @param array  $statuses Enabled post statuses.
	 * @param string $connection_name Connection name.
	 * @return int Row count.
	 */
	private function get_postgresql_count_by_post_type( $post_type, $statuses, $connection_name ) {
		$db   = new GG_Data_DB();
		$conn = $db->get_connection( $connection_name );

		if ( ! $conn ) {
			$this->logger->log( "Failed to get PostgreSQL connection for validation: posts by type $post_type", 'error', 'sync', $connection_name, array( 'post_type' => $post_type ) );
			return 0;
		}

		try {
			// Build placeholders for statuses.
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '?' ) );

			$sql  = "SELECT COUNT(*) FROM wp_posts WHERE post_type = ? AND post_status IN ($status_placeholders)";
			$stmt = $conn->prepare( $sql );

			// Bind post type and statuses.
			$params = array_merge( array( $post_type ), $statuses );
			$stmt->execute( $params );

			$count = $stmt->fetchColumn();
			return (int) $count;
		} catch ( PDOException $e ) {
			$this->logger->log(
				"PostgreSQL count query failed for post type $post_type: " . $e->getMessage(),
				'error',
				'sync',
				$connection_name,
				array(
					'post_type' => $post_type,
					'error'     => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Get drift status based on percentage
	 *
	 * @param float $drift_percentage Drift percentage.
	 * @return string Status: healthy, warning, or critical.
	 */
	private function get_drift_status( $drift_percentage ) {
		if ( $drift_percentage > self::DRIFT_THRESHOLD ) {
			return 'critical';
		} elseif ( $drift_percentage > 0 ) {
			return 'warning';
		}
		return 'healthy';
	}

	/**
	 * Send drift alert email
	 *
	 * @param array  $validation_results Validation results.
	 * @param string $connection_name Connection name.
	 * @return void
	 */
	private function send_drift_alert( $validation_results, $connection_name ) {
		// Prevent email spam - only send once per 24 hours per connection.
		$email_key       = self::LAST_EMAIL_KEY_PREFIX . $connection_name;
		$last_email_sent = get_option( $email_key, 0 );
		if ( time() - $last_email_sent < ( 24 * HOUR_IN_SECONDS ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf( '[%s] PostgreSQL Sync Drift Detected', $site_name );

		$critical_tables = array();
		foreach ( $validation_results as $table => $result ) {
			if ( is_array( $result ) && isset( $result['drift_percentage'] ) && $result['drift_percentage'] > self::DRIFT_THRESHOLD ) {
				$critical_tables[] = sprintf(
					'%s: %d WordPress vs %d PostgreSQL (%.2f%% drift)',
					$table,
					$result['wordpress_count'],
					$result['postgresql_count'],
					$result['drift_percentage']
				);
			}
		}

		$message = sprintf(
			"PostgreSQL sync validation detected significant data drift.\n\n" .
			"Critical Tables (>%.1f%% drift):\n%s\n\n" .
			"Actions to take:\n" .
			"1. Review sync queue for failures\n" .
			"2. Check PostgreSQL connection health\n" .
			"3. Consider running manual sync for affected post types\n\n" .
			"Dashboard: %s\n\n" .
			"---\n" .
			"This email was sent by Gregius Data plugin.\n" .
			'To change this email address, go to WordPress Settings → General → Administration Email Address.',
			self::DRIFT_THRESHOLD,
			implode( "\n", $critical_tables ),
			admin_url( 'admin.php?page=gregius-data#/sync' )
		);

		wp_mail( $admin_email, $subject, $message );

		update_option( $email_key, time() );

		$this->logger->log( 'Drift alert email sent to admin for connection: ' . $connection_name, 'warning', 'sync', $connection_name );
	}

	/**
	 * Reset validation data (for testing)
	 *
	 * @param string|null $connection_name Optional connection name. If not provided, uses active connection.
	 * @return void
	 */
	public function reset_validation( $connection_name = null ) {
		if ( empty( $connection_name ) ) {
			$active_connections = $this->settings->get_active_connections();
			$connection_name    = ! empty( $active_connections ) ? key( $active_connections ) : 'default';
		}

		$results_key = self::VALIDATION_RESULTS_KEY_PREFIX . $connection_name;
		$email_key   = self::LAST_EMAIL_KEY_PREFIX . $connection_name;

		delete_option( $results_key );
		delete_option( $email_key );

		$this->logger->log( 'Validation data reset for connection: ' . $connection_name, 'info', 'sync', $connection_name );
	}
}
