<?php
/**
 * WP-CLI command.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

use WP_CLI;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp vector-search commands.
 */
final class CLI_Command {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Indexer service.
	 *
	 * @var Indexer
	 */
	private Indexer $indexer;

	/**
	 * Media describer service.
	 *
	 * @var Media_Describer
	 */
	private Media_Describer $media_describer;

	/**
	 * Constructor.
	 */
	public function __construct( Settings $settings, Indexer $indexer, Media_Describer $media_describer ) {
		$this->settings        = $settings;
		$this->indexer         = $indexer;
		$this->media_describer = $media_describer;
	}

	/**
	 * Register the command.
	 */
	public function register(): void {
		WP_CLI::add_command( 'vector-search', $this );
		WP_CLI::add_command( 'vector-search describe-media', array( $this, 'describe_media' ) );
		WP_CLI::add_command( 'vector-search index-media', array( $this, 'index_media' ) );
		WP_CLI::add_command( 'vector-search run-queue', array( $this, 'run_queue' ) );
	}

	/**
	 * Index existing posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<post_type>]
	 * : Post type to index. Defaults to configured post types.
	 *
	 * [--limit=<limit>]
	 * : Maximum number of posts to process. Defaults to 100.
	 *
	 * [--force]
	 * : Regenerate embeddings even if content hash is unchanged.
	 *
	 * [--dry-run]
	 * : Report posts that would be indexed without calling OpenAI or writing the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vector-search index --post_type=post --limit=100
	 *     wp vector-search index --post_type=attachment --limit=100
	 *
	 * @param array<int, string>   $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function index( array $args, array $assoc_args ): void {
		unset( $args );

		$configured_post_types = (array) $this->settings->get( 'post_types' );
		$post_type            = isset( $assoc_args['post_type'] ) ? sanitize_key( (string) $assoc_args['post_type'] ) : $configured_post_types;
		$limit                = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 100;
		$force                = isset( $assoc_args['force'] );
		$dry_run              = isset( $assoc_args['dry-run'] );

		if ( 'attachment' === $post_type ) {
			$this->index_media( array(), $assoc_args );
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => max( 1, $limit ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$counts = array(
			'indexed'      => 0,
			'updated'      => 0,
			'skipped'      => 0,
			'deleted'      => 0,
			'would_delete' => 0,
			'would_update' => 0,
			'would_index'  => 0,
			'failed'       => 0,
		);

		foreach ( $query->posts as $post_id ) {
			$result = $this->indexer->index_post( (int) $post_id, $force, $dry_run );
			if ( is_wp_error( $result ) ) {
				$counts['failed']++;
				WP_CLI::warning( sprintf( 'Post %d: %s', (int) $post_id, $result->get_error_message() ) );
				continue;
			}

			$status = (string) ( $result['status'] ?? 'skipped' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}

			WP_CLI::log( sprintf( 'Post %d: %s', (int) $post_id, $status ) );
		}

		WP_CLI::success(
			sprintf(
				'Done. indexed=%d updated=%d skipped=%d deleted=%d would_delete=%d would_update=%d would_index=%d failed=%d',
				$counts['indexed'],
				$counts['updated'],
				$counts['skipped'],
				$counts['deleted'],
				$counts['would_delete'],
				$counts['would_update'],
				$counts['would_index'],
				$counts['failed']
			)
		);
	}

	/**
	 * Generate descriptions for existing image attachments.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of attachments to process. Defaults to 100.
	 *
	 * [--force]
	 * : Regenerate descriptions even if the file hash and model are unchanged.
	 *
	 * [--dry-run]
	 * : Report images that would be described without calling OpenAI or writing postmeta.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vector-search describe-media --limit=100
	 *
	 * @param array<int, string>   $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function describe_media( array $args, array $assoc_args ): void {
		unset( $args );

		$limit   = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 100;
		$force   = isset( $assoc_args['force'] );
		$dry_run = isset( $assoc_args['dry-run'] );

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => $this->get_indexable_attachment_statuses(),
				'post_mime_type'         => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ),
				'posts_per_page'         => max( 1, $limit ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$counts = array(
			'described'       => 0,
			'skipped'         => 0,
			'would_describe'  => 0,
			'failed'          => 0,
		);

		foreach ( $query->posts as $attachment_id ) {
			$result = $this->media_describer->describe_attachment( (int) $attachment_id, $force, $dry_run );
			if ( is_wp_error( $result ) ) {
				$counts['failed']++;
				WP_CLI::warning( sprintf( 'Attachment %d: %s', (int) $attachment_id, $result->get_error_message() ) );
				continue;
			}

			$status = (string) ( $result['status'] ?? 'skipped' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}

			WP_CLI::log( sprintf( 'Attachment %d: %s', (int) $attachment_id, $status ) );
		}

		WP_CLI::success(
			sprintf(
				'Done. described=%d skipped=%d would_describe=%d failed=%d',
				$counts['described'],
				$counts['skipped'],
				$counts['would_describe'],
				$counts['failed']
			)
		);
	}

	/**
	 * Generate descriptions and index existing image attachments.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of attachments to process. Defaults to 100.
	 *
	 * [--force]
	 * : Regenerate descriptions and embeddings even if unchanged.
	 *
	 * [--dry-run]
	 * : Report images that would be indexed without calling OpenAI or writing the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vector-search index-media --limit=100
	 *
	 * @param array<int, string>   $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function index_media( array $args, array $assoc_args ): void {
		unset( $args );

		$limit   = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 100;
		$force   = isset( $assoc_args['force'] );
		$dry_run = isset( $assoc_args['dry-run'] );

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => $this->get_indexable_attachment_statuses(),
				'post_mime_type'         => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ),
				'posts_per_page'         => max( 1, $limit ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$counts = array(
			'indexed'      => 0,
			'updated'      => 0,
			'skipped'      => 0,
			'deleted'      => 0,
			'would_delete' => 0,
			'would_update' => 0,
			'would_index'  => 0,
			'failed'       => 0,
		);

		foreach ( $query->posts as $attachment_id ) {
			if ( $dry_run ) {
				$describe_result = $this->media_describer->describe_attachment( (int) $attachment_id, $force, true );
				$index_result    = $this->indexer->index_media( (int) $attachment_id, $force, true );
			} else {
				$describe_result = $this->media_describer->describe_attachment( (int) $attachment_id, $force, false );
				if ( is_wp_error( $describe_result ) ) {
					$counts['failed']++;
					WP_CLI::warning( sprintf( 'Attachment %d description: %s', (int) $attachment_id, $describe_result->get_error_message() ) );
					continue;
				}

				$index_result = $this->indexer->index_media( (int) $attachment_id, $force, false );
			}

			if ( is_wp_error( $describe_result ) ) {
				$counts['failed']++;
				WP_CLI::warning( sprintf( 'Attachment %d description: %s', (int) $attachment_id, $describe_result->get_error_message() ) );
				continue;
			}

			if ( is_wp_error( $index_result ) ) {
				$counts['failed']++;
				WP_CLI::warning( sprintf( 'Attachment %d index: %s', (int) $attachment_id, $index_result->get_error_message() ) );
				continue;
			}

			$status = (string) ( $index_result['status'] ?? 'skipped' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}

			$describe_status = (string) ( $describe_result['status'] ?? 'skipped' );
			WP_CLI::log( sprintf( 'Attachment %d: description=%s index=%s', (int) $attachment_id, $describe_status, $status ) );
		}

		WP_CLI::success(
			sprintf(
				'Done. indexed=%d updated=%d skipped=%d deleted=%d would_delete=%d would_update=%d would_index=%d failed=%d',
				$counts['indexed'],
				$counts['updated'],
				$counts['skipped'],
				$counts['deleted'],
				$counts['would_delete'],
				$counts['would_update'],
				$counts['would_index'],
				$counts['failed']
			)
		);
	}

	/**
	 * Run queued post indexing jobs.
	 *
	 * ## OPTIONS
	 *
	 * [--due-now]
	 * : Run only jobs whose scheduled time is due. By default all queued vector search post jobs are run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vector-search run-queue
	 *
	 * @param array<int, string>   $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function run_queue( array $args, array $assoc_args ): void {
		unset( $args );

		$cron    = _get_cron_array();
		$now     = time();
		$due_now = isset( $assoc_args['due-now'] );
		$ran     = 0;

		foreach ( $cron as $timestamp => $hooks ) {
			if ( $due_now && (int) $timestamp > $now ) {
				continue;
			}

			if ( ! isset( $hooks['wp_native_vector_search_index_post'] ) ) {
				continue;
			}

			foreach ( $hooks['wp_native_vector_search_index_post'] as $event ) {
				$args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
				if ( empty( $args ) ) {
					continue;
				}

				$post_id = (int) $args[0];
				$result  = $this->indexer->index_post( $post_id );

				wp_unschedule_event( (int) $timestamp, 'wp_native_vector_search_index_post', $args );

				if ( is_wp_error( $result ) ) {
					WP_CLI::warning( sprintf( 'Post %d: %s', $post_id, $result->get_error_message() ) );
					continue;
				}

				$ran++;
				WP_CLI::log( sprintf( 'Post %d: %s', $post_id, (string) ( $result['status'] ?? 'skipped' ) ) );
			}
		}

		WP_CLI::success( sprintf( 'Done. ran=%d', $ran ) );
	}

	/**
	 * Get attachment statuses that should be considered for indexing.
	 *
	 * Attachments commonly use "inherit", which is excluded from WP_Query's
	 * "any" status, so list normal editable statuses explicitly.
	 *
	 * @return array<int, string>
	 */
	private function get_indexable_attachment_statuses(): array {
		return array( 'inherit', 'publish', 'future', 'draft', 'pending', 'private' );
	}
}
