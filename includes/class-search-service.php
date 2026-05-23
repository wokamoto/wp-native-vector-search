<?php
/**
 * Semantic search service.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searches stored embeddings using cosine similarity.
 */
final class Search_Service {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Database service.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * OpenAI client.
	 *
	 * @var OpenAI_Client
	 */
	private OpenAI_Client $openai_client;

	/**
	 * Constructor.
	 */
	public function __construct( Settings $settings, Database $database, OpenAI_Client $openai_client ) {
		$this->settings      = $settings;
		$this->database      = $database;
		$this->openai_client = $openai_client;
	}

	/**
	 * Search posts.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum results.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function search( string $query, int $limit = 10 ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return new WP_Error( 'wp_native_vector_search_empty_query', __( 'Search query is required.', 'wp-native-vector-search' ), array( 'status' => 400 ) );
		}

		$limit                 = max( 1, min( 50, $limit ) );
		$model                 = (string) $this->settings->get( 'embedding_model' );
		$min_score             = (float) $this->settings->get( 'min_score' );
		$keyword_boost_enabled = (bool) $this->settings->get( 'keyword_boost' );
		$max_keyword_boost     = (float) $this->settings->get( 'max_keyword_boost' );
		$query_embedding       = $this->openai_client->create_embedding( $query, $model );
		if ( is_wp_error( $query_embedding ) ) {
			return $query_embedding;
		}

		$post_types = (array) $this->settings->get( 'post_types' );
		if ( (bool) $this->settings->get( 'include_attachments' ) ) {
			$post_types[] = 'attachment';
		}
		$post_types = array_values( array_unique( $post_types ) );
		$rows       = $this->database->get_candidate_embeddings( $model, $post_types );
		$matches    = array();

		foreach ( $rows as $row ) {
			$post = get_post( (int) $row['post_id'] );
			if ( ! $post || ! $this->is_post_searchable( $post ) ) {
				continue;
			}

			$embedding = json_decode( (string) $row['embedding'], true );
			if ( ! is_array( $embedding ) || count( $embedding ) !== count( $query_embedding ) ) {
				continue;
			}

			$vector_score = $this->cosine_similarity( $query_embedding, array_map( 'floatval', $embedding ) );
			if ( null === $vector_score ) {
				continue;
			}

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

		usort(
			$matches,
			static function ( array $a, array $b ): int {
				return $b['score'] <=> $a['score'];
			}
		);

		$matches = array_slice( $matches, 0, $limit );
		$results = array();

		foreach ( $matches as $match ) {
			$post = $match['post'] ?? null;
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( 'attachment' === $post->post_type ) {
				$results[] = $this->format_media_result( $post, $match );
				continue;
			}

			$results[] = $this->format_post_result( $post, $match );
		}

		return $results;
	}

	/**
	 * Format a post search result.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array<string, mixed> $match Match scores.
	 * @return array<string, mixed>
	 */
	private function format_post_result( \WP_Post $post, array $match ): array {
		return array(
			'type'          => 'post',
			'post_id'       => (int) $post->ID,
			'title'         => get_the_title( $post ),
			'description'   => $this->build_result_description( $post ),
			'url'           => get_permalink( $post ),
			'post_type'     => $post->post_type,
			'thumbnail_url' => $this->get_post_thumbnail_url( $post ),
			'score'         => round( (float) $match['score'], 6 ),
			'vector_score'  => round( (float) $match['vector_score'], 6 ),
			'keyword_score' => round( (float) $match['keyword_score'], 6 ),
		);
	}

	/**
	 * Format a media search result.
	 *
	 * @param \WP_Post $attachment Attachment object.
	 * @param array<string, mixed> $match Match scores.
	 * @return array<string, mixed>
	 */
	private function format_media_result( \WP_Post $attachment, array $match ): array {
		return array(
			'type'           => 'media',
			'attachment_id'  => (int) $attachment->ID,
			'post_id'        => (int) $attachment->ID,
			'title'          => get_the_title( $attachment ),
			'description'    => $this->build_result_description( $attachment ),
			'url'            => wp_get_attachment_url( (int) $attachment->ID ),
			'post_type'      => 'attachment',
			'thumbnail_url'  => wp_get_attachment_image_url( (int) $attachment->ID, 'thumbnail' ),
			'media_url'      => wp_get_attachment_url( (int) $attachment->ID ),
			'score'          => round( (float) $match['score'], 6 ),
			'vector_score'   => round( (float) $match['vector_score'], 6 ),
			'keyword_score'  => round( (float) $match['keyword_score'], 6 ),
		);
	}

