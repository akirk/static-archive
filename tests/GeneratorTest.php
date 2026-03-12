<?php

use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase {

	private Static_Archive_Generator $generator;
	private string $tmpDir;

	protected function setUp(): void {
		$GLOBALS['_test_options']      = array( 'date_format' => 'Y-m-d' );
		$GLOBALS['_test_page_by_path'] = array();
		$GLOBALS['_test_page_uri']     = array();
		$GLOBALS['_test_posts']        = array();
		$this->tmpDir                  = sys_get_temp_dir() . '/static-archive-test';
		if ( is_dir( $this->tmpDir ) ) {
			$this->removeDir( $this->tmpDir );
		}
		mkdir( $this->tmpDir, 0777, true );
		$this->generator = new Static_Archive_Generator();
	}

	protected function tearDown(): void {
		if ( is_dir( $this->tmpDir ) ) {
			$this->removeDir( $this->tmpDir );
		}
	}

	private function removeDir( string $dir ): void {
		foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
			is_dir( $file ) ? $this->removeDir( $file ) : unlink( $file );
		}
		rmdir( $dir );
	}

	private function make_post( array $props ): stdClass {
		return (object) array_merge(
			array(
				'ID'           => 1,
				'post_title'   => '',
				'post_excerpt' => '',
				'post_content' => '',
				'post_date'    => '2020-06-15 10:00:00',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			),
			$props
		);
	}

	// -------------------------------------------------------------------------
	// html_to_markdown
	// -------------------------------------------------------------------------

	public function test_paragraph() {
		$this->assertSame( 'Hello world.', $this->generator->html_to_markdown( '<p>Hello world.</p>' ) );
	}

	public function test_multiple_paragraphs() {
		$result = $this->generator->html_to_markdown( '<p>First.</p><p>Second.</p>' );
		$this->assertSame( "First.\n\nSecond.", $result );
	}

	public function test_heading_h1() {
		$this->assertSame( '# Title', $this->generator->html_to_markdown( '<h1>Title</h1>' ) );
	}

	public function test_heading_h2() {
		$this->assertSame( '## Section', $this->generator->html_to_markdown( '<h2>Section</h2>' ) );
	}

	public function test_heading_h3_through_h6() {
		$this->assertSame( '### H3', $this->generator->html_to_markdown( '<h3>H3</h3>' ) );
		$this->assertSame( '#### H4', $this->generator->html_to_markdown( '<h4>H4</h4>' ) );
		$this->assertSame( '##### H5', $this->generator->html_to_markdown( '<h5>H5</h5>' ) );
		$this->assertSame( '###### H6', $this->generator->html_to_markdown( '<h6>H6</h6>' ) );
	}

	public function test_bold_strong() {
		$this->assertSame( '**bold**', $this->generator->html_to_markdown( '<strong>bold</strong>' ) );
	}

	public function test_bold_b_tag() {
		$this->assertSame( '**bold**', $this->generator->html_to_markdown( '<b>bold</b>' ) );
	}

	public function test_italic_em() {
		$this->assertSame( '*italic*', $this->generator->html_to_markdown( '<em>italic</em>' ) );
	}

	public function test_italic_i_tag() {
		$this->assertSame( '*italic*', $this->generator->html_to_markdown( '<i>italic</i>' ) );
	}

	public function test_inline_code() {
		$this->assertSame( '`code`', $this->generator->html_to_markdown( '<code>code</code>' ) );
	}

	public function test_fenced_code_block() {
		$result = $this->generator->html_to_markdown( '<pre><code>function foo() {}</code></pre>' );
		$this->assertSame( "```\nfunction foo() {}\n```", $result );
	}

	public function test_link() {
		$result = $this->generator->html_to_markdown( '<a href="https://example.com">Example</a>' );
		$this->assertSame( '[Example](https://example.com)', $result );
	}

	public function test_image_with_alt() {
		$result = $this->generator->html_to_markdown( '<img src="photo.jpg" alt="A photo">' );
		$this->assertSame( '![A photo](photo.jpg)', $result );
	}

	public function test_image_without_alt() {
		$result = $this->generator->html_to_markdown( '<img src="photo.jpg">' );
		$this->assertSame( '![](photo.jpg)', $result );
	}

	public function test_unordered_list() {
		$result = $this->generator->html_to_markdown( '<ul><li>Apple</li><li>Banana</li></ul>' );
		$this->assertSame( "- Apple\n- Banana", $result );
	}

	public function test_ordered_list() {
		$result = $this->generator->html_to_markdown( '<ol><li>First</li><li>Second</li></ol>' );
		$this->assertSame( "1. First\n2. Second", $result );
	}

	public function test_horizontal_rule() {
		$result = $this->generator->html_to_markdown( '<p>Above</p><hr><p>Below</p>' );
		$this->assertSame( "Above\n\n---\n\nBelow", $result );
	}

	public function test_line_break() {
		$result = $this->generator->html_to_markdown( '<p>Line one<br>Line two</p>' );
		$this->assertSame( "Line one\\\nLine two", $result );
	}

	public function test_html_entities_decoded() {
		$this->assertSame( 'Hello & "world"', $this->generator->html_to_markdown( '<p>Hello &amp; &quot;world&quot;</p>' ) );
	}

	public function test_bold_inside_paragraph() {
		$result = $this->generator->html_to_markdown( '<p>This is <strong>important</strong>.</p>' );
		$this->assertSame( 'This is **important**.', $result );
	}

	public function test_link_inside_paragraph() {
		$result = $this->generator->html_to_markdown( '<p>Visit <a href="https://example.com">here</a>.</p>' );
		$this->assertSame( 'Visit [here](https://example.com).', $result );
	}

	public function test_unknown_tags_stripped() {
		$result = $this->generator->html_to_markdown( '<div><span>Text</span></div>' );
		$this->assertSame( 'Text', $result );
	}

	// -------------------------------------------------------------------------
	// make_relative_path
	// -------------------------------------------------------------------------

	public function test_same_directory() {
		$result = $this->generator->make_relative_path( '/uploads/2020', '/uploads/2020/12/image.jpg' );
		$this->assertSame( '12/image.jpg', $result );
	}

	public function test_sibling_year_directory() {
		$result = $this->generator->make_relative_path( '/uploads/2021', '/uploads/2020/12/image.jpg' );
		$this->assertSame( '../2020/12/image.jpg', $result );
	}

	public function test_from_root_to_subdir() {
		$result = $this->generator->make_relative_path( '/uploads', '/uploads/2020/post-1.html' );
		$this->assertSame( '2020/post-1.html', $result );
	}

	public function test_from_deep_subdir_to_root_file() {
		$result = $this->generator->make_relative_path( '/uploads/2020', '/uploads/style.css' );
		$this->assertSame( '../style.css', $result );
	}

	public function test_same_year_different_file() {
		$result = $this->generator->make_relative_path( '/uploads/2020', '/uploads/2020/post-1.html' );
		$this->assertSame( 'post-1.html', $result );
	}

	public function test_pages_directory() {
		$result = $this->generator->make_relative_path( '/uploads/pages', '/uploads/2020/post-1.html' );
		$this->assertSame( '../2020/post-1.html', $result );
	}

	// -------------------------------------------------------------------------
	// filename / get_index_filename / get_style_filename
	// -------------------------------------------------------------------------

	public function test_filename_default_extension() {
		$this->assertSame( 'post-42-aaaaaaaa.html', $this->generator->filename( 'post-42' ) );
	}

	public function test_filename_custom_extension() {
		$this->assertSame( 'post-42-aaaaaaaa.md', $this->generator->filename( 'post-42', 'md' ) );
	}

	public function test_get_index_filename_html() {
		$this->assertSame( 'archive-aaaaaaaa.html', $this->generator->get_index_filename() );
	}

	public function test_get_index_filename_md() {
		$this->assertSame( 'archive-aaaaaaaa.md', $this->generator->get_index_filename( 'md' ) );
	}

	public function test_get_style_filename() {
		$this->assertSame( 'style-aaaaaaaa.css', $this->generator->get_style_filename() );
	}

	// -------------------------------------------------------------------------
	// get_post_relative_path
	// -------------------------------------------------------------------------

	public function test_post_relative_path_for_post() {
		$post = $this->make_post(
			array(
				'ID'        => 42,
				'post_type' => 'post',
				'post_date' => '2020-06-15 10:00:00',
			)
		);
		$this->assertSame( '2020/post-42-aaaaaaaa.html', $this->generator->get_post_relative_path( $post ) );
	}

	public function test_post_relative_path_for_page() {
		$post = $this->make_post(
			array(
				'ID'        => 10,
				'post_name' => 'about',
				'post_type' => 'page',
			)
		);
		$this->assertSame( 'pages/about-aaaaaaaa.html', $this->generator->get_post_relative_path( $post ) );
	}

	public function test_post_relative_path_for_child_page() {
		$post = $this->make_post(
			array(
				'ID'        => 20,
				'post_name' => 'team',
				'post_type' => 'page',
			)
		);
		$GLOBALS['_test_page_uri'][20] = 'about/team';
		$this->assertSame( 'pages/about/team-aaaaaaaa.html', $this->generator->get_post_relative_path( $post ) );
	}

	public function test_post_relative_path_for_front_page() {
		$GLOBALS['_test_options']['show_on_front']  = 'page';
		$GLOBALS['_test_options']['page_on_front']  = 5;
		$gen  = new Static_Archive_Generator();
		$post = $this->make_post(
			array(
				'ID'        => 5,
				'post_name' => 'home',
				'post_type' => 'page',
			)
		);
		$this->assertSame( 'home-aaaaaaaa.html', $gen->get_post_relative_path( $post ) );
	}

	public function test_post_relative_path_for_page_not_front_page() {
		$GLOBALS['_test_options']['show_on_front'] = 'page';
		$GLOBALS['_test_options']['page_on_front'] = 5;
		$gen  = new Static_Archive_Generator();
		$post = $this->make_post(
			array(
				'ID'        => 10,
				'post_name' => 'about',
				'post_type' => 'page',
			)
		);
		$this->assertSame( 'pages/about-aaaaaaaa.html', $gen->get_post_relative_path( $post ) );
	}

	public function test_post_relative_path_markdown_extension() {
		$post = $this->make_post(
			array(
				'ID'        => 42,
				'post_type' => 'post',
				'post_date' => '2020-06-15 10:00:00',
			)
		);
		$this->assertSame( '2020/post-42-aaaaaaaa.md', $this->generator->get_post_relative_path( $post, 'md' ) );
	}

	// -------------------------------------------------------------------------
	// get_year_archive_filenames
	// -------------------------------------------------------------------------

	public function test_get_year_archive_filenames_html() {
		$filenames = $this->generator->get_year_archive_filenames();
		$this->assertSame( 'archive-aaaaaaaa.html', $filenames['asc'] );
		$this->assertSame( 'latest-aaaaaaaa.html', $filenames['desc'] );
	}

	public function test_get_year_archive_filenames_md() {
		$filenames = $this->generator->get_year_archive_filenames( 'md' );
		$this->assertSame( 'archive-aaaaaaaa.md', $filenames['asc'] );
		$this->assertSame( 'latest-aaaaaaaa.md', $filenames['desc'] );
	}

	// -------------------------------------------------------------------------
	// should_output_html / should_output_markdown
	// -------------------------------------------------------------------------

	public function test_should_output_html_when_html_format() {
		$this->assertTrue( $this->generator->should_output_html() );
	}

	public function test_should_not_output_markdown_when_html_format() {
		$this->assertFalse( $this->generator->should_output_markdown() );
	}

	public function test_should_output_markdown_when_markdown_format() {
		$GLOBALS['_test_options']['static_archive_output_format'] = 'markdown';
		$gen = new Static_Archive_Generator();
		$this->assertFalse( $gen->should_output_html() );
		$this->assertTrue( $gen->should_output_markdown() );
	}

	public function test_should_output_both_when_both_format() {
		$GLOBALS['_test_options']['static_archive_output_format'] = 'both';
		$gen = new Static_Archive_Generator();
		$this->assertTrue( $gen->should_output_html() );
		$this->assertTrue( $gen->should_output_markdown() );
	}

	// -------------------------------------------------------------------------
	// get_post_types / get_dated_post_types
	// -------------------------------------------------------------------------

	public function test_get_post_types_default() {
		$this->assertSame( array( 'post', 'page' ), Static_Archive_Generator::get_post_types() );
	}

	public function test_get_post_types_custom() {
		$GLOBALS['_test_options']['static_archive_post_types'] = array( 'post', 'page', 'product' );
		$this->assertSame( array( 'post', 'page', 'product' ), Static_Archive_Generator::get_post_types() );
	}

	public function test_get_dated_post_types_excludes_page() {
		$this->assertSame( array( 'post' ), $this->generator->get_dated_post_types() );
	}

	public function test_get_dated_post_types_with_custom_types() {
		$GLOBALS['_test_options']['static_archive_post_types'] = array( 'post', 'page', 'product' );
		$gen = new Static_Archive_Generator();
		$this->assertSame( array( 'post', 'product' ), $gen->get_dated_post_types() );
	}

	// -------------------------------------------------------------------------
	// write_file
	// -------------------------------------------------------------------------

	public function test_write_file_creates_new_file() {
		$file   = $this->tmpDir . '/test.html';
		$result = $this->generator->write_file( $file, 'hello' );
		$this->assertSame( 'created', $result );
		$this->assertSame( 'hello', file_get_contents( $file ) );
	}

	public function test_write_file_unchanged_when_content_matches() {
		$file = $this->tmpDir . '/test.html';
		file_put_contents( $file, 'hello' );
		$this->assertSame( 'unchanged', $this->generator->write_file( $file, 'hello' ) );
	}

	public function test_write_file_updated_when_content_differs() {
		$file = $this->tmpDir . '/test.html';
		file_put_contents( $file, 'hello' );
		$result = $this->generator->write_file( $file, 'world' );
		$this->assertSame( 'updated', $result );
		$this->assertSame( 'world', file_get_contents( $file ) );
	}

	public function test_write_file_creates_parent_directory() {
		$file = $this->tmpDir . '/subdir/nested/test.html';
		$this->generator->write_file( $file, 'hello' );
		$this->assertTrue( is_dir( $this->tmpDir . '/subdir/nested' ) );
	}

	public function test_write_file_sets_mtime() {
		$file  = $this->tmpDir . '/test.html';
		$mtime = strtotime( '2020-01-01 00:00:00' );
		$this->generator->write_file( $file, 'hello', $mtime );
		$this->assertSame( $mtime, filemtime( $file ) );
	}

	public function test_write_file_updates_mtime_when_unchanged() {
		$file  = $this->tmpDir . '/test.html';
		$mtime = strtotime( '2020-01-01 00:00:00' );
		file_put_contents( $file, 'hello' );
		touch( $file, 1000 );
		$this->generator->write_file( $file, 'hello', $mtime );
		$this->assertSame( $mtime, filemtime( $file ) );
	}

	// -------------------------------------------------------------------------
	// get_display_title
	// -------------------------------------------------------------------------

	public function test_display_title_uses_post_title() {
		$post = $this->make_post( array( 'post_title' => 'Hello World' ) );
		$this->assertSame( 'Hello World', $this->generator->get_display_title( $post ) );
	}

	public function test_display_title_falls_back_to_excerpt() {
		$post = $this->make_post( array( 'post_excerpt' => 'A short excerpt here' ) );
		$this->assertSame( 'A short excerpt here', $this->generator->get_display_title( $post ) );
	}

	public function test_display_title_falls_back_to_content_snippet() {
		$post = $this->make_post( array( 'post_content' => '<p>Some plain content here</p>' ) );
		$this->assertSame( 'Some plain content here', $this->generator->get_display_title( $post ) );
	}

	public function test_display_title_falls_back_to_date() {
		$post = $this->make_post( array( 'post_date' => '2020-06-15 10:00:00' ) );
		$this->assertSame( '2020-06-15', $this->generator->get_display_title( $post ) );
	}

	public function test_display_title_truncates_long_excerpt() {
		$post  = $this->make_post( array( 'post_excerpt' => 'one two three four five six seven eight nine ten eleven' ) );
		$title = $this->generator->get_display_title( $post );
		$this->assertStringEndsWith( '…', $title );
		$this->assertStringNotContainsString( 'eleven', $title );
	}

	// -------------------------------------------------------------------------
	// rewrite_urls
	// -------------------------------------------------------------------------

	public function test_rewrite_urls_same_year_directory() {
		$result = $this->generator->rewrite_urls(
			'<img src="http://example.com/wp-content/uploads/2020/12/photo.jpg">',
			'/tmp/wp-uploads/2020'
		);
		$this->assertSame( '<img src="12/photo.jpg">', $result );
	}

	public function test_rewrite_urls_cross_year() {
		$result = $this->generator->rewrite_urls(
			'<img src="http://example.com/wp-content/uploads/2019/06/photo.jpg">',
			'/tmp/wp-uploads/2020'
		);
		$this->assertSame( '<img src="../2019/06/photo.jpg">', $result );
	}

	public function test_rewrite_urls_protocol_relative() {
		$result = $this->generator->rewrite_urls(
			'<img src="//example.com/wp-content/uploads/2020/12/photo.jpg">',
			'/tmp/wp-uploads/2020'
		);
		$this->assertSame( '<img src="12/photo.jpg">', $result );
	}

	public function test_rewrite_urls_leaves_external_urls_unchanged() {
		$input  = '<img src="https://other-site.com/image.jpg">';
		$result = $this->generator->rewrite_urls( $input, '/tmp/wp-uploads/2020' );
		$this->assertSame( $input, $result );
	}

	public function test_rewrite_urls_rewrites_internal_permalink() {
		$GLOBALS['_test_page_by_path']['my-post'] = $this->make_post(
			array(
				'ID'        => 42,
				'post_type' => 'post',
				'post_date' => '2020-06-15 10:00:00',
			)
		);
		$result                                   = $this->generator->rewrite_urls(
			'<a href="http://example.com/my-post">link</a>',
			'/tmp/wp-uploads/2020'
		);
		$this->assertSame( '<a href="post-42-aaaaaaaa.html">link</a>', $result );
	}

	public function test_rewrite_urls_leaves_unknown_permalink_unchanged() {
		$input  = '<a href="http://example.com/unknown-post">link</a>';
		$result = $this->generator->rewrite_urls( $input, '/tmp/wp-uploads/2020' );
		$this->assertSame( $input, $result );
	}

	// -------------------------------------------------------------------------
	// delete_all
	// -------------------------------------------------------------------------

	public function test_delete_all_removes_files_with_current_suffix() {
		$dir = '/tmp/wp-uploads';
		mkdir( $dir . '/2020', 0777, true );
		mkdir( $dir . '/pages', 0777, true );
		mkdir( $dir . '/pages/about', 0777, true );

		$files = array(
			$dir . '/archive-aaaaaaaa.html',
			$dir . '/archive-aaaaaaaa.md',
			$dir . '/home-aaaaaaaa.html',
			$dir . '/style-aaaaaaaa.css',
			$dir . '/2020/archive-aaaaaaaa.html',
			$dir . '/2020/latest-aaaaaaaa.html',
			$dir . '/2020/post-1-aaaaaaaa.html',
			$dir . '/2020/post-1-aaaaaaaa.md',
			$dir . '/pages/about-aaaaaaaa.html',
			$dir . '/pages/about/team-aaaaaaaa.html',
		);
		foreach ( $files as $file ) {
			file_put_contents( $file, 'test' );
		}

		$deleted = $this->generator->delete_all();

		$this->assertSame( count( $files ), $deleted );
		foreach ( $files as $file ) {
			$this->assertFileDoesNotExist( $file );
		}

		@rmdir( $dir . '/2020' );
		@rmdir( $dir . '/pages/about' );
		@rmdir( $dir . '/pages' );
	}

	public function test_delete_all_leaves_files_with_different_suffix() {
		$dir = '/tmp/wp-uploads';
		mkdir( $dir . '/2020', 0777, true );

		$other_file = $dir . '/2020/post-1-bbbbbbbb.html';
		file_put_contents( $other_file, 'test' );

		$this->generator->delete_all();

		$this->assertFileExists( $other_file );

		unlink( $other_file );
		@rmdir( $dir . '/2020' );
	}
}
