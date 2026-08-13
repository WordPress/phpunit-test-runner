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

skip_if_no_prepared_environment( $runner_vars );

foreach ( $runner_vars['WPT_PHP_EXECUTABLES'] as $php ) {
	$paths = get_php_run_paths( $runner_vars, $php );

	log_message( 'Cleaning environment for PHP ' . $php['version'] );

	if ( is_dir( $paths['prepare_dir'] ) ) {
		perform_operations(
			array(
				'rm -rf ' . escapeshellarg( $paths['prepare_dir'] . '/.git' ),
				'rm -rf ' . escapeshellarg( $paths['prepare_dir'] . '/node_modules/.cache' ),
				'rm -r ' . escapeshellarg( $paths['prepare_dir'] ),
			)
		);
	}

	if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		perform_operations(
			array(
				'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $paths['rm_cmd'] ),
			)
		);
	}
}
