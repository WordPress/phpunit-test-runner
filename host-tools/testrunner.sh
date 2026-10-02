#!/usr/bin/env bash
#
# Run the full test cycle in one step: update the runner, prepare, test,
# report and clean up.
#
# Use this after the runner is set up and its .env file is configured
# (database credentials, and WPT_REPORT_API_KEY if you report results).
#
# Usage:
#   ./host-tools/testrunner.sh
#
# Example cron entry (every 4 hours):
#   0 */4 * * * /home/wptestrunner/phpunit-test-runner/host-tools/testrunner.sh >> /home/wptestrunner/testrunner.log 2>&1
#
# Optional environment variables:
#   WPT_RUNNER_DIR   Path to the runner directory. Default: the parent of this script's directory.
#   WPT_SKIP_UPDATE  Set to 1 to skip "git pull" (for example, when you test local changes).
#
# The PHP binary comes from WPT_PHP_EXECUTABLE in .env (default: php), the
# same setting that the runner uses to run the tests.
#
# Exit status: 0 when every step succeeds, 1 when any step fails.

set -uo pipefail

RUNNER_DIR="${WPT_RUNNER_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

cd "$RUNNER_DIR" || { echo "Error: cannot open runner directory: $RUNNER_DIR" >&2; exit 1; }

if [[ ! -f .env ]]; then
	echo "Error: $RUNNER_DIR/.env not found. Copy .env.default to .env and configure it first." >&2
	exit 1
fi

# Stop when another run is still active, for example a slow run that cron starts again.
if command -v flock >/dev/null 2>&1; then
	exec 9>"${TMPDIR:-/tmp}/wpt-testrunner-$(id -u).lock"
	if ! flock -n 9; then
		echo "Another test run is still active. Stopping." >&2
		exit 1
	fi
fi

if [[ "${WPT_SKIP_UPDATE:-0}" != "1" ]]; then
	git pull --ff-only origin master || echo "Warning: could not update the runner. Continuing with the current version." >&2
fi

# shellcheck source=/dev/null
source .env

read -r -a PHP <<< "${WPT_PHP_EXECUTABLE:-php}"

status=0

# Always clean up, also when a step fails or the run is stopped.
trap '"${PHP[@]}" cleanup.php || status=1' EXIT

if ! "${PHP[@]}" prepare.php; then
	echo "Error: prepare.php failed. Skipping the tests and the report." >&2
	exit 1
fi

# test.php exits with a non-zero status when a test fails.
# Run the report anyway, so that the failures are reported.
"${PHP[@]}" test.php || status=1
"${PHP[@]}" report.php || status=1

trap - EXIT
"${PHP[@]}" cleanup.php || status=1

exit "$status"
