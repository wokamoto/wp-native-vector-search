<?php
/**
 * Search box block render template.
 *
 * @package WP_Native_Vector_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wp-native-vector-search-box',
	)
);
$input_id           = wp_unique_id( 'wp-native-vector-search-query-' );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<form class="wp-native-vector-search-box__form" role="search">
		<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
			<?php esc_html_e( 'Search query', 'wp-native-vector-search' ); ?>
		</label>
		<input
			id="<?php echo esc_attr( $input_id ); ?>"
			class="wp-native-vector-search-box__input"
			type="search"
			name="query"
			placeholder="<?php echo esc_attr__( 'Search posts', 'wp-native-vector-search' ); ?>"
			required
		/>
		<button class="wp-native-vector-search-box__button" type="submit">
			<?php esc_html_e( 'Search', 'wp-native-vector-search' ); ?>
		</button>
	</form>
	<div class="wp-native-vector-search-box__status" aria-live="polite"></div>
	<ol class="wp-native-vector-search-box__results"></ol>
</div>
