<?php

/**
 * Runs the PHPUnit test suite in the test environment.
 */

require __DIR__ . '/functions.php';

// Check required environment variables
check_required_env();

$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );

// Run phpunit in the test environment
$phpunit_exec = 'cd ' . escapeshellarg( $WPT_TEST_DIR ) . '; php phpunit.phar';
if ( false !== $WPT_SSH_CONNECT ) {
	$phpunit_exec = 'ssh -o StrictHostKeyChecking=no ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $phpunit_exec );
}
perform_operations( array(
	$phpunit_exec,
) );
