<?php

/**
 * Reports the test run results to WordPress.org
 */

require __DIR__ . '/functions.php';

// Check required environment variables.
check_required_env( false );

$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' );
$WPT_REPORT_API_KEY = getenv( 'WPT_REPORT_API_KEY' );
$WPT_DEBUG = getenv( 'WPT_DEBUG' );

log_message('Getting SVN Revision');
$rev = exec('git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR ) . '/.git log -1 --pretty=%B | grep "git-svn-id:" | cut -d " " -f 2 | cut -d "@" -f 2');

log_message('Getting SVN message');
$message = trim( exec('git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR ) . '/.git log -1 --pretty=%B | head -1') );

log_message('Copying junit.xml results');
$junit_location = escapeshellarg( $WPT_TEST_DIR ) . '/tests/phpunit/build/logs/*';

if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$junit_location = '-e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $junit_location );
}

$rsync_options = '-r';

if ( 'verbose' === $WPT_DEBUG ) {
	$rsync_options = $rsync_options . 'v';
}

$junit_exec = 'rsync ' . $rsync_options . ' ' . $junit_location . ' ' . escapeshellarg( $WPT_PREPARE_DIR );
perform_operations( array(
	$junit_exec,
) );

log_message( 'Processing and uploading junit.xml' );

$xml = file_get_contents( $WPT_PREPARE_DIR . '/junit.xml' );
$results = process_junit_xml( $xml );

$env = '';
if ( file_exists( $WPT_PREPARE_DIR . '/env.json' ) ) {
	$env = file_get_contents( $WPT_PREPARE_DIR . '/env.json' );
} elseif ( $WPT_PREPARE_DIR === $WPT_TEST_DIR ) {
	$env = json_encode( get_env_details(), JSON_PRETTY_PRINT );
}

list( $http_status, $response_body ) = upload_results( $results, $rev, $message, $env, $WPT_REPORT_API_KEY );

$response = json_decode( $response_body, true );
if ( 20 == substr( $http_status, 0, 2 ) ) {
	$message = 'Results successfully uploaded';
	$message .= isset( $response['link'] ) ? ': ' . $response['link'] : '';
	log_message( $message );
} else {
	$message = 'Error uploading results';
	$message .= isset( $response['message'] ) ? ': ' . $response['message'] : '';
	$message .= ' (HTTP status ' . (int) $http_status . ')';
	error_message( $message );
}
