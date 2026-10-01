<?php

use PHPUnit\Framework\TestCase;
use StaticArchive\AutomaticUpdates;

require_once dirname( __DIR__ ) . '/includes/class-automatic-updates.php';

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		return $GLOBALS['_test_scheduled'][ $hook ] ?? false;
	}
	function wp_schedule_single_event( $timestamp, $hook ) {
		$GLOBALS['_test_scheduled'][ $hook ] = $timestamp;
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

	public function testPendingUpdatesAreDeduplicatedAndDeletionPathsSurvive(): void {
		$post = (object) array( 'ID' => 42, 'post_type' => 'post', 'post_date' => '2026-10-01' );
		$job = AutomaticUpdates::make_job( $post, false );
		$GLOBALS['_test_options'][ AutomaticUpdates::OPTION ] = array( 42 => array( $job ) );
		AutomaticUpdates::update( $post );
		AutomaticUpdates::update( $post );
		$this->assertCount( 1, get_option( AutomaticUpdates::OPTION )[42] );
		AutomaticUpdates::update( $post, true );
		$pending = get_option( AutomaticUpdates::OPTION );
		$this->assertCount( 2, $pending[42] );
		$this->assertSame( $job['paths'], $pending[42][1]['paths'] );
		$this->assertTrue( $pending[42][1]['delete'] );
		$this->assertCount( 1, $GLOBALS['_test_scheduled'] );
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
			$this->assertArrayHasKey( 424242, get_option( AutomaticUpdates::OPTION ) );
			$this->assertFileDoesNotExist( $dir . '/post-424242-permission-test.html' );
			$this->assertSame( 'original archive', file_get_contents( $file ) );
			$this->assertNotFalse( wp_next_scheduled( AutomaticUpdates::HOOK ) );
		} finally {
			unlink( $file );
		}
	}

	public function testBlockedCronRetainsPendingDeletion(): void {
		$job = array( 'id' => 42, 'delete' => true, 'paths' => array( '/proc/version' ), 'year' => '2026' );
		$pending = array( 42 => array( $job ) );
		$GLOBALS['_test_options'][ AutomaticUpdates::OPTION ] = $pending;
		AutomaticUpdates::run_pending();
		$this->assertSame( $pending, get_option( AutomaticUpdates::OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( AutomaticUpdates::HOOK ) );
	}
}
