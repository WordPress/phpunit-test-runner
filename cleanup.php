<?php
/**
 * WordPress PHPUnit Test Runner: Cleanup script
 *
 * This script is responsible for cleaning up the test environment after the
 * Test Runner completes.
 *
 * All files and directories created by the test runner or the PHPUnit test
 * suite are removed.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 *
 * @package WordPress
 */
require __DIR__ . '/functions.php';

/*
 * Check for the presence of required environment variables.
 *
 * This function should be defined in functions.php and should throw an
 * exception or exit if any required variables are missing.
 */
check_required_env();

/*
 * Retrieve environment variables falling back to defaults.
 *
 * These variables are used to configure SSH connections, file paths, and
 * executable commands needed for setting up the test environment.
 */
$WPT_PREPARE_DIR     = trim( getenv( 'WPT_PREPARE_DIR' ) );
$WPT_SSH_CONNECT     = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_SSH_OPTIONS     = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR        = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_RM_TEST_DIR_CMD = trim( getenv( 'WPT_RM_TEST_DIR_CMD' ) ) ? : 'rm -r ' . $WPT_TEST_DIR;

/*
 * Clean up the test preparation directory.
 *
 * This ensures a clean slate the next time the test runner is executed.
 *
 * `WPT_PREPARE_DIR` will exist so long as prepare.php ran correctly.
 *
 * The following actions are performed:
 * - Forcefully deletes only the .git directory and the node_modules cache.
 * - Forcefully remove the `node_modules/.cache` directory.
 * - Remove the entire preparation directory.
 */
perform_operations( array(
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/.git' ),
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/node_modules/.cache' ),
	'rm -r ' . escapeshellarg( $WPT_PREPARE_DIR ),
) );

/*
 * Clean up the test directory on a remote server.
 *
 * This ensures a clean slate on the remote server the next time the test
 * runner is executed.
 */
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	perform_operations( array(
		'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_RM_TEST_DIR_CMD ),
	) );
}
