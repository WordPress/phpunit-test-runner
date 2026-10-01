#!/usr/bin/env bash
#
# Runs the full test runner pipeline inside the container:
#
#   prepare.php -> test.php -> report.php -> cleanup.php
#
# The output of every step is written to "$WPT_OUTPUT_DIR/<step>.log", the
# junit.xml and env.json results are copied to "$WPT_OUTPUT_DIR", and a
# Markdown summary of the run is written to "$WPT_OUTPUT_DIR/summary.md".
#
# cleanup.php always runs. The test and report steps are skipped when the
# environment could not be prepared.
#
# The exit code is the exit code of the first step that failed, or 0. Set
# WPT_IGNORE_TEST_FAILURES=1 to only fail on test failures when no junit.xml
# results were produced, so failing WordPress tests do not fail the run.
#
# Pass a different command (for example `bash`) to run it instead.

set -u

if [ "$#" -gt 0 ]; then
	exec "$@"
fi

RUNNER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${WPT_OUTPUT_DIR:-/output}"
STATUS_FILE="${OUTPUT_DIR}/steps.tsv"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_DIR}"/*.log "${OUTPUT_DIR}/junit.xml" "${OUTPUT_DIR}/env.json" "${OUTPUT_DIR}/summary.md"
: > "${STATUS_FILE}"

cd "${RUNNER_DIR}" || exit 1

exit_code=0

# Records the outcome of a step in the status file.
#
# $1 Step name. $2 Status (passed, failed, skipped). $3 Exit code. $4 Seconds.
record() {
	printf '%s\t%s\t%s\t%s\n' "$1" "$2" "$3" "$4" >> "${STATUS_FILE}"
}

# Runs one of the runner scripts, saving its output and outcome.
#
# $1 Step name, matching the <step>.php script.
# $2 Pass "optional" to keep a failure from setting the exit code.
run_step() {
	local step="$1"
	local optional="${2:-}"
	local start=$SECONDS
	local code

	echo "==> ${step}.php"
	php "${step}.php" 2>&1 | tee "${OUTPUT_DIR}/${step}.log"
	code=${PIPESTATUS[0]}

	if [ "${code}" -eq 0 ]; then
		record "${step}" passed 0 $(( SECONDS - start ))
	else
		echo "Error: ${step}.php exited with code ${code}." >&2
		record "${step}" failed "${code}" $(( SECONDS - start ))
		if [ "${exit_code}" -eq 0 ] && [ "${optional}" != optional ]; then
			exit_code=${code}
		fi
	fi

	return "${code}"
}

# Waits for the database server to accept connections with the configured credentials.
wait_for_database() {
	local attempts="${WPT_DB_WAIT_ATTEMPTS:-60}"
	local i

	echo "Waiting for the database at ${WPT_DB_HOST:-localhost} ..."
	for (( i = 1; i <= attempts; i++ )); do
		if php -r '
			mysqli_report( MYSQLI_REPORT_OFF );
			$host = getenv( "WPT_DB_HOST" ) ?: "localhost";
			$port = null;
			if ( preg_match( "/^([^:]+):([0-9]+)$/", $host, $m ) ) {
				$host = $m[1];
				$port = (int) $m[2];
			}
			$db = @mysqli_connect( $host, getenv( "WPT_DB_USER" ), getenv( "WPT_DB_PASSWORD" ), getenv( "WPT_DB_NAME" ), $port );
			exit( $db ? 0 : 1 );
		'; then
			echo "Database is ready."
			return 0
		fi
		sleep 2
	done

	echo "Error: the database did not become available after ${attempts} attempts." >&2
	return 1
}

# Copies junit.xml and env.json out of the prepare directory before cleanup removes it.
collect_results() {
	local prepare_dir="${WPT_PREPARE_DIR:-/tmp/wp-test-runner}"
	local logs_dir="${prepare_dir}/tests/phpunit/build/logs"

	if [ -f "${prepare_dir}/junit.xml" ]; then
		cp "${prepare_dir}/junit.xml" "${OUTPUT_DIR}/junit.xml"
	elif [ -f "${logs_dir}/junit.xml" ]; then
		cp "${logs_dir}/junit.xml" "${OUTPUT_DIR}/junit.xml"
	fi

	if [ -f "${logs_dir}/env.json" ]; then
		cp "${logs_dir}/env.json" "${OUTPUT_DIR}/env.json"
	fi
}

if [ -z "${WPT_SSH_CONNECT:-}" ] && ! wait_for_database; then
	record database failed 1 0
	exit_code=1
fi

if [ "${exit_code}" -eq 0 ] && run_step prepare; then
	if [ -n "${WPT_IGNORE_TEST_FAILURES:-}" ] && [ "${WPT_IGNORE_TEST_FAILURES}" != 0 ]; then
		run_step test optional
	else
		run_step test
	fi
	run_step report
else
	record test skipped - 0
	record report skipped - 0
fi

collect_results

if [ "${exit_code}" -eq 0 ] && grep -q $'^test\tfailed' "${STATUS_FILE}" && [ ! -s "${OUTPUT_DIR}/junit.xml" ]; then
	echo "Error: the tests failed without producing junit.xml results." >&2
	exit_code=1
fi

run_step cleanup

php "${RUNNER_DIR}/docker/summarize.php" "${OUTPUT_DIR}" > "${OUTPUT_DIR}/summary.md"
cat "${OUTPUT_DIR}/summary.md"

exit "${exit_code}"
