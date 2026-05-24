<?php
/**
 * MariaDB Vector semantic search service.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searches stored embeddings using MariaDB Vector when configured.
 */
final class Search_Service_Maria extends Search_Service {
	/**
	 * MariaDB database service.
	 *
	 * @var Database_Maria
	 */
	private Database_Maria $maria_database;

	/**
	 * Constructor.
	 */
	public function __construct( Settings $settings, Database_Maria $database, OpenAI_Client $openai_client ) {
		parent::__construct( $settings, $database, $openai_client );

		$this->maria_database = $database;
	}

	/**
	 * Search posts.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum results.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function search( string $query, int $limit = 10 ) {
		$query = Text_Normalizer::normalize_for_embedding( $query );
		if ( '' === $query ) {
			return new WP_Error( 'wp_native_vector_search_empty_query', __( 'Search query is required.', 'wp-native-vector-search' ), array( 'status' => 400 ) );
		}

		$search_backend = (string) $this->settings->get( 'search_backend' );
		if ( 'mariadb_vector' !== $search_backend && 'auto' !== $search_backend ) {
			return parent::search( $query, $limit );
		}

		$limit                 = max( 1, min( 50, $limit ) );
		$model                 = (string) $this->settings->get( 'embedding_model' );
		$min_score             = (float) $this->settings->get( 'min_score' );
		$keyword_boost_enabled = (bool) $this->settings->get( 'keyword_boost' );
		$max_keyword_boost     = (float) $this->settings->get( 'max_keyword_boost' );
		$query_embedding       = $this->get_query_embedding( $query, $model );
		if ( is_wp_error( $query_embedding ) ) {
			return $query_embedding;
		}

		$post_types     = $this->get_search_post_types();
		$vector_results = $this->search_with_mariadb_vector(
			$query,
			$query_embedding,
			$model,
			$post_types,
			$limit,
			$min_score,
			$keyword_boost_enabled,
			$max_keyword_boost
		);

		if ( ! is_wp_error( $vector_results ) ) {
			return $vector_results;
		}

		if ( 'mariadb_vector' === $search_backend ) {
			return $vector_results;
		}

		return $this->search_with_php(
			$query,
			$query_embedding,
			$model,
			$post_types,
			$limit,
			$min_score,
			$keyword_boost_enabled,
			$max_keyword_boost
		);
	}

	/**
	 * Search using MariaDB Vector.
	 *
	 * @param string             $query Normalized search query.
	 * @param array<int, float>  $query_embedding Query embedding.
	 * @param string             $model Embedding model.
	 * @param array<int, string> $post_types Post types.
	 * @param int                $limit Maximum results.
	 * @param float              $min_score Minimum score.
	 * @param bool               $keyword_boost_enabled Whether keyword boost is enabled.
	 * @param float              $max_keyword_boost Maximum keyword boost.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function search_with_mariadb_vector( string $query, array $query_embedding, string $model, array $post_types, int $limit, float $min_score, bool $keyword_boost_enabled, float $max_keyword_boost ) {
		$dimensions = count( $query_embedding );
		$status     = $this->maria_database->get_mariadb_vector_status( $dimensions );
		if ( empty( $status['available'] ) ) {
			return new WP_Error( 'wp_native_vector_search_mariadb_vector_unavailable', __( 'MariaDB Vector search is not available for the selected embedding model.', 'wp-native-vector-search' ), array( 'status' => 500 ) );
		}

		$candidate_limit = max( 100, $limit * 5 );
		$rows            = $this->maria_database->get_vector_search_candidates( $model, $post_types, $query_embedding, $candidate_limit );
		$matches         = array();

		foreach ( $rows as $row ) {
			$post = get_post( (int) $row['post_id'] );
			if ( ! $post || ! $this->is_post_searchable( $post ) ) {
				continue;
			}

			if ( ! isset( $row['distance'] ) || ! is_numeric( $row['distance'] ) ) {
				continue;
			}

			$vector_score  = 1.0 - (float) $row['distance'];
			$keyword_score = $keyword_boost_enabled
				? $this->calculate_keyword_boost( $query, $post, $max_keyword_boost )
				: 0.0;
			$score         = min( 1.0, $vector_score + $keyword_score );

			if ( $score < $min_score ) {
				continue;
			}

			$matches[] = array(
				'post_id'       => (int) $row['post_id'],
				'post'          => $post,
				'post_type'     => $post->post_type,
				'score'         => $score,
				'vector_score'  => $vector_score,
				'keyword_score' => $keyword_score,
			);
		}

		return $this->format_matches( $matches, $limit );
	}
}
