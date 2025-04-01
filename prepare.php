<?php
/**
 * This script prepares the environment for WordPress unit tests. It
 * sets up the necessary variables and configurations based on the environment.
 * The script assumes that certain environment variables are set to configure
 * SSH, directories, and executables used in the test preparation process.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 *
 * @package WordPress
 */
require __DIR__ . '/functions.php';

/**
 * Check for the presence of required environment variables. This function
 * should be defined in functions.php and should throw an exception or exit if
 * any required variables are missing.
 */
check_required_env();

/**
 * Retrieves environment variables and sets defaults for test preparation. These
 * variables are used to configure SSH connections, file paths, and executable
 * commands needed for setting up the test environment.
 */
$WPT_LABEL                  = trim( getenv( 'WPT_LABEL' ) ) ? : 'default';
$WPT_PREPARE_DIR            = trim( getenv( 'WPT_PREPARE_DIR' ) ) ? : '/tmp/wp-test-runner';
$WPT_SSH_CONNECT            = trim( getenv( 'WPT_SSH_CONNECT' ) ) ? : '';
$WPT_SSH_OPTIONS            = trim( getenv( 'WPT_SSH_OPTIONS' ) ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR               = trim( getenv( 'WPT_TEST_DIR' ) ) ? : '/tmp/wp-test-runner';
$WPT_PHP_EXECUTABLE         = trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ? : 'php';
$WPT_PHP_EXECUTABLE_MULTI   = trim( getenv( 'WPT_PHP_EXECUTABLE_MULTI' ) ) ? : '';
$WPT_CERTIFICATE_VALIDATION = (int) getenv( 'WPT_CERTIFICATE_VALIDATION' );

/**
 * Determines if the debug mode is enabled based on the 'WPT_DEBUG' environment
 * variable. The debug mode can affect error reporting and other debug-related
 * settings.
 */
$WPT_DEBUG_INI = trim( getenv( 'WPT_DEBUG' ) );
switch ( $WPT_DEBUG_INI ) {
	case '':
	default:
		$WPT_DEBUG = false;
		break;
	case 'verbose':
		$WPT_DEBUG = 'verbose';
		break;
}
unset( $WPT_DEBUG_INI );

/**
 * Determines if commits are enabled based on the 'WPT_COMMITS' environment
 * variable.
 */
$WPT_COMMITS_INI = (int) getenv( 'WPT_COMMITS' );
switch ( $WPT_COMMITS_INI ) {
	case 0:
	default:
		$WPT_COMMITS = false;
		break;
	case 1:
		$WPT_COMMITS = true;
		break;
}
unset( $WPT_COMMITS_INI );

/*
 * Fetches the latest 10 commits from the WordPress development repository.
 */
$WPT_COMMIT = array();
if ( $WPT_COMMITS ) {

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, 'https://api.github.com/repos/WordPress/wordpress-develop/commits?per_page=10' );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_USERAGENT, 'WordPress.org PHPUnit test tool' );
	$commits = curl_exec( $ch );
	curl_close( $ch );

	$commits_array = json_decode( $commits, true );
	unset( $commits );

	foreach ( $commits_array as $commit ) {
		$WPT_COMMIT[] = trim( (string) $commit['sha'] );
		unset( $commit );
	}
	unset( $commits_array );
}

/**
 * Processes the latest commits from the GitHub repository and updates the local
 * JSON file with executed and pending commits.
 */
if ( ! empty( $WPT_COMMIT ) ) {
	$commitsFile = __DIR__ . '/commits.json';

	if ( file_exists( $commitsFile ) ) {

		$c_array = json_decode( file_get_contents( $commitsFile ), true ) ?? [];

		$testing_commit    = $c_array['testing_commit'] ?? null;
		$executed_commits  = $c_array['executed_commits'] ?? [];
		$pending_commits   = $c_array['pending_commits'] ?? [];

		// Filter 'executed_commits' to include only commits present in $WPT_COMMIT
		$executed_commits = array_values( array_intersect( $executed_commits, $WPT_COMMIT ) );

		// Filter 'pending_commits' to include only commits present in $WPT_COMMIT
		$pending_commits = array_values( array_intersect( $pending_commits, $WPT_COMMIT ) );

		// Remove from 'pending_commits' the commits that are already in 'executed_commits' or are the 'testing_commit'.
		$pending_commits = array_filter( $pending_commits, function( $commithash ) use ( $executed_commits, $testing_commit ) {
			return ! in_array( $commithash, $executed_commits, true ) && $commithash !== $testing_commit;
		});

		// Add new commits to 'pending_commits' that are neither in 'executed_commits' nor in 'pending_commits'.
		$new_commits = array_diff( $WPT_COMMIT, $executed_commits, $pending_commits );
		$pending_commits = array_merge( $pending_commits, $new_commits );

		// Reindex the array of 'pending_commits'.
		$pending_commits = array_values( $pending_commits );

		// Prepare the final array to encode to JSON
		$c = [
			'executed_commits' => $executed_commits,
			'pending_commits'  => $pending_commits,
			'testing_commit'   => $testing_commit,
		];

	} else {

		// If the JSON file does not exist, initialize the default values.
		$c = [
			'executed_commits' => [],
			'pending_commits'  => $WPT_COMMIT,
			'testing_commit'   => null,
		];
	}

	// Encodes the array to JSON and saves it to the file
	file_put_contents( $commitsFile, json_encode( $c, JSON_PRETTY_PRINT ) );
}

