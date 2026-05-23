<?php
/**
 * Uninstall cleanup.
 *
 * @package WP_Native_Vector_Search
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_native_vector_search_settings' );

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'vector_search_embeddings' );
