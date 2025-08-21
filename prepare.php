<?php
/**
 * WordPress PHPUnit Test Runner: Prepare script
 *
 * This script is responsible for preparing the environment to run the
 * WordPress Core PHPUnit test suite.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 *
 * @package WordPress
 */
require __DIR__ . '/functions.php';

/*
 * Check for the presence of required environment variables.
 *
 * This function should be defined in functions.php and should throw an
 * exception or exit if any required variables are missing.
 */
check_required_env();

/*
 * Retrieve environment variables falling back to defaults.
 *
 * These variables are used to configure SSH connections, file paths, and
 * executable commands needed for setting up the test environment.
 */
$WPT_PREPARE_DIR    = trim( getenv( 'WPT_PREPARE_DIR' ) );
$WPT_SSH_CONNECT    = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_SSH_OPTIONS    = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR       = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_PHP_EXECUTABLE = trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ? : 'php';
$WPT_CERTIFICATE_VALIDATION = trim( getenv( 'WPT_CERTIFICATE_VALIDATION' ) );

// Configure debug mode based on the WPT_DEBUG environment variable.
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

/*
 * Configure a private SSH key for remote testing.
 *
 * A base64-encoded private SSH key can be provided through the
 * 'WPT_SSH_PRIVATE_KEY_BASE64' environment variable to support executing the
 * test runner on a remote server.
 *
 * When provided, the key is decoded and saved to the user's .ssh directory as
 * an 'id_rsa' key file.
 *
 * @throws Exception If there is an issue creating the .ssh directory or
 *                   writing the key file.
 */
// Set the SSH private key if it's provided in the environment.
$WPT_SSH_PRIVATE_KEY_BASE64 = trim( getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' ) );

// Check if the private key variable is not empty.
if ( ! empty( $WPT_SSH_PRIVATE_KEY_BASE64 ) ) {

	// Log the action of securely extracting the private key.
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );

	// Check if the .ssh directory exists in the home directory, and create it if it does not.
	if ( ! is_dir( getenv( 'HOME' ) . '/.ssh' ) ) {
		// The mkdir function creates the directory with the specified permissions and the recursive flag set to true.
		mkdir( getenv( 'HOME' ) . '/.ssh', 0777, true );
	}

	// Write the decoded private key into the id_rsa file within the .ssh directory.
	file_put_contents( getenv( 'HOME' ) . '/.ssh/id_rsa', base64_decode( $WPT_SSH_PRIVATE_KEY_BASE64 ) );

	// Define the array of operations to perform, depending on the SSH connection availability.
	// When am SSH connection string is not provided, add a local operation to the array.
	// When an SSH connection string is provided, add a remote operation to the array.
	// Execute the operations defined in the operations array.
	if( empty( $WPT_SSH_CONNECT ) ) {
		perform_operations( array(
			'chmod 600 ~/.ssh/id_rsa',
			'wp cli info'
		) );
	} else {
		perform_operations( array(
			'chmod 600 ~/.ssh/id_rsa',
			'ssh -q ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' wp cli info'
		) );
	}

}

/**
 * Don't validate the TLS certificate
 * Useful for local environments
 */
$certificate_validation = '';
if( ! $WPT_CERTIFICATE_VALIDATION ) {
	$certificate_validation .= ' --no-check-certificate';
}

/*
 * Checkout and prepare wordpress-develop for testing.
 *
 * The following actions are performed:
 * - Creates a directory to prepare wordpress-develop.
 * - Clones the WordPress/wordpress-develop repository from GitHub.
 * - Install npm dependencies and run the build script.
 */
// Prepare an array of shell commands to set up the testing environment.
perform_operations( array(

	// Create the preparation directory if it doesn't exist. The '-p' flag creates intermediate directories as required.
	'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR ),

	// Clone the WordPress develop repository from GitHub into the preparation directory.
	// The '--depth=1' flag creates a shallow clone with a history truncated to the last commit.
	'git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR ),

	// Change directory to the preparation directory, install npm dependencies, and build the project.
	'cd ' . escapeshellarg( $WPT_PREPARE_DIR ) . '; npm install && npm run build'

) );

