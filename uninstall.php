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

$table_names = array(
	$wpdb->prefix . 'vector_search_embeddings',
	$wpdb->prefix . 'vector_search_embeddings_vec_1536',
	$wpdb->prefix . 'vector_search_embeddings_vec_3072',
);

foreach ( $table_names as $table_name ) {
	$table_name = esc_sql( $table_name );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
}
