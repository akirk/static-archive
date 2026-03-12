<?php

class Static_Archive_Generator {

	private $output_dir;
	private $upload_baseurl;
	private $blog_name;
	private $blog_description;
	private $lang;
	private $suffix;
	private $post_types;
	private $output_format;

	public function __construct() {
		$upload_dir             = wp_get_upload_dir();
		$this->output_dir       = $upload_dir['basedir'];
		$this->upload_baseurl   = $upload_dir['baseurl'];
		$this->blog_name        = get_bloginfo( 'name' );
		$this->blog_description = get_bloginfo( 'description' );
		$this->lang             = substr( get_locale(), 0, 2 );
		$this->suffix           = self::get_filename_suffix();
		$this->post_types       = self::get_post_types();
		$this->output_format    = get_option( 'static_archive_output_format', 'html' );
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
	 * Get the configured post types.
	 */
	public static function get_post_types() {
		return get_option( 'static_archive_post_types', array( 'post', 'page' ) );
	}

	public function should_output_html() {
		return 'markdown' !== $this->output_format;
	}

	public function should_output_markdown() {
		return 'html' !== $this->output_format;
	}

	/**
	 * Build a filename with the suffix, e.g. 'post-123' → 'post-123-xK4mQ9p.html'.
	 *
	 * @param string $base Base name without extension.
	 * @param string $ext  File extension.
	 * @return string
	 */
	public function filename( $base, $ext = 'html' ) {
		return $base . $this->suffix . '.' . $ext;
	}

	/**
	 * Get the ID of the static front page, or 0 if the front page shows latest posts.
	 */
	private function get_front_page_id() {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return 0;
		}
		return (int) get_option( 'page_on_front', 0 );
	}

	/**
	 * Get the relative path for a post's file (e.g. '2024/post-123-xK4mQ9p.html').
	 *
	 * Pages use slug-based paths reflecting the page hierarchy (e.g. 'pages/about/team-xK4mQ9p.html').
	 * The static front page lives at the root level (e.g. 'home-xK4mQ9p.html').
	 */
	public function get_post_relative_path( $wp_post, $ext = 'html' ) {
		if ( 'page' === $wp_post->post_type ) {
			$front_page_id = $this->get_front_page_id();
			if ( $front_page_id && (int) $wp_post->ID === $front_page_id ) {
				return $this->filename( $wp_post->post_name, $ext );
			}
			$uri    = get_page_uri( $wp_post );
			$parts  = explode( '/', $uri );
			$slug   = array_pop( $parts );
			$subdir = implode( '/', $parts );
			return 'pages/' . ( $subdir ? $subdir . '/' : '' ) . $this->filename( $slug, $ext );
		}
		$year = gmdate( 'Y', strtotime( $wp_post->post_date ) );
		return $year . '/' . $this->filename( $wp_post->post_type . '-' . $wp_post->ID, $ext );
	}

	/**
	 * Get the filename of the static front page archive file, or null if none.
	 */
	public function get_front_page_filename( $ext = 'html' ) {
		$front_page_id = $this->get_front_page_id();
		if ( ! $front_page_id ) {
			return null;
		}
		$wp_post = get_post( $front_page_id );
		if ( ! $wp_post || 'publish' !== $wp_post->post_status ) {
			return null;
		}
		return $this->filename( $wp_post->post_name, $ext );
	}

	/**
	 * Get the absolute file path for a post's file.
	 */
	private function get_post_file_path( $wp_post, $ext = 'html' ) {
		return $this->output_dir . '/' . $this->get_post_relative_path( $wp_post, $ext );
	}

	/**
	 * Get the main index filename.
	 */
	public function get_index_filename( $ext = 'html' ) {
		return $this->filename( 'archive', $ext );
	}

	/**
	 * Get the stylesheet filename.
	 */
	public function get_style_filename() {
		return 'style' . $this->suffix . '.css';
	}

	/**
	 * Get the year archive filenames.
	 *
	 * @return array { asc: string, desc: string }
	 */
	public function get_year_archive_filenames( $ext = 'html' ) {
		return array(
			'asc'  => $this->filename( 'archive', $ext ),
			'desc' => $this->filename( 'latest', $ext ),
		);
	}

