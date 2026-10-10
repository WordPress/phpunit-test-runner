#!/usr/bin/env bash
#
# Set up the test runner: check the requirements, create .env and test the
# database connection.
#
# Run this from your clone of the runner:
#
#   git clone https://github.com/WordPress/phpunit-test-runner.git
#   cd phpunit-test-runner
#   ./host-tools/setup.sh
#
# The script asks for each value. Press Enter to keep the value in brackets.
# Values come from the environment first, then from the current .env (if it
# exists), then from built-in defaults. Running the script again keeps the
# settings that it does not ask about, such as the SSH settings.
#
# Options:
#   --non-interactive  Do not ask. Use values from the environment and the current .env.
#                      Example: WPT_DB_NAME=wptests WPT_DB_USER=wptests WPT_DB_PASSWORD=secret \
#                               ./host-tools/setup.sh --non-interactive
#   --skip-checks      Do not check the required software.
#   --skip-db-check    Do not test the database connection.
#
# The script does not install software, create the database, or set up cron.

set -uo pipefail

RUNNER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$RUNNER_DIR/.env"
ENV_DEFAULT="$RUNNER_DIR/.env.default"

INTERACTIVE=1
CHECK_SOFTWARE=1
CHECK_DB=1

for arg in "$@"; do
	case "$arg" in
		--non-interactive) INTERACTIVE=0 ;;
		--skip-checks) CHECK_SOFTWARE=0 ;;
		--skip-db-check) CHECK_DB=0 ;;
		-h|--help) sed -n '3,24p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) echo "Error: unknown option: $arg" >&2; exit 1 ;;
	esac
done

if [[ ! -f "$ENV_DEFAULT" ]]; then
	echo "Error: $ENV_DEFAULT not found. Run this script from a clone of the runner." >&2
	exit 1
fi

if [[ $EUID -eq 0 ]]; then
	echo "Warning: you are root. Run the tests as a non-root user (see the Requirements section in the README)." >&2
fi

# --- Requirements -----------------------------------------------------------

