<?php
/**
 * Plugin Name:       Gregius Data
 * Plugin URI:        https://gregius.com/gregius-data
 * Description:       Multi-provider AI orchestration for WordPress. RAG pipelines, agentic workflows, and vector search.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Hector Jarquin, Gregius
 * Author URI:        https://gregius.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gregius-data
 * Domain Path:       /languages
 *
 * @package           gregius-data
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Check WordPress version requirement (6.9+ for Abilities API).
if ( version_compare( $GLOBALS['wp_version'], '6.9', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: 1: Required WordPress version, 2: Current WordPress version, 3: WordPress update URL */
							__( '<strong>Gregius Data</strong> requires WordPress %1$s or higher (you are running %2$s). This plugin is built natively on the WordPress Abilities API introduced in WordPress 6.9. <a href="%3$s" target="_blank">Update WordPress</a>', 'gregius-data' ),
							array(
								'strong' => array(),
								'a'      => array(
									'href'   => array(),
									'target' => array(),
								),
							)
						),
						esc_html( '6.9' ),
						esc_html( $GLOBALS['wp_version'] ),
						esc_url( admin_url( 'update-core.php' ) )
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

// Define plugin constants.
// GREGIUS_DATA_VERSION must match the plugin header Version in gregius-data.php.
define( 'GREGIUS_DATA_VERSION', '1.0.0' );
define( 'GREGIUS_DATA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GREGIUS_DATA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GREGIUS_DATA_DEBUG_MODE', false ); // Set to true to enable debug logging.
// Include necessary files safely.
$gregius_data_files = array(
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-sanitizer.php', // Shared recursive input sanitizer.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-logger.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-log-retention.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-debug.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-cron-manager.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-table-prefix-resolver.php',

	// Provider architecture - Must load before Gregius_Data_DB.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/providers/interface-gregius-data-db-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/providers/class-gregius-data-provider-factory.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/providers/class-gregius-data-postgresql-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/providers/class-gregius-data-postgrest-provider.php',

	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-db.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-schema-manager.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-settings-manager.php', // Settings manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-model-registry.php', // Model registry.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-model-catalog.php', // Curated model catalog.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-model-discovery.php', // Live model discovery + lifecycle.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-connection-model-manager.php', // Connection-Model manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-error-handler.php', // Error classification.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-retry-queue.php', // Retry queue manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-connection-health.php', // Connection health monitor.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-sync-validator.php', // Sync validator.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-sync-metadata-manager.php', // Sync metadata manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-orphan-manager.php', // Orphan manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-sync-service.php', // Sync service.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-connection-manager.php', // Connection manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-abilities-manager.php', // Abilities manager (AI).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/hooks/class-gregius-data-lifecycle-hooks.php', // Lifecycle hooks.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/hooks/class-gregius-data-rag-security-hooks.php', // RAG security hooks.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-content-processor.php', // Content processing.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-chunker.php', // Content chunking for embeddings.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-content-source.php', // Content source resolver (posts vs attachments).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-content-cleaner.php', // Content cleaner service.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/trait-gregius-data-format-json-output.php', // Shared JSON output formatter.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-evaluation-service.php', // Evaluation orchestration service.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/sync/class-gregius-data-sync-hooks.php', // Sync hooks.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/vectors/interface-gregius-data-vector-strategy.php', // Vector strategy interface.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/vectors/class-gregius-data-vector-generator.php', // Vector generator orchestrator.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-admin.php', // Admin interface (menu + React dashboard container, no duplicate AJAX).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-activator.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-deactivator.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-uninstaller.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/batch/class-gregius-data-post-sync.php', // Post sync.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/batch/class-gregius-data-postmeta-sync.php', // Postmeta sync.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/batch/class-gregius-data-taxonomy-sync.php', // Taxonomy sync.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/batch/class-gregius-data-clean-batch.php', // Clean batch.

	// REST API classes.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-settings-controller.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-data-retention-controller.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-connections-controller.php', // Connection API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-schema-controller.php', // Schema API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-sync-controller.php', // Sync API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-vector-queue-controller.php', // Vector Queue API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-retry-queue-controller.php', // Retry Queue API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-connection-health-controller.php', // Connection Health API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-sync-validator-controller.php', // Sync Validator API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-models-controller.php', // Models API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-connection-models-controller.php', // Connection-Models API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-logs-controller.php', // Logs API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-interactions-controller.php', // Interactions API (admin-only).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-api.php',

	// Search classes.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/search/search-settings.php', // Search default settings.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/search/class-gregius-data-search-fallback.php', // Search fallback manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/search/class-gregius-data-search-schema.php', // Search schema manager.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/search/class-gregius-data-search-integration.php', // WordPress search integration.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-search-controller.php', // Search REST API.

	// AI Provider Architecture.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/interfaces/interface-gregius-data-ai-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/class-gregius-data-llm-registry.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-openai-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-deepseek-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-anthropic-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-gemini-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-cohere-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-internal-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gregius-data-typesafe-provider.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/class-gregius-data-ai-request.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/class-gregius-data-ai-client.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/class-gregius-data-document-extractor.php',

	// Tool Calling Strategies (for RAG tool selection).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/strategies/interface-gregius-data-tool-calling-strategy.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gregius-data-prompt-tool-strategy.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gregius-data-openai-tool-strategy.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gregius-data-anthropic-tool-strategy.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gregius-data-gemini-tool-strategy.php',
	GREGIUS_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gregius-data-tool-strategy-factory.php',

	// RAG classes.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-openai-client.php', // OpenAI API client.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-token-counter.php', // Token counting and usage tracking.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/rag/class-gregius-data-manifest-validator.php', // Manifest schema contract + normalizer.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/rag/class-gregius-data-coverage-gate.php', // Coverage gate (evidence sufficiency policy).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/rag/class-gregius-data-rag-service.php', // RAG service (embedding-agnostic).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/rag/class-gregius-data-entity-grounding.php', // Entity grounding (current post → RAG prompts).
	GREGIUS_DATA_PLUGIN_DIR . 'includes/rag/class-gregius-data-entity-content-tool.php', // search_entity_content RAG tool.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-rag-controller.php', // RAG REST API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-rag-journey-controller.php', // RAG journey continuity REST API.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-sse-handler.php', // SSE streaming for RAG progress.

	// Interaction tracking.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-interaction.php', // Interaction post type + recording functions.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-prompt.php', // Prompt post type + metadata.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/class-gregius-data-prompt-resolver.php', // Prompt resolver service.
	GREGIUS_DATA_PLUGIN_DIR . 'includes/api/class-gregius-data-rest-prompts-controller.php', // Prompt REST API.

	// Assets.
	GREGIUS_DATA_PLUGIN_DIR . 'assets/assets.php',

	// Blocks.
	GREGIUS_DATA_PLUGIN_DIR . 'blocks/rag-assistant/rag-assistant.php',
);

