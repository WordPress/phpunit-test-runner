<?php
/**
 * WordPress PHPUnit Test Runner: Database cleanup script
 *
 * Drops every test table so the next run starts from a clean database. Stale
 * rows survive the test suite's own install step, because WordPress boots and
 * loads data such as user roles into memory before the tables are dropped,
 * and then writes that stale data back into the fresh tables. See issue #110.
 *
 * This script is standalone on purpose. It runs where the tests ran, which is
 * the remote test environment when an SSH connection is used, so it cannot
 * depend on functions.php or on any other file of this repository. It is fed
 * to the remote PHP binary on stdin:
 *
 *     ssh <host> 'php -- /path/to/test/dir' < cleanup-db.php
 *
 * or executed directly when the tests ran locally:
 *
 *     php cleanup-db.php /path/to/test/dir
 *
 * Database credentials are read from the wp-tests-config.php file inside the
 * given directory, exactly as the test suite read them, so no password ever
 * appears on a command line or in a process list. The file is parsed rather
 * than included, because prepare.php prepends a logger with side effects to
 * the generated configuration.
 *
 * Exit code 0 means every matching table was dropped. Exit code 1 means the
 * cleanup could not run or did not complete; the caller treats that as a
 * warning, not as a fatal error.
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 *
 * @package WordPress
 */

// phpcs:disable WordPress.DB.RestrictedFunctions, WordPress.DB.RestrictedClasses -- WordPress is not loaded in this standalone script, so $wpdb is unavailable by design and mysqli is used directly. See the file docblock.

// Report mysqli failures through return values on every PHP version rather
// than exceptions, which became the default in PHP 8.1.
mysqli_report( MYSQLI_REPORT_OFF );

/**
 * Writes a message to STDERR and exits with a failure code.
 *
 * @param string $message The message to display.
 */
function wpt_db_cleanup_fail( $message ) {
	fwrite( STDERR, 'Database cleanup: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Extracts a single-quoted or double-quoted PHP string literal assignment
 * from configuration file contents.
 *
 * Handles the formats produced by wp-tests-config-sample.php and prepare.php,
 * such as `define( 'DB_NAME', 'value' );` and `$table_prefix = 'wptests_';`.
 * Escaped quotes and backslashes inside the literal are unescaped.
 *
 * @param string $contents The configuration file contents.
 * @param string $pattern  Regular expression with the literal in group 2 and
 *                         its quote character in group 1.
 * @return string|null The parsed value, or null when not found.
 */
function wpt_db_cleanup_parse( $contents, $pattern ) {
	if ( 1 !== preg_match( $pattern, $contents, $matches ) ) {
		return null;
	}
	$quote = $matches[1];

	// Unescape in a single pass, so an escaped backslash is never rescanned.
	return strtr(
		$matches[2],
		array(
			'\\\\'        => '\\',
			'\\' . $quote => $quote,
		)
	);
}

/**
 * Reads one constant defined in the configuration file contents.
 *
 * @param string $contents The configuration file contents.
 * @param string $name     The constant name, for example 'DB_NAME'.
 * @return string|null The constant value, or null when not found.
 */
function wpt_db_cleanup_constant( $contents, $name ) {
	$name = preg_quote( $name, '/' );

	return wpt_db_cleanup_parse(
		$contents,
		'/^[ \t]*define\s*\(\s*[\'"]' . $name . '[\'"]\s*,\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*\)/m'
	);
}

/**
 * Splits a DB_HOST value into host, port and socket.
 *
 * This mirrors wpdb::parse_db_host(), so every DB_HOST notation the test
 * suite accepted is interpreted the same way here: `host`, `host:port`,
 * `host:/path/to/socket`, IPv6 addresses with or without brackets, and a
 * bracketed IPv6 address with a port.
 *
 * @param string $db_host The DB_HOST value from the configuration.
 * @return array|false Host, port (int|null) and socket (string|null), in
 *                     that order, or false when the value cannot be parsed.
 */
function wpt_db_cleanup_parse_host( $db_host ) {
	$socket = null;

	// First peel off the socket parameter from the right, if it exists.
	$socket_pos = strpos( $db_host, ':/' );
	if ( false !== $socket_pos ) {
		$socket  = substr( $db_host, $socket_pos + 1 );
		$db_host = substr( $db_host, 0, $socket_pos );
	}

	/*
	 * An IPv6 address will always contain at least two colons. Anything
	 * else is treated as IPv4 or a hostname, where at most one port
	 * suffix can follow.
	 */
	if ( substr_count( $db_host, ':' ) > 1 ) {
		$pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>[\d]+))?#';
	} else {
		$pattern = '#^(?P<host>[^:/]*)(?::(?P<port>[\d]+))?#';
	}

	if ( 1 !== preg_match( $pattern, $db_host, $matches ) ) {
		return false;
	}

	$host = ! empty( $matches['host'] ) ? $matches['host'] : '';
	$port = ! empty( $matches['port'] ) ? (int) $matches['port'] : null;

	return array( $host, $port, $socket );
}

