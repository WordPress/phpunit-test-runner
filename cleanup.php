<?php
/**
 * Cleans up the test environment after a run of the WordPress PHPUnit Test Runner.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 * @package WordPress
 */
require __DIR__ . '/functions.php';

check_required_env();

$runner_vars = setup_runner_env_vars();

// Clean up the local preparation directory on the GHA runner.
perform_operations( array(
	'rm -rf ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/.git' ),
	'rm -rf ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/node_modules/.cache' ),
	'rm -r ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ),
) );

// Clean up the test directory on the Pantheon container.
$PANTHEON_SITE_NAME = $runner_vars['PANTHEON_SITE_NAME'];
$PANTHEON_SITE_ENV  = $runner_vars['PANTHEON_SITE_ENV'];
$site_env           = escapeshellarg( $PANTHEON_SITE_NAME . '.' . $PANTHEON_SITE_ENV );

$rm_cmd    = 'rm -rf ' . escapeshellarg( $runner_vars['WPT_TEST_DIR'] );
$eval_code = 'passthru(' . var_export( $rm_cmd, true ) . ');';
perform_operations( array(
	'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $eval_code ) . ' --skip-wordpress',
) );
