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

skip_if_no_prepared_environment( $runner_vars );

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

$custom_phpunit_cmd = trim( getenv( 'WPT_PHPUNIT_CMD' ) );

foreach ( $runner_vars['WPT_PHP_EXECUTABLES'] as $php ) {
	$paths = get_php_run_paths( $runner_vars, $php );

	log_message( 'Running tests for PHP ' . $php['version'] . ' (' . $php['bin'] . ')' );

	if ( '' !== $custom_phpunit_cmd && '' === $php['suffix'] ) {
		$wpt_phpunit_cmd = $custom_phpunit_cmd;
	} else {
		$wpt_phpunit_cmd = 'cd ' . escapeshellarg( $paths['test_dir'] ) . ' && ' . $php['bin'] . ' ./vendor/phpunit/phpunit/phpunit --dont-report-useless-tests' . $wpt_flavor_txt . $wpt_extratests_txt;
	}

	if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		$wpt_phpunit_cmd = 'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $wpt_phpunit_cmd );
	}

	perform_operations(
		array(
			$wpt_phpunit_cmd,
		)
	);
}
