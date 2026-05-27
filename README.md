# WP Native Vector Search

> This plugin is intended for technical evaluation. Using it on sites with a large amount of data may cause severe performance degradation. If you want to use it on a production site, replace the cosine similarity search currently performed in PHP with a dedicated Vector DB.

WP Native Vector Search is a local-first WordPress plugin that stores OpenAI embeddings in a WordPress database table and provides semantic search for posts, pages, and image media.

It does not require an external vector database.

## Features

- Generate text embeddings with OpenAI
- Store vectors in a custom WordPress database table
- Queue post indexing when saved or when status changes
- Generate natural-language descriptions for uploaded images
- Store generated image descriptions in `wp_postmeta`
- Index image descriptions for semantic media search
- Search posts and media through a custom REST API endpoint
- Provide a Gutenberg search block
- Provide WP-CLI commands for bulk indexing
- Provide a settings screen under `Settings > Vector Search`
- Show generated image descriptions in the Media Library admin UI

## Requirements

- WordPress 6.5 or later
- PHP 8.0 or later
- OpenAI API key
- WordPress HTTP API access to `api.openai.com`

## OpenAI Models

Default embedding model:

- `text-embedding-3-small`

Default vision model:

- `gpt-4.1-mini`

Both model names can be changed from the plugin settings screen.

## Settings

Open `Settings > Vector Search`.

Available settings:

- OpenAI API Key
- Embedding model
- Vision model
- Target post types
- Maximum characters for embedding input
- Minimum similarity score
- Automatic indexing on save/status change
- Media search inclusion
- Replace standard WordPress search forms with vector search

The API key can also be provided with a constant:

```php
define( 'WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY', 'sk-...' );
```

When the constant is defined, it takes precedence over the saved setting.

## Database Table

The plugin creates this table on activation:

```text
wp_vector_search_embeddings
```

Columns:

- `id`
- `post_id`
- `post_type`
- `post_status`
- `content_hash`
- `embedding`
- `embedding_model`
- `dimensions`
- `created_at`
- `updated_at`

Embeddings are stored as JSON arrays.

For media search, image attachment embeddings are stored in the same table:

- `post_id`: attachment ID
- `post_type`: `attachment`
- `post_status`: the attachment's current status

Post embeddings are stored regardless of post status. Search checks the current WordPress post status at query time and only returns publicly searchable posts. Media embeddings are searchable regardless of attachment status when media search is enabled.

## Post Indexing

Automatic post indexing queues work on:

- `save_post`
- `transition_post_status`
- `deleted_post`

Publish and save requests do not call OpenAI directly. They schedule a single WordPress cron event for the post, avoiding duplicate API calls from overlapping save/status hooks.

The embedding input includes:

- Post title
- Post excerpt
- Post content

Only configured post types are indexed.

Configured post types are indexed regardless of status. Non-published posts remain in the vector table, but are excluded from search until their current status is searchable.

Media embeddings are stored regardless of attachment status or parent post status. The settings screen controls whether media embeddings are included in search results.

## Image Description Generation

The plugin can generate searchable descriptions for image media.

Supported MIME types:

- `image/jpeg`
- `image/png`
- `image/webp`
- `image/gif`

When an image is uploaded or edited, the plugin schedules a WordPress cron event that sends the local image file to OpenAI Responses API as a base64 data URL and asks the vision model to generate a Japanese description. Once the description is available, a second cron event stores the media embedding.

The generated description includes:

- What appears in the image
- Likely usage
- Readable text
- Colors
- Composition
- Mood
- Related search terms

Generated metadata is stored in `wp_postmeta`.

Post meta keys:

- `_wp_native_vector_search_image_description`
- `_wp_native_vector_search_image_description_model`
- `_wp_native_vector_search_image_description_hash`
- `_wp_native_vector_search_image_description_generated_at`
- `_wp_native_vector_search_image_description_error`

Images over 10 MB are skipped by the initial implementation.

## Media Indexing

Media indexing converts generated image descriptions into text embeddings.

The media embedding input includes:

- Attachment title
- Alt text
- Caption
- Attachment description
- Generated image description

This allows natural-language search queries such as:

- `CMS の比較表`
- `青い背景のロゴ`
- `管理画面のスクリーンショット`
- `WordPress と Headless CMS の図解`

## Search Backend

The default backend is PHP fallback, which reads JSON embeddings and calculates cosine similarity in PHP.

MariaDB 11.7+ installations can optionally use MariaDB Vector:

- `php`: always use the JSON/PHP fallback path.
- `mariadb_vector`: require a ready MariaDB Vector table for the selected embedding model.
- `auto`: use MariaDB Vector when available and fall back to PHP otherwise.

Vector tables are dimension-specific:

- `wp_vector_search_embeddings_vec_1536`
- `wp_vector_search_embeddings_vec_3072`

The JSON embeddings table remains the compatibility source of truth.

## WP-CLI Commands

### Index Posts

```sh
wp vector-search index
```

Options:

