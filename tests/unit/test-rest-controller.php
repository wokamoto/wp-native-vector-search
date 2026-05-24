<?php
/**
 * REST_Controller unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\REST_Controller;
use WP_Native_Vector_Search\Search_Service;

/**
 * Search service fake for REST tests.
 */
final class WPNVS_Test_Search_Service extends Search_Service {
	public function __construct() {}

	public function search( string $query, int $limit = 10 ) {
		return array(
			array(
				'post_id' => 1,
				'title'   => $query . '-' . $limit,
			),
		);
	}
}

test_case(
	'REST_Controller registers the search route',
	static function (): void {
		$controller = new REST_Controller( new WPNVS_Test_Search_Service() );
		$controller->register_routes();

		assert_same( 'vector-search/v1', $GLOBALS['wpnvs_test_rest_routes'][0]['namespace'] );
		assert_same( '/search', $GLOBALS['wpnvs_test_rest_routes'][0]['route'] );
	}
);

test_case(
	'REST_Controller::search returns wrapped search results',
	static function (): void {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$controller = new REST_Controller( new WPNVS_Test_Search_Service() );
		$response   = $controller->search( new WP_REST_Request( array( 'query' => 'alpha', 'limit' => 3 ) ) );

		assert_true( $response instanceof WP_REST_Response );
		assert_same( 'alpha-3', $response->get_data()['results'][0]['title'] );
	}
);

