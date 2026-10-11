<?php
/**
 * Curated model catalog.
 *
 * The single source of truth for the models this plugin supports, across every
 * provider and every model type (LLM/vision, embedding, rerank). Providers read
 * their supported lists from here rather than hardcoding them.
 *
 * Why curated: model *capability* metadata (vision support, embedding
 * dimension, rerank context) is not exposed by the providers' model-list
 * endpoints (OpenAI's GET /v1/models returns only ids), so it must live here.
 * Model *lifecycle* (active / retired) is NOT stored here — it is
 * derived live from each provider's GET /models endpoint.
 *
 * @package Gregius_Data
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GG_Data_Model_Catalog' ) ) {

	/**
	 * Curated, per-provider model definitions.
	 */
	class GG_Data_Model_Catalog {

		/**
		 * Cached catalog array.
		 *
		 * @since 1.0.0
		 * @var array|null
		 */
		private static $catalog = null;

		/**
		 * Full catalog, keyed by provider id, then model id.
		 *
		 * Each model entry accepts:
		 *   name             (string) Display name.
		 *   kind             (string) 'llm' | 'embedding' | 'rerank'.
		 *   vision           (bool)   LLM only: accepts image / PDF input.
		 *   dimensions       (int)    Embedding only: vector dimensions.
		 *   max_input_tokens (int)    Embedding only: max input tokens.
		 *   max_context      (int)    Rerank only: max context tokens.
		 *   description      (string) Optional.
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public static function all(): array {
			if ( null === self::$catalog ) {
				self::$catalog = array(
					'gemini'    => array(
						'gemini-3.8-flash'      => array(
							'name'   => 'Gemini 3.8 Flash',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gemini-3.5-flash-lite' => array(
							'name'   => 'Gemini 3.5 Flash-Lite',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gemini-embedding-2'    => array(
							'name'             => 'Gemini Embedding 2',
							'kind'             => 'embedding',
							'dimensions'       => 3072,
							'max_input_tokens' => 8192,
							'description'      => 'Multimodal embeddings with a free tier. Text, image, video, audio and PDF input (the plugin uses text only).',
						),
					),
					'openai'    => array(
						'gpt-4o-mini'            => array(
							'name'   => 'GPT-4o Mini',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gpt-6-luna'             => array(
							'name'   => 'GPT-6 Luna',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gpt-4o'                 => array(
							'name'   => 'GPT-4o',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gpt-6.1-sol'            => array(
							'name'   => 'GPT-6.1 Sol',
							'kind'   => 'llm',
							'vision' => true,
						),
						'text-embedding-3-small' => array(
							'name'             => 'Text Embedding 3 Small',
							'kind'             => 'embedding',
							'dimensions'       => 1536,
							'max_input_tokens' => 8191,
						),
						'text-embedding-3-large' => array(
							'name'             => 'Text Embedding 3 Large',
							'kind'             => 'embedding',
							'dimensions'       => 3072,
							'max_input_tokens' => 8191,
						),
					),
					'anthropic' => array(
						'claude-opus-5-5'         => array(
							'name'   => 'Claude Opus 5.5',
							'kind'   => 'llm',
							'vision' => true,
						),
						'claude-sonnet-5-5'       => array(
							'name'   => 'Claude Sonnet 5.5',
							'kind'   => 'llm',
							'vision' => true,
						),
						'claude-haiku-5-5'        => array(
							'name'   => 'Claude Haiku 5.5',
							'kind'   => 'llm',
							'vision' => true,
						),
						'claude-opus-4-20250514'   => array(
							'name'   => 'Claude Opus 4.5',
							'kind'   => 'llm',
							'vision' => true,
						),
						'claude-sonnet-4-20250514' => array(
							'name'   => 'Claude Sonnet 4.5',
							'kind'   => 'llm',
							'vision' => true,
						),
						'claude-haiku-4-20250514'  => array(
							'name'   => 'Claude Haiku 4.5',
							'kind'   => 'llm',
							'vision' => true,
						),
					),
					'cohere'    => array(
						'embed-v4.0'       => array(
							'name'             => 'Embed v4.0',
							'kind'             => 'embedding',
							'dimensions'       => 1536,
							'max_input_tokens' => 128000,
							'description'      => 'Latest multilingual embeddings (100+ languages). Supports MRL dimensions: 256, 512, 1024, 1536.',
						),
						'rerank-v4.0-fast' => array(
							'name'        => 'Rerank v4.0 Fast',
							'kind'        => 'rerank',
							'max_context' => 4096,
							'description' => 'Fast multilingual reranking.',
						),
					),
					'voyage'    => array(
						'voyage-4'      => array(
							'name'             => 'Voyage 4',
							'kind'             => 'embedding',
							'dimensions'       => 1024,
							'max_input_tokens' => 32000,
							'description'      => 'General-purpose multilingual embeddings. Shared embedding space across the Voyage 4 series.',
						),
						'voyage-4-lite' => array(
							'name'             => 'Voyage 4 Lite',
							'kind'             => 'embedding',
							'dimensions'       => 1024,
							'max_input_tokens' => 32000,
							'description'      => 'Lowest-cost Voyage 4 embedding.',
						),
						'rerank-3'      => array(
							'name'        => 'Rerank 3',
							'kind'        => 'rerank',
							'max_context' => 32000,
							'description' => 'Multilingual reranking.',
						),
						'rerank-3-lite' => array(
							'name'        => 'Rerank 3 Lite',
							'kind'        => 'rerank',
							'max_context' => 32000,
							'description' => 'Budget reranking.',
						),
					),
					'deepseek'  => array(
						'deepseek-flash'  => array(
							'name'   => 'DeepSeek Flash',
							'kind'   => 'llm',
							'vision' => true,
						),
						'deepseek-v4-pro' => array(
							'name'   => 'DeepSeek V4 Pro',
							'kind'   => 'llm',
							'vision' => false,
						),
					),
					'internal'  => array(
						'hashingtf-murmur3-1024' => array(
							'name'             => 'Hashing TF Murmur3 1024D',
							'kind'             => 'embedding',
							'dimensions'       => 1024,
							'max_input_tokens' => 0,
							'description'      => 'Free stateless local embeddings via feature hashing. No vocabulary required.',
						),
					),
				);
			}

			return self::$catalog;
		}

		/**
		 * All catalog entries for a provider.
		 *
		 * @since 1.0.0
		 * @param string $provider Provider id.
		 * @return array Model definitions keyed by model id.
		 */
		public static function models( string $provider ): array {
			$all = self::all();

			return isset( $all[ $provider ] ) ? $all[ $provider ] : array();
		}

		/**
		 * LLM models as a model-id => display-name map.
		 *
		 * @since 1.0.0
		 * @param string $provider Provider id.
		 * @return array
		 */
		public static function llm_models( string $provider ): array {
			$models = array();

			foreach ( self::models( $provider ) as $id => $model ) {
				if ( isset( $model['kind'] ) && 'llm' === $model['kind'] ) {
					$models[ $id ] = isset( $model['name'] ) ? $model['name'] : $id;
				}
			}

			return $models;
		}

		/**
		 * Vision-capable LLM model ids.
		 *
		 * @since 1.0.0
		 * @param string $provider Provider id.
		 * @return array
		 */
		public static function vision_models( string $provider ): array {
			$models = array();

			foreach ( self::models( $provider ) as $id => $model ) {
				if ( isset( $model['kind'] ) && 'llm' === $model['kind'] && ! empty( $model['vision'] ) ) {
					$models[] = $id;
				}
			}

			return $models;
		}

		/**
		 * Embedding models as a model-id => config map.
		 *
		 * @since 1.0.0
		 * @param string $provider Provider id.
		 * @return array
		 */
		public static function embedding_models( string $provider ): array {
			$models = array();

			foreach ( self::models( $provider ) as $id => $model ) {
				if ( isset( $model['kind'] ) && 'embedding' === $model['kind'] ) {
					$models[ $id ] = array(
						'name'             => isset( $model['name'] ) ? $model['name'] : $id,
						'dimensions'       => isset( $model['dimensions'] ) ? $model['dimensions'] : 0,
						'max_input_tokens' => isset( $model['max_input_tokens'] ) ? $model['max_input_tokens'] : 0,
						'description'      => isset( $model['description'] ) ? $model['description'] : '',
					);
				}
			}

			return $models;
		}

		/**
		 * Rerank models as a model-id => config map.
		 *
		 * @since 1.0.0
		 * @param string $provider Provider id.
		 * @return array
		 */
		public static function rerank_models( string $provider ): array {
			$models = array();

			foreach ( self::models( $provider ) as $id => $model ) {
				if ( isset( $model['kind'] ) && 'rerank' === $model['kind'] ) {
					$models[ $id ] = array(
						'name'        => isset( $model['name'] ) ? $model['name'] : $id,
						'max_context' => isset( $model['max_context'] ) ? $model['max_context'] : 0,
						'description' => isset( $model['description'] ) ? $model['description'] : '',
					);
				}
			}

			return $models;
		}

		/**
		 * Context-window limits per LLM model id, for prompt budgeting.
		 *
		 * Per-provider defaults, overridable per catalog entry via
		 * `context_window`.
		 *
		 * @since 1.0.0
		 * @return array Model-id => token limit.
		 */
		public static function context_limits(): array {
			$defaults = array(
				'openai'    => 128000,
				'anthropic' => 200000,
				'gemini'    => 1048576,
				'deepseek'  => 64000,
			);

			return self::llm_limits( $defaults, 'context_window' );
		}

		/**
		 * Native max output tokens per LLM model id.
		 *
		 * Per-provider defaults, overridable per catalog entry via
		 * `max_tokens`.
		 *
		 * @since 1.0.0
		 * @return array Model-id => token limit.
		 */
		public static function max_tokens_map(): array {
			$defaults = array(
				'openai'    => 16384,
				'anthropic' => 8192,
				'gemini'    => 8192,
				'deepseek'  => 8192,
			);

			return self::llm_limits( $defaults, 'max_tokens' );
		}

		/**
		 * Build a flat model-id => limit map for LLM entries.
		 *
		 * @since 1.0.0
		 * @param array  $provider_defaults Provider id => default limit.
		 * @param string $field             Catalog field to prefer.
		 * @return array
		 */
		private static function llm_limits( array $provider_defaults, string $field ): array {
			$limits = array();

			foreach ( self::all() as $provider => $models ) {
				foreach ( $models as $id => $model ) {
					if ( ! isset( $model['kind'] ) || 'llm' !== $model['kind'] ) {
						continue;
					}

					if ( isset( $model[ $field ] ) ) {
						$limits[ $id ] = (int) $model[ $field ];
					} elseif ( isset( $provider_defaults[ $provider ] ) ) {
						$limits[ $id ] = (int) $provider_defaults[ $provider ];
					}
				}
			}

			return $limits;
		}
	}
}
