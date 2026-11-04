<?php
/**
 * WordPress PHPUnit Test Runner: Container run summary
 *
 * Builds a Markdown summary of a containerized run from the files the
 * entrypoint writes to the output directory:
 *
 * - steps.tsv: one "step, status, exit code, seconds" line per step.
 * - <step>.log: the output of each step.
 * - junit.xml: the PHPUnit results, when the tests ran.
 *
 * The summary is printed to STDOUT so it can be used as a GitHub Actions job
 * summary or as the body of a pull request comment.
 *
 * Usage: php docker/summarize.php <output-dir>
 *
 * @link https://github.com/wordpress/phpunit-test-runner/ Original source repository
 *
 * @package WordPress
 */

// Maximum number of failed tests to list individually.
const WPT_SUMMARY_MAX_FAILURES = 50;

// Number of trailing log lines shown for a failed step.
const WPT_SUMMARY_LOG_LINES = 40;

$output_dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '/output';

/**
 * Reads the step outcomes recorded by the entrypoint.
 *
 * @param string $file Path to steps.tsv.
 *
 * @return array[] List of steps, each with name, status, code, and seconds.
 */
function wpt_summary_read_steps( $file ) {
	$steps = array();
	if ( ! is_readable( $file ) ) {
		return $steps;
	}

	foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$parts = explode( "\t", $line );
		if ( 4 !== count( $parts ) ) {
			continue;
		}
		$steps[] = array(
			'name'    => $parts[0],
			'status'  => $parts[1],
			'code'    => $parts[2],
			'seconds' => (int) $parts[3],
		);
	}

	return $steps;
}

/**
 * Collects the totals and failed tests from a junit.xml file.
 *
 * @param string $file Path to junit.xml.
 *
 * @return array|null Totals and failures, or null when the file is unusable.
 */
function wpt_summary_read_junit( $file ) {
	if ( ! is_readable( $file ) || 0 === filesize( $file ) ) {
		return null;
	}

	libxml_use_internal_errors( true );
	$xml = simplexml_load_file( $file );
	if ( false === $xml ) {
		return null;
	}

	$suite = isset( $xml->testsuite ) ? $xml->testsuite[0] : $xml;

	$result = array(
		'tests'    => (int) $suite['tests'],
		'failures' => (int) $suite['failures'],
		'errors'   => (int) $suite['errors'],
		'skipped'  => (int) $suite['skipped'],
		'time'     => (float) $suite['time'],
		'failed'   => array(),
	);

	foreach ( $xml->xpath( '//testcase[failure or error]' ) as $testcase ) {
		$problem = isset( $testcase->failure ) ? $testcase->failure : $testcase->error;
		$message = trim( (string) $problem['message'] );
		if ( '' === $message ) {
			// PHPUnit puts the test name on the first line, followed by the assertion message.
			$lines   = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $problem ) ) ) );
			$message = isset( $lines[1] ) ? $lines[1] : ( isset( $lines[0] ) ? $lines[0] : '' );
		}

		$result['failed'][] = array(
			'name'    => (string) $testcase['class'] . '::' . (string) $testcase['name'],
			'message' => $message,
		);
	}

	return $result;
}

/**
 * Returns the last lines of a file.
 *
 * @param string $file  Path to the file.
 * @param int    $count Number of lines.
 *
 * @return string The trailing lines, or an empty string.
 */
function wpt_summary_tail( $file, $count ) {
	if ( ! is_readable( $file ) ) {
		return '';
	}

	$lines = file( $file, FILE_IGNORE_NEW_LINES );

	return implode( "\n", array_slice( $lines, -$count ) );
}

/**
 * Escapes a value for use inside a Markdown table cell.
 *
 * @param string $value The value.
 *
 * @return string The escaped value.
 */
function wpt_summary_cell( $value ) {
	$value = preg_replace( '/\s+/', ' ', (string) $value );

	return str_replace( '|', '\|', $value );
}

$steps  = wpt_summary_read_steps( $output_dir . '/steps.tsv' );
$junit  = wpt_summary_read_junit( $output_dir . '/junit.xml' );
$failed = array_filter(
	$steps,
	function ( $step ) {
		return 'failed' === $step['status'];
	}
);
$icons  = array(
	'passed'  => '✅',
	'failed'  => '❌',
	'skipped' => '⏭️',
);

$php_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
$db_label    = getenv( 'WPT_DB_LABEL' );

if ( empty( $failed ) ) {
	$heading = '✅ passed';
} elseif ( null !== $junit && array( 'test' ) === array_values( array_column( $failed, 'name' ) ) ) {
	// Only the tests failed, and they produced results to look at.
	$heading = '⚠️ runner passed, some tests failed';
} else {
	$heading = '❌ runner failed';
}

echo '## PHPUnit Test Runner: ' . $heading . "\n\n";
echo '- PHP: `' . $php_version . "`\n";
if ( $db_label ) {
	echo '- Database: `' . $db_label . "`\n";
}
echo "\n";

echo "| Step | Status | Exit code | Duration |\n";
echo "| --- | --- | --- | --- |\n";
foreach ( $steps as $step ) {
	$icon = isset( $icons[ $step['status'] ] ) ? $icons[ $step['status'] ] : '';
	printf(
		"| %s | %s %s | %s | %ds |\n",
		wpt_summary_cell( $step['name'] ),
		$icon,
		wpt_summary_cell( $step['status'] ),
		wpt_summary_cell( $step['code'] ),
		$step['seconds']
	);
}
echo "\n";

if ( null !== $junit ) {
	echo "### Test results\n\n";
	echo "| Tests | Failures | Errors | Skipped | Time |\n";
	echo "| --- | --- | --- | --- | --- |\n";
	printf(
		"| %d | %d | %d | %d | %ds |\n\n",
		$junit['tests'],
		$junit['failures'],
		$junit['errors'],
		$junit['skipped'],
		$junit['time']
	);

	if ( ! empty( $junit['failed'] ) ) {
		echo '<details><summary>Failed tests (' . count( $junit['failed'] ) . ")</summary>\n\n";
		echo "| Test | Message |\n";
		echo "| --- | --- |\n";
		foreach ( array_slice( $junit['failed'], 0, WPT_SUMMARY_MAX_FAILURES ) as $test ) {
			echo '| `' . wpt_summary_cell( $test['name'] ) . '` | ' . wpt_summary_cell( $test['message'] ) . " |\n";
		}
		if ( count( $junit['failed'] ) > WPT_SUMMARY_MAX_FAILURES ) {
			echo "\n_Showing the first " . WPT_SUMMARY_MAX_FAILURES . ' failures. See junit.xml for the full list._' . "\n";
		}
		echo "\n</details>\n\n";
	}
} else {
	echo "_No junit.xml results were produced._\n\n";
}

foreach ( $failed as $step ) {
	$tail = wpt_summary_tail( $output_dir . '/' . $step['name'] . '.log', WPT_SUMMARY_LOG_LINES );
	if ( '' === $tail ) {
		continue;
	}

	echo '<details><summary>Last ' . WPT_SUMMARY_LOG_LINES . ' lines of ' . $step['name'] . ".log</summary>\n\n";
	echo "```\n" . str_replace( '```', "'''", $tail ) . "\n```\n\n";
	echo "</details>\n\n";
}
