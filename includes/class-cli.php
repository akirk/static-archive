<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Static_Archive_CLI {

	/**
	 * Generate static archive files.
	 *
	 * Generates HTML and/or Markdown files for all configured post types
	 * (posts, pages, and any custom post types selected in Settings).
	 * The output format depends on the plugin settings.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<id>]
	 * : Generate files for a single post or page.
	 *
	 * ## EXAMPLES
	 *
	 *     wp static-archive generate
	 *     wp static-archive generate --post_id=123
	 *
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		$generator = new Static_Archive_Generator();

		if ( ! empty( $assoc_args['post_id'] ) ) {
			$generator->copy_stylesheet();
			$status = $generator->generate_post( (int) $assoc_args['post_id'] );
			WP_CLI::success( "Post {$assoc_args['post_id']}: {$status}" );
			$generator->generate_index();
			WP_CLI::success( 'Index regenerated.' );
			return;
		}

		WP_CLI::log( 'Generating static archive...' );

		$stats = $generator->generate_all(
			function ( $current, $total, $status, $slug ) {
				WP_CLI::log( sprintf( '[%d/%d] %s: %s', $current, $total, $status, $slug ) );
			}
		);

		WP_CLI::success(
			sprintf(
				'Done. %d created, %d updated, %d unchanged, %d skipped out of %d entries.',
				$stats['created'],
				$stats['updated'],
				$stats['unchanged'],
				$stats['skipped'],
				$stats['total']
			)
		);

		WP_CLI::log( 'Output: ' . $generator->get_output_dir() );
	}

	/**
	 * Verify the static archive against published content.
	 *
	 * Checks all configured post types for missing, outdated, or orphaned
	 * archive files.
	 *
	 * ## EXAMPLES
	 *
	 *     wp static-archive verify
	 *
	 * @subcommand verify
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI signature.
	public function verify( $args, $assoc_args ) {
		$generator = new Static_Archive_Generator();
		$report    = $generator->verify();

		WP_CLI::log(
			sprintf(
				'Entries: %d total, %d archived.',
				$report['total_posts'],
				$report['total_archived']
			)
		);

		if ( ! empty( $report['missing'] ) ) {
			WP_CLI::warning( count( $report['missing'] ) . ' missing files:' );
			foreach ( $report['missing'] as $item ) {
				WP_CLI::log( sprintf( '  - [%d] %s (%s)', $item['id'], $item['slug'], $item['title'] ) );
			}
		}

		if ( ! empty( $report['outdated'] ) ) {
			WP_CLI::warning( count( $report['outdated'] ) . ' outdated files:' );
			foreach ( $report['outdated'] as $item ) {
				WP_CLI::log( sprintf( '  - [%d] %s (%s)', $item['id'], $item['slug'], $item['title'] ) );
			}
		}

		if ( ! empty( $report['orphaned'] ) ) {
			WP_CLI::warning( count( $report['orphaned'] ) . ' orphaned files:' );
			foreach ( $report['orphaned'] as $file ) {
				WP_CLI::log( '  - ' . $file );
			}
		}

		if ( empty( $report['missing'] ) && empty( $report['outdated'] ) && empty( $report['orphaned'] ) ) {
			WP_CLI::success( 'Archive is complete and up to date.' );
		}
	}
}

WP_CLI::add_command( 'static-archive', 'Static_Archive_CLI' );
