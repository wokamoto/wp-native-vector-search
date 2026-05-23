<?php
/**
 * Standard WordPress search form replacement.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces standard WordPress search UI with vector search UI when enabled.
 */
final class Search_Form {
	private const SCRIPT_HANDLE = 'wp-native-vector-search-replace-search';
	private const STYLE_HANDLE  = 'wp-native-vector-search-replace-search';

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register frontend hooks.
	 */
	public function register(): void {
		if ( ! (bool) $this->settings->get( 'replace_search' ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'register_replacements' ) );
	}

	/**
	 * Register replacement filters after WordPress has resolved a frontend request.
	 */
	public function register_replacements(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		add_filter( 'get_search_form', array( $this, 'replace_search_form' ), 20 );
		add_filter( 'render_block', array( $this, 'replace_core_search_block' ), 20, 2 );
	}

	/**
	 * Enqueue vector search form assets.
	 */
	public function enqueue_assets(): void {
		$asset = include WP_NATIVE_VECTOR_SEARCH_DIR . 'blocks/search-box/view.asset.php';
		if ( ! is_array( $asset ) ) {
			$asset = array(
				'dependencies' => array(),
				'version'      => WP_NATIVE_VECTOR_SEARCH_VERSION,
			);
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WP_NATIVE_VECTOR_SEARCH_URL . 'blocks/search-box/view.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? WP_NATIVE_VECTOR_SEARCH_VERSION,
			true
		);

		wp_enqueue_style(
			self::STYLE_HANDLE,
			WP_NATIVE_VECTOR_SEARCH_URL . 'blocks/search-box/style.css',
			array(),
			WP_NATIVE_VECTOR_SEARCH_VERSION
		);
	}

	/**
	 * Replace output from get_search_form().
	 *
	 * @param string $form Search form markup.
	 */
	public function replace_search_form( string $form ): string {
		unset( $form );

		return $this->render_form();
	}

	/**
	 * Replace core/search block output.
	 *
	 * @param string               $block_content Rendered block content.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function replace_core_search_block( string $block_content, array $block ): string {
		if ( 'core/search' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		return $this->render_form();
	}

	/**
	 * Render vector search form markup.
	 */
	private function render_form(): string {
		$input_id = wp_unique_id( 'wp-native-vector-search-query-' );

		ob_start();
		?>
		<div class="wp-native-vector-search-box wp-native-vector-search-box--replacement">
			<form class="wp-native-vector-search-box__form" role="search">
				<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
					<?php esc_html_e( 'Search query', 'wp-native-vector-search' ); ?>
				</label>
				<input
					id="<?php echo esc_attr( $input_id ); ?>"
					class="wp-native-vector-search-box__input"
					type="search"
					name="query"
					placeholder="<?php echo esc_attr__( 'Search content', 'wp-native-vector-search' ); ?>"
					required
				/>
				<button class="wp-native-vector-search-box__button" type="submit">
					<?php esc_html_e( 'Search', 'wp-native-vector-search' ); ?>
				</button>
			</form>
			<div class="wp-native-vector-search-box__status" aria-live="polite"></div>
			<ol class="wp-native-vector-search-box__results"></ol>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
