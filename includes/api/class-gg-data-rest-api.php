<?php
/**
 * REST API Initialization for Gregius PostgreSQL Plugin
 *
 * Handles the initialization and registration of all REST API endpoints.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * REST API bootstrap and route registration.
 *
 * @since 1.0.0
 */
class GG_Data_REST_API {

	/**
	 * Initialize the REST API
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes
	 */
	public function register_routes() {
		// Settings controller.
		$settings_controller = new GG_Data_REST_Settings_Controller();
		$settings_controller->register_routes();

		// Connections controller .
		$connections_controller = new GG_Data_REST_Connections_Controller();
		$connections_controller->register_routes();

		// Schema controller .
		$schema_controller = new GG_Data_REST_Schema_Controller();
		$schema_controller->register_routes();

		// Sync controller .
		$sync_controller = new GG_Data_REST_Sync_Controller();
		$sync_controller->register_routes();

		// Vector Queue controller .
		$vector_queue_controller = new GG_Data_REST_Vector_Queue_Controller();
		$vector_queue_controller->register_routes();

		// Vocabulary controller .
		$vocabulary_controller = new GG_Data_REST_Vocabulary_Controller();
		$vocabulary_controller->register_routes();

		// Retry Queue controller .
		$retry_queue_controller = new GG_Data_REST_Retry_Queue_Controller();
		$retry_queue_controller->register_routes();

		// Connection Health controller .
		$connection_health_controller = new GG_Data_REST_Connection_Health_Controller();
		$connection_health_controller->register_routes();

		// Sync Validator controller .
		$sync_validator_controller = new GG_Data_REST_Sync_Validator_Controller();
		$sync_validator_controller->register_routes();

		// Search controller .
		$search_controller = new GG_Data_REST_Search_Controller();
		$search_controller->register_routes();

		// RAG controller.
		$rag_controller = new GG_Data_REST_RAG_Controller();
		$rag_controller->register_routes();

		// RAG journey continuity controller.
		$rag_journey_controller = new GG_Data_REST_RAG_Journey_Controller();
		$rag_journey_controller->register_routes();

		// Models controller.
		$models_controller = new GG_Data_REST_Models_Controller();
		$models_controller->register_routes();

		// Connection Models controller.
		$connection_models_controller = new GG_Data_REST_Connection_Models_Controller();
		$connection_models_controller->register_routes();

		// Logs controller.
		$logs_controller = new GG_Data_REST_Logs_Controller();
		$logs_controller->register_routes();

		// Prompts controller.
		$prompts_controller = new GG_Data_REST_Prompts_Controller();
		$prompts_controller->register_routes();

		// Interactions controller (for custom feedback endpoint).
		$interactions_controller = new GG_Data_REST_Interactions_Controller( 'gg_interaction' );
		$interactions_controller->register_routes();
	}
}
