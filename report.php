<?php

/**
 * Reports the test run results to WordPress.org
 */

require __DIR__ . '/functions.php';
require __DIR__ . '/vendor/autoload.php';

// Check required environment variables
check_required_env();

$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' );

log_message('Getting SVN Revision');
$rev = exec('git -C ' . escapeshellarg( $WPT_PREPARE_DIR ) . ' log -1 --pretty=%B | grep "git-svn-id:" | cut -d " " -f 2 | cut -d "@" -f 2');

log_message('Copying junit.xml results');
$junit_location = escapeshellarg( $WPT_TEST_DIR ) . '/tests/phpunit/build/logs/junit.xml';

if ( false !== $WPT_SSH_CONNECT ) {
	$junit_location = '-e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $junit_location );
}

$junit_exec = 'rsync -rv ' . $junit_location . ' ./';
perform_operations( array(
	$junit_exec,
) );

log_message( 'Processing and uploading junit.xml' );
$success = upload( process( './junit.xml' ), $rev );

if ( $success ) {
	log_message( 'Results successfully uploaded' );
} else {
	log_message( 'Error uploading results' );
}

function process( $path )
{
	$xml = simplexml_load_file( $path );
	$project = $xml->testsuite;
	$results = array();

	$results = array(
		'tests' => (string) $project['tests'],
		'failures' => (string) $project['failures'],
		'errors' => (string) $project['errors'],
	);

	$results['testsuites'] = array();
	// log_message( sprintf( 'Tests: %s Failures: %s, Errors: %s', (string) $project['tests'], (string) $project['failures'], (string) $project['errors']) );
	foreach ($project->testsuite as $testsuite) {
		// log_message( sprintf( 'Testsuite: %s Tests: %s Failures: %s, Errors: %s', (string) $testsuite['name'], (string) $testsuite['tests'], (string) $testsuite['failures'], (string) $testsuite['errors']) );
		$results['testsuites'][ (string) $testsuite['name'] ] = array();
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

function upload( $content, $rev ) {
	$data = array(
		'results' => $content,
		'commit' => $rev,
	);

	$api_instance = new octalmage\WPUnitTestApi\Api\DefaultApi();
	$results = new \octalmage\WPUnitTestApi\Model\NewResult( $data );

	try {
		$result = $api_instance->addResults( $results );
	} catch (Exception $e) {
		echo 'Exception when calling DefaultApi->addResults: ', $e->getMessage(), PHP_EOL;
	}

	return $result->getSuccess();
}
