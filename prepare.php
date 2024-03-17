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
 * Retrieves environment variables and sets defaults for test preparation.
 * These variables are used to configure SSH connections, file paths, and
 * executable commands needed for setting up the test environment.
 */
$WPT_LABEL          = trim( getenv( 'WPT_LABEL' ) ) ? : 'default';
$WPT_PREPARE_DIR    = trim( getenv( 'WPT_PREPARE_DIR' ) );
$WPT_SSH_CONNECT    = trim( getenv( 'WPT_SSH_CONNECT' ) );
$WPT_SSH_OPTIONS    = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR       = trim( getenv( 'WPT_TEST_DIR' ) );
$WPT_PHP_EXECUTABLE = trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ? : 'php';
$WPT_PHP_EXECUTABLE_MULTI = trim( getenv( 'WPT_PHP_EXECUTABLE_MULTI' ) ) ? : '';
$WPT_CERTIFICATE_VALIDATION = trim( getenv( 'WPT_CERTIFICATE_VALIDATION' ) );

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

$WPT_COMMIT = array();
if( $WPT_COMMITS ) {

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/WordPress/wordpress-develop/commits?per_page=10');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress.org PHPUnit test tool');
	$commits = curl_exec($ch);
	curl_close($ch);

	$commits_array = json_decode( $commits, true );
	unset( $commits );
	foreach ( $commits_array as $commit ) {
		
		$WPT_COMMIT[] = $commit['sha'];
		
		unset( $commit );
	}
	unset( $commits_array );

}

if( count( $WPT_COMMIT ) ) {
	
	if( file_exists( __DIR__ . '/commits.json' ) ) {
		
		$c_array = json_decode( file_get_contents( __DIR__ . '/commits.json' ), true );
		
		if( isset( $c_array['testing_commit'] ) ) {
			$testing_commit = $c_array['testing_commit'];
		} else {
			$testing_commit = array();
		}

		if( isset( $c_array['executed_commits'] ) ) {
			$executed_commits = $c_array['executed_commits'];
		} else {
			$executed_commits = array();
		}

		if( isset( $c_array['pending_commits'] ) ) {
			$pending_commits = $c_array['pending_commits'];
		} else {
			$pending_commits = array();
		}

		foreach ($executed_commits as $key => $commithash) {
			if (!in_array($commithash, $WPT_COMMIT)) {
				unset($executed_commits[$key]);
			}
			unset( $key, $commithash );
		}

		foreach ($pending_commits as $key => $commithash) {
			if (!in_array($commithash, $WPT_COMMIT)) {
				unset($pending_commits[$key]);
			}
			unset( $key, $commithash );
		}

		foreach ($WPT_COMMIT as $commithash) {
			if (!in_array($commithash, $pending_commits) && !in_array($commithash, $executed_commits)) {
				array_push($pending_commits, $commithash);
			}
			unset( $commithash );
		}

		$c = array( 'executed_commits' => $executed_commits, 'pending_commits' => $pending_commits, 'testing_commit' => $testing_commit );

		unset( $executed_commits, $pending_commits, $testing_commit );

		$c_json = json_encode( $c );
		
		file_put_contents( __DIR__ . '/commits.json', $c_json );
		
		unset( $c, $c_json );


	} else {
		
		$c = array( 'executed_commits' => array(), 'pending_commits' => $WPT_COMMIT, 'testing_commit' => $testing_commit );

		$c_json = json_encode( $c );
		
		file_put_contents( __DIR__ . '/commits.json', $c_json );
		
		unset( $c, $c_json );

	}

}

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

