<?php
/**
 * Plugin Name: WP Native Vector Search
 * Description: Stores OpenAI embeddings in WordPress tables and exposes semantic post search.
 * Version: 0.2.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: wokamoto
 * Text Domain: wp-native-vector-search
 *
 * @package WP_Native_Vector_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_NATIVE_VECTOR_SEARCH_VERSION', '0.2.1' );
define( 'WP_NATIVE_VECTOR_SEARCH_FILE', __FILE__ );
define( 'WP_NATIVE_VECTOR_SEARCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_NATIVE_VECTOR_SEARCH_URL', plugin_dir_url( __FILE__ ) );

require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-settings.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-database.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-database-maria.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-openai-client.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-text-normalizer.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-media-describer.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-indexer.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-search-service.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-search-service-maria.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-rest-controller.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-admin.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-blocks.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-search-form.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-cli-command.php';
require_once WP_NATIVE_VECTOR_SEARCH_DIR . 'includes/class-plugin.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		$database = new WP_Native_Vector_Search\Database();
		$database->create_table();

		WP_Native_Vector_Search\Settings::add_defaults();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new WP_Native_Vector_Search\Plugin();
		$plugin->register();
	}
);
