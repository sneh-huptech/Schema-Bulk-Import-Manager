<?php
/**
 * Frontend Schema Injector — outputs JSON-LD verbatim in <head>.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_Frontend {

	/** @var SBIM_Frontend|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priority 1 — run early in <head> before theme scripts.
		add_action( 'wp_head', [ $this, 'output_schema' ], 1 );
	}

	/**
	 * Output the schema markup for the current page.
	 * Schema is output verbatim — zero modification.
	 */
	public function output_schema(): void {
		if ( is_admin() ) {
			return;
		}

		$post_id = $this->get_current_post_id();
		if ( ! $post_id ) {
			return;
		}

		$schema = SBIM_Database::get_schema_by_post_id( $post_id );
		if ( empty( $schema ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// Output verbatim — schema must not be modified or escaped.
		echo "\n" . $schema . "\n";
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get the current queried post ID.
	 * Handles homepage (static page), singular posts, and Elementor pages.
	 *
	 * @return int|null
	 */
	private function get_current_post_id(): ?int {
		// Static front page.
		if ( is_front_page() && is_page() ) {
			return (int) get_option( 'page_on_front' );
		}

		// Blog posts page.
		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			return $page_for_posts > 0 ? $page_for_posts : null;
		}

		// Singular post/page/CPT.
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		return null;
	}
}