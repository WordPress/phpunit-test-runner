<?php
/**
 * Reports PHPUnit test results to WordPress.org.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 * @package WordPress
 */
require __DIR__ . '/functions.php';

check_required_env( false );

$runner_vars        = setup_runner_env_vars();
$PANTHEON_SITE_NAME = $runner_vars['PANTHEON_SITE_NAME'];
$PANTHEON_SITE_ENV  = $runner_vars['PANTHEON_SITE_ENV'];
$site_env           = escapeshellarg( $PANTHEON_SITE_NAME . '.' . $PANTHEON_SITE_ENV );
$test_dir           = $runner_vars['WPT_TEST_DIR'];
$logs_dir           = $test_dir . '/tests/phpunit/build/logs/';
$logs_local         = $runner_vars['WPT_PREPARE_DIR'] . '/tests/phpunit/build/logs/';

log_message( 'API key present: ' . ( ! empty( $runner_vars['WPT_REPORT_API_KEY'] ) ? 'yes' : 'NO — results will not be uploaded' ) );

/**
 * Get git commit info from Pantheon in a single terminus call.
 * wordpress-develop uses git (not SVN), so we use the commit hash and subject.
 */
log_message( 'Getting commit info from Pantheon' );
$git_php  = 'echo json_encode(["hash"=>trim(shell_exec("git -C " . escapeshellarg(' . var_export( $test_dir, true ) . ') . " log -1 --pretty=%H 2>/dev/null")), "msg"=>trim(shell_exec("git -C " . escapeshellarg(' . var_export( $test_dir, true ) . ') . " log -1 --pretty=%s 2>/dev/null"))]);';
$git_json = trim( shell_exec( 'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $git_php ) . ' --skip-wordpress --quiet 2>/dev/null' ) );
$git_info = json_decode( $git_json, true );
$hash     = $git_info['hash'] ?? '';
$message  = $git_info['msg']  ?? '';

// The WordPress.org hosting test API expects an SVN revision number for 'commit' (e.g. 62519).
// Fetch the current trunk revision from the SVN WebDAV endpoint using curl (no svn client needed).
$svn_xml = shell_exec( 'curl -s --max-time 10 -X PROPFIND "https://develop.svn.wordpress.org/trunk" -H "Depth: 0" 2>/dev/null' );
preg_match( '/<[^>]+version-name[^>]*>(\d+)</', $svn_xml, $svn_matches );
$rev = $svn_matches[1] ?? '';
if ( ! empty( $hash ) ) {
	$message = substr( $hash, 0, 10 ) . ' ' . $message;
}
log_message( 'SVN revision: ' . ( $rev ?: '(empty)' ) );
log_message( 'Git commit:   ' . ( $hash ?: '(empty)' ) );
log_message( 'Message:      ' . ( $message ?: '(empty)' ) );

/**
 * Retrieve result files from Pantheon by base64-encoding them through terminus eval.
 */
log_message( 'Fetching test results from Pantheon' );

if ( ! is_dir( $logs_local ) ) {
	mkdir( $logs_local, 0777, true );
}

// List what's actually in the logs directory on Pantheon so we can see what was written.
log_message( 'Listing logs directory on Pantheon: ' . $logs_dir );
$ls_php  = 'if(is_dir(' . var_export( $logs_dir, true ) . ')){$f=scandir(' . var_export( $logs_dir, true ) . ');foreach($f as $n){if($n!="."&&$n!=".."){echo $n." ".filesize(' . var_export( $logs_dir, true ) . '.$n)."\n";}}}else{echo "directory not found";}';
$ls_out  = trim( shell_exec( 'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $ls_php ) . ' --skip-wordpress --quiet 2>/dev/null' ) );
log_message( $ls_out ?: '(empty)' );

foreach ( array( 'junit.xml', 'env.json', 'testdox.txt' ) as $result_file ) {
	log_message( 'Fetching ' . $result_file . ' ...' );
	// Gzip before base64 so large files (junit.xml can be 5MB+) don't overwhelm the terminus connection.
	$read_php = 'if(file_exists(' . var_export( $logs_dir . $result_file, true ) . ')){echo base64_encode(gzencode(file_get_contents(' . var_export( $logs_dir . $result_file, true ) . ')));}else{echo "";}';
	$encoded  = trim( shell_exec( 'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $read_php ) . ' --skip-wordpress --quiet 2>/dev/null' ) );
	if ( ! empty( $encoded ) ) {
		$decoded = gzdecode( base64_decode( $encoded ) );
		file_put_contents( $logs_local . $result_file, $decoded );
		log_message( 'Retrieved ' . $result_file . ' (' . strlen( $decoded ) . ' bytes)' );
	} else {
		log_message( 'Warning: ' . $result_file . ' not found on Pantheon.' );
	}
}

/**
 * Process and upload results.
 */
log_message( 'Processing junit.xml' );

if ( ! file_exists( $logs_local . 'junit.xml' ) ) {
	error_message( 'junit.xml not found — test run did not complete (probable PHP crash). This is a hard failure.' );
}

$xml     = file_get_contents( $logs_local . 'junit.xml' );
$results = process_junit_xml( $xml );

$env = '';
if ( file_exists( $logs_local . 'env.json' ) ) {
	$env = file_get_contents( $logs_local . 'env.json' );
	log_message( 'env.json loaded' );
}

/**
 * Write a GHA step summary with key run info.
 */
$summary_file = getenv( 'GITHUB_STEP_SUMMARY' );
if ( $summary_file ) {
	$env_data = $env ? json_decode( $env, true ) : array();
	$summary  = "## WordPress PHPUnit Test Results\n\n";
	$summary .= '| | |' . "\n" . '|---|---|' . "\n";
	$summary .= '| **Site** | ' . $PANTHEON_SITE_NAME . '.' . $PANTHEON_SITE_ENV . " |\n";
	$summary .= '| **Revision** | ' . ( $rev ?: 'unknown' ) . " |\n";
	$summary .= '| **Commit** | ' . ( $message ?: 'unknown' ) . " |\n";
	$summary .= '| **PHP** | ' . ( $env_data['php_version'] ?? 'unknown' ) . " |\n";
	$summary .= '| **MySQL** | ' . ( $env_data['mysql_version'] ?? 'unknown' ) . " |\n";
	file_put_contents( $summary_file, $summary, FILE_APPEND );
}

log_message( 'Uploading results' );

if ( ! empty( $runner_vars['WPT_REPORT_API_KEY'] ) ) {

	list( $http_status, $response_body ) = upload_results( $results, $rev, $message, $env, $runner_vars['WPT_REPORT_API_KEY'] );
	$response = json_decode( $response_body, true );

	if ( 20 == substr( $http_status, 0, 2 ) ) {
		$upload_msg  = 'Results successfully uploaded';
		$upload_msg .= isset( $response['link'] ) ? ': ' . $response['link'] : '';
		log_message( $upload_msg );
		if ( $summary_file ) {
			$link_line = isset( $response['link'] ) ? '| **Results** | [View on WordPress.org](' . $response['link'] . ") |\n" : '';
			file_put_contents( $summary_file, $link_line, FILE_APPEND );
		}
	} else {
		$err  = 'Error uploading results';
		$err .= isset( $response['message'] ) ? ': ' . $response['message'] : '';
		$err .= ' (HTTP ' . (int) $http_status . ')';
		error_message( $err );
	}

} else {
	log_message( 'No API key — logging results locally only' );
	log_message( '[+] TEST RESULTS' . "\n\n" . $results . "\n\n" );
	log_message( '[+] ENVIRONMENT' . "\n\n" . $env . "\n\n" );
}
