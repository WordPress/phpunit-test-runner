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
 * Ensure that all environment variables are present with default values.
 */
$runner_vars = setup_runner_env_vars();

/**
 * The directory path of the test preparation directory is assumed to be previously defined.
 * For example: $runner_vars['WPT_PREPARE_DIR'] = '/path/to/your/preparation/dir';
 * Clean up the preparation directory.
 * Forcefully deletes only the .git directory and the node_modules cache.
 * Afterward, the entire preparation directory is removed to ensure a clean state for the next test run.
 */
perform_operations( array(
	'rm -rf ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/.git' ),
	'rm -rf ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/node_modules/.cache' ),
	'rm -r ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ),
) );

/**
 * Cleans up the test directory on a remote server.
 * This conditional block checks if an SSH connection string is provided and is not empty.
 * If a connection string is present, it triggers a cleanup operation on the remote environment.
 * The cleanup operation is executed by the `perform_operations` function which takes an array
 * of shell commands as its input.
 */
if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
	perform_operations( array(
		'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $runner_vars['WPT_RM_TEST_DIR_CMD'] ),
	) );
}