- `--post_type=post|attachment`
- `--limit=100`
- `--force`
- `--dry-run`

Example:

```sh
wp vector-search index --post_type=post --limit=100
```

When `--post_type=attachment` is used, this command runs the media indexing flow. If an image description has not been generated yet, it is generated before creating the embedding.

### Run Queued Post Indexing

```sh
wp vector-search run-queue
```

Options:

- `--due-now`

By default this runs all queued vector search post indexing jobs. Use `--due-now` to run only jobs whose scheduled time has arrived.

### Describe Media

```sh
wp vector-search describe-media
```

Options:

- `--limit=100`
- `--force`
- `--dry-run`

Example:

```sh
wp vector-search describe-media --limit=100
```

### Index Media

```sh
wp vector-search index-media
```

Options:

- `--limit=100`
- `--force`
- `--dry-run`

Example:

```sh
wp vector-search index-media --limit=100
```

If an image description has not been generated yet, `index-media` generates it before creating the embedding.

### MariaDB Vector Operations

```sh
wp vector-search vector-status
wp vector-search create-vector-tables
wp vector-search migrate-vectors --dimension=1536
```

Options:

- `vector-status --dimension=1536 --refresh`
- `create-vector-tables --dimension=1536`
- `migrate-vectors --dimension=1536 --batch=100`

Create the vector tables first, then migrate existing JSON embeddings. New indexing writes to both JSON storage and the matching vector table when MariaDB Vector is available.

## Unit Tests

Run the lightweight unit test suite from the plugin directory:

```sh
php tests/unit/run.php
```

The tests use local WordPress and database stubs, so they do not require Composer, PHPUnit, WordPress core, OpenAI API access, or a running database.

## REST API

Endpoint:

```text
/wp-json/vector-search/v1/search
```

Methods:

- `GET`
- `POST`

Request:

```json
{
  "query": "WordPress セキュリティ",
  "limit": 10
}
```

The endpoint applies a simple IP-based rate limit and caches normalized query embeddings for five minutes to reduce accidental API overuse.

Post result:

```json
{
  "type": "post",
  "post_id": 1,
  "title": "WordPress Security",
  "description": "A practical guide to hardening WordPress login, permissions, and plugin updates.",
  "url": "https://example.com/wordpress-security",
  "post_type": "post",
  "thumbnail_url": "https://example.com/wp-content/uploads/wordpress-security-150x150.png",
  "score": 0.91
}
```

Media result:

```json
{
  "type": "media",
  "attachment_id": 10,
  "post_id": 10,
  "title": "cms-comparison",
  "description": "CMS comparison diagram with WordPress and headless CMS options.",
  "url": "https://example.com/wp-content/uploads/cms-comparison.png",
  "post_type": "attachment",
  "thumbnail_url": "https://example.com/wp-content/uploads/cms-comparison-150x150.png",
  "media_url": "https://example.com/wp-content/uploads/cms-comparison.png",
  "score": 0.88
}
```

## Gutenberg Block

Block name:

```text
wp-native-vector-search/search-box
```

Block title:

```text
Vector Search Box
```

The block provides:

- Search input
- REST API request
- Loading status
- Search result display with media thumbnails

## Standard Search Replacement

When `Replace WordPress Search` is enabled in the settings screen, the plugin replaces:

- Search forms rendered by `get_search_form()`
- Core Search blocks (`core/search`)

The replacement form uses the plugin REST API and returns vector search results without navigating to the normal WordPress search results page.

Replacement filters are registered only for frontend template requests. The plugin does not replace search markup in wp-admin, AJAX, REST API, or WP-CLI contexts.

## Admin UI

### Settings

Settings are available at:

```text
Settings > Vector Search
```

### Media Library

The Media Library list table includes a `Vector Description` column.

Attachment details and the media modal show:

- Generated image description
- Vision model
- Generated timestamp
- Last generation error, when present

The generated description is shown as read-only content.

## Search Implementation

Search uses cosine similarity.

Initial implementation details:

- Query text is embedded with OpenAI
- Matching embeddings are loaded from the WordPress database
- Cosine similarity is calculated in PHP
- Results are sorted by score descending
- Results below the configured minimum score are hidden

This is suitable for small to moderate local datasets. Large media libraries or high-traffic production sites should add batching, queues, caching, and more selective retrieval.

## Development Notes

This plugin is currently designed for local development and experimentation.

Important tradeoffs:

- No external vector database is used
- Embeddings are stored as JSON in MySQL
- Image search uses generated text descriptions, not direct image embeddings
- Uploaded or edited images are described through WordPress cron, while WP-CLI media indexing still runs in the current command
- Indexing many images can take time and consume API quota

## Uninstall

On uninstall, the plugin removes:

- `wp_native_vector_search_settings`
- `wp_vector_search_embeddings`
- `wp_vector_search_embeddings_vec_1536`
- `wp_vector_search_embeddings_vec_3072`

Generated image description postmeta is currently removed when each attachment is deleted.
