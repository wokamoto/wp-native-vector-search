<?php
/**
 * Tests for uninstall cleanup.
 *
 * @package WP_Native_Vector_Search
 */

test_case(
	'uninstall.php removes JSON and MariaDB Vector tables',
	function (): void {
		global $wpdb;

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		update_option( 'wp_native_vector_search_settings', array( 'embedding_model' => 'text-embedding-3-small' ) );

		require dirname( __DIR__, 2 ) . '/uninstall.php';

		assert_false( isset( $GLOBALS['wpnvs_test_options']['wp_native_vector_search_settings'] ) );
		assert_contains_string( 'DROP TABLE IF EXISTS `wp_vector_search_embeddings`', implode( "\n", $wpdb->queries ) );
		assert_contains_string( 'DROP TABLE IF EXISTS `wp_vector_search_embeddings_vec_1536`', implode( "\n", $wpdb->queries ) );
		assert_contains_string( 'DROP TABLE IF EXISTS `wp_vector_search_embeddings_vec_3072`', implode( "\n", $wpdb->queries ) );
	}
);
