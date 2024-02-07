<?php
/**
 * This script is responsible for cleaning up the test environment after a run of the WordPress PHPUnit Test Runner.
 * It ensures that temporary directories and files created during the test process are properly deleted.
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
$WPT_PREPARE_DIR     = trim( getenv( 'WPT_PREPARE_DIR' ) );
$WPT_SSH_CONNECT     = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_SSH_OPTIONS     = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR        = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_RM_TEST_DIR_CMD = trim( getenv( 'WPT_RM_TEST_DIR_CMD' ) ) ? : 'rm -r ' . $WPT_TEST_DIR;

/**
 * The directory path of the test preparation directory is assumed to be previously defined.
 * For example: $WPT_PREPARE_DIR = '/path/to/your/preparation/dir';
 * Clean up the preparation directory.
 * Forcefully deletes only the .git directory and the node_modules cache.
 * Afterward, the entire preparation directory is removed to ensure a clean state for the next test run.
 */
perform_operations( array(
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/.git' ),
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/node_modules/.cache' ),
	'rm -r ' . escapeshellarg( $WPT_PREPARE_DIR ),
) );

/**
 * Cleans up the test directory on a remote server.
 * This conditional block checks if an SSH connection string is provided and is not empty.
 * If a connection string is present, it triggers a cleanup operation on the remote environment.
 * The cleanup operation is executed by the `perform_operations` function which takes an array
 * of shell commands as its input.
 */
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	perform_operations( array(
		'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_RM_TEST_DIR_CMD ),
	) );
}
