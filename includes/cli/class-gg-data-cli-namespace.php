<?php
/**
 * WP-CLI Command Namespace
 *
 * Defines the top-level `wp gg-data` namespace and help examples.
 *
 * @package Gregius_Data
 * @subpackage CLI
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gregius Data - Multi-provider AI orchestration for WordPress.
 *
 * ## EXAMPLES
 *
 *     # Sync all posts to PostgreSQL
 *     $ wp gg-data sync posts --connection=gregius-data
 *
 *     # Generate vectors for search
 *     $ wp gg-data vectors generate --connection=gregius-data
 *
 *     # Ask a question using RAG
 *     $ wp gg-data answer "What is dementia care?"
 *
 *     # List available connections
 *     $ wp gg-data list-connections
 *
 *     # View recent logs
 *     $ wp gg-data logs list --limit=20
 *
 * @since 1.0.0
 */
class GG_Data_CLI_Namespace extends \WP_CLI\Dispatcher\CommandNamespace {
}
