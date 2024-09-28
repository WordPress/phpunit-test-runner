<?php
/**
 * This script is responsible for cleaning up the test environment after a run
 * of the WordPress PHPUnit Test Runner. It ensures that temporary directories
 * and files created during the test process are properly deleted.
 * 
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 * 
 * @package WordPress
 */
require __DIR__ . '/functions.php';

/**
 * Check for the presence of required environment variables. This function
 * should be defined in functions.php and should throw an exception or exit if
 * any required variables are missing.
 */
check_required_env();

/**
 * Retrieves environment variables and sets defaults for test preparation. These
 * variables are used to configure SSH connections, file paths, and executable
 * commands needed for setting up the test environment.
 */
$WPT_PREPARE_DIR          = trim( getenv( 'WPT_PREPARE_DIR' ) ) ? : '/tmp/wp-test-runner';
$WPT_SSH_CONNECT          = trim( getenv( 'WPT_SSH_CONNECT' ) ) ? : '';
$WPT_SSH_OPTIONS          = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR             = trim( getenv( 'WPT_TEST_DIR' ) ) ? : '/tmp/wp-test-runner';
$WPT_PHP_EXECUTABLE_MULTI = trim( getenv( 'WPT_PHP_EXECUTABLE_MULTI' ) ) ? : '';

/**
 * Parses the multi PHP executable versions and populates the
 * WPT_PHP_EXECUTABLE_MULTI_ARRAY.
 */
$WPT_PHP_EXECUTABLE_MULTI_ARRAY = [];
if ( ! empty( $WPT_PHP_EXECUTABLE_MULTI ) ) {

	// Divide the version string by semicolon
	$php_multi_versions = explode( ';', $WPT_PHP_EXECUTABLE_MULTI );

	foreach ( $php_multi_versions as $php_multi_version ) {
		// Divide each version by the equals sign and apply trim to each part
		$parts = array_map( 'trim', explode( '=', $php_multi_version, 2 ) );

		// Ensures that both parts exist and are not empty.
		if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
			[ $version, $bin ] = $parts;
			$WPT_PHP_EXECUTABLE_MULTI_ARRAY[] = [
				'version' => $version,
				'bin'     => $bin
			];
		}
	}
}

// Prepare an array of PHP executables. If multi-PHP is configured, use the multi-array; otherwise, use a single executable.
$php_executables = ! empty( $WPT_PHP_EXECUTABLE_MULTI_ARRAY ) ? $WPT_PHP_EXECUTABLE_MULTI_ARRAY : [
	[
		'version' => 'default',
		'bin'     => $WPT_PHP_EXECUTABLE, // Ensure this variable is defined for the single PHP executable case.
	]
];

/**
 * Performs a series of operations to clean up the test environment.
 * This includes deleting specific directories and, if provided, cleaning up
 * remote directories via SSH.
 */
foreach ( $php_executables as $php_multi ) {
	// Generate unique directory names based on the PHP version.
	$WPT_PREPARE_DIR_MULTI = $WPT_PREPARE_DIR . '-' . crc32( $php_multi['version'] );
	$WPT_TEST_DIR_MULTI = $WPT_TEST_DIR . '-' . crc32( $php_multi['version'] );

	// Determines the command to remove the test directory, based on an environment variable or a default value.
	$WPT_RM_TEST_DIR_CMD = trim( getenv( 'WPT_RM_TEST_DIR_CMD' ) ) ?: 'rm -r ' . $WPT_TEST_DIR_MULTI;

	/**
	 * Forcibly removes only the .git directory and the node_modules cache.
	 * Then, remove the entire staging directory to ensure a clean state for the next test run.
	*/
	perform_operations([
		'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/.git' ),
		'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/node_modules/.cache' ),
		'rm -r ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),
	]);

	/**
	 * Clears the test directory on a remote server if an SSH connection string is provided.
	 * This conditional block checks if the WPT_SSH_CONNECT environment variable is set.
	 * If so, it executes the remote cleanup command using SSH.
	*/
	if ( ! empty( $WPT_SSH_CONNECT ) ) {
		perform_operations([
			'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $WPT_RM_TEST_DIR_CMD ),
		]);
	}
}
