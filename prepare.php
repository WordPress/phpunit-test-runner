<?php
/**
 * Prepares the environment for WordPress unit tests.
 *
 * In Pantheon mode: clones wordpress-develop directly on the Pantheon container
 * (avoiding rsync and SSH key requirements), generates wp-tests-config.php locally
 * using Pantheon's internal DB credentials, then uploads the config and runs
 * Composer on Pantheon via Terminus.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 * @package WordPress
 */
require __DIR__ . '/functions.php';

check_required_env();

$runner_vars = setup_runner_env_vars();

$PANTHEON_SITE_NAME = $runner_vars['PANTHEON_SITE_NAME'];
$PANTHEON_SITE_ENV  = $runner_vars['PANTHEON_SITE_ENV'];
$site_env           = escapeshellarg( $PANTHEON_SITE_NAME . '.' . $PANTHEON_SITE_ENV );
$test_dir           = $runner_vars['WPT_TEST_DIR'];

/**
 * Set up the SSH private key so Terminus can authenticate for remote:wp and remote:composer.
 */
$WPT_SSH_PRIVATE_KEY_BASE64 = trim( getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' ) );
if ( ! empty( $WPT_SSH_PRIVATE_KEY_BASE64 ) ) {
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );
	if ( ! is_dir( getenv( 'HOME' ) . '/.ssh' ) ) {
		mkdir( getenv( 'HOME' ) . '/.ssh', 0777, true );
	}
	file_put_contents( getenv( 'HOME' ) . '/.ssh/id_rsa', base64_decode( $WPT_SSH_PRIVATE_KEY_BASE64 ) );
	perform_operations( array(
		'chmod 600 ~/.ssh/id_rsa',
		'echo "Host *.drush.in" >> ~/.ssh/config',
		'echo "  HostKeyAlgorithms +ssh-rsa" >> ~/.ssh/config',
		'echo "  PubkeyAcceptedKeyTypes +ssh-rsa" >> ~/.ssh/config',
		'echo "StrictHostKeyChecking no" >> ~/.ssh/config',
	) );
}

/**
 * Fetch Pantheon's internal DB credentials and PHP version in one terminus call.
 * Using internal credentials means tests connect to localhost — no latency.
 */
