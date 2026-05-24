<?php
/**
 * Plugin settings.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and registers plugin options.
 */
final class Settings {
	public const OPTION_NAME = 'wp_native_vector_search_settings';

	private const ALLOWED_EMBEDDING_MODELS = array(
		'text-embedding-3-small',
		'text-embedding-3-large',
		'text-embedding-ada-002',
	);

	private const ALLOWED_SEARCH_BACKENDS = array(
		'php',
		'mariadb_vector',
		'auto',
	);

	/**
	 * Register Settings API hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Add default options on activation.
	 */
	public static function add_defaults(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	/**
	 * Get default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'api_key'           => '',
			'embedding_model'   => 'text-embedding-3-small',
			'vision_model'      => 'gpt-4.1-mini',
			'search_backend'    => 'php',
			'max_chars'         => 8000,
			'min_score'         => 0.25,
			'keyword_boost'     => true,
			'max_keyword_boost' => 0.2,
			'auto_index'        => true,
			'include_attachments' => true,
			'replace_search'    => false,
			'post_types'        => array( 'post' ),
		);
	}

	/**
	 * Register the option.
	 */
	public function register_setting(): void {
		register_setting(
			'wp_native_vector_search',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param mixed $input Raw option input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$defaults = self::defaults();
		$current  = $this->get_all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		$settings = $defaults;

		$settings['api_key'] = isset( $input['api_key'] )
			? sanitize_text_field( wp_unslash( (string) $input['api_key'] ) )
			: $current['api_key'];

		$settings['embedding_model'] = isset( $input['embedding_model'] )
			? sanitize_text_field( wp_unslash( (string) $input['embedding_model'] ) )
			: $current['embedding_model'];

		if ( ! in_array( $settings['embedding_model'], self::ALLOWED_EMBEDDING_MODELS, true ) ) {
			$settings['embedding_model'] = $defaults['embedding_model'];
		}

		$settings['search_backend'] = isset( $input['search_backend'] )
			? sanitize_key( wp_unslash( (string) $input['search_backend'] ) )
			: $current['search_backend'];

		if ( ! in_array( $settings['search_backend'], self::ALLOWED_SEARCH_BACKENDS, true ) ) {
			$settings['search_backend'] = $defaults['search_backend'];
		}

		if ( 'mariadb_vector' === $settings['search_backend'] ) {
			$database   = new Database_Maria();
			$dimensions = $database->get_model_dimensions( $settings['embedding_model'] );
			$status     = $database->get_mariadb_vector_status( $dimensions, true );

			if ( empty( $status['available'] ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'wp_native_vector_search_mariadb_vector_unavailable',
					__( 'MariaDB Vector backend was not saved because the required vector table or index is not available for the selected embedding model.', 'wp-native-vector-search' ),
					'error'
				);
				$settings['search_backend'] = in_array( (string) $current['search_backend'], self::ALLOWED_SEARCH_BACKENDS, true )
					? (string) $current['search_backend']
					: $defaults['search_backend'];
			}
		}

		$settings['vision_model'] = isset( $input['vision_model'] )
			? sanitize_text_field( wp_unslash( (string) $input['vision_model'] ) )
			: $current['vision_model'];

		if ( '' === $settings['vision_model'] ) {
			$settings['vision_model'] = $defaults['vision_model'];
		}

		$settings['max_chars'] = isset( $input['max_chars'] )
			? max( 100, min( 50000, absint( $input['max_chars'] ) ) )
			: $current['max_chars'];

		$settings['min_score'] = isset( $input['min_score'] )
			? max( -1.0, min( 1.0, (float) $input['min_score'] ) )
			: (float) $current['min_score'];

		$settings['keyword_boost'] = ! empty( $input['keyword_boost'] );
		$settings['max_keyword_boost'] = isset( $input['max_keyword_boost'] )
			? max( 0.0, min( 1.0, (float) $input['max_keyword_boost'] ) )
			: (float) $current['max_keyword_boost'];

		$settings['auto_index'] = ! empty( $input['auto_index'] );
		$settings['include_attachments'] = ! empty( $input['include_attachments'] );
		$settings['replace_search'] = ! empty( $input['replace_search'] );

		$allowed_post_types = $this->get_available_post_types();
		$post_types         = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $input['post_types'] ) )
			: $current['post_types'];

		$settings['post_types'] = array_values( array_intersect( $post_types, array_keys( $allowed_post_types ) ) );
		if ( array() === $settings['post_types'] ) {
			$settings['post_types'] = $defaults['post_types'];
		}

		return $settings;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return array_merge( self::defaults(), $options );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( string $key ) {
		$settings = $this->get_all();
		return $settings[ $key ] ?? null;
	}

	/**
	 * Get the configured API key.
	 */
	public function get_api_key(): string {
		if ( defined( 'WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY' ) && WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY ) {
			return (string) WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY;
		}

		return (string) $this->get( 'api_key' );
	}

	/**
	 * Get available public post types.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	public function get_available_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'] );

		return $post_types;
	}
}
