<?php
/**
 * Text_Normalizer unit tests.
 *
 * @package WP_Native_Vector_Search
 */

use WP_Native_Vector_Search\Text_Normalizer;

test_case(
	'Text_Normalizer strips markup, decodes entities, and collapses whitespace',
	static function (): void {
		$result = Text_Normalizer::normalize_for_embedding( "<p>CMS&nbsp;  search</p>\n\n<strong>test</strong>" );

		assert_same( 'CMS search test', $result );
	}
);

