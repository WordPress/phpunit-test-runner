<?php

/**
 * Utility functions for the test runner.
 */

/**
 * Check for required environment variables.
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
 * Perform some number of shell operations
 *
 * @param array $operations
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
 * Log a message to STDOUT
 *
 * @param string $message
 */
function log_message( $message ) {
	fwrite( STDOUT, $message . PHP_EOL );
}

/**
 * Log an error message to STDERR
 *
 * @param string $message
 */
function error_message( $message ) {
	fwrite( STDERR, 'Error: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Add a trailing slash to the string
 *
 * @param string
 * @return string
 */
function trailingslashit( $string ) {
	return rtrim( $string, '/' ) . '/';
}

/**
 * Process JUnit test results and return JSON. The resulting JSON will only
 * include failures.
 *
 * @param  string $xml String containing JUnit results.
 * @return string
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
 * Upload the results to the reporting API.
 *
 * @param  string $content The processed JUnit XML.
 * @param  string $rev     The SVN revision.
 * @param  string $message The SVN message.
 * @param  string $env     The environment data in JSON format
 * @param  string $api_key The API key for the reporting API.
 * @return array           Response from the reporting API.
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
 * Get the environmental details
 */
function get_env_details() {
	$env = array(
		'php_version'    => phpversion(),
		'php_modules'    => array(),
		'system_utils'   => array(),
		'mysql_version'  => trim( shell_exec( 'mysql --version' ) ),
		'os_name'        => trim( shell_exec( 'uname -s' ) ),
		'os_version'     => trim( shell_exec( 'uname -r' ) ),
	);
	$php_modules = array(
		'bcmath',
		'curl',
		'filter',
		'gd',
		'libsodium',
		'mcrypt',
		'mod_xml',
		'mysqli',
		'imagick',
		'gmagick',
		'pcre',
		'xml',
		'xmlreader',
		'zlib',
	);
	foreach( $php_modules as $php_module ) {
		$env['php_modules'][ $php_module ] = phpversion( $php_module );
	}
	$curl_bits = explode( PHP_EOL, str_replace( 'curl ', '', shell_exec( 'curl --version' ) ) );
	$curl = array_shift( $curl_bits );
	$env['system_utils']['curl'] = trim( $curl );
	$env['system_utils']['ghostscript'] = trim( shell_exec( 'gs --version' ) );
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
