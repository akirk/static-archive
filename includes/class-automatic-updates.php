<?php

/**
 * Keep automatic updates synchronous unless their destinations are unwritable.
 */
class Static_Archive_Automatic_Updates {

	const OPTION = 'static_archive_pending_updates';
	const HOOK   = 'static_archive_pending_updates';

	public static function update( $post, $delete = false ) {
		$job     = self::make_job( $post, $delete );
		$pending = get_option( self::OPTION, array() );
		// Preserve paths captured before deletion and combine repeated imports.
		if ( isset( $pending[ $post->ID ] ) || ! self::can_run( array( $job ) ) ) {
			$pending[ $post->ID ][] = $job;
			$pending[ $post->ID ]   = array_values( array_unique( $pending[ $post->ID ], SORT_REGULAR ) );
			update_option( self::OPTION, $pending, false );
			self::schedule();
			return;
		}
		if ( ! self::run( array( $job ) ) ) {
			$pending                = get_option( self::OPTION, array() );
			$pending[ $post->ID ][] = $job;
			update_option( self::OPTION, $pending, false );
			self::schedule();
		}
	}

	public static function make_job( $post, $delete ) {
		$generator = new Static_Archive_Generator();
		$paths     = array();
		foreach ( array( 'html', 'md' ) as $ext ) {
			$paths[] = $generator->get_output_dir() . '/' . $generator->get_post_relative_path( $post, $ext );
		}
		return array(
			'id'     => $post->ID,
			'delete' => $delete,
			'paths'  => $paths,
			'year'   => 'page' === $post->post_type ? null : gmdate( 'Y', strtotime( $post->post_date ) ),
		);
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) && ! wp_schedule_single_event( time() + 60, self::HOOK ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Report retained or unscheduled archive work.
			error_log( 'Static Archive: could not schedule pending archive updates.' );
		}
	}

	/**
	 * Check the nearest existing ancestor when a destination is not yet created.
	 */
	public static function writable( $path, $delete = false ) {
		if ( $delete && ! file_exists( $path ) ) {
			return true;
		}
		if ( $delete ) {
			$path = dirname( $path );
		}
		while ( ! file_exists( $path ) ) {
			$parent = dirname( $path );
			if ( $parent === $path ) {
				return false;
			}
			$path = $parent;
		}
		return is_writable( $path );
	}

	public static function can_run( $jobs ) {
		$generator = new Static_Archive_Generator();
		$base      = $generator->get_output_dir() . '/';
		$files     = array( $base . $generator->get_style_filename() );
		foreach ( $jobs as $job ) {
			foreach ( $job['paths'] as $path ) {
				if ( ! $job['delete'] && ( ( '.html' === substr( $path, -5 ) && ! $generator->should_output_html() ) || ( '.md' === substr( $path, -3 ) && ! $generator->should_output_markdown() ) ) ) {
					continue;
				}
				if ( ! self::writable( $path, $job['delete'] ) ) {
					return false;
				}
			}
			foreach ( array( 'html', 'md' ) as $ext ) {
				if ( ( 'html' === $ext && ! $generator->should_output_html() ) || ( 'md' === $ext && ! $generator->should_output_markdown() ) ) {
					continue;
				}
				$files[] = $base . $generator->get_index_filename( $ext );
				if ( $job['year'] ) {
					foreach ( $generator->get_year_archive_filenames( $ext ) as $filename ) {
						$files[] = $base . $job['year'] . '/' . $filename;
					}
				}
			}
		}
		foreach ( array_unique( $files ) as $file ) {
			if ( ! self::writable( $file ) ) {
				return false;
			}
		}
		return true;
	}

	public static function run_pending() {
		$pending      = get_option( self::OPTION, array() );
		$jobs         = array();
		$jobs_current = array();
		foreach ( $pending as $entries ) {
			foreach ( $entries as $job ) {
				$jobs[] = $job;
			}
		}
		if ( ! $jobs ) {
			return;
		}
		// Resolve current state, including posts deleted since the job was queued.
		foreach ( $jobs as &$job ) {
			$post          = get_post( $job['id'] );
			$job['delete'] = ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, Static_Archive_Generator::get_post_types(), true );
			if ( ! $job['delete'] ) {
				$jobs_current[] = self::make_job( $post, false );
			}
		}
		unset( $job );
		$jobs = array_merge( $jobs, $jobs_current );
		if ( ! self::can_run( $jobs ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Report retained or unscheduled archive work.
			error_log( 'Static Archive: pending updates retained because archive paths are not writable.' );
			self::schedule();
			return;
		}
		if ( ! self::run( $jobs ) ) {
			self::schedule();
			return;
		}
		// Preserve entries added by another request while generation was running.
		$current = get_option( self::OPTION, array() );
		foreach ( $pending as $id => $entries ) {
			if ( isset( $current[ $id ] ) && $current[ $id ] === $entries ) {
				unset( $current[ $id ] );
			}
		}
		update_option( self::OPTION, $current, false );
		if ( $current ) {
			self::schedule();
		}
	}

	private static function run( $jobs ) {
		$generator = new Static_Archive_Generator();
		$years     = array();
		$posts     = array();
		$failed    = false;
		// Retain jobs when a filesystem operation fails after the permission check.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Track failed archive operations for retry.
		set_error_handler(
			static function () use ( &$failed ) {
				$failed = true;
				return false;
			},
			E_WARNING
		);
		try {
			$generator->copy_stylesheet();
			foreach ( $jobs as $job ) {
				if ( $job['delete'] ) {
					foreach ( $job['paths'] as $path ) {
						if ( file_exists( $path ) ) {
							wp_delete_file( $path );
						}
					}
				} else {
					$posts[ $job['id'] ] = true;
				}
				if ( $job['year'] ) {
					$years[ $job['year'] ] = true;
				}
			}
			foreach ( array_keys( $posts ) as $id ) {
				$generator->generate_post( $id );
			}
			$generator->generate_index();
			foreach ( array_keys( $years ) as $year ) {
				$generator->generate_year_archive( $year );
			}
		} finally {
			restore_error_handler();
		}
		return ! $failed;
	}
}
