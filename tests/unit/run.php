<?php
/**
 * Lightweight unit test runner.
 *
 * @package WP_Native_Vector_Search
 */

require_once __DIR__ . '/bootstrap.php';

$tests = array();

/**
 * Register a test case.
 *
 * @param string   $name Test name.
 * @param callable $test Test callback.
 */
function test_case( string $name, callable $test ): void {
	global $tests;

	$tests[] = array( $name, $test );
}

/**
 * Assert strict equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual Actual value.
 * @param string $message Assertion message.
 */
function assert_same( $expected, $actual, string $message = '' ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
		);
	}
}

/**
 * Assert truthy value.
 *
 * @param mixed  $actual Actual value.
 * @param string $message Assertion message.
 */
function assert_true( $actual, string $message = '' ): void {
	if ( true !== $actual ) {
		throw new RuntimeException( $message . "\nActual: " . var_export( $actual, true ) );
	}
}

/**
 * Assert false value.
 *
 * @param mixed  $actual Actual value.
 * @param string $message Assertion message.
 */
function assert_false( $actual, string $message = '' ): void {
	if ( false !== $actual ) {
		throw new RuntimeException( $message . "\nActual: " . var_export( $actual, true ) );
	}
}

/**
 * Assert substring.
 *
 * @param string $needle Expected substring.
 * @param string $haystack Input text.
 * @param string $message Assertion message.
 */
function assert_contains_string( string $needle, string $haystack, string $message = '' ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . "\nMissing: {$needle}\nIn: {$haystack}" );
	}
}

require_once __DIR__ . '/test-database.php';
require_once __DIR__ . '/test-database-maria.php';
require_once __DIR__ . '/test-search-service.php';
require_once __DIR__ . '/test-search-service-maria.php';
require_once __DIR__ . '/test-settings.php';
require_once __DIR__ . '/test-text-normalizer.php';
require_once __DIR__ . '/test-openai-client.php';
require_once __DIR__ . '/test-media-describer.php';
require_once __DIR__ . '/test-indexer.php';
require_once __DIR__ . '/test-rest-controller.php';
require_once __DIR__ . '/test-blocks.php';
require_once __DIR__ . '/test-search-form.php';
require_once __DIR__ . '/test-admin.php';
require_once __DIR__ . '/test-plugin.php';
require_once __DIR__ . '/test-cli-command.php';
require_once __DIR__ . '/test-uninstall.php';

$passed = 0;
$failed = 0;

foreach ( $tests as $case ) {
	$name = $case[0];
	$test = $case[1];

	wpnvs_test_reset();

	try {
		$test();
		$passed++;
		echo "PASS {$name}\n";
	} catch ( Throwable $throwable ) {
		$failed++;
		echo "FAIL {$name}\n";
		echo $throwable->getMessage() . "\n";
	}
}

echo sprintf( "Tests: %d passed, %d failed\n", $passed, $failed );

exit( 0 === $failed ? 0 : 1 );