foreach ( $gregius_data_files as $file ) {
	if ( file_exists( $file ) ) {
		require_once $file;
	} else {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical: Log missing required files for production debugging
		error_log( 'Gregius PostgreSQL plugin error: Missing required file ' . $file );
	}
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Gregius_Data_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Gregius_Data_Deactivator', 'deactivate' ) );

// Check version on every admin page load (catches updates while plugin is active).
add_action( 'admin_init', array( 'Gregius_Data_Activator', 'check_version' ) );

// Register custom cron schedules (must happen before any wp_schedule_event calls).
Gregius_Data_Cron_Manager::register_schedules();

/**
 * Initialize the Gregius PostgreSQL plugin and its admin components.
 */
function gregius_data_init() {
	// Initialize the main plugin class.
	$gregius_data = new Gregius_Data();
	$gregius_data->init();

	// Instantiate the schema manager (used by the cron hook below; no runtime init required).
	$gregius_data_schema = new Gregius_Data_Schema_Manager();

	// Initialize retry queue manager.
	$gregius_data_retry_queue = new Gregius_Data_Retry_Queue();

	// Initialize connection health monitor.
	$gregius_data_connection_health = new Gregius_Data_Connection_Health();

	// Initialize sync validator.
	$gregius_data_sync_validator = new Gregius_Data_Sync_Validator();

	// Initialize search integration.
	$gregius_data_search_integration = new Gregius_Data_Search_Integration();
	$gregius_data_search_integration->init();

	// Initialize interaction tracking.
	$gregius_interaction = new Gregius_Data_Interaction();
	$gregius_interaction->init();

	// Initialize scheduled log retention handling.
	$gregius_data_log_retention = new Gregius_Data_Log_Retention();
	$gregius_data_log_retention->init();

	// Initialize prompt management.
	$gregius_prompt = new Gregius_Data_Prompt();
	$gregius_prompt->init();

	// Backfill prompt type terms for factory prompts (one-time repair).
	add_action( 'init', array( 'Gregius_Data_Activator', 'maybe_backfill_prompt_types' ), 11 );

	// Add connection health check cron hook.
	add_action( 'gregius_data_check_connection_health', array( $gregius_data_connection_health, 'perform_health_check' ) );

	// Add daily sync validation cron hook.
	add_action( 'gregius_data_daily_validation', array( $gregius_data_sync_validator, 'run_validation' ) );

	// Initialize minimal admin interface (creates WordPress menu + React dashboard container).
	// All AJAX handlers are in their respective feature classes.
	// All REST API endpoints are in the REST API controllers.
	if ( is_admin() ) {
		$gregius_data_admin = new Gregius_Data_Admin();
		$gregius_data_admin->init();
	}

	// Initialize REST API.
	$gregius_data_rest_api = new Gregius_Data_REST_API();
	$gregius_data_rest_api->init();

	// Initialize SSE handler for RAG streaming (AJAX-based, not REST).
	new Gregius_Data_SSE_Handler();

	// Initialize RAG security hooks (access control for public endpoints).
	new Gregius_Data_RAG_Security_Hooks();

	// Initialize entity grounding (inject the current post into RAG prompts).
	new Gregius_Data_Entity_Grounding();

	// Register the search_entity_content RAG tool.
	Gregius_Data_Entity_Content_Tool::init();
}
// Hook into plugins_loaded to ensure all dependencies are loaded.
add_action( 'plugins_loaded', 'gregius_data_init' );

/**
 * Register AI providers
 *
 * @param array $providers Existing providers.
 * @return array Modified providers array.
 */
function gregius_data_register_providers( $providers ) {
	// Register OpenAI provider (LLM + Embeddings).
	$providers['openai'] = new Gregius_Data_OpenAI_Provider();

	// Register DeepSeek provider (LLM only - 18x cheaper than GPT-4o).
	$providers['deepseek'] = new Gregius_Data_DeepSeek_Provider();

	// Register Anthropic provider (LLM only - Best instruction following).
	$providers['anthropic'] = new Gregius_Data_Anthropic_Provider();

	// Register Google Gemini provider (LLM + Embeddings - Free tier available).
	$providers['gemini'] = new Gregius_Data_Gemini_Provider();

	// Register Cohere provider (Embeddings + Reranking - Industry-leading reranking).
	$providers['cohere'] = new Gregius_Data_Cohere_Provider();

	// Register Internal provider (HashingTF Embeddings - Free Tier).
	$providers['internal'] = new Gregius_Data_Internal_Provider();

	// Register TypeSafe provider (Evaluator/Decision models - Jev).
	$providers['typesafe'] = new Gregius_Data_Typesafe_Provider();

	return $providers;
}
add_filter( 'gregius_data_llm_providers', 'gregius_data_register_providers' );

/**
 * Initialize WP-CLI commands if WP-CLI is available.
 *
 * @since 1.0.0
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GREGIUS_DATA_PLUGIN_DIR . 'includes/cli/class-gregius-data-cli.php';
	Gregius_Data_CLI::register_commands();
}
