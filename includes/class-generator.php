<?php

class Static_Archive_Generator {

	private $output_dir;
	private $upload_baseurl;
	private $blog_name;
	private $blog_description;
	private $lang;
	private $suffix;

	public function __construct() {
		$upload_dir           = wp_get_upload_dir();
		$this->output_dir     = $upload_dir['basedir'];
		$this->upload_baseurl = $upload_dir['baseurl'];
		$this->blog_name        = get_bloginfo( 'name' );
		$this->blog_description = get_bloginfo( 'description' );
		$this->lang           = substr( get_locale(), 0, 2 );
		$this->suffix         = self::get_filename_suffix();
	}

	/**
	 * Get (or generate) the filename suffix used for all archive HTML files.
	 *
	 * @return string e.g. '-xK4mQ9p' or '' if user cleared it.
	 */
	public static function get_filename_suffix() {
		$suffix = get_option( 'static_archive_filename_suffix', false );
		if ( false === $suffix ) {
			$suffix = '-' . wp_generate_password( 8, false );
			update_option( 'static_archive_filename_suffix', $suffix );
		}
		return $suffix;
	}

	/**
	 * Build a filename with the suffix, e.g. 'post-123' → 'post-123-xK4mQ9p.html'.
	 *
	 * @param string $base Base name without extension.
	 * @return string
	 */
	private function filename( $base ) {
		return $base . $this->suffix . '.html';
	}

	/**
	 * Get the relative path for a post's HTML file (e.g. '2024/post-123-xK4mQ9p.html').
	 */
	private function get_post_relative_path( $post ) {
		$year = date( 'Y', strtotime( $post->post_date ) );
		return $year . '/' . $this->filename( 'post-' . $post->ID );
	}

	/**
	 * Get the absolute file path for a post's HTML file.
	 */
	private function get_post_file_path( $post ) {
		return $this->output_dir . '/' . $this->get_post_relative_path( $post );
	}

	/**
	 * Get the main index filename.
	 */
	public function get_index_filename() {
		return $this->filename( 'archive' );
	}

	/**
	 * Get the year archive filenames.
	 *
	 * @return array { asc: string, desc: string }
	 */
	private function get_year_archive_filenames() {
		return array(
			'asc'  => $this->filename( 'archive' ),
			'desc' => $this->filename( 'latest' ),
		);
	}

