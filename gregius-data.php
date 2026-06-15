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
define( 'GG_DATA_VERSION', '1.0.0' );
define( 'GG_DATA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GG_DATA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GG_DATA_DEBUG_MODE', false ); // Set to true to enable debug logging.
// Include necessary files safely.
$gg_data_files = array(
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-logger.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-log-retention.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-debug.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-cron-manager.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-table-prefix-resolver.php',

	// Provider architecture - Must load before GG_DATA_DB.
	GG_DATA_PLUGIN_DIR . 'includes/providers/interface-gg-db-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/providers/class-gg-provider-factory.php',
	GG_DATA_PLUGIN_DIR . 'includes/providers/class-gg-postgresql-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/providers/class-gg-postgrest-provider.php',

	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-db.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-schema-manager.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-settings-manager.php', // Settings manager.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-model-registry.php', // Model registry.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-connection-model-manager.php', // Connection-Model manager.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-error-handler.php', // Error classification.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-retry-queue.php', // Retry queue manager.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-connection-health.php', // Connection health monitor.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-sync-validator.php', // Sync validator.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-sync-metadata-manager.php', // Sync metadata manager.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-orphan-manager.php', // Orphan manager.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-sync-service.php', // Sync service.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-connection-manager.php', // Connection manager.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-abilities-manager.php', // Abilities manager (AI).
	GG_DATA_PLUGIN_DIR . 'includes/hooks/class-gg-data-lifecycle-hooks.php', // Lifecycle hooks.
	GG_DATA_PLUGIN_DIR . 'includes/hooks/class-gg-data-rag-security-hooks.php', // RAG security hooks.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-content-processor.php', // Content processing.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-chunker.php', // Content chunking for embeddings.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-content-cleaner.php', // Content cleaner service.
	GG_DATA_PLUGIN_DIR . 'includes/trait-gg-data-format-json-output.php', // Shared JSON output formatter.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-benchmark-service.php', // Benchmark orchestration service.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-evaluation-service.php', // Evaluation orchestration service.
	GG_DATA_PLUGIN_DIR . 'includes/sync/class-gg-data-sync-hooks.php', // Sync hooks.
	GG_DATA_PLUGIN_DIR . 'includes/vectors/class-gg-data-tfidf-300-embeddings.php', // TF-IDF 300D embeddings.
	GG_DATA_PLUGIN_DIR . 'includes/vectors/class-gg-data-vocabulary-manager.php', // Vocabulary cache manager.
	GG_DATA_PLUGIN_DIR . 'includes/vectors/class-gg-data-vector-processor.php', // Vector processing controller.
	GG_DATA_PLUGIN_DIR . 'includes/vectors/interface-gg-data-vector-strategy.php', // Vector strategy interface.
	GG_DATA_PLUGIN_DIR . 'includes/vectors/class-gg-data-vector-generator.php', // Vector generator orchestrator.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-admin.php', // Admin interface (menu + React dashboard container, no duplicate AJAX).
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-activator.php',
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-deactivator.php',
	GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-post-sync.php', // Post sync.
	GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-postmeta-sync.php', // Postmeta sync.
	GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-taxonomy-sync.php', // Taxonomy sync.
	GG_DATA_PLUGIN_DIR . 'includes/batch/class-gg-data-clean-batch.php', // Clean batch.

	// REST API classes.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-settings-controller.php',
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-connections-controller.php', // Connection API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-schema-controller.php', // Schema API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-sync-controller.php', // Sync API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-vector-queue-controller.php', // Vector Queue API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-vocabulary-controller.php', // Vocabulary API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-retry-queue-controller.php', // Retry Queue API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-connection-health-controller.php', // Connection Health API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-sync-validator-controller.php', // Sync Validator API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-models-controller.php', // Models API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-connection-models-controller.php', // Connection-Models API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-logs-controller.php', // Logs API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-interactions-controller.php', // Interactions API (admin-only).
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-api.php',

	// Search classes.
	GG_DATA_PLUGIN_DIR . 'includes/search/search-settings.php', // Search default settings.
	GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-fallback.php', // Search fallback manager.
	GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-schema.php', // Search schema manager.
	GG_DATA_PLUGIN_DIR . 'includes/search/class-gg-data-search-integration.php', // WordPress search integration.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-search-controller.php', // Search REST API.

	// AI Provider Architecture.
	GG_DATA_PLUGIN_DIR . 'includes/interfaces/interface-gg-data-ai-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/class-gg-data-llm-registry.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-openai-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-deepseek-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-anthropic-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-gemini-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-voyage-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-cohere-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/providers/class-gg-data-internal-provider.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/class-gg-data-ai-request.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/class-gg-data-ai-client.php',

	// Tool Calling Strategies (for RAG tool selection).
	GG_DATA_PLUGIN_DIR . 'includes/ai/strategies/interface-gg-data-tool-calling-strategy.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gg-data-prompt-tool-strategy.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gg-data-openai-tool-strategy.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gg-data-anthropic-tool-strategy.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gg-data-gemini-tool-strategy.php',
	GG_DATA_PLUGIN_DIR . 'includes/ai/strategies/class-gg-data-tool-strategy-factory.php',

	// RAG classes.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-openai-client.php', // OpenAI API client.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-token-counter.php', // Token counting and usage tracking.
	GG_DATA_PLUGIN_DIR . 'includes/rag/class-gg-data-manifest-validator.php', // Manifest schema contract + normalizer.
	GG_DATA_PLUGIN_DIR . 'includes/rag/class-gg-data-coverage-gate.php', // Coverage gate (evidence sufficiency policy).
	GG_DATA_PLUGIN_DIR . 'includes/rag/class-gg-data-rag-service.php', // RAG service (embedding-agnostic).
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-rag-controller.php', // RAG REST API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-rag-journey-controller.php', // RAG journey continuity REST API.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-sse-handler.php', // SSE streaming for RAG progress.

	// Interaction tracking.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-interaction.php', // Interaction post type + recording functions.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-prompt.php', // Prompt post type + metadata.
	GG_DATA_PLUGIN_DIR . 'includes/class-gg-data-prompt-resolver.php', // Prompt resolver service.
	GG_DATA_PLUGIN_DIR . 'includes/api/class-gg-data-rest-prompts-controller.php', // Prompt REST API.

	// Assets.
	GG_DATA_PLUGIN_DIR . 'assets/assets.php',

	// Blocks.
	GG_DATA_PLUGIN_DIR . 'blocks/rag-assistant/rag-assistant.php',
);