	/**
	 * Get the post types that use year-based directory structure (everything except page).
	 */
	public function get_dated_post_types() {
		return array_values( array_diff( $this->post_types, array( 'page' ) ) );
	}

	/**
	 * Write a file, returning 'created', 'updated', or 'unchanged'.
	 */
	public function write_file( $file, $content, $mtime = 0 ) {
		$existed = file_exists( $file );

		if ( $existed && file_get_contents( $file ) === $content ) {
			if ( $mtime ) {
				touch( $file, $mtime );
			}
			return 'unchanged';
		}

		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, $content );
		if ( $mtime ) {
			touch( $file, $mtime );
		}
		return $existed ? 'updated' : 'created';
	}

	/**
	 * Generate a single post's files.
	 *
	 * @param int $post_id
	 * @return string Status: 'created', 'updated', 'unchanged', or 'skipped'.
	 */
	public function generate_post( $post_id ) {
		$wp_post = get_post( $post_id );
		if ( ! $wp_post || 'publish' !== $wp_post->post_status || ! in_array( $wp_post->post_type, $this->post_types, true ) ) {
			return 'skipped';
		}

		$mtime = strtotime( $wp_post->post_modified );

		// Get adjacent posts for navigation (only for post type).
		$prev_link = '';
		$next_link = '';
		if ( 'post' === $wp_post->post_type ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by setup_postdata().
			$original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by setup_postdata().
			$GLOBALS['post'] = $wp_post;
			setup_postdata( $wp_post );

			$prev = get_previous_post();
			$next = get_next_post();

			$prev_nav_title = $prev ? $this->get_display_title( $prev ) : '';
			$next_nav_title = $next ? $this->get_display_title( $next ) : '';

			$prev_link = $prev ? '<a href="../' . esc_attr( $this->get_post_relative_path( $prev ) ) . '">&larr; ' . esc_html( $prev_nav_title ) . '</a>' : '';
			$next_link = $next ? '<a href="../' . esc_attr( $this->get_post_relative_path( $next ) ) . '">' . esc_html( $next_nav_title ) . ' &rarr;</a>' : '';

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original value.
			$GLOBALS['post'] = $original_post;
			if ( $original_post ) {
				setup_postdata( $original_post );
			}
		}

		// Render content.
		$content = apply_filters( 'the_content', $wp_post->post_content );
		$content = $this->rewrite_urls( $content, dirname( $this->get_post_file_path( $wp_post ) ) );

		// Template variables.
		$post_title       = $wp_post->post_title;
		$page_title       = $this->get_display_title( $wp_post );
		$post_date        = date_i18n( get_option( 'date_format' ), strtotime( $wp_post->post_date ) );
		$post_date_iso    = gmdate( 'Y-m-d', strtotime( $wp_post->post_date ) );
		$post_author      = get_the_author_meta( 'display_name', $wp_post->post_author );
		$blog_name        = $this->blog_name;
		$blog_description = $this->blog_description;
		$lang             = $this->lang;
		$post_dir         = dirname( $this->get_post_file_path( $wp_post ) );
		$index_url        = $this->make_relative_path( $post_dir, $this->output_dir . '/' . $this->get_index_filename() );
		$style_url        = $this->make_relative_path( $post_dir, $this->output_dir . '/' . $this->get_style_filename() );
		$year_archive_url = 'post' === $wp_post->post_type ? $this->filename( 'latest' ) : $index_url;
		$archive_url      = ( $this->get_front_page_id() === (int) $wp_post->ID ) ? $index_url : '';

		$status = 'unchanged';

		if ( $this->should_output_html() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/post.php';
			$result = $this->write_file( $this->get_post_file_path( $wp_post ), ob_get_clean(), $mtime );
			if ( 'unchanged' !== $result ) {
				$status = $result;
			}
		}

		if ( $this->should_output_markdown() ) {
			$content_md = $this->html_to_markdown( $content );
			ob_start();
			include dirname( __DIR__ ) . '/templates/post.md.php';
			$result = $this->write_file( $this->get_post_file_path( $wp_post, 'md' ), ob_get_clean(), $mtime );
			if ( 'unchanged' !== $result ) {
				$status = $result;
			}
		}

		return $status;
	}

	/**
	 * Delete the files for a post.
	 */
	public function delete_post_files( $post_id ) {
		$wp_post = get_post( $post_id );
		if ( ! $wp_post ) {
			return false;
		}

		$deleted = false;
		foreach ( array( 'html', 'md' ) as $ext ) {
			$file = $this->get_post_file_path( $wp_post, $ext );
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
				$deleted = true;
			}
		}

		return $deleted;
	}

	/**
	 * Generate the main index listing all posts, with a year nav at top.
	 */
	public function generate_index() {
		$posts_query = new WP_Query(
			array(
				'post_type'      => $this->post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$front_page_id = $this->get_front_page_id();
		$front_page    = null;
		$years         = array();
		$pages         = array();
		$authors       = array();
		foreach ( $posts_query->posts as $wp_post ) {
			$author = get_the_author_meta( 'display_name', $wp_post->post_author );

			if ( 'page' === $wp_post->post_type ) {
				if ( $front_page_id && (int) $wp_post->ID === $front_page_id ) {
					$front_page = array(
						'title' => $this->get_display_title( $wp_post ),
						'href'  => $this->get_post_relative_path( $wp_post ),
					);
					continue;
				}
				$pages[] = array(
					'title' => $this->get_display_title( $wp_post ),
					'href'  => $this->get_post_relative_path( $wp_post ),
				);
				continue;
			}

			$year = gmdate( 'Y', strtotime( $wp_post->post_date ) );

			$years[ $year ][] = array(
				'title'    => $this->get_display_title( $wp_post ),
				'href'     => $this->get_post_relative_path( $wp_post ),
				'date'     => date_i18n( 'j. F', strtotime( $wp_post->post_date ) ),
				'date_iso' => gmdate( 'Y-m-d', strtotime( $wp_post->post_date ) ),
				'author'   => $author,
			);

			$authors[ $author ] = true;
		}

		$dated_posts = array_filter(
			$posts_query->posts,
			function ( $p ) {
				return 'page' !== $p->post_type;
			}
		);

		$stats = array(
			'total'      => count( $dated_posts ),
			'date_first' => $dated_posts ? date_i18n( 'j. F Y', strtotime( end( $dated_posts )->post_date ) ) : '',
			'date_last'  => $dated_posts ? date_i18n( 'j. F Y', strtotime( reset( $dated_posts )->post_date ) ) : '',
			'authors'    => array_keys( $authors ),
		);

		$year_filenames   = $this->get_year_archive_filenames();
		$blog_name        = $this->blog_name;
		$blog_description = $this->blog_description;
		$lang             = $this->lang;
		$style_url        = $this->get_style_filename();

		if ( $this->should_output_html() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/index.php';
			file_put_contents( $this->output_dir . '/' . $this->get_index_filename(), ob_get_clean() );
		}

		if ( $this->should_output_markdown() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/index.md.php';
			file_put_contents( $this->output_dir . '/' . $this->get_index_filename( 'md' ), ob_get_clean() );
		}
	}

	/**
	 * Generate year archive pages for all years that have posts.
	 */
	public function generate_year_archives() {
		$dated_types = $this->get_dated_post_types();
		if ( empty( $dated_types ) ) {
			return;
		}

		$posts_query = new WP_Query(
			array(
				'post_type'      => $dated_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$years = array();
		foreach ( $posts_query->posts as $wp_post ) {
			$year             = gmdate( 'Y', strtotime( $wp_post->post_date ) );
			$years[ $year ][] = $wp_post;
		}

		foreach ( $years as $year => $year_posts ) {
			$this->generate_year_archive( $year, $year_posts );
		}
	}

	/**
	 * Generate both ASC and DESC year archive pages.
	 */
	public function generate_year_archive( $year, $year_posts = null ) {
		$dated_types = $this->get_dated_post_types();

		if ( null === $year_posts ) {
			$year_posts = get_posts(
				array(
					'post_type'      => $dated_types,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'date_query'     => array(
						array( 'year' => (int) $year ),
					),
				)
			);
		}

		if ( empty( $year_posts ) ) {
			return;
		}

		$from_dir = $this->output_dir . '/' . $year;
		$entries  = array();
		foreach ( $year_posts as $wp_post ) {
			$content = apply_filters( 'the_content', $wp_post->post_content );
			$content = $this->rewrite_urls( $content, $from_dir );

			$entries[] = array(
				'title'      => $wp_post->post_title,
				'date'       => date_i18n( get_option( 'date_format' ), strtotime( $wp_post->post_date ) ),
				'date_iso'   => gmdate( 'Y-m-d', strtotime( $wp_post->post_date ) ),
				'author'     => get_the_author_meta( 'display_name', $wp_post->post_author ),
				'content'    => $content,
				'content_md' => $this->should_output_markdown() ? $this->html_to_markdown( $content ) : '',
				'href'       => $this->filename( $wp_post->post_type . '-' . $wp_post->ID ),
			);
		}

		$filenames        = $this->get_year_archive_filenames();
		$blog_name        = $this->blog_name;
		$blog_description = $this->blog_description;
		$lang             = $this->lang;
		$index_url        = '../' . $this->get_index_filename();
		$style_url        = '../' . $this->get_style_filename();
		$dir              = $from_dir;
		wp_mkdir_p( $dir );

		// ASC version (oldest first).
		$order     = 'asc';
		$other_url = $filenames['desc'];

		if ( $this->should_output_html() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/year.php';
			file_put_contents( $dir . '/' . $filenames['asc'], ob_get_clean() );
		}

		if ( $this->should_output_markdown() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/year.md.php';
			$md_filenames = $this->get_year_archive_filenames( 'md' );
			file_put_contents( $dir . '/' . $md_filenames['asc'], ob_get_clean() );
		}

		// DESC version (newest first).
		$order     = 'desc';
		$other_url = $filenames['asc'];
		$entries   = array_reverse( $entries );

		if ( $this->should_output_html() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/year.php';
			file_put_contents( $dir . '/' . $filenames['desc'], ob_get_clean() );
		}

		if ( $this->should_output_markdown() ) {
			ob_start();
			include dirname( __DIR__ ) . '/templates/year.md.php';
			$md_filenames = $this->get_year_archive_filenames( 'md' );
			file_put_contents( $dir . '/' . $md_filenames['desc'], ob_get_clean() );
		}
	}

	/**
	 * Delete all generated archive files. Returns the number of files deleted.
	 */
	public function delete_all() {
		$files          = array();
		$suffix_pattern = preg_quote( $this->suffix, '/' );

		// Root-level files (archive index, front page).
		foreach ( array( 'html', 'md' ) as $ext ) {
			foreach ( glob( $this->output_dir . '/*' . $this->suffix . '.' . $ext ) as $f ) {
				$files[] = $f;
			}
		}
		$files[] = $this->output_dir . '/' . $this->get_style_filename();

		// Year directories.
		foreach ( glob( $this->output_dir . '/[0-9][0-9][0-9][0-9]' ) as $year_dir ) {
			if ( ! is_dir( $year_dir ) ) {
				continue;
			}
			foreach ( array( 'html', 'md' ) as $ext ) {
				foreach ( glob( $year_dir . '/*' . $this->suffix . '.' . $ext ) as $f ) {
					$files[] = $f;
				}
			}
		}

		// Pages directory (recursive, slug-based filenames).
		$pages_dir = $this->output_dir . '/pages';
		if ( is_dir( $pages_dir ) ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $pages_dir, RecursiveDirectoryIterator::SKIP_DOTS )
			);
			foreach ( $it as $file_info ) {
				if ( preg_match( '/' . $suffix_pattern . '\.(html|md)$/', $file_info->getFilename() ) ) {
					$files[] = $file_info->getPathname();
				}
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
			$this->output_dir . '/' . $this->get_style_filename()
		);
	}

	/**
	 * Generate all posts and the index.
	 */
	public function generate_all( $progress = null ) {
		$this->copy_stylesheet();

		$post_ids = get_posts(
			array(
				'post_type'      => $this->post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$stats = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
			'total'     => count( $post_ids ),
		);

		foreach ( $post_ids as $i => $post_id ) {
			$status = $this->generate_post( $post_id );
			++$stats[ $status ];

			if ( $progress ) {
				$wp_post = get_post( $post_id );
				$progress( $i + 1, $stats['total'], $status, $wp_post ? $wp_post->post_name : '' );
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
		$post_ids = get_posts(
			array(
				'post_type'      => $this->post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$total = count( $post_ids );
		$batch = array_slice( $post_ids, $offset, $limit );

		if ( 0 === $offset ) {
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
			++$stats[ $status ];

			$wp_post = get_post( $post_id );
			if ( $wp_post ) {
				$d = date_i18n( get_option( 'date_format' ), strtotime( $wp_post->post_date ) );
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
		$all_posts = get_posts(
			array(
				'post_type'      => $this->post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$expected_files = array();
		$missing        = array();
		$outdated       = array();

		// Determine which extensions to check.
		$exts = array();
		if ( $this->should_output_html() ) {
			$exts[] = 'html';
		}
		if ( $this->should_output_markdown() ) {
			$exts[] = 'md';
		}

		$missing_capped = false;
		foreach ( $all_posts as $wp_post ) {
			$post_missing  = false;
			$post_outdated = false;
			foreach ( $exts as $ext ) {
				$file                        = $this->get_post_file_path( $wp_post, $ext );
				$rel_path                    = $this->get_post_relative_path( $wp_post, $ext );
				$expected_files[ $rel_path ] = true;

				if ( ! file_exists( $file ) ) {
					$post_missing = true;
				} elseif ( filemtime( $file ) < strtotime( $wp_post->post_modified ) ) {
					$post_outdated = true;
				}
			}
			$entry = array(
				'id'    => $wp_post->ID,
				'slug'  => $wp_post->post_name,
				'title' => $wp_post->post_title,
			);
			if ( $post_missing ) {
				$missing[] = $entry;
				if ( count( $missing ) > 10 ) {
					$missing_capped = true;
					break;
				}
			} elseif ( $post_outdated ) {
				$outdated[] = $entry;
			}
		}

		// Find orphaned files — only when we have the full post list.
		$orphaned = array();
		if ( ! $missing_capped ) {
			// Add known archive index files so they're not flagged as orphans.
			foreach ( array( 'html', 'md' ) as $ext ) {
				$expected_files[ $this->get_index_filename( $ext ) ] = true;
			}

			$suffix_pattern    = preg_quote( $this->suffix, '/' );
			$year_index_html   = array( $this->filename( 'archive' ), $this->filename( 'latest' ) );
			$year_index_md     = array( $this->filename( 'archive', 'md' ), $this->filename( 'latest', 'md' ) );
			$year_index_by_ext = array(
				'html' => $year_index_html,
				'md'   => $year_index_md,
			);

			// Root-level files (front page, archive index).
			foreach ( array( 'html', 'md' ) as $ext ) {
				foreach ( glob( $this->output_dir . '/*' . $this->suffix . '.' . $ext ) as $file ) {
					$rel_path = basename( $file );
					if ( ! isset( $expected_files[ $rel_path ] ) ) {
						$orphaned[] = $rel_path;
					}
				}
			}

			// Year directories.
			foreach ( glob( $this->output_dir . '/[0-9][0-9][0-9][0-9]' ) as $year_dir ) {
				if ( ! is_dir( $year_dir ) ) {
					continue;
				}
				$year = basename( $year_dir );
				foreach ( array( 'html', 'md' ) as $ext ) {
					foreach ( glob( $year_dir . '/*' . $this->suffix . '.' . $ext ) as $file ) {
						$basename = basename( $file );
						if ( in_array( $basename, $year_index_by_ext[ $ext ], true ) ) {
							continue;
						}
						$rel_path = $year . '/' . $basename;
						if ( ! isset( $expected_files[ $rel_path ] ) ) {
							$orphaned[] = $rel_path;
						}
					}
				}
			}

			// Pages directory (recursive).
			$pages_dir = $this->output_dir . '/pages';
			if ( is_dir( $pages_dir ) ) {
				$it = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $pages_dir, RecursiveDirectoryIterator::SKIP_DOTS )
				);
				foreach ( $it as $file_info ) {
					if ( preg_match( '/' . $suffix_pattern . '\.(html|md)$/', $file_info->getFilename() ) ) {
						$rel_path = 'pages/' . substr( $file_info->getPathname(), strlen( $pages_dir ) + 1 );
						if ( ! isset( $expected_files[ $rel_path ] ) ) {
							$orphaned[] = $rel_path;
						}
					}
				}
			}
		}

		return array(
			'missing'        => $missing,
			'missing_capped' => $missing_capped,
			'orphaned'       => $orphaned,
			'outdated'       => $outdated,
			'total_posts'    => count( $all_posts ),
			'total_archived' => count( $all_posts ) - count( $missing ),
		);
	}

	/**
	 * Get a display title for a post, falling back to excerpt, content snippet, or date.
	 */
	public function get_display_title( $wp_post ) {
		if ( $wp_post->post_title ) {
			return $wp_post->post_title;
		}
		if ( $wp_post->post_excerpt ) {
			return wp_trim_words( $wp_post->post_excerpt, 10, "\xe2\x80\xa6" );
		}
		$snippet = wp_trim_words( wp_strip_all_tags( $wp_post->post_content ), 10, "\xe2\x80\xa6" );
		if ( $snippet ) {
			return $snippet;
		}
		return date_i18n( get_option( 'date_format' ), strtotime( $wp_post->post_date ) );
	}

	/**
	 * Convert HTML to Markdown.
	 */
	public function html_to_markdown( $html ) {
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Fenced code blocks.
		$html = preg_replace_callback(
			'/<pre[^>]*>\s*<code[^>]*>(.*?)<\/code>\s*<\/pre>/si',
			function ( $m ) {
				$code = html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				return "\n\n```\n" . trim( $code ) . "\n```\n\n";
			},
			$html
		);

		// Headings.
		for ( $i = 6; $i >= 1; $i-- ) {
			$hashes = str_repeat( '#', $i );
			$html   = preg_replace_callback(
				'/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/si',
				function ( $m ) use ( $hashes ) {
					return "\n\n{$hashes} " . trim( strip_tags( $m[1] ) ) . "\n\n";
				},
				$html
			);
		}

		// Bold and italic.
		$html = preg_replace( '/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/si', '**$2**', $html );
		$html = preg_replace( '/<(em|i)[^>]*>(.*?)<\/(em|i)>/si', '*$2*', $html );

		// Inline code.
		$html = preg_replace( '/<code[^>]*>(.*?)<\/code>/si', '`$1`', $html );

		// Images (before links).
		$html = preg_replace_callback(
			'/<img[^>]+\/?>/si',
			function ( $m ) {
				preg_match( '/src=["\']([^"\']*)["\']/', $m[0], $src );
				preg_match( '/alt=["\']([^"\']*)["\']/', $m[0], $alt );
				return '![' . ( isset( $alt[1] ) ? $alt[1] : '' ) . '](' . ( isset( $src[1] ) ? $src[1] : '' ) . ')';
			},
			$html
		);

		// Links.
		$html = preg_replace_callback(
			'/<a[^>]+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si',
			function ( $m ) {
				return '[' . trim( strip_tags( $m[2] ) ) . '](' . $m[1] . ')';
			},
			$html
		);

		// Blockquotes.
		$html = preg_replace_callback(
			'/<blockquote[^>]*>(.*?)<\/blockquote>/si',
			function ( $m ) {
				$inner = trim( strip_tags( $m[1] ) );
				$lines = explode( "\n", $inner );
				return "\n\n" . implode( "\n", array_map( fn( $l ) => '> ' . $l, $lines ) ) . "\n\n";
			},
			$html
		);

		// Unordered lists.
		$html = preg_replace_callback(
			'/<ul[^>]*>(.*?)<\/ul>/si',
			function ( $m ) {
				return "\n\n" . preg_replace( '/<li[^>]*>(.*?)<\/li>/si', "- $1\n", $m[1] ) . "\n";
			},
			$html
		);

		// Ordered lists.
		$html = preg_replace_callback(
			'/<ol[^>]*>(.*?)<\/ol>/si',
			function ( $m ) {
				$n = 0;
				return "\n\n" . preg_replace_callback(
					'/<li[^>]*>(.*?)<\/li>/si',
					function ( $li ) use ( &$n ) {
						return ( ++$n ) . '. ' . $li[1] . "\n";
					},
					$m[1]
				) . "\n";
			},
			$html
		);

		// Horizontal rules, line breaks, and paragraphs.
		$html = preg_replace( '/<hr[^>]*\/?>/si', "\n\n---\n\n", $html );
		$html = preg_replace( '/<br[^>]*\/?>/si', "\\\n", $html );
		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/si', "$1\n\n", $html );

		$html = strip_tags( $html );
		$html = preg_replace( '/[ \t]+\n/', "\n", $html );
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );

		return trim( $html );
	}

	/**
	 * Rewrite absolute upload URLs to relative paths, computed from $from_dir.
	 *
	 * @param string $content  HTML content.
	 * @param string $from_dir Absolute path of the output file's directory.
	 */
	public function rewrite_urls( $content, $from_dir ) {
		$upload_host = wp_parse_url( $this->upload_baseurl, PHP_URL_HOST );
		$upload_path = rtrim( wp_parse_url( $this->upload_baseurl, PHP_URL_PATH ), '/' );

		$pattern = '/(?:https?:)?\/\/' . preg_quote( $upload_host, '/' )
			. preg_quote( $upload_path, '/' ) . '\/([^"\'>\s]*)/';

		$content = preg_replace_callback(
			$pattern,
			function ( $m ) use ( $from_dir ) {
				$target = $this->output_dir . '/' . $m[1];
				return $this->make_relative_path( $from_dir, $target );
			},
			$content
		);

		// Rewrite internal post permalinks to archive files.
		$site_url      = preg_quote( trailingslashit( home_url() ), '/' );
		$front_page_id = $this->get_front_page_id();

		// Rewrite bare home URL to the static front page.
		if ( $front_page_id ) {
			$front_page = get_post( $front_page_id );
			if ( $front_page && 'publish' === $front_page->post_status ) {
				$front_target    = $this->output_dir . '/' . $this->get_post_relative_path( $front_page );
				$front_rewritten = 'href="' . $this->make_relative_path( $from_dir, $front_target ) . '"';
				$content         = preg_replace_callback(
					'/href=["\']' . $site_url . '["\']|href=["\']' . preg_quote( rtrim( home_url(), '/' ), '/' ) . '["\']/',
					function () use ( $front_rewritten ) {
						return $front_rewritten;
					},
					$content
				);
			}
		}

		$content = preg_replace_callback(
			'/href=["\']' . $site_url . '([a-z0-9][a-z0-9-]*(?:\/[a-z0-9][a-z0-9-]*)*)\/?["\']/',
			function ( $matches ) use ( $from_dir ) {
				$slug    = $matches[1];
				$wp_post = get_page_by_path( $slug, OBJECT, $this->post_types );
				if ( $wp_post && 'publish' === $wp_post->post_status ) {
					$target = $this->output_dir . '/' . $this->get_post_relative_path( $wp_post );
					return 'href="' . $this->make_relative_path( $from_dir, $target ) . '"';
				}
				return $matches[0];
			},
			$content
		);

		return $content;
	}

	/**
	 * Compute a relative file path from a directory to a target file.
	 *
	 * @param string $from_dir Absolute path of the source directory.
	 * @param string $to_file  Absolute path of the target file.
	 * @return string
	 */
	public function make_relative_path( $from_dir, $to_file ) {
		$from_parts = array_values( array_filter( explode( '/', $from_dir ), fn( $p ) => '' !== $p ) );
		$to_parts   = array_values( array_filter( explode( '/', $to_file ), fn( $p ) => '' !== $p ) );

		$i   = 0;
		$len = min( count( $from_parts ), count( $to_parts ) );
		while ( $i < $len && $from_parts[ $i ] === $to_parts[ $i ] ) {
			++$i;
		}

		$ups = count( $from_parts ) - $i;
		return str_repeat( '../', $ups ) . implode( '/', array_slice( $to_parts, $i ) );
	}

	/**
	 * Get the output directory path.
	 */
	public function get_output_dir() {
		return $this->output_dir;
	}
}
