<?php
/**
 * Schema validator — validates JSON-LD script blocks.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_Validator {

	/**
	 * Validate a full schema block (including <script> tags).
	 *
	 * @param  string $schema_markup  The raw schema markup from CSV.
	 * @param  int    $row_number     For error reporting.
	 * @return array { valid: bool, error: string, json: array|null }
	 */
	public static function validate( string $schema_markup, int $row_number = 0 ): array {
		$schema_markup = trim( $schema_markup );

		// 1. Must not be empty.
		if ( empty( $schema_markup ) ) {
			return self::fail( "Row {$row_number}: Schema markup is empty." );
		}

		// 2. Must contain opening script tag.
		if ( stripos( $schema_markup, '<script' ) === false ) {
			return self::fail( "Row {$row_number}: Missing <script> opening tag." );
		}

		// 3. Must contain type="application/ld+json".
		if ( stripos( $schema_markup, 'application/ld+json' ) === false ) {
			return self::fail( "Row {$row_number}: Script tag must include type=\"application/ld+json\"." );
		}

		// 4. Must contain closing </script> tag.
		if ( stripos( $schema_markup, '</script>' ) === false ) {
			return self::fail( "Row {$row_number}: Missing </script> closing tag." );
		}

		// 5. Extract inner JSON between the script tags.
		$inner_json = self::extract_json( $schema_markup );

		if ( null === $inner_json ) {
			return self::fail( "Row {$row_number}: Could not extract JSON content from script block." );
		}

		if ( empty( trim( $inner_json ) ) ) {
			return self::fail( "Row {$row_number}: JSON-LD content is empty inside script tag." );
		}

		// 6. Validate JSON structure.
		$decoded = json_decode( $inner_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$json_error = json_last_error_msg();
			return self::fail( "Row {$row_number}: Invalid JSON — {$json_error}." );
		}

		if ( ! is_array( $decoded ) ) {
			return self::fail( "Row {$row_number}: JSON-LD must be a JSON object or array." );
		}

		// 7. Validate JSON-LD: must have @context or @graph.
		if ( ! isset( $decoded['@context'] ) && ! isset( $decoded['@graph'] ) ) {
			return self::fail( "Row {$row_number}: JSON-LD must contain \"@context\" or \"@graph\" property." );
		}

		return [
			'valid' => true,
			'error' => '',
			'json'  => $decoded,
		];
	}

	/**
	 * Extract JSON string from inside <script> tags.
	 */
	public static function extract_json( string $markup ): ?string {
		// Use regex to grab content between the script tags.
		if ( preg_match( '/<script[^>]*>(.*?)<\/script>/is', $markup, $matches ) ) {
			return trim( $matches[1] );
		}
		return null;
	}

	/**
	 * Validate a URL string (basic format check — existence is handled by URL matcher).
	 */
	public static function validate_url( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Build a failure response.
	 */
	private static function fail( string $message ): array {
		return [
			'valid' => false,
			'error' => $message,
			'json'  => null,
		];
	}
}