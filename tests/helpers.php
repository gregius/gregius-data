<?php
/**
 * Shared test helpers for Gregius Data unit tests.
 *
 * Provides common mocks and global state setup used across multiple test files.
 *
 * @package gregius-data
 */

/**
 * Stub WP_Error for unit tests where WordPress core is not loaded.
 */
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( $code ) {
                $this->errors[ $code ][] = $message;
            }
            if ( $data ) {
                $this->error_data[ $code ] = $data;
            }
        }

        public function get_error_code() {
            if ( empty( $this->errors ) ) {
                return '';
            }
            return key( $this->errors );
        }

        public function get_error_message( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return $this->errors[ $code ][0] ?? '';
        }
    }
}

/**
 * Set up the global $wpdb mock for unit tests.
 *
 * Must be called before Brain\Monkey\setUp() to ensure
 * the global is available when source constructors run.
 */
function gg_data_test_setup_wpdb() {
    global $wpdb;
    if ( ! isset( $wpdb ) ) {
        $wpdb = new stdClass();
    }
    $wpdb->prefix = 'wp_';
}

/**
 * Set up common Brain\Monkey function stubs needed by most tests.
 *
 * Call from setUp() after parent::setUp() and Monkey\setUp().
 */
function gg_data_test_stub_common_functions() {
    Brain\Monkey\Functions\when( 'get_current_blog_id' )->justReturn( 1 );
    Brain\Monkey\Functions\when( 'current_time' )->justReturn( '2025-01-01 00:00:00' );
    Brain\Monkey\Functions\when( '__' )->alias(
        function ( $text ) {
            return $text;
        }
    );
    Brain\Monkey\Functions\when( 'wp_kses_post' )->alias( 'strval' );
    Brain\Monkey\Functions\when( 'sanitize_text_field' )->alias( 'strval' );
    Brain\Monkey\Functions\when( 'absint' )->alias( 'intval' );
    Brain\Monkey\Functions\when( 'sanitize_key' )->alias( 'strval' );
    Brain\Monkey\Functions\when( 'wp_date' )->alias(
        function ( $format ) {
            return date( $format );
        }
    );
    Brain\Monkey\Functions\when( 'plugin_dir_path' )->alias(
        function ( $file ) {
            return dirname( $file ) . '/';
        }
    );
    Brain\Monkey\Functions\when( 'maybe_unserialize' )->alias(
        function ( $value ) {
            $unserialized = @unserialize( $value );
            if ( false !== $unserialized ) {
                return $unserialized;
            }
            return $value;
        }
    );
}
