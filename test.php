<?php

/**
 * Runs the PHPUnit test suite in the test environment.
 */

require __DIR__ . '/functions.php';

/**
 * Check for the presence of required environment variables.
 * This function should be defined in functions.php and should throw an
 * exception or exit if any required variables are missing.
 */
check_required_env();

$WPT_SSH_CONNECT    = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_TEST_DIR       = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_SSH_OPTIONS    = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_PHP_EXECUTABLE = trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ? : 'php';

// This uses `||` to run PHPUnit when it is downloaded manually (like for PHP 5.6-7.0) rather than through Composer.
$WPT_PHPUNIT_CMD = trim( getenv( 'WPT_PHPUNIT_CMD' ) ) ? : 'cd ' . escapeshellarg( $WPT_TEST_DIR ) . ' && ' . $WPT_PHP_EXECUTABLE . ' ./vendor/phpunit/phpunit/phpunit --dont-report-useless-tests || ' . $WPT_PHP_EXECUTABLE . ' phpunit.phar --dont-report-useless-tests';

// Run phpunit in the test environment.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$WPT_PHPUNIT_CMD = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_PHPUNIT_CMD );
}
perform_operations( array(
	$WPT_PHPUNIT_CMD,
) );
