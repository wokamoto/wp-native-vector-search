<?php
/**
 * Indexer unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Indexer;
use WP_Native_Vector_Search\Media_Describer;
use WP_Native_Vector_Search\OpenAI_Client;
use WP_Native_Vector_Search\Settings;

test_case(
	'Indexer::build_embedding_text normalizes and truncates post text',
	static function (): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'max_chars' => 12 ) ) );
		$post = wpnvs_test_register_post(
			1,
			array(
				'post_title'   => 'Title',
				'post_excerpt' => 'Excerpt',
				'post_content' => '<p>Content&nbsp;text</p>',
			)
		);

		$settings = new Settings();
		$indexer  = new Indexer( $settings, new WP_Native_Vector_Search\Database(), new OpenAI_Client( $settings ) );

		assert_same( 'Title Excerp', $indexer->build_embedding_text( $post ) );
	}
);

test_case(
	'Indexer::index_post stores a generated embedding',
	static function (): void {
		global $wpdb;

		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'api_key' => 'key', 'post_types' => array( 'post' ) ) ) );
		$GLOBALS['wpnvs_test_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"data":[{"embedding":[0.1,0.2]}]}',
		);
		wpnvs_test_register_post(
			1,
			array(
				'post_title'   => 'Title',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		$settings = new Settings();
		$indexer  = new Indexer( $settings, new WP_Native_Vector_Search\Database(), new OpenAI_Client( $settings ) );
		$result   = $indexer->index_post( 1 );

		assert_same( 'indexed', $result['status'] );
		assert_same( 2, $result['dimensions'] );
		assert_same( 1, $wpdb->insert_calls[0]['data']['post_id'] );
	}
);

test_case(
	'Indexer::index_media requires a generated media description',
	static function (): void {
		wpnvs_test_register_post( 22, array( 'post_type' => 'attachment' ) );

		$settings = new Settings();
		$indexer  = new Indexer( $settings, new WP_Native_Vector_Search\Database(), new OpenAI_Client( $settings ) );
		$result   = $indexer->index_media( 22 );

		assert_same( 'skipped', $result['status'] );
		assert_same( 'missing_description', $result['reason'] );
	}
);

test_case(
	'Indexer::queue_post_index schedules configured post types',
	static function (): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'post_types' => array( 'post' ) ) ) );
		wpnvs_test_register_post( 1, array( 'post_type' => 'post' ) );

		$settings = new Settings();
		$indexer  = new Indexer( $settings, new WP_Native_Vector_Search\Database(), new OpenAI_Client( $settings ) );
		$indexer->queue_post_index( 1 );

		assert_same( 'wp_native_vector_search_index_post', $GLOBALS['wpnvs_test_scheduled'][0]['hook'] );
		assert_same( array( 1 ), $GLOBALS['wpnvs_test_scheduled'][0]['args'] );
	}
);

