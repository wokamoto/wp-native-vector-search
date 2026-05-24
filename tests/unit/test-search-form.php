<?php
/**
 * Search_Form unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Search_Form;
use WP_Native_Vector_Search\Settings;

test_case(
	'Search_Form registers frontend hooks only when replacement is enabled',
	static function (): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'replace_search' => true ) ) );

		$form = new Search_Form( new Settings() );
		$form->register();

		assert_same( 'wp_enqueue_scripts', $GLOBALS['wpnvs_test_hooks'][0]['hook'] );
		assert_same( 'template_redirect', $GLOBALS['wpnvs_test_hooks'][1]['hook'] );
	}
);

test_case(
	'Search_Form replaces core search block markup',
	static function (): void {
		$form   = new Search_Form( new Settings() );
		$result = $form->replace_core_search_block( '<form>core</form>', array( 'blockName' => 'core/search' ) );

		assert_contains_string( 'wp-native-vector-search-box', $result );
		assert_contains_string( 'type="search"', $result );
	}
);

test_case(
	'Search_Form leaves non-search blocks unchanged',
	static function (): void {
		$form = new Search_Form( new Settings() );

		assert_same( '<p>content</p>', $form->replace_core_search_block( '<p>content</p>', array( 'blockName' => 'core/paragraph' ) ) );
	}
);

