<?php
/**
 * Database unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Database;

test_case(
	'Database::upsert_embedding inserts a JSON embedding row',
	static function (): void {
		global $wpdb;

		$database = new Database();
		$result   = $database->upsert_embedding(
			array(
				'post_id'         => 10,
				'post_type'       => 'post',
				'post_status'     => 'publish',
				'content_hash'    => 'hash',
				'embedding'       => array( 1.0, 0.5 ),
				'embedding_model' => 'text-embedding-3-small',
				'dimensions'      => 2,
			)
		);

		assert_true( $result );
		assert_same( 'wp_vector_search_embeddings', $wpdb->insert_calls[0]['table'] );
		assert_same( '[1,0.5]', $wpdb->insert_calls[0]['data']['embedding'] );
		assert_same( 2, $wpdb->insert_calls[0]['data']['dimensions'] );
	}
);

test_case(
	'Database::upsert_embedding updates an existing row',
	static function (): void {
		global $wpdb;

		$wpdb->existing_row = array( 'id' => 99 );

		$database = new Database();
		$result   = $database->upsert_embedding(
			array(
				'post_id'         => 10,
				'post_type'       => 'page',
				'post_status'     => 'draft',
				'content_hash'    => 'hash-2',
				'embedding'       => array( 0.25, 0.75 ),
				'embedding_model' => 'text-embedding-3-small',
				'dimensions'      => 2,
			)
		);

		assert_true( $result );
		assert_same( array(), $wpdb->insert_calls );
		assert_same( 'wp_vector_search_embeddings', $wpdb->update_calls[0]['table'] );
		assert_same( array( 'id' => 99 ), $wpdb->update_calls[0]['where'] );
		assert_same( 'draft', $wpdb->update_calls[0]['data']['post_status'] );
	}
);

test_case(
	'Database::get_candidate_embeddings sanitizes post types and returns rows',
	static function (): void {
		global $wpdb;

		$wpdb->candidate_rows = array(
			array(
				'post_id'   => 1,
				'embedding' => '[1,0]',
			),
		);

		$database = new Database();
		$rows     = $database->get_candidate_embeddings( 'model-a', array( 'post', 'Bad Type!!' ) );

		assert_same( $wpdb->candidate_rows, $rows );
		assert_contains_string( "'badtype'", end( $wpdb->prepared_queries ) );
	}
);

