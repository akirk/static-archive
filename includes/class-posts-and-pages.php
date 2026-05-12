<?php
/**
 * Built-in WordPress post and page support.
 */

class Static_Archive_Posts_And_Pages {

	/**
	 * Register the built-in post/page filters.
	 */
	public static function register() {
		add_filter( 'static_archive_post_types', array( __CLASS__, 'add_post_types' ) );
		add_filter( 'static_archive_post_html', array( __CLASS__, 'render_html' ), 5, 2 );
		add_filter( 'static_archive_post_markdown', array( __CLASS__, 'render_markdown' ), 5, 4 );
	}

	/**
	 * Add WordPress posts and pages to Static Archive.
	 *
	 * @param string[] $post_types Post type names.
	 * @return string[]
	 */
	public static function add_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			$post_types = array();
		}

		$post_types[] = 'post';
		$post_types[] = 'page';

		return $post_types;
	}

	/**
	 * Render HTML for WordPress posts and pages.
	 *
	 * @param string  $html    Raw or previously filtered HTML body.
	 * @param WP_Post $wp_post Post object.
	 * @return string
	 */
	public static function render_html( $html, $wp_post ) {
		if ( ! self::supports( $wp_post ) ) {
			return $html;
		}
		if ( (string) $html !== (string) $wp_post->post_content ) {
			return $html;
		}

		return apply_filters( 'the_content', $wp_post->post_content );
	}

	/**
	 * Render Markdown for WordPress posts and pages.
	 *
	 * @param string|null              $markdown  Markdown body, or null.
	 * @param WP_Post                  $wp_post   Post object.
	 * @param Static_Archive_Generator $generator Generator instance.
	 * @param string                   $html      Filtered HTML body.
	 * @return string|null
	 */
	public static function render_markdown( $markdown, $wp_post, $generator, $html ) {
		if ( ! self::supports( $wp_post ) ) {
			return $markdown;
		}
		if ( null !== $markdown ) {
			return $markdown;
		}

		return $generator->html_to_markdown( $html );
	}

	/**
	 * Check whether a post uses one of Static Archive's built-in post types.
	 *
	 * @param WP_Post $wp_post Post object.
	 * @return bool
	 */
	private static function supports( $wp_post ) {
		return in_array( $wp_post->post_type, array( 'post', 'page' ), true );
	}
}
