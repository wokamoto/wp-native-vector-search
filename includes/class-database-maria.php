<?php
/**
 * MariaDB Vector table access.
 *
 * @package WP_Native_Vector_Search
 */

namespace WP_Native_Vector_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds MariaDB Vector storage and diagnostics to the JSON embeddings database.
 */
final class Database_Maria extends Database {
	private const VECTOR_STATUS_CACHE_TTL = 300;
	private const VECTOR_WRITE_ERROR_TRANSIENT = 'wpnvs_vector_write_error';
	private const SUPPORTED_VECTOR_DIMENSIONS = array( 1536, 3072 );
	private const MODEL_DIMENSIONS = array(
		'text-embedding-3-small' => 1536,
		'text-embedding-3-large' => 3072,
		'text-embedding-ada-002' => 1536,
	);

	/**
	 * Get the dimension-specific MariaDB Vector table name.
	 *
	 * @param int $dimensions Embedding dimensions.
	 */
	public function vector_table_name( int $dimensions ): string {
		global $wpdb;

		return $wpdb->prefix . 'vector_search_embeddings_vec_' . $dimensions;
	}

	/**
	 * Get embedding dimensions for a known model.
	 *
	 * @param string $model Embedding model.
	 */
	public function get_model_dimensions( string $model ): int {
		return self::MODEL_DIMENSIONS[ $model ] ?? 0;
	}

	/**
	 * Get supported MariaDB Vector dimensions.
	 *
	 * @return array<int, int>
	 */
	public function get_supported_vector_dimensions(): array {
		return self::SUPPORTED_VECTOR_DIMENSIONS;
	}

	/**
	 * Create a dimension-specific MariaDB Vector table.
	 *
	 * @param int $dimensions Embedding dimensions.
	 * @return bool|\WP_Error
	 */
	public function create_vector_table( int $dimensions ) {
		global $wpdb;

		if ( ! $this->is_supported_vector_dimension( $dimensions ) ) {
			return new \WP_Error( 'wp_native_vector_search_unsupported_dimensions', __( 'Unsupported vector dimensions.', 'wp-native-vector-search' ) );
		}

		$status = $this->get_mariadb_vector_status( $dimensions, true );
		if ( ! $status['server_is_mariadb'] || ! $status['version_supported'] || ! $status['vector_functions_available'] ) {
			return new \WP_Error( 'wp_native_vector_search_vector_unavailable', __( 'MariaDB Vector is not available on this database server.', 'wp-native-vector-search' ) );
		}

		$table_name      = $this->vector_table_name( $dimensions );
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = sprintf(
			'CREATE TABLE IF NOT EXISTS %s (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				post_type varchar(32) NOT NULL,
				post_status varchar(20) NOT NULL,
				content_hash char(64) NOT NULL,
				embedding VECTOR(%d) NOT NULL,
				embedding_model varchar(100) NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY post_model (post_id, embedding_model),
				KEY post_type_status (post_type, post_status),
				KEY content_hash (content_hash),
				VECTOR INDEX (embedding) M=8 DISTANCE=cosine
			) %s',
			$this->quote_identifier( $table_name ),
			$dimensions,
			$charset_collate
		);

		$result = $wpdb->query( $sql );
		$this->delete_vector_status_cache( $dimensions );

		if ( false === $result ) {
			return new \WP_Error( 'wp_native_vector_search_vector_table_failed', __( 'Could not create the MariaDB Vector table.', 'wp-native-vector-search' ), $wpdb->last_error );
		}

		$status = $this->get_mariadb_vector_status( $dimensions, true );
		if ( empty( $status['available'] ) ) {
			return new \WP_Error( 'wp_native_vector_search_vector_table_not_ready', __( 'The MariaDB Vector table exists but is missing the required cosine vector index.', 'wp-native-vector-search' ) );
		}

