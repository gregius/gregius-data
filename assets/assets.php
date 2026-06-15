<?php
/**
 * Asset Management for Dashboard, Frontend and Editor
 *
 * This file contains functions to enqueue frontend styles and scripts,
 * as well as styles and scripts for the WordPress block editor and admin dashboard.
 *
 * @package gregius-data
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue frontend styles and scripts.
 *
 * @return void
 */
function gg_data_frontend_scripts_support() {
	// Enqueue the main stylesheet.
	$css_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/frontend.min.*.css' );
	if ( ! empty( $css_file_path ) && file_exists( $css_file_path[0] ) ) {
		$css_file_uri = plugin_dir_url( __FILE__ ) . 'build/' . basename( $css_file_path[0] );
		wp_enqueue_style( 'gg-data-frontend-style', $css_file_uri, array(), filemtime( $css_file_path[0] ), 'all' );
	}

	// Enqueue the main JavaScript file.
	$js_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/frontend.min.*.js' );
	if ( ! empty( $js_file_path ) && file_exists( $js_file_path[0] ) ) {
		$js_file_uri = plugin_dir_url( __FILE__ ) . 'build/' . basename( $js_file_path[0] );
		wp_enqueue_script( 'gg-data-frontend-script', $js_file_uri, array(), filemtime( $js_file_path[0] ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'gg_data_frontend_scripts_support', 999 );

/**
 * Enqueue editor styles.
 *
 * @return void
 */
function gg_data_editor_styles_support() {
	// Check if editor CSS file exists (may not be built).
	$css_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/editor.*.css' );
	if ( ! empty( $css_file_path ) && file_exists( $css_file_path[0] ) ) {
		add_editor_style( plugin_dir_url( __FILE__ ) . 'build/' . basename( $css_file_path[0] ) );
	}
}
add_action( 'after_setup_theme', 'gg_data_editor_styles_support' );

/**
 * Enqueue editor scripts.
 *
 * @return void
 */
function gg_data_editor_scripts_support() {
	// Enqueue the editor scripts.
	wp_enqueue_script(
		'gg-data-site-editor-script',
		plugin_dir_url( __FILE__ ) . 'build/editor.js',
		array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post' ),
		'1.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'gg_data_editor_scripts_support' );

/**
 * Enqueue dashboard styles and scripts.
 *
 * @return void
 */
function gg_data_dashboard_scripts_support() {
	// Only load on our plugin's admin pages.
	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'gregius-data' ) === false ) {
		return;
	}

	// Ensure WordPress REST API is initialized.
	wp_enqueue_script( 'wp-api' );

	// Enqueue the dashboard stylesheet.
	$css_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/dashboard.min.*.css' );
	if ( ! empty( $css_file_path ) && file_exists( $css_file_path[0] ) ) {
		$css_file_uri = plugin_dir_url( __FILE__ ) . 'build/' . basename( $css_file_path[0] );
		wp_enqueue_style( 'gg-data-dashboard-style', $css_file_uri, array(), filemtime( $css_file_path[0] ), 'all' );
	}

	// Enqueue WordPress Components styles.
	$style_css_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/style-dashboard.min.*.css' );
	if ( ! empty( $style_css_file_path ) && file_exists( $style_css_file_path[0] ) ) {
		$style_css_file_uri = plugin_dir_url( __FILE__ ) . 'build/' . basename( $style_css_file_path[0] );
		wp_enqueue_style( 'gg-data-dashboard-components', $style_css_file_uri, array(), filemtime( $style_css_file_path[0] ), 'all' );
	}

	// Get the asset file for dependencies.
	$asset_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/dashboard.min.*.asset.php' );
	$asset_file      = ! empty( $asset_file_path ) && file_exists( $asset_file_path[0] ) ? include $asset_file_path[0] : array();

	$dependencies = isset( $asset_file['dependencies'] ) ? $asset_file['dependencies'] : array(
		'wp-element',
		'wp-i18n',
		'wp-components',
		'wp-api-fetch',
		'wp-data',
		'wp-core-data',
		'wp-dom-ready',
		'wp-url',
	);
	$version      = isset( $asset_file['version'] ) ? $asset_file['version'] : null;

	// Enqueue the dashboard JavaScript file with proper dependencies.
	$js_file_path = glob( plugin_dir_path( __FILE__ ) . 'build/dashboard.min.*.js' );
	if ( ! empty( $js_file_path ) && file_exists( $js_file_path[0] ) ) {
		$js_file_uri = plugin_dir_url( __FILE__ ) . 'build/' . basename( $js_file_path[0] );
		wp_enqueue_script( 'gg-data-dashboard-script', $js_file_uri, $dependencies, $version ?? filemtime( $js_file_path[0] ), true );

		// Ensure WordPress REST API settings are available.
		wp_localize_script(
			'gg-data-dashboard-script',
			'ggDataSettings',
			array(
				'root'          => esc_url_raw( rest_url() ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'versionString' => 'wp/v2/',
			)
		);

		// Add inline script to set up API configuration.
		wp_add_inline_script(
			'gg-data-dashboard-script',
			'
            window.ggPgDashboard = {
                apiUrl: "' . esc_js( rest_url( 'gg-data/v1/' ) ) . '",
                restUrl: "' . esc_js( rest_url() ) . '",
                nonce: "' . esc_js( wp_create_nonce( 'wp_rest' ) ) . '",
                currentUser: ' . wp_json_encode( array(
	'id'           => get_current_user_id(),
	'display_name' => wp_get_current_user()->display_name,
	'email'        => wp_get_current_user()->user_email,
) ) . ',
                pluginUrl: "' . esc_js( plugin_dir_url( __DIR__ ) ) . '"
            };
        ',
			'before'
		);
	}
}
add_action( 'admin_enqueue_scripts', 'gg_data_dashboard_scripts_support' );

/**
 * Clear localStorage when plugin is deactivated
 */
function gg_data_clear_localstorage_script() {
	if ( get_transient( 'gg_data_clear_localstorage' ) ) {
		delete_transient( 'gg_data_clear_localstorage' );
		?>
		<script>
			// Clear Gregius Data localStorage on deactivation.
			if (typeof localStorage !== 'undefined') {
				const keysToRemove = [];
				for (let i = 0; i < localStorage.length; i++) {
					const key = localStorage.key(i);
					if (key && key.startsWith('gg_data_')) {
						keysToRemove.push(key);
					}
				}
				keysToRemove.forEach(key => localStorage.removeItem(key));
				console.log('Gregius Data: Cleared ' + keysToRemove.length + ' localStorage items after deactivation');
			}
		</script>
		<?php
	}
}
add_action( 'admin_head', 'gg_data_clear_localstorage_script' );
