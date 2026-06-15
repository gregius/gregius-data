<?php
/**
 * Gregius Data Uninstall
 *
 * Uninstalling Gregius Data deletes custom tables and options from the database.
 *
 * @package Gregius_Data
 * @version 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Get all blog IDs for multisite support.
$blog_ids = array( 1 );
if ( is_multisite() ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to fetch all blog IDs from WordPress core table for multisite uninstall cleanup
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
}

// Loop through each blog and clean up.
foreach ( $blog_ids as $site_blog_id ) {
	if ( is_multisite() ) {
		switch_to_blog( (int) $site_blog_id );
	}

	// Unschedule all cron events.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		// Retry queue processing.
		as_unschedule_all_actions( 'gg_data_process_retry_queue', null, 'gregius-data' );

		// Batch operations.
		as_unschedule_all_actions( 'gg_data_process_batch_sync', null, 'gregius-data' );
		as_unschedule_all_actions( 'gg_data_process_batch_embeddings', null, 'gregius-data' );

		// Content sync hooks.
		as_unschedule_all_actions( 'gg_data_sync_post', null, 'gregius-data' );
		as_unschedule_all_actions( 'gg_data_delete_post', null, 'gregius-data' );
	} else {
		// Fallback to wp_clear_scheduled_hook if Action Scheduler not available.
		wp_clear_scheduled_hook( 'gg_data_process_retry_queue' );
		wp_clear_scheduled_hook( 'gg_data_process_batch_sync' );
		wp_clear_scheduled_hook( 'gg_data_process_batch_embeddings' );
		wp_clear_scheduled_hook( 'gg_data_sync_post' );
		wp_clear_scheduled_hook( 'gg_data_delete_post' );
	}

	// Delete all plugin options from wp_options.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin options during uninstall
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gg\_data\_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin options during uninstall
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gregius\_data\_%'" );

	// Delete transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin transients during uninstall
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_gg\_data\_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin transients during uninstall
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_gg\_data\_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin transients during uninstall
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_gg\_data\_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin transients during uninstall
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_timeout\_gg\_data\_%'" );

	// Drop custom database tables.
	$prefix = $wpdb->prefix;

	// Settings table.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required to drop custom plugin table during uninstall, table name cannot be parameterized
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}gg_settings" );

	// Sync metadata table.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required to drop custom plugin table during uninstall, table name cannot be parameterized
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}gg_sync_metadata" );

	// Clear any cached data.
	wp_cache_flush();

	if ( is_multisite() ) {
		restore_current_blog();
	}
}

// Delete site options for multisite.
if ( is_multisite() ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin site options during multisite uninstall
	$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'gg\_data\_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to delete all plugin site options during multisite uninstall
	$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'gregius\_data\_%'" );
}
