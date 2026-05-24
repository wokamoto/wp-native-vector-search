<?php
/**
 * Media_Describer unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Media_Describer;
use WP_Native_Vector_Search\OpenAI_Client;
use WP_Native_Vector_Search\Settings;

test_case(
	'Media_Describer queues image description generation',
	static function (): void {
		$settings  = new Settings();
		$describer = new Media_Describer( $settings, new OpenAI_Client( $settings ) );

		$describer->queue_description_generation( 22 );

		assert_same( 'wp_native_vector_search_describe_attachment', $GLOBALS['wpnvs_test_scheduled'][0]['hook'] );
		assert_same( array( 22 ), $GLOBALS['wpnvs_test_scheduled'][0]['args'] );
	}
);

test_case(
	'Media_Describer::describe_attachment stores generated metadata',
	static function (): void {
		$file = tempnam( sys_get_temp_dir(), 'wpnvs-media-' );
		file_put_contents( $file, 'small-image' );
		wpnvs_test_register_post(
			22,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
			)
		);
		$GLOBALS['wpnvs_test_files'][22] = $file;
		$GLOBALS['wpnvs_test_mime_types'][22] = 'image/png';
		$GLOBALS['wpnvs_test_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"output_text":"画像の説明"}',
		);
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'api_key' => 'key' ) ) );

		$settings  = new Settings();
		$describer = new Media_Describer( $settings, new OpenAI_Client( $settings ) );
		$result    = $describer->describe_attachment( 22 );

		unlink( $file );

		assert_same( 'described', $result['status'] );
		assert_same( '画像の説明', get_post_meta( 22, Media_Describer::META_DESCRIPTION, true ) );
		assert_same( 'gpt-4.1-mini', get_post_meta( 22, Media_Describer::META_MODEL, true ) );
	}
);

test_case(
	'Media_Describer::describe_attachment skips unsupported mime types',
	static function (): void {
		wpnvs_test_register_post(
			22,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			)
		);

		$settings  = new Settings();
		$describer = new Media_Describer( $settings, new OpenAI_Client( $settings ) );
		$result    = $describer->describe_attachment( 22 );

		assert_same( 'skipped', $result['status'] );
		assert_same( 'unsupported_mime_type', $result['reason'] );
	}
);