	/**
	 * Build a short description for a search result.
	 *
	 * @param \WP_Post $post Post or attachment object.
	 */
	private function build_result_description( \WP_Post $post ): string {
		if ( 'attachment' === $post->post_type ) {
			$description = (string) get_post_meta( (int) $post->ID, Media_Describer::META_DESCRIPTION, true );
			if ( '' !== trim( $description ) ) {
				return $this->trim_result_description( $description );
			}
		}

		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

		return $this->trim_result_description( $text );
	}

	/**
	 * Trim result descriptions to a compact plain-text snippet.
	 *
	 * @param string $text Input text.
	 */
	private function trim_result_description( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		if ( '' === $text ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text ) > 160 ? mb_substr( $text, 0, 157 ) . '...' : $text;
		}

		return strlen( $text ) > 160 ? substr( $text, 0, 157 ) . '...' : $text;
	}

	/**
	 * Get a normalized featured image URL for post-like search results.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function get_post_thumbnail_url( \WP_Post $post ): string {
		$thumbnail_url = get_the_post_thumbnail_url( $post, 'thumbnail' );

		return is_string( $thumbnail_url ) ? $thumbnail_url : '';
	}

	/**
	 * Calculate a keyword boost for exact query and token matches.
	 *
	 * @param string   $query Search query.
	 * @param \WP_Post $post Matched post or attachment.
	 * @param float    $max_boost Maximum boost.
	 */
	private function calculate_keyword_boost( string $query, \WP_Post $post, float $max_boost ): float {
		if ( $max_boost <= 0.0 ) {
			return 0.0;
		}

		$query = $this->normalize_keyword_text( $query );
		if ( '' === $query ) {
			return 0.0;
		}

		$title = $this->normalize_keyword_text( get_the_title( $post ) );
		$body  = $this->build_keyword_text( $post );
		$terms = $this->build_query_terms( $query );
		$boost = 0.0;

		if ( str_contains( $title, $query ) ) {
			$boost += 0.12;
		} elseif ( str_contains( $body, $query ) ) {
			$boost += 0.10;
		}

		foreach ( $terms as $term ) {
			if ( $term === $query ) {
				continue;
			}

			if ( str_contains( $title, $term ) ) {
				$boost += 0.06;
			} elseif ( str_contains( $body, $term ) ) {
				$boost += 0.04;
			}
		}

		return min( $max_boost, $boost );
	}

	/**
	 * Build searchable keyword text for a post or attachment.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function build_keyword_text( \WP_Post $post ): string {
		$parts = array(
			get_the_title( $post ),
			$post->post_excerpt,
			$post->post_content,
		);

		if ( 'attachment' === $post->post_type ) {
			$parts[] = (string) get_post_meta( (int) $post->ID, '_wp_attachment_image_alt', true );
			$parts[] = (string) get_post_meta( (int) $post->ID, Media_Describer::META_DESCRIPTION, true );
		}

		$text = implode( ' ', array_filter( array_map( 'strval', $parts ) ) );
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

		return $this->normalize_keyword_text( $text );
	}

	/**
	 * Determine whether a stored row's current post should be included in search.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function is_post_searchable( \WP_Post $post ): bool {
		if ( 'attachment' === $post->post_type ) {
			return true;
		}

		return 'publish' === $post->post_status;
	}

	/**
	 * Build keyword query terms from a normalized query.
	 *
	 * @param string $query Normalized query.
	 * @return array<int, string>
	 */
	private function build_query_terms( string $query ): array {
		$terms = preg_split( '/[\s　]+/u', $query );
		if ( ! is_array( $terms ) ) {
			return array( $query );
		}

		$terms[] = $query;
		$terms   = array_filter(
			array_map(
				static function ( string $term ): string {
					return trim( $term );
				},
				$terms
			),
			static function ( string $term ): bool {
				return '' !== $term;
			}
		);

		return array_values( array_unique( $terms ) );
	}

	/**
	 * Normalize text for simple keyword matching.
	 *
	 * @param string $text Input text.
	 */
	private function normalize_keyword_text( string $text ): string {
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return is_string( $text ) ? trim( $text ) : '';
	}

	/**
	 * Calculate cosine similarity.
	 *
	 * @param array<int, float> $a First vector.
	 * @param array<int, float> $b Second vector.
	 */
	private function cosine_similarity( array $a, array $b ): ?float {
		$dot   = 0.0;
		$norma = 0.0;
		$normb = 0.0;
		$count = count( $a );

		for ( $i = 0; $i < $count; $i++ ) {
			$dot   += $a[ $i ] * $b[ $i ];
			$norma += $a[ $i ] ** 2;
			$normb += $b[ $i ] ** 2;
		}

		if ( 0.0 === $norma || 0.0 === $normb ) {
			return null;
		}

		return $dot / ( sqrt( $norma ) * sqrt( $normb ) );
	}
}
