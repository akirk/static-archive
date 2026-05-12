<?php

// Minimal WordPress function stubs for unit tests.

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// Controllable options store — reset in setUp() for each test.
$GLOBALS['_test_options']      = array();
$GLOBALS['_test_page_by_path'] = array();
$GLOBALS['_test_page_uri']     = array();
$GLOBALS['_test_posts']        = array();
$GLOBALS['_test_filters']      = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['_test_filters'][ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		if ( empty( $GLOBALS['_test_filters'][ $hook_name ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['_test_filters'][ $hook_name ] );
		foreach ( $GLOBALS['_test_filters'][ $hook_name ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$accepted = max( 1, (int) $callback['accepted_args'] );
				$value    = call_user_func_array(
					$callback['callback'],
					array_slice( array_merge( array( $value ), $args ), 0, $accepted )
				);
			}
		}

		return $value;
	}
}

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

if ( ! function_exists( 'get_the_author_meta' ) ) {
	function get_the_author_meta( $field, $user_id = false ) {
		return 'Test Author';
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

if ( ! function_exists( 'get_page_uri' ) ) {
	function get_page_uri( $page ) {
		$id = is_object( $page ) ? $page->ID : (int) $page;
		return $GLOBALS['_test_page_uri'][ $id ] ?? ( is_object( $page ) ? $page->post_name : '' );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return $GLOBALS['_test_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$posts = array_values( $GLOBALS['_test_posts'] );

		if ( ! empty( $args['post_type'] ) ) {
			$post_types = (array) $args['post_type'];
			$posts      = array_filter(
				$posts,
				function ( $post ) use ( $post_types ) {
					return in_array( $post->post_type, $post_types, true );
				}
			);
		}

		if ( ! empty( $args['post_status'] ) ) {
			$post_statuses = (array) $args['post_status'];
			$posts         = array_filter(
				$posts,
				function ( $post ) use ( $post_statuses ) {
					return in_array( $post->post_status, $post_statuses, true );
				}
			);
		}

		$excluded = array();
		if ( ! empty( $args['exclude'] ) ) {
			$excluded = array_merge( $excluded, array_map( 'intval', (array) $args['exclude'] ) );
		}
		if ( $excluded ) {
			$posts = array_filter(
				$posts,
				function ( $post ) use ( $excluded ) {
					return ! in_array( (int) $post->ID, $excluded, true );
				}
			);
		}

		if ( ! empty( $args['date_query'] ) ) {
			foreach ( $args['date_query'] as $date_query ) {
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $date_query ) {
						$post_time = strtotime( $post->post_date );
						if ( isset( $date_query['before'] ) && $post_time >= strtotime( $date_query['before'] ) ) {
							return false;
						}
						if ( isset( $date_query['after'] ) && $post_time <= strtotime( $date_query['after'] ) ) {
							return false;
						}
						return true;
					}
				);
			}
		}

		if ( empty( $args['orderby'] ) || 'date' === $args['orderby'] ) {
			usort(
				$posts,
				function ( $a, $b ) use ( $args ) {
					$comparison = strtotime( $a->post_date ) <=> strtotime( $b->post_date );
					return ( isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ) ? -$comparison : $comparison;
				}
			);
		}

		$limit = $args['posts_per_page'] ?? 5;
		if ( -1 !== (int) $limit ) {
			$posts = array_slice( $posts, 0, (int) $limit );
		}

		return $posts;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = array(), $output = 'names' ) {
		$types = array(
			'post' => (object) array(
				'name'   => 'post',
				'public' => true,
				'labels' => (object) array( 'name' => 'Posts' ),
			),
			'page' => (object) array(
				'name'   => 'page',
				'public' => true,
				'labels' => (object) array( 'name' => 'Pages' ),
			),
		);

		if ( isset( $args['public'] ) ) {
			$types = array_filter(
				$types,
				function ( $type ) use ( $args ) {
					return (bool) $type->public === (bool) $args['public'];
				}
			);
		}

		return 'objects' === $output ? $types : array_keys( $types );
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
require_once dirname( __DIR__ ) . '/includes/class-posts-and-pages.php';
