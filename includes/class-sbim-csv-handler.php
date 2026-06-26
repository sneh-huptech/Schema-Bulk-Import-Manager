<?php
/**
 * CSV Handler — parses uploaded CSV files and processes rows.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_CSV_Handler {

	/** Maximum allowed file size: 10 MB */
	const MAX_FILE_SIZE = 10 * 1024 * 1024;

	/** Allowed MIME types */
	const ALLOWED_MIME_TYPES = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ];

	/**
	 * Parse and validate an uploaded CSV file.
	 *
	 * @param  array $file  $_FILES['csv_file'] entry.
	 * @return array { success: bool, error: string, rows: array }
	 */
	public static function parse_upload( array $file ): array {
		// 1. Verify upload integrity.
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return self::fail( 'File upload error: ' . self::upload_error_message( $file['error'] ) );
		}

		// 2. Check file size.
		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return self::fail( 'File too large. Maximum allowed size is 10 MB.' );
		}

		// 3. Validate file extension.
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $ext !== 'csv' ) {
			return self::fail( 'Invalid file type. Only .csv files are allowed.' );
		}

		// 4. Validate MIME type via wp_check_filetype.
		$check = wp_check_filetype( $file['name'] );
		// Accept common CSV-reported types.
		$accepted = [ 'csv', 'txt' ];
		if ( ! empty( $check['ext'] ) && ! in_array( $check['ext'], $accepted, true ) ) {
			return self::fail( 'Invalid MIME type. Please upload a valid CSV file.' );
		}

		// 5. Parse the CSV.
		return self::parse_file( $file['tmp_name'] );
	}

	/**
	 * Parse a CSV file from disk.
	 *
	 * @param  string $filepath  Absolute path to the CSV file.
	 * @return array { success: bool, error: string, rows: array }
	 */
	public static function parse_file( string $filepath ): array {
		if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
			return self::fail( 'Cannot read uploaded file.' );
		}

		$handle = fopen( $filepath, 'r' );
		if ( ! $handle ) {
			return self::fail( 'Failed to open CSV file.' );
		}

		// Detect BOM and skip if present.
		$bom = fread( $handle, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		$rows         = [];
		$line_number  = 0;
		$header_found = false;

		while ( ( $data = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
			$line_number++;

			// Skip completely empty lines.
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			// Header row detection.
			if ( ! $header_found ) {
				if ( self::is_header_row( $data ) ) {
					$header_found = true;
					continue;
				}
				// No header — treat first row as data.
				$header_found = true;
			}

			// Must have at least 2 columns.
			if ( count( $data ) < 2 ) {
				$rows[] = [
					'row_number'    => $line_number,
					'url'           => $data[0] ?? '',
					'schema_markup' => '',
					'parse_error'   => 'Row has fewer than 2 columns.',
				];
				continue;
			}

			$url           = trim( $data[0] );
			$schema_markup = trim( $data[1] );

			// Handle multi-column schema (JSON with commas might have split).
			// Re-join columns 2+ if the JSON was split across columns.
			if ( count( $data ) > 2 ) {
				$schema_markup = implode( ',', array_slice( $data, 1 ) );
				$schema_markup = trim( $schema_markup );
			}

			$rows[] = [
				'row_number'    => $line_number,
				'url'           => $url,
				'schema_markup' => $schema_markup,
				'parse_error'   => '',
			];
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return self::fail( 'CSV file contains no data rows.' );
		}

		return [
			'success' => true,
			'error'   => '',
			'rows'    => $rows,
		];
	}

	/**
	 * Generate a sample CSV file and stream it to the browser.
	 */
	public static function output_sample_csv(): void {
		$filename = 'schema-bulk-import-sample.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// BOM for Excel compatibility.
		fputs( $output, "\xEF\xBB\xBF" );

		// Header row.
		fputcsv( $output, [ 'URL', 'JSON-LD' ] );

		// Example row 1 — FAQ schema.
		$example_url_1    = home_url( '/your-page-url/' );
		$example_schema_1 = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What services do you offer?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We offer a wide range of professional services."
      }
    }
  ]
}
</script>';
		fputcsv( $output, [ $example_url_1, $example_schema_1 ] );

		// Example row 2 — LocalBusiness schema.
		$example_url_2    = home_url( '/about/' );
		$example_schema_2 = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "LocalBusiness",
      "@id": "/#localbusiness",
      "name": "Your Business Name",
      "url": "' . home_url() . '",
      "telephone": "+1-000-000-0000",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "123 Main Street",
        "addressLocality": "Your City",
        "addressRegion": "ST",
        "postalCode": "00000",
        "addressCountry": "US"
      }
    }
  ]
}
</script>';
		fputcsv( $output, [ $example_url_2, $example_schema_2 ] );

		fclose( $output );
		exit;
	}

	/**
	 * Generate export CSV of all saved schemas.
	 *
	 * @param array[] $records
	 */
	public static function output_export_csv( array $records ): void {
		$filename = 'schema-export-' . date( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputs( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, [ 'URL', 'JSON-LD', 'Status', 'Post ID', 'Date Added', 'Last Updated' ] );

		foreach ( $records as $row ) {
			fputcsv( $output, [
				$row['url'],
				$row['schema_markup'],
				$row['status'],
				$row['post_id'],
				$row['created_at'],
				$row['updated_at'],
			] );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Generate import report CSV.
	 *
	 * @param array $report { imported, skipped, not_found, invalid_json, duplicates, rows }
	 */
	public static function output_report_csv( array $report ): void {
		$filename = 'schema-import-report-' . date( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputs( $output, "\xEF\xBB\xBF" );

		// Summary.
		fputcsv( $output, [ 'Summary' ] );
		fputcsv( $output, [ 'Total Rows',     $report['total'] ] );
		fputcsv( $output, [ 'Imported',        $report['imported'] ] );
		fputcsv( $output, [ 'Skipped',         $report['skipped'] ] );
		fputcsv( $output, [ 'Not Found',       $report['not_found'] ] );
		fputcsv( $output, [ 'Invalid JSON',    $report['invalid_json'] ] );
		fputcsv( $output, [ 'Duplicate URLs',  $report['duplicates'] ] );
		fputcsv( $output, [] );

		// Detail rows.
		fputcsv( $output, [ 'Row #', 'URL', 'Status', 'Post ID', 'Note' ] );
		foreach ( $report['rows'] as $row ) {
			fputcsv( $output, [
				$row['row_number'],
				$row['url'],
				$row['result'],
				$row['post_id'] ?? '',
				$row['note'] ?? '',
			] );
		}

		fclose( $output );
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function is_header_row( array $data ): bool {
		$first = strtolower( trim( $data[0] ?? '' ) );
		return in_array( $first, [ 'url', 'page url', 'page_url' ], true );
	}

	private static function fail( string $message ): array {
		return [ 'success' => false, 'error' => $message, 'rows' => [] ];
	}

	private static function upload_error_message( int $code ): string {
		$messages = [
			UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
			UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
			UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
			UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension.',
		];
		return $messages[ $code ] ?? 'Unknown upload error (code ' . $code . ').';
	}
}