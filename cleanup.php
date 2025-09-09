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

/**
 * Ensure that all environment variables are present with default values.
 */
$runner_vars = setup_runner_env_vars();

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
	'rm -rf ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/.git' ),
	'rm -rf ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/node_modules/.cache' ),
	'rm -r ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ),
) );

/*
 * Clean up the test directory on a remote server.
 *
 * This ensures a clean slate on the remote server the next time the test
 * runner is executed.
 */
if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
	perform_operations( array(
		'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $runner_vars['WPT_RM_TEST_DIR_CMD'] ),
	) );
}
