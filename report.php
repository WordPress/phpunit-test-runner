<?php
/**
 * This script is responsible for reporting the results of the PHPUnit test runs to WordPress.org.
 * It gathers necessary information such as the SVN revision, test run messages, and the junit.xml
 * file containing the results. It then uploads these details using the WordPress.org API if an API
 * key is provided, or logs the results for later use.
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
check_required_env( false );

/**
 * Ensure that optional environment variables are present with default values.
 */
$runner_vars = setup_runner_env_vars();

skip_if_no_prepared_environment( $runner_vars );

foreach ( $runner_vars['WPT_PHP_EXECUTABLES'] as $php ) {
	$paths = get_php_run_paths( $runner_vars, $php );

	log_message( 'Reporting results for PHP ' . $php['version'] . ' (' . $php['bin'] . ')' );

	log_message( 'Getting SVN Revision' );
	$rev = exec( 'git --git-dir=' . escapeshellarg( $paths['prepare_dir'] ) . '/.git log -1 --pretty=%B | grep "git-svn-id:" | cut -d " " -f 2 | cut -d "@" -f 2' );

	log_message( 'Getting SVN message' );
	$message = trim( exec( 'git --git-dir=' . escapeshellarg( $paths['prepare_dir'] ) . '/.git log -1 --pretty=%B | head -1' ) );

	log_message( 'Copying junit.xml results' );
	$junit_location = escapeshellarg( $paths['test_dir'] ) . '/tests/phpunit/build/logs/*';

	if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		$junit_location = '-e "ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . '" ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] . ':' . $junit_location );
	}

	$rsync_options = '-r';

	if ( $runner_vars['WPT_DEBUG'] ) {
		$rsync_options = $rsync_options . 'v';
	}

	$junit_exec = 'rsync ' . $rsync_options . ' ' . $junit_location . ' ' . escapeshellarg( $paths['prepare_dir'] );
	perform_operations(
		array(
			$junit_exec,
		)
	);

	log_message( 'Processing and uploading junit.xml' );
	$xml     = file_get_contents( $paths['prepare_dir'] . '/junit.xml' );
	$results = process_junit_xml( $xml );

	$env = '';
	if ( file_exists( $paths['prepare_dir'] . '/env.json' ) ) {
		$env = file_get_contents( $paths['prepare_dir'] . '/env.json' );
	} elseif ( $paths['prepare_dir'] === $paths['test_dir'] ) {
		$env = json_encode( get_env_details(), JSON_PRETTY_PRINT );
	}

	if ( ! empty( $runner_vars['WPT_REPORT_API_KEY'] ) ) {

		list( $http_status, $response_body ) = upload_results( $results, $rev, $message, $env, $runner_vars['WPT_REPORT_API_KEY'] );

		$response = json_decode( $response_body, true );
		if ( 20 == substr( $http_status, 0, 2 ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual

			$message  = 'Results successfully uploaded';
			$message .= isset( $response['link'] ) ? ': ' . $response['link'] : '';
			log_message( $message );

		} else {

			$message  = 'Error uploading results';
			$message .= isset( $response['message'] ) ? ': ' . $response['message'] : '';
			$message .= ' (HTTP status ' . (int) $http_status . ')';
			error_message( $message );

		}
	} else {

		log_message( '[+] TEST RESULTS' . "\n\n" . $results . "\n\n" );
		log_message( '[+] ENVIRONMENT' . "\n\n" . $env . "\n\n" );

	}
}

mark_testing_commit_executed();