		return true;
	}

	/**
	 * Insert or update an embedding row and mirror it to MariaDB Vector when ready.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	public function upsert_embedding( array $data ): bool {
		$stored = parent::upsert_embedding( $data );
		if ( ! $stored ) {
			return false;
		}

		$this->maybe_upsert_vector_embedding( $data );

		return true;
	}

	/**
	 * Delete all embeddings for a post from JSON and vector tables.
	 *
	 * @param int $post_id Post ID.
	 */
	public function delete_by_post_id( int $post_id ): bool {
		global $wpdb;

		$deleted = parent::delete_by_post_id( $post_id );
		if ( ! $deleted ) {
			return false;
		}

		foreach ( self::SUPPORTED_VECTOR_DIMENSIONS as $dimensions ) {
			if ( ! $this->table_exists( $this->vector_table_name( $dimensions ) ) ) {
				continue;
			}

			$wpdb->delete(
				$this->vector_table_name( $dimensions ),
				array(
					'post_id' => $post_id,
				),
				array( '%d' )
			);
		}

		return true;
	}

	/**
	 * Update stored post state in JSON and vector tables.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $model Embedding model.
	 * @param string $post_type Current post type.
	 * @param string $post_status Current post status.
	 */
	public function update_embedding_post_state( int $post_id, string $model, string $post_type, string $post_status ): bool {
		global $wpdb;

		$updated = parent::update_embedding_post_state( $post_id, $model, $post_type, $post_status );
		if ( ! $updated ) {
			return false;
		}

		foreach ( self::SUPPORTED_VECTOR_DIMENSIONS as $dimensions ) {
			if ( ! $this->table_exists( $this->vector_table_name( $dimensions ) ) ) {
				continue;
			}

			$wpdb->update(
				$this->vector_table_name( $dimensions ),
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
		}

		return true;
	}

	/**
	 * Get vector-search candidates from a MariaDB Vector table.
	 *
	 * @param string             $model Embedding model.
	 * @param array<int, string> $post_types Post types.
	 * @param array<int, float>  $query_embedding Query embedding.
	 * @param int                $limit Maximum candidates.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_vector_search_candidates( string $model, array $post_types, array $query_embedding, int $limit ): array {
		global $wpdb;

		$dimensions = count( $query_embedding );
		if ( ! $this->is_supported_vector_dimension( $dimensions ) ) {
			return array();
		}

		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
		if ( array() === $post_types ) {
			return array();
		}

		$table_name  = $this->vector_table_name( $dimensions );
		$vector_text = wp_json_encode( array_map( 'floatval', $query_embedding ) );
		if ( ! is_string( $vector_text ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$params       = array_merge( array( $vector_text, $model ), $post_types, array( max( 1, $limit ) ) );

		$sql = 'SELECT post_id, post_type, post_status, VEC_DISTANCE_COSINE(embedding, VEC_FromText(%s)) AS distance FROM '
			. $this->quote_identifier( $table_name )
			. " WHERE embedding_model = %s AND post_type IN ({$placeholders}) ORDER BY distance ASC LIMIT %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get stored JSON embeddings for vector table migration.
	 *
	 * @param int $dimensions Embedding dimensions.
	 * @param int $limit Maximum rows.
	 * @param int $offset Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_embeddings_for_vector_migration( int $dimensions, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		if ( ! $this->is_supported_vector_dimension( $dimensions ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, post_type, post_status, content_hash, embedding, embedding_model, dimensions FROM ' . $this->table_name() . ' WHERE dimensions = %d ORDER BY id ASC LIMIT %d OFFSET %d',
				$dimensions,
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Upsert a vector row from a stored JSON embedding row.
	 *
	 * @param array<string, mixed> $row Stored JSON embedding row.
	 */
	public function upsert_vector_embedding_from_row( array $row ): bool {
		$embedding = json_decode( (string) ( $row['embedding'] ?? '' ), true );
		if ( ! is_array( $embedding ) ) {
			return false;
		}

		return $this->upsert_vector_embedding(
			array(
				'post_id'         => (int) $row['post_id'],
				'post_type'       => (string) $row['post_type'],
				'post_status'     => (string) $row['post_status'],
				'content_hash'    => (string) $row['content_hash'],
				'embedding'       => array_map( 'floatval', $embedding ),
				'embedding_model' => (string) $row['embedding_model'],
				'dimensions'      => (int) $row['dimensions'],
			)
		);
	}

	/**
	 * Get MariaDB Vector availability details.
	 *
	 * @param int|null $dimensions Optional embedding dimensions.
	 * @param bool     $force_refresh Ignore cached status.
	 * @return array<string, mixed>
	 */
	public function get_mariadb_vector_status( ?int $dimensions = null, bool $force_refresh = false ): array {
		$cache_key = 'wpnvs_mariadb_vector_status_' . ( $dimensions ?? 'all' );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$server_version             = $this->get_server_version();
		$server_is_mariadb          = str_contains( strtolower( $server_version ), 'mariadb' );
		$normalized_version         = $this->normalize_mariadb_version( $server_version );
		$version_supported          = $server_is_mariadb && '' !== $normalized_version && version_compare( $normalized_version, '11.7.0', '>=' );
		$vector_functions_available = $server_is_mariadb && $version_supported && $this->vector_functions_available();
		$dimensions_to_check        = null === $dimensions ? self::SUPPORTED_VECTOR_DIMENSIONS : array( $dimensions );
		$tables                     = array();

		foreach ( $dimensions_to_check as $dimension ) {
			$table_exists = $this->table_exists( $this->vector_table_name( $dimension ) );
			$index_exists = $table_exists && $this->vector_index_exists( $this->vector_table_name( $dimension ) );

			$tables[ $dimension ] = array(
				'table_name'          => $this->vector_table_name( $dimension ),
				'table_exists'        => $table_exists,
				'vector_index_exists' => $index_exists,
				'available'           => $server_is_mariadb && $version_supported && $vector_functions_available && $table_exists && $index_exists,
			);
		}

		$dimension_status = null !== $dimensions && isset( $tables[ $dimensions ] ) ? $tables[ $dimensions ] : null;
		$status           = array(
			'server_version'             => $server_version,
			'server_is_mariadb'          => $server_is_mariadb,
			'normalized_version'         => $normalized_version,
			'version_supported'          => $version_supported,
			'vector_functions_available' => $vector_functions_available,
			'dimensions'                 => $dimensions,
			'tables'                     => $tables,
			'available'                  => null !== $dimension_status ? (bool) $dimension_status['available'] : false,
			'last_write_error'           => $this->get_last_vector_write_error(),
		);

		set_transient( $cache_key, $status, self::VECTOR_STATUS_CACHE_TTL );

		return $status;
	}

	/**
	 * Get the most recent vector write error.
	 */
	public function get_last_vector_write_error(): string {
		$error = get_transient( self::VECTOR_WRITE_ERROR_TRANSIENT );

		return is_string( $error ) ? $error : '';
	}

	/**
	 * Whether the connected server is MariaDB.
	 */
	public function is_mariadb_server(): bool {
		return str_contains( strtolower( $this->get_server_version() ), 'mariadb' );
	}

	/**
	 * Write to the vector table when it is available.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	private function maybe_upsert_vector_embedding( array $data ): void {
		$dimensions = (int) ( $data['dimensions'] ?? 0 );
		if ( ! $this->is_supported_vector_dimension( $dimensions ) ) {
			return;
		}

		$status = $this->get_mariadb_vector_status( $dimensions );
		if ( empty( $status['available'] ) ) {
			return;
		}

		if ( ! $this->upsert_vector_embedding( $data ) ) {
			$error = $this->get_last_database_error();
			set_transient( self::VECTOR_WRITE_ERROR_TRANSIENT, $error, DAY_IN_SECONDS );
			error_log( sprintf( 'WP Native Vector Search MariaDB Vector write failed: %s', $error ) );
			return;
		}

		delete_transient( self::VECTOR_WRITE_ERROR_TRANSIENT );
	}

	/**
	 * Upsert a row into a dimension-specific vector table.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	private function upsert_vector_embedding( array $data ): bool {
		global $wpdb;

		$dimensions = (int) ( $data['dimensions'] ?? 0 );
		if ( ! $this->is_supported_vector_dimension( $dimensions ) ) {
			return false;
		}

		$embedding = $data['embedding'] ?? array();
		if ( ! is_array( $embedding ) || count( $embedding ) !== $dimensions ) {
			return false;
		}

		$table_name  = $this->vector_table_name( $dimensions );
		$now         = current_time( 'mysql', true );
		$vector_text = wp_json_encode( array_map( 'floatval', $embedding ) );
		if ( ! is_string( $vector_text ) ) {
			return false;
		}

		$sql = 'INSERT INTO ' . $this->quote_identifier( $table_name ) . ' (
				post_id,
				post_type,
				post_status,
				content_hash,
				embedding,
				embedding_model,
				created_at,
				updated_at
			) VALUES (
				%d,
				%s,
				%s,
				%s,
				VEC_FromText(%s),
				%s,
				%s,
				%s
			)
			ON DUPLICATE KEY UPDATE
				post_type = VALUES(post_type),
				post_status = VALUES(post_status),
				content_hash = VALUES(content_hash),
				embedding = VALUES(embedding),
				updated_at = VALUES(updated_at)';

		$result = $wpdb->query(
			$wpdb->prepare(
				$sql,
				(int) $data['post_id'],
				(string) $data['post_type'],
				(string) $data['post_status'],
				(string) $data['content_hash'],
				$vector_text,
				(string) $data['embedding_model'],
				$now,
				$now
			)
		);

		return false !== $result;
	}

	/**
	 * Whether the plugin supports a vector dimension.
	 *
	 * @param int $dimensions Embedding dimensions.
	 */
	private function is_supported_vector_dimension( int $dimensions ): bool {
		return in_array( $dimensions, self::SUPPORTED_VECTOR_DIMENSIONS, true );
	}

	/**
	 * Get the raw database server version.
	 */
	private function get_server_version(): string {
		global $wpdb;

		$version = $wpdb->get_var( 'SELECT VERSION()' );

		return is_string( $version ) ? $version : '';
	}

	/**
	 * Extract a comparable MariaDB version.
	 *
	 * @param string $server_version Raw server version.
	 */
	private function normalize_mariadb_version( string $server_version ): string {
		if ( preg_match( '/(\d+\.\d+(?:\.\d+)?)/', $server_version, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Check whether MariaDB Vector functions are executable.
	 */
	private function vector_functions_available(): bool {
		global $wpdb;

		$previous = $wpdb->suppress_errors( true );
		$result   = $wpdb->get_var( "SELECT VEC_DISTANCE_COSINE(VEC_FromText('[1,0]'), VEC_FromText('[1,0]'))" );
		$wpdb->suppress_errors( $previous );

		return null !== $result && is_numeric( $result );
	}

	/**
	 * Determine whether a table exists.
	 *
	 * @param string $table_name Table name.
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return is_string( $found ) && strtolower( $found ) === strtolower( $table_name );
	}

	/**
	 * Determine whether a vector table has a cosine vector index.
	 *
	 * @param string $table_name Table name.
	 */
	private function vector_index_exists( string $table_name ): bool {
		global $wpdb;

		$row = $wpdb->get_row( 'SHOW CREATE TABLE ' . $this->quote_identifier( $table_name ), ARRAY_N );
		if ( ! is_array( $row ) || ! isset( $row[1] ) ) {
			return false;
		}

		$create_sql = strtoupper( (string) $row[1] );

		return str_contains( $create_sql, 'VECTOR' )
			&& str_contains( $create_sql, 'EMBEDDING' )
			&& str_contains( $create_sql, 'COSINE' );
	}

	/**
	 * Delete cached vector status.
	 *
	 * @param int|null $dimensions Optional dimensions.
	 */
	private function delete_vector_status_cache( ?int $dimensions = null ): void {
		delete_transient( 'wpnvs_mariadb_vector_status_all' );
		if ( null !== $dimensions ) {
			delete_transient( 'wpnvs_mariadb_vector_status_' . $dimensions );
		}
	}

	/**
	 * Quote a trusted SQL identifier.
	 *
	 * @param string $identifier SQL identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Get the last database error.
	 */
	private function get_last_database_error(): string {
		global $wpdb;

		return '' !== $wpdb->last_error ? $wpdb->last_error : __( 'Unknown database error.', 'wp-native-vector-search' );
	}
}
