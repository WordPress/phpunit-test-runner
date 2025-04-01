<?php
/**
 * This script is responsible for reporting the results of the PHPUnit test runs
 * to WordPress.org. It gathers necessary information such as the SVN revision,
 * test run messages, and the junit.xml file containing the results. It then
 * uploads these details using the WordPress.org API if an API key is provided,
 * or logs the results for later use.
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
check_required_env( false );

/**
 * Retrieves environment variables and sets defaults for test preparation. These
 * variables are used to configure SSH connections, file paths, and executable
 * commands needed for setting up the test environment.
 */
$WPT_SSH_CONNECT          = trim( getenv( 'WPT_SSH_CONNECT' ) ) ? : '';
$WPT_TEST_DIR             = trim( getenv( 'WPT_TEST_DIR' ) ) ? : '/tmp/wp-test-runner';
$WPT_PREPARE_DIR          = trim( getenv( 'WPT_PREPARE_DIR' ) ) ? : '/tmp/wp-test-runner';
$WPT_SSH_OPTIONS          = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_REPORT_API_KEY       = trim( getenv( 'WPT_REPORT_API_KEY' ) ) ? : '';;
$WPT_PHP_EXECUTABLE_MULTI = trim( getenv( 'WPT_PHP_EXECUTABLE_MULTI' ) ) ? : '';

/**
 * Determines if the debug mode is enabled based on the 'WPT_DEBUG' environment
 * variable. The debug mode can affect error reporting and other debug-related
 * settings.
 */
$WPT_DEBUG_INI = trim( getenv( 'WPT_DEBUG' ) );
switch ( $WPT_DEBUG_INI ) {
	case '':
	default:
		$WPT_DEBUG = false;
		break;
	case 'verbose':
		$WPT_DEBUG = 'verbose';
		break;
}
unset( $WPT_DEBUG_INI );

/**
 * Determines if commits are enabled based on the 'WPT_COMMITS' environment
 * variable.
 */
$WPT_COMMITS_INI = (int) getenv( 'WPT_COMMITS' );
switch ( $WPT_COMMITS_INI ) {
	case 0:
	default:
		$WPT_COMMITS = false;
		break;
	case 1:
		$WPT_COMMITS = true;
		break;
}
unset( $WPT_COMMITS_INI );

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
 * Performs a series of operations to set up the test environment.
 * This includes handling commits, retrieving SVN revisions, copying results, processing junit.xml,
 * and uploading test results.
 */
foreach ( $php_executables as $php_multi ) {
	// Generate unique directory names based on the PHP version.
	$WPT_PREPARE_DIR_MULTI = $WPT_PREPARE_DIR . '-' . crc32( $php_multi['version'] );
	$WPT_TEST_DIR_MULTI = $WPT_TEST_DIR . '-' . crc32( $php_multi['version'] );

	/**
	 * Handle commits if $WPT_COMMITS is enabled.
	 */
	if ( $WPT_COMMITS ) {
		$commits_file = __DIR__ . '/commits.json';
		if ( file_exists( $commits_file ) ) {
			$c_array = json_decode( file_get_contents( $commits_file ), true );

			if ( isset( $c_array['testing_commit'] ) && count( $c_array['testing_commit'] ) ) {
				// Move the current testing commit to executed_commits
				$c_array['executed_commits'][] = $c_array['testing_commit'][0];
				unset( $c_array['testing_commit'][0] );
			}

			file_put_contents( $commits_file, json_encode( $c_array ) );
		}
	}

	/**
	 * Retrieve the SVN revision number from the git repository log.
	 */
	log_message( 'Getting SVN Revision' );
	$rev = exec( 'git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/.git' ) . ' log -1 --pretty=%B | grep "git-svn-id:" | cut -d " " -f 2 | cut -d "@" -f 2' );

	/**
	 * Retrieve the latest SVN commit message from the git repository log.
	 */
	log_message( 'Getting SVN message' );
	$message = trim( exec( 'git --git-dir=' . escapeshellarg($WPT_PREPARE_DIR_MULTI . '/.git') . ' log -1 --pretty=%B | head -1' ) );

	/**
	 * Copy the junit.xml results.
	 */
	log_message( 'Copying junit.xml results' );
	$junit_location = escapeshellarg( $WPT_TEST_DIR_MULTI ) . '/tests/phpunit/build/logs/*';

	if ( ! empty( $WPT_SSH_CONNECT ) ) {
		$junit_location = '-e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $junit_location );
	}

	/**
	 * Set rsync options based on the debug mode.
	 */
	$rsync_options = '-r';
	if ( 'verbose' === $WPT_DEBUG ) {
		$rsync_options .= 'v';
	}

	/**
	 * Construct and execute the rsync command.
	 */
	$junit_exec = 'rsync ' . $rsync_options . ' ' . $junit_location . ' ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI );
	perform_operations([
		$junit_exec,
	]);

	/**
	 * Process and upload the junit.xml file.
	 */
	log_message( 'Processing and uploading junit.xml' );
	$xml = file_get_contents( $WPT_PREPARE_DIR_MULTI . '/junit.xml' );
	$results = process_junit_xml( $xml );

	/**
	 * Retrieve environment details from a JSON file or generate them if not available.
	 */
	$env = '';
	$env_json_path = $WPT_PREPARE_DIR_MULTI . '/env.json';
	if ( file_exists( $env_json_path ) ) {
		$env = file_get_contents( $env_json_path );
	} elseif ( $WPT_PREPARE_DIR_MULTI === $WPT_TEST_DIR_MULTI ) {
		$env = json_encode( get_env_details(), JSON_PRETTY_PRINT );
	}

	/**
	 * Attempt to upload test results if an API key is available; otherwise, log the results locally.
	 */
	if ( ! empty( $WPT_REPORT_API_KEY ) ) {
		// Upload the results and capture the HTTP status and response body
		list( $http_status, $response_body ) = upload_results( $results, $rev, $message, $env, $WPT_REPORT_API_KEY );

		// Decode the JSON response body
		$response = json_decode( $response_body, true );
		if ( strpos( $http_status, '20' ) === 0 ) { // Check if status code starts with '20'
			// Construct and log a success message with a link if provided in the response
			$success_message = 'Results successfully uploaded';
			if ( isset( $response['link'] ) ) {
				$success_message .= ': ' . $response['link'];
			}
			log_message( $success_message );
		} else {
			// Construct and log an error message with additional details if provided in the response
			$error_message = 'Error uploading results';
			if ( isset( $response['message'] ) ) {
				$error_message .= ': ' . $response['message'];
			}
			$error_message .= ' (HTTP status ' . (int)$http_status . ')';
			error_message( $error_message );
		}
	} else {
		// Log the test results and environment details locally if no API key is provided
		log_message( '[+] TEST RESULTS' . "\n\n" . $results . "\n\n" );
		log_message( '[+] ENVIRONMENT' . "\n\n" . $env . "\n\n" );
	}
}
