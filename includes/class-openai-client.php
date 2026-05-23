<?php
/**
 * OpenAI embeddings client.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls the OpenAI embeddings API.
 */
final class OpenAI_Client {
	private const EMBEDDINGS_ENDPOINT = 'https://api.openai.com/v1/embeddings';
	private const RESPONSES_ENDPOINT  = 'https://api.openai.com/v1/responses';

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Generate an embedding for text.
	 *
	 * @param string      $text Input text.
	 * @param string|null $model Optional model override.
	 * @return array<int, float>|WP_Error
	 */
	public function create_embedding( string $text, ?string $model = null ) {
		$api_key = $this->settings->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'wp_native_vector_search_missing_api_key', __( 'OpenAI API key is not configured.', 'wp-native-vector-search' ) );
		}

		$model = $model ?: (string) $this->settings->get( 'embedding_model' );

		$response = wp_remote_post(
			self::EMBEDDINGS_ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => $model,
						'input' => $text,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: __( 'OpenAI embeddings request failed.', 'wp-native-vector-search' );

			return new WP_Error( 'wp_native_vector_search_openai_error', $message, array( 'status' => $status_code ) );
		}

		if ( ! is_array( $body ) || ! isset( $body['data'][0]['embedding'] ) || ! is_array( $body['data'][0]['embedding'] ) ) {
			return new WP_Error( 'wp_native_vector_search_invalid_openai_response', __( 'OpenAI embeddings response did not include an embedding.', 'wp-native-vector-search' ) );
		}

		return array_map( 'floatval', $body['data'][0]['embedding'] );
	}

	/**
	 * Generate a natural-language image description using a vision-capable model.
	 *
	 * @param string      $image_path Local image file path.
	 * @param string      $mime_type Image MIME type.
	 * @param string|null $model Optional model override.
	 * @return string|WP_Error
	 */
	public function create_image_description( string $image_path, string $mime_type, ?string $model = null ) {
		$api_key = $this->settings->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'wp_native_vector_search_missing_api_key', __( 'OpenAI API key is not configured.', 'wp-native-vector-search' ) );
		}

		if ( ! is_readable( $image_path ) ) {
			return new WP_Error( 'wp_native_vector_search_image_not_readable', __( 'The image file is not readable.', 'wp-native-vector-search' ) );
		}

		$image_bytes = file_get_contents( $image_path );
		if ( false === $image_bytes ) {
			return new WP_Error( 'wp_native_vector_search_image_read_failed', __( 'Could not read the image file.', 'wp-native-vector-search' ) );
		}

		$model      = $model ?: (string) $this->settings->get( 'vision_model' );
		$data_url   = 'data:' . $mime_type . ';base64,' . base64_encode( $image_bytes );
		$prompt     = implode(
			"\n",
			array(
				'この画像を WordPress メディア検索用に日本語で説明してください。',
				'次の観点を含めてください: 何が写っているか、用途、読み取れる文字情報、色、構図、雰囲気、関連しそうな検索語。',
				'事実として画像から判断できる内容を優先し、推測は控えめにしてください。',
				'検索インデックス用なので、見出しや箇条書きではなく 300 文字程度の自然な説明文にしてください。',
			)
		);
		$response   = wp_remote_post(
			self::RESPONSES_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'             => $model,
						'store'             => false,
						'max_output_tokens' => 500,
						'input'             => array(
							array(
								'role'    => 'user',
								'content' => array(
									array(
										'type' => 'input_text',
										'text' => $prompt,
									),
									array(
										'type'      => 'input_image',
										'image_url' => $data_url,
										'detail'    => 'low',
									),
								),
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: __( 'OpenAI image description request failed.', 'wp-native-vector-search' );

			return new WP_Error( 'wp_native_vector_search_openai_vision_error', $message, array( 'status' => $status_code ) );
		}

		$description = $this->extract_response_text( $body );
		if ( '' === $description ) {
			return new WP_Error( 'wp_native_vector_search_invalid_vision_response', __( 'OpenAI response did not include description text.', 'wp-native-vector-search' ) );
		}

		return $description;
	}

	/**
	 * Extract text output from a Responses API payload.
	 *
	 * @param mixed $body Decoded response body.
	 */
	private function extract_response_text( $body ): string {
		if ( is_array( $body ) && isset( $body['output_text'] ) && is_string( $body['output_text'] ) ) {
			return trim( $body['output_text'] );
		}

		if ( ! is_array( $body ) || ! isset( $body['output'] ) || ! is_array( $body['output'] ) ) {
			return '';
		}

		$text = '';
		foreach ( $body['output'] as $output ) {
			if ( ! is_array( $output ) || ! isset( $output['content'] ) || ! is_array( $output['content'] ) ) {
				continue;
			}

			foreach ( $output['content'] as $content ) {
				if ( is_array( $content ) && isset( $content['text'] ) && is_string( $content['text'] ) ) {
					$text .= "\n" . $content['text'];
				}
			}
		}

		return trim( $text );
	}
}
