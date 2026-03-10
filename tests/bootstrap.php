<?php

// Minimal WordPress function stubs for unit tests.

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// Controllable options store — reset in setUp() for each test.
$GLOBALS['_test_options']      = array();
$GLOBALS['_test_page_by_path'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		$GLOBALS['_test_options'][ $option ] = $value;
	}
}

if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	function wp_get_upload_dir() {
		return array(
			'basedir' => '/tmp/wp-uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
		);
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show ) {
		return 'Test Blog';
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'en_US';
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length, $special_chars = true ) {
		return substr( str_repeat( 'a', $length ), 0, $length );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://example.com' . $path;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
		return $GLOBALS['_test_page_by_path'][ $slug ] ?? null;
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		if ( null === $more ) {
			$more = '…';
		}
		$words = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) > $num_words ) {
			return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
		}
		return implode( ' ', $words );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp = false ) {
		return gmdate( $format, false === $timestamp ? time() : $timestamp );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		return is_dir( $dir ) || mkdir( $dir, 0777, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-generator.php';