if ( empty( $argv[1] ) ) {
	wpt_db_cleanup_fail( 'usage: php cleanup-db.php <test directory>' );
}

$config_path = rtrim( $argv[1], '/' ) . '/wp-tests-config.php';

if ( ! is_readable( $config_path ) ) {
	wpt_db_cleanup_fail( 'configuration file not found: ' . $config_path );
}

if ( ! extension_loaded( 'mysqli' ) ) {
	wpt_db_cleanup_fail( 'the mysqli extension is not available.' );
}

$config_contents = file_get_contents( $config_path );

$db_name = wpt_db_cleanup_constant( $config_contents, 'DB_NAME' );
$db_user = wpt_db_cleanup_constant( $config_contents, 'DB_USER' );
$db_pass = wpt_db_cleanup_constant( $config_contents, 'DB_PASSWORD' );
$db_host = wpt_db_cleanup_constant( $config_contents, 'DB_HOST' );
$prefix  = wpt_db_cleanup_parse(
	$config_contents,
	'/^[ \t]*\$table_prefix\s*=\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*;/m'
);

if ( null === $db_name || '' === $db_name || null === $db_user || '' === $db_user || null === $db_pass || null === $db_host || '' === $db_host ) {
	wpt_db_cleanup_fail( 'could not parse the database settings from ' . $config_path );
}

// Refuse to run with an empty prefix: the LIKE pattern would match every
// table in the database, not only the ones the test suite created.
if ( null === $prefix || '' === $prefix ) {
	wpt_db_cleanup_fail( 'could not determine a non-empty $table_prefix from ' . $config_path );
}

$parsed_host = wpt_db_cleanup_parse_host( $db_host );

if ( false === $parsed_host ) {
	wpt_db_cleanup_fail( 'could not parse the DB_HOST value: ' . $db_host );
}

list( $host, $port, $socket ) = $parsed_host;

if ( null !== $socket ) {
	$mysqli = new mysqli( $host, $db_user, $db_pass, $db_name, null === $port ? (int) ini_get( 'mysqli.default_port' ) : $port, $socket );
} elseif ( null !== $port ) {
	$mysqli = new mysqli( $host, $db_user, $db_pass, $db_name, $port );
} else {
	$mysqli = new mysqli( $host, $db_user, $db_pass, $db_name );
}

if ( $mysqli->connect_errno ) {
	wpt_db_cleanup_fail( 'could not connect to the database: ' . $mysqli->connect_error );
}

/*
 * Collect every base table whose name starts with the test table prefix.
 * This covers the tables of multisite sub-sites, such as wptests_2_posts,
 * and tables left behind by older WordPress branches or interrupted runs,
 * which a fixed list of current core tables would miss.
 *
 * LIKE wildcard characters in the prefix are escaped, so the underscore in
 * the default wptests_ prefix matches only a literal underscore.
 */
$statement = $mysqli->prepare(
	'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' AND TABLE_NAME LIKE ?'
);

if ( false === $statement ) {
	wpt_db_cleanup_fail( 'could not prepare the table lookup: ' . $mysqli->error );
}

$like = addcslashes( $prefix, '\\_%' ) . '%';
$statement->bind_param( 's', $like );

if ( ! $statement->execute() ) {
	wpt_db_cleanup_fail( 'could not list the test tables: ' . $statement->error );
}

$tables = array();
$table  = '';
$statement->bind_result( $table );

/*
 * fetch() returns true for a row, null when the rows are exhausted and
 * false on error. Treating an error as the end of the list would drop
 * only part of the tables while still reporting success.
 */
while ( true ) {
	$fetch_result = $statement->fetch();
	if ( true !== $fetch_result ) {
		break;
	}
	$tables[] = $table;
}

if ( false === $fetch_result ) {
	wpt_db_cleanup_fail( 'could not read the table list: ' . $statement->error );
}

$statement->close();

if ( array() === $tables ) {
	echo 'Database cleanup: no tables with prefix ' . $prefix . ' found.' . PHP_EOL;
	$mysqli->close();
	exit( 0 );
}

$mysqli->query( 'SET foreign_key_checks = 0' );

$failed = array();
foreach ( $tables as $table_name ) {
	$escaped = '`' . str_replace( '`', '``', $table_name ) . '`';
	if ( ! $mysqli->query( 'DROP TABLE ' . $escaped ) ) {
		$failed[] = $table_name . ' (' . $mysqli->error . ')';
	}
}

$mysqli->query( 'SET foreign_key_checks = 1' );
$mysqli->close();

if ( array() !== $failed ) {
	wpt_db_cleanup_fail( 'could not drop: ' . implode( ', ', $failed ) );
}

echo 'Database cleanup: dropped ' . count( $tables ) . ' tables with prefix ' . $prefix . '.' . PHP_EOL;
exit( 0 );
