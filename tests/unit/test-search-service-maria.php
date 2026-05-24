<?php
/**
 * Search_Service_Maria unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Database_Maria;
use WP_Native_Vector_Search\OpenAI_Client;
use WP_Native_Vector_Search\Search_Service_Maria;
use WP_Native_Vector_Search\Settings;
use WP_Native_Vector_Search\Text_Normalizer;

test_case(
	'Search_Service_Maria uses MariaDB Vector results when the backend is mariadb_vector',
	static function (): void {
		global $wpdb;

		$wpdb->existing_tables = array( 'wp_vector_search_embeddings_vec_1536' );
		$wpdb->show_create     = array(
			'wp_vector_search_embeddings_vec_1536' => 'CREATE TABLE t (embedding VECTOR(1536) NOT NULL, VECTOR INDEX (embedding) M=8 DISTANCE=cosine)',
		);
		$wpdb->vector_rows     = array(
			array(
				'post_id'  => 1,
				'distance' => '0.05',
			),
			array(
				'post_id'  => 2,
				'distance' => '0.4',
			),
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'embedding_model'     => 'text-embedding-3-small',
					'search_backend'      => 'mariadb_vector',
					'min_score'           => -1,
					'keyword_boost'       => false,
					'include_attachments' => false,
					'post_types'          => array( 'post' ),
				)
			)
		);

		$query     = Text_Normalizer::normalize_for_embedding( 'alpha' );
		$cache_key = 'wpnvs_qemb_' . hash( 'sha256', "text-embedding-3-small\n" . $query );
		set_transient( $cache_key, array_fill( 0, 1536, 0.1 ), 300 );

		wpnvs_test_register_post( 1, array( 'post_title' => 'Alpha', 'post_status' => 'publish' ) );
		wpnvs_test_register_post( 2, array( 'post_title' => 'Beta', 'post_status' => 'publish' ) );

		$settings = new Settings();
		$database = new Database_Maria();
		$service  = new Search_Service_Maria( $settings, $database, new OpenAI_Client( $settings ) );
		$results  = $service->search( 'alpha', 10 );

		assert_same( 2, count( $results ) );
		assert_same( 1, $results[0]['post_id'] );
		assert_same( 0.95, $results[0]['vector_score'] );
		assert_contains_string( 'VEC_DISTANCE_COSINE', end( $wpdb->prepared_queries ) );
	}
);

test_case(
	'Search_Service_Maria falls back to PHP search when auto backend has no vector table',
	static function (): void {
		global $wpdb;

		$wpdb->existing_tables = array();
		$wpdb->candidate_rows  = array(
			array(
				'post_id'   => 1,
				'embedding' => '[1,0]',
			),
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'embedding_model'     => 'text-embedding-3-small',
					'search_backend'      => 'auto',
					'min_score'           => -1,
					'keyword_boost'       => false,
					'include_attachments' => false,
					'post_types'          => array( 'post' ),
				)
			)
		);

		$query     = Text_Normalizer::normalize_for_embedding( 'alpha' );
		$cache_key = 'wpnvs_qemb_' . hash( 'sha256', "text-embedding-3-small\n" . $query );
		set_transient( $cache_key, array( 1.0, 0.0 ), 300 );

		wpnvs_test_register_post( 1, array( 'post_title' => 'Alpha', 'post_status' => 'publish' ) );

		$settings = new Settings();
		$database = new Database_Maria();
		$service  = new Search_Service_Maria( $settings, $database, new OpenAI_Client( $settings ) );
		$results  = $service->search( 'alpha', 10 );

		assert_same( 1, count( $results ) );
		assert_same( 1, $results[0]['post_id'] );
		assert_false( str_contains( implode( "\n", $wpdb->prepared_queries ), 'VEC_DISTANCE_COSINE' ) );
	}
);

test_case(
	'Search_Service_Maria returns an error when strict MariaDB Vector backend is unavailable',
	static function (): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'embedding_model' => 'text-embedding-3-small',
					'search_backend'  => 'mariadb_vector',
				)
			)
		);

		$query     = Text_Normalizer::normalize_for_embedding( 'alpha' );
		$cache_key = 'wpnvs_qemb_' . hash( 'sha256', "text-embedding-3-small\n" . $query );
		set_transient( $cache_key, array_fill( 0, 1536, 0.1 ), 300 );

		$settings = new Settings();
		$database = new Database_Maria();
		$service  = new Search_Service_Maria( $settings, $database, new OpenAI_Client( $settings ) );
		$result   = $service->search( 'alpha', 10 );

		assert_true( $result instanceof WP_Error );
		assert_same( 'wp_native_vector_search_mariadb_vector_unavailable', $result->get_error_code() );
	}
);

