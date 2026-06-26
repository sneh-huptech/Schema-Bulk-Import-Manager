<?php
/**
 * Database handler — creates and manages the custom schema table.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_Database {

	/** @var string */
	private static string $table;

	/**
	 * Get the full table name (with WP prefix).
	 */
	public static function table(): string {
		global $wpdb;
		if ( empty( self::$table ) ) {
			self::$table = $wpdb->prefix . SBIM_TABLE_NAME;
		}
		return self::$table;
	}

	/**
	 * Create tables on plugin activation.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table      = self::table();
		$charset    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			url         VARCHAR(2083)       NOT NULL,
			post_id     BIGINT(20) UNSIGNED DEFAULT NULL,
			schema_markup LONGTEXT          NOT NULL,
			status      VARCHAR(20)         NOT NULL DEFAULT 'active',
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_url     (url(191)),
			KEY idx_post_id (post_id),
			KEY idx_status  (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'sbim_db_version', SBIM_VERSION );
	}

	/**
	 * Drop tables on uninstall.
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert a new schema record.
	 *
	 * @param array $data { url, post_id, schema_markup, status }
	 * @return int|false Inserted ID or false.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::table(),
			[
				'url'           => sanitize_url( $data['url'] ),
				'post_id'       => ! empty( $data['post_id'] ) ? absint( $data['post_id'] ) : null,
				'schema_markup' => $data['schema_markup'], // stored verbatim — no sanitization.
				'status'        => sanitize_key( $data['status'] ?? 'active' ),
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing schema record.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$update_data   = [];
		$update_format = [];

		if ( isset( $data['url'] ) ) {
			$update_data['url']    = sanitize_url( $data['url'] );
			$update_format[]       = '%s';
		}
		if ( isset( $data['post_id'] ) ) {
			$update_data['post_id'] = absint( $data['post_id'] );
			$update_format[]        = '%d';
		}
		if ( isset( $data['schema_markup'] ) ) {
			$update_data['schema_markup'] = $data['schema_markup'];
			$update_format[]              = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_key( $data['status'] );
			$update_format[]       = '%s';
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$update_format[]           = '%s';

		$result = $wpdb->update(
			self::table(),
			$update_data,
			[ 'id' => $id ],
			$update_format,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a schema record by ID.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Bulk delete by array of IDs.
	 *
	 * @param int[] $ids
	 */
	public static function bulk_delete( array $ids ): int {
		global $wpdb;
		$table       = self::table();
		$ids         = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$query  = $wpdb->prepare(
			"DELETE FROM {$table} WHERE id IN ({$placeholders})",
			...$ids
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $query );
	}

	/**
	 * Get a single record by ID.
	 *
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
	}

	/**
	 * Get schema markup by post_id (used on frontend — cached).
	 *
	 * @return string|null
	 */
	public static function get_schema_by_post_id( int $post_id ): ?string {
		$cache_key = 'sbim_schema_' . $post_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached ?: null;
		}

		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT schema_markup FROM {$table} WHERE post_id = %d AND status = 'active' LIMIT 1",
				$post_id
			)
		);

		$schema = $row ? $row->schema_markup : '';
		set_transient( $cache_key, $schema, HOUR_IN_SECONDS * 12 );

		return $schema ?: null;
	}

	/**
	 * Check if a URL already exists in the database.
	 *
	 * @return int|null Record ID if exists, null otherwise.
	 */
	public static function url_exists( string $url ): ?int {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE url = %s LIMIT 1",
				sanitize_url( $url )
			)
		);
		return $row ? (int) $row->id : null;
	}

	/**
	 * Get all records with optional filters and pagination.
	 *
	 * @param array $args { search, status, per_page, page, orderby, order }
	 * @return array { items: object[], total: int }
	 */
	public static function get_all( array $args = [] ): array {
		global $wpdb;
		$table = self::table();

		$defaults = [
			'search'   => '',
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'url LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Whitelist orderby / order.
		$allowed_orderby = [ 'id', 'url', 'status', 'created_at', 'updated_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$per_page = absint( $args['per_page'] );
		$offset   = ( absint( $args['page'] ) - 1 ) * $per_page;

		// Total count.
		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where_sql}" );
		}

		// Items.
		$limit_sql = $wpdb->prepare( "LIMIT %d OFFSET %d", $per_page, $offset );

		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} {$limit_sql}", ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$items = $wpdb->get_results( "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} {$limit_sql}" );
		}

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}

	/**
	 * Dashboard statistics.
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table = self::table();

		$cache_key = 'sbim_stats';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
		$inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'not_found'" );

		$stats = [
			'total'    => $total,
			'active'   => $active,
			'inactive' => $inactive,
		];

		set_transient( $cache_key, $stats, MINUTE_IN_SECONDS * 5 );
		return $stats;
	}

	/**
	 * Invalidate caches for a specific post_id.
	 */
	public static function flush_cache( int $post_id ): void {
		delete_transient( 'sbim_schema_' . $post_id );
		delete_transient( 'sbim_stats' );
	}

	/**
	 * Flush all plugin transients.
	 */
	public static function flush_all_cache(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sbim_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sbim_%'" );
	}

	/**
	 * Get all records for export (no pagination).
	 */
	public static function get_all_for_export(): array {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( "SELECT id, url, post_id, schema_markup, status, created_at, updated_at FROM {$table} ORDER BY id ASC", ARRAY_A ) ?: [];
	}
}