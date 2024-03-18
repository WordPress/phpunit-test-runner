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
 * Retrieves environment variables and sets defaults for test preparation.
 * These variables are used to configure SSH connections, file paths, and
 * executable commands needed for setting up the test environment.
 */
$WPT_SSH_CONNECT    = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_TEST_DIR       = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_PREPARE_DIR    = trim( getenv( 'WPT_PREPARE_DIR' ) );
$WPT_SSH_OPTIONS    = trim( getenv( 'WPT_SSH_OPTIONS' ) );
$WPT_REPORT_API_KEY = trim( getenv( 'WPT_REPORT_API_KEY' ) );
$WPT_PHP_EXECUTABLE_MULTI = trim( getenv( 'WPT_PHP_EXECUTABLE_MULTI' ) ) ? : '';

/**
 * Determines if the debug mode is enabled based on the 'WPT_DEBUG' environment variable.
 * The debug mode can affect error reporting and other debug-related settings.
 */
$WPT_DEBUG_INI = getenv( 'WPT_DEBUG' );
switch( $WPT_DEBUG_INI ) {
	case 0:
	case 'false':
		$WPT_DEBUG = false;
		break;
	case 1:
	case 'true':
	case 'verbose':
		$WPT_DEBUG = 'verbose';
		break;
	default:
		$WPT_DEBUG = false;
		break;
}
unset( $WPT_DEBUG_INI );

/**
 */
$WPT_COMMITS_INI = getenv( 'WPT_COMMITS' );
switch( $WPT_COMMITS_INI ) {
	case 0:
	case 'false':
		$WPT_COMMITS = false;
		break;
	case 1:
	case 'true':
		$WPT_COMMITS = true;
		break;
	default:
		$WPT_COMMITS = false;
		break;
}
unset( $WPT_COMMITS_INI );

/**
 */
$WPT_COMMITS_INI = getenv( 'WPT_COMMITS' );
switch( $WPT_COMMITS_INI ) {
	case 0:
	case 'false':
		$WPT_COMMITS = false;
		break;
	case 1:
	case 'true':
		$WPT_COMMITS = true;
		break;
	default:
		$WPT_COMMITS = false;
		break;
}
unset( $WPT_COMMITS_INI );

/**
 */
$WPT_PHP_EXECUTABLE_MULTI_ARRAY = array();
if ( '' !== $WPT_PHP_EXECUTABLE_MULTI ) {

	$php_multi_versions = explode( ',', $WPT_PHP_EXECUTABLE_MULTI );

	foreach( $php_multi_versions as $php_multi_version ) {

		$php_multi_v = explode( '+', $php_multi_version );

		if( isset( $php_multi_v[0] ) && $php_multi_v[0] && isset( $php_multi_v[1] ) && $php_multi_v[1] ) {
			$WPT_PHP_EXECUTABLE_MULTI_ARRAY[] = array( 'version' => trim( $php_multi_v[0] ), 'bin' => trim( $php_multi_v[1] ) );
		}

		unset( $php_multi_version );
	}
	
	unset( $php_multi_versions );
}

// MULTI-PHP Thing

