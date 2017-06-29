<?php

/**
 * Runs the PHPUnit test suite in the test environment.
 */

require __DIR__ . '/functions.php';

// Check required environment variables
check_required_env();

$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' ) ? : '-o StrictHostKeyChecking=no';
$WPT_PHP_EXECUTABLE = getenv( 'WPT_PHP_EXECUTABLE' ) ? : 'php';

// Run phpunit in the test environment
$phpunit_exec = 'cd ' . escapeshellarg( $WPT_TEST_DIR ) . '; ' . $WPT_PHP_EXECUTABLE . ' phpunit.phar';
if ( false !== $WPT_SSH_CONNECT ) {
	$phpunit_exec = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $phpunit_exec );
}
perform_operations( array(
	$phpunit_exec,
) );
