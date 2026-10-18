<?php
/**
 * WP-CLI Bootstrap
 *
 * Registers all Gregius Data WP-CLI commands.
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
 * WP-CLI Main Bootstrap Class
 *
 * @since 1.0.0
 */
class Gregius_Data_CLI {

	/**
	 * Register all CLI commands
	 *
	 * Called from main plugin file when WP_CLI is defined.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_commands() {
		// Ensure WP-CLI is available.
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// Load command classes.
		$cli_dir = GREGIUS_DATA_PLUGIN_DIR . 'includes/cli/';

		require_once $cli_dir . 'class-gregius-data-cli-sync.php';
		require_once $cli_dir . 'class-gregius-data-cli-vectors.php';
		require_once $cli_dir . 'class-gregius-data-cli-answer.php';
		require_once $cli_dir . 'class-gregius-data-cli-namespace.php';
		require_once $cli_dir . 'class-gregius-data-cli-evaluation.php';
		require_once $cli_dir . 'class-gregius-data-cli-list-connections.php';
		require_once $cli_dir . 'class-gregius-data-cli-list-models.php';
		require_once $cli_dir . 'class-gregius-data-cli-logs.php';

		// Register parent command namespace.
		WP_CLI::add_command( 'gregius-data', 'Gregius_Data_CLI_Namespace' );

		// Register subcommands.
		WP_CLI::add_command( 'gregius-data sync', 'Gregius_Data_CLI_Sync' );
		WP_CLI::add_command( 'gregius-data vectors', 'Gregius_Data_CLI_Vectors' );
		WP_CLI::add_command( 'gregius-data answer', 'Gregius_Data_CLI_Answer' );
		WP_CLI::add_command( 'gregius-data evaluation', 'Gregius_Data_CLI_Evaluation' );
		WP_CLI::add_command( 'gregius-data list-connections', 'Gregius_Data_CLI_List_Connections' );
		WP_CLI::add_command( 'gregius-data list-models', 'Gregius_Data_CLI_List_Models' );
		WP_CLI::add_command( 'gregius-data logs', 'Gregius_Data_CLI_Logs' );
	}
}
