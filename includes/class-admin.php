<?php
/**
 * Admin settings screen.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the admin settings page.
 */
final class Admin {
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
	 * Constructor.
	 */
	public function __construct( Settings $settings, Database $database ) {
		$this->settings = $settings;
		$this->database = $database;
	}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings_fields' ) );
		add_filter( 'manage_upload_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_fields' ), 10, 2 );
	}

	/**
	 * Add the settings page.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Vector Search', 'wp-native-vector-search' ),
			__( 'Vector Search', 'wp-native-vector-search' ),
			'manage_options',
			'wp-native-vector-search',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add settings sections and fields.
	 */
	public function add_settings_fields(): void {
		add_settings_section(
			'wp_native_vector_search_main',
			__( 'Embedding Settings', 'wp-native-vector-search' ),
			'__return_false',
			'wp-native-vector-search'
		);

		add_settings_field( 'api_key', __( 'OpenAI API Key', 'wp-native-vector-search' ), array( $this, 'render_api_key_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'embedding_model', __( 'Embedding Model', 'wp-native-vector-search' ), array( $this, 'render_model_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'vision_model', __( 'Vision Model', 'wp-native-vector-search' ), array( $this, 'render_vision_model_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'post_types', __( 'Post Types', 'wp-native-vector-search' ), array( $this, 'render_post_types_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'max_chars', __( 'Maximum Characters', 'wp-native-vector-search' ), array( $this, 'render_max_chars_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'min_score', __( 'Minimum Score', 'wp-native-vector-search' ), array( $this, 'render_min_score_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'keyword_boost', __( 'Keyword Boost', 'wp-native-vector-search' ), array( $this, 'render_keyword_boost_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'max_keyword_boost', __( 'Maximum Keyword Boost', 'wp-native-vector-search' ), array( $this, 'render_max_keyword_boost_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'auto_index', __( 'Automatic Indexing', 'wp-native-vector-search' ), array( $this, 'render_auto_index_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
		add_settings_field( 'replace_search', __( 'Replace WordPress Search', 'wp-native-vector-search' ), array( $this, 'render_replace_search_field' ), 'wp-native-vector-search', 'wp_native_vector_search_main' );
	}

	/**
	 * Render settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %d: stored embedding count. */
					esc_html__( 'Stored embeddings: %d', 'wp-native-vector-search' ),
					esc_html( (string) $this->database->count_embeddings() )
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wp_native_vector_search' );
				do_settings_sections( 'wp-native-vector-search' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render API key field.
	 */
	public function render_api_key_field(): void {
		$value = (string) $this->settings->get( 'api_key' );
		?>
		<input
			type="password"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[api_key]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<?php if ( defined( 'WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY' ) && WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY ) : ?>
			<p class="description"><?php esc_html_e( 'The API key constant is defined and will be used instead of this value.', 'wp-native-vector-search' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render embedding model field.
	 */
	public function render_model_field(): void {
		$value = (string) $this->settings->get( 'embedding_model' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[embedding_model]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<?php
	}

	/**
	 * Render vision model field.
	 */
	public function render_vision_model_field(): void {
		$value = (string) $this->settings->get( 'vision_model' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[vision_model]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description"><?php esc_html_e( 'Used to describe uploaded images before text embedding. The default is gpt-4.1-mini.', 'wp-native-vector-search' ); ?></p>
		<?php
	}

	/**
	 * Render post types field.
	 */
	public function render_post_types_field(): void {
		$selected   = (array) $this->settings->get( 'post_types' );
		$post_types = $this->settings->get_available_post_types();

		foreach ( $post_types as $post_type => $object ) :
			?>
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[post_types][]"
					value="<?php echo esc_attr( $post_type ); ?>"
					<?php checked( in_array( $post_type, $selected, true ) ); ?>
				/>
				<?php echo esc_html( $object->labels->singular_name ); ?>
			</label><br />
			<?php
		endforeach;
	}

	/**
	 * Render max chars field.
	 */
	public function render_max_chars_field(): void {
		$value = (int) $this->settings->get( 'max_chars' );
		?>
		<input
			type="number"
			min="100"
			max="50000"
			step="100"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[max_chars]"
			value="<?php echo esc_attr( (string) $value ); ?>"
		/>
		<?php
	}

	/**
	 * Render minimum score field.
	 */
	public function render_min_score_field(): void {
		$value = (float) $this->settings->get( 'min_score' );
		?>
		<input
			type="number"
			min="-1"
			max="1"
			step="0.01"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[min_score]"
			value="<?php echo esc_attr( (string) $value ); ?>"
		/>
		<p class="description"><?php esc_html_e( 'Results below this cosine similarity score are hidden. The default is 0.25.', 'wp-native-vector-search' ); ?></p>
		<?php
	}

	/**
	 * Render keyword boost field.
	 */
	public function render_keyword_boost_field(): void {
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[keyword_boost]"
				value="1"
				<?php checked( (bool) $this->settings->get( 'keyword_boost' ) ); ?>
			/>
			<?php esc_html_e( 'Add a small score boost when the query text appears in the title, content, or media description.', 'wp-native-vector-search' ); ?>
		</label>
		<?php
	}

	/**
	 * Render maximum keyword boost field.
	 */
	public function render_max_keyword_boost_field(): void {
		$value = (float) $this->settings->get( 'max_keyword_boost' );
		?>
		<input
			type="number"
			min="0"
			max="1"
			step="0.01"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[max_keyword_boost]"
			value="<?php echo esc_attr( (string) $value ); ?>"
		/>
		<p class="description"><?php esc_html_e( 'Maximum additional score from keyword matches. The default is 0.20.', 'wp-native-vector-search' ); ?></p>
		<?php
	}

	/**
	 * Render automatic indexing field.
	 */
	public function render_auto_index_field(): void {
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[auto_index]"
				value="1"
				<?php checked( (bool) $this->settings->get( 'auto_index' ) ); ?>
			/>
			<?php esc_html_e( 'Generate embeddings when configured posts are published or updated.', 'wp-native-vector-search' ); ?>
		</label>
		<?php
	}

	/**
	 * Render replace search field.
	 */
	public function render_replace_search_field(): void {
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[replace_search]"
				value="1"
				<?php checked( (bool) $this->settings->get( 'replace_search' ) ); ?>
			/>
			<?php esc_html_e( 'Replace standard WordPress search forms and core Search blocks with the vector search form.', 'wp-native-vector-search' ); ?>
		</label>
		<?php
	}

	/**
	 * Add media library columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_media_columns( array $columns ): array {
		$columns['wp_native_vector_search_image_description'] = __( 'Vector Description', 'wp-native-vector-search' );

		return $columns;
	}

	/**
	 * Render media library custom columns.
	 *
	 * @param string $column_name Column name.
	 * @param int    $attachment_id Attachment ID.
	 */
	public function render_media_column( string $column_name, int $attachment_id ): void {
		if ( 'wp_native_vector_search_image_description' !== $column_name ) {
			return;
		}

		$description = $this->get_image_description( $attachment_id );
		if ( '' === $description ) {
			echo esc_html_x( 'Not generated', 'image description status', 'wp-native-vector-search' );
			return;
		}

		$excerpt = function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 120 ) : substr( $description, 0, 120 );
		if ( $excerpt !== $description ) {
			$excerpt .= '...';
		}

		echo esc_html( $excerpt );
	}

	/**
	 * Add readonly generated description fields to attachment details.
	 *
	 * @param array<string, mixed> $form_fields Existing attachment fields.
	 * @param \WP_Post            $post Attachment post.
	 * @return array<string, mixed>
	 */
	public function add_attachment_fields( array $form_fields, \WP_Post $post ): array {
		$description  = $this->get_image_description( (int) $post->ID );
		$model        = (string) get_post_meta( (int) $post->ID, Media_Describer::META_MODEL, true );
		$generated_at = (string) get_post_meta( (int) $post->ID, Media_Describer::META_GENERATED_AT, true );
		$error        = (string) get_post_meta( (int) $post->ID, Media_Describer::META_ERROR, true );

		$html = '<textarea class="widefat" rows="6" readonly="readonly">' . esc_textarea( $description ) . '</textarea>';

		if ( '' === $description ) {
			$html = '<p>' . esc_html_x( 'Not generated', 'image description status', 'wp-native-vector-search' ) . '</p>';
		}

		if ( '' !== $model || '' !== $generated_at ) {
			$meta_parts = array_filter(
				array(
					'' !== $model ? sprintf( __( 'Model: %s', 'wp-native-vector-search' ), $model ) : '',
					'' !== $generated_at ? sprintf( __( 'Generated at: %s', 'wp-native-vector-search' ), $generated_at ) : '',
				)
			);

			$html .= '<p class="description">' . esc_html( implode( ' / ', $meta_parts ) ) . '</p>';
		}

		if ( '' !== $error ) {
			$html .= '<p class="description" style="color:#b32d2e;">' . esc_html( $error ) . '</p>';
		}

		$form_fields['wp_native_vector_search_image_description'] = array(
			'label' => __( 'Vector Description', 'wp-native-vector-search' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * Get generated image description for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function get_image_description( int $attachment_id ): string {
		$description = get_post_meta( $attachment_id, Media_Describer::META_DESCRIPTION, true );

		return is_string( $description ) ? $description : '';
	}
}
