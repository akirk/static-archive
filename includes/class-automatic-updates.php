<?php

namespace StaticArchive;

/**
 * Keep automatic updates synchronous unless their destinations are unwritable.
 */
class AutomaticUpdates {

	const HOOK = 'static_archive_update_post';

	public static function update( $post, $delete = false ) {
		$job = self::make_job( $post, $delete );
		if ( self::queued_event( $job['id'] ) || ! self::can_run( array( $job ) ) || ! self::run( array( $job ) ) ) {
			self::schedule( $job );
		}
	}

	public static function make_job( $post, $delete ) {
		$generator = new Generator();
		$paths     = array();
		foreach ( array( 'html', 'md' ) as $ext ) {
			$paths[] = $generator->get_output_dir() . '/' . $generator->get_post_relative_path( $post, $ext );
		}
		return array(
			'id'     => $post->ID,
			'delete' => $delete,
			'paths'  => $paths,
			'years'  => 'page' === $post->post_type ? array() : array( gmdate( 'Y', strtotime( $post->post_date ) ) ),
		);
	}

	private static function queued_event( $post_id ) {
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			if ( empty( $hooks[ self::HOOK ] ) ) {
				continue;
			}
			foreach ( $hooks[ self::HOOK ] as $event ) {
				if ( (int) $event['args'][0]['id'] === (int) $post_id ) {
					$event['timestamp'] = $timestamp;
					return $event;
				}
			}
		}
		return false;
	}

	private static function schedule( $job ) {
		$queued = self::queued_event( $job['id'] );
		if ( $queued ) {
			$previous     = $queued['args'][0];
			$job['paths'] = array_values( array_unique( array_merge( $previous['paths'], $job['paths'] ) ) );
			$job['years'] = array_values( array_unique( array_merge( $previous['years'], $job['years'] ) ) );
			if ( array( $job ) === $queued['args'] ) {
				return;
			}
		}
		$timestamp = $queued ? $queued['timestamp'] : time() + 60;
		// Schedule the replacement first so a scheduling failure retains the old event.
		if ( ! wp_schedule_single_event( $timestamp, self::HOOK, array( $job ) ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Report unscheduled archive work.
			error_log( 'Static Archive: could not schedule archive update for post ' . $job['id'] . '.' );
			return;
		}
		if ( $queued ) {
			wp_unschedule_event( $queued['timestamp'], self::HOOK, $queued['args'] );
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
		$generator = new Generator();
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
				foreach ( $job['years'] as $year ) {
					foreach ( $generator->get_year_archive_filenames( $ext ) as $filename ) {
						$files[] = $base . $year . '/' . $filename;
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

	public static function run_pending( $job ) {
		// Read current state so stale events cannot recreate an unpublished or deleted post.
		$post          = get_post( $job['id'] );
		$job['delete'] = ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, Generator::get_post_types(), true );
		$jobs          = array( $job );
		if ( ! $job['delete'] ) {
			$current = self::make_job( $post, false );
			// Clean up captured paths if a post changed its date or page slug while queued.
			$jobs[0]['paths']  = array_diff( $job['paths'], $current['paths'] );
			$jobs[0]['delete'] = true;
			$jobs[]            = $current;
		}
		if ( ! self::can_run( $jobs ) || ! self::run( $jobs ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Report archive work that needs retrying.
			error_log( 'Static Archive: retrying archive update for post ' . $job['id'] . '.' );
			self::schedule( $job );
		}
	}

	private static function run( $jobs ) {
		$generator = new Generator();
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
				foreach ( $job['years'] as $year ) {
					$years[ $year ] = true;
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

// Preserve public class names used by existing integrations.
class_alias( AutomaticUpdates::class, 'Static_Archive_Automatic_Updates' );
