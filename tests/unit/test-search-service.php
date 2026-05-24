<?php
/**
 * Search_Service unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\OpenAI_Client;
use WP_Native_Vector_Search\Search_Service;
use WP_Native_Vector_Search\Settings;
use WP_Native_Vector_Search\Text_Normalizer;

test_case(
	'Search_Service returns ranked PHP cosine results and filters unpublished posts',
	static function (): void {
		global $wpdb;

		$settings_values = array_merge(
			Settings::defaults(),
			array(
				'embedding_model'     => 'text-embedding-3-small',
				'min_score'           => -1,
				'keyword_boost'       => false,
				'include_attachments' => false,
				'post_types'          => array( 'post' ),
			)
		);
		update_option( Settings::OPTION_NAME, $settings_values );

		$query     = Text_Normalizer::normalize_for_embedding( 'alpha' );
		$cache_key = 'wpnvs_qemb_' . hash( 'sha256', "text-embedding-3-small\n" . $query );
		set_transient( $cache_key, array( 1.0, 0.0 ), 300 );

		wpnvs_test_register_post(
			1,
			array(
				'post_title'   => 'Alpha',
				'post_content' => 'First result',
				'post_status'  => 'publish',
			)
		);
		wpnvs_test_register_post(
			2,
			array(
				'post_title'   => 'Beta',
				'post_content' => 'Second result',
				'post_status'  => 'publish',
			)
		);
		wpnvs_test_register_post(
			3,
			array(
				'post_title'   => 'Draft',
				'post_content' => 'Hidden result',
				'post_status'  => 'draft',
			)
		);

		$wpdb->candidate_rows = array(
			array(
				'post_id'   => 2,
				'embedding' => '[0,1]',
			),
			array(
				'post_id'   => 1,
				'embedding' => '[1,0]',
			),
			array(
				'post_id'   => 3,
				'embedding' => '[1,0]',
			),
		);

		$settings = new Settings();
		$service  = new Search_Service( $settings, new WP_Native_Vector_Search\Database(), new OpenAI_Client( $settings ) );
		$results  = $service->search( 'alpha', 10 );

		assert_same( 2, count( $results ) );
		assert_same( 1, $results[0]['post_id'] );
		assert_same( 1.0, $results[0]['vector_score'] );
		assert_same( 2, $results[1]['post_id'] );
	}
);

test_case(
	'Search_Service returns WP_Error for an empty query',
	static function (): void {
		$settings = new Settings();
		$service  = new Search_Service( $settings, new WP_Native_Vector_Search\Database(), new OpenAI_Client( $settings ) );
		$result   = $service->search( '   ', 10 );

		assert_true( $result instanceof WP_Error );
		assert_same( 'wp_native_vector_search_empty_query', $result->get_error_code() );
	}
);

