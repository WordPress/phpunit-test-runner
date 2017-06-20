<?php

/**
 * Cleans up the environment after the test run.
 */

require __DIR__ . '/functions.php';

// Check required environment variables
check_required_env();

// Bring some environment variables into scope
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );

// Clean up the preparation directory
// Only forcefully delete the .git directory, to prevent disasters otherwise
perform_operations( array(
	'rm -rf ' . escapeshellarg( $WPT_PREPARE_DIR . '/.git' ),
	'rm -r ' . escapeshellarg( $WPT_PREPARE_DIR ),
) );