foreach ( $gg_data_files as $file ) {
	if ( file_exists( $file ) ) {
		require_once $file;
	} else {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical: Log missing required files for production debugging
		error_log( 'Gregius PostgreSQL plugin error: Missing required file ' . $file );
	}
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'GG_Data_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GG_Data_Deactivator', 'deactivate' ) );

// Check version on every admin page load (catches updates while plugin is active).
add_action( 'admin_init', array( 'GG_Data_Activator', 'check_version' ) );

// Register custom cron schedules (must happen before any wp_schedule_event calls).
GG_Data_Cron_Manager::register_schedules();

/**
 * Initialize the Gregius PostgreSQL plugin and its admin components.
 */
function gg_data_init() {
	// Initialize the main plugin class.
	$gg_data = new GG_Data();
	$gg_data->init();

	// Initialize the schema manager.
	$gg_data_schema = new GG_Data_Schema_Manager();
	$gg_data_schema->init();

	// Initialize retry queue manager.
	$gg_data_retry_queue = new GG_Data_Retry_Queue();

	// Initialize connection health monitor.
	$gg_data_connection_health = new GG_Data_Connection_Health();

	// Initialize sync validator.
	$gg_data_sync_validator = new GG_Data_Sync_Validator();

	// Initialize search integration.
	$gg_data_search_integration = new GG_Data_Search_Integration();
	$gg_data_search_integration->init();

	// Initialize interaction tracking.
	$gg_data_interaction = new GG_Data_Interaction();
	$gg_data_interaction->init();

	// Initialize scheduled log retention handling.
	$gg_data_log_retention = new GG_Data_Log_Retention();
	$gg_data_log_retention->init();

	// Initialize prompt management.
	$gg_data_prompt = new GG_Data_Prompt();
	$gg_data_prompt->init();

	// Add connection health check cron hook.
	add_action( 'gg_data_check_connection_health', array( $gg_data_connection_health, 'perform_health_check' ) );

	// Add daily sync validation cron hook.
	add_action( 'gg_data_daily_validation', array( $gg_data_sync_validator, 'run_validation' ) );

	// Add vector index optimization cron hook.
	add_action( 'gg_data_check_vector_indexes', array( $gg_data_schema, 'scheduled_index_check' ) );

	// Initialize minimal admin interface (creates WordPress menu + React dashboard container).
	// All AJAX handlers are in their respective feature classes.
	// All REST API endpoints are in the REST API controllers.
	if ( is_admin() ) {
		$gg_data_admin = new GG_Data_Admin();
		$gg_data_admin->init();
	}

	// Initialize REST API.
	$gg_data_rest_api = new GG_Data_REST_API();
	$gg_data_rest_api->init();

	// Initialize SSE handler for RAG streaming (AJAX-based, not REST).
	new GG_Data_SSE_Handler();

	// Initialize RAG security hooks (access control for public endpoints).
	new GG_Data_RAG_Security_Hooks();
}
// Hook into plugins_loaded to ensure all dependencies are loaded.
add_action( 'plugins_loaded', 'gg_data_init' );

/**
 * Register AI providers
 *
 * @param array $providers Existing providers.
 * @return array Modified providers array.
 */
function gg_data_register_providers( $providers ) {
	// Register OpenAI provider (LLM + Embeddings).
	$providers['openai'] = new GG_Data_OpenAI_Provider();

	// Register DeepSeek provider (LLM only - 18x cheaper than GPT-4o).
	$providers['deepseek'] = new GG_Data_DeepSeek_Provider();

	// Register Anthropic provider (LLM only - Best instruction following).
	$providers['anthropic'] = new GG_Data_Anthropic_Provider();

	// Register Google Gemini provider (LLM + Embeddings - Free tier available).
	$providers['gemini'] = new GG_Data_Gemini_Provider();

	// Register Voyage AI provider (Embeddings + Rerank - RAG-optimized).
	$providers['voyage'] = new GG_Data_Voyage_Provider();

	// Register Cohere provider (Rerank only - Industry-leading reranking).
	$providers['cohere'] = new GG_Data_Cohere_Provider();

	// Register Internal provider (TF-IDF Embeddings - Free Tier).
	$providers['internal'] = new GG_Data_Internal_Provider();

	return $providers;
}
add_filter( 'gg_data_llm_providers', 'gg_data_register_providers' );

/**
 * Initialize WP-CLI commands if WP-CLI is available.
 *
 * @since 1.0.0
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GG_DATA_PLUGIN_DIR . 'includes/cli/class-gg-data-cli.php';
	GG_Data_CLI::register_commands();
}
