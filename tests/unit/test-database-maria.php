<?php
/**
 * Database_Maria unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Database_Maria;

test_case(
	'Database_Maria reports MariaDB Vector availability for ready tables',
	static function (): void {
		global $wpdb;

		$wpdb->existing_tables = array( 'wp_vector_search_embeddings_vec_1536' );
		$wpdb->show_create     = array(
			'wp_vector_search_embeddings_vec_1536' => 'CREATE TABLE t (embedding VECTOR(1536) NOT NULL, VECTOR INDEX (embedding) M=8 DISTANCE=cosine)',
		);

		$database = new Database_Maria();
		$status   = $database->get_mariadb_vector_status( 1536, true );

		assert_true( $status['server_is_mariadb'] );
		assert_true( $status['version_supported'] );
		assert_true( $status['vector_functions_available'] );
		assert_true( $status['available'] );
	}
);

test_case(
	'Database_Maria creates a dimension-specific vector table',
	static function (): void {
		global $wpdb;

		$wpdb->existing_tables = array( 'wp_vector_search_embeddings_vec_1536' );
		$wpdb->show_create     = array(
			'wp_vector_search_embeddings_vec_1536' => 'CREATE TABLE t (embedding VECTOR(1536) NOT NULL, VECTOR INDEX (embedding) M=8 DISTANCE=cosine)',
		);

		$database = new Database_Maria();
		$result   = $database->create_vector_table( 1536 );

		assert_true( $result );
		$create_query = '';
		foreach ( $wpdb->queries as $query ) {
			if ( str_contains( $query, 'CREATE TABLE IF NOT EXISTS' ) ) {
				$create_query = $query;
				break;
			}
		}

		assert_contains_string( 'VECTOR(1536)', $create_query );
		assert_contains_string( 'DISTANCE=cosine', $create_query );
	}
);

test_case(
	'Database_Maria rejects unsupported vector dimensions',
	static function (): void {
		$database = new Database_Maria();
		$result   = $database->create_vector_table( 999 );

		assert_true( $result instanceof WP_Error );
		assert_same( 'wp_native_vector_search_unsupported_dimensions', $result->get_error_code() );
	}
);

test_case(
	'Database_Maria builds vector candidate search SQL',
	static function (): void {
		global $wpdb;

		$wpdb->vector_rows = array(
			array(
				'post_id'  => 10,
				'distance' => '0.1',
			),
		);

		$database = new Database_Maria();
		$rows     = $database->get_vector_search_candidates( 'text-embedding-3-small', array( 'post' ), array_fill( 0, 1536, 0.1 ), 25 );

		assert_same( $wpdb->vector_rows, $rows );
		assert_contains_string( 'VEC_DISTANCE_COSINE', end( $wpdb->prepared_queries ) );
		assert_contains_string( 'VEC_FromText', end( $wpdb->prepared_queries ) );
		assert_contains_string( 'LIMIT 25', end( $wpdb->prepared_queries ) );
	}
);

test_case(
	'Database_Maria mirrors JSON upserts to the vector table when available',
	static function (): void {
		global $wpdb;

		$wpdb->existing_tables = array( 'wp_vector_search_embeddings_vec_1536' );
		$wpdb->show_create     = array(
			'wp_vector_search_embeddings_vec_1536' => 'CREATE TABLE t (embedding VECTOR(1536) NOT NULL, VECTOR INDEX (embedding) M=8 DISTANCE=cosine)',
		);

		$database = new Database_Maria();
		$result   = $database->upsert_embedding(
			array(
				'post_id'         => 10,
				'post_type'       => 'post',
				'post_status'     => 'publish',
				'content_hash'    => 'hash',
				'embedding'       => array_fill( 0, 1536, 0.1 ),
				'embedding_model' => 'text-embedding-3-small',
				'dimensions'      => 1536,
			)
		);

		assert_true( $result );
		assert_same( 'wp_vector_search_embeddings', $wpdb->insert_calls[0]['table'] );
		assert_contains_string( 'INSERT INTO `wp_vector_search_embeddings_vec_1536`', end( $wpdb->prepared_queries ) );
		assert_contains_string( 'VEC_FromText', end( $wpdb->prepared_queries ) );
	}
);
