<?php
/**
 * AJAX handler — processes all admin AJAX requests.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_Ajax {

	/** @var SBIM_Ajax|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_sbim_upload_csv',   [ $this, 'handle_upload_csv' ] );
		add_action( 'wp_ajax_sbim_save_schemas', [ $this, 'handle_save_schemas' ] );
		add_action( 'wp_ajax_sbim_delete_schema',[ $this, 'handle_delete_schema' ] );
		add_action( 'wp_ajax_sbim_bulk_delete',  [ $this, 'handle_bulk_delete' ] );
	}

	// -------------------------------------------------------------------------
	// Upload + Parse CSV
	// -------------------------------------------------------------------------

	/**
	 * Step 1: Parse the uploaded CSV and return a preview table (not saved yet).
	 */
	public function handle_upload_csv(): void {
		$this->verify_nonce();

		// Check file upload.
		if ( empty( $_FILES['csv_file'] ) ) {
			$this->error( __( 'No file received.', 'schema-bulk-import-manager' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file   = $_FILES['csv_file'];
		$parsed = SBIM_CSV_Handler::parse_upload( $file );

		if ( ! $parsed['success'] ) {
			$this->error( $parsed['error'] );
		}

		$raw_rows = $parsed['rows'];
		$preview  = [];
		$stats    = [
			'total'     => count( $raw_rows ),
			'found'     => 0,
			'not_found' => 0,
			'invalid'   => 0,
		];

		foreach ( $raw_rows as $index => $raw ) {
			$row_number    = $raw['row_number'];
			$url           = $raw['url'];
			$schema_markup = $raw['schema_markup'];
			$parse_error   = $raw['parse_error'];

			$preview_row = [
				'row_number'    => $row_number,
				'url'           => $url,
				'schema_markup' => $schema_markup,
				'url_status'    => 'not_found',
				'post_id'       => null,
				'post_type'     => '',
				'post_title'    => '',
				'schema_valid'  => false,
				'schema_error'  => '',
				'duplicate_id'  => null,
				'importable'    => false,
			];

			// Parse error from CSV.
			if ( $parse_error ) {
				$preview_row['schema_error'] = $parse_error;
				$stats['invalid']++;
				$preview[] = $preview_row;
				continue;
			}

			// Validate URL format.
			if ( empty( $url ) || ! SBIM_Validator::validate_url( $url ) ) {
				$preview_row['schema_error'] = "Row {$row_number}: Invalid or missing URL.";
				$stats['invalid']++;
				$preview[] = $preview_row;
				continue;
			}

			// Validate schema.
			$validation = SBIM_Validator::validate( $schema_markup, $row_number );
			if ( ! $validation['valid'] ) {
				$preview_row['schema_error'] = $validation['error'];
				$stats['invalid']++;
				$preview[] = $preview_row;
				continue;
			}

			$preview_row['schema_valid'] = true;

			// URL matching.
			$post_id = SBIM_URL_Matcher::url_to_post_id( $url );
			if ( $post_id ) {
				$preview_row['url_status'] = 'found';
				$preview_row['post_id']    = $post_id;
				$post                      = get_post( $post_id );
				$preview_row['post_title'] = $post ? $post->post_title : '';
				$preview_row['post_type']  = $post ? SBIM_URL_Matcher::get_post_type_label( $post_id ) : '';
				$stats['found']++;
			} else {
				$preview_row['url_status'] = 'not_found';
				$stats['not_found']++;
			}

			// Check for duplicate.
			$existing_id = SBIM_Database::url_exists( $url );
			if ( $existing_id ) {
				$preview_row['duplicate_id'] = $existing_id;
			}

			// A row is importable if URL was found and schema is valid.
			$preview_row['importable'] = ( $preview_row['url_status'] === 'found' && $preview_row['schema_valid'] );

			$preview[] = $preview_row;
		}

		// Store preview in transient (10 min TTL) for the save step.
		$session_key = 'sbim_preview_' . get_current_user_id();
		set_transient( $session_key, $preview, 10 * MINUTE_IN_SECONDS );

		wp_send_json_success( [
			'preview' => $preview,
			'stats'   => $stats,
		] );
	}

	// -------------------------------------------------------------------------
	// Save Schemas
	// -------------------------------------------------------------------------

	/**
	 * Step 2: Save previewed (importable) rows to the database.
	 */
	public function handle_save_schemas(): void {
		$this->verify_nonce();

		$session_key = 'sbim_preview_' . get_current_user_id();
		$preview     = get_transient( $session_key );

		if ( ! $preview ) {
			$this->error( __( 'Preview session expired. Please re-upload your CSV.', 'schema-bulk-import-manager' ) );
		}

		// Duplicate handling instructions from client { url => 'replace'|'skip' }
		$duplicate_actions = [];
		if ( ! empty( $_POST['duplicate_actions'] ) ) {
			$raw_actions = json_decode( sanitize_text_field( wp_unslash( $_POST['duplicate_actions'] ) ), true );
			if ( is_array( $raw_actions ) ) {
				foreach ( $raw_actions as $url => $action ) {
					$duplicate_actions[ sanitize_url( $url ) ] = in_array( $action, [ 'replace', 'skip' ], true ) ? $action : 'skip';
				}
			}
		}

		$report = [
			'total'       => count( $preview ),
			'imported'    => 0,
			'skipped'     => 0,
			'not_found'   => 0,
			'invalid_json'=> 0,
			'duplicates'  => 0,
			'rows'        => [],
		];

		foreach ( $preview as $row ) {
			$row_result = [
				'row_number' => $row['row_number'],
				'url'        => $row['url'],
				'post_id'    => $row['post_id'],
				'result'     => '',
				'note'       => '',
			];

			// Not importable.
			if ( ! $row['importable'] ) {
				if ( $row['url_status'] === 'not_found' ) {
					$row_result['result'] = 'not_found';
					$report['not_found']++;
				} elseif ( ! $row['schema_valid'] ) {
					$row_result['result'] = 'invalid_json';
					$row_result['note']   = $row['schema_error'];
					$report['invalid_json']++;
				} else {
					$row_result['result'] = 'skipped';
					$report['skipped']++;
				}
				$report['rows'][] = $row_result;
				continue;
			}

			// Handle duplicate.
			$existing_id = $row['duplicate_id'];
			if ( $existing_id ) {
				$action = $duplicate_actions[ $row['url'] ] ?? 'skip';

				if ( $action === 'replace' ) {
					SBIM_Database::update( $existing_id, [
						'schema_markup' => $row['schema_markup'],
						'post_id'       => $row['post_id'],
						'status'        => 'active',
					] );
					SBIM_Database::flush_cache( (int) $row['post_id'] );
					$row_result['result'] = 'replaced';
					$row_result['note']   = 'Existing schema replaced.';
					$report['imported']++;
					$report['duplicates']++;
				} else {
					$row_result['result'] = 'skipped';
					$row_result['note']   = 'Duplicate — skipped.';
					$report['skipped']++;
					$report['duplicates']++;
				}

				$report['rows'][] = $row_result;
				continue;
			}

			// Fresh insert.
			$inserted = SBIM_Database::insert( [
				'url'           => $row['url'],
				'post_id'       => $row['post_id'],
				'schema_markup' => $row['schema_markup'],
				'status'        => 'active',
			] );

			if ( $inserted ) {
				SBIM_Database::flush_cache( (int) $row['post_id'] );
				$row_result['result'] = 'imported';
				$report['imported']++;
			} else {
				$row_result['result'] = 'skipped';
				$row_result['note']   = 'Database insert failed.';
				$report['skipped']++;
			}

			$report['rows'][] = $row_result;
		}

		// Flush global stats cache.
		SBIM_Database::flush_all_cache();

		// Store report for download.
		set_transient( 'sbim_last_import_report', $report, HOUR_IN_SECONDS );

		// Clear preview.
		delete_transient( $session_key );

		wp_send_json_success( [ 'report' => $report ] );
	}

	// -------------------------------------------------------------------------
	// Delete
	// -------------------------------------------------------------------------

	public function handle_delete_schema(): void {
		$this->verify_nonce();

		$id  = absint( $_POST['id'] ?? 0 );
		$row = SBIM_Database::get( $id );

		if ( ! $row ) {
			$this->error( __( 'Schema not found.', 'schema-bulk-import-manager' ) );
		}

		SBIM_Database::delete( $id );
		SBIM_Database::flush_cache( (int) $row->post_id );

		wp_send_json_success( [ 'message' => __( 'Schema deleted.', 'schema-bulk-import-manager' ) ] );
	}

	public function handle_bulk_delete(): void {
		$this->verify_nonce();

		$ids = array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) );
		if ( empty( $ids ) ) {
			$this->error( __( 'No IDs provided.', 'schema-bulk-import-manager' ) );
		}

		// Flush caches for each before deleting.
		foreach ( $ids as $id ) {
			$row = SBIM_Database::get( $id );
			if ( $row ) {
				SBIM_Database::flush_cache( (int) $row->post_id );
			}
		}

		$count = SBIM_Database::bulk_delete( $ids );
		SBIM_Database::flush_all_cache();

		wp_send_json_success( [
			'deleted' => $count,
			'message' => sprintf(
				/* translators: %d number of deleted schemas */
				_n( '%d schema deleted.', '%d schemas deleted.', $count, 'schema-bulk-import-manager' ),
				$count
			),
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function verify_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbim_ajax_nonce' ) ) {
			$this->error( __( 'Security verification failed.', 'schema-bulk-import-manager' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Insufficient permissions.', 'schema-bulk-import-manager' ) );
		}
	}

	private function error( string $message ): void {
		wp_send_json_error( [ 'message' => $message ] );
		exit;
	}
}