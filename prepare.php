<?php
/**
 * This script prepares the environment for WordPress unit tests.
 * It sets up the necessary variables and configurations based on the environment.
 * The script assumes that certain environment variables are set to configure SSH,
 * directories, and executables used in the test preparation process.
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
check_required_env();

/**
 * Ensure that optional environment variables are present with default values.
 */
$runner_vars = setup_runner_env_vars();

/**
 * Sets up the SSH private key for use in the test environment if provided.
 * The private key is expected to be in base64-encoded form in the environment variable 'WPT_SSH_PRIVATE_KEY_BASE64'.
 * It is decoded and saved to the user's .ssh directory as 'id_rsa'.
 * Proper permissions are set on the private key to secure it.
 * If an SSH connection string is provided, it performs a remote operation to ensure the WP CLI is accessible.
 * Otherwise, it performs a local operation to check the WP CLI.
 *
 * @throws Exception If there is an issue creating the .ssh directory or writing the key file.
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
	// If no SSH connection string is provided, add a local operation to the array.
	// If an SSH connection string is provided, add a remote operation to the array.
	// Execute the operations defined in the operations array.
	if( empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		perform_operations( array(
			'chmod 600 ~/.ssh/id_rsa',
			'wp cli info'
		) );
	} else {
		perform_operations( array(
			'chmod 600 ~/.ssh/id_rsa',
			'ssh -q ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' wp cli info'
		) );
	}

}


/**
 * Performs a series of operations to set up the test environment. This includes creating a preparation directory,
 * cloning the WordPress development repository, and preparing the environment with npm.
 */
// Prepare an array of shell commands to set up the testing environment.
perform_operations( array(

	// Create the preparation directory if it doesn't exist. The '-p' flag creates intermediate directories as required.
	'mkdir -p ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ),

	// Clone the WordPress develop repository from GitHub into the preparation directory.
	// The '--depth=1' flag creates a shallow clone with a history truncated to the last commit.
	'git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ),

	// Change directory to the preparation directory, install npm dependencies, and build the project.
	'cd ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ) . '; npm install && npm run build'

) );

// Log a message indicating the start of the variable replacement process for configuration.
log_message( 'Replacing variables in wp-tests-config.php' );

/**
 * Reads the contents of the WordPress test configuration sample file.
 * This file contains template placeholders that need to be replaced with actual values 
 * from environment variables to configure the WordPress test environment.
 */
$contents = file_get_contents( $runner_vars['WPT_PREPARE_DIR'] . '/wp-tests-config-sample.php' );

/**
 * Prepares a script to log system information relevant to the testing environment.
 * The script checks for the existence of the log directory and creates it if it does not exist.
 * It then collects various pieces of system information including PHP version, loaded PHP modules,
 * database server version, operating system details, and versions of key utilities like cURL and OpenSSL.
 * This information is collected in an array and written to a JSON file in the log directory.
 * Additionally, if running from the command line during a WordPress installation process, 
 * it outputs the PHP version and executable path.
 */
