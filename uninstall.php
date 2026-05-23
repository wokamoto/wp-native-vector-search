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
$table_name = esc_sql( $wpdb->prefix . 'vector_search_embeddings' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
