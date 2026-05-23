<?php
/**
 * Block registration.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin blocks.
 */
final class Blocks {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register block metadata.
	 */
	public function register_blocks(): void {
		register_block_type( WP_NATIVE_VECTOR_SEARCH_DIR . 'blocks/search-box' );
	}
}
