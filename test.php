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

/**
 * Determines the PHPUnit command to execute the test suite.
 * Retrieves the PHPUnit command from the environment variable 'WPT_PHPUNIT_CMD'. If the environment
 * variable is not set or is empty, it constructs a default command using the PHP executable path and
 * the test directory path from environment variables, appending parameters to the PHPUnit call to
 * avoid reporting useless tests.
 */
$wpt_phpunit_cmd = trim( getenv( 'WPT_PHPUNIT_CMD' ) );
if ( empty( $wpt_phpunit_cmd ) ) {
	$wpt_phpunit_cmd = 'cd ' . escapeshellarg( $runner_vars['WPT_TEST_DIR'] ) . ' && ' . $runner_vars['WPT_PHP_EXECUTABLE'] . ' ./vendor/phpunit/phpunit/phpunit --dont-report-useless-tests' . $wpt_flavor_txt . $wpt_extratests_txt;
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