if [[ $CHECK_SOFTWARE -eq 1 ]]; then
	echo "Checking the required software..."
	missing=()
	for cmd in php git rsync wget unzip node npm; do
		if command -v "$cmd" >/dev/null 2>&1; then
			echo "  found:   $cmd"
		else
			echo "  missing: $cmd"
			missing+=("$cmd")
		fi
	done
	if command -v composer >/dev/null 2>&1; then
		echo "  found:   composer"
	else
		echo "  not found: composer (optional, prepare.php downloads composer.phar)"
	fi
	if [[ ${#missing[@]} -gt 0 ]]; then
		echo "Error: install the missing software, then run this script again: ${missing[*]}" >&2
		echo "See the Requirements section in the README." >&2
		exit 1
	fi
	echo "  Node.js $(node --version), npm $(npm --version)."
	echo "  Use the versions in the \"engines\" field of wordpress-develop's package.json."
fi

# --- Current values ---------------------------------------------------------

# The variables that this script sets. All other lines of the current .env
# (or of .env.default, the first time) are kept.
VARS=(WPT_PREPARE_DIR WPT_TEST_DIR WPT_DB_NAME WPT_DB_USER WPT_DB_PASSWORD WPT_DB_HOST WPT_REPORT_API_KEY WPT_PHP_EXECUTABLE)

# Values from the environment win over the current .env.
for var in "${VARS[@]}"; do
	if [[ -n "${!var+set}" ]]; then
		printf -v "ENV_$var" '%s' "${!var}"
	fi
done
if [[ -f "$ENV_FILE" ]]; then
	# A .env line may use a variable that is not set. The runner allows that.
	set +u
	# shellcheck source=/dev/null
	source "$ENV_FILE"
	set -u
fi
for var in "${VARS[@]}"; do
	env_name="ENV_$var"
	if [[ -n "${!env_name+set}" ]]; then
		printf -v "$var" '%s' "${!env_name}"
	fi
done

# Defaults for a local run. WPT_TEST_DIR must be the same as WPT_PREPARE_DIR when
# the tests run on this server (check_required_env() in functions.php).
WPT_PREPARE_DIR="${WPT_PREPARE_DIR:-/tmp/wp-test-runner}"
WPT_TEST_DIR="${WPT_TEST_DIR:-$WPT_PREPARE_DIR}"
WPT_DB_HOST="${WPT_DB_HOST:-localhost}"
WPT_PHP_EXECUTABLE="${WPT_PHP_EXECUTABLE:-php}"

# ask VAR "Question" [secret]
ask() {
	local var="$1" question="$2" secret="${3:-}" current="${!1:-}" shown answer
	shown="$current"
	if [[ -n "$secret" && -n "$current" ]]; then
		shown="(hidden)"
	fi
	if [[ -n "$secret" && -t 0 ]]; then
		read -r -s -p "$question [$shown]: " answer
		echo
	else
		read -r -p "$question [$shown]: " answer
	fi
	if [[ -n "$answer" ]]; then
		printf -v "$var" '%s' "$answer"
	fi
}

if [[ $INTERACTIVE -eq 1 ]]; then
	echo
	echo "Enter the values for .env. Press Enter to keep the value in brackets."
	echo "WARNING: the test suite deletes and creates tables in this database. Use a database that is only for the tests."
	ask WPT_DB_NAME "Database name"
	ask WPT_DB_USER "Database user"
	ask WPT_DB_PASSWORD "Database password" secret
	ask WPT_DB_HOST "Database host (host, host:port or localhost:/path/to/socket)"
	ask WPT_PHP_EXECUTABLE "PHP executable"
	ask WPT_PREPARE_DIR "Directory for the WordPress checkout"
	echo "To report results to make.wordpress.org, enter the bot user and application password as user:password."
	echo "Leave it empty to run the tests without reporting."
	ask WPT_REPORT_API_KEY "Report API key" secret
fi

# A run on this server needs WPT_TEST_DIR to be the same as WPT_PREPARE_DIR
# (check_required_env() in functions.php). With WPT_SSH_CONNECT set,
# WPT_TEST_DIR is the directory on the remote server, so it is kept.
if [[ -z "${WPT_SSH_CONNECT:-}" ]]; then
	WPT_TEST_DIR="$WPT_PREPARE_DIR"
fi

for var in WPT_DB_NAME WPT_DB_USER WPT_DB_HOST; do
	if [[ -z "${!var:-}" ]]; then
		echo "Error: $var is empty." >&2
		exit 1
	fi
done

# --- Database connection ----------------------------------------------------

if [[ $CHECK_DB -eq 1 && -n "${WPT_SSH_CONNECT:-}" ]]; then
	echo
	echo "WPT_SSH_CONNECT is set, so the tests connect to the database from $WPT_SSH_CONNECT. Skipping the database check."
elif [[ $CHECK_DB -eq 1 ]]; then
	echo
	echo "Testing the database connection..."
	read -r -a PHP <<< "$WPT_PHP_EXECUTABLE"
	if ! WPT_RUNNER_FUNCTIONS="$RUNNER_DIR/functions.php" WPT_DB_NAME="$WPT_DB_NAME" WPT_DB_USER="$WPT_DB_USER" WPT_DB_PASSWORD="${WPT_DB_PASSWORD:-}" WPT_DB_HOST="$WPT_DB_HOST" \
		"${PHP[@]}" -r '
			require getenv( "WPT_RUNNER_FUNCTIONS" );
			if ( ! extension_loaded( "mysqli" ) ) {
				fwrite( STDERR, "The mysqli PHP extension is not loaded.\n" );
				exit( 1 );
			}
			$db = wpt_runner_parse_db_host( getenv( "WPT_DB_HOST" ) );
			if ( false === $db ) {
				fwrite( STDERR, "WPT_DB_HOST is not valid.\n" );
				exit( 1 );
			}
			mysqli_report( MYSQLI_REPORT_OFF );
			$link = @mysqli_connect( $db["host"], getenv( "WPT_DB_USER" ), getenv( "WPT_DB_PASSWORD" ), getenv( "WPT_DB_NAME" ), $db["port"] ? $db["port"] : 0, $db["socket"] ? $db["socket"] : "" );
			if ( ! $link ) {
				fwrite( STDERR, mysqli_connect_error() . "\n" );
				exit( 1 );
			}
			echo "  Connected to " . mysqli_get_server_info( $link ) . " with PHP " . PHP_VERSION . ".\n";
		' 2>&1; then
		echo "Error: cannot connect to the database. .env was not changed." >&2
		echo "Check the values, or use --skip-db-check to save them anyway." >&2
		exit 1
	fi
fi

# --- Write .env -------------------------------------------------------------

if [[ -f "$ENV_FILE" ]]; then
	backup="$ENV_FILE.bak-$(date +%Y%m%d%H%M%S)"
	cp -p "$ENV_FILE" "$backup"
	chmod 600 "$backup"
	echo "Saved the old .env as $backup"
fi

# Quote a value for a shell file: 'value', with each ' written as '\''.
shell_quote() {
	printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"
}

# Start from the current .env, so the settings that this script does not ask
# about (SSH, report URL, debug, and so on) are kept.
template="$ENV_DEFAULT"
if [[ -f "$ENV_FILE" ]]; then
	template="$ENV_FILE"
fi

tmp="$(mktemp "$RUNNER_DIR/.env.XXXXXX")"
chmod 600 "$tmp"
written=" "
while IFS= read -r line || [[ -n "$line" ]]; do
	replaced=0
	for var in "${VARS[@]}"; do
		if [[ "$line" == "export $var="* ]]; then
			printf 'export %s=%s\n' "$var" "$(shell_quote "${!var:-}")"
			written="$written$var "
			replaced=1
			break
		fi
	done
	if [[ $replaced -eq 0 ]]; then
		printf '%s\n' "$line"
	fi
done < "$template" > "$tmp"
# Add the variables that the current .env does not have yet.
for var in "${VARS[@]}"; do
	if [[ "$written" != *" $var "* ]]; then
		printf 'export %s=%s\n' "$var" "$(shell_quote "${!var:-}")" >> "$tmp"
	fi
done
mv "$tmp" "$ENV_FILE"

echo "Saved $ENV_FILE (readable only by you)."
echo
echo "Next steps:"
echo "  1. Run the tests once:"
if [[ -f "$RUNNER_DIR/host-tools/testrunner.sh" ]]; then
	echo "       $RUNNER_DIR/host-tools/testrunner.sh"
else
	echo "       cd $RUNNER_DIR && source .env && php prepare.php && php test.php; php report.php; php cleanup.php"
fi
echo "  2. Schedule the run, for example with cron. See \"Automatic running\" in the README."
if [[ -z "${WPT_REPORT_API_KEY:-}" ]]; then
	echo "  3. To report results, create a bot user. See https://make.wordpress.org/hosting/handbook/tests/"
fi
