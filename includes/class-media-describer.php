<?php
/**
 * Media image description generation.
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
 * Generates and stores image descriptions for media attachments.
 */
final class Media_Describer {
	public const META_DESCRIPTION  = '_wp_native_vector_search_image_description';
	public const META_MODEL        = '_wp_native_vector_search_image_description_model';
	public const META_FILE_HASH    = '_wp_native_vector_search_image_description_hash';
	public const META_GENERATED_AT = '_wp_native_vector_search_image_description_generated_at';
	public const META_ERROR        = '_wp_native_vector_search_image_description_error';

	private const CRON_HOOK = 'wp_native_vector_search_describe_attachment';

	private const SUPPORTED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
	);

	private const MAX_IMAGE_BYTES = 10485760;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * OpenAI client.
	 *
	 * @var OpenAI_Client
	 */
	private OpenAI_Client $openai_client;

	/**
	 * Constructor.
	 */
	public function __construct( Settings $settings, OpenAI_Client $openai_client ) {
		$this->settings      = $settings;
		$this->openai_client = $openai_client;
	}

	/**
	 * Register attachment hooks.
	 */
	public function register(): void {
		add_action( 'edit_attachment', array( $this, 'queue_description_generation' ) );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'handle_attachment_metadata_update' ), 20, 2 );
		add_action( self::CRON_HOOK, array( $this, 'run_queued_description' ) );
		add_action( 'delete_attachment', array( $this, 'delete_description_meta' ) );
	}

	/**
	 * Queue image description generation outside the upload/edit request.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function queue_description_generation( int $attachment_id ): void {
		if ( wp_next_scheduled( self::CRON_HOOK, array( $attachment_id ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $attachment_id ) );
	}

	/**
	 * Run a queued image description generation job.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function run_queued_description( int $attachment_id ): void {
		$result = $this->describe_attachment( $attachment_id );
		if ( is_wp_error( $result ) ) {
			update_post_meta( $attachment_id, self::META_ERROR, $result->get_error_message() );
		}
	}

	/**
	 * Generate a description after image metadata is available.
	 *
	 * @param mixed $metadata Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return mixed
	 */
	public function handle_attachment_metadata_update( $metadata, int $attachment_id ) {
		$this->queue_description_generation( $attachment_id );

		return $metadata;
	}

	/**
	 * Generate and store an image description.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force Force regeneration.
	 * @param bool $dry_run Do not call OpenAI or write postmeta.
	 * @return array<string, mixed>|WP_Error
	 */
	public function describe_attachment( int $attachment_id, bool $force = false, bool $dry_run = false ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'wp_native_vector_search_attachment_not_found', __( 'Attachment not found.', 'wp-native-vector-search' ) );
		}

		$mime_type = get_post_mime_type( $attachment );
		if ( ! is_string( $mime_type ) || ! in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'unsupported_mime_type' );
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! is_string( $file_path ) || '' === $file_path || ! is_readable( $file_path ) ) {
			return new WP_Error( 'wp_native_vector_search_attachment_file_missing', __( 'Attachment image file is missing or unreadable.', 'wp-native-vector-search' ) );
		}

		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size > self::MAX_IMAGE_BYTES ) {
			return array( 'status' => 'skipped', 'reason' => 'image_too_large' );
		}

		$file_hash     = hash_file( 'sha256', $file_path );
		$current_hash  = (string) get_post_meta( $attachment_id, self::META_FILE_HASH, true );
		$current_model = (string) get_post_meta( $attachment_id, self::META_MODEL, true );
		$vision_model  = (string) $this->settings->get( 'vision_model' );

		if ( ! $force && $file_hash === $current_hash && $vision_model === $current_model ) {
			return array( 'status' => 'skipped', 'reason' => 'unchanged' );
		}

		if ( $dry_run ) {
			return array(
				'status'    => 'would_describe',
				'file_hash' => $file_hash,
				'mime_type' => $mime_type,
				'bytes'     => $file_size,
			);
		}

		$description = $this->openai_client->create_image_description( $file_path, $mime_type, $vision_model );
		if ( is_wp_error( $description ) ) {
			update_post_meta( $attachment_id, self::META_ERROR, $description->get_error_message() );
			return $description;
		}

		update_post_meta( $attachment_id, self::META_DESCRIPTION, $description );
		update_post_meta( $attachment_id, self::META_MODEL, $vision_model );
		update_post_meta( $attachment_id, self::META_FILE_HASH, $file_hash );
		update_post_meta( $attachment_id, self::META_GENERATED_AT, current_time( 'mysql', true ) );
		delete_post_meta( $attachment_id, self::META_ERROR );

		return array(
			'status'      => 'described',
			'description' => $description,
		);
	}

	/**
	 * Delete stored description metadata for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function delete_description_meta( int $attachment_id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $attachment_id ) );
		delete_post_meta( $attachment_id, self::META_DESCRIPTION );
		delete_post_meta( $attachment_id, self::META_MODEL );
		delete_post_meta( $attachment_id, self::META_FILE_HASH );
		delete_post_meta( $attachment_id, self::META_GENERATED_AT );
		delete_post_meta( $attachment_id, self::META_ERROR );
	}
}
