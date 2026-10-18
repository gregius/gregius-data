<?php
/**
 * Document Extractor for Gregius Data
 *
 * Extracts text from document attachments (PDF) using vision-capable models.
 * Routes per provider: Gemini native PDF input, OpenAI/Claude rasterize the
 * PDF to page images then run vision. Additive — does not touch the text-only
 * generation path.
 *
 * @package Gregius_Data
 * @subpackage Gregius_Data/includes/ai
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gregius_Data_Document_Extractor
 *
 * @since 1.0.0
 */
class Gregius_Data_Document_Extractor {

	/**
	 * Gemini REST base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * OpenAI REST base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const OPENAI_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * Anthropic REST base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const ANTHROPIC_BASE_URL = 'https://api.anthropic.com/v1';

	/**
	 * Anthropic API version header.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const ANTHROPIC_API_VERSION = '2023-06-01';

	/**
	 * Default extraction prompt.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const DEFAULT_PROMPT = 'Extract the full text content of this document, preserving its structure (headings, paragraphs, lists). Output only the extracted text.';

	/**
	 * Default maximum PDF file size in bytes (20MB — Gemini inline_data limit).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const DEFAULT_MAX_BYTES = 20971520;

	/**
	 * Default maximum pages to rasterize for image-based providers.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const DEFAULT_MAX_PAGES = 20;

	/**
	 * Extract text from a document (PDF) using a vision-capable model.
	 *
	 * @since 1.0.0
	 * @param string $file_path    Absolute path to the PDF file.
	 * @param array  $model_config Model config array (from Gregius_Data_Model_Registry)
	 *                             with 'provider', 'provider_model_id', and the
	 *                             API key (top-level or in 'config').
	 * @param array  $options      {
	 *     Optional. Extraction options.
	 *
	 *     @type string $prompt     Extraction prompt.
	 *     @type int    $max_pages  Max pages to rasterize (image providers only).
	 *     @type int    $max_bytes  Max file size in bytes.
	 *     @type int    $max_tokens Max output tokens (image providers only).
	 * }
	 * @return array|WP_Error { text: string, model: string } or error.
	 */
	public static function extract( string $file_path, array $model_config, array $options = array() ): array|WP_Error {
		// Document extraction (rasterize + vision) can exceed the default 30s limit
		// on long PDFs; raise it best-effort so it completes instead of fataling.
		$time_limit = (int) apply_filters( 'gregius_data_document_extraction_time_limit', 120 );
		if ( $time_limit > 0 ) {
			@set_time_limit( $time_limit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Best-effort time-limit raise for long-PDF rasterization; no-op where the host disallows it.
		}

		$provider = isset( $model_config['provider'] ) ? (string) $model_config['provider'] : '';
		$model    = isset( $model_config['provider_model_id'] ) ? (string) $model_config['provider_model_id'] : '';

		if ( '' === $model && isset( $model_config['model_key'] ) ) {
			$model = (string) $model_config['model_key'];
		}

		if ( '' === $provider || '' === $model ) {
			return new WP_Error(
				'gregius_data_document_invalid_model',
				__( 'Document model configuration is missing provider or model.', 'gregius-data' )
			);
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'gregius_data_document_file_missing',
				sprintf(
					/* translators: %s: file path */
					__( 'Document file not found or unreadable: %s', 'gregius-data' ),
					$file_path
				)
			);
		}

		$max_bytes = isset( $options['max_bytes'] ) ? (int) $options['max_bytes'] : self::DEFAULT_MAX_BYTES;
		$file_size = filesize( $file_path );

		if ( false === $file_size ) {
			return new WP_Error(
				'gregius_data_document_file_error',
				__( 'Could not determine document file size.', 'gregius-data' )
			);
		}

		if ( $file_size > $max_bytes ) {
			return new WP_Error(
				'gregius_data_document_too_large',
				sprintf(
					/* translators: 1: file size, 2: max bytes */
					__( 'Document is %1$d bytes, exceeding the %2$d byte limit.', 'gregius-data' ),
					$file_size,
					$max_bytes
				)
			);
		}

		$api_key = self::resolve_api_key( $model_config );

		if ( '' === $api_key ) {
			return new WP_Error(
				'gregius_data_missing_api_key',
				__( 'API key is missing for the document model.', 'gregius-data' )
			);
		}

		switch ( $provider ) {
			case 'gemini':
				return self::extract_gemini( $file_path, $model, $api_key, $options );
			case 'openai':
				return self::extract_openai( $file_path, $model, $api_key, $options );
			case 'anthropic':
				return self::extract_anthropic( $file_path, $model, $api_key, $options );
			default:
				return new WP_Error(
					'gregius_data_document_provider_unsupported',
					sprintf(
						/* translators: %s: provider ID */
						__( 'Provider "%s" does not support document extraction.', 'gregius-data' ),
						$provider
					)
				);
		}
	}