/**
 * Prepares a script to log system information relevant to the testing environment.
 * The script checks for the existence of the log directory and creates it if it does not exist.
 * It then collects various pieces of system information including PHP version, loaded PHP modules,
 * MySQL version, operating system details, and versions of key utilities like cURL and OpenSSL.
 * This information is collected in an array and written to a JSON file in the log directory.
 * Additionally, if running from the command line during a WordPress installation process, 
 * it outputs the PHP version and executable path.
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
	'label'					 => '{$WPT_LABEL}',
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

		perform_operations( array(

			// Create the preparation directory if it doesn't exist. The '-p' flag creates intermediate directories as required.
			'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),

			// Clone the WordPress develop repository from GitHub into the preparation directory.
			// The '--depth=1' flag creates a shallow clone with a history truncated to the last commit.
			'git clone --depth=10 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),

			'git config --add safe.directory ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),

		) );

		if( $WPT_COMMITS ) {

			$commit_sha = null;
			if( file_exists( __DIR__ . '/commits.json' ) ) {
		
				$c_array = json_decode( file_get_contents( __DIR__ . '/commits.json' ), true );
				
				if( isset( $c_array['testing_commit'] ) && count( $c_array['testing_commit'] ) ) {
					
					$commit_sha = $c_array['testing_commit'][0];

				} else {

					$commit_sha = array_shift($c_array['pending_commits']);

					$c_array['testing_commit'][0] = $commit_sha;

				}

				$c_json = json_encode( $c_array );
		
				file_put_contents( __DIR__ . '/commits.json', $c_json );

			}
			
			if( ! is_null( $commit_sha ) ) {
				
				perform_operations( array(

					'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),
					'git checkout ' . $commit_sha

				) );

			}
				
		}

		perform_operations( array(

			// Download the WordPress importer plugin zip file to the specified plugins directory.
			'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/tests/phpunit/data/plugins/wordpress-importer.zip' ) . ' https://downloads.wordpress.org/plugin/wordpress-importer.zip' . $certificate_validation,

			// Change directory to the plugin directory, unzip the WordPress importer plugin, and remove the zip file.
			'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/tests/phpunit/data/plugins/' ) . '; unzip wordpress-importer.zip; rm wordpress-importer.zip',

			// Change directory to the preparation directory, install npm dependencies, and build the project.
			'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . '; npm install && npm run build'

		) );

		// Log a message indicating the start of the variable replacement process for configuration.
		log_message( 'Replacing variables in ' . $php_multi['version'] . ' wp-tests-config.php' );

		/**
		 * Reads the contents of the WordPress test configuration sample file.
		 * This file contains template placeholders that need to be replaced with actual values 
		 * from environment variables to configure the WordPress test environment.
		 */

		$WPT_PREPARE_DIR_MULTI = $WPT_PREPARE_DIR . '-' . crc32( $php_multi['version'] );
		$contents = file_get_contents( $WPT_PREPARE_DIR_MULTI . '/wp-tests-config-sample.php' );

		// Define a string that will set the 'WP_PHP_BINARY' constant to the path of the PHP executable.

		$php_binary_string = 'define( \'WP_PHP_BINARY\', \''. $php_multi['bin'] . '\' );';

		/**
		 * An associative array mapping configuration file placeholders to environment-specific values.
		 * This array is used in the subsequent str_replace operation to replace placeholders
		 * in the wp-tests-config-sample.php file with values from the environment or defaults if none are provided.
		 */

		$wptests_tableprefix = trim( getenv( 'WPT_TABLE_PREFIX' ) ) ? : 'wptests_';
		$wptests_tableprefix .= crc32( $php_multi['version'] );
		$search_replace = array(
			'wptests_'                              => $wptests_tableprefix,
			'youremptytestdbnamehere'               => trim( getenv( 'WPT_DB_NAME' ) ),
			'yourusernamehere'                      => trim( getenv( 'WPT_DB_USER' ) ),
			'yourpasswordhere'                      => trim( getenv( 'WPT_DB_PASSWORD' ) ),
			'localhost'                             => trim( getenv( 'WPT_DB_HOST' ) ),
			'define( \'WP_PHP_BINARY\', \'php\' );' => $php_binary_string,
			$logger_replace_string                  => $system_logger,
		);
		$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );

		// Replace the placeholders in the wp-tests-config-sample.php file content with actual values.
		// Write the modified content to the wp-tests-config.php file, which will be used by the test suite.

		$WPT_PREPARE_DIR_MULTI = $WPT_PREPARE_DIR . '-' . crc32( $php_multi['version'] );
		file_put_contents( $WPT_PREPARE_DIR_MULTI . '/wp-tests-config.php', $contents );

		/**
		 * Determines the PHP version of the test environment to ensure the correct version of PHPUnit is installed.
		 * It constructs a command that prints out the PHP version in a format compatible with PHPUnit's version requirements.
		 */
		$php_version_cmd = $php_multi['bin'] . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";

		/**
		 * If an SSH connection string is provided, the command to determine the PHP version is modified 
		 * to execute remotely over SSH. This is required if the test environment is not the local machine.
		 */
		if ( ! empty( $WPT_SSH_CONNECT ) ) {
			// The PHP version check command is prefixed with the SSH command, including SSH options,
			// and the connection string, ensuring the command is executed on the remote machine.
			$php_version_cmd = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $php_version_cmd );
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
			error_message( 'Could not retrieve the environment PHP Version for ' . $php_multi['version'] . '.' );
		}

		// Log the obtained PHP version for confirmation and debugging purposes.
		log_message( 'Environment PHP Version: ' . $env_php_version );

		/**
		 * Checks if the detected PHP version is below 7.0.
		 * The test runner requires PHP version 7.0 or above, and if the environment's PHP version
		 * is lower, it logs an error message and could terminate the script.
		 */
		if ( version_compare( $env_php_version, '7.0', '<' ) ) {
			// Logs an error message indicating the test runner's incompatibility with PHP versions below 7.0.
			error_message( 'The test runner is not compatible with PHP < 7.0.' );
		}

		/**
		 * Use Composer to manage PHPUnit and its dependencies.
		 * This allows for better dependency management and compatibility.
		 */

		// Check if Composer is installed and available in the PATH.

		$composer_cmd = 'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . ' && ';

		$retval = 0;
		$composer_path = escapeshellarg( system( 'which composer', $retval ) );

		if ( $retval === 0 ) {

			// If Composer is available, prepare the command to use the Composer binary.
			$composer_cmd .= $composer_path . ' ';

		} else {

			// If Composer is not available, download the Composer phar file.
			log_message( 'Local Composer not found. Downloading latest stable ...' );

			perform_operations( array(
				'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/composer.phar' ) . ' https://getcomposer.org/composer-stable.phar',
			) );

			// Update the command to use the downloaded Composer phar file.
			$composer_cmd .= $php_multi['bin'] . ' composer.phar ';
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
				'rsync ' . $rsync_options . ' --exclude=".git/" --exclude="node_modules/" --exclude="composer.phar" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( trailingslashit( $WPT_PREPARE_DIR_MULTI )  ) . ' ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
			) );
		}

	}

} else {

	perform_operations( array(

		// Create the preparation directory if it doesn't exist. The '-p' flag creates intermediate directories as required.
		'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR ),

		// Clone the WordPress develop repository from GitHub into the preparation directory.
		// The '--depth=1' flag creates a shallow clone with a history truncated to the last commit.
		'git clone --depth=10 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR ),

		'git config --add safe.directory ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),

	) );

	if( $WPT_COMMITS ) {

		$commit_sha = null;
		if( file_exists( __DIR__ . '/commits.json' ) ) {
	
			$c_array = json_decode( file_get_contents( __DIR__ . '/commits.json' ), true );
			
			if( isset( $c_array['testing_commit'] ) && count( $c_array['testing_commit'] ) ) {
				
				$commit_sha = $c_array['testing_commit'][0];

			} else {

				$commit_sha = array_shift($c_array['pending_commits']);

				$c_array['testing_commit'][0] = $commit_sha;

			}

			$c_json = json_encode( $c_array );
	
			file_put_contents( __DIR__ . '/commits.json', $c_json );

		}
		
		if( ! is_null( $commit_sha ) ) {
			
			perform_operations( array(

				'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),
				'git checkout ' . $commit_sha,

			) );

		}
			
	}

	perform_operations( array(

		// Download the WordPress importer plugin zip file to the specified plugins directory.
		'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/wordpress-importer.zip' ) . ' https://downloads.wordpress.org/plugin/wordpress-importer.zip' . $certificate_validation,

		// Change directory to the plugin directory, unzip the WordPress importer plugin, and remove the zip file.
		'cd ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/' ) . '; unzip wordpress-importer.zip; rm wordpress-importer.zip',

		// Change directory to the preparation directory, install npm dependencies, and build the project.
		'cd ' . escapeshellarg( $WPT_PREPARE_DIR ) . '; npm install && npm run build'

	) );

	// Log a message indicating the start of the variable replacement process for configuration.
	log_message( 'Replacing variables in wp-tests-config.php' );


