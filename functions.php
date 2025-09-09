<?php
/**
 * Confirms the presence of required environment variables.
 *
 * For the test runner to function correctly, a few requirements must be met:
 * - A database configuration must be provided using the documented environment
 *   variables.
 * - The preparation and test directories must be the same when running
 *   locally (not making use of an SSH connection).
 *
 * @param bool $check_db Optional. Whether to confirm `WPT_DB_*` environment
 *                       variables are present. Default true.
 *
 * @return void This function does not return a value but will halt execution
 *              if any required environment variable is missing.
 *
 * @uses getenv() to retrieve environment variable values.
 * @uses error_message() to display error messages for missing variables.
 * @uses log_message() to log a success message when all checks pass.
 */
function check_required_env( $check_db = true ) {

	$required = array(
		'WPT_PREPARE_DIR',
		'WPT_TEST_DIR',
		'WPT_DB_NAME',
		'WPT_DB_USER',
		'WPT_DB_PASSWORD',
		'WPT_DB_HOST',
	);
	foreach( $required as $var ) {
		if ( ! $check_db && 0 === strpos( $var, 'WPT_DB_' ) ) {
			continue;
		}
		if ( false === getenv( $var ) ) {
			error_message( $var . ' must be set as an environment variable.' );
		}
	}

	if ( empty( getenv( 'WPT_SSH_CONNECT' ) )
	     && getenv( 'WPT_TEST_DIR' ) !== getenv( 'WPT_PREPARE_DIR' ) ) {
		error_message( 'WPT_TEST_DIR must be the same as WPT_PREPARE_DIR when running locally.' );
	}

	log_message( 'Environment variables pass checks.' );
}

/**
 * Parses environment variables used to configure the test runner.
 *
 * @return array[] {
 *      Test runner configuration options.
 *
 *      @type array ...$0 {
 *          An associative array of test runner configuration options.
 *
 *          @type string $WPT_TEST_DIR               Path to the directory where wordpress-develop is placed for testing
 *                                                   after being prepared. Default '/tmp/wp-test-runner'.
 *          @type string $WPT_PREPARE_DIR            Path to the temporary directory where wordpress-develop is cloned
 *                                                   and configured. Default '/tmp/wp-test-runner'.
 *          @type string $WPT_SSH_CONNECT            List of inner blocks. An array of arrays that
 *                                                   have the same structure as this one.
 *          @type string $WPT_SSH_OPTIONS            HTML from inside block comment delimiters.
 *          @type string $WPT_PHP_EXECUTABLE         List of string fragments and null markers where
 *                                                   inner blocks were found.
 *          @type string $WPT_RM_TEST_DIR_CMD        Command for removing the test directory.
 *          @type string $WPT_REPORT_API_KEY         API key for submitting test results.
 *          @type bool   $WPT_CERTIFICATE_VALIDATION Whether to validate TLS certificates. Default true.
 *          @type bool   $WPT_DEBUG_MODE             Whether debug mode is enabled.
 *      }
 *  }
 */
function setup_runner_env_vars() {
	// Set the test directory first as it's needed for processing other variables.
	$runner_configuration = array(
		'WPT_TEST_DIR' => trim( getenv( 'WPT_TEST_DIR' ) ) ?: '/tmp/wp-test-runner',
	);

	/*
	 * When no value is provided for WPT_CERTIFICATE_VALIDATION, assume that the default of true (validate certificates)
	 * is desired.
	 */
	if ( false === getenv( 'WPT_CERTIFICATE_VALIDATION' ) ) {
		$runner_configuration['WPT_CERTIFICATE_VALIDATION'] = true;
	} else {
		$runner_configuration['WPT_CERTIFICATE_VALIDATION'] = (bool) getenv( 'WPT_CERTIFICATE_VALIDATION' );
	}

	return array_merge(
		$runner_configuration,
		array(
			// Directory configuration
			'WPT_PREPARE_DIR'            => trim( getenv( 'WPT_PREPARE_DIR' ) ) ?: '/tmp/wp-test-runner',
			// SSH connection configuration
			'WPT_SSH_CONNECT'            => trim( getenv( 'WPT_SSH_CONNECT' ) ),
			'WPT_SSH_OPTIONS'            => trim( getenv( 'WPT_SSH_OPTIONS' ) ) ?: '-o StrictHostKeyChecking=no',
			// Test execution configuration
			'WPT_PHP_EXECUTABLE'         => trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ?: 'php',
			// Cleanup configuration
			'WPT_RM_TEST_DIR_CMD'        => trim( getenv( 'WPT_RM_TEST_DIR_CMD' ) ) ?: 'rm -r ' . $runner_configuration['WPT_TEST_DIR'],
			// Reporting configuration
			'WPT_REPORT_API_KEY'         => trim( getenv( 'WPT_REPORT_API_KEY' ) ),
			// Miscellaneous
			'WPT_DEBUG'                  => (bool) getenv( 'WPT_DEBUG' ),
		)
	);
}

