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

if ( ! class_exists( 'Gregius_Data_Model_Catalog' ) ) {

	/**
	 * Curated, per-provider model definitions.
	 */
	class Gregius_Data_Model_Catalog {

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
		 *   kind             (string) 'llm' | 'embedding' | 'rerank' | 'evaluator'.
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
						'gemini-2.5-flash'      => array(
							'name'   => 'Gemini 2.5 Flash',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gemini-2.5-pro'        => array(
							'name'   => 'Gemini 2.5 Pro',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gemini-2.0-flash-lite' => array(
							'name'   => 'Gemini 2.0 Flash-Lite',
							'kind'   => 'llm',
							'vision' => true,
						),
						'gemini-embedding-001'  => array(
							'name'             => 'Gemini Embedding 001',
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
						'embed-v5.0-pro'   => array(
							'name'             => 'Embed v5.0 Pro',
							'kind'             => 'embedding',
							'dimensions'       => 2048,
							'max_input_tokens' => 128000,
							'description'      => 'High-accuracy embeddings (256-2048 dimensions).',
						),
						'embed-v5.0-fast'  => array(
							'name'             => 'Embed v5.0 Fast',
							'kind'             => 'embedding',
							'dimensions'       => 2048,
							'max_input_tokens' => 128000,
							'description'      => 'Fast embeddings (256-2048 dimensions).',
						),
						'rerank-v4.0-pro'  => array(
							'name'        => 'Rerank v4.0 Pro',
							'kind'        => 'rerank',
							'max_context' => 32000,
							'description' => 'High-accuracy multilingual reranking.',
						),
						'rerank-v4.0-fast' => array(
							'name'        => 'Rerank v4.0 Fast',
							'kind'        => 'rerank',
							'max_context' => 32000,
							'description' => 'Fast multilingual reranking.',
						),
					),
					'deepseek'  => array(
						'deepseek-chat'     => array(
							'name'   => 'DeepSeek Chat',
							'kind'   => 'llm',
							'vision' => false,
						),
						'deepseek-reasoner' => array(
							'name'   => 'DeepSeek Reasoner',
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
					'typesafe'  => array(
						'jev-1.13.0' => array(
							'name'        => 'Jev 1.13.0',
							'kind'        => 'evaluator',
							'description' => 'TypeSafe System One decision model for fast, calibrated judgments (e.g., retrieval sufficiency).',
						),
						'jev-latest' => array(
							'name'        => 'Jev (latest)',
							'kind'        => 'evaluator',
							'description' => 'TypeSafe System One decision model alias — resolves to the current stable release.',
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
		 * Evaluator (decision) models as a model-id => config map.
		 *
		 * @since 1.0.0
		 * @param string $provider Provider id.
		 * @return array
		 */
		public static function evaluator_models( string $provider ): array {
			$models = array();

			foreach ( self::models( $provider ) as $id => $model ) {
				if ( isset( $model['kind'] ) && 'evaluator' === $model['kind'] ) {
					$models[ $id ] = array(
						'name'        => isset( $model['name'] ) ? $model['name'] : $id,
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
		 * Flat embedding model-id => dimensions map across all providers.
		 *
		 * @since 1.0.0
		 * @return array Model-id => dimensions.
		 */
		public static function embedding_dimensions(): array {
			$dimensions = array();

			foreach ( self::all() as $models ) {
				foreach ( $models as $id => $model ) {
					if ( isset( $model['kind'] ) && 'embedding' === $model['kind'] && isset( $model['dimensions'] ) ) {
						$dimensions[ $id ] = (int) $model['dimensions'];
					}
				}
			}

			return $dimensions;
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
