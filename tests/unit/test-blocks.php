<?php
/**
 * Blocks unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Blocks;

test_case(
	'Blocks registers its init hook and block metadata path',
	static function (): void {
		$blocks = new Blocks();
		$blocks->register();
		$blocks->register_blocks();

		assert_same( 'init', $GLOBALS['wpnvs_test_hooks'][0]['hook'] );
		assert_same( WP_NATIVE_VECTOR_SEARCH_DIR . 'blocks/search-box', $GLOBALS['wpnvs_test_blocks'][0] );
	}
);

