<?php
/**
 * Text normalization helpers.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes text before embedding and keyword matching.
 */
final class Text_Normalizer {
	/**
	 * Normalize plain text for embedding.
	 *
	 * @param string $text Input text.
	 */
	public static function normalize_for_embedding( string $text ): string {
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return is_string( $text ) ? trim( $text ) : '';
	}
}