log_message( 'Fetching Pantheon environment DB credentials and PHP version' );
$info_php = 'echo json_encode(["host"=>getenv("DB_HOST"),"port"=>getenv("DB_PORT"),"name"=>getenv("DB_NAME"),"user"=>getenv("DB_USER"),"pass"=>getenv("DB_PASSWORD"),"php"=>PHP_VERSION]);';
$info_json = trim( shell_exec( 'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $info_php ) . ' --skip-wordpress --quiet 2>/dev/null' ) );
$pantheon  = json_decode( $info_json, true );

if ( empty( $pantheon ) || empty( $pantheon['host'] ) ) {
	error_message( 'Could not retrieve Pantheon environment info. Check Terminus authentication and site/env names.' );
}

$env_php_version  = $pantheon['php'];
$pantheon_db_host = $pantheon['host'] . ':' . $pantheon['port'];
$php_bin          = 'php' . implode( '.', array_slice( explode( '.', $env_php_version ), 0, 2 ) );

log_message( 'Pantheon PHP: ' . $env_php_version . ' (binary: ' . $php_bin . ')' );
log_message( 'Pantheon DB host: ' . $pantheon_db_host );

if ( version_compare( $env_php_version, '7.2', '<' ) ) {
	error_message( 'The test runner is not compatible with PHP < 7.2.' );
}

/**
 * Clone wordpress-develop directly on Pantheon's container.
 * Pantheon has git and outbound HTTPS, so this avoids any rsync/SSH-key dependency.
 * npm build is intentionally skipped — PHP unit tests do not require compiled JS/CSS.
 */
log_message( 'Cloning wordpress-develop on Pantheon' );
$clone_cmd = 'rm -rf ' . escapeshellarg( $test_dir ) . ' && git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $test_dir ) . ' 2>&1';
perform_operations( array(
	'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( 'passthru(' . var_export( $clone_cmd, true ) . ');' ) . ' --skip-wordpress',
) );

/**
 * Generate wp-tests-config.php locally using Pantheon's internal DB credentials,
 * then upload it to Pantheon by base64-encoding it through terminus eval.
 * This avoids any file transfer over SSH/rsync.
 */
log_message( 'Generating wp-tests-config.php' );
$sample_php = 'echo base64_encode(file_get_contents(' . var_export( $test_dir . '/wp-tests-config-sample.php', true ) . '));';
$sample_b64 = trim( shell_exec( 'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $sample_php ) . ' --skip-wordpress --quiet 2>/dev/null' ) );

if ( empty( $sample_b64 ) ) {
	error_message( 'Could not read wp-tests-config-sample.php from Pantheon. Did the git clone succeed?' );
}

$contents = base64_decode( $sample_b64 );

$system_logger = <<<EOT
// Create the log directory to store test results
if ( ! is_dir(  __DIR__ . '/tests/phpunit/build/logs/' ) ) {
	mkdir( __DIR__ . '/tests/phpunit/build/logs/', 0777, true );
}
// Log environment details that are useful to have reported.
\$gd_info = array();
if( extension_loaded( 'gd' ) ) {
	\$gd_info = gd_info();
}
\$imagick_info = array();
if( extension_loaded( 'imagick' ) ) {
	\$imagick_info = Imagick::queryFormats();
}
\$env = array(
	'php_version'    => phpversion(),
	'php_modules'    => array(),
	'gd_info'        => \$gd_info,
	'imagick_info'   => \$imagick_info,
	'mysql_version'  => trim( shell_exec( 'mysql --version' ) ),
	'system_utils'   => array(),
	'os_name'        => trim( shell_exec( 'uname -s' ) ),
	'os_version'     => trim( shell_exec( 'uname -r' ) ),
);
\$php_modules = array(
	'bcmath',
	'ctype',
	'curl',
	'date',
	'dom',
	'exif',
	'fileinfo',
	'filter',
	'ftp',
	'gd',
	'gettext',
	'gmagick',
	'hash',
	'iconv',
	'imagick',
	'imap',
	'intl',
	'json',
	'libsodium',
	'libxml',
	'mbstring',
	'mcrypt',
	'mod_xml',
	'mysqli',
	'mysqlnd',
	'openssl',
	'pcre',
	'pdo_mysql',
	'soap',
	'sockets',
	'sodium',
	'xml',
	'xmlreader',
	'zip',
	'zlib',
);
foreach( \$php_modules as \$php_module ) {
	\$env['php_modules'][ \$php_module ] = phpversion( \$php_module );
}
function curl_selected_bits(\$k) { return in_array(\$k, array('version', 'ssl_version', 'libz_version')); }
\$curl_bits = curl_version();
\$env['system_utils']['curl'] = implode(' ',array_values(array_filter(\$curl_bits, 'curl_selected_bits',ARRAY_FILTER_USE_KEY) ));
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
$system_logger         = $logger_replace_string . $system_logger;

$search_replace = array(
	'wptests_'                              => trim( getenv( 'WPT_TABLE_PREFIX' ) ) ?: 'wptests_',
	'youremptytestdbnamehere'               => $pantheon['name'],
	'yourusernamehere'                      => $pantheon['user'],
	'yourpasswordhere'                      => $pantheon['pass'],
	'localhost'                             => $pantheon_db_host,
	'define( \'WP_PHP_BINARY\', \'php\' );' => 'define( \'WP_PHP_BINARY\', \'' . $php_bin . '\' );',
	$logger_replace_string                  => $system_logger,
);

$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );

// Upload the generated config to Pantheon via base64 through terminus eval.
$config_b64  = base64_encode( $contents );
$write_php   = 'file_put_contents(' . var_export( $test_dir . '/wp-tests-config.php', true ) . ', base64_decode(' . var_export( $config_b64, true ) . ')); echo "Config written.\n";';
perform_operations( array(
	'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $write_php ) . ' --skip-wordpress',
) );

/**
 * Run Composer on Pantheon to install PHPUnit and its dependencies.
 */
log_message( 'Running Composer on Pantheon' );
perform_operations( array(
	'terminus remote:composer ' . $site_env . ' -- config platform.php ' . escapeshellarg( $env_php_version ) . ' --working-dir=' . escapeshellarg( $test_dir ),
	'terminus remote:composer ' . $site_env . ' -- update --working-dir=' . escapeshellarg( $test_dir ),
) );

log_message( 'Success: Prepared environment.' );
