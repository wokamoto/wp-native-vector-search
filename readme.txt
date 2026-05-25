=== WP Native Vector Search ===
Contributors: local-development
Tags: search, semantic search, vector search, openai, media
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Semantic search for WordPress posts and image media using OpenAI embeddings, stored locally in the WordPress database.

Important: This plugin is intended for technical evaluation. The default PHP cosine similarity search is simple but can be slow on large datasets. MariaDB 11.7+ installations can optionally use MariaDB Vector for database-side vector search.

== Description ==

WP Native Vector Search stores OpenAI embeddings in a custom WordPress database table and provides semantic search for posts, pages, and image media.

The plugin does not require an external vector database. Embeddings are stored in MySQL or MariaDB as JSON arrays and cosine similarity can be calculated in PHP.

When the site runs on MariaDB 11.7 or later, the plugin can also mirror supported embedding dimensions into MariaDB Vector tables and run similarity search in MariaDB. The JSON embedding table remains the source of truth and the fallback path.

Post embeddings are stored regardless of post status. Search checks the current WordPress status at query time and only returns publicly searchable posts. Media embeddings are searchable regardless of attachment status when media search is enabled.

= Main features =

* Generate text embeddings with OpenAI.
* Store vectors in `wp_vector_search_embeddings`.
* Queue post indexing when saved or when status changes.
* Generate natural-language descriptions for uploaded images.
* Store generated image descriptions in `wp_postmeta`.
* Index image descriptions for semantic media search.
* Search posts and media through `/wp-json/vector-search/v1/search`.
* Hide weak matches below a configurable minimum score.
* Add a Gutenberg block named `Vector Search Box`.
* Provide WP-CLI commands for bulk indexing.
* Optionally use MariaDB Vector for database-side vector search.
* Choose PHP, MariaDB Vector, or automatic search backend selection.
* Provide WP-CLI commands for MariaDB Vector diagnostics, table creation, and migration.
* Show generated image descriptions in the Media Library admin UI.
* Optionally replace standard WordPress search forms with vector search.

= Image media search =

The plugin can describe uploaded images with an OpenAI vision model, then turn that generated description into a text embedding.

The generated description includes:

* What appears in the image.
* Likely usage.
* Readable text.
* Colors.
* Composition.
* Mood.
* Related search terms.

This enables natural-language searches such as "CMS comparison illustration", "blue logo", or "WordPress admin screenshot".

Media is searchable regardless of attachment status or parent post status when media search is enabled.

== Installation ==

1. Upload the `wp-native-vector-search` folder to `/wp-content/plugins/`.
2. Activate `WP Native Vector Search` from the Plugins screen.
3. Open `Settings > Vector Search`.
4. Enter an OpenAI API key.
5. Configure the embedding model, vision model, target post types, media search, and automatic indexing.
6. Use WP-CLI to index existing content.

The API key can also be provided as a PHP constant:

`
define( 'WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY', 'sk-...' );
`

When the constant is defined, it takes precedence over the saved setting.

== Frequently Asked Questions ==

= Does this plugin require an external vector database? =

No. Embeddings are stored in a custom WordPress database table. PHP cosine similarity search works without an external vector database.

= Can this use MariaDB Vector for search? =

Yes. MariaDB 11.7+ can use MariaDB Vector for database-side vector search. MariaDB 11.8 is recommended.

The plugin creates dimension-specific vector tables:

* `wp_vector_search_embeddings_vec_1536`
* `wp_vector_search_embeddings_vec_3072`

Supported dimensions map to the current OpenAI embedding models:

* 1536 dimensions: `text-embedding-3-small` and `text-embedding-ada-002`
* 3072 dimensions: `text-embedding-3-large`

JSON embeddings stay in `wp_vector_search_embeddings` as the source of truth. New indexing writes to the matching MariaDB Vector table when it is available.

= How do I enable MariaDB Vector search? =

Use WP-CLI to inspect support, create vector tables, and migrate existing embeddings:

`
wp vector-search vector-status
wp vector-search create-vector-tables
wp vector-search migrate-vectors --dimension=1536
`

Use `--dimension=3072` when the selected embedding model uses 3072 dimensions.

Then open `Settings > Vector Search` and set `Search Backend` to `MariaDB Vector` or `Auto`.

Backend options:

* `PHP`: use JSON embeddings and PHP cosine similarity.
* `MariaDB Vector`: require a ready MariaDB Vector table for the selected model.
* `Auto`: use MariaDB Vector when available and fall back to PHP otherwise.

= Which OpenAI models are used by default? =

The default embedding model is `text-embedding-3-small`.

The default vision model for image descriptions is `gpt-4.1-mini`.

= Does the plugin directly embed image files? =

No. The initial implementation generates a natural-language description of each image, then embeds that text. This keeps the implementation compatible with text embeddings while still enabling natural-language media search.

= Where are image descriptions stored? =

Generated image descriptions are stored in `wp_postmeta`.

Meta keys:

* `_wp_native_vector_search_image_description`
* `_wp_native_vector_search_image_description_model`
* `_wp_native_vector_search_image_description_hash`
* `_wp_native_vector_search_image_description_generated_at`
* `_wp_native_vector_search_image_description_error`

