<?php

use PHPUnit\Framework\TestCase;
use StaticArchive\AutomaticUpdates;

require_once dirname( __DIR__ ) . '/includes/class-automatic-updates.php';

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return $GLOBALS['_test_scheduled'][ $hook ][ serialize( $args ) ]['timestamp'] ?? false;
	}
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['_test_scheduled'][ $hook ][ serialize( $args ) ] = array( 'timestamp' => $timestamp, 'args' => $args );
		return true;
	}
	function _get_cron_array() {
		$cron = array();
		foreach ( $GLOBALS['_test_scheduled'] as $hook => $events ) {
			foreach ( $events as $key => $event ) {
				$cron[ $event['timestamp'] ][ $hook ][ $key ] = $event;
			}
		}
		return $cron;
	}
	function wp_unschedule_event( $timestamp, $hook, $args ) {
		unset( $GLOBALS['_test_scheduled'][ $hook ][ serialize( $args ) ] );
		return true;
	}

}

class AutomaticUpdatesTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['_test_options'] = array();
		$GLOBALS['_test_posts'] = array();
		$GLOBALS['_test_scheduled'] = array();
		$GLOBALS['_test_filters'] = array();
	}

	public function testMissingNestedDestinationUsesExistingAncestor(): void {
		$this->assertTrue( AutomaticUpdates::writable( sys_get_temp_dir() . '/static-archive-missing/subdir/post.html' ) );
	}

	public function testDeletionRequiresWritableDirectoryRatherThanFile(): void {
		$file = tempnam( sys_get_temp_dir(), 'sa-' );
		try {
			chmod( $file, 0444 );
			$this->assertFalse( AutomaticUpdates::writable( $file ) );
			$this->assertTrue( AutomaticUpdates::writable( $file, true ) );
		} finally {
			unlink( $file );
		}
	}

	public function testUnwritableYearArchiveDefersBeforeCreatingPostFile(): void {
		$GLOBALS['_test_options']['static_archive_filename_suffix'] = '-permission-test';
		$dir = '/tmp/wp-uploads/2099';
		wp_mkdir_p( $dir );
		$file = $dir . '/latest-permission-test.html';
		file_put_contents( $file, 'original archive' );
		chmod( $file, 0444 );
		$post = (object) array( 'ID' => 424242, 'post_type' => 'post', 'post_date' => '2099-10-01' );
		try {
			AutomaticUpdates::update( $post );
			AutomaticUpdates::update( $post );
			$job = AutomaticUpdates::make_job( $post, false );
			$this->assertCount( 1, $GLOBALS['_test_scheduled'][ AutomaticUpdates::HOOK ] );
			$this->assertFalse( get_option( 'static_archive_pending_updates' ) );
			$this->assertFileDoesNotExist( $dir . '/post-424242-permission-test.html' );
			$this->assertSame( 'original archive', file_get_contents( $file ) );
			$this->assertNotFalse( wp_next_scheduled( AutomaticUpdates::HOOK, array( $job ) ) );
		} finally {
			unlink( $file );
		}
	}

	public function testQueuedJobIsUpdatedAndRetainsEarlierPathsAndYears(): void {
		$post = (object) array( 'ID' => 42, 'post_type' => 'post', 'post_date' => '2025-10-01' );
		$old = AutomaticUpdates::make_job( $post, false );
		wp_schedule_single_event( 12345, AutomaticUpdates::HOOK, array( $old ) );
		$post->post_date = '2026-10-01';
		AutomaticUpdates::update( $post, true );
		$events = array_values( $GLOBALS['_test_scheduled'][ AutomaticUpdates::HOOK ] );
		$this->assertCount( 1, $events );
		$this->assertSame( 12345, $events[0]['timestamp'] );
		$job = $events[0]['args'][0];
		$this->assertTrue( $job['delete'] );
		$this->assertSame( array( '2025', '2026' ), $job['years'] );
		$this->assertCount( 4, $job['paths'] );
		$this->assertSame( $old['paths'], array_slice( $job['paths'], 0, 2 ) );
	}

	public function testBlockedCronReschedulesCapturedDeletionParameters(): void {
		$job = array( 'id' => 42, 'delete' => true, 'paths' => array( '/proc/version' ), 'years' => array( '2026' ) );
		AutomaticUpdates::run_pending( $job );
		$this->assertNotFalse( wp_next_scheduled( AutomaticUpdates::HOOK, array( $job ) ) );
		$event = $GLOBALS['_test_scheduled'][ AutomaticUpdates::HOOK ][ serialize( array( $job ) ) ];
		$this->assertSame( array( $job ), $event['args'] );
		$this->assertFalse( get_option( 'static_archive_pending_updates' ) );
	}
}
