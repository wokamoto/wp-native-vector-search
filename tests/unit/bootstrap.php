<?php
/**
 * Unit test bootstrap.
 *
 * @package WP_Native_Vector_Search
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_NATIVE_VECTOR_SEARCH_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'WP_NATIVE_VECTOR_SEARCH_URL', 'https://example.test/wp-content/plugins/wp-native-vector-search/' );
define( 'WP_NATIVE_VECTOR_SEARCH_VERSION', 'test' );

$GLOBALS['wpnvs_test_options']    = array();
$GLOBALS['wpnvs_test_transients'] = array();
$GLOBALS['wpnvs_test_posts']      = array();
$GLOBALS['wpnvs_test_meta']       = array();
$GLOBALS['wpnvs_test_hooks']      = array();
$GLOBALS['wpnvs_test_filters']    = array();
$GLOBALS['wpnvs_test_scheduled']  = array();
$GLOBALS['wpnvs_test_actions']    = array();
$GLOBALS['wpnvs_test_deleted_meta'] = array();
$GLOBALS['wpnvs_test_files']      = array();
$GLOBALS['wpnvs_test_mime_types'] = array();
$GLOBALS['wpnvs_test_http_response'] = null;
$GLOBALS['wpnvs_test_http_requests'] = array();
$GLOBALS['wpnvs_test_rest_routes'] = array();
$GLOBALS['wpnvs_test_scripts']    = array();
$GLOBALS['wpnvs_test_styles']     = array();
$GLOBALS['wpnvs_test_blocks']     = array();
$GLOBALS['wpnvs_test_admin']      = false;
$GLOBALS['wpnvs_test_ajax']       = false;
$GLOBALS['wpnvs_test_unique_id']  = 0;
$GLOBALS['wpnvs_test_settings_errors'] = array();

/**
 * Minimal WP REST server test double.
 */
class WP_REST_Server {
	public const READABLE = 'GET';
	public const CREATABLE = 'POST';
}

/**
 * Minimal WP REST request test double.
 */
class WP_REST_Request {
	/**
	 * Request params.
	 *
	 * @var array<string, mixed>
	 */
	private array $params;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $params Request params.
	 */
	public function __construct( array $params = array() ) {
		$this->params = $params;
	}

	/**
	 * Get a request param.
	 *
	 * @param string $key Param key.
	 * @return mixed
	 */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}
}

/**
 * Minimal WP REST response test double.
 */
class WP_REST_Response {
	/**
	 * Response data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param mixed $data Response data.
	 */
	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Get response data.
	 *
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}
}

/**
 * Minimal WP-CLI test double.
 */
class WP_CLI {
	public static array $commands = array();
	public static array $logs = array();
	public static array $warnings = array();

	/**
	 * Add a command.
	 *
	 * @param string $name Command name.
	 * @param mixed  $callable Command handler.
	 */
	public static function add_command( string $name, $callable ): void {
		self::$commands[ $name ] = $callable;
	}

	/**
	 * Log a message.
	 *
	 * @param string $message Message.
	 */
	public static function log( string $message ): void {
		self::$logs[] = $message;
	}

	/**
	 * Log a warning.
	 *
	 * @param string $message Message.
	 */
	public static function warning( string $message ): void {
		self::$warnings[] = $message;
	}

	/**
	 * Mark success.
	 *
	 * @param string $message Message.
	 */
	public static function success( string $message ): void {
		self::$logs[] = 'Success: ' . $message;
	}

	/**
	 * Raise an error.
	 *
	 * @param string $message Message.
	 */
	public static function error( string $message ): void {
		throw new RuntimeException( $message );
	}
}

/**
 * Minimal WP_CLI\Utils test double.
 */
final class WPNVS_Test_WP_CLI_Utils {
	/**
	 * Format items.
	 *
	 * @param string            $format Format.
	 * @param array<int, mixed> $items Items.
	 * @param array<int,string> $fields Fields.
	 */
	public static function format_items( string $format, array $items, array $fields ): void {
		$GLOBALS['wpnvs_test_cli_items'][] = compact( 'format', 'items', 'fields' );
	}
}

class_alias( WPNVS_Test_WP_CLI_Utils::class, 'WP_CLI\Utils' );

/**
 * Minimal WP_Error test double.
 */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Error data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param mixed  $data Error data.
	 */
	public function __construct( string $code = '', string $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	/**
	 * Get error code.
	 */
	public function get_error_code(): string {
		return $this->code;
	}

	/**
	 * Get error message.
	 */
	public function get_error_message(): string {
		return $this->message;
	}

	/**
	 * Get error data.
	 *
	 * @return mixed
	 */
	public function get_error_data() {
		return $this->data;
	}
}

