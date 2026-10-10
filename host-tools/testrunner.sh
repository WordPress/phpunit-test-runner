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
# Example cron entry (every 4 hours). Replace /path/to/phpunit-test-runner with
# the directory of your clone. The script finds the runner from its own location,
# so it does not need to be started from that directory. Cron starts with a short
# PATH, so set one that finds npm and composer:
#   PATH=/usr/local/bin:/usr/bin:/bin
#   0 */4 * * * /path/to/phpunit-test-runner/host-tools/testrunner.sh >> /path/to/testrunner.log 2>&1
#
# Optional environment variables:
#   WPT_RUNNER_DIR   Path to the runner directory. Default: the parent of this script's directory.
#   WPT_SKIP_UPDATE  Set to 1 to skip "git pull" (for example, when you test local changes).
#
# The PHP binary comes from WPT_PHP_EXECUTABLE in .env (default: php), the
# same setting that the runner uses to run the tests. With WPT_SSH_CONNECT set,
# WPT_PHP_EXECUTABLE is the PHP on the remote test host, so the runner steps run
# with the local php.
#
# Exit status: 0 when every step succeeds, 1 when any step fails.

set -uo pipefail

RUNNER_DIR="${WPT_RUNNER_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

cd "$RUNNER_DIR" || { echo "Error: cannot open runner directory: $RUNNER_DIR" >&2; exit 1; }

if [[ ! -f .env ]]; then
	echo "Error: $RUNNER_DIR/.env not found. Copy .env.default to .env and configure it first." >&2
	exit 1
fi

# Stop when another run of this checkout is still active, for example a slow run
# that cron starts again. Other checkouts on the same host can run at the same time.
if command -v flock >/dev/null 2>&1; then
	exec 9>"${TMPDIR:-/tmp}/wpt-testrunner-$(id -u)-$(pwd -P | cksum | cut -d' ' -f1).lock"
	if ! flock -n 9; then
		echo "Another test run is still active. Stopping." >&2
		exit 1
	fi
fi

if [[ "${WPT_SKIP_UPDATE:-0}" != "1" ]]; then
	git pull --ff-only origin master || echo "Warning: could not update the runner. Continuing with the current version." >&2
fi

# A .env line may use a variable that is not set. The runner allows that.
set +u
# shellcheck source=/dev/null
source .env
set -u

# With WPT_SSH_CONNECT set, WPT_PHP_EXECUTABLE is the PHP on the remote test
# host, so the runner itself runs with the local php, as in the README.
if [[ -n "${WPT_SSH_CONNECT:-}" ]]; then
	PHP=( php )
else
	read -r -a PHP <<< "${WPT_PHP_EXECUTABLE:-php}"
fi

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
