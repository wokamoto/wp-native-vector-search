<?php
/**
 * Post indexing.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and stores post embeddings.
 */
final class Indexer {
	private const CRON_HOOK = 'wp_native_vector_search_index_post';
	private const HASH_PREFIX_MEDIA = 'media';

	/**
	 * Post IDs queued during the current request.
	 *
	 * @var array<int, bool>
	 */
	private array $queued_post_ids = array();

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
	 * OpenAI client.
	 *
	 * @var OpenAI_Client
	 */
	private OpenAI_Client $openai_client;

	/**
	 * Constructor.
	 */
	public function __construct( Settings $settings, Database $database, OpenAI_Client $openai_client ) {
		$this->settings      = $settings;
		$this->database      = $database;
		$this->openai_client = $openai_client;
	}

	/**
	 * Register indexing hooks.
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
		add_action( 'transition_post_status', array( $this, 'handle_status_transition' ), 20, 3 );
		add_action( 'deleted_post', array( $this, 'handle_deleted_post' ) );
		add_action( 'delete_attachment', array( $this, 'handle_deleted_post' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_queued_post_index' ) );
	}

	/**
	 * Handle post saves.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an update.
	 */
	public function handle_save_post( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( ! $this->should_auto_index() ) {
			return;
		}

		$this->queue_post_index( $post_id );
	}

	/**
	 * Handle status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post object.
	 */
	public function handle_status_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		$is_publish_transition = 'publish' === $new_status || 'publish' === $old_status;