$system_logger = <<<'EOT'
// Create the log directory to store test results
if ( ! is_dir(  __DIR__ . '/tests/phpunit/build/logs/' ) ) {
	mkdir( __DIR__ . '/tests/phpunit/build/logs/', 0777, true );
}
// Log environment details that are useful to have reported.
$gd_info = array();
if( extension_loaded( 'gd' ) ) {
	$gd_info = gd_info();
}
$imagick_info = array();
if( extension_loaded( 'imagick' ) ) {
	$imagick_info = Imagick::queryFormats();
}
if ( ! function_exists( 'wpt_runner_parse_db_host' ) ) {
	function wpt_runner_parse_db_host( $host ) {
		$host = (string) $host;
		$socket = null;
		$port = null;
		$is_ipv6 = false;

		if ( '' === $host ) {
			return false;
		}

		$socket_pos = strpos( $host, ':/' );
		if ( false !== $socket_pos ) {
			$socket = substr( $host, $socket_pos + 1 );
			$host = substr( $host, 0, $socket_pos );
		}

		if ( substr_count( $host, ':' ) > 1 ) {
			if ( 1 !== preg_match( '/^(?:\[(?P<host>[0-9a-fA-F:.]+)\](?::(?P<port>[0-9]+))?|(?P<host_unbracketed>[0-9a-fA-F:.]+))$/', $host, $matches ) ) {
				return false;
			}
			$parsed_host = ! empty( $matches['host'] ) ? $matches['host'] : ( isset( $matches['host_unbracketed'] ) ? $matches['host_unbracketed'] : '' );
			$is_ipv6 = true;
		} else {
			if ( 1 !== preg_match( '/^(?P<host>[^:]*)(?::(?P<port>[0-9]+))?$/', $host, $matches ) ) {
				return false;
			}
			$parsed_host = $matches['host'];
		}

		if ( '' === $parsed_host ) {
			return false;
		}

		if ( isset( $matches['port'] ) && '' !== $matches['port'] ) {
			$port = (int) $matches['port'];
		}

		return array(
			'host'    => $parsed_host,
			'port'    => $port,
			'socket'  => $socket,
			'is_ipv6' => $is_ipv6,
		);
	}
}
if ( ! function_exists( 'wpt_runner_get_db_server_version' ) ) {
	function wpt_runner_get_db_server_version( $db_host, $db_user, $db_password, $db_name ) {
		$db_host = trim( (string) $db_host );
		$db_user = trim( (string) $db_user );
		$db_password = (string) $db_password;
		$db_name = trim( (string) $db_name );

		if ( '' === $db_host ) {
			$db_host = 'localhost';
		}

		if ( '' === $db_user || '' === $db_name ) {
			return '';
		}

		if ( ! class_exists( 'mysqli' ) ) {
			return '';
		}

		$required_functions = array(
			'mysqli_close',
			'mysqli_fetch_row',
			'mysqli_free_result',
			'mysqli_init',
			'mysqli_options',
			'mysqli_query',
			'mysqli_real_connect',
		);

		foreach ( $required_functions as $function_name ) {
			if ( ! function_exists( $function_name ) ) {
				return '';
			}
		}

		$parsed_host = wpt_runner_parse_db_host( $db_host );
		if ( false === $parsed_host ) {
			return '';
		}
		$connect_host = $parsed_host['host'];
		// mysqlnd expects IPv6 hosts in brackets, matching WordPress core's connection handling.
		if ( $parsed_host['is_ipv6'] && extension_loaded( 'mysqlnd' ) ) {
			$connect_host = '[' . $connect_host . ']';
		}

		$mysqli = null;
		$mysqli_report_mode = null;

		try {
			if ( class_exists( 'mysqli_driver' ) ) {
				$mysqli_driver = new mysqli_driver();
				$mysqli_report_mode = $mysqli_driver->report_mode;
			}

			if ( function_exists( 'mysqli_report' ) ) {
				mysqli_report( MYSQLI_REPORT_OFF );
			}

			$mysqli = mysqli_init();
			if ( false === $mysqli ) {
				return '';
			}

			if ( defined( 'MYSQLI_OPT_CONNECT_TIMEOUT' ) ) {
				mysqli_options( $mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5 );
			}

			if ( ! @mysqli_real_connect(
				$mysqli,
				$connect_host,
				$db_user,
				$db_password,
				$db_name,
				$parsed_host['port'],
				$parsed_host['socket']
			) ) {
				return '';
			}

			$result = @mysqli_query( $mysqli, 'SELECT VERSION()' );
			if ( ! is_object( $result ) ) {
				return '';
			}

			$row = mysqli_fetch_row( $result );
			mysqli_free_result( $result );

			if ( ! is_array( $row ) || ! isset( $row[0] ) || '' === (string) $row[0] ) {
				return '';
			}

			return (string) $row[0];
		} catch ( Throwable $e ) {
			return '';
		} finally {
			if ( $mysqli instanceof mysqli ) {
				@mysqli_close( $mysqli );
			}
			if ( null !== $mysqli_report_mode && function_exists( 'mysqli_report' ) ) {
				mysqli_report( $mysqli_report_mode );
			}
		}
	}
}
$env = array(
	'php_version'    => phpversion(),
	'php_modules'    => array(),
	'gd_info'        => $gd_info,
	'imagick_info'   => $imagick_info,
	'mysql_version'  => wpt_runner_get_db_server_version( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME ),
	'system_utils'   => array(),
	'os_name'        => trim( shell_exec( 'uname -s' ) ),
	'os_version'     => trim( shell_exec( 'uname -r' ) ),
);
$php_modules = array(
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
foreach( $php_modules as $php_module ) {
	$env['php_modules'][ $php_module ] = phpversion( $php_module );
}
function curl_selected_bits($k) { return in_array($k, array('version', 'ssl_version', 'libz_version')); }
$curl_bits = curl_version();
$env['system_utils']['curl'] = implode(' ',array_values(array_filter($curl_bits, 'curl_selected_bits',ARRAY_FILTER_USE_KEY) ));
if ( class_exists( 'Imagick' ) ) {
	$imagick = new Imagick();
	$version = $imagick->getVersion();
	preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', $version['versionString'], $version );
	$env['system_utils']['imagemagick'] = $version[1];
} elseif ( class_exists( 'Gmagick' ) ) {
	$gmagick = new Gmagick();
	$version = $gmagick->getversion();
	preg_match( '/Magick (\d+\.\d+\.\d+-\d+|\d+\.\d+\.\d+|\d+\.\d+\-\d+|\d+\.\d+)/', $version['versionString'], $version );
	$env['system_utils']['graphicsmagick'] = $version[1];
}
$env['system_utils']['openssl'] = str_replace( 'OpenSSL ', '', trim( shell_exec( 'openssl version' ) ) );
file_put_contents( __DIR__ . '/tests/phpunit/build/logs/env.json', json_encode( $env, JSON_PRETTY_PRINT ) );
if ( 'cli' === php_sapi_name() && defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	echo PHP_EOL;
	echo 'PHP version: ' . phpversion() . ' (' . realpath( $_SERVER['_'] ) . ')' . PHP_EOL;
	echo PHP_EOL;
}
EOT;

// Initialize a string that will be used to identify the post-database-settings section in the configuration file.
$logger_replace_string = 'define( \'DB_COLLATE\', \'\' );' . PHP_EOL;

if ( false === strpos( $contents, $logger_replace_string ) ) {
	error_message( 'Unable to insert the system logger after the database constants in wp-tests-config.php.' );
}

// Append the logger script to the database settings constants to ensure DB_* constants are available.
$system_logger = $logger_replace_string . $system_logger;

// Define a string that will set the 'WP_PHP_BINARY' constant to the path of the PHP executable.
$php_binary_string = 'define( \'WP_PHP_BINARY\', \''. $runner_vars['WPT_PHP_EXECUTABLE'] . '\' );';

/**
 * An associative array mapping configuration file placeholders to environment-specific values.
 * This array is used in the subsequent str_replace operation to replace placeholders
 * in the wp-tests-config-sample.php file with values from the environment or defaults if none are provided.
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
file_put_contents( $runner_vars['WPT_PREPARE_DIR'] . '/wp-tests-config.php', $contents );

/**
 * Determines the PHP version of the test environment to ensure the correct version of PHPUnit is installed.
 * It constructs a command that prints out the PHP version in a format compatible with PHPUnit's version requirements.
 */
$php_version_cmd = $runner_vars['WPT_PHP_EXECUTABLE'] . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";

/**
 * If an SSH connection string is provided, the command to determine the PHP version is modified 
 * to execute remotely over SSH. This is required if the test environment is not the local machine.
 */
if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
	// The PHP version check command is prefixed with the SSH command, including SSH options,
	// and the connection string, ensuring the command is executed on the remote machine.
	$php_version_cmd = 'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $php_version_cmd );
}

