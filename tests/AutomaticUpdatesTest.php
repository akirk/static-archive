<?php

use PHPUnit\Framework\TestCase;

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
		$this->assertTrue( Static_Archive_Automatic_Updates::writable( sys_get_temp_dir() . '/static-archive-missing/subdir/post.html' ) );
	}

	public function testDeletionRequiresWritableDirectoryRatherThanFile(): void {
		$file = tempnam( sys_get_temp_dir(), 'sa-' );
		try {
			chmod( $file, 0444 );
			$this->assertFalse( Static_Archive_Automatic_Updates::writable( $file ) );
			$this->assertTrue( Static_Archive_Automatic_Updates::writable( $file, true ) );
		} finally {
			unlink( $file );
		}
	}

	public function testPendingUpdatesAreDeduplicatedAndDeletionPathsSurvive(): void {
		$post = (object) array( 'ID' => 42, 'post_type' => 'post', 'post_date' => '2026-10-01' );
		$job = Static_Archive_Automatic_Updates::make_job( $post, false );
		$GLOBALS['_test_options'][ Static_Archive_Automatic_Updates::OPTION ] = array( 42 => array( $job ) );
		Static_Archive_Automatic_Updates::update( $post );
		Static_Archive_Automatic_Updates::update( $post );
		$this->assertCount( 1, get_option( Static_Archive_Automatic_Updates::OPTION )[42] );
		Static_Archive_Automatic_Updates::update( $post, true );
		$pending = get_option( Static_Archive_Automatic_Updates::OPTION );
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
			Static_Archive_Automatic_Updates::update( $post );
			$this->assertArrayHasKey( 424242, get_option( Static_Archive_Automatic_Updates::OPTION ) );
			$this->assertFileDoesNotExist( $dir . '/post-424242-permission-test.html' );
			$this->assertSame( 'original archive', file_get_contents( $file ) );
			$this->assertNotFalse( wp_next_scheduled( Static_Archive_Automatic_Updates::HOOK ) );
		} finally {
			unlink( $file );
		}
	}

	public function testBlockedCronRetainsPendingDeletion(): void {
		$job = array( 'id' => 42, 'delete' => true, 'paths' => array( '/proc/version' ), 'year' => '2026' );
		$pending = array( 42 => array( $job ) );
		$GLOBALS['_test_options'][ Static_Archive_Automatic_Updates::OPTION ] = $pending;
		Static_Archive_Automatic_Updates::run_pending();
		$this->assertSame( $pending, get_option( Static_Archive_Automatic_Updates::OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Static_Archive_Automatic_Updates::HOOK ) );
	}
}