/**
 * Minimal WP_Post test double.
 */
class WP_Post extends stdClass {
	/**
	 * Constructor.
	 *
	 * @param object|array<string, mixed> $data Post data.
	 */
	public function __construct( $data ) {
		foreach ( (array) $data as $key => $value ) {
			$this->{$key} = $value;
		}
	}
}

/**
 * Minimal WP_Query test double.
 */
class WP_Query {
	/**
	 * Queried posts.
	 *
	 * @var array<int, int>
	 */
	public array $posts;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Query args.
	 */
	public function __construct( array $args ) {
		$GLOBALS['wpnvs_test_last_query_args'] = $args;
		$this->posts = $GLOBALS['wpnvs_test_query_posts'] ?? array();
	}
}

/**
 * Fake wpdb implementation focused on SQL assertions.
 */
final class WPNVS_Test_WPDB {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public string $server_version = '11.8.7-MariaDB';
	public bool $vector_functions_available = true;
	public array $existing_tables = array();
	public array $show_create = array();
	public array $candidate_rows = array();
	public array $vector_rows = array();
	public array $migration_rows = array();
	public ?array $existing_row = null;
	public int $count = 0;
	public array $insert_calls = array();
	public array $update_calls = array();
	public array $delete_calls = array();
	public array $queries = array();
	public array $prepared_queries = array();

	/**
	 * Reset mutable state.
	 */
	public function reset(): void {
		$this->last_error                 = '';
		$this->server_version             = '11.8.7-MariaDB';
		$this->vector_functions_available = true;
		$this->existing_tables            = array();
		$this->show_create                = array();
		$this->candidate_rows             = array();
		$this->vector_rows                = array();
		$this->migration_rows             = array();
		$this->existing_row               = null;
		$this->count                      = 0;
		$this->insert_calls               = array();
		$this->update_calls               = array();
		$this->delete_calls               = array();
		$this->queries                    = array();
		$this->prepared_queries           = array();
	}

	/**
	 * Get charset collation.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	/**
	 * Record an insert call.
	 *
	 * @param string $table Table name.
	 * @param array<string, mixed> $data Row data.
	 * @param array<int, string> $formats Formats.
	 */
	public function insert( string $table, array $data, array $formats ) {
		$this->insert_calls[] = compact( 'table', 'data', 'formats' );

		return 1;
	}

	/**
	 * Record an update call.
	 *
	 * @param string $table Table name.
	 * @param array<string, mixed> $data Row data.
	 * @param array<string, mixed> $where Where data.
	 * @param array<int, string> $formats Formats.
	 * @param array<int, string> $where_formats Where formats.
	 */
	public function update( string $table, array $data, array $where, array $formats, array $where_formats ) {
		$this->update_calls[] = compact( 'table', 'data', 'where', 'formats', 'where_formats' );

		return 1;
	}

	/**
	 * Record a delete call.
	 *
	 * @param string $table Table name.
	 * @param array<string, mixed> $where Where data.
	 * @param array<int, string> $where_formats Where formats.
	 */
	public function delete( string $table, array $where, array $where_formats ) {
		$this->delete_calls[] = compact( 'table', 'where', 'where_formats' );

		return 1;
	}

	/**
	 * Prepare SQL using simple test-only placeholder replacement.
	 *
	 * @param string              $query SQL query.
	 * @param mixed|array<int,mixed> ...$args Arguments.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		foreach ( $args as $arg ) {
			$replacement = is_int( $arg ) || is_float( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
			$query       = preg_replace( '/%[sd]/', $replacement, $query, 1 );
		}

		$this->prepared_queries[] = $query;

		return $query;
	}

	/**
	 * Execute a query.
	 *
	 * @param string $query SQL query.
	 */
	public function query( string $query ) {
		$this->queries[] = $query;

		return false === $this->last_error ? false : 1;
	}

	/**
	 * Get a scalar value.
	 *
	 * @param string $query SQL query.
	 */
	public function get_var( string $query ) {
		$this->queries[] = $query;

		if ( str_contains( $query, 'SELECT VERSION()' ) ) {
			return $this->server_version;
		}

		if ( str_contains( $query, 'VEC_DISTANCE_COSINE' ) ) {
			return $this->vector_functions_available ? '0' : null;
		}

		if ( str_contains( $query, 'SHOW TABLES LIKE' ) ) {
			foreach ( $this->existing_tables as $table ) {
				if ( str_contains( $query, (string) $table ) ) {
					return $table;
				}
			}

			return null;
		}

		if ( str_contains( $query, 'COUNT(*)' ) ) {
			return $this->count;
		}

		return null;
	}

