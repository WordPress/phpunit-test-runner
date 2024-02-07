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

// Uses the flavor (usually to test WordPress Multisite)
$WPT_FLAVOR_INI = trim( getenv( 'WPT_FLAVOR' ) );
switch( $WPT_FLAVOR_INI ) {
	case 0:
		$WPT_FLAVOR_TXT = ''; // Simple WordPress
		break;
	case 1:
		$WPT_FLAVOR_TXT = ' -c tests/phpunit/multisite.xml'; // WordPress Multisite
		break;
	default:
		$WPT_FLAVOR_TXT = '';
		break;
}
unset( $WPT_FLAVOR_INI );

// Uses the flavor (usually to test WordPress Multisite)
$WPT_EXTRATESTS_INI = trim( getenv( 'WPT_EXTRATESTS' ) );
switch( $WPT_EXTRATESTS_INI ) {
	case 0:
		$WPT_EXTRATESTS_TXT = ''; // no extra tests
		break;
	case 1:
		$WPT_EXTRATESTS_TXT = ' --group ajax'; // ajax tests
		break;
	case 2:
		$WPT_EXTRATESTS_TXT = ' --group ms-files'; // ms-files tests
		break;
	case 3:
		$WPT_EXTRATESTS_TXT = ' --group external-http'; // external-http tests
		break;
	default:
		$WPT_EXTRATESTS_TXT = '';
		break;
}
unset( $WPT_EXTRATESTS_INI );

/**
 * Determines the PHPUnit command to execute the test suite.
 * Retrieves the PHPUnit command from the environment variable 'WPT_PHPUNIT_CMD'. If the environment
 * variable is not set or is empty, it constructs a default command using the PHP executable path and
 * the test directory path from environment variables, appending parameters to the PHPUnit call to
 * avoid reporting useless tests.
 */
$WPT_PHPUNIT_CMD = trim( getenv( 'WPT_PHPUNIT_CMD' ) );
if( empty( $WPT_PHPUNIT_CMD ) ) {
	$WPT_PHPUNIT_CMD = 'cd ' . escapeshellarg( $WPT_TEST_DIR ) . ' && ' . $WPT_PHP_EXECUTABLE . ' ./vendor/phpunit/phpunit/phpunit --dont-report-useless-tests' . $WPT_FLAVOR_TXT . $WPT_EXTRATESTS_TXT;
}

// If an SSH connection string is provided, prepend the SSH command to the PHPUnit execution command.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$WPT_PHPUNIT_CMD = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_PHPUNIT_CMD );
}

// Execute the PHPUnit command.
perform_operations( array(
	$WPT_PHPUNIT_CMD
) );
