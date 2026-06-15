<?php
/**
 * PHPUnit bootstrap for Gregius Data.
 *
 * - Unit & Integration tiers: load Composer autoloader + stubs only.
 * - Full WP tier: load wp-phpunit to bootstrap a WordPress test environment.
 *
 * Set WP_TESTS_RUN=1 to enable the full WordPress test tier, or set
 * WP_TESTS_CONFIG_FILE_PATH to point at wp-tests-config.php.
 *
 * @package gregius-data
 */

$running_wp_tests = ( getenv( 'WP_TESTS_RUN' ) || defined( 'WP_TESTS_CONFIG_FILE_PATH' ) );

if ( ! $running_wp_tests && ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', true );
}

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

if ( ! $running_wp_tests ) {
    require_once __DIR__ . '/helpers.php';
    gg_data_test_setup_wpdb();
}

$run_wp_tests = getenv( 'WP_TESTS_RUN' );
$has_config   = defined( 'WP_TESTS_CONFIG_FILE_PATH' )
    && file_exists( WP_TESTS_CONFIG_FILE_PATH );

if ( ! $has_config && $run_wp_tests ) {
    $config_paths = [
        '/var/www/html/wp-tests-config.php',
        __DIR__ . '/wp-tests-config.php',
    ];

    foreach ( $config_paths as $path ) {
        if ( file_exists( $path ) ) {
            define( 'WP_TESTS_CONFIG_FILE_PATH', $path );
            $has_config = true;
            break;
        }
    }
}

if ( $has_config ) {
    $plugin_dir  = dirname( __DIR__ );
    $wp_test_base_paths = [
        $plugin_dir . '/tests/wp-phpunit',
        dirname( __DIR__ ) . '/tests/wp-phpunit',
    ];

    $wp_test_base = null;
    foreach ( $wp_test_base_paths as $path ) {
        if ( is_dir( $path ) ) {
            $wp_test_base = $path;
            break;
        }
    }

    if ( ! $wp_test_base ) {
        echo "Error: wp-phpunit not found. Run:\n";
        echo "  svn co https://develop.svn.wordpress.org/trunk/tests/phpunit tests/wp-phpunit\n";
        exit( 1 );
    }

    $functions_file = $wp_test_base . '/includes/functions.php';
    if ( file_exists( $functions_file ) ) {
        require_once $functions_file;
    }

    tests_add_filter( 'muplugins_loaded', function () use ( $plugin_dir ) {
        require_once $plugin_dir . '/gregius-data.php';
    } );

    tests_add_filter( 'setup_theme', function () {
        if ( ! get_option( 'gg_data_db_version' ) ) {
            GG_Data_Activator::activate();
        }
    } );

    $bootstrap_file = $wp_test_base . '/includes/bootstrap.php';
    if ( file_exists( $bootstrap_file ) ) {
        require_once $bootstrap_file;
    } else {
        echo "Error: wp-phpunit bootstrap not found at {$bootstrap_file}\n";
        exit( 1 );
    }
}
