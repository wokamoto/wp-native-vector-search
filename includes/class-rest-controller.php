<?php
/**
 * REST API controller.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the vector search REST route.
 */
final class REST_Controller {
	private const NAMESPACE = 'vector-search/v1';

	/**
	 * Search service.
	 *
	 * @var Search_Service
	 */
	private Search_Service $search_service;

	/**
	 * Constructor.
	 */
	public function __construct( Search_Service $search_service ) {
		$this->search_service = $search_service;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				array(
					'methods'             => array( WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ),
					'callback'            => array( $this, 'search' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'query' => array(
							'type'              => 'string',
							'required'          => true,
							'minLength'         => 1,
							'maxLength'         => 500,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit' => array(
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Search endpoint callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function search( WP_REST_Request $request ) {
		$results = $this->search_service->search(
			(string) $request->get_param( 'query' ),
			(int) $request->get_param( 'limit' )
		);

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return rest_ensure_response(
			array(
				'results' => $results,
			)
		);
	}
}