/**
 * Executes a set of shell commands.
 *
 * Each command is logged before being executed.
 *
 * When a non-zero return code is encountered, the error message is displayed
 * and the runner will fail.
 *
 * @param array $operations A list of shell commands (strings) to execute.
 * Each command should be a valid shell command and properly escaped for safety.
 * The commands are executed in the order they appear in the array.
 *
 * @return void This function does not return a value. However, it will output
 * the result of each shell command to the standard output and log the
 * execution. It will also halt on the first command that fails, displaying an
 * error message.
 *
 * @uses log_message() to log each operation before execution. This can be used
 * for debugging or auditing purposes.
 *
 * @uses passthru() to execute the shell command, which provides direct output
 * to the browser. Be aware that using this function with untrusted input can
 * lead to security vulnerabilities, such as command injection attacks.
 *
 * @uses error_message() to display an error message if a shell command fails.
 * The execution stops at the first failure.
 */
function perform_operations( $operations ) {
	foreach( $operations as $operation ) {
		log_message( $operation );
		passthru( $operation, $return_code );

		// Check for command execution failure.
		if ( 0 !== $return_code ) {
			error_message( 'Failed to perform operation: ' . $operation . '.' );
			return; // Halt execution on the first failure.
		}
	}
}

/**
 * Writes a message to the standard output (STDOUT).
 *
 * The message is appended with PHP_EOL to ensure proper line breaks on
 * different operating systems.
 *
 * @param string $message The message to be logged.
 *
 * @return void This function does not return a value. It directly writes the
 * message to STDOUT, which is typically visible in the console or terminal
 * where the PHP script is executed.
 *
 * @uses fwrite() to write the message to STDOUT. This is a low-level file
 * operation function that works with various file streams, including standard
 * output, standard error, and regular files.
 */
function log_message( $message ) {
	fwrite( STDOUT, $message . PHP_EOL ); // Write message to standard output.
}

/**
 * Displays an error message and terminates the test runner execution.
 *
 * The error message is prefixed with "Error: " and appended with PHP_EOL
 * before being written to the standard output (STDOUT).
 *
 * After outputting the error message, the script will be terminated with a
 * status code of 1.
 *
 * @param string $message The error message to be logged. This string will be
 * output as provided, but prefixed with "Error: " to indicate its nature,
 * followed by a system-specific newline character.
 *
 * @return void This function does not return a value. It directly writes the
 * error message to STDERR and then terminates the script execution using
 * `exit(1)`, indicating an error condition to the environment.
 *
 * @uses fwrite() to write the error message to STDERR. This function is used
 * for low-level writing to file streams or output streams, in this case,
 * STDERR, which is specifically for error reporting.
 */
