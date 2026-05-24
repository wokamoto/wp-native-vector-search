<?php
/**
 * OpenAI_Client unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\OpenAI_Client;
use WP_Native_Vector_Search\Settings;

test_case(
	'OpenAI_Client::create_embedding posts to the embeddings API and returns floats',
	static function (): void {
		$GLOBALS['wpnvs_test_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"data":[{"embedding":[1,"0.5"]}]}',
		);
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'api_key' => 'test-key' ) ) );

		$settings = new Settings();
		$client   = new OpenAI_Client( $settings );
		$result   = $client->create_embedding( 'hello', 'text-embedding-3-small' );

		assert_same( array( 1.0, 0.5 ), $result );
		assert_contains_string( '/v1/embeddings', $GLOBALS['wpnvs_test_http_requests'][0]['url'] );
		assert_same( 'Bearer test-key', $GLOBALS['wpnvs_test_http_requests'][0]['args']['headers']['Authorization'] );
	}
);

test_case(
	'OpenAI_Client::create_embedding returns WP_Error when API key is missing',
	static function (): void {
		$client = new OpenAI_Client( new Settings() );
		$result = $client->create_embedding( 'hello' );

		assert_true( $result instanceof WP_Error );
		assert_same( 'wp_native_vector_search_missing_api_key', $result->get_error_code() );
	}
);

test_case(
	'OpenAI_Client::create_image_description extracts output_text',
	static function (): void {
		$file = tempnam( sys_get_temp_dir(), 'wpnvs-img-' );
		file_put_contents( $file, 'image-bytes' );
		$GLOBALS['wpnvs_test_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"output_text":"説明文"}',
		);
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'api_key' => 'test-key' ) ) );

		$client = new OpenAI_Client( new Settings() );
		$result = $client->create_image_description( $file, 'image/png', 'gpt-4.1-mini' );

		unlink( $file );

		assert_same( '説明文', $result );
		assert_contains_string( '/v1/responses', $GLOBALS['wpnvs_test_http_requests'][0]['url'] );
	}
);

