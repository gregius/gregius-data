<?php
/**
 * Admin interface for Gregius Data
 *
 * This class handles:
 * 1. Creating the WordPress admin menu structure
 * 2. Rendering the React dashboard container
 * 3. Rendering admin pages (logs, etc.)
 *
 * All AJAX handlers are in their respective feature classes (Connection Manager, Schema Manager, etc.)
 * All REST API endpoints are in the REST API controllers
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GG_Data_Admin' ) ) {

	/**
	 * Admin interface class
	 */
	class GG_Data_Admin {

		/**
		 * Sidebar menu icon as inline SVG data URI.
		 */
		private const MENU_ICON_DATA_URI = 'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgd2lkdGg9IjMycHgiIGhlaWdodD0iMzJweCIgdmlld0JveD0iMCAwIDMyIDMyIiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCAzMiAzMiIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiNmM2YxZjE7fTwvc3R5bGU+PC9kZWZzPjxnPjxnPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTE2LjA3NSwxOC4zODZjLTAuNjE2LDAtMS4yMzMtMC4yMzUtMS43MDMtMC43MDZjLTAuOTQxLTAuOTQxLTAuOTQxLTIuNDY2LDAtMy40MDhsOS40MjctOS40MjdjMC45NDEtMC45NDEsMi40NjYtMC45NDEsMy40MDcsMGMwLjk0MSwwLjk0MSwwLjk0MSwyLjQ2NiwwLDMuNDA3bC05LjQyNyw5LjQyOEMxNy4zMDgsMTguMTUsMTYuNjkxLDE4LjM4NiwxNi4wNzUsMTguMzg2eiIvPjwvZz48Zz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0xNi4wODksMzEuNzQ4Yy0yLjAwMiwwLTQuMDA4LTAuMzY1LTUuODgyLTEuMTEyYy02LjI3OC0yLjUwNC04LjU5Ni03Ljk4OC05LjE1Ny05LjYxYy0xLjQzNi00LjE1Mi0wLjcwNi04LjU5OCwwLjM1NS0xMS4xNDVjMi4zNzEtNS42OTEsNy44MTEtOS4yOSwxNC41NDktOS42MjVjMS4zMjctMC4wNjcsMi40NTksMC45NTgsMi41MjYsMi4yODZzLTAuOTU4LDIuNDU5LTIuMjg2LDIuNTI2Yy00LjgzNywwLjI0MS04LjcwMywyLjczMy0xMC4zNDIsNi42NjdjLTAuNzY4LDEuODQzLTEuMTczLDUuMDQ0LTAuMjQ5LDcuNzE3YzAuMzkyLDEuMTMzLDIuMDEsNC45NjMsNi4zODksNi43MDljMy41MDEsMS4zOTcsNy44MzEsMC44MzksMTAuNzcyLTEuMzg5YzIuNjQ4LTIuMDA1LDQuMTY3LTUuMjEsNC4xNjctOC43OTVjMC0xLjMzMSwxLjA3OS0yLjQwOSwyLjQwOS0yLjQwOXMyLjQwOSwxLjA3OCwyLjQwOSwyLjQwOWMwLDUuMTA3LTIuMjE1LDkuNzEzLTYuMDc3LDEyLjYzN0MyMi45NTMsMzAuNjczLDE5LjUyNywzMS43NDgsMTYuMDg5LDMxLjc0OHoiLz48L2c+PC9nPjwvc3ZnPgo=';

		/**
		 * Initialize the admin interface
		 *
		 * @since 1.0.0
		 */
		public function init() {
			// Add admin menu.
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

			// Add admin notices for missing PHP extensions.
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}

		/**
		 * Add admin menu
		 *
		 * @since 1.0.0
		 */
		public function add_admin_menu() {
			// Add main menu page.
			add_menu_page(
				__( 'Data', 'gregius-data' ),
				__( 'Data', 'gregius-data' ),
				'manage_options',
				'gregius-data',
				array( $this, 'render_settings_page' ),
				self::MENU_ICON_DATA_URI,
				80
			);

			// Add submenu pages.
			add_submenu_page(
				'gregius-data',
				__( 'Settings', 'gregius-data' ),
				__( 'Settings', 'gregius-data' ),
				'manage_options',
				'gregius-data',
				array( $this, 'render_settings_page' )
			);

			// Note: Legacy PHP admin pages removed (Logs, Query).
			// All functionality now in React dashboard tabs.
		}
		/**
		 * Admin notices for missing PHP extensions
		 *
		 * @since 1.0.0
		 */
		public function admin_notices() {
			// Notice removed - plugin now supports multiple connection methods.
			// including Supabase, Neon, and direct PostgreSQL (when pdo_pgsql available).
		}

		/**
		 * Render main settings page with React dashboard
		 *
		 * @since 1.0.0
		 */
		public function render_settings_page() {
			?>
			<div class="wrap gg-data-admin">
				<!-- React Dashboard Container -->
				<div id="gg-data-react-dashboard">
					<!-- React dashboard will be rendered here by assets/assets.php -->
					<div class="gg-data-loading-placeholder">
						<p><?php esc_html_e( 'Loading dashboard...', 'gregius-data' ); ?></p>
					</div>
				</div>
			</div>
			<?php
		}
	}
}