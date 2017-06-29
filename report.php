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
$WPT_REPORT_API_KEY = getenv( 'WPT_REPORT_API_KEY' );

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
$success = upload( processJUnitXML( './junit.xml' ), $rev, $WPT_REPORT_API_KEY );

if ( $success ) {
	log_message( 'Results successfully uploaded' );
} else {
	log_message( 'Error uploading results' );
}

function upload( $content, $rev, $api_key ) {
	$data = array(
		'results' => $content,
		'commit' => $rev,
	);

	$access_token = base64_encode( $api_key );
	$config = new \octalmage\WPUnitTestApi\Configuration();
	$config->addDefaultHeader('Authorization', "Basic $access_token");
	$client = new \octalmage\WPUnitTestApi\ApiClient( $config );
	$api_instance = new octalmage\WPUnitTestApi\Api\DefaultApi( $client );
	$results = new octalmage\WPUnitTestApi\Model\NewResult( $data );

	try {
		$result = $api_instance->addResults( $results );
		return $result->getSuccess();
	} catch (Exception $e) {
		echo 'Exception when calling DefaultApi->addResults: ', $e->getMessage(), PHP_EOL;
		return false;
	}
}
