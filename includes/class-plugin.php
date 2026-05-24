<?php
/**
 * Plugin bootstrap.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin services to WordPress hooks.
 */
final class Plugin {
	/**
	 * Register plugin services.
	 */
	public function register(): void {
		$settings        = new Settings();
		$maria_database  = new Database_Maria();
		$database        = $maria_database->is_mariadb_server() ? $maria_database : new Database();
		$openai_client   = new OpenAI_Client( $settings );
		$media_describer = new Media_Describer( $settings, $openai_client );
		$indexer         = new Indexer( $settings, $database, $openai_client );
		$search_service  = $database instanceof Database_Maria
			? new Search_Service_Maria( $settings, $database, $openai_client )
			: new Search_Service( $settings, $database, $openai_client );

		$settings->register();
		$media_describer->register();
		$indexer->register();
		( new REST_Controller( $search_service ) )->register();
		( new Blocks() )->register();
		( new Search_Form( $settings ) )->register();

		if ( is_admin() ) {
			( new Admin( $settings, $database ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			( new CLI_Command( $settings, $indexer, $media_describer, $database ) )->register();
		}
	}
}