	/**
	 * Generate a single post's HTML file.
	 *
	 * @param int $post_id
	 * @return string Status: 'created', 'updated', 'unchanged', or 'skipped'.
	 */
	public function generate_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || 'post' !== $post->post_type ) {
			return 'skipped';
		}

		$file = $this->get_post_file_path( $post );
		$existed = file_exists( $file );

		// Get adjacent posts for navigation.
		$original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$prev = get_previous_post();
		$next = get_next_post();

		$prev_nav_title = $prev ? $this->get_display_title( $prev ) : '';
		$next_nav_title = $next ? $this->get_display_title( $next ) : '';

		$prev_link = $prev ? '<a href="../' . esc_attr( $this->get_post_relative_path( $prev ) ) . '">&larr; ' . esc_html( $prev_nav_title ) . '</a>' : '';
		$next_link = $next ? '<a href="../' . esc_attr( $this->get_post_relative_path( $next ) ) . '">' . esc_html( $next_nav_title ) . ' &rarr;</a>' : '';

		$GLOBALS['post'] = $original_post;
		if ( $original_post ) {
			setup_postdata( $original_post );
		}

		// Render content.
		$content = apply_filters( 'the_content', $post->post_content );
		$content = $this->rewrite_urls( $content );

		// Template variables.
		$post_title    = $post->post_title;
		$page_title    = $this->get_display_title( $post );
		$post_date     = date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) );
		$post_date_iso = gmdate( 'Y-m-d', strtotime( $post->post_date ) );
		$post_author   = get_the_author_meta( 'display_name', $post->post_author );
		$blog_name        = $this->blog_name;
		$blog_description = $this->blog_description;
		$lang             = $this->lang;
		$index_url         = '../' . $this->get_index_filename();
		$style_url         = '../style.css';
		$year_archive_url  = $this->filename( 'latest' );

		ob_start();
		include dirname( __DIR__ ) . '/templates/post.php';
		$html = ob_get_clean();

		// Check if content changed.
		if ( $existed && file_get_contents( $file ) === $html ) {
			return 'unchanged';
		}

		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, $html );
		return $existed ? 'updated' : 'created';
	}

	/**
	 * Delete the HTML file for a post.
	 */
	public function delete_post_html( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$file = $this->get_post_file_path( $post );
		if ( file_exists( $file ) ) {
			unlink( $file );
			return true;
		}

		return false;
	}

	/**
	 * Generate the main index listing all posts, with a year nav at top.
	 */
	public function generate_index() {
		$posts_query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		$years   = array();
		$authors = array();
		foreach ( $posts_query->posts as $post ) {
			$year   = date( 'Y', strtotime( $post->post_date ) );
			$author = get_the_author_meta( 'display_name', $post->post_author );

			$years[ $year ][] = array(
				'title'    => $this->get_display_title( $post ),
				'href'     => $this->get_post_relative_path( $post ),
				'date'     => date_i18n( 'j. F', strtotime( $post->post_date ) ),
				'date_iso' => gmdate( 'Y-m-d', strtotime( $post->post_date ) ),
				'author'   => $author,
			);

			$authors[ $author ] = true;
		}

		$stats = array(
			'total'      => count( $posts_query->posts ),
			'date_first' => $posts_query->posts ? date_i18n( 'j. F Y', strtotime( end( $posts_query->posts )->post_date ) ) : '',
			'date_last'  => $posts_query->posts ? date_i18n( 'j. F Y', strtotime( $posts_query->posts[0]->post_date ) ) : '',
			'authors'    => array_keys( $authors ),
		);

		$year_filenames = $this->get_year_archive_filenames();
		$blog_name        = $this->blog_name;
		$blog_description = $this->blog_description;
		$lang             = $this->lang;
		$style_url        = 'style.css';

		ob_start();
		include dirname( __DIR__ ) . '/templates/index.php';
		$html = ob_get_clean();

		file_put_contents( $this->output_dir . '/' . $this->get_index_filename(), $html );
	}

	/**
	 * Generate year archive pages for all years that have posts.
	 */
	public function generate_year_archives() {
		$posts_query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );

		$years = array();
		foreach ( $posts_query->posts as $post ) {
			$year = date( 'Y', strtotime( $post->post_date ) );
			$years[ $year ][] = $post;
		}

		foreach ( $years as $year => $posts ) {
			$this->generate_year_archive( $year, $posts );
		}
	}

	/**
	 * Generate both ASC and DESC year archive pages.
	 */
	public function generate_year_archive( $year, $posts = null ) {
		if ( null === $posts ) {
			$posts = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'date_query'     => array(
					array( 'year' => (int) $year ),
				),
			) );
		}

		if ( empty( $posts ) ) {
			return;
		}

		$entries = array();
		foreach ( $posts as $post ) {
			$content = apply_filters( 'the_content', $post->post_content );
			$content = $this->rewrite_urls( $content );

			$entries[] = array(
				'title'    => $post->post_title,
				'date'     => date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) ),
				'date_iso' => gmdate( 'Y-m-d', strtotime( $post->post_date ) ),
				'author'   => get_the_author_meta( 'display_name', $post->post_author ),
				'content'  => $content,
				'href'     => $this->filename( 'post-' . $post->ID ),
			);
		}

		$filenames = $this->get_year_archive_filenames();
		$blog_name        = $this->blog_name;
		$blog_description = $this->blog_description;
		$lang             = $this->lang;
		$index_url        = '../' . $this->get_index_filename();
		$style_url = '../style.css';
		$dir       = $this->output_dir . '/' . $year;
		wp_mkdir_p( $dir );

		// ASC version (oldest first).
		$order     = 'asc';
		$other_url = $filenames['desc'];
		ob_start();
		include dirname( __DIR__ ) . '/templates/year.php';
		file_put_contents( $dir . '/' . $filenames['asc'], ob_get_clean() );

		// DESC version (newest first).
		$order     = 'desc';
		$other_url = $filenames['asc'];
		$entries   = array_reverse( $entries );
		ob_start();
		include dirname( __DIR__ ) . '/templates/year.php';
		file_put_contents( $dir . '/' . $filenames['desc'], ob_get_clean() );
	}

	/**
	 * Delete all generated archive files. Returns the number of files deleted.
	 */
	public function delete_all() {
		$files = array(
			$this->output_dir . '/archive' . $this->suffix . '.html',
			$this->output_dir . '/style.css',
		);

		foreach ( glob( $this->output_dir . '/[0-9][0-9][0-9][0-9]' ) as $year_dir ) {
			if ( ! is_dir( $year_dir ) ) {
				continue;
			}
			$files[] = $year_dir . '/archive' . $this->suffix . '.html';
			$files[] = $year_dir . '/latest' . $this->suffix . '.html';
			foreach ( glob( $year_dir . '/post-*' . $this->suffix . '.html' ) as $post_file ) {
				$files[] = $post_file;
			}
		}

		$deleted = 0;
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Copy the stylesheet to the output directory.
	 */
	public function copy_stylesheet() {
		copy(
			dirname( __DIR__ ) . '/templates/style.css',
			$this->output_dir . '/style.css'
		);
	}

	/**
	 * Generate all posts and the index.
	 */
	public function generate_all( $progress = null ) {
		$this->copy_stylesheet();

		$post_ids = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );

		$stats = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
			'total'     => count( $post_ids ),
		);

		foreach ( $post_ids as $i => $post_id ) {
			$status = $this->generate_post( $post_id );
			$stats[ $status ]++;

			if ( $progress ) {
				$post = get_post( $post_id );
				$progress( $i + 1, $stats['total'], $status, $post ? $post->post_name : '' );
			}
		}

		$this->generate_index();
		$this->generate_year_archives();

		return $stats;
	}

	/**
	 * Generate a batch of posts by offset.
	 */
	public function generate_batch( $offset, $limit = 50 ) {
		$post_ids = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );

		$total = count( $post_ids );
		$batch = array_slice( $post_ids, $offset, $limit );

		if ( $offset === 0 ) {
			$this->copy_stylesheet();
		}

		$stats = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
		);

		$first_date = '';
		$last_date  = '';

		foreach ( $batch as $post_id ) {
			$status = $this->generate_post( $post_id );
			$stats[ $status ]++;

			$post = get_post( $post_id );
			if ( $post ) {
				$d = date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) );
				if ( ! $first_date ) {
					$first_date = $d;
				}
				$last_date = $d;
			}
		}

		$next_offset = $offset + $limit;
		$has_more    = $next_offset < $total;

		$phase = 'posts';
		if ( ! $has_more ) {
			$phase = 'indexes';
			$this->generate_index();
			$this->generate_year_archives();
		}

		return array(
			'stats'       => $stats,
			'has_more'    => $has_more,
			'next_offset' => $next_offset,
			'total'       => $total,
			'processed'   => $offset + count( $batch ),
			'first_date'  => $first_date,
			'last_date'   => $last_date,
			'phase'       => $phase,
		);
	}

	/**
	 * Verify the archive: find missing, orphaned, and outdated files.
	 */
	public function verify() {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		$expected_files = array();
		$missing        = array();
		$outdated       = array();

		foreach ( $posts as $post ) {
			$file     = $this->get_post_file_path( $post );
			$rel_path = $this->get_post_relative_path( $post );
			$expected_files[ $rel_path ] = true;

			if ( ! file_exists( $file ) ) {
				$missing[] = array(
					'id'    => $post->ID,
					'slug'  => $post->post_name,
					'title' => $post->post_title,
				);
			} elseif ( filemtime( $file ) < strtotime( $post->post_modified ) ) {
				$outdated[] = array(
					'id'    => $post->ID,
					'slug'  => $post->post_name,
					'title' => $post->post_title,
				);
			}
		}

		// Find orphaned post-*.html files in year directories.
		$orphaned = array();
		foreach ( glob( $this->output_dir . '/*/post-*.html' ) as $file ) {
			$year     = basename( dirname( $file ) );
			$basename = basename( $file );
			$rel_path = $year . '/' . $basename;
			if ( ! isset( $expected_files[ $rel_path ] ) ) {
				$orphaned[] = $rel_path;
			}
		}

		return array(
			'missing'        => $missing,
			'orphaned'       => $orphaned,
			'outdated'       => $outdated,
			'total_posts'    => count( $posts ),
			'total_archived' => count( $posts ) - count( $missing ),
		);
	}

	/**
	 * Get a display title for a post, falling back to excerpt, content snippet, or date.
	 */
	private function get_display_title( $post ) {
		if ( $post->post_title ) {
			return $post->post_title;
		}
		if ( $post->post_excerpt ) {
			return wp_trim_words( $post->post_excerpt, 10, "\xe2\x80\xa6" );
		}
		$snippet = wp_trim_words( wp_strip_all_tags( $post->post_content ), 10, "\xe2\x80\xa6" );
		if ( $snippet ) {
			return $snippet;
		}
		return date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) );
	}

	/**
	 * Rewrite absolute upload URLs to relative paths.
	 * Since post HTML files live in {year}/ subdirectories, all paths use ../ prefix.
	 */
	private function rewrite_urls( $content ) {
		$pattern = '/(?:https?:)?\/\/' . preg_quote( wp_parse_url( $this->upload_baseurl, PHP_URL_HOST ), '/' )
			. preg_quote( wp_parse_url( $this->upload_baseurl, PHP_URL_PATH ), '/' ) . '\//';
		$content = preg_replace( $pattern, '../', $content );

		// Rewrite internal post permalinks to post-{id}.html files.
		$site_url = preg_quote( trailingslashit( home_url() ), '/' );
		$content = preg_replace_callback(
			'/href=["\']' . $site_url . '([a-z0-9-]+)\/?["\']/',
			function( $matches ) {
				$slug = $matches[1];
				$post = get_page_by_path( $slug, OBJECT, 'post' );
				if ( $post && 'publish' === $post->post_status ) {
					return 'href="../' . $this->get_post_relative_path( $post ) . '"';
				}
				return $matches[0];
			},
			$content
		);

		return $content;
	}

	/**
	 * Get the output directory path.
	 */
	public function get_output_dir() {
		return $this->output_dir;
	}
}
