<?php
/**
 * Plugin activation handling
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GG_Data_Activator' ) ) {

	/**
	 * Plugin activation class
	 */
	class GG_Data_Activator {

		/**
		 * Plugin version constant (must match header Version in gregius-data.php)
		 */
		const VERSION = '1.0.0';

		/**
		 * WordPress option name for storing database version
		 */
		const VERSION_OPTION = 'gg_data_db_version';

		/**
		 * Actions to perform on plugin activation
		 */
		public static function activate() {
			self::run_upgrades();
			// Initialize settings manager and create settings table for all sites.
			if ( class_exists( 'GG_Data_Settings_Manager' ) ) {
				$settings_manager = new GG_Data_Settings_Manager();
				$settings_manager->create_settings_tables_for_all_sites();
			}

			// Initialize schema manager and create sync metadata tables for all sites.
			if ( class_exists( 'GG_Data_Schema_Manager' ) ) {
				$schema_manager = new GG_Data_Schema_Manager();
				$schema_manager->create_sync_metadata_tables_for_all_sites();
			}

			// Initialize logger and create logs table for all sites.
			if ( class_exists( 'GG_Data_Logger' ) ) {
				$logger = new GG_Data_Logger();
				$logger->create_logs_tables_for_all_sites();
			}

			// Initialize search settings.
			if ( function_exists( 'gg_data_init_search_settings' ) ) {
				gg_data_init_search_settings();
			}

			// Initialize default content sync settings.
			self::init_default_sync_settings();

			// Add scheduled event for connection health checks.
			if ( ! wp_next_scheduled( 'gg_data_check_connection_health' ) ) {
				wp_schedule_event( time(), 'gg_data_every_five_minutes', 'gg_data_check_connection_health' );
			}

			// Add scheduled event for sync validation.
			if ( ! wp_next_scheduled( 'gg_data_daily_validation' ) ) {
				wp_schedule_event( strtotime( 'tomorrow 3:00 AM' ), 'daily', 'gg_data_daily_validation' );
			}

			// Add scheduled event for daily log retention purge (site-scoped in multisite).
			self::schedule_log_retention_events();

			// Add capabilities to administrator.
			$admin_role = get_role( 'administrator' );

			if ( $admin_role ) {
				$admin_role->add_cap( 'manage_gg_pg' );
			}

			// Seed factory prompt terms and defaults.
			self::ensure_prompt_type_terms();
			self::seed_default_prompts();
		}

		/**
		 * Ensure required prompt type terms exist.
		 *
		 * @since 1.0.0
		 */
		private static function ensure_prompt_type_terms() {
			if ( get_option( 'gg_data_prompt_type_terms_seeded' ) ) {
				return;
			}

			if ( ! class_exists( 'GG_Data_Prompt' ) || ! taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
				return;
			}

			$terms = array(
				'system'   => __( 'System Prompt', 'gregius-data' ),
				'security' => __( 'Security Prompt', 'gregius-data' ),
			);

			foreach ( $terms as $slug => $name ) {
				$existing = get_term_by( 'slug', $slug, GG_Data_Prompt::TAXONOMY );

				if ( ! $existing ) {
					wp_insert_term(
						$name,
						GG_Data_Prompt::TAXONOMY,
						array(
							'slug' => $slug,
						)
					);
				}
			}

			update_option( 'gg_data_prompt_type_terms_seeded', true );
		}

		/**
		 * Seed factory prompts for system answer generation and security gatekeeping.
		 *
		 * @since 1.0.0
		 */
		private static function seed_default_prompts() {
			self::seed_prompt_post(
				'gg_data_default_prompt_seeded',
				__( 'System Default', 'gregius-data' ),
				'system',
				'You are a helpful assistant for a WordPress website. Current date: {{date}}. Current time: {{time}}.

IMPORTANT: Answer questions using ONLY the provided context. Include inline citations using [Source N] markers that match the context labels (for example: [Source 1]). Keep writing natural and conversational.',
				true
			);

			self::seed_prompt_post(
				'gg_data_security_prompt_seeded',
				__( 'Security Default', 'gregius-data' ),
				'security',
				'You are a security gatekeeper for a WordPress site assistant. Review the user query and classify it as SAFE or UNSAFE.

Mark UNSAFE when the query requests illegal, harmful, exploitative, violent, hateful, sexual, self-harm, malware, privacy invasion, credential theft, social engineering, or policy-evading instructions.

Respond with this exact JSON shape only:
{"status":"SAFE|UNSAFE","reason":"short reason"}

Keep reason concise. Do not add extra keys or text.',
				true
			);
		}

		/**
		 * Seed a factory prompt post if it does not exist yet.
		 *
		 * @since 1.0.0
		 * @param string $option_name Site option used as idempotency guard.
		 * @param string $title       Prompt title.
		 * @param string $prompt_type Prompt type term slug.
		 * @param string $content     Prompt content.
		 * @param bool   $selected    Whether prompt is active by default.
		 */
		private static function seed_prompt_post( $option_name, $title, $prompt_type, $content, $selected ) {
			if ( get_option( $option_name ) ) {
				return;
			}

			if ( ! class_exists( 'GG_Data_Prompt' ) ) {
				return;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => GG_Data_Prompt::POST_TYPE,
					'post_status'  => 'publish',
					'post_author'  => 1,
					'post_title'   => $title,
					'post_content' => wp_kses_post( $content ),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return;
			}

			$hash = hash( 'sha256', str_replace( "\r\n", "\n", trim( $content ) ) );

			update_post_meta( $post_id, '_gg_prompt_version', 1 );
			update_post_meta( $post_id, '_gg_prompt_status', 'published' );
			update_post_meta( $post_id, '_gg_prompt_hash', $hash );
			update_post_meta( $post_id, '_gg_prompt_notes', '' );
			update_post_meta( $post_id, '_gg_prompt_is_factory', '1' );
			update_post_meta( $post_id, '_gg_prompt_selected', $selected ? '1' : '' );

			if ( taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
				wp_set_object_terms( $post_id, array( sanitize_key( $prompt_type ) ), GG_Data_Prompt::TAXONOMY, false );
			}

			update_option( $option_name, true );
		}

		/**
		 * Ensure legacy prompts have a prompt type assigned.
		 *
		 * @since 1.0.0
		 */
		private static function migrate_prompt_types() {
			if ( get_option( 'gg_data_prompt_type_migrated' ) ) {
				return;
			}

			if ( ! class_exists( 'GG_Data_Prompt' ) || ! taxonomy_exists( GG_Data_Prompt::TAXONOMY ) ) {
				return;
			}

			$prompts = get_posts(
				array(
					'post_type'      => GG_Data_Prompt::POST_TYPE,
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $prompts as $prompt_id ) {
				$terms = wp_get_object_terms( (int) $prompt_id, GG_Data_Prompt::TAXONOMY, array( 'fields' => 'slugs' ) );
				if ( is_wp_error( $terms ) || ! empty( $terms ) ) {
					continue;
				}

				wp_set_object_terms( (int) $prompt_id, array( 'system' ), GG_Data_Prompt::TAXONOMY, false );
			}

			update_option( 'gg_data_prompt_type_migrated', true );
		}

		/**
		 * Replace any baked-in date/time values in the system default prompt with
		 * {{date}} / {{time}} placeholders so the resolver can inject live values.
		 *
		 * Runs once, guarded by a site option.
		 */
		private static function migrate_factory_prompt_to_placeholders() {
			if ( get_option( 'gg_data_factory_prompt_placeholders_migrated' ) ) {
				return;
			}

			$posts = get_posts(
				array(
					'post_type'   => 'gg_prompt',
					'post_status' => 'publish',
					'numberposts' => -1,
				)
			);

			foreach ( $posts as $post ) {
				if ( '1' !== (string) get_post_meta( $post->ID, '_gg_prompt_is_factory', true ) ) {
					continue;
				}

				$content = $post->post_content;

				// Replace "Current date: <anything>." and "Current time: <anything>."
				// with their placeholder equivalents.
				$content = preg_replace(
					'/Current date: [^.]+\./',
					'Current date: {{date}}.',
					$content
				);
				$content = preg_replace(
					'/Current time: [^.]+\./',
					'Current time: {{time}}.',
					$content
				);

				if ( $content !== $post->post_content ) {
					wp_update_post(
						array(
							'ID'           => $post->ID,
							'post_content' => wp_kses_post( $content ),
						)
					);
					$hash = hash( 'sha256', str_replace( "\r\n", "\n", trim( $content ) ) );
					update_post_meta( $post->ID, '_gg_prompt_hash', $hash );
				}
			}

			update_option( 'gg_data_factory_prompt_placeholders_migrated', true );
		}

		/**
		 * Check plugin version and run upgrades if needed
		 * Called on admin_init hook to catch updates while plugin is active
		 */
		public static function check_version() {
			$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

			if ( version_compare( $installed_version, self::VERSION, '<' ) ) {
				self::run_upgrades();

				if ( function_exists( 'gg_data_migrate_search_settings_scope' ) ) {
					gg_data_migrate_search_settings_scope();
				}

				self::ensure_prompt_type_terms();
				self::seed_default_prompts();
				self::migrate_prompt_types();
				self::migrate_factory_prompt_to_placeholders();
			}
		}

		/**
		 * Run all necessary upgrade routines
		 * Upgrades run in sequence based on version comparison
		 */
		private static function run_upgrades() {
			$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

			// Update version number only after successful upgrades.
			update_option( self::VERSION_OPTION, self::VERSION );

			// Show admin notice for successful upgrade.
			if ( version_compare( $installed_version, self::VERSION, '<' ) ) {
				add_action(
					'admin_notices',
					function () use ( $installed_version ) {
						$class   = 'notice notice-success is-dismissible';
						$message = sprintf(
							/* translators: 1: old version, 2: new version */
							__( 'Gregius Data updated from version %1$s to %2$s successfully.', 'gregius-data' ),
							$installed_version,
							self::VERSION
						);
						printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
					}
				);
			}
		}

		/**
		 * Initialize default sync settings for all connections
		 * Starts with empty defaults to force user selection
		 *
		 * @since 1.0.0
		 */
		private static function init_default_sync_settings() {
			// Empty defaults - user must explicitly choose what to sync.
			$defaults = array(
				'enabled_post_types' => array(),
				'enabled_statuses'   => array(),
				'real_time_sync'     => false,
				'sync_meta'          => true,
				'sync_terms'         => true,
			);

			// Store defaults in wp_options for global access.
			foreach ( $defaults as $key => $value ) {
				$option_name = 'gg_data_sync_' . $key;
				if ( false === get_option( $option_name ) ) {
					update_option( $option_name, $value );
				}
			}
		}

		/**
		 * Schedule log retention event for each site context.
		 *
		 * @since 1.0.0
		 */
		private static function schedule_log_retention_events() {
			if ( ! class_exists( 'GG_Data_Log_Retention' ) ) {
				return;
			}

			if ( is_multisite() ) {
				$sites = get_sites( array( 'number' => 0 ) );

				foreach ( $sites as $site ) {
					switch_to_blog( (int) $site->blog_id );
					GG_Data_Log_Retention::schedule_for_current_site();
					restore_current_blog();
				}

				return;
			}

			GG_Data_Log_Retention::schedule_for_current_site();
		}
	}
}
