<?php
/**
 * Model discovery service.
 *
 * Resolves the live availability of a provider's models by asking the
 * provider itself (via its optional list_remote_models() method, which hits
 * the provider's GET /models endpoint). Results are cached briefly so a
 * landing-page check is cheap and non-blocking.
 *
 * Lifecycle rules: a model present in the live list is `active`; a model
 * absent from it is `retired`. Discovery failures NEVER retire a model —
 * they resolve to `unknown`, so a transient outage cannot blacklist a model.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gregius_Data_Model_Discovery' ) ) {

	/**
	 * Live model discovery with transient caching.
	 */
	class Gregius_Data_Model_Discovery {

		/**
		 * Transient key prefix.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		private const CACHE_PREFIX = 'gregius_data_model_discovery_';

		/**
		 * Cache lifetime, in seconds.
		 *
		 * Short TTL: the Models page also refreshes on load, so this only
		 * exists as an offline / rate-limit fallback.
		 *
		 * @since 1.0.0
		 * @var int
		 */
		private const CACHE_TTL = 300;

		/**
		 * Resolves a provider id to a provider instance.
		 *
		 * Injectable so the discovery can be unit-tested without the registry.
		 *
		 * @since 1.0.0
		 * @var callable
		 */
		private $provider_resolver;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @param callable|null $provider_resolver Optional. Resolves a provider id to an instance.
		 */
		public function __construct( ?callable $provider_resolver = null ) {
			$this->provider_resolver = $provider_resolver ?? static function ( $provider_id ) {
				return Gregius_Data_LLM_Registry::get_provider( $provider_id );
			};
		}

		/**
		 * Fetch the live model ids for a provider.
		 *
		 * @since 1.0.0
		 * @param string $provider_id Provider id (e.g. 'gemini').
		 * @param string $api_key     Provider API key.
		 * @param bool   $force       Optional. Bypass the cache. Default false.
		 * @return array|WP_Error Array of model ids, or WP_Error on failure.
		 */
		public function fetch_live_models( string $provider_id, string $api_key, bool $force = false ): array|WP_Error {
			$provider = call_user_func( $this->provider_resolver, $provider_id );

			if ( is_wp_error( $provider ) || ! method_exists( $provider, 'list_remote_models' ) ) {
				return new WP_Error(
					'gregius_data_discovery_unsupported',
					/* translators: %s: provider id */
					sprintf( __( 'Provider "%s" does not support live model discovery.', 'gregius-data' ), $provider_id )
				);
			}

			$cache_key = self::CACHE_PREFIX . md5( $provider_id . '|' . $api_key );

			if ( ! $force ) {
				$cached = get_transient( $cache_key );
				if ( is_array( $cached ) ) {
					return $cached;
				}
			}

			$result = $provider->list_remote_models( $api_key );

			if ( is_wp_error( $result ) ) {
				// Do not cache failures.
				return $result;
			}

			set_transient( $cache_key, $result, self::CACHE_TTL );

			return $result;
		}

		/**
		 * Resolve the lifecycle status for a single model.
		 *
		 * @since 1.0.0
		 * @param string $provider_id Provider id.
		 * @param string $api_key     Provider API key.
		 * @param string $model_id    Provider model id.
		 * @return string 'active' | 'retired' | 'unknown'.
		 */
		public function resolve_lifecycle( string $provider_id, string $api_key, string $model_id ): string {
			$live = $this->fetch_live_models( $provider_id, $api_key );

			if ( is_wp_error( $live ) ) {
				return 'unknown';
			}

			if ( in_array( $model_id, $live, true ) ) {
				return 'active';
			}

			// Absent from the list. Only conclude "retired" when the provider's
			// list actually covers this model's category — otherwise the list
			// simply omits that category (e.g. Cohere's /v2/models omits rerank).
			$category = $this->model_category( $provider_id, $model_id );

			if ( null === $category || ! $this->list_covers_category( $provider_id, $category, $live ) ) {
				return 'unknown';
			}

			return 'retired';
		}

		/**
		 * Look up a model's category from the catalog.
		 *
		 * @since 1.0.0
		 * @param string $provider_id Provider id.
		 * @param string $model_id    Provider model id.
		 * @return string|null 'llm' | 'embedding' | 'rerank', or null if unknown.
		 */
		private function model_category( string $provider_id, string $model_id ): ?string {
			$models = Gregius_Data_Model_Catalog::models( $provider_id );

			return isset( $models[ $model_id ]['kind'] ) ? $models[ $model_id ]['kind'] : null;
		}

		/**
		 * Whether the live list contains a catalog model of the given category.
		 *
		 * @since 1.0.0
		 * @param string $provider_id Provider id.
		 * @param string $category    Catalog category.
		 * @param array  $live        Live model ids.
		 * @return bool
		 */
		private function list_covers_category( string $provider_id, string $category, array $live ): bool {
			foreach ( Gregius_Data_Model_Catalog::models( $provider_id ) as $id => $model ) {
				if ( isset( $model['kind'] ) && $model['kind'] === $category && in_array( $id, $live, true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Clear the cached live list for a provider + key.
		 *
		 * @since 1.0.0
		 * @param string $provider_id Provider id.
		 * @param string $api_key     Provider API key.
		 * @return void
		 */
		public function flush( string $provider_id, string $api_key ): void {
			delete_transient( self::CACHE_PREFIX . md5( $provider_id . '|' . $api_key ) );
		}
	}
}
