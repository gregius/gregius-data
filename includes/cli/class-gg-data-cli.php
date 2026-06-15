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
class GG_Data_CLI {

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
		$cli_dir = GG_DATA_PLUGIN_DIR . 'includes/cli/';

		require_once $cli_dir . 'class-gg-data-cli-sync.php';
		require_once $cli_dir . 'class-gg-data-cli-vectors.php';
		require_once $cli_dir . 'class-gg-data-cli-answer.php';
		require_once $cli_dir . 'class-gg-data-cli-namespace.php';
		require_once $cli_dir . 'class-gg-data-cli-benchmark.php';
		require_once $cli_dir . 'class-gg-data-cli-evaluation.php';
		require_once $cli_dir . 'class-gg-data-cli-list-connections.php';
		require_once $cli_dir . 'class-gg-data-cli-list-models.php';
		require_once $cli_dir . 'class-gg-data-cli-logs.php';

		// Register parent command namespace.
		WP_CLI::add_command( 'gg-data', 'GG_Data_CLI_Namespace' );

		// Register subcommands.
		WP_CLI::add_command( 'gg-data sync', 'GG_Data_CLI_Sync' );
		WP_CLI::add_command( 'gg-data vectors', 'GG_Data_CLI_Vectors' );
		WP_CLI::add_command( 'gg-data answer', 'GG_Data_CLI_Answer' );
		WP_CLI::add_command( 'gg-data benchmark', 'GG_Data_CLI_Benchmark' );
		WP_CLI::add_command( 'gg-data evaluation', 'GG_Data_CLI_Evaluation' );
		WP_CLI::add_command( 'gg-data list-connections', 'GG_Data_CLI_List_Connections' );
		WP_CLI::add_command( 'gg-data list-models', 'GG_Data_CLI_List_Models' );
		WP_CLI::add_command( 'gg-data logs', 'GG_Data_CLI_Logs' );
	}
}
