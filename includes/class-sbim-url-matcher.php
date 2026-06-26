<?php
/**
 * URL Matcher — resolves full URLs to WordPress post IDs.
 * Supports pages, posts, CPTs, and Elementor pages.
 *
 * @package SchemaBulkImportManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBIM_URL_Matcher {

	/**
	 * Resolve a full URL to a WordPress post ID.
	 *
	 * @param  string $url  Full URL e.g. https://example.com/service-a/
	 * @return int|null     Post ID if found, null otherwise.
	 */
	public static function url_to_post_id( string $url ): ?int {
		$url = trim( $url );

		// 1. Check homepage.
		if ( self::is_homepage( $url ) ) {
			$page_id = (int) get_option( 'page_on_front' );
			return $page_id > 0 ? $page_id : 0;
		}

		// 2. Try WordPress native url_to_postid() first.
		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		// 3. Try stripping/adding trailing slash variant.
		$alt_url = self::toggle_trailing_slash( $url );
		$post_id = url_to_postid( $alt_url );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		// 4. Try matching by post permalink across all public post types.
		$post_id = self::match_by_permalink( $url );
		if ( $post_id ) {
			return $post_id;
		}

		// 5. Try matching by slug extracted from URL.
		$post_id = self::match_by_slug( $url );
		if ( $post_id ) {
			return $post_id;
		}

		return null;
	}

	/**
	 * Check if a URL points to the site homepage.
	 */
	private static function is_homepage( string $url ): bool {
		$home     = trailingslashit( home_url() );
		$url_norm = trailingslashit( $url );
		return $url_norm === $home;
	}

	/**
	 * Toggle trailing slash on a URL.
	 */
	private static function toggle_trailing_slash( string $url ): string {
		return substr( $url, -1 ) === '/'
			? rtrim( $url, '/' )
			: trailingslashit( $url );
	}

	/**
	 * Match URL against all public post type permalinks.
	 * Used when url_to_postid() fails (e.g. some CPT setups).
	 *
	 * @return int|null
	 */
	private static function match_by_permalink( string $url ): ?int {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		$query = new WP_Query( [
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		foreach ( $query->posts as $pid ) {
			$permalink = get_permalink( $pid );
			if ( ! $permalink ) {
				continue;
			}
			// Compare normalised (trailing slash stripped, lowercased).
			if ( self::normalise( $permalink ) === self::normalise( $url ) ) {
				return (int) $pid;
			}
		}

		return null;
	}

	/**
	 * Match by extracting the slug from the URL and querying.
	 *
	 * @return int|null
	 */
	private static function match_by_slug( string $url ): ?int {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( empty( $path ) ) {
			return null;
		}

		$slug = basename( untrailingslashit( $path ) );
		if ( empty( $slug ) ) {
			return null;
		}

		$post_types = get_post_types( [ 'public' => true ], 'names' );

		$query = new WP_Query( [
			'name'           => $slug,
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		return null;
	}

	/**
	 * Normalise a URL for comparison:
	 * - lowercase
	 * - remove trailing slash
	 * - strip scheme variations (http vs https)
	 */
	private static function normalise( string $url ): string {
		$url = strtolower( $url );
		$url = preg_replace( '#^https?://#', '', $url );
		$url = rtrim( $url, '/' );
		return $url;
	}

	/**
	 * Get human-readable post type label for a post ID.
	 */
	public static function get_post_type_label( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$pt = get_post_type_object( $post->post_type );
		return $pt ? $pt->labels->singular_name : $post->post_type;
	}
}