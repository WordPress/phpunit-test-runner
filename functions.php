<?php
/**
 * Validates the presence of essential environment variables necessary for the application to run correctly.
 * Specifically checks for variables related to directories and database configuration. It also ensures that
 * the test and preparation directories are the same when running locally without SSH connection requirements.
 *
 * This function will issue error messages through `error_message()` for any missing environment variables
 * and logs a message upon successful validation of all required variables.
 *
 * @param bool $check_db Optional. Whether to include database-related environment variables in the check. Defaults to true.
 *                       If set to false, database variables (prefixed with 'WPT_DB_') are not checked.
 *
 * @return void This function does not return a value but will halt execution if any required environment variable is missing.
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
			error_message( $var . ' must be set as an environment variable. Did you remember to execute \'source .env\' to load the environment variables?' );
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
 *          @type bool   $WPT_DEBUG_MODE             Whether debug mode is enabled.
 *      }
 *  }
 */
function setup_runner_env_vars() {
	// Set the test directory first as it's needed for processing other variables.
	$runner_configuration = array(
		'WPT_TEST_DIR' => trim( getenv( 'WPT_TEST_DIR' ) ) ?: '/tmp/wp-test-runner',
	);



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
 * Executes a series of shell commands provided in the operations array. Each operation is logged before execution.
 * If any command fails (indicated by a non-zero return code), an error message is displayed. This function is
 * useful for automating batch shell tasks within a PHP script, with error handling for each operation.
 *
 * @param array $operations An array of shell commands (strings) to be executed. Each command should be
 *                          a valid shell command and properly escaped for safety. The commands are executed
 *                          in the order they appear in the array.
 *
 * @return void This function does not return a value. However, it will output the result of each shell command
 *              to the standard output and log the execution. It will also halt on the first command that fails,
 *              displaying an error message.
 *
 * @uses log_message() to log each operation before execution. This can be used for debugging or auditing purposes.
 * @uses passthru() to execute the shell command, which provides direct output to the browser. Be aware that using
 *      this function with untrusted input can lead to security vulnerabilities, such as command injection attacks.
 * @uses error_message() to display an error message if a shell command fails. The execution stops at the first failure.
 */
function perform_operations( $operations ) {
	foreach( $operations as $operation ) {
		log_message( $operation );
		passthru( $operation, $return_code );
		if ( 0 !== $return_code ) {
			error_message( 'Failed to perform operation.' );
		}
	}
}

/**
 * Writes a message followed by a newline to the standard output (STDOUT). This function is commonly used for logging purposes,
 * providing feedback during script execution, or debugging. The message is appended with the PHP end-of-line constant (PHP_EOL)
 * to ensure proper line breaks on different operating systems.
 *
 * @param string $message The message to be logged. This should be a string, and it will be output exactly as provided,
 *                        followed by a system-specific newline character.
 *
 * @return void This function does not return a value. It directly writes the message to STDOUT, which is typically
 *              visible in the console or terminal where the PHP script is executed.
 *
 * @uses fwrite() to write the message to STDOUT. This is a low-level file operation function that works with various
 *      file streams, including standard output, standard error, and regular files.
 */
function log_message( $message ) {
	fwrite( STDOUT, $message . PHP_EOL );
}

/**
 * Writes an error message prefixed with "Error: " to the standard error output (STDERR) and terminates the script
 * with a status code of 1. This function is typically used to report errors during script execution, where an
 * immediate halt is necessary due to unrecoverable conditions. The message is appended with the PHP end-of-line
 * constant (PHP_EOL) to ensure it is properly displayed on all operating systems.
 *
 * @param string $message The error message to be logged. This string will be output as provided, but prefixed
 *                        with "Error: " to indicate its nature, followed by a system-specific newline character.
 *
 * @return void This function does not return a value. It directly writes the error message to STDERR and then
 *              terminates the script execution using `exit(1)`, indicating an error condition to the environment.
 *
 * @uses fwrite() to write the error message to STDERR. This function is used for low-level writing to file streams
 *      or output streams, in this case, STDERR, which is specifically for error reporting.
 * @uses exit() to terminate the script execution with a status code of 1, indicating an error has occurred. This is
 *      a common practice in command-line scripts and applications to signal failure to the calling process or environment.
 */
function error_message( $message ) {
	fwrite( STDERR, 'Error: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Ensures a single trailing slash is present at the end of a given string. This function first removes any existing
 * trailing slashes from the input string to avoid duplication and then appends a single slash. It's commonly used
 * to normalize file paths or URLs to ensure consistency in format, especially when concatenating paths or performing
 * file system operations that expect a trailing slash.
 *
 * @param string $string The input string to which a trailing slash will be added. This could be a file path, URL,
 *                       or any other string that requires a trailing slash for proper formatting or usage.
 *
 * @return string The modified string with a single trailing slash appended at the end. If the input string already
 *                has one or more trailing slashes, they will be trimmed to a single slash.
 *
 * @uses rtrim() to remove any existing trailing slashes from the input string before appending a new trailing slash.
 *      This ensures that the result consistently has exactly one trailing slash, regardless of the input string's initial state.
 */
function trailingslashit( $string ) {
	return rtrim( $string, '/' ) . '/';
}

/**
 * Parses JUnit XML formatted string to extract test results, focusing specifically on test failures and errors.
 * The function converts the XML data into a structured JSON format that summarizes the overall test outcomes,
 * including the total number of tests, failures, errors, and execution time. Only test suites and cases that
 * contain failures or errors are included in the final JSON output. This function is useful for automated test
 * result analysis, continuous integration reporting, or any scenario where a quick summary of test failures and
 * errors is needed.
 *
 * @param string $xml_string The JUnit XML data as a string. This should be well-formed XML representing the results
 *                           of test executions, typically generated by testing frameworks compatible with JUnit reporting.
 *
 * @return string A JSON encoded string that represents a summary of the test results, including overall metrics and
 *                detailed information about each failed or errored test case. The JSON structure will include keys
 *                for 'tests', 'failures', 'errors', 'time', and 'testsuites', where 'testsuites' is an array of test
 *                suites that contains the failures or errors.
 *
 * @uses simplexml_load_string() to parse the JUnit XML data into an object for easy access and manipulation of the XML elements.
 * @uses xpath() to query specific elements within the XML structure, particularly to find test suites with failures or errors.
 * @uses json_encode() to convert the array structure containing the test results into a JSON formatted string.
 */
function process_junit_xml( $xml_string )
{
	if ( empty( $xml_string ) ) {
		return '';
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

	$testsuites = $xml->xpath( '//testsuites//testsuite[ ( count( testcase ) > 0 ) and ( @errors > 0 or @failures > 0 ) ]' );
	foreach ( $testsuites as $testsuite ) {
		$result = array(
			'name'     => (string) $testsuite['name'],
			'tests'    => (string) $testsuite['tests'],
			'failures' => (string) $testsuite['failures'],
			'errors'   => (string) $testsuite['errors']
		);
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

	return json_encode( $results );
}

/**
 * Submits test results along with associated metadata to a specified reporting API. The function constructs
 * a POST request containing the test results, SVN revision, SVN message, environment data, and uses an API key
 * for authentication. The reporting API's URL is retrieved from an environment variable; if not found, a default
 * URL is used. This function is typically used to automate the reporting of test outcomes to a centralized system
 * for analysis, tracking, and historical record-keeping.
 *
 * @param string $results The test results in a processed format (e.g., JSON) ready for submission to the reporting API.
 * @param string $rev     The SVN revision associated with the test results. This often corresponds to a specific code
 *                        commit or build identifier.
 * @param string $message The SVN commit message associated with the revision, providing context or notes about the changes.
 * @param string $env     The environment data in JSON format, detailing the conditions under which the tests were run,
 *                        such as operating system, PHP version, etc.
 * @param string $api_key The API key for authenticating with the reporting API, ensuring secure and authorized access.
 *
 * @return array An array containing two elements: the HTTP status code of the response (int) and the body of the response
 *               (string) from the reporting API. This can be used to verify successful submission or to handle errors.
 *
 * @uses curl_init(), curl_setopt(), and curl_exec() to perform the HTTP POST request to the reporting API.
 * @uses json_encode() to encode the data payload as a JSON string for submission.
 * @uses base64_encode() to encode the API key for HTTP Basic Authentication in the Authorization header.
 */
function upload_results( $results, $rev, $message, $env, $api_key ) {
	$WPT_REPORT_URL = getenv( 'WPT_REPORT_URL' );
	if ( ! $WPT_REPORT_URL ) {
		$WPT_REPORT_URL = 'https://make.wordpress.org/hosting/wp-json/wp-unit-test-api/v1/results';
	}
	$process = curl_init( $WPT_REPORT_URL );
	$access_token = base64_encode( $api_key );
	$data = array(
		'results' => $results,
		'commit'  => $rev,
		'message' => $message,
		'env'     => $env,
	);
	$data_string = json_encode( $data );

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

	$return = curl_exec( $process );
	$status_code = curl_getinfo( $process, CURLINFO_HTTP_CODE );
	curl_close( $process );

	return array( $status_code, $return );
}

/**
 * Collects and returns an array of key environment details relevant to the application's context. This includes
 * the PHP version, installed PHP modules with their versions, system utilities like curl and OpenSSL versions,
 * MySQL version, and operating system details. This function is useful for diagnostic purposes, ensuring
 * compatibility, or for reporting system configurations in debugging or error logs.
 *
 * The function checks for the availability of specific PHP modules and system utilities and captures their versions.
 * It uses shell commands to retrieve system information, which requires the PHP environment to have access to these
 * commands and appropriate permissions.
 *
 * @return array An associative array containing detailed environment information. The array includes:
 *               - 'php_version': The current PHP version.
 *               - 'php_modules': An associative array of selected PHP modules and their versions.
 *               - 'system_utils': Versions of certain system utilities such as 'curl', 'imagemagick', 'graphicsmagick', and 'openssl'.
 *               - 'mysql_version': The version of MySQL installed.
 *               - 'os_name': The name of the operating system.
 *               - 'os_version': The version of the operating system.
 *
 * @uses phpversion() to get the PHP version and module versions.
 * @uses shell_exec() to execute system commands for retrieving MySQL version, OS details, and versions of utilities like curl and OpenSSL.
 * @uses class_exists() to check for the availability of the Imagick and Gmagick classes for version detection.
 */
function get_env_details() {

	$gd_info = array();
	if( extension_loaded( 'gd' ) ) {
		$gd_info = gd_info();
	}
	$imagick_info = array();
	if( extension_loaded( 'imagick' ) ) {
		$imagick_info = Imagick::queryFormats();
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
		preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', $version['versionString'], $version );
		$env['system_utils']['imagemagick'] = $version[1];
	} elseif ( class_exists( 'Gmagick' ) ) {
		$gmagick = new Gmagick();
		$version = $gmagick->getversion();
		preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', $version['versionString'], $version );
		$env['system_utils']['graphicsmagick'] = $version[1];
	}

	$env['system_utils']['openssl'] = str_replace( 'OpenSSL ', '', trim( shell_exec( 'openssl version' ) ) );

	return $env;
}
