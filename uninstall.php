<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-generator.php';

$generator = new Static_Archive_Generator();
$generator->delete_all();

delete_option( 'static_archive_filename_suffix' );
