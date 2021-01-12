<?php

/**
 * Cleans up the environment after the test run.
 */

require __DIR__ . '/functions.php';

// Check required environment variables.
check_required_env();

// Bring some environment variables into scope.
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );
$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );
$WPT_RM_TEST_DIR_CMD = getenv( 'WPT_RM_TEST_DIR_CMD' ) ? : 'rm -r ' . $WPT_TEST_DIR;

// Clean up the preparation directory.
// Only forcefully delete the .git directory, to prevent disasters otherwise.
perform_operations( array(
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/.git' ),
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/node_modules/.cache' ),
	'rm -r ' . escapeshellarg( $WPT_PREPARE_DIR ),
) );

// Clean up the test directory in remote environments.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	perform_operations( array(
		'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_RM_TEST_DIR_CMD ),
	) );
}