exit;



	/**
	 * Reads the contents of the WordPress test configuration sample file.
	 * This file contains template placeholders that need to be replaced with actual values 
	 * from environment variables to configure the WordPress test environment.
	 */

	$contents = file_get_contents( $WPT_PREPARE_DIR . '/wp-tests-config-sample.php' );

	// Define a string that will set the 'WP_PHP_BINARY' constant to the path of the PHP executable.

	$php_binary_string = 'define( \'WP_PHP_BINARY\', \''. $WPT_PHP_EXECUTABLE . '\' );';

	/**
	 * An associative array mapping configuration file placeholders to environment-specific values.
	 * This array is used in the subsequent str_replace operation to replace placeholders
	 * in the wp-tests-config-sample.php file with values from the environment or defaults if none are provided.
	 */

	$wptests_tableprefix = trim( getenv( 'WPT_TABLE_PREFIX' ) ) ? : 'wptests_';
	$search_replace = array(
		'wptests_'                              => $wptests_tableprefix,
		'youremptytestdbnamehere'               => trim( getenv( 'WPT_DB_NAME' ) ),
		'yourusernamehere'                      => trim( getenv( 'WPT_DB_USER' ) ),
		'yourpasswordhere'                      => trim( getenv( 'WPT_DB_PASSWORD' ) ),
		'localhost'                             => trim( getenv( 'WPT_DB_HOST' ) ),
		'define( \'WP_PHP_BINARY\', \'php\' );' => $php_binary_string,
		$logger_replace_string                  => $system_logger,
	);
	$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );

	// Replace the placeholders in the wp-tests-config-sample.php file content with actual values.
	// Write the modified content to the wp-tests-config.php file, which will be used by the test suite.

	file_put_contents( $WPT_PREPARE_DIR . '/wp-tests-config.php', $contents );

	/**
	 * Determines the PHP version of the test environment to ensure the correct version of PHPUnit is installed.
	 * It constructs a command that prints out the PHP version in a format compatible with PHPUnit's version requirements.
	 */
	$php_version_cmd = $WPT_PHP_EXECUTABLE . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";

	/**
	 * If an SSH connection string is provided, the command to determine the PHP version is modified 
	 * to execute remotely over SSH. This is required if the test environment is not the local machine.
	 */
	if ( ! empty( $WPT_SSH_CONNECT ) ) {
		// The PHP version check command is prefixed with the SSH command, including SSH options,
		// and the connection string, ensuring the command is executed on the remote machine.
		$php_version_cmd = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $php_version_cmd );
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
	 * Checks if the detected PHP version is below 7.0.
	 * The test runner requires PHP version 7.0 or above, and if the environment's PHP version
	 * is lower, it logs an error message and could terminate the script.
	 */
	if ( version_compare( $env_php_version, '7.0', '<' ) ) {
		// Logs an error message indicating the test runner's incompatibility with PHP versions below 7.0.
		error_message( 'The test runner is not compatible with PHP < 7.0.' );
	}

	/**
	 * Use Composer to manage PHPUnit and its dependencies.
	 * This allows for better dependency management and compatibility.
	 */

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
		$composer_cmd .= $WPT_PHP_EXECUTABLE . ' composer.phar ';
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

}

// Log a success message indicating that the environment has been prepared.
log_message( 'Success: Prepared environment.' );