if( count( $WPT_PHP_EXECUTABLE_MULTI_ARRAY ) ) {

/**
 * Performs a series of operations to set up the test environment. This includes creating a preparation directory,
 * cloning the WordPress development repository, downloading the WordPress importer plugin, and preparing the environment with npm.
 */
// Prepare an array of shell commands to set up the testing environment.

	foreach( $WPT_PHP_EXECUTABLE_MULTI_ARRAY as $php_multi ) {

		$WPT_PREPARE_DIR_MULTI = $WPT_PREPARE_DIR . '-' . crc32( $php_multi['version'] );
		$WPT_TEST_DIR_MULTI = $WPT_TEST_DIR . '-' . crc32( $php_multi['version'] );

		if( $WPT_COMMITS ) {

			$commit_sha = null;
			if( file_exists( __DIR__ . '/commits.json' ) ) {
		
				$c_array = json_decode( file_get_contents( __DIR__ . '/commits.json' ), true );
				
				if( isset( $c_array['testing_commit'] ) && count( $c_array['testing_commit'] ) ) {

					$c_array['executed_commits'][] = $c_array['testing_commit'][0];
					
					unset( $c_array['testing_commit'][0] );

				}

				$c_json = json_encode( $c_array );
		
				file_put_contents( __DIR__ . '/commits.json', $c_json );

			}
		}

		/**
		 * Retrieves the SVN revision number from the git repository log.
		 * Logs a message indicating the start of the SVN revision retrieval process.
		 * Executes a shell command that accesses the git directory specified by the
		 * WPT_PREPARE_DIR environment variable, retrieves the latest commit message,
		 * and extracts the SVN revision number using a combination of grep and cut commands.
		 */
		log_message('Getting SVN Revision');
		$rev = exec('git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . '/.git log -1 --pretty=%B | grep "git-svn-id:" | cut -d " " -f 2 | cut -d "@" -f 2');

		/**
		 * Retrieves the latest SVN commit message from the git repository log.
		 * Logs a message to indicate the retrieval of the SVN commit message. Executes a shell command
		 * that accesses the git directory specified by the WPT_PREPARE_DIR environment variable,
		 * fetches the latest commit message, and trims any whitespace from the message.
		 */
		log_message('Getting SVN message');
		$message = trim( exec('git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . '/.git log -1 --pretty=%B | head -1') );

		/**
		 * Prepares the file path for copying the junit.xml results.
		 * Logs a message indicating the start of the operation to copy junit.xml results.
		 * Constructs the file path to the junit.xml file(s) located in the test directory,
		 * making use of the WPT_TEST_DIR environment variable. The path is sanitized to be
		 * safely used in shell commands.
		 */
		log_message('Copying junit.xml results');
		$junit_location = escapeshellarg( $WPT_TEST_DIR_MULTI ) . '/tests/phpunit/build/logs/*';

		/**
		 * Modifies the junit.xml results file path for a remote location if an SSH connection is available.
		 * If the WPT_SSH_CONNECT environment variable is not empty, indicating that an SSH connection
		 * is configured, this snippet adapts the junit_location variable to include the necessary SSH
		 * command and options for accessing the remote file system. It concatenates SSH options with the
		 * remote path to ensure that the junit.xml results can be accessed or copied over SSH.
		 */
		if ( ! empty( $WPT_SSH_CONNECT ) ) {
			$junit_location = '-e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $junit_location );
		}

		/**
		 * Sets the options for the rsync command based on the debug mode.
		 * Initializes the rsync options with the recursive flag. If the debug mode is set to 'verbose',
		 * appends the 'v' flag to the rsync options to enable verbose output during the rsync operation,
		 * providing more detailed information about the file transfer process.
		 */
		$rsync_options = '-r';

		if ( 'verbose' === $WPT_DEBUG ) {
			$rsync_options = $rsync_options . 'v';
		}

		/**
		 * Constructs the rsync command for executing the synchronization of junit.xml files.
		 * Concatenates the rsync command with the previously defined options and the source and
		 * destination paths. The destination path is sanitized for shell execution. This command is
		 * then passed to the `perform_operations` function, which executes the command to synchronize
		 * the junit.xml files from the source to the destination directory.
		 */
		$junit_exec = 'rsync ' . $rsync_options . ' ' . $junit_location . ' ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI );
		perform_operations( array(
			$junit_exec,
		) );

		/**
		 * Processes and uploads the junit.xml file.
		 * First, a log message is recorded to indicate the start of processing the junit.xml file.
		 * Then, the contents of the junit.xml file are read from the prepared directory into a string.
		 * This XML string is then passed to a function that processes the XML data, presumably to prepare
		 * it for upload or to extract relevant test run information.
		 */
		log_message( 'Processing and uploading junit.xml' );
		$xml = file_get_contents( $WPT_PREPARE_DIR_MULTI . '/junit.xml' );
		$results = process_junit_xml( $xml );

		/**
		 * Retrieves environment details from a JSON file or generates them if not available.
		 * Initializes the environment details string. If an 'env.json' file exists in the prepared
		 * directory, its contents are read into the environment details string. If the file doesn't
		 * exist but the prepared directory is the same as the test directory, the environment details
		 * are generated by calling a function that retrieves these details, then encoded into JSON format.
		 */
		$env = '';
		if ( file_exists( $WPT_PREPARE_DIR_MULTI . '/env.json' ) ) {
			$env = file_get_contents( $WPT_PREPARE_DIR_MULTI . '/env.json' );
		} elseif ( $WPT_PREPARE_DIR_MULTI === $WPT_TEST_DIR_MULTI ) {
			$env = json_encode( get_env_details(), JSON_PRETTY_PRINT );
		}

		/**
		 * Attempts to upload test results if an API key is available, otherwise logs the results locally.
		 * Checks if an API key for reporting is present. If so, it attempts to upload the test results
		 * using the `upload_results` function and processes the HTTP response. A success message is logged
		 * if the upload is successful, indicated by a 20x HTTP status code. If the upload fails, an error
		 * message is logged along with the HTTP status. If no API key is provided, it logs the test results
		 * and environment details locally.
		 */
		if( ! empty( $WPT_REPORT_API_KEY ) ) {

			// Upload the results and capture the HTTP status and response body
			list( $http_status, $response_body ) = upload_results( $results, $rev, $message, $env, $WPT_REPORT_API_KEY );

			// Decode the JSON response body
			$response = json_decode( $response_body, true );
			if ( 20 == substr( $http_status, 0, 2 ) ) {

				// Construct and log a success message with a link if provided in the response
				$message = 'Results successfully uploaded';
				$message .= isset( $response['link'] ) ? ': ' . $response['link'] : '';
				log_message( $message );

			} else {

				// Construct and log an error message with additional details if provided in the response
				$message = 'Error uploading results';
				$message .= isset( $response['message'] ) ? ': ' . $response['message'] : '';
				$message .= ' (HTTP status ' . (int) $http_status . ')';
				error_message( $message );

			}

		} else {

			// Log the test results and environment details locally if no API key is provided
			log_message( '[+] TEST RESULTS' . "\n\n" . $results. "\n\n" );
			log_message( '[+] ENVIRONMENT' . "\n\n" . $env . "\n\n" );

		}

	}
	
} else {

	if( $WPT_COMMITS ) {

		$commit_sha = null;
		if( file_exists( __DIR__ . '/commits.json' ) ) {
	
			$c_array = json_decode( file_get_contents( __DIR__ . '/commits.json' ), true );
			
			if( isset( $c_array['testing_commit'] ) && count( $c_array['testing_commit'] ) ) {

				$c_array['executed_commits'][] = $c_array['testing_commit'][0];
				
				unset( $c_array['testing_commit'][0] );

			}

			$c_json = json_encode( $c_array );
	
			file_put_contents( __DIR__ . '/commits.json', $c_json );

		}
	}

	/**
	 * Retrieves the SVN revision number from the git repository log.
	 * Logs a message indicating the start of the SVN revision retrieval process.
	 * Executes a shell command that accesses the git directory specified by the
	 * WPT_PREPARE_DIR environment variable, retrieves the latest commit message,
	 * and extracts the SVN revision number using a combination of grep and cut commands.
	 */
	log_message('Getting SVN Revision');
	$rev = exec('git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR ) . '/.git log -1 --pretty=%B | grep "git-svn-id:" | cut -d " " -f 2 | cut -d "@" -f 2');

	/**
	 * Retrieves the latest SVN commit message from the git repository log.
	 * Logs a message to indicate the retrieval of the SVN commit message. Executes a shell command
	 * that accesses the git directory specified by the WPT_PREPARE_DIR environment variable,
	 * fetches the latest commit message, and trims any whitespace from the message.
	 */
	log_message('Getting SVN message');
	$message = trim( exec('git --git-dir=' . escapeshellarg( $WPT_PREPARE_DIR ) . '/.git log -1 --pretty=%B | head -1') );

	/**
	 * Prepares the file path for copying the junit.xml results.
	 * Logs a message indicating the start of the operation to copy junit.xml results.
	 * Constructs the file path to the junit.xml file(s) located in the test directory,
	 * making use of the WPT_TEST_DIR environment variable. The path is sanitized to be
	 * safely used in shell commands.
	 */
	log_message('Copying junit.xml results');
	$junit_location = escapeshellarg( $WPT_TEST_DIR ) . '/tests/phpunit/build/logs/*';

	/**
	 * Modifies the junit.xml results file path for a remote location if an SSH connection is available.
	 * If the WPT_SSH_CONNECT environment variable is not empty, indicating that an SSH connection
	 * is configured, this snippet adapts the junit_location variable to include the necessary SSH
	 * command and options for accessing the remote file system. It concatenates SSH options with the
	 * remote path to ensure that the junit.xml results can be accessed or copied over SSH.
	 */
	if ( ! empty( $WPT_SSH_CONNECT ) ) {
		$junit_location = '-e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $junit_location );
	}

	/**
	 * Sets the options for the rsync command based on the debug mode.
	 * Initializes the rsync options with the recursive flag. If the debug mode is set to 'verbose',
	 * appends the 'v' flag to the rsync options to enable verbose output during the rsync operation,
	 * providing more detailed information about the file transfer process.
	 */
	$rsync_options = '-r';

	if ( 'verbose' === $WPT_DEBUG ) {
		$rsync_options = $rsync_options . 'v';
	}

	/**
	 * Constructs the rsync command for executing the synchronization of junit.xml files.
	 * Concatenates the rsync command with the previously defined options and the source and
	 * destination paths. The destination path is sanitized for shell execution. This command is
	 * then passed to the `perform_operations` function, which executes the command to synchronize
	 * the junit.xml files from the source to the destination directory.
	 */
	$junit_exec = 'rsync ' . $rsync_options . ' ' . $junit_location . ' ' . escapeshellarg( $WPT_PREPARE_DIR );
	perform_operations( array(
		$junit_exec,
	) );

	/**
	 * Processes and uploads the junit.xml file.
	 * First, a log message is recorded to indicate the start of processing the junit.xml file.
	 * Then, the contents of the junit.xml file are read from the prepared directory into a string.
	 * This XML string is then passed to a function that processes the XML data, presumably to prepare
	 * it for upload or to extract relevant test run information.
	 */
	log_message( 'Processing and uploading junit.xml' );
	$xml = file_get_contents( $WPT_PREPARE_DIR . '/junit.xml' );
	$results = process_junit_xml( $xml );

	/**
	 * Retrieves environment details from a JSON file or generates them if not available.
	 * Initializes the environment details string. If an 'env.json' file exists in the prepared
	 * directory, its contents are read into the environment details string. If the file doesn't
	 * exist but the prepared directory is the same as the test directory, the environment details
	 * are generated by calling a function that retrieves these details, then encoded into JSON format.
	 */
	$env = '';
	if ( file_exists( $WPT_PREPARE_DIR . '/env.json' ) ) {
		$env = file_get_contents( $WPT_PREPARE_DIR . '/env.json' );
	} elseif ( $WPT_PREPARE_DIR === $WPT_TEST_DIR ) {
		$env = json_encode( get_env_details(), JSON_PRETTY_PRINT );
	}

	/**
	 * Attempts to upload test results if an API key is available, otherwise logs the results locally.
	 * Checks if an API key for reporting is present. If so, it attempts to upload the test results
	 * using the `upload_results` function and processes the HTTP response. A success message is logged
	 * if the upload is successful, indicated by a 20x HTTP status code. If the upload fails, an error
	 * message is logged along with the HTTP status. If no API key is provided, it logs the test results
	 * and environment details locally.
	 */
	if( ! empty( $WPT_REPORT_API_KEY ) ) {

		// Upload the results and capture the HTTP status and response body
		list( $http_status, $response_body ) = upload_results( $results, $rev, $message, $env, $WPT_REPORT_API_KEY );

		// Decode the JSON response body
		$response = json_decode( $response_body, true );
		if ( 20 == substr( $http_status, 0, 2 ) ) {

			// Construct and log a success message with a link if provided in the response
			$message = 'Results successfully uploaded';
			$message .= isset( $response['link'] ) ? ': ' . $response['link'] : '';
			log_message( $message );

		} else {

			// Construct and log an error message with additional details if provided in the response
			$message = 'Error uploading results';
			$message .= isset( $response['message'] ) ? ': ' . $response['message'] : '';
			$message .= ' (HTTP status ' . (int) $http_status . ')';
			error_message( $message );

		}

	} else {

		// Log the test results and environment details locally if no API key is provided
		log_message( '[+] TEST RESULTS' . "\n\n" . $results. "\n\n" );
		log_message( '[+] ENVIRONMENT' . "\n\n" . $env . "\n\n" );

	}
}