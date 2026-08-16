<?php
/**
 * WordPress PHPUnit Test Runner: Test script
 *
 * This script executes the WordPress Core PHPUnit test suite within the
 * prepared environment.
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

// Uses the flavor (usually to test WordPress Multisite)
$wpt_flavor_ini = trim( getenv( 'WPT_FLAVOR' ) );
switch ( $wpt_flavor_ini ) {
	case 0:
		$wpt_flavor_txt = ''; // Simple WordPress
		break;
	case 1:
		$wpt_flavor_txt = ' -c tests/phpunit/multisite.xml'; // WordPress Multisite
		break;
	default:
		$wpt_flavor_txt = '';
		break;
}
unset( $wpt_flavor_ini );

// Uses the extra tests group (e.g., ajax, ms-files, external-http)
$wpt_extratests_ini = trim( getenv( 'WPT_EXTRATESTS' ) );
switch ( $wpt_extratests_ini ) {
	case 0:
		$wpt_extratests_txt = ''; // no extra tests
		break;
	case 1:
		$wpt_extratests_txt = ' --group ajax'; // ajax tests
		break;
	case 2:
		$wpt_extratests_txt = ' --group ms-files'; // ms-files tests
		break;
	case 3:
		$wpt_extratests_txt = ' --group external-http'; // external-http tests
		break;
	default:
		$wpt_extratests_txt = '';
		break;
}
unset( $wpt_extratests_ini );

// Construct the command used for running the PHPUnit tests.
$WPT_PHPUNIT_CMD = trim( getenv( 'WPT_PHPUNIT_CMD' ) );
if( empty( $WPT_PHPUNIT_CMD ) ) {
	$WPT_PHPUNIT_CMD = 'cd ' . escapeshellarg( $runner_vars['WPT_TEST_DIR'] ) . ' && ' . $runner_vars['WPT_PHP_EXECUTABLE'] . ' ./vendor/phpunit/phpunit/phpunit --dont-report-useless-tests' . $WPT_FLAVOR_TXT . $WPT_EXTRATESTS_TXT;
}

// If an SSH connection string is provided, prepend the SSH command to the PHPUnit execution command.
if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
	$wpt_phpunit_cmd = 'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $wpt_phpunit_cmd );
}

// Execute the PHPUnit command.
perform_operations(
	array(
		$wpt_phpunit_cmd,
	)
);