// Log a message indicating the start of the variable replacement process for configuration.
log_message( 'Replacing variables in wp-tests-config.php' );

// Don't validate the TLS certificate. Useful for local environments.
$contents = file_get_contents( $WPT_PREPARE_DIR . '/wp-tests-config-sample.php' );

/*
 * Prepare a script for logging system information.
 *
 * The versions of PHP, PHP modules, database software, and system utilities
 * can impact the results of the test suite. This gathers the relevant details
 * and stores them in a JSON file for later reference.
 *
 * The script performs the following actions:
 * - Confirms the presence of the `tests/phpunit/build/logs/` directory,
 *   creating one when it does not exist.
 * - Collects information about the environment.
 * - The info is written to the /tests/phpunit/build/logs/env.json file.
 *
 * When running from the command line during the WordPress installation
 * process, the PHP version and executable path are also output.
 */
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
	preg_match('/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', \$version['versionString'], \$matches);
	\$env['system_utils']['imagemagick'] = \$matches[1] ?? 'Unknown';
} elseif (class_exists('Gmagick')) {
	\$gmagick = new Gmagick();
	\$version = \$gmagick->getVersion();
	preg_match('/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', \$version['versionString'], \$matches);
	\$env['system_utils']['graphicsmagick'] = \$matches[1] ?? 'Unknown';
}
\$env['system_utils']['openssl'] = str_replace( 'OpenSSL ', '', trim( shell_exec( 'openssl version' ) ) );
//\$mysqli = new mysqli( WPT_DB_HOST, WPT_DB_USER, WPT_DB_PASSWORD, WPT_DB_NAME );
//\$env['mysql_version'] = \$mysqli->query("SELECT VERSION()")->fetch_row()[0];
//\$mysqli->close();
file_put_contents( __DIR__ . '/tests/phpunit/build/logs/env.json', json_encode( \$env, JSON_PRETTY_PRINT ) );
if ( 'cli' === php_sapi_name() && defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	echo PHP_EOL;
	echo 'PHP version: ' . phpversion() . ' (' . realpath( \$_SERVER['_'] ) . ')' . PHP_EOL;
	echo PHP_EOL;
}
EOT;

// Initialize a string that will be used to identify the database settings section in the configuration file.
$logger_replace_string = '// ** Database settings ** //' . PHP_EOL;

// Prepend the logger script to the database settings identifier to ensure it gets included in the wp-tests-config.php file.
$system_logger = $logger_replace_string . $system_logger;

// Define a string that will set the 'WP_PHP_BINARY' constant to the path of the PHP executable.
$php_binary_string = 'define( \'WP_PHP_BINARY\', \''. $WPT_PHP_EXECUTABLE . '\' );';

/*
 * Map configuration file placeholders to environment-specific values.
 *
 * This is used in the subsequent str_replace operation to replace placeholder
 * values in the wp-tests-config-sample.php file with the ones provided.
 */
$search_replace = array(
	'wptests_'                              => trim( getenv( 'WPT_TABLE_PREFIX' ) ) ? : 'wptests_',
	'youremptytestdbnamehere'               => trim( getenv( 'WPT_DB_NAME' ) ),
	'yourusernamehere'                      => trim( getenv( 'WPT_DB_USER' ) ),
	'yourpasswordhere'                      => trim( getenv( 'WPT_DB_PASSWORD' ) ),
	'localhost'                             => trim( getenv( 'WPT_DB_HOST' ) ),
	'define( \'WP_PHP_BINARY\', \'php\' );' => $php_binary_string,
	$logger_replace_string                  => $system_logger,
);