	/**
	 * Get one row.
	 *
	 * @param string $query SQL query.
	 * @param string $output Output mode.
	 */
	public function get_row( string $query, string $output ) {
		$this->queries[] = $query;

		if ( str_contains( $query, 'SHOW CREATE TABLE' ) ) {
			foreach ( $this->show_create as $table => $sql ) {
				if ( str_contains( $query, (string) $table ) ) {
					return ARRAY_N === $output ? array( $table, $sql ) : array( 'Table' => $table, 'Create Table' => $sql );
				}
			}

			return null;
		}

		if ( str_contains( $query, 'WHERE post_id' ) && str_contains( $query, 'embedding_model' ) ) {
			return $this->existing_row;
		}

		return null;
	}

	/**
	 * Get result rows.
	 *
	 * @param string $query SQL query.
	 * @param string $output Output mode.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( string $query, string $output ): array {
		$this->queries[] = $query;
		unset( $output );

		if ( str_contains( $query, 'VEC_DISTANCE_COSINE' ) ) {
			return $this->vector_rows;
		}

		if ( str_contains( $query, 'dimensions =' ) ) {
			return $this->migration_rows;
		}

		return $this->candidate_rows;
	}

	/**
	 * Suppress errors.
	 *
	 * @param bool $suppress Whether to suppress.
	 */
	public function suppress_errors( bool $suppress ): bool {
		unset( $suppress );

		return false;
	}
}

$GLOBALS['wpdb'] = new WPNVS_Test_WPDB();

function wpnvs_test_reset(): void {
	global $wpdb;

	$wpdb->reset();
	$GLOBALS['wpnvs_test_options']       = array();
	$GLOBALS['wpnvs_test_transients']    = array();
	$GLOBALS['wpnvs_test_posts']         = array();
	$GLOBALS['wpnvs_test_meta']          = array();
	$GLOBALS['wpnvs_test_hooks']         = array();
	$GLOBALS['wpnvs_test_filters']       = array();
	$GLOBALS['wpnvs_test_scheduled']     = array();
	$GLOBALS['wpnvs_test_actions']       = array();
	$GLOBALS['wpnvs_test_deleted_meta']  = array();
	$GLOBALS['wpnvs_test_files']         = array();
	$GLOBALS['wpnvs_test_mime_types']    = array();
	$GLOBALS['wpnvs_test_http_response'] = null;
	$GLOBALS['wpnvs_test_http_requests'] = array();
	$GLOBALS['wpnvs_test_rest_routes']   = array();
	$GLOBALS['wpnvs_test_scripts']       = array();
	$GLOBALS['wpnvs_test_styles']        = array();
	$GLOBALS['wpnvs_test_blocks']        = array();
	$GLOBALS['wpnvs_test_admin']         = false;
	$GLOBALS['wpnvs_test_ajax']          = false;
	$GLOBALS['wpnvs_test_unique_id']     = 0;
	$GLOBALS['wpnvs_test_settings_errors'] = array();
	$GLOBALS['wpnvs_test_query_posts']   = array();
	$GLOBALS['wpnvs_test_last_query_args'] = array();
	$GLOBALS['wpnvs_test_cli_items']     = array();
	WP_CLI::$commands                    = array();
	WP_CLI::$logs                        = array();
	WP_CLI::$warnings                    = array();
}

function wpnvs_test_register_post( int $post_id, array $data ): WP_Post {
	$post = new WP_Post(
		array_merge(
			array(
				'ID'           => $post_id,
				'post_title'   => 'Post ' . $post_id,
				'post_excerpt' => '',
				'post_content' => '',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			),
			$data
		)
	);

	$GLOBALS['wpnvs_test_posts'][ $post_id ] = $post;

	return $post;
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );

	return $text;
}

function esc_html__( string $text, string $domain = '' ): string {
	return __( $text, $domain );
}

function esc_html_e( string $text, string $domain = '' ): void {
	echo esc_html__( $text, $domain );
}

function esc_attr__( string $text, string $domain = '' ): string {
	return __( $text, $domain );
}

function esc_html_x( string $text, string $context = '', string $domain = '' ): string {
	unset( $context );

	return __( $text, $domain );
}

