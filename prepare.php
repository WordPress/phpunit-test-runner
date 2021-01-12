<?php

/**
 * Prepares the environment for the test run.
 */

require __DIR__ . '/functions.php';

// Check required environment variables.
check_required_env();

// Bring some environment variables into scope.
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );
$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );
$WPT_PHP_EXECUTABLE = getenv( 'WPT_PHP_EXECUTABLE' ) ? : 'php';
$WPT_DEBUG = getenv( 'WPT_DEBUG' );

// Set the ssh private key if it's set.
$WPT_SSH_PRIVATE_KEY_BASE64 = getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' );
if ( ! empty( $WPT_SSH_PRIVATE_KEY_BASE64 ) ) {
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );
	file_put_contents( getenv( 'HOME' ) . '/.ssh/id_rsa', base64_decode( $WPT_SSH_PRIVATE_KEY_BASE64 ) );
	perform_operations( array(
		'chmod 600 ~/.ssh/id_rsa',
		'ssh -q ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' wp cli info',
	) );
}

// Create the preparation directory and fetch corresponding files
perform_operations( array(
	'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR ),
	'git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR ),
	'wget -O ' .  escapeshellarg( $WPT_PREPARE_DIR . '/phpunit.phar' ) . ' https://phar.phpunit.de/phpunit-5.7.phar',
	'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/wordpress-importer.zip' ) . ' https://downloads.wordpress.org/plugin/wordpress-importer.zip',
	'cd ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/' ) . '; unzip wordpress-importer.zip; rm wordpress-importer.zip',
	'cd ' . escapeshellarg( $WPT_PREPARE_DIR ) . '; npm install && npm run build',
) );

// Replace variables in the wp-config.php file.
log_message( 'Replacing variables in wp-tests-config.php' );
$contents = file_get_contents( $WPT_PREPARE_DIR . '/wp-tests-config-sample.php' );
// Log system information to same directory as test run log.
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
	'mysql_version'  => trim( shell_exec( 'mysql --version' ) ),
	'os_name'        => trim( shell_exec( 'uname -s' ) ),
	'os_version'     => trim( shell_exec( 'uname -r' ) ),
);
\$php_modules = array(
	'bcmath',
	'curl',
	'filter',
	'gd',
	'libsodium',
	'mcrypt',
	'mod_xml',
	'mysqli',
	'imagick',
	'pcre',
	'xml',
	'xmlreader',
	'zlib',
);
foreach( \$php_modules as \$php_module ) {
	\$env['php_modules'][ \$php_module ] = phpversion( \$php_module );
}
\$curl_bits = explode( PHP_EOL, str_replace( 'curl ', '', shell_exec( 'curl --version' ) ) );
\$curl = array_shift( \$curl_bits );
\$env['system_utils']['curl'] = trim( \$curl );
\$env['system_utils']['ghostscript'] = trim( shell_exec( 'gs --version' ) );
\$ret = shell_exec( 'convert --version' );
preg_match( '#Version: ImageMagick ([^\s]+)#', \$ret, \$matches );
\$env['system_utils']['imagemagick'] = isset( \$matches[1] ) ? \$matches[1] : false;
\$env['system_utils']['openssl'] = str_replace( 'OpenSSL ', '', trim( shell_exec( 'openssl version' ) ) );
file_put_contents( __DIR__ . '/tests/phpunit/build/logs/env.json', json_encode( \$env, JSON_PRETTY_PRINT ) );
if ( 'cli' === php_sapi_name() && defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	echo PHP_EOL;
	echo 'PHP version: ' . phpversion() . ' (' . realpath( \$_SERVER['_'] ) . ')' . PHP_EOL;
	echo PHP_EOL;
}
EOT;
$logger_replace_string = '// ** MySQL settings ** //' . PHP_EOL;
$system_logger = $logger_replace_string . $system_logger;
$php_binary_string = 'define( \'WP_PHP_BINARY\', \''. $WPT_PHP_EXECUTABLE . '\' );';
$search_replace = array(
	'wptests_'                              => getenv( 'WPT_TABLE_PREFIX' ) ? : 'wptests_',
	'youremptytestdbnamehere'               => getenv( 'WPT_DB_NAME' ),
	'yourusernamehere'                      => getenv( 'WPT_DB_USER' ),
	'yourpasswordhere'                      => getenv( 'WPT_DB_PASSWORD' ),
	'localhost'                             => getenv( 'WPT_DB_HOST' ),
	'define( \'WP_PHP_BINARY\', \'php\' );' => $php_binary_string,
	$logger_replace_string                  => $system_logger,
);
$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );
file_put_contents( $WPT_PREPARE_DIR . '/wp-tests-config.php', $contents );

// Deliver all files to test environment.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$rsync_options = '-r';

	if ( 'verbose' === $WPT_DEBUG ) {
		$rsync_options = $rsync_options . 'v';
	}

	perform_operations( array(
		'rsync ' . $rsync_options . ' --exclude=".git/" --exclude="node_modules/" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( trailingslashit( $WPT_PREPARE_DIR )  ) . ' ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
	) );
}

log_message( 'Success: Prepared environment.' );