	/**
	 * Resolve the API key from a model config array.
	 *
	 * @since 1.0.0
	 * @param array $model_config Model config array.
	 * @return string API key (empty if not found).
	 */
	private static function resolve_api_key( array $model_config ): string {
		if ( ! empty( $model_config['api_key'] ) ) {
			return (string) $model_config['api_key'];
		}

		if ( ! empty( $model_config['config'] ) && is_array( $model_config['config'] ) && ! empty( $model_config['config']['api_key'] ) ) {
			return (string) $model_config['config']['api_key'];
		}

		// Fall back to the model registry — callers may have stripped the key to
		// keep it out of fatal stack traces.
		$model_id = isset( $model_config['provider_model_id'] ) ? (string) $model_config['provider_model_id'] : '';
		if ( '' === $model_id && isset( $model_config['model_key'] ) ) {
			$model_id = (string) $model_config['model_key'];
		}

		if ( '' !== $model_id ) {
			$registry = new Gregius_Data_Model_Registry();
			$model    = $registry->get_model( 'gregius-data', $model_id );

			if ( $model && ! is_wp_error( $model ) ) {
				if ( ! empty( $model['api_key'] ) ) {
					return (string) $model['api_key'];
				}
				if ( ! empty( $model['config'] ) && is_array( $model['config'] ) && ! empty( $model['config']['api_key'] ) ) {
					return (string) $model['config']['api_key'];
				}
			}
		}

		return '';
	}