// Replace the placeholders in the wp-tests-config-sample.php file content with actual values.
$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );

// Write the modified content to the wp-tests-config.php file, which will be used by the test suite.
file_put_contents( $WPT_PREPARE_DIR . '/wp-tests-config.php', $contents );

/*
 * Construct a command that generates a PHP version string compatible with
 * PHPUnit version requirements.
 */
$php_version_cmd = $WPT_PHP_EXECUTABLE . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";

/**
 * This command will differ when running on a remote server via SSH.
 */
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	// The PHP version check command is prefixed with the SSH command, including SSH options,
	// and the connection string, ensuring the command is executed on the remote machine.
	$php_version_cmd = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $php_version_cmd );
}

// Initialize return value variable for the exec function call.
$retval = 0;

/*
 * Execute the constructed command to obtain the PHP version of the test
 * environment.
 *
 * The output is stored in $env_php_version and the return value of the
 * command execution is stored in $retval.
 */
$env_php_version = exec( $php_version_cmd, $output, $retval );

// Check if the command execution was successful by inspecting the return value.
if ( $retval !== 0 ) {
	// If the return value is not zero, an error occurred, and a message is logged.
	error_message( 'Could not retrieve the environment PHP Version.' );
}

// Log the obtained PHP version for confirmation and debugging purposes.
log_message( 'Environment PHP Version: ' . $env_php_version );

/*
 * Confirm that the environment meets the minimum PHP version requirement.
 *
 * When the requirements are not met, execution will end with an error message.
 */
if ( version_compare( $env_php_version, '7.2', '<' ) ) {
	// Logs an error message indicating the test runner's incompatibility with PHP versions below 7.2.
	error_message( 'The test runner is not compatible with PHP < 7.2.' );
}


// Check if Composer is installed and available in the PATH.
$composer_cmd = 'cd ' . escapeshellarg( $WPT_PREPARE_DIR ) . ' && ';
$retval = 0;
$composer_path = escapeshellarg( system( 'which composer', $retval ) );

if ( $retval === 0 ) {

	// If Composer is available, prepare the command to use the Composer binary.
	$composer_cmd .= $composer_path . ' ';

} else {

	// If Composer is not available, download the Composer phar file.
	log_message( 'Local Composer not found. Downloading latest stable ...' );

	perform_operations( array(
		'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR . '/composer.phar' ) . ' https://getcomposer.org/composer-stable.phar',
	) );

	// Update the command to use the downloaded Composer phar file.
	$composer_cmd .= 'php composer.phar ';
}

// Set the PHP version for Composer to ensure compatibility and update dependencies.
perform_operations( array(
	$composer_cmd . 'config platform.php ' . escapeshellarg( $env_php_version ),
	$composer_cmd . 'update',
) );

/*
 * Transfer the built WordPress codebase to the remote test environment.
 *
 * When an SSH connection is configured, rsync is used to copy the files
 * required tp rim the WordPress PHPUnit test suite.
 *
 * The -r option for rsync enables recursive copying to handle nested directory
 * structures.
 */
if ( ! empty( $WPT_SSH_CONNECT ) ) {
	// Initialize rsync options with recursive copying.
	$rsync_options = '-r';

	// If debug mode is set to verbose, append 'v' to rsync options for verbose output.
	if ( 'verbose' === $WPT_DEBUG ) {
		$rsync_options = $rsync_options . 'v';
	}

	// Perform the rsync operation with the configured options and exclude patterns.
	// This operation synchronizes the test environment with the prepared files, excluding version control directories
	// and other non-essential files for test execution.
	perform_operations( array(
		'rsync ' . $rsync_options . ' --exclude=".git/" --exclude="node_modules/" --exclude="composer.phar" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( trailingslashit( $WPT_PREPARE_DIR )  ) . ' ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
	) );
}

// Log a success message indicating that the environment has been prepared.
log_message( 'Success: Prepared environment.' );
