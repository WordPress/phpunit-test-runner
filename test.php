<?php

/**
 * Runs the PHPUnit test suite in the test environment.
 */

require __DIR__ . '/functions.php';

// Check required environment variables.
check_required_env();

$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' ) ? : '-o StrictHostKeyChecking=no';
$WPT_PHP_EXECUTABLE = getenv( 'WPT_PHP_EXECUTABLE' ) ? : 'php';
$WPT_PHPUNIT_CMD = getenv( 'WPT_PHPUNIT_CMD' ) ? : 'cd ' . escapeshellarg( $WPT_TEST_DIR ) . '; ' . $WPT_PHP_EXECUTABLE . ' phpunit.phar';

// Run phpunit in the test environment.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$WPT_PHPUNIT_CMD = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_PHPUNIT_CMD );
}
perform_operations( array(
	$WPT_PHPUNIT_CMD,
) );
