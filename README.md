# PHPUnit Test Runner

Thanks for running the WordPress PHPUnit test suite on your infrastructure. We appreciate you helping to ensure WordPress’s compatibility for your users.

If you haven't already, [please first read through the "Getting Started" documentation](https://make.wordpress.org/hosting/handbook/tests/).

The test suite runner is designed to be used without any file modification. Configuration happens with a series of environment variables (see [.env.default](.env.default) for an annotated overview).

At a high level, the test suite runner:

1. Prepares the test environment for the test suite.
2. Runs the PHPUnit tests in the test environment.
3. Reports the PHPUnit test results to WordPress.org
4. Cleans up the test suite environment.

## Setup

The test suite runner can be used in one of two ways:

1. With GitHub Actions, (or Travis, Circle, or another CI service) as the controller that connects to the remote test environment.
2. With the runner cloned to and run directly within the test environment.

The test runner is configured through environment variables, documented in [`.env.default`](.env.default). It shouldn't need any code modifications; in fact, please refrain from editing the scripts entirely, as it will make it easier to stay up to date.

With a direct Git clone, you can:

```bash
# Copy the default .env file.
cp .env.default .env
# Edit the .env file to define your variables.
vim .env
# Load your variables into scope.
source .env
```

In a CI service, you can set these environment variables through the service's web console. Importantly, the `WPT_SSH_CONNECT` environment variable determines whether the test suite is run locally or against a remote environment.

Concurrently run tests in the same environment by appending build ids to the test directory and table prefix:

```bash
export WPT_TEST_DIR=wp-test-runner-$TRAVIS_BUILD_NUMBER
export WPT_TABLE_PREFIX=wptests_$TRAVIS_BUILD_NUMBER\_
```

Connect to a remote environment over SSH by having the CI job provision the SSH key:

```bash
# 1. Create a SSH key pair for the controller to use
ssh-keygen -t rsa -b 4096 -C "travis@travis-ci.org"
# 2. base64 encode the private key for use with the environment variable
cat ~/.ssh/id_rsa | base64 --wrap=0
# 3. Append id_rsa.pub to authorized_keys so the CI service can SSH in
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys
```

Use a more complex SSH connection process by creating an SSH alias:

```bash
# 1. Add the following to ~/.ssh/config to create a 'wpt' alias
Host wpt
  Hostname 123.45.67.89
  User wpt
  Port 1234
# 2. Use 'wpt' wherever you might normally use a SSH connection string
ssh wpt
```

## Running

The test suite runner is run in four steps. This explanation is for the local execution.

### Requirements

To use the Runner, the following is required (testing WordPress 6.5):

- Server / hosting (infrastructure) with the usual configuration you use
- A database where you can test (tables will be created and destroyed several times)
- PHP 7.0+ (view )
- MySQL 5.5+ / MariaDB 10.0+
- NodeJS 20.x / npm 10.x / grunt
- PHP Composer
- Git, RSync, WGet, UnZip

Test environment:

