<?php
/**
 * Settings unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Settings;

test_case(
	'Settings::add_defaults stores defaults only when missing',
	static function (): void {
		Settings::add_defaults();

		$settings = get_option( Settings::OPTION_NAME );
		assert_same( 'text-embedding-3-small', $settings['embedding_model'] );
		assert_same( 'php', $settings['search_backend'] );
	}
);

test_case(
	'Settings::sanitize clamps numeric settings and filters post types',
	static function (): void {
		$settings = new Settings();
		$result   = $settings->sanitize(
			array(
				'api_key'           => " key \n",
				'embedding_model'   => 'unknown-model',
				'vision_model'      => '',
				'search_backend'    => 'invalid',
				'max_chars'         => 1,
				'min_score'         => 5,
				'max_keyword_boost' => 5,
				'keyword_boost'     => '1',
				'auto_index'        => '',
				'include_attachments' => '1',
				'replace_search'    => '1',
				'post_types'        => array( 'post', 'attachment', 'missing' ),
			)
		);

		assert_same( 'text-embedding-3-small', $result['embedding_model'] );
		assert_same( 'php', $result['search_backend'] );
		assert_same( 'gpt-4.1-mini', $result['vision_model'] );
		assert_same( 100, $result['max_chars'] );
		assert_same( 1.0, $result['min_score'] );
		assert_same( 1.0, $result['max_keyword_boost'] );
		assert_same( array( 'post' ), $result['post_types'] );
		assert_false( $result['auto_index'] );
		assert_true( $result['include_attachments'] );
		assert_true( $result['replace_search'] );
	}
);

test_case(
	'Settings::get_api_key prefers the configured option',
	static function (): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'api_key' => 'option-key' ) )
		);

		$settings = new Settings();

		assert_same( 'option-key', $settings->get_api_key() );
	}
);

