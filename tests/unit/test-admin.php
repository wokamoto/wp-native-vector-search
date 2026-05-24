<?php
/**
 * Admin unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Admin;
use WP_Native_Vector_Search\Database;
use WP_Native_Vector_Search\Media_Describer;
use WP_Native_Vector_Search\Settings;

test_case(
	'Admin registers settings and media hooks',
	static function (): void {
		$admin = new Admin( new Settings(), new Database() );
		$admin->register();

		assert_same( 'admin_menu', $GLOBALS['wpnvs_test_hooks'][0]['hook'] );
		assert_same( 'admin_init', $GLOBALS['wpnvs_test_hooks'][1]['hook'] );
		assert_same( 'manage_upload_columns', $GLOBALS['wpnvs_test_filters'][0]['hook'] );
	}
);

test_case(
	'Admin adds the media description column',
	static function (): void {
		$admin   = new Admin( new Settings(), new Database() );
		$columns = $admin->add_media_columns( array( 'title' => 'Title' ) );

		assert_same( 'Vector Description', $columns['wp_native_vector_search_image_description'] );
	}
);

test_case(
	'Admin adds attachment fields with generated description metadata',
	static function (): void {
		$post = wpnvs_test_register_post( 22, array( 'post_type' => 'attachment' ) );
		update_post_meta( 22, Media_Describer::META_DESCRIPTION, '説明' );
		update_post_meta( 22, Media_Describer::META_MODEL, 'vision-model' );
		update_post_meta( 22, Media_Describer::META_GENERATED_AT, '2026-05-25' );

		$admin  = new Admin( new Settings(), new Database() );
		$fields = $admin->add_attachment_fields( array(), $post );

		assert_contains_string( '説明', $fields['wp_native_vector_search_image_description']['html'] );
		assert_contains_string( 'vision-model', $fields['wp_native_vector_search_image_description']['html'] );
	}
);