- Writable filesystem for the entire test directory (see [#40910](https://core.trac.wordpress.org/ticket/40910)).
- Run with a non-root user, both for security and practical purposes (see [#44233](https://core.trac.wordpress.org/ticket/44233#comment:34)/[#46577](https://core.trac.wordpress.org/ticket/46577)).

#### Database creation

_This is an example for MySQL / MariaDB._

```sql
CREATE DATABASE wordpressdatabase CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;
GRANT ALL ON wordpressdatabase.* TO 'wordpressusername'@'localhost' IDENTIFIED BY 'wordpresspassword';
GRANT ALL ON wordpressdatabase.* TO 'wordpressusername'@'127.0.0.1' IDENTIFIED BY 'wordpresspassword';
FLUSH PRIVILEGES;
```

#### NodeJS installation

_This is an example for Debian / Ubuntu._

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt -y install nodejs
sudo npm install -g npm@latest
nodejs --version
npm --version
```

#### PHP Composer

_This is an example for Debian / Ubuntu._

```bash
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

#### Git

_This is an example for Debian / Ubuntu._

```bash
apt -y install git
git --version
```

### Installing the Test Runner

First, download the software. This example uses `/home/wptestrunner/` folder, but set the best for this environment.

```bash
cd /home/wptestrunner/
git clone https://github.com/WordPress/phpunit-test-runner.git
cd phpunit-test-runner/
```

The next step will be to configure the environment. To do this, make a copy of the example file and then configure it.

```bash
cp .env.default .env
vim .env
```

The content (in summary form) can be something like this:

```bash
###
# Configuration environment variables used by the test runner
#
# # Create a copy for your local environment
# $ cp .env.default .env
#
# # Make any necessary changes to the default values
# $ vim .env
#
# # Load your variables into your environment
# $ source .env
###

# Path to the directory where files can be prepared before being delivered to the environment.
export WPT_PREPARE_DIR=/tmp/wp-test-runner

# Path to the directory where the WordPress develop checkout can be placed and tests can be run.
# When running tests in the same environment, set WPT_TEST_DIR to WPT_PREPARE_DIR
export WPT_TEST_DIR=/tmp/wp-test-runner

# API key to authenticate with the reporting service in 'username:password' format.
export WPT_REPORT_API_KEY=

# (Optionally) define an alternate reporting URL
export WPT_REPORT_URL=

# Credentials for a database that can be written to and reset.
# WARNING!!! This database will be destroyed between tests. Only use safe database credentials.
# Please note that you must escape _or_ refrain from using # as special character in your credentials.
export WPT_DB_NAME=
export WPT_DB_USER=
export WPT_DB_PASSWORD=
export WPT_DB_HOST=

# (Optionally) set a custom table prefix to permit concurrency against the same database.
export WPT_TABLE_PREFIX=${WPT_TABLE_PREFIX-wptests_}

# (Optionally) define the PHP executable to be called
export WPT_PHP_EXECUTABLE=${WPT_PHP_EXECUTABLE-php}

# (Optionally) define the PHPUnit command execution call.
# Use if `php phpunit.phar` can't be called directly for some reason.
export WPT_PHPUNIT_CMD=

# (Optionally) define the command execution to remove the test directory
# Use if `rm -r` can't be called directly for some reason.
export WPT_RM_TEST_DIR_CMD=

# SSH connection string (can also be an alias).
# Leave empty if tests are meant to run in the same environment.
export WPT_SSH_CONNECT=

# Any options to be passed to the SSH connection
# Defaults to '-o StrictHostKeyChecking=no'
export WPT_SSH_OPTIONS=

# SSH private key, base64 encoded.
export WPT_SSH_PRIVATE_KEY_BASE64=

# Output logging
# Use 'verbose' to increase verbosity
export WPT_DEBUG=

# Certificate validation
# Use 1 to validate, and 0 to not validate
export WPT_CERTIFICATE_VALIDATION=1

# WordPress flavor
# 0 = WordPress (simple version)
# 1 = WordPress Multisite
export WPT_FLAVOR=1

# Extra tests (groups)
# 0 = none
# 1 = ajax
# 2 = ms-files
# 3 = external-http
export WPT_EXTRATESTS=0
```

Configure the folder where the WordPress software downloads and the database accesses will be made in order to prepare the tests.

### Preparing the environment

Before performing the first test, let’s update all the components. This process can be run before each test in this environment if wanted to keep it up to date, although it will depend more if it is in a production environment.

```bash
cd /home/wptestrunner/phpunit-test-runner/
git pull
source .env
git checkout master
```

If you want to check a different branch, you can change it doing:

```bash
git checkout example-branch
```

## Preparing the test

Now there is the environment ready, run the test preparation.

```bash
php prepare.php
```

The system will run a long series of installations, configurations and compilations of different elements in order to prepare the test. If warnings and warnings come out you should not worry too much, as it is quite normal. At the end of the process it will warn you if it needs something it doesn’t have. If it works, you should see something like this at the end:

```
Success: Prepared environment.
```

Now that the environment has been prepared, the next step is to run the tests for the first time.

### Running the test

Now that the environment is ready, let’s run the tests. To do this, execute the file that will perform it.

```bash
php test.php
```

What do the symbols mean?

`.` → Each dot means that the test has been passed correctly.

`S` → It means the test has been skipped. This is usually because these tests are only valid in certain configurations.

`F` → Means that the test has failed. Information about why this happened is displayed at the end.

`E` → It means that the test has failed due to a PHP error, which can be an error, warning or notice.

`I` → Means that the test has been marked as incomplete.

If you follow these steps, everything should work perfectly and not make any mistakes. In case you get any error, it may be normal due to some missing adjustment or extension of PHP, among others. We recommend that you adjust the configuration until it works correctly. After all, this tool is to help you improve the optimal configuration for WordPress in that infrastructure.

### Creating a report

Even if the test has failed, a report will be made. The first one shows the information about our environment. Among the most important elements are the extensions that are commonly used in WordPress and some utilities that are also generally useful.

```bash
cat /tmp/wp-test-runner/tests/phpunit/build/logs/env.json
```

The content of this file is somewhat similar to this:

```bash
{
  "php_version": "7.4.5",
  "php_modules": {
    "bcmath": false,
    "curl": "7.4.5",
    "filter": "7.4.5",
    "gd": false,
    "libsodium": false,
    "mcrypt": false,
    "mod_xml": false,
    "mysqli": "7.4.5",
    "imagick": false,
    "pcre": "7.4.5",
    "xml": "7.4.5",
    "xmlreader": "7.4.5",
    "zlib": "7.4.5"
  },
  "system_utils": {
    "curl": "7.58.0 (x86_64-pc-linux-gnu) libcurl\/7.58.0 OpenSSL\/1.1.1g zlib\/1.2.11 libidn2\/2.3.0 libpsl\/0.19.1 (+libidn2\/2.0.4) nghttp2\/1.30.0 librtmp\/2.3",
    "ghostscript": "",
    "imagemagick": false,
    "openssl": "1.1.1g 21 Apr 2020"
  },
  "mysql_version": "mysql Ver 15.1 Distrib 10.4.12-MariaDB, for debian-linux-gnu (x86_64) using readline 5.2",
  "os_name": "Linux",
  "os_version": "4.15.0-20-generic"
}
```

In addition to this report, a definitive file with all the information of what happened in the tests. This is the one that includes all the tests that are made (more than 10,000) giving information of the time that they take to be executed, problems that have arisen…

```bash
cat /tmp/wp-test-runner/tests/phpunit/build/logs/junit.xml
```

At this point we can generate the reports by sending them to WordPress.org, if necessary. Even if you haven’t included the WordPress user (see below for how to create it), you can still run this file.

```bash
php report.php
```

### Cleaning up the environment for other tests

Having the tests working, all that remains is to delete all the files that have been created so that we can start over. To do this, execute the following command:

```bash
php cleanup.php
```

### Automatic running

The best way to run this test is to create a cron that runs everything. Having in mind that the tests can overlap, the best way can be using a systemd timer.

```bash
cat > /etc/systemd/system/wordpressphpunittestrunner.service << EOF
[Unit]
Description=WordPress PHPUnit Test Runner
[Service]
Type=oneshot
ExecStart=cd /home/wptestrunner/phpunit-test-runner/ && source .env && php prepare.php && php test.php && php report.php && php cleanup.php
User=wptestrunner
Group=wptestrunner
EOF
```

```bash
cat > /etc/systemd/system/wordpressphpunittestrunner.timer << EOF
[Unit]
Description=WordPress PHPUnit Test Runner
[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true
[Install]
WantedBy=timers.target
EOF
```

```bash
systemctl daemon-reload
systemctl enable wordpressphpunittestrunner.timer
systemctl start wordpressphpunittestrunner.timer
systemctl status wordpressphpunittestrunner.timer
```

If you want to check how is everything working...

```bash
journalctl -u wordpressphpunittestrunner.timer
journalctl -n 120 -u wordpressphpunittestrunner.service
```

## Contributing

If you have questions about the process or run into test failures along the way, please [open an issue in the project repository](https://github.com/WordPress/phpunit-test-runner/issues) and we’ll help diagnose/get the documentation updated. Alternatively, you can also pop into the `#hosting` channel on [WordPress.org Slack](https://make.wordpress.org/chat/) for help.

## License

See [LICENSE](LICENSE) for project license.