/**
 * Parses the multi PHP executable versions and populates the
 * WPT_PHP_EXECUTABLE_MULTI_ARRAY.
 */
$WPT_PHP_EXECUTABLE_MULTI_ARRAY = [];
if ( ! empty( $WPT_PHP_EXECUTABLE_MULTI ) ) {

	// Divide the version string by semicolon
	$php_multi_versions = explode( ';', $WPT_PHP_EXECUTABLE_MULTI );

	foreach ( $php_multi_versions as $php_multi_version ) {
		// Divide each version by the equals sign and apply trim to each part
		$parts = array_map( 'trim', explode( '=', $php_multi_version, 2 ) );

		// Ensures that both parts exist and are not empty.
		if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
			[ $version, $bin ] = $parts;
			$WPT_PHP_EXECUTABLE_MULTI_ARRAY[] = [
				'version' => $version,
				'bin'     => $bin
			];
		}
	}
}

/**
 * Sets up the SSH private key for use in the test environment if provided.
 */
$WPT_SSH_PRIVATE_KEY_BASE64 = trim( getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' ) );

// Check if the private key variable is not empty
if ( ! empty( $WPT_SSH_PRIVATE_KEY_BASE64 ) ) {

	// Log the action of securely extracting the private key
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );

	// Define the .ssh directory path
	$SSHDIR = getenv('HOME') . '/.ssh';

	// Check if the .ssh directory exists, create it if it does not
	if ( ! is_dir( $SSHDIR ) ) {
		mkdir( $SSHDIR, 0777, true );
	}

	// Decode the base64-encoded private key and write it to the id_rsa file
	file_put_contents( $SSHDIR . '/id_rsa', base64_decode( $WPT_SSH_PRIVATE_KEY_BASE64 ) );

	// Define the array of operations to perform
	$operations = [
		'chmod 600 ~/.ssh/id_rsa'
	];

	if ( empty( $WPT_SSH_CONNECT ) ) {
		// Add local operation if no SSH connection string is provided
		$operations[] = 'wp cli info';
	} else {
		// Add remote operation if SSH connection string is provided
		$operations[] = 'ssh -q ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' wp cli info';
	}

	// Execute the defined operations
	perform_operations( $operations );
}

/**
 * Don't validate the TLS certificate. Useful for local environments.
 */