= Which image types are supported? =

The initial implementation supports:

* JPEG
* PNG
* WebP
* GIF

Images over 10 MB are skipped.

= How do I index existing posts? =

Use WP-CLI:

`
wp vector-search index --post_type=post --limit=100
`

Use `--post_type=attachment` to run the media indexing flow from the same command.

Options:

* `--post_type=post|attachment`
* `--limit=100`
* `--force`
* `--dry-run`

= How do I run queued post indexing jobs? =

Use WP-CLI:

`
wp vector-search run-queue
`

Publish and save requests only schedule a WordPress cron event. OpenAI API calls are not made during the editor save request.

Uploaded or edited images also schedule WordPress cron events for image description generation. Once the description is available, another cron event stores the media embedding.

= How do I generate descriptions for existing media? =

Use WP-CLI:

`
wp vector-search describe-media --limit=100
`

Options:

* `--limit=100`
* `--force`
* `--dry-run`

= How do I index media for search? =

Use WP-CLI:

`
wp vector-search index-media --limit=100
`

Options:

* `--limit=100`
* `--force`
* `--dry-run`

If an image description has not been generated yet, `index-media` generates it before creating the embedding.

= Which WP-CLI commands are available for MariaDB Vector? =

MariaDB Vector commands are registered only when WordPress is connected to MariaDB:

* `vector-status --dimension=1536 --refresh`
* `create-vector-tables --dimension=1536`
* `migrate-vectors --dimension=1536 --batch=100`

When `--dimension` is omitted, `vector-status` and `create-vector-tables` process all supported dimensions. `migrate-vectors` uses the dimension for the currently selected embedding model.

= What does the REST API return? =

The endpoint applies a simple IP-based rate limit and caches normalized query embeddings for five minutes to reduce accidental API overuse.

Endpoint:

`
/wp-json/vector-search/v1/search
`

Request:

`
{
  "query": "WordPress security",
  "limit": 10
}
`

Post results include:

* `type: post`
* `post_id`
* `title`
* `description`
* `url`
* `post_type`
* `thumbnail_url`
* `score`

Results below the configured minimum score are omitted.

Media results include:

* `type: media`
* `attachment_id`
* `post_id`
* `title`
* `description`
* `url`
* `post_type`
* `thumbnail_url`
* `media_url`
* `score`

= Where can I view generated image descriptions in wp-admin? =

Open `Media > Library`.

The list table includes a `Vector Description` column. Attachment details and the media modal show the generated description, model, timestamp, and last error if present.

= Can this replace the standard WordPress search box? =

Yes. Enable `Replace WordPress Search` in `Settings > Vector Search`.

When enabled, the plugin replaces forms rendered by `get_search_form()` and Core Search blocks (`core/search`) with the vector search form.

= Is this production-ready? =

This version is intended for local development and experimentation. Uploaded or edited images are described through WordPress cron, while WP-CLI media indexing still runs in the current command. Large media libraries or production traffic should add batching, caching, and more selective retrieval.

== Screenshots ==

1. Vector Search settings screen.
2. Media Library with generated Vector Description.
3. Vector Search Box block on the frontend.

== Changelog ==

= 0.2.1 =

* Limited search result links to titles and thumbnails so descriptions remain plain text.

= 0.2.0 =

* Added optional MariaDB Vector search backend for MariaDB 11.7+.
* Added dimension-specific MariaDB Vector tables for 1536 and 3072 dimension embeddings.
* Added PHP, MariaDB Vector, and Auto search backend settings.
* Added MariaDB Vector diagnostics to the settings screen.
* Added WP-CLI commands for vector status, table creation, and migration.
* Added dual-write indexing to mirror supported embeddings into MariaDB Vector tables.
* Added unit tests for the core plugin classes.

= 0.1.2 =

* Added shared embedding text normalization and short-lived query embedding caching.

= 0.1.1 =

* Added descriptions to post and media search results.
* Added featured image thumbnails to post and page search results.

= 0.1.0 =

* Initial plugin scaffold.
* Added custom embeddings database table.
* Added OpenAI embedding client.
* Added post indexing on publish/update.
* Added WP-CLI post indexing command.
* Added REST API search endpoint.
* Added Gutenberg search block.
* Added settings page.
* Added OpenAI vision-based image description generation.
* Added image description postmeta storage.
* Added Media Library admin display for generated descriptions.
* Added WP-CLI media description command.
* Added WP-CLI media indexing command.
* Added media search results with `type`, `attachment_id`, `thumbnail_url`, and `media_url`.
* Added optional standard WordPress search form replacement.

== Upgrade Notice ==

= 0.2.1 =

Search result descriptions are no longer part of the clickable link area.

= 0.2.0 =

MariaDB Vector support is optional. Existing JSON embeddings continue to work with the PHP backend. Run `wp vector-search vector-status`, create vector tables, and migrate existing embeddings before selecting the MariaDB Vector backend.

= 0.1.2 =

Search query embeddings are normalized consistently with indexed content and cached briefly.

= 0.1.1 =

Search results now include descriptions and post/page featured image thumbnails.

= 0.1.0 =

Initial development release.