// Initialize return value variable for the exec function call.
$retval = 0;

/**
 * Executes the constructed command to obtain the PHP version of the test environment.
 * The output is stored in $env_php_version, and the return value of the command execution is stored in $retval.
 */
$env_php_version = exec( $php_version_cmd, $output, $retval );

// Check if the command execution was successful by inspecting the return value.
if ( $retval !== 0 ) {
	// If the return value is not zero, an error occurred, and a message is logged.
	error_message( 'Could not retrieve the environment PHP Version.' );
}

// Log the obtained PHP version for confirmation and debugging purposes.
log_message( 'Environment PHP Version: ' . $env_php_version );

/**
 * Checks if the detected PHP version is below 7.2.
 * The test runner requires PHP version 7.2 or above, and if the environment's PHP version
 * is lower, it logs an error message and could terminate the script.
 */
if ( version_compare( $env_php_version, '7.2', '<' ) ) {
	// Logs an error message indicating the test runner's incompatibility with PHP versions below 7.2.
	error_message( 'The test runner is not compatible with PHP < 7.2.' );
}

/**
 * Use Composer to manage PHPUnit and its dependencies.
 * This allows for better dependency management and compatibility.
 */

// Check if Composer is installed and available in the PATH.
$composer_cmd = 'cd ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] ) . ' && ';
$retval = 0;
$composer_path = escapeshellarg( system( 'which composer', $retval ) );

