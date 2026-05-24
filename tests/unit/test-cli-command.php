<?php
/**
 * CLI_Command unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\CLI_Command;
use WP_Native_Vector_Search\Database;
use WP_Native_Vector_Search\Database_Maria;
use WP_Native_Vector_Search\Indexer;
use WP_Native_Vector_Search\Media_Describer;
use WP_Native_Vector_Search\OpenAI_Client;
use WP_Native_Vector_Search\Settings;

test_case(
	'CLI_Command registers base commands for non-MariaDB databases',
	static function (): void {
		$settings = new Settings();
		$openai   = new OpenAI_Client( $settings );
		$database = new Database();
		$cli      = new CLI_Command(
			$settings,
			new Indexer( $settings, $database, $openai ),
			new Media_Describer( $settings, $openai ),
			$database
		);

		$cli->register();

		assert_true( isset( WP_CLI::$commands['vector-search'] ) );
		assert_true( isset( WP_CLI::$commands['vector-search index-media'] ) );
		assert_false( isset( WP_CLI::$commands['vector-search vector-status'] ) );
	}
);

test_case(
	'CLI_Command registers vector commands for MariaDB databases',
	static function (): void {
		$settings = new Settings();
		$openai   = new OpenAI_Client( $settings );
		$database = new Database_Maria();
		$cli      = new CLI_Command(
			$settings,
			new Indexer( $settings, $database, $openai ),
			new Media_Describer( $settings, $openai ),
			$database
		);

		$cli->register();

		assert_true( isset( WP_CLI::$commands['vector-search vector-status'] ) );
		assert_true( isset( WP_CLI::$commands['vector-search create-vector-tables'] ) );
		assert_true( isset( WP_CLI::$commands['vector-search migrate-vectors'] ) );
	}
);

