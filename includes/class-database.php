<?php
/**
 * Embedding database table access.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the custom embeddings table.
 */
final class Database {
	/**
	 * Get the embeddings table name.
	 */
	public function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'vector_search_embeddings';
	}

	/**
	 * Create or update the embeddings table.
	 */
	public function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $this->table_name();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(32) NOT NULL,
			post_status varchar(20) NOT NULL,
			content_hash char(64) NOT NULL,
			embedding longtext NOT NULL,
			embedding_model varchar(100) NOT NULL,
			dimensions int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_model (post_id, embedding_model),
			KEY post_type_status (post_type, post_status),
			KEY content_hash (content_hash)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Insert or update an embedding row.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	public function upsert_embedding( array $data ): bool {
		global $wpdb;

		$table_name = $this->table_name();
		$now        = current_time( 'mysql', true );
		$existing   = $this->get_by_post_and_model( (int) $data['post_id'], (string) $data['embedding_model'] );

		$row = array(
			'post_id'         => (int) $data['post_id'],
			'post_type'       => (string) $data['post_type'],
			'post_status'     => (string) $data['post_status'],
			'content_hash'    => (string) $data['content_hash'],
			'embedding'       => wp_json_encode( $data['embedding'] ),
			'embedding_model' => (string) $data['embedding_model'],
			'dimensions'      => (int) $data['dimensions'],
			'updated_at'      => $now,
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$table_name,
				$row,
				array(
					'id' => (int) $existing['id'],
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			return false !== $result;
		}

		$row['created_at'] = $now;

		$result = $wpdb->insert(
			$table_name,
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get an embedding row for a post and model.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $model Embedding model.
	 * @return array<string, mixed>|null
	 */
	public function get_by_post_and_model( int $post_id, string $model ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' WHERE post_id = %d AND embedding_model = %s',
				$post_id,
				$model
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete all embeddings for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function delete_by_post_id( int $post_id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name(),
			array(
				'post_id' => $post_id,
			),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update stored post state for an existing embedding row.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $model Embedding model.
	 * @param string $post_type Current post type.
	 * @param string $post_status Current post status.
	 */
	public function update_embedding_post_state( int $post_id, string $model, string $post_type, string $post_status ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table_name(),
			array(
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array(
				'post_id'         => $post_id,
				'embedding_model' => $model,
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get embedding rows matching the configured model and post types.
	 *
	 * @param string                $model Embedding model.
	 * @param array<int, string>    $post_types Post types.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidate_embeddings( string $model, array $post_types ): array {
		global $wpdb;

		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
		if ( array() === $post_types ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$params       = array_merge( array( $model ), $post_types );

		$sql = 'SELECT post_id, embedding FROM ' . $this->table_name()
			. " WHERE embedding_model = %s AND post_type IN ({$placeholders})";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count stored embeddings.
	 */
	public function count_embeddings(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table_name() );
	}
}
