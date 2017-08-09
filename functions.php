<?php

/**
 * Utility functions for the test runner.
 */

/**
 * Check for required environment variables.
 */
function check_required_env() {

	$required = array(
		'WPT_PREPARE_DIR',
		'WPT_TEST_DIR',
		'WPT_DB_NAME',
		'WPT_DB_USER',
		'WPT_DB_PASSWORD',
		'WPT_DB_HOST',
	);
	foreach( $required as $var ) {
		if ( false === getenv( $var ) ) {
			error_message( $var . ' must be set as an environment variable.' );
		}
	}

	if ( false === getenv( 'WPT_SSH_CONNECT' )
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
	$xml = simplexml_load_string( $xml_string );
	$xml_string = null;
	$project = $xml->testsuite;
	$results = array();

	$results = array(
		'tests' => (string) $project['tests'],
		'failures' => (string) $project['failures'],
		'errors' => (string) $project['errors'],
	);

	$results['testsuites'] = array();
	foreach ( $project->testsuite as $testsuite ) {
		$results['testsuites'][ (string) $testsuite['name'] ] = array(
			'name' => (string) $testsuite['name'],
			'tests' => (string) $testsuite['tests'],
			'failures' => (string) $testsuite['failures'],
			'errors' => (string) $testsuite['errors']
		);
		$results['testsuites'][ (string) $testsuite['name'] ]['testcases'] = array();
		foreach ( $testsuite->testcase as $testcase ) {
			if ( isset( $testcase->failure ) ) {
				$results['testsuites'][ (string) $testsuite['name'] ]['testcases'][ (string) $testcase['name'] ] = array(
					'name' => (string) $testcase['name'],
					'failure' => (string) $testcase->failure,
				);
			}
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
 * @param  string $meta    The metadata in JSON format.
 * @param  string $api_key The API key for the reporting API.
 * @return array           Response from the reporting API.
 */
function upload_results( $results, $rev, $message, $meta, $api_key ) {
	$process = curl_init( 'https://wpunittestapi.wpengine.com/wp-json/wp-unit-test-api/v1/results' );
	$access_token = base64_encode( $api_key );
	$data = array(
		'results' => $results,
		'commit' => $rev,
		'message' => $message,
		'meta' => $meta,
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