if ( $retval === 0 ) {

	// If Composer is available, prepare the command to use the Composer binary.
	$composer_cmd .= $composer_path . ' ';

} else {

	// If Composer is not available, download the Composer phar file.
	log_message( 'Local Composer not found. Downloading latest stable ...' );

	perform_operations( array(
		'wget -O ' . escapeshellarg( $runner_vars['WPT_PREPARE_DIR'] . '/composer.phar' ) . ' https://getcomposer.org/composer-stable.phar',
	) );

	// Update the command to use the downloaded Composer phar file.
	$composer_cmd .= $runner_vars['WPT_PHP_EXECUTABLE'] . ' composer.phar ';
}

// Set the PHP version for Composer to ensure compatibility and update dependencies.
perform_operations( array(
	$composer_cmd . 'config platform.php ' . escapeshellarg( $env_php_version ),
	$composer_cmd . 'update',
) );

/**
 * If an SSH connection is configured, use rsync to transfer the prepared files to the remote test environment.
 * The -r option for rsync enables recursive copying to handle directory structures.
 * Additional rsync options may be included for more verbose output if debugging is enabled.
 */
if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
	// Initialize rsync options with recursive copying.
	$rsync_options = '-r';

	// If debug mode is set to verbose, append 'v' to rsync options for verbose output.
	if ( $runner_vars['WPT_DEBUG'] ) {
		$rsync_options = $rsync_options . 'v';
	}

	// Perform the rsync operation with the configured options and exclude patterns.
	// This operation synchronizes the test environment with the prepared files, excluding
	// version control directories and other non-essential files for test execution.
	perform_operations(
		array(
			'rsync ' . $rsync_options
				. ' --exclude=".git/"'
				. ' --exclude="node_modules/"'
				. ' --exclude="composer.phar"'
				. ' --exclude=".cache/"'
				. ' --exclude=".devcontainer/"'
				. ' --exclude=".github/"'
				. ' --exclude="tools/"'
				// Exclude all subdirectories in tests/ except phpunit/.
				. ' --exclude="tests/*" --include="tests/phpunit/**"'
				. ' -e "ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . '" '
				. escapeshellarg( trailingslashit( $runner_vars['WPT_PREPARE_DIR'] ) )
				. ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] . ':' . $runner_vars['WPT_TEST_DIR'] ),
		)
	);
}

// Log a success message indicating that the environment has been prepared.
log_message( 'Success: Prepared environment.' );