function esc_html( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_textarea( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function checked( bool $checked, bool $current = true ): void {
	if ( $checked === $current ) {
		echo 'checked="checked"';
	}
}

function selected( string $selected, string $current ): void {
	if ( $selected === $current ) {
		echo 'selected="selected"';
	}
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function sanitize_key( $key ): string {
	$key = strtolower( (string) $key );

	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_text_field( $value ): string {
	$value = wp_strip_all_tags( (string) $value, true );
	$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

	return is_string( $value ) ? trim( $value ) : '';
}

function current_time( string $type, bool $gmt = false ): string {
	unset( $type, $gmt );

	return '2026-05-25 00:00:00';
}

function get_option( string $name, $default = false ) {
	return $GLOBALS['wpnvs_test_options'][ $name ] ?? $default;
}

function add_option( string $name, $value, string $deprecated = '', bool $autoload = true ): bool {
	unset( $deprecated, $autoload );
	$GLOBALS['wpnvs_test_options'][ $name ] = $value;

	return true;
}

function update_option( string $name, $value, bool $autoload = true ): bool {
	unset( $autoload );
	$GLOBALS['wpnvs_test_options'][ $name ] = $value;

	return true;
}

function get_transient( string $key ) {
	return $GLOBALS['wpnvs_test_transients'][ $key ] ?? false;
}

function set_transient( string $key, $value, int $expiration = 0 ): bool {
	unset( $expiration );
	$GLOBALS['wpnvs_test_transients'][ $key ] = $value;

	return true;
}

function delete_transient( string $key ): bool {
	unset( $GLOBALS['wpnvs_test_transients'][ $key ] );

	return true;
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function wp_unslash( $value ) {
	return $value;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function get_post( int $post_id ) {
	return $GLOBALS['wpnvs_test_posts'][ $post_id ] ?? null;
}

function get_the_title( $post ): string {
	if ( is_numeric( $post ) ) {
		$post = get_post( (int) $post );
	}

	return $post instanceof WP_Post ? (string) $post->post_title : '';
}

function get_permalink( WP_Post $post ): string {
	return 'https://example.test/?p=' . (int) $post->ID;
}

function get_the_post_thumbnail_url( WP_Post $post, string $size ) {
	unset( $size );

	return 'https://example.test/thumb-' . (int) $post->ID . '.jpg';
}

function wp_get_attachment_url( int $attachment_id ): string {
	return 'https://example.test/media-' . $attachment_id . '.jpg';
}

function wp_get_attachment_image_url( int $attachment_id, string $size ): string {
	unset( $size );

	return 'https://example.test/media-thumb-' . $attachment_id . '.jpg';
}

function get_post_meta( int $post_id, string $key, bool $single = false ) {
	unset( $single );

	return $GLOBALS['wpnvs_test_meta'][ $post_id ][ $key ] ?? '';
}

function update_post_meta( int $post_id, string $key, $value ): bool {
	$GLOBALS['wpnvs_test_meta'][ $post_id ][ $key ] = $value;

	return true;
}

function delete_post_meta( int $post_id, string $key ): bool {
	unset( $GLOBALS['wpnvs_test_meta'][ $post_id ][ $key ] );
	$GLOBALS['wpnvs_test_deleted_meta'][] = array( $post_id, $key );

	return true;
}

function get_post_mime_type( $post ) {
	if ( is_numeric( $post ) ) {
		$post = get_post( (int) $post );
	}

	return $GLOBALS['wpnvs_test_mime_types'][ (int) $post->ID ] ?? ( $post->post_mime_type ?? '' );
}

function get_attached_file( int $attachment_id ) {
	return $GLOBALS['wpnvs_test_files'][ $attachment_id ] ?? '';
}

function strip_shortcodes( string $text ): string {
	return $text;
}

function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
	unset( $remove_breaks );

	return strip_tags( $text );
}

function get_bloginfo( string $show ): string {
	unset( $show );

	return 'UTF-8';
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['wpnvs_test_hooks'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
}

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['wpnvs_test_filters'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
}

function do_action( string $hook, ...$args ): void {
	$GLOBALS['wpnvs_test_actions'][] = compact( 'hook', 'args' );
}

function wp_next_scheduled( string $hook, array $args = array() ) {
	foreach ( $GLOBALS['wpnvs_test_scheduled'] as $event ) {
		if ( $event['hook'] === $hook && $event['args'] === $args ) {
			return $event['timestamp'];
		}
	}

	return false;
}

function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ): bool {
	$GLOBALS['wpnvs_test_scheduled'][] = compact( 'timestamp', 'hook', 'args' );

	return true;
}

function wp_clear_scheduled_hook( string $hook, array $args = array() ): int {
	$removed = 0;
	foreach ( $GLOBALS['wpnvs_test_scheduled'] as $index => $event ) {
		if ( $event['hook'] === $hook && $event['args'] === $args ) {
			unset( $GLOBALS['wpnvs_test_scheduled'][ $index ] );
			$removed++;
		}
	}

	$GLOBALS['wpnvs_test_scheduled'] = array_values( $GLOBALS['wpnvs_test_scheduled'] );

	return $removed;
}

function wp_is_post_revision( int $post_id ) {
	return false;
}

function wp_is_post_autosave( int $post_id ) {
	return false;
}

function is_admin(): bool {
	return (bool) $GLOBALS['wpnvs_test_admin'];
}

function wp_doing_ajax(): bool {
	return (bool) $GLOBALS['wpnvs_test_ajax'];
}

function wp_unique_id( string $prefix = '' ): string {
	$GLOBALS['wpnvs_test_unique_id']++;

	return $prefix . $GLOBALS['wpnvs_test_unique_id'];
}

function wp_enqueue_script( string $handle, string $src, array $deps = array(), string $ver = '', bool $in_footer = false ): void {
	$GLOBALS['wpnvs_test_scripts'][] = compact( 'handle', 'src', 'deps', 'ver', 'in_footer' );
}

function wp_enqueue_style( string $handle, string $src, array $deps = array(), string $ver = '' ): void {
	$GLOBALS['wpnvs_test_styles'][] = compact( 'handle', 'src', 'deps', 'ver' );
}

function register_block_type( string $path ): void {
	$GLOBALS['wpnvs_test_blocks'][] = $path;
}

function register_rest_route( string $namespace, string $route, array $args ): void {
	$GLOBALS['wpnvs_test_rest_routes'][] = compact( 'namespace', 'route', 'args' );
}

function rest_ensure_response( $data ): WP_REST_Response {
	return $data instanceof WP_REST_Response ? $data : new WP_REST_Response( $data );
}

function __return_true(): bool {
	return true;
}

function register_setting( string $group, string $name, array $args ): void {
	$GLOBALS['wpnvs_test_registered_settings'][] = compact( 'group', 'name', 'args' );
}

function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
	$GLOBALS['wpnvs_test_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
}

function get_post_types( array $args = array(), string $output = 'names' ): array {
	unset( $args );

	if ( 'objects' === $output ) {
		return array(
			'post'       => (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ) ),
			'page'       => (object) array( 'labels' => (object) array( 'singular_name' => 'Page' ) ),
			'attachment' => (object) array( 'labels' => (object) array( 'singular_name' => 'Media' ) ),
		);
	}

	return array( 'post', 'page', 'attachment' );
}

function wp_remote_post( string $url, array $args ) {
	$GLOBALS['wpnvs_test_http_requests'][] = compact( 'url', 'args' );

	return $GLOBALS['wpnvs_test_http_response'] ?? array(
		'response' => array( 'code' => 200 ),
		'body'     => '{}',
	);
}

function wp_remote_retrieve_response_code( array $response ): int {
	return (int) ( $response['response']['code'] ?? 0 );
}

function wp_remote_retrieve_body( array $response ): string {
	return (string) ( $response['body'] ?? '' );
}

require_once __DIR__ . '/../../includes/class-settings.php';
require_once __DIR__ . '/../../includes/class-database.php';
require_once __DIR__ . '/../../includes/class-database-maria.php';
require_once __DIR__ . '/../../includes/class-openai-client.php';
require_once __DIR__ . '/../../includes/class-text-normalizer.php';
require_once __DIR__ . '/../../includes/class-media-describer.php';
require_once __DIR__ . '/../../includes/class-indexer.php';
require_once __DIR__ . '/../../includes/class-search-service.php';
require_once __DIR__ . '/../../includes/class-search-service-maria.php';
require_once __DIR__ . '/../../includes/class-rest-controller.php';
require_once __DIR__ . '/../../includes/class-blocks.php';
require_once __DIR__ . '/../../includes/class-search-form.php';
require_once __DIR__ . '/../../includes/class-admin.php';
require_once __DIR__ . '/../../includes/class-plugin.php';
require_once __DIR__ . '/../../includes/class-cli-command.php';
