<?php
/**
 * Runs the WordPress PHPUnit test suite on the Pantheon environment via Terminus.
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

// Determine the PHP binary from the PHP version Terminus reported.
// We derive it from WPT_PHP_EXECUTABLE if set, otherwise fall back to php8.1.
$php_bin = trim( getenv( 'WPT_PHP_EXECUTABLE' ) ) ?: 'php8.1';

// Uses the flavor (usually to test WordPress Multisite).
$WPT_FLAVOR_INI = trim( getenv( 'WPT_FLAVOR' ) );
switch ( $WPT_FLAVOR_INI ) {
	case 1:
		$WPT_FLAVOR_TXT = ' -c tests/phpunit/multisite.xml';
		break;
	default:
		$WPT_FLAVOR_TXT = '';
		break;
}
unset( $WPT_FLAVOR_INI );

// Uses the extra tests group (e.g., ajax, ms-files, external-http).
$WPT_EXTRATESTS_INI = trim( getenv( 'WPT_EXTRATESTS' ) );
switch ( $WPT_EXTRATESTS_INI ) {
	case 1:
		$WPT_EXTRATESTS_TXT = ' --group ajax';
		break;
	case 2:
		$WPT_EXTRATESTS_TXT = ' --group ms-files';
		break;
	case 3:
		$WPT_EXTRATESTS_TXT = ' --group external-http';
		break;
	default:
		$WPT_EXTRATESTS_TXT = '';
		break;
}
unset( $WPT_EXTRATESTS_INI );

$logs_dir  = $runner_vars['WPT_TEST_DIR'] . '/tests/phpunit/build/logs';
$junit_log = $logs_dir . '/junit.xml';
// PHPUnit runs as a subprocess (via passthru) whose writes don't persist to /code/ across
// terminus connections. Write to /tmp/ instead, then copy to /code/ from the parent process.
$junit_tmp   = '/tmp/wpt-junit.xml';
$testdox_tmp = '/tmp/wpt-testdox.txt';

// Build the PHPUnit shell command that will run on the Pantheon container.
// Optional scoping via workflow_dispatch inputs.
$WPT_TEST_FILTER = trim( getenv( 'WPT_TEST_FILTER' ) );
$WPT_TEST_GROUP  = trim( getenv( 'WPT_TEST_GROUP' ) );
$scope_txt = '';
if ( ! empty( $WPT_TEST_FILTER ) ) {
	$scope_txt = ' --filter ' . escapeshellarg( $WPT_TEST_FILTER );
} elseif ( ! empty( $WPT_TEST_GROUP ) ) {
	$scope_txt = ' --group ' . escapeshellarg( $WPT_TEST_GROUP );
}

$WPT_PHPUNIT_CMD = trim( getenv( 'WPT_PHPUNIT_CMD' ) );
if ( empty( $WPT_PHPUNIT_CMD ) ) {
	$WPT_PHPUNIT_CMD = 'cd ' . escapeshellarg( $runner_vars['WPT_TEST_DIR'] )
		. ' && ' . $php_bin . ' ./vendor/phpunit/phpunit/phpunit'
		. ' --dont-report-useless-tests'
		. ' --default-time-limit=60'
		. ' --log-junit ' . escapeshellarg( $junit_tmp )
		. ' --testdox-text ' . escapeshellarg( $testdox_tmp )
		. $WPT_FLAVOR_TXT
		. $WPT_EXTRATESTS_TXT
		. $scope_txt;
}

// After passthru() returns, copy files from /tmp/ (where the subprocess wrote them)
// to /code/ (parent-process write, persists across terminus connections).
$copy_php = '
if(file_exists(' . var_export( $junit_tmp, true ) . ')){
    @mkdir(' . var_export( $logs_dir, true ) . ', 0777, true);
    copy(' . var_export( $junit_tmp, true ) . ', ' . var_export( $junit_log, true ) . ');
    echo "Copied junit.xml (" . filesize(' . var_export( $junit_log, true ) . ') . " bytes)\n";
}
if(file_exists(' . var_export( $testdox_tmp, true ) . ')){
    copy(' . var_export( $testdox_tmp, true ) . ', ' . var_export( $logs_dir . '/testdox.txt', true ) . ');
    echo "Copied testdox.txt\n";
}
';

// Wrap in passthru() and execute on Pantheon via terminus remote:wp eval --skip-wordpress.
// passthru() streams PHPUnit's output in real time so progress is visible in GHA logs.
// Capture and propagate the exit code so terminus (and GHA) can see test failures/crashes.
$eval_code = '$c=0; passthru(' . var_export( $WPT_PHPUNIT_CMD, true ) . ', $c); ' . $copy_php . ' exit($c);';

perform_operations( array(
	'terminus remote:wp ' . $site_env . ' -- eval ' . escapeshellarg( $eval_code ) . ' --skip-wordpress',
) );