	/**
	 * Extract text via Gemini native PDF input.
	 *
	 * @since 1.0.0
	 * @param string $file_path PDF file path.
	 * @param string $model     Model ID.
	 * @param string $api_key   API key.
	 * @param array  $options   Options.
	 * @return array|WP_Error
	 */
	private static function extract_gemini( string $file_path, string $model, string $api_key, array $options ): array|WP_Error {
		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a document for extraction.

		if ( false === $contents ) {
			return new WP_Error(
				'gregius_data_document_read_error',
				__( 'Could not read document file contents.', 'gregius-data' )
			);
		}

		$prompt = isset( $options['prompt'] ) && '' !== $options['prompt'] ? (string) $options['prompt'] : self::DEFAULT_PROMPT;

		$request_data = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $prompt ),
						array(
							'inline_data' => array(
								'mime_type' => 'application/pdf',
								'data'      => base64_encode( $contents ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by Gemini inline_data contract.
							),
						),
					),
				),
			),
		);

		$response = wp_safe_remote_post(
			sprintf( '%s/models/%s:generateContent', self::GEMINI_BASE_URL, rawurlencode( $model ) ),
			array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-Goog-Api-Key' => $api_key,
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 120,
			)
		);

		return self::parse_gemini_response( $response, $model );
	}

	/**
	 * Extract text via OpenAI (rasterize PDF to page images then vision).
	 *
	 * @since 1.0.0
	 * @param string $file_path PDF file path.
	 * @param string $model     Model ID.
	 * @param string $api_key   API key.
	 * @param array  $options   Options.
	 * @return array|WP_Error
	 */
	private static function extract_openai( string $file_path, string $model, string $api_key, array $options ): array|WP_Error {
		$images = self::rasterize_pdf( $file_path, $options );

		if ( is_wp_error( $images ) ) {
			return $images;
		}

		$prompt = isset( $options['prompt'] ) && '' !== $options['prompt'] ? (string) $options['prompt'] : self::DEFAULT_PROMPT;

		$content = array(
			array(
				'type' => 'text',
				'text' => $prompt,
			),
		);

		foreach ( $images as $image_base64 ) {
			$content[] = array(
				'type'      => 'image_url',
				'image_url' => array(
					'url' => 'data:image/jpeg;base64,' . $image_base64,
				),
			);
		}

		$request_data = array(
			'model'      => $model,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
			'max_tokens' => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 4096,
		);

		$response = wp_safe_remote_post(
			self::OPENAI_BASE_URL . '/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 120,
			)
		);

		return self::parse_openai_response( $response, $model );
	}

	/**
	 * Extract text via Anthropic (rasterize PDF to page images then vision).
	 *
	 * @since 1.0.0
	 * @param string $file_path PDF file path.
	 * @param string $model     Model ID.
	 * @param string $api_key   API key.
	 * @param array  $options   Options.
	 * @return array|WP_Error
	 */
	private static function extract_anthropic( string $file_path, string $model, string $api_key, array $options ): array|WP_Error {
		$images = self::rasterize_pdf( $file_path, $options );

		if ( is_wp_error( $images ) ) {
			return $images;
		}

		$prompt = isset( $options['prompt'] ) && '' !== $options['prompt'] ? (string) $options['prompt'] : self::DEFAULT_PROMPT;

		$content = array(
			array(
				'type' => 'text',
				'text' => $prompt,
			),
		);

		foreach ( $images as $image_base64 ) {
			$content[] = array(
				'type'   => 'image',
				'source' => array(
					'type'       => 'base64',
					'media_type' => 'image/jpeg',
					'data'       => $image_base64,
				),
			);
		}

		$request_data = array(
			'model'      => $model,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
			'max_tokens' => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 4096,
		);

		$response = wp_safe_remote_post(
			self::ANTHROPIC_BASE_URL . '/messages',
			array(
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => self::ANTHROPIC_API_VERSION,
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 120,
			)
		);

		return self::parse_anthropic_response( $response, $model );
	}

	/**
	 * Rasterize a PDF into base64-encoded PNG page images using Imagick.
	 *
	 * @since 1.0.0
	 * @param string $file_path PDF file path.
	 * @param array  $options   Options (max_pages).
	 * @return array|WP_Error Array of base64 PNG strings, or error.
	 */
	private static function rasterize_pdf( string $file_path, array $options ): array|WP_Error {
		if ( ! class_exists( 'Imagick' ) ) {
			return new WP_Error(
				'gregius_data_document_rasterizer_missing',
				__( 'PDF rasterization requires the Imagick PHP extension.', 'gregius-data' )
			);
		}

		$max_pages = isset( $options['max_pages'] ) ? (int) $options['max_pages'] : (int) apply_filters( 'gregius_data_document_max_pages', self::DEFAULT_MAX_PAGES );
		$max_pages = max( 1, $max_pages );

		try {
			$imagick = new Imagick();
			$imagick->setResolution( 150, 150 );
			$imagick->readImage( $file_path );

			$page_count = min( (int) $imagick->getNumberImages(), $max_pages );
			$images     = array();

			for ( $i = 0; $i < $page_count; $i++ ) {
				$imagick->setIteratorIndex( $i );
				$imagick->setImageFormat( 'jpeg' );
				$imagick->setImageCompressionQuality( 80 );
				$images[] = base64_encode( (string) $imagick->getImageBlob() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by vision API image contracts.
			}

			$imagick->clear();
			$imagick->destroy();

			if ( empty( $images ) ) {
				return new WP_Error(
					'gregius_data_document_rasterize_empty',
					__( 'PDF rasterization produced no readable pages (possible blank render).', 'gregius-data' )
				);
			}

			return $images;
		} catch ( Exception $e ) {
			return new WP_Error(
				'gregius_data_document_rasterize_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'PDF rasterization failed: %s', 'gregius-data' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Normalize extracted text (trim whitespace and strip a stray code fence).
	 *
	 * Some models wrap output in a markdown code fence; strip it so the extracted
	 * text is clean before entering the clean/chunk/embed pipeline.
	 *
	 * @since 1.0.0
	 * @param string $text Raw extracted text.
	 * @return string Normalized text.
	 */
	private static function normalize_extracted_text( string $text ): string {
		$text = trim( $text );
		$text = preg_replace( '/^```[a-zA-Z]*\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );

		return trim( $text );
	}

	/**
	 * Detect a model "refusal" — an apology/error instead of extracted document text.
	 *
	 * @since 1.0.0
	 * @param string $text Normalized extracted text.
	 * @return bool True when the output looks like a non-extraction.
	 */
	private static function looks_like_refusal( string $text ): bool {
		$lower = strtolower( $text );

		$patterns = array(
			'unable to extract',
			'cannot extract',
			"can't extract",
			'non-text content',
			'no text content',
			'does not contain text',
			'not contain text',
			'no readable text',
			"i'm sorry",
			'i cannot',
			"i can't",
			'unable to read',
			'cannot read',
			'no text was',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $lower, $pattern ) ) {
				return true;
			}
		}

		// A real document extraction has meaningful alphanumeric density; a short
		// apology/error string does not.
		$alnum = preg_replace( '/[^a-z0-9]/i', '', $lower );

		return strlen( $alnum ) < 20;
	}

	/**
	 * Normalize extracted text and reject refusals as a WP_Error.
	 *
	 * @since 1.0.0
	 * @param string $raw   Raw model output.
	 * @param string $model Model ID.
	 * @return array|WP_Error { text: string, model: string } or error.
	 */
	private static function extracted_text_or_error( string $raw, string $model ): array|WP_Error {
		$text = self::normalize_extracted_text( $raw );

		if ( self::looks_like_refusal( $text ) ) {
			return new WP_Error(
				'gregius_data_document_no_text',
				__( 'The model did not extract usable text from this document.', 'gregius-data' )
			);
		}

		return array(
			'text'  => $text,
			'model' => $model,
		);
	}

	/**
	 * Parse a Gemini generateContent response.
	 *
	 * @since 1.0.0
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $model    Model ID.
	 * @return array|WP_Error
	 */
	private static function parse_gemini_response( $response, string $model ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gregius_data_document_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to Gemini: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'gregius-data' );

			return new WP_Error(
				self::is_model_unavailable( $message ) ? 'gregius_data_document_model_unavailable' : 'gregius_data_document_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error message */
					__( 'Gemini document extraction failed (HTTP %1$d): %2$s', 'gregius-data' ),
					$status_code,
					$message
				)
			);
		}

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error(
				'gregius_data_document_invalid_response',
				__( 'Invalid response structure from Gemini document extraction.', 'gregius-data' )
			);
		}

		return self::extracted_text_or_error( (string) $data['candidates'][0]['content']['parts'][0]['text'], $model );
	}

	/**
	 * Parse an OpenAI chat completion response.
	 *
	 * @since 1.0.0
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $model    Model ID.
	 * @return array|WP_Error
	 */
	private static function parse_openai_response( $response, string $model ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gregius_data_document_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to OpenAI: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'gregius-data' );

			return new WP_Error(
				self::is_model_unavailable( $message ) ? 'gregius_data_document_model_unavailable' : 'gregius_data_document_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error message */
					__( 'OpenAI document extraction failed (HTTP %1$d): %2$s', 'gregius-data' ),
					$status_code,
					$message
				)
			);
		}

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'gregius_data_document_invalid_response',
				__( 'Invalid response structure from OpenAI document extraction.', 'gregius-data' )
			);
		}

		return self::extracted_text_or_error( (string) $data['choices'][0]['message']['content'], $model );
	}

	/**
	 * Parse an Anthropic messages response.
	 *
	 * @since 1.0.0
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $model    Model ID.
	 * @return array|WP_Error
	 */
	private static function parse_anthropic_response( $response, string $model ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gregius_data_document_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to Anthropic: %s', 'gregius-data' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'gregius-data' );

			return new WP_Error(
				self::is_model_unavailable( $message ) ? 'gregius_data_document_model_unavailable' : 'gregius_data_document_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error message */
					__( 'Anthropic document extraction failed (HTTP %1$d): %2$s', 'gregius-data' ),
					$status_code,
					$message
				)
			);
		}

		if ( ! isset( $data['content'][0]['text'] ) ) {
			return new WP_Error(
				'gregius_data_document_invalid_response',
				__( 'Invalid response structure from Anthropic document extraction.', 'gregius-data' )
			);
		}

		return self::extracted_text_or_error( (string) $data['content'][0]['text'], $model );
	}

	/**
	 * Whether a provider error message indicates the model is gone.
	 *
	 * @since 1.0.0
	 * @param string $message Provider error message.
	 * @return bool True when the model is unavailable / deprecated / retired.
	 */
	private static function is_model_unavailable( string $message ): bool {
		$message = strtolower( $message );
		$needles = array(
			'no longer available',
			'no longer supported',
			'has been retired',
			'has been deprecated',
			'is deprecated',
			'model not found',
			'model_not_found',
			'does not exist',
			'not a valid model',
			'invalid model',
			'unknown model',
		);

		foreach ( $needles as $needle ) {
			if ( false !== strpos( $message, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
