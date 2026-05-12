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
		add_filter( 'static_archive_post_previous_post', array( __CLASS__, 'previous_post' ), 5, 2 );
		add_filter( 'static_archive_post_next_post', array( __CLASS__, 'next_post' ), 5, 2 );
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
	 * Get the previous post for WordPress post navigation.
	 *
	 * @param WP_Post|null $previous Previous post object, or null.
	 * @param WP_Post      $wp_post  Current post object.
	 * @return WP_Post|null
	 */
	public static function previous_post( $previous, $wp_post ) {
		if ( $previous || 'post' !== $wp_post->post_type ) {
			return $previous;
		}

		return self::get_adjacent_post( $wp_post, 'previous' );
	}

	/**
	 * Get the next post for WordPress post navigation.
	 *
	 * @param WP_Post|null $next    Next post object, or null.
	 * @param WP_Post      $wp_post Current post object.
	 * @return WP_Post|null
	 */
	public static function next_post( $next, $wp_post ) {
		if ( $next || 'post' !== $wp_post->post_type ) {
			return $next;
		}

		return self::get_adjacent_post( $wp_post, 'next' );
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

	/**
	 * Get an adjacent post.
	 *
	 * @param WP_Post $wp_post   Current post object.
	 * @param string  $direction Adjacent direction: previous or next.
	 * @return WP_Post|null
	 */
	private static function get_adjacent_post( $wp_post, $direction ) {
		$is_previous = 'previous' === $direction;
		$date_key    = $is_previous ? 'before' : 'after';
		$query       = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'orderby'             => 'date',
			'order'               => $is_previous ? 'DESC' : 'ASC',
			'exclude'             => array( $wp_post->ID ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'date_query'          => array(
				array(
					$date_key   => $wp_post->post_date,
					'column'    => 'post_date',
					'inclusive' => false,
				),
			),
		);
		$posts       = get_posts( $query );

		return empty( $posts ) ? null : $posts[0];
	}
}
