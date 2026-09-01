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
 * Select the wordpress-develop commit to test. Already reported SHAs are skipped
 * via commits.json. When WPT_COMMITS is on, the last 30 commits are queued.
 */
$commit_sha = select_commit_to_test( $runner_vars['WPT_COMMITS'] );
if ( '' === $commit_sha ) {
	log_message( 'No untested wordpress-develop commits to run. Skipping.' );
	exit( 0 );
}

if ( 'HEAD' === $commit_sha ) {
	$resolved_sha = fetch_wordpress_develop_head_sha();
	if ( '' !== $resolved_sha ) {
		$state = load_commits_state();
		if ( in_array( $resolved_sha, $state['executed_commits'], true ) ) {
			log_message( 'wordpress-develop HEAD ' . $resolved_sha . ' was already tested. Skipping.' );
			exit( 0 );
		}
		$state['testing_commit'] = $resolved_sha;
		save_commits_state( $state );
		$commit_sha = $resolved_sha;
	}
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
$wpt_ssh_private_key_base64 = trim( getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' ) );

if ( ! empty( $wpt_ssh_private_key_base64 ) ) {

	// Log the action of securely extracting the private key.
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );

	// Check if the .ssh directory exists in the home directory, and create it if it does not.
	if ( ! is_dir( getenv( 'HOME' ) . '/.ssh' ) ) {
		// The mkdir function creates the directory with the specified permissions and the recursive flag set to true.
		mkdir( getenv( 'HOME' ) . '/.ssh', 0777, true );
	}

	// Write the decoded private key into the id_rsa file within the .ssh directory.
	file_put_contents( getenv( 'HOME' ) . '/.ssh/id_rsa', base64_decode( $wpt_ssh_private_key_base64 ) );

	// Define the array of operations to perform, depending on the SSH connection availability.
	// If no SSH connection string is provided, add a local operation to the array.
	// If an SSH connection string is provided, add a remote operation to the array.
	// Execute the operations defined in the operations array.
	if ( empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		perform_operations(
			array(
				'chmod 600 ~/.ssh/id_rsa',
				'wp cli info',
			)
		);
	} else {
		perform_operations(
			array(
				'chmod 600 ~/.ssh/id_rsa',
				'ssh -q ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' wp cli info',
			)
		);
	}
}

$wpt_label = addslashes( $runner_vars['WPT_LABEL'] );

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
	'label'          => '$wpt_label',
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

foreach ( $runner_vars['WPT_PHP_EXECUTABLES'] as $php ) {
	$paths = get_php_run_paths( $runner_vars, $php );

	log_message( 'Preparing environment for PHP ' . $php['version'] . ' (' . $php['bin'] . ')' );

	$clone_operations = array();
	if ( is_dir( $paths['prepare_dir'] ) ) {
		$clone_operations[] = 'rm -rf ' . escapeshellarg( $paths['prepare_dir'] );
	}

	$clone_operations[] = 'mkdir -p ' . escapeshellarg( $paths['prepare_dir'] );
	$clone_operations[] = 'git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $paths['prepare_dir'] );
	$clone_operations[] = 'git -C ' . escapeshellarg( $paths['prepare_dir'] ) . ' config --add safe.directory ' . escapeshellarg( $paths['prepare_dir'] );

	if ( 'HEAD' !== $commit_sha ) {
		$clone_operations[] = 'cd ' . escapeshellarg( $paths['prepare_dir'] ) . ' && git fetch --depth=1 origin ' . escapeshellarg( $commit_sha ) . ' && git checkout ' . escapeshellarg( $commit_sha );
	}

	$clone_operations[] = 'cd ' . escapeshellarg( $paths['prepare_dir'] ) . '; npm install && npm run build';

	perform_operations( $clone_operations );

	if ( 'HEAD' === $commit_sha ) {
		$resolved_sha = trim( (string) exec( 'git --git-dir=' . escapeshellarg( $paths['prepare_dir'] . '/.git' ) . ' rev-parse HEAD' ) );
		if ( '' !== $resolved_sha ) {
			$state = load_commits_state();
			if ( '' === $state['testing_commit'] ) {
				$state['testing_commit'] = $resolved_sha;
				save_commits_state( $state );
			}
			$commit_sha = $resolved_sha;
		}
	}

	log_message( 'Replacing variables in wp-tests-config.php' );

	$contents = file_get_contents( $paths['prepare_dir'] . '/wp-tests-config-sample.php' );

	$php_binary_string = 'define( \'WP_PHP_BINARY\', \'' . $php['bin'] . '\' );';

	$wpt_table_prefix = trim( getenv( 'WPT_TABLE_PREFIX' ) );
	$wpt_table_prefix = '' !== $wpt_table_prefix ? $wpt_table_prefix : 'wptests_';
	if ( 'default' !== $php['version'] ) {
		$wpt_table_prefix .= str_replace( '.', '_', $php['version'] ) . '_';
	}

	$search_replace = array(
		'wptests_'                              => $wpt_table_prefix,
		'youremptytestdbnamehere'               => trim( getenv( 'WPT_DB_NAME' ) ),
		'yourusernamehere'                      => trim( getenv( 'WPT_DB_USER' ) ),
		'yourpasswordhere'                      => trim( getenv( 'WPT_DB_PASSWORD' ) ),
		'localhost'                             => trim( getenv( 'WPT_DB_HOST' ) ),
		'define( \'WP_PHP_BINARY\', \'php\' );' => $php_binary_string,
		$logger_replace_string                  => $system_logger,
	);

	$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );

	file_put_contents( $paths['prepare_dir'] . '/wp-tests-config.php', $contents );

	$php_version_cmd = $php['bin'] . " -r \"print PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;\"";

	if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		$php_version_cmd = 'ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] ) . ' ' . escapeshellarg( $php_version_cmd );
	}

	$retval          = 0;
	$env_php_version = exec( $php_version_cmd, $output, $retval );

	if ( 0 !== $retval ) {
		error_message( 'Could not retrieve the environment PHP Version for ' . $php['version'] . '.' );
	}

	log_message( 'Environment PHP Version: ' . $env_php_version );

	if ( version_compare( $env_php_version, '7.2', '<' ) ) {
		error_message( 'The test runner is not compatible with PHP < 7.2.' );
	}

	$composer_cmd  = 'cd ' . escapeshellarg( $paths['prepare_dir'] ) . ' && ';
	$retval        = 0;
	$composer_path = escapeshellarg( system( 'which composer', $retval ) );

	if ( 0 === $retval ) {
		$composer_cmd .= $composer_path . ' ';
	} else {
		log_message( 'Local Composer not found. Downloading latest stable ...' );

		perform_operations(
			array(
				'wget -O ' . escapeshellarg( $paths['prepare_dir'] . '/composer.phar' ) . ' https://getcomposer.org/composer-stable.phar',
			)
		);

		$composer_cmd .= $php['bin'] . ' composer.phar ';
	}

	perform_operations(
		array(
			$composer_cmd . 'config platform.php ' . escapeshellarg( $env_php_version ),
			$composer_cmd . 'update',
		)
	);

	if ( ! empty( $runner_vars['WPT_SSH_CONNECT'] ) ) {
		$rsync_options = '-r';

		if ( $runner_vars['WPT_DEBUG'] ) {
			$rsync_options = $rsync_options . 'v';
		}

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
					. ' --exclude="tests/*" --include="tests/phpunit/**"'
					. ' -e "ssh ' . $runner_vars['WPT_SSH_OPTIONS'] . '" '
					. escapeshellarg( trailingslashit( $paths['prepare_dir'] ) )
					. ' ' . escapeshellarg( $runner_vars['WPT_SSH_CONNECT'] . ':' . $paths['test_dir'] ),
			)
		);
	}
}

log_message( 'Success: Prepared environment.' );
