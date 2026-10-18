<?php
/**
 * Gregius Data Uninstall
 *
 * Data is preserved by default. If the user opted in via the deactivation
 * modal (the `gregius_data_remove_data_on_uninstall` flag), WordPress-side data is
 * removed. The external PostgreSQL/Supabase mirror is never touched.
 *
 * @package Gregius_Data
 * @version 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-gregius-data-uninstaller.php';

Gregius_Data_Uninstaller::run();
