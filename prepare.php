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
	if ( ! is_dir( getenv( 'HOME' ) . '/.ssh' ) ) {
		mkdir( getenv( 'HOME' ) . '/.ssh', 0777, true );
	}
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
	'gmagick',
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
if ( class_exists( 'Imagick' ) ) {
	\$imagick = new Imagick();
	\$version = \$imagick->getVersion();
	preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', \$version['versionString'], \$version );
	\$env['system_utils']['imagemagick'] = \$version[1];
} elseif ( class_exists( 'Gmagick' ) ) {
	\$gmagick = new Gmagick();
	\$version = \$gmagick->getversion();
	preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', \$version['versionString'], \$version );
	\$env['system_utils']['graphicsmagick'] = \$version[1];
}
\$env['system_utils']['openssl'] = str_replace( 'OpenSSL ', '', trim( shell_exec( 'openssl version' ) ) );
file_put_contents( __DIR__ . '/tests/phpunit/build/logs/env.json', json_encode( \$env, JSON_PRETTY_PRINT ) );
if ( 'cli' === php_sapi_name() && defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	echo PHP_EOL;
	echo 'PHP version: ' . phpversion() . ' (' . realpath( \$_SERVER['_'] ) . ')' . PHP_EOL;
	echo PHP_EOL;
}
EOT;
$logger_replace_string = '// ** Database settings ** //' . PHP_EOL;
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

// Now, install PHPUnit based on the test environment's PHP Version
$php_version_cmd = $WPT_PHP_EXECUTABLE . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$php_version_cmd = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $php_version_cmd );
}

$retval = 0;
$env_php_version = exec( $php_version_cmd, $output, $retval );
if ( $retval !== 0 ) {
	error_message( 'Could not retrieve the environment PHP Version.' );
}
log_message( "Environment PHP Version: $env_php_version" );

// If PHP Version is 8.X.X, set PHP Version to 7.4 for compatibility with core PHPUnit tests.
if ( substr( $env_php_version, 0 , 2 ) === '8.' ) {
	log_message( 'Version 8.x.x Found. Downloading PHPUnit for PHP 7.4 instead for compatibility.' );
	$env_php_version = '7.4';
}

if ( version_compare( $env_php_version, '5.6', '<' ) ) {
	error_message( "The test runner is not compatible with PHP < 5.6." );
}

// If PHP version is 5.6-7.0, download PHPUnit 5.7 phar directly.
if ( version_compare( $env_php_version, '7.1', '<' ) ) {
	perform_operations( array(
		'wget -O ' .  escapeshellarg( $WPT_PREPARE_DIR . '/phpunit.phar' ) . ' https://phar.phpunit.de/phpunit-5.7.phar',
	) );

// Otherwise, use Composer to download PHPUnit to get further necessary dependencies.
} else {

	// First, check if composer is available. Download if not.
	$composer_cmd = 'cd ' . escapeshellarg( $WPT_PREPARE_DIR ) . ' && ';

	$retval = 0;
	$composer_path = escapeshellarg( system( 'which composer', $retval ) );
	if ( $retval === 0 ) {
		$composer_cmd .= $composer_path . ' ';
	} else {
		log_message( 'Local Composer not found. Downloading latest stable ...' );

		perform_operations( array(
			'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR . '/composer.phar' ) . ' https://getcomposer.org/composer-stable.phar',
		) );

		$composer_cmd .= 'php composer.phar ';
	}

	// Set Composer PHP environment, then run Composer.
	perform_operations( array(
		$composer_cmd . 'config platform.php ' . escapeshellarg( $env_php_version ),
		$composer_cmd . 'update',
	) );
}

// Deliver all files to test environment.
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	$rsync_options = '-r';

	if ( 'verbose' === $WPT_DEBUG ) {
		$rsync_options = $rsync_options . 'v';
	}

	perform_operations( array(
		'rsync ' . $rsync_options . ' --exclude=".git/" --exclude="node_modules/" --exclude="composer.phar" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( trailingslashit( $WPT_PREPARE_DIR )  ) . ' ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
	) );
}

log_message( 'Success: Prepared environment.' );
