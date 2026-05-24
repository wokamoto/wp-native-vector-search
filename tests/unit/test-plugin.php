<?php
/**
 * Plugin unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Plugin;

test_case(
	'Plugin wires core service hooks',
	static function (): void {
		global $wpdb;

		$wpdb->server_version = '8.4.0-MySQL';

		$plugin = new Plugin();
		$plugin->register();

		$hook_names = array_column( $GLOBALS['wpnvs_test_hooks'], 'hook' );

		assert_true( in_array( 'admin_init', $hook_names, true ) );
		assert_true( in_array( 'save_post', $hook_names, true ) );
		assert_true( in_array( 'rest_api_init', $hook_names, true ) );
		assert_true( in_array( 'init', $hook_names, true ) );
	}
);

