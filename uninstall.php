<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-generator.php';

$generator = new Static_Archive_Generator();
$generator->delete_all();

delete_option( 'static_archive_filename_suffix' );
delete_option( 'static_archive_post_types' );
delete_option( 'static_archive_output_format' );
