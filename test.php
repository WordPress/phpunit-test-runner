<?php

/**
 * Executes the PHPUnit test suite within the WordPress testing environment.
 * This script is designed to run tests either locally or on a remote server based on the environment setup.
 * It dynamically constructs the command to run PHPUnit and then executes it.
 * 
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 * @package WordPress
 */
require __DIR__ . '/functions.php';

/**
 * Check for the presence of required environment variables.
 * This function should be defined in functions.php and should throw an
 * exception or exit if any required variables are missing.
 */
check_required_env();

/**
 * Retrieves environment variables and sets defaults for test preparation.
 * These variables are used to configure SSH connections, file paths, and
 * executable commands needed for setting up the test environment.
 */
$WPT_SSH_CONNECT    = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_TEST_DIR       = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_SSH_OPTIONS    = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_PHP_EXECUTABLE = trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ? : 'php';

// This uses `||` to run PHPUnit when it is downloaded manually (like for PHP 5.6-7.0) rather than through Composer.
$WPT_PHPUNIT_CMD = trim( getenv( 'WPT_PHPUNIT_CMD' ) ) ? : 'cd ' . escapeshellarg( $WPT_TEST_DIR ) . ' && ' . $WPT_PHP_EXECUTABLE . ' ./vendor/phpunit/phpunit/phpunit --dont-report-useless-tests || ' . $WPT_PHP_EXECUTABLE . ' phpunit.phar --dont-report-useless-tests';

// If an SSH connection string is provided, prepend the SSH command to the PHPUnit execution command.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$WPT_PHPUNIT_CMD = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_PHPUNIT_CMD );
}

// Execute the PHPUnit command.
perform_operations( array(
	$WPT_PHPUNIT_CMD
) );