		if ( $is_publish_transition && $this->should_auto_index() ) {
			$this->queue_post_index( (int) $post->ID );
		}
	}

	/**
	 * Handle post deletion.
	 *
	 * @param int $post_id Post ID.
	 */
	public function handle_deleted_post( int $post_id ): void {
		$this->database->delete_by_post_id( $post_id );
		$this->clear_queued_post_index( $post_id );
	}

	/**
	 * Queue post indexing outside the save/status-change request.
	 *
	 * @param int $post_id Post ID.
	 */
	public function queue_post_index( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$post_types = (array) $this->settings->get( 'post_types' );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		if ( isset( $this->queued_post_ids[ $post_id ] ) ) {
			return;
		}

		$this->queued_post_ids[ $post_id ] = true;

		if ( wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 10, self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Run a queued post indexing job.
	 *
	 * @param int $post_id Post ID.
	 */
	public function run_queued_post_index( int $post_id ): void {
		$result = $this->index_post( $post_id );
		if ( is_wp_error( $result ) ) {
			error_log( sprintf( 'WP Native Vector Search post index failed for %d: %s', $post_id, $result->get_error_message() ) );
		}
	}

	/**
	 * Clear a pending post indexing job.
	 *
	 * @param int $post_id Post ID.
	 */
	private function clear_queued_post_index( int $post_id ): void {
		unset( $this->queued_post_ids[ $post_id ] );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Index a post.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force Force regeneration.
	 * @param bool $dry_run Do not call OpenAI or write DB.
	 * @return array<string, mixed>|WP_Error
	 */
	public function index_post( int $post_id, bool $force = false, bool $dry_run = false ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return array( 'status' => 'skipped', 'reason' => 'revision_or_autosave' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'wp_native_vector_search_post_not_found', __( 'Post not found.', 'wp-native-vector-search' ) );
		}

		$post_types = (array) $this->settings->get( 'post_types' );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'post_type_not_configured' );
		}

		$model = (string) $this->settings->get( 'embedding_model' );

		$text = $this->build_embedding_text( $post );
		if ( '' === $text ) {
			if ( $dry_run ) {
				return array( 'status' => 'would_delete', 'reason' => 'empty_content' );
			}

			$this->database->delete_by_post_id( $post_id );
			return array( 'status' => 'deleted', 'reason' => 'empty_content' );
		}

		// Keep the original post hash format for existing embedding rows.
		$content_hash = hash( 'sha256', $model . "\n" . $text );
		$existing     = $this->database->get_by_post_and_model( $post_id, $model );

		if ( ! $force && $existing && $content_hash === (string) $existing['content_hash'] ) {
			if ( (string) $existing['post_type'] !== $post->post_type || (string) $existing['post_status'] !== $post->post_status ) {
				if ( $dry_run ) {
					return array( 'status' => 'would_update', 'reason' => 'post_state_changed' );
				}

				$updated = $this->database->update_embedding_post_state( $post_id, $model, $post->post_type, $post->post_status );
				if ( ! $updated ) {
					return new WP_Error( 'wp_native_vector_search_db_write_failed', __( 'Could not update the post embedding status.', 'wp-native-vector-search' ) );
				}

				return array( 'status' => 'updated', 'reason' => 'post_state_changed' );
			}

			return array( 'status' => 'skipped', 'reason' => 'unchanged' );
		}

		if ( $dry_run ) {
			return array(
				'status'       => 'would_index',
				'content_hash' => $content_hash,
				'chars'        => strlen( $text ),
			);
		}

		$embedding = $this->openai_client->create_embedding( $text, $model );
		if ( is_wp_error( $embedding ) ) {
			return $embedding;
		}

		$stored = $this->database->upsert_embedding(
			array(
				'post_id'         => $post_id,
				'post_type'       => $post->post_type,
				'post_status'     => $post->post_status,
				'content_hash'    => $content_hash,
				'embedding'       => $embedding,
				'embedding_model' => $model,
				'dimensions'      => count( $embedding ),
			)
		);

		if ( ! $stored ) {
			return new WP_Error( 'wp_native_vector_search_db_write_failed', __( 'Could not store the post embedding.', 'wp-native-vector-search' ) );
		}

		return array(
			'status'     => 'indexed',
			'dimensions' => count( $embedding ),
		);
	}

	/**
	 * Index an image attachment using its generated description.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force Force regeneration.
	 * @param bool $dry_run Do not call OpenAI or write DB.
	 * @return array<string, mixed>|WP_Error
	 */
	public function index_media( int $attachment_id, bool $force = false, bool $dry_run = false ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'wp_native_vector_search_attachment_not_found', __( 'Attachment not found.', 'wp-native-vector-search' ) );
		}

		$description = get_post_meta( $attachment_id, Media_Describer::META_DESCRIPTION, true );
		$description = is_string( $description ) ? trim( $description ) : '';

		if ( '' === $description ) {
			return array( 'status' => 'skipped', 'reason' => 'missing_description' );
		}

		$model = (string) $this->settings->get( 'embedding_model' );
		$text  = $this->build_media_embedding_text( $attachment, $description );

		$content_hash = hash( 'sha256', $model . "\n" . self::HASH_PREFIX_MEDIA . "\n" . $text );
		$existing     = $this->database->get_by_post_and_model( $attachment_id, $model );

		if ( ! $force && $existing && $content_hash === (string) $existing['content_hash'] ) {
			if ( (string) $existing['post_type'] !== 'attachment' || (string) $existing['post_status'] !== $attachment->post_status ) {
				if ( $dry_run ) {
					return array( 'status' => 'would_update', 'reason' => 'post_state_changed' );
				}

				$updated = $this->database->update_embedding_post_state( $attachment_id, $model, 'attachment', $attachment->post_status );
				if ( ! $updated ) {
					return new WP_Error( 'wp_native_vector_search_db_write_failed', __( 'Could not update the media embedding status.', 'wp-native-vector-search' ) );
				}

				return array( 'status' => 'updated', 'reason' => 'post_state_changed' );
			}

			return array( 'status' => 'skipped', 'reason' => 'unchanged' );
		}

		if ( $dry_run ) {
			return array(
				'status'       => 'would_index',
				'content_hash' => $content_hash,
				'chars'        => strlen( $text ),
			);
		}

		$embedding = $this->openai_client->create_embedding( $text, $model );
		if ( is_wp_error( $embedding ) ) {
			return $embedding;
		}

		$stored = $this->database->upsert_embedding(
			array(
				'post_id'         => $attachment_id,
				'post_type'       => 'attachment',
				'post_status'     => $attachment->post_status,
				'content_hash'    => $content_hash,
				'embedding'       => $embedding,
				'embedding_model' => $model,
				'dimensions'      => count( $embedding ),
			)
		);

		if ( ! $stored ) {
			return new WP_Error( 'wp_native_vector_search_db_write_failed', __( 'Could not store the media embedding.', 'wp-native-vector-search' ) );
		}

		return array(
			'status'     => 'indexed',
			'dimensions' => count( $embedding ),
		);
	}

	/**
	 * Build plain text for embedding.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function build_embedding_text( WP_Post $post ): string {
		$parts = array(
			get_the_title( $post ),
			$post->post_excerpt,
			$post->post_content,
		);

		$text = implode( "\n\n", array_filter( array_map( 'strval', $parts ) ) );
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		$max_chars = (int) $this->settings->get( 'max_chars' );
		if ( $max_chars > 0 && function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, $max_chars );
		} elseif ( $max_chars > 0 ) {
			$text = substr( $text, 0, $max_chars );
		}

		return $text;
	}

	/**
	 * Build plain text for media embedding.
	 *
	 * @param WP_Post $attachment Attachment post.
	 * @param string  $description Generated image description.
	 */
	public function build_media_embedding_text( WP_Post $attachment, string $description ): string {
		$alt_text = (string) get_post_meta( (int) $attachment->ID, '_wp_attachment_image_alt', true );
		$parts    = array(
			get_the_title( $attachment ),
			$alt_text,
			$attachment->post_excerpt,
			$attachment->post_content,
			$description,
		);

		$text = implode( "\n\n", array_filter( array_map( 'strval', $parts ) ) );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		$max_chars = (int) $this->settings->get( 'max_chars' );
		if ( $max_chars > 0 && function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, $max_chars );
		} elseif ( $max_chars > 0 ) {
			$text = substr( $text, 0, $max_chars );
		}

		return $text;
	}

	/**
	 * Whether automatic indexing is enabled.
	 */
	private function should_auto_index(): bool {
		return (bool) $this->settings->get( 'auto_index' );
	}
}
