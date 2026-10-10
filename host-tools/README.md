# Host tools

Ready-made scripts for hosts that run the test runner on their own servers. Use them in place of writing your own setup and cron scripts.

| Script | What it does |
|---|---|
| [`setup.sh`](setup.sh) | Checks the required software, creates `.env` with prompts, and tests the database connection. |
| [`testrunner.sh`](testrunner.sh) | Runs the full test cycle in one step: update, prepare, test, report and clean up. |

The scripts do not change the runner itself. They use the same `.env` file and the same PHP files (`prepare.php`, `test.php`, `report.php`, `cleanup.php`) as a manual setup.

## Before you start

You need:

- A Linux server with the usual configuration you give to your customers.
- A non-root user to run the tests.
- A database and a database user that are only for the tests. The test suite deletes and creates tables in this database.
- The software in the [Requirements](../README.md#requirements) section of the main README: PHP, Git, rsync, wget, unzip, Node.js and npm. Composer is optional.

The scripts do not install software, create the database, or set up cron.

## Set up and run the tests

1. Clone the runner as the test user:

   ```bash
   git clone https://github.com/WordPress/phpunit-test-runner.git
   cd phpunit-test-runner
   ```

2. Create `.env`:

   ```bash
   ./host-tools/setup.sh
   ```

   The script asks for the database credentials, the PHP executable, the checkout directory and the report API key. Press Enter to keep the value in brackets. If it cannot connect to the database, it stops and does not change `.env`.

3. Run the tests once and read the output:

   ```bash
   ./host-tools/testrunner.sh
   ```

   A run can take a long time. Each run clones WordPress and installs the npm and Composer dependencies again.

4. Run the tests on a schedule. For example, a cron entry for every 4 hours. Replace `/path/to/phpunit-test-runner` with the directory of your clone:

   ```
   PATH=/usr/local/bin:/usr/bin:/bin
   0 */4 * * * /path/to/phpunit-test-runner/host-tools/testrunner.sh >> /path/to/testrunner.log 2>&1
   ```

   Cron starts with a short `PATH`, and `prepare.php` calls `npm` and `composer`. Set a `PATH` that finds them.

   The script finds the runner from its own location, so cron does not need to change to that directory first.

To report results to the [Host Test Results](https://make.wordpress.org/hosting/test-results/) page, you need a bot user with the "Test Reporter" role. See [How to report](https://make.wordpress.org/hosting/handbook/tests/). After you get the application password, run `./host-tools/setup.sh` again and enter it as `botuser:application password`.

## setup.sh

```bash
./host-tools/setup.sh [--non-interactive] [--skip-checks] [--skip-db-check]
```

| Option | Effect |
|---|---|
| `--non-interactive` | Do not ask. Use values from the environment and the current `.env`. For provisioning tools. |
| `--skip-checks` | Do not check the required software. |
| `--skip-db-check` | Do not test the database connection. |

- Values come from the environment first, then from the current `.env`, then from the defaults.
- For a run on this server, the script sets `WPT_TEST_DIR` to the same directory as `WPT_PREPARE_DIR`, which the runner requires. With `WPT_SSH_CONNECT` set, it keeps `WPT_TEST_DIR`, the directory on the remote server.
- Settings that the script does not ask about, like the SSH settings, stay as they are in the current `.env`.
- It saves the old `.env` as `.env.bak-<date>`. The new `.env` and the backup are readable only by their owner.

Example for a provisioning tool:

```bash
WPT_DB_NAME=wptests WPT_DB_USER=wptests WPT_DB_PASSWORD=secret \
WPT_REPORT_API_KEY='botuser:application password' \
./host-tools/setup.sh --non-interactive
```

## testrunner.sh

```bash
./host-tools/testrunner.sh
```

1. Stops if `.env` does not exist.
2. Stops if another run of the same checkout is still active. This uses `flock`, which most Linux systems have. Without `flock`, the script does not check for other runs.
3. Updates the runner with `git pull --ff-only origin master`. If the update fails, the script shows a warning and continues.
4. Runs `prepare.php`, `test.php`, `report.php` and `cleanup.php` with the PHP executable from `WPT_PHP_EXECUTABLE`. With `WPT_SSH_CONNECT` set, `WPT_PHP_EXECUTABLE` is the PHP on the remote server, so these run with the local `php`.

Behavior when a step fails:

- When a test fails, the script still runs the report, so the failure shows on the results page.
- When `prepare.php` fails, the script skips the tests and the report.
- The script always runs `cleanup.php`, also when a step fails or the run is stopped.
- The exit status is `0` when every step succeeds and `1` when any step fails.

| Environment variable | Effect |
|---|---|
| `WPT_RUNNER_DIR` | The runner directory. Default: the directory above `host-tools/`. |
| `WPT_SKIP_UPDATE=1` | Skip `git pull`, for example when you test local changes. |

## Contributing

- Keep the scripts in Bash, and do not change the runner's PHP files from them.
- Test changes on a Linux server, with a successful run and with a failed step.
- Update this README when you add a script or an option.
