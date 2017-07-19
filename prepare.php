<?php

/**
 * Prepares the environment for the test run.
 */

require __DIR__ . '/functions.php';

// Check required environment variables
check_required_env();

// Bring some environment variables into scope
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );
$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );

// Set the ssh private key if it's set
$WPT_SSH_PRIVATE_KEY_BASE64 = getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' );
if ( false !== $WPT_SSH_PRIVATE_KEY_BASE64 ) {
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );
	file_put_contents( getenv( 'HOME' ) . '/.ssh/id_rsa', base64_decode( $WPT_SSH_PRIVATE_KEY_BASE64 ) );
	perform_operations( array(
		'chmod 600 ~/.ssh/id_rsa',
		'ssh -q ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' wp cli info',
	) );
}

// Create the prepation directory and fetch corresponding files
perform_operations( array(
	'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR ),
	'git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR ),
	'wget -O ' .  escapeshellarg( $WPT_PREPARE_DIR . '/phpunit.phar' ) . ' https://phar.phpunit.de/phpunit-5.7.phar',
	'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/wordpress-importer.zip' ) . ' https://downloads.wordpress.org/plugin/wordpress-importer.zip',
	'cd ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/' ) . '; unzip wordpress-importer.zip; rm wordpress-importer.zip',
) );

// Replace variables in the wp-config.php file
log_message( 'Replacing variables in wp-tests-config.php' );
$contents = file_get_contents( $WPT_PREPARE_DIR . '/wp-tests-config-sample.php' );
// Log system information to same directory as test run log
$system_logger = <<<EOT
// Create the log directory to store test results
if ( ! is_dir(  __DIR__ . '/tests/phpunit/build/logs/' ) ) {
	mkdir( __DIR__ . '/tests/phpunit/build/logs/', 0777, true );
}
// Log environment details that are useful to have reported.
\$env = array(
	'php_version'    => phpversion(),
	'php_modules'    => array(),
	'system_utils'   => array(),
);
\$php_modules = array(
	'imagick',
	'filter',
	'xml',
	'pcre',
	'mod_xml',
	'bcmath',
);
foreach( \$php_modules as \$php_module ) {
	\$env['php_modules'][ \$php_module ] = phpversion( \$php_module );
}
\$ret = shell_exec( 'convert --version' );
preg_match( '#Version: ImageMagick ([^\s]+)#', \$ret, \$matches );
\$env['system_utils']['imagemagick'] = isset( \$matches[1] ) ? \$matches[1] : false;
\$env['system_utils']['ghostscript'] = shell_exec( 'gs --version' );
file_put_contents( __DIR__ . '/tests/phpunit/build/logs/env.json', json_encode( \$env, JSON_PRETTY_PRINT ) );
EOT;
$logger_replace_string = '// wordpress/wp-config.php will be ignored.' . PHP_EOL;
$system_logger = $logger_replace_string . $system_logger;
$search_replace = array(
	'wptests_'                => getenv( 'WPT_TABLE_PREFIX' ) ? : 'wptests_',
	'youremptytestdbnamehere' => getenv( 'WPT_DB_NAME' ),
	'yourusernamehere'        => getenv( 'WPT_DB_USER' ),
	'yourpasswordhere'        => getenv( 'WPT_DB_PASSWORD' ),
	'localhost'               => getenv( 'WPT_DB_HOST' ),
	$logger_replace_string    => $system_logger,
);
$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );
file_put_contents( $WPT_PREPARE_DIR . '/wp-tests-config.php', $contents );

// Deliver all files to test environment
if ( false !== $WPT_SSH_CONNECT ) {
	perform_operations( array(
		'rsync -rv --exclude=".git/" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( trailingslashit( $WPT_PREPARE_DIR )  ) . ' ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
	) );
}

log_message( 'Success: Prepared environment.' );
