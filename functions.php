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
	);
	foreach( $required as $var ) {
		if ( false === getenv( $var ) ) {
			error_message( $var . ' must be set as an environment variable.' );
		}
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