// Initialize the certificate validation flag
$certificate_validation = '';
// Append the no-check-certificate option if certificate validation is disabled
if ( ! $WPT_CERTIFICATE_VALIDATION ) {
	$certificate_validation = ' --no-check-certificate';
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
\$logDir = __DIR__ . '/tests/phpunit/build/logs/';
if (!is_dir(\$logDir)) {
	mkdir(\$logDir, 0777, true);
}
// Initialize GD and Imagick info
\$gd_info = extension_loaded('gd') ? gd_info() : [];
\$imagick_info = extension_loaded('imagick') ? Imagick::queryFormats() : [];
// Collect environment details
\$env = [
	'label'          => '{$WPT_LABEL}',
	'php_version'    => phpversion(),
	'php_modules'    => [],
	'gd_info'        => \$gd_info,
	'imagick_info'   => \$imagick_info,
	'mysql_version'  => trim(shell_exec('mysql --version')),
	'system_utils'   => [],
	'os_name'        => trim(shell_exec('uname -s')),
	'os_version'     => trim(shell_exec('uname -r')),
];
// List of PHP modules to check
\$php_modules = [
	'bcmath', 'ctype', 'curl', 'date', 'dom', 'exif', 'fileinfo', 'filter',
	'ftp', 'gd', 'gettext', 'gmagick', 'hash', 'iconv', 'imagick', 'imap',
	'intl', 'json', 'libsodium', 'libxml', 'mbstring', 'mcrypt', 'mod_xml',
	'mysqli', 'mysqlnd', 'openssl', 'pcre', 'pdo_mysql', 'soap', 'sockets',
	'sodium', 'xml', 'xmlreader', 'zip', 'zlib',
];
// Populate PHP modules versions
foreach (\$php_modules as \$php_module) {
    \$env['php_modules'][\$php_module] = phpversion(\$php_module);
}
// Helper function to filter cURL bits
function curl_selected_bits(\$key) { 
    return in_array(\$key, ['version', 'ssl_version', 'libz_version']); 
}
// Collect cURL version info
\$curl_bits = curl_version();
\$env['system_utils']['curl'] = implode(' ', array_values(array_filter(\$curl_bits, 'curl_selected_bits', ARRAY_FILTER_USE_KEY)));
// Collect Imagick or Gmagick version info
if (class_exists('Imagick')) {
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
// Collect OpenSSL version
\$env['system_utils']['openssl'] = str_replace('OpenSSL ', '', trim(shell_exec('openssl version')));
// Optional: Collect MySQL version using mysqli (currently commented out)
// \$mysqli = new mysqli(WPT_DB_HOST, WPT_DB_USER, WPT_DB_PASSWORD, WPT_DB_NAME);
// \$env['mysql_version'] = \$mysqli->query("SELECT VERSION()")->fetch_row()[0];
// \$mysqli->close();
// Write environment info to JSON file
file_put_contents(\$logDir . 'env.json', json_encode(\$env, JSON_PRETTY_PRINT));
// If running from CLI during WP installation, output PHP version and executable path
if ('cli' === php_sapi_name() && defined('WP_INSTALLING') && WP_INSTALLING) {
	echo PHP_EOL;
	echo 'PHP version: ' . phpversion() . ' (' . realpath(\$_SERVER['_']) . ')' . PHP_EOL;
	echo PHP_EOL;
}
EOT;

// Initialize a string that will be used to identify the database settings section in the configuration file.
$logger_replace_string = '// ** Database settings ** //' . PHP_EOL;

// Prepend the logger script to the database settings identifier to ensure it gets included in the wp-tests-config.php file.
$system_logger = $logger_replace_string . $system_logger;

/**
 * Prepares a script to log system information relevant to the testing environment.
 * The script checks for the existence of the log directory and creates it if it does not exist.
 * It then collects various pieces of system information including PHP version, loaded PHP modules,
 * MySQL version, operating system details, and versions of key utilities like cURL and OpenSSL.
 * This information is collected in an array and written to a JSON file in the log directory.
 * Additionally, if running from the command line during a WordPress installation process, 
 * it outputs the PHP version and executable path.
 */

// Prepare an array of PHP executables. If multi-PHP is configured, use the multi-array; otherwise, use a single executable.
$php_executables = ! empty( $WPT_PHP_EXECUTABLE_MULTI_ARRAY ) ? $WPT_PHP_EXECUTABLE_MULTI_ARRAY : [
	[
		'version' => 'default',
		'bin'     => $WPT_PHP_EXECUTABLE, // Ensure this variable is defined for the single PHP executable case.
	]
];

/**
 * Performs a series of operations to set up the test environment. This includes creating a preparation directory,
 * cloning the WordPress development repository, downloading the WordPress importer plugin, and preparing the environment with npm.
 */
foreach ( $php_executables as $php_multi ) {

	// Generate unique directory names based on the PHP version.
	$version_hash = crc32( $php_multi['version'] );
	$WPT_PREPARE_DIR_MULTI = $WPT_PREPARE_DIR . '-' . $version_hash;
	$WPT_TEST_DIR_MULTI = $WPT_TEST_DIR . '-' . $version_hash;

	// Create the preparation directory and clone the WordPress develop repository.
	perform_operations([
		'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),
		'git clone --depth=10 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),
		'git config --add safe.directory ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ),
	]);

	// Handle commit SHA if $WPT_COMMITS is enabled.
	if ( $WPT_COMMITS ) {
		$commit_sha = null;
		$commits_file = __DIR__ . '/commits.json';

		if ( file_exists( $commits_file ) ) {
			$c_array = json_decode( file_get_contents( $commits_file ), true );

			if ( isset( $c_array['testing_commit'] ) && count( $c_array['testing_commit'] ) ) {
				$commit_sha = $c_array['testing_commit'][0];
			} else {
				$commit_sha = array_pop( $c_array['pending_commits'] );
				$c_array['testing_commit'][0] = $commit_sha;
			}

			file_put_contents( $commits_file, json_encode( $c_array ) );
		}

		if ( ! is_null( $commit_sha ) ) {
			perform_operations([
				'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . ' && git checkout ' . $commit_sha,
			]);
		}
	}

	// Download the WordPress importer plugin, unzip it, and build the project with npm.
	perform_operations([
		'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/tests/phpunit/data/plugins/wordpress-importer.zip' ) . ' https://downloads.wordpress.org/plugin/wordpress-importer.zip' . $certificate_validation,
		'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/tests/phpunit/data/plugins/' ) . '; unzip wordpress-importer.zip; rm wordpress-importer.zip',
		'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . '; npm install && npm run build',
	]);

	// Log the start of the variable replacement process for the specific PHP version.
	log_message( 'Replacing variables in ' . $php_multi['version'] . ' wp-tests-config.php' );

	// Read the contents of the wp-tests-config-sample.php file.
	$config_sample_path = $WPT_PREPARE_DIR_MULTI . '/wp-tests-config-sample.php';
	$contents = file_get_contents( $config_sample_path );

	// Define the PHP binary string for the specific PHP executable.
	$php_binary_string = 'define( \'WP_PHP_BINARY\', \'' . $php_multi['bin'] . '\' );';

	// Generate a unique table prefix based on environment variables and PHP version.
	$wptests_tableprefix = trim( getenv( 'WPT_TABLE_PREFIX' ) ) ?: 'wptests_';
	$wptests_tableprefix .= crc32( $php_multi['version'] );

	// Create an associative array for search and replace in the configuration file.
	$search_replace = [
		'wptests_'                              => $wptests_tableprefix,
		'youremptytestdbnamehere'               => trim( getenv( 'WPT_DB_NAME' ) ),
		'yourusernamehere'                      => trim( getenv( 'WPT_DB_USER' ) ),
		'yourpasswordhere'                      => trim( getenv( 'WPT_DB_PASSWORD' ) ),
		'localhost'                             => trim( getenv( 'WPT_DB_HOST' ) ),
		'define( \'WP_PHP_BINARY\', \'php\' );' => $php_binary_string,
		$logger_replace_string                  => $system_logger,
	];

	// Replace placeholders in the configuration sample with actual values.
	$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );

	// Write the modified content to wp-tests-config.php.
	$config_path = $WPT_PREPARE_DIR_MULTI . '/wp-tests-config.php';
	file_put_contents( $config_path, $contents );

	/**
	 * Determines the PHP version of the test environment to ensure the correct version of PHPUnit is installed.
	 * It constructs a command that prints out the PHP version in a format compatible with PHPUnit's version requirements.
	 */
	$php_version_cmd = $php_multi['bin'] . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";

	// Modify the command to execute remotely via SSH if an SSH connection is provided.
	if ( ! empty( $WPT_SSH_CONNECT ) ) {
		$php_version_cmd = 'ssh ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' ' . escapeshellarg( $php_version_cmd );
	}

	// Execute the command to retrieve the PHP version.
	$retval = 0;
	$env_php_version = exec( $php_version_cmd, $output, $retval );

	// Log an error if the PHP version retrieval failed.
	if ( 0 !== $retval ) {
		error_message( 'Could not retrieve the environment PHP Version for ' . $php_multi['version'] . '.' );
	}

	// Log the obtained PHP version.
	log_message( 'Environment PHP Version: ' . $env_php_version );

	/**
	 * Checks if the detected PHP version is below 7.2.
	 * The test runner requires PHP version 7.2 or above, and if the environment's PHP version
	 * is lower, it logs an error message.
	 */
	if ( version_compare( $env_php_version, '7.2', '<' ) ) {
		error_message( 'The test runner is not compatible with PHP < 7.2.' );
	}

	/**
   * Use Composer to manage PHPUnit and its dependencies.
   * This allows for better dependency management and compatibility.
	 */

	// Define the Composer command based on its availability.
	$composer_cmd = 'cd ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI ) . ' && ';
	$composer_path = system( 'which composer', $retval );
	$composer_path = escapeshellarg( trim( $composer_path ) );

	if ( 0 === $retval && ! empty( $composer_path ) ) {
		// If Composer is available, use the Composer binary.
		$composer_cmd .= $composer_path . ' ';
	} else {
		// If Composer is not available, download the Composer phar file.
		log_message( 'Local Composer not found. Downloading latest stable ...' );
		perform_operations([
			'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR_MULTI . '/composer.phar' ) . ' https://getcomposer.org/composer-stable.phar' . $certificate_validation,
		]);
		// Use the downloaded Composer phar file.
		$composer_cmd .= $php_multi['bin'] . ' composer.phar ';
	}

	// Set the PHP version for Composer and update dependencies.
	perform_operations([
		$composer_cmd . 'config platform.php ' . escapeshellarg( $env_php_version ),
		$composer_cmd . 'update',
	]);

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
			$rsync_options .= 'v';
		}

		// Perform the rsync operation with the configured options and exclude patterns.
		perform_operations([
			'rsync ' . $rsync_options . ' --exclude=".git/" --exclude="node_modules/" --exclude="composer.phar" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( rtrim( $WPT_PREPARE_DIR_MULTI, '/' ) ) . '/ ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
		]);
	}
}

// Log a success message indicating that the environment has been prepared.
log_message( 'Success: Prepared environment.' );