function error_message( $message ) {
	fwrite( STDERR, 'Error: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Ensures a single trailing slash is present at the end of a given string.
 *
 * File system operations often expect a single trailing slash when referring
 * to directories or paths. This ensures that only one trailing slash is
 * present at the end of a given string.
 *
 * @param string $string The input string to which a trailing slash will be
 * added. This could be a file path, URL, or any other string that requires a
 * trailing slash for proper formatting or usage.
 *
 * @return string The modified string with a single trailing slash appended at
 * the end. If the input string already has one or more trailing slashes, they
 * will be trimmed to a single slash.
 *
 * @uses rtrim() to remove any existing trailing slashes from the input string
 * before appending a new trailing slash. This ensures that the result
 * consistently has exactly one trailing slash, regardless of the input string's
 * initial state.
 */
function trailingslashit( $string ) {
	return rtrim( $string, '/' ) . '/'; // Trim existing trailing slashes and append a single slash.
}

/**
 * Extracts test results from a JUnit XML string.
 *
 * This extracts the relevant information from the test results into a format
 * accepted and understood by the WordPress Test Reporter plugin.
 *
 * The data specifically extracted is:
 * - Total number of tests.
 * - Number of failures.
 * - Number of errors.
 * - Overall execution time.
 *
 * @param string $xml_string The JUnit XML data as a string. This should be
 * well-formed XML representing the results of test executions, typically
 * generated by testing frameworks compatible with JUnit reporting.
 *
 * @return string A JSON encoded string that represents a summary of the test
 * results, including overall metrics and detailed information about each failed
 * or errored test case. The JSON structure will include keys for 'tests',
 * 'failures', 'errors', 'time', and 'testsuites', where 'testsuites' is an
 * array of test suites that contains the failures or errors.
 *
 * @uses simplexml_load_string() to parse the JUnit XML data into an object for
 * easy access and manipulation of the XML elements.
 *
 * @uses xpath() to query specific elements within the XML structure,
 * particularly to find test suites with failures or errors.
 *
 * @uses json_encode() to convert the array structure containing the test
 * results into a JSON formatted string.
 */
function process_junit_xml( $xml_string )
{
	if ( empty( $xml_string ) ) {
		return '';
		return ''; // Return an empty string if the XML string is empty.
	}

	$xml = simplexml_load_string( $xml_string );
	$xml_string = null;
	$project = $xml->testsuite;
	$results = array();

	$results = array(
		'tests'    => (string) $project['tests'],
		'failures' => (string) $project['failures'],
		'errors'   => (string) $project['errors'],
		'time'     => (string) $project['time'],
	);

	$results['testsuites'] = array();

	// XPath query to find test suites with failures or errors.
	$testsuites = $xml->xpath( '//testsuites//testsuite[ ( count( testcase ) > 0 ) and ( @errors > 0 or @failures > 0 ) ]' );
	foreach ( $testsuites as $testsuite ) {
		$result = array(
			'name'     => (string) $testsuite['name'],
			'tests'    => (string) $testsuite['tests'],
			'failures' => (string) $testsuite['failures'],
			'errors'   => (string) $testsuite['errors']
		);

		// Only include suites with failures or errors.
		if ( empty( $result['failures'] ) && empty( $result['errors'] ) ) {
			continue;
		}
		$failures = array();
		foreach ( $testsuite->testcase as $testcase ) {
			// Capture both failure and error children.
			foreach ( array( 'failure', 'error') as $key ) {
				if ( isset( $testcase->{$key} ) ) {
					$failures[ (string) $testcase['name'] ] = array(
						'name' => (string) $testcase['name'],
						$key   => (string) $testcase->{$key},
					);
				}
			}
		}
		if ( $failures ) {
			$results['testsuites'][ (string) $testsuite['name'] ] = $result;
			$results['testsuites'][ (string) $testsuite['name'] ]['testcases'] = $failures;
		}
	}

	return json_encode( $results ); // Return the results as a JSON encoded string.
}

/**
 * Submits test results to a reporting API endpoint.
 *
 * This submits test results and related metadata to a site running the
 * WordPress Test Reporter plugin using cURL.
 *
 * Reports are always submitted to WordPress.org Unless the WPT_REPORT_URL
 * environment variable is set.
 *
 * @param string $results The test results in a processed format (e.g., JSON)
 * ready for submission to the reporting API.
 *
 * @param string $rev The SVN revision associated with the test results. This
 * often corresponds to a specific code commit or build identifier.
 *
 * @param string $message The SVN commit message associated with the revision,
 * providing context or notes about the changes.
 *
 * @param string $env The environment data in JSON format, detailing the
 * conditions under which the tests were run, such as operating system, PHP
 * version, etc.
 *
 * @param string $api_key The API key for authenticating with the reporting API,
 * ensuring secure and authorized access.
 *
 * @return array An array containing two elements: the HTTP status code of the
 * response (int) and the body of the response (string) from the reporting API.
 * This can be used to verify successful submission or to handle errors.
 *
 * @uses curl_init(), curl_setopt(), and curl_exec() to perform the HTTP POST
 * request to the reporting API.
 *
 * @uses json_encode() to encode the data payload as a JSON string for
 * submission.
 *
 * @uses base64_encode() to encode the API key for HTTP Basic Authentication in
 * the Authorization header.
 */
function upload_results( $results, $rev, $message, $env, $api_key ) {
	$WPT_REPORT_URL = getenv( 'WPT_REPORT_URL' );
	if ( ! $WPT_REPORT_URL ) {
		$WPT_REPORT_URL = 'https://make.wordpress.org/hosting/wp-json/wp-unit-test-api/v1/results'; // Default URL.
	}
	$process = curl_init( $WPT_REPORT_URL );
	$access_token = base64_encode( $api_key );
	$data = array(
		'results' => $results,
		'commit'  => $rev,
		'message' => $message,
		'env'     => $env,
	);

	$data_string = json_encode( $data ); // Convert data to JSON format.

	// Set CURL options.
	curl_setopt( $process, CURLOPT_TIMEOUT, 30 );
	curl_setopt( $process, CURLOPT_POST, 1 );
	curl_setopt( $process, CURLOPT_CUSTOMREQUEST, 'POST' );
	curl_setopt( $process, CURLOPT_USERAGENT, 'WordPress PHPUnit Test Runner' );
	curl_setopt( $process, CURLOPT_POSTFIELDS, $data_string );
	curl_setopt( $process, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $process, CURLOPT_HTTPHEADER, array(
		"Authorization: Basic $access_token",
		'Content-Type: application/json',
		'Content-Length: ' . strlen( $data_string )
	));

	$return = curl_exec( $process ); // Execute the request.
	$status_code = curl_getinfo( $process, CURLINFO_HTTP_CODE ); // Get the HTTP status code.
	curl_close( $process ); // Close CURL session.

	return array( $status_code, $return ); // Return status code and response.
}

/**
 Collects details about the testing environment.
 *
 * The versions of PHP, PHP modules, database software, and system utilities
 * can impact the results of the test suite. This gathers the relevant details
 * to include in test report submissions.
 *
 * @return array An associative array containing detailed environment
 *               information. The array includes:
 *               - 'php_version': The current PHP version.
 *               - 'php_modules': An associative array of selected PHP modules and their versions.
 *               - 'system_utils': Versions of certain system utilities such as 'curl', 'imagemagick',
 *                 'graphicsmagick', and 'openssl'.
 *               - 'mysql_version': The version of MySQL installed.
 *               - 'os_name': The name of the operating system.
 *               - 'os_version': The version of the operating system.
 *
 * @uses phpversion() to get the PHP version and module versions.
 *
 * @uses shell_exec() to execute system commands for retrieving MySQL version,
 *                    OS details, and versions of utilities like curl and OpenSSL.
 *
 * @uses class_exists() to check for the availability of the Imagick and Gmagick
 *                      classes for version detection.
 */
function get_env_details() {

	$gd_info = array();
	if( extension_loaded( 'gd' ) ) {
		$gd_info = gd_info(); // Get GD info if the GD extension is loaded.
	}
	$imagick_info = array();
	if( extension_loaded( 'imagick' ) ) {
		$imagick_info = Imagick::queryFormats(); // Get Imagick info if the Imagick extension is loaded.
	}

	$env = array(
		'php_version'    => phpversion(),
		'php_modules'    => array(),
		'gd_info'        => $gd_info,
		'imagick_info'   => $imagick_info,
		'mysql_version'  => trim( shell_exec( 'mysql --version' ) ),
		'system_utils'   => array(),
		'os_name'        => trim( shell_exec( 'uname -s' ) ),
		'os_version'     => trim( shell_exec( 'uname -r' ) ),
	);
	unset( $gd_info, $imagick_info );

	$php_modules = array(
		'bcmath',
		'ctype',
		'curl',
		'date',
		'dom',
		'exif',
		'fileinfo',
		'filter',
		'ftp',
		'gd',
		'gettext',
		'gmagick',
		'hash',
		'iconv',
		'imagick',
		'imap',
		'intl',
		'json',
		'libsodium',
		'libxml',
		'mbstring',
		'mcrypt',
		'mod_xml',
		'mysqli',
		'mysqlnd',
		'openssl',
		'pcre',
		'pdo_mysql',
		'soap',
		'sockets',
		'sodium',
		'xml',
		'xmlreader',
		'zip',
		'zlib',
	);
	foreach( $php_modules as $php_module ) {
		$env['php_modules'][ $php_module ] = phpversion( $php_module );
	}

	function curl_selected_bits($k) { return in_array($k, array('version', 'ssl_version', 'libz_version')); }
	$curl_bits = curl_version();
	$env['system_utils']['curl'] = implode(' ',array_values(array_filter($curl_bits, 'curl_selected_bits',ARRAY_FILTER_USE_KEY) ));

	$WPT_DB_HOST		 	= trim( getenv( 'WPT_DB_HOST' ) );
	if( ! $WPT_DB_HOST ) {
		$WPT_DB_HOST = 'localhost';
	}
	$WPT_DB_USER 			= trim( getenv( 'WPT_DB_USER' ) );
	$WPT_DB_PASSWORD 	= trim( getenv( 'WPT_DB_PASSWORD' ) );
	$WPT_DB_NAME 			= trim( getenv( 'WPT_DB_NAME' ) );

	//$mysqli = new mysqli( $WPT_DB_HOST, $WPT_DB_USER, $WPT_DB_PASSWORD, $WPT_DB_NAME );
	//$env['mysql_version'] = $mysqli->query("SELECT VERSION()")->fetch_row()[0];
	//$mysqli->close();

	if ( class_exists( 'Imagick' ) ) {
		$imagick = new Imagick();
		$version = $imagick->getVersion();
		preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', $version['versionString'], $matches );
		$env['system_utils']['imagemagick'] = $matches[1]; // Get Imagick version.
	} elseif ( class_exists( 'Gmagick' ) ) {
		$gmagick = new Gmagick();
		$version = $gmagick->getversion();
		preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', $version['versionString'], $matches );
		$env['system_utils']['graphicsmagick'] = $matches[1]; // Get GraphicsMagick version.
	}

	$env['system_utils']['openssl'] = str_replace( 'OpenSSL ', '', trim( shell_exec( 'openssl version' ) ) ); // Get OpenSSL version.

	return $env; // Return the collected environment details.
}
