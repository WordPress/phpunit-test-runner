# Requirements

To use the Runner, the following is required:

- A server / hosting (infrastructure) with the usual configuration you have.
- A database (MySQL, MariaDB, or WordPress compatible) where you can test (it will be created and destroyed several times)
- NodeJS 20.x
- git, wget, rsync, zip, unzip...

## NodeJS installation

If you are using Debian / Ubuntu, install or update NodeJS 18 with this command:

```
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt -y install nodejs
node -v
npm install -g npm@latest
npm --version
```

If you are using RHEL / CentOS, install or update NodeJS 20 with this command:

```
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo -E bash -
sudo yum install -y nodejs
node -v
npm install -g npm@latest
npm --version
```

## Composer installation

```
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

# Installing the Runner

First, download the software. This example use `/home/wptestrunner/` folder, but set the best for this environment.

```
cd /home/wptestrunner/
git clone https://github.com/WordPress/phpunit-test-runner.git
cd phpunit-test-runner/
```

The next step will be to configure the environment. To do this, make a copy of the example file and then configure it.

```
cp .env.default .env
vim .env
```

This is the default configuraton file:

```
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

# Label for the environment. Can be empty (default) or be like "shared", "vps", "cloud" or similar.
# Please use only alphanumeric keywords, and try to be descriptive
export WPT_LABEL=

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

# (Optionally) array of versions (like: 8.0+/bin/php8.0,8.1+/bin/php8.1)
export WPT_PHP_EXECUTABLE_MULTI=

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
export WPT_CERTIFICATE_VALIDATION=0

# WordPress flavor
# 0 = WordPress (simple version)
# 1 = WordPress Multisite
export WPT_FLAVOR=0

# Extra tests (groups)
# 0 = none
# 1 = ajax
# 2 = ms-files
# 3 = external-http
export WPT_EXTRATESTS=0

# Check all commits
# 0 = latest
# 1 = all
export WPT_COMMITS=0
````

And this could be an example of each part:

**Label**

Label for the environment. Can be empty (default) or be like "shared", "vps", "cloud" or similar. Please use only alphanumeric keywords, and try to be descriptive

```
export WPT_LABEL=shared
```

**Preparation directory**

Path to the directory where files can be prepared before being delivered to the environment.

Usually can be a /tmp/ folder so it does everything temporary. 

```
export WPT_PREPARE_DIR=/tmp/wp-test-runner
```

**Test directory**

Path to the directory where the WordPress develop checkout can be placed and tests can be run. When running tests in the same environment, set WPT_TEST_DIR to WPT_PREPARE_DIR equally.


```
export WPT_TEST_DIR=/tmp/wp-test-runner
```

**API KEY**

API key to authenticate with the reporting service in 'username:password' format. This is only needed if you want to publish your results in the WordPress site, so data can help developers to improve WordPress.

Read: [How to report: Creating your bot for WordPress.org](https://make.wordpress.org/hosting/handbook/tests/#how-to-report-creating-your-bot-for-wordpress-org)

```
export WPT_REPORT_API_KEY=userbot:12345ABCDE67890F
```

**Reporting URL**

(Optionally) Define an alternate reporting URL, if you are running your own website.

It should look like:
`https://reporter.example.com/wp-json/wp-unit-test-api/v1/results`

``` 
export WPT_REPORT_URL=https://reporter.example.com/wp-json/wp-unit-test-api/v1/results
```

**Database credentials**

Credentials for a database that can be written to and reset.

WARNING: This database will be destroyed between tests. Only use safe database credentials.

Please note that you must escape _or_ refrain from using # as special character in your credentials.


```
export WPT_DB_NAME=testbot
export WPT_DB_USER=wpuser
export WPT_DB_PASSWORD=wppassword
export WPT_DB_HOST=localhost
```

**Tables Custom prefix**

(Optionally) Set a custom table prefix to allow concurrency against the same database. This is very useful if you activate the multi-php or multi-environment part.

```
export WPT_TABLE_PREFIX=${WPT_TABLE_PREFIX-wptests_}
```

**PHP versions**

_There are two options for backward compatibility._

The first one is the binary file / path for the default PHP. If it's empty it will use "php" as the command.

```
export WPT_PHP_EXECUTABLE=${WPT_PHP_EXECUTABLE-php}
```

The second one is the optional part. This allow to test more than one PHP versions. The format to use is:

_majorversion1+binary_path1,majorversion2+binary_path2_

something like:

`8.0+/bin/php8.0,8.1+/bin/php8.1`

Use as much versions as you want, but it will take more time. The idea is to put all the versions offered to users.

```
export WPT_PHP_EXECUTABLE_MULTI=7.4+/bin/php7.4,8.3+/bin/php8.3
```

**PHPUnit execution call**

(Optionally) define the PHPUnit command execution call. Use if `php phpunit.phar` can't be called directly for some reason.

```
export WPT_PHPUNIT_CMD=
```

****

```
# (Optionally) define the command execution to remove the test directory
# Use if `rm -r` can't be called directly for some reason.
export WPT_RM_TEST_DIR_CMD=
```


```
# SSH connection string (can also be an alias).
# Leave empty if tests are meant to run in the same environment.
export WPT_SSH_CONNECT=
```


```
# Any options to be passed to the SSH connection
# Defaults to '-o StrictHostKeyChecking=no'
export WPT_SSH_OPTIONS=
```


```
# SSH private key, base64 encoded.
export WPT_SSH_PRIVATE_KEY_BASE64=
```


```
# Output logging
# Use 'verbose' to increase verbosity
export WPT_DEBUG=
```

```
# Certificate validation
# Use 1 to validate, and 0 to not validate
export WPT_CERTIFICATE_VALIDATION=0
```

```
# WordPress flavor
# 0 = WordPress (simple version)
# 1 = WordPress Multisite
export WPT_FLAVOR=0
```

```
# Extra tests (groups)
# 0 = none
# 1 = ajax
# 2 = ms-files
# 3 = external-http
export WPT_EXTRATESTS=0
```

```
# Check all commits
# 0 = latest
# 1 = all
export WPT_COMMITS=0
```


Configure the folder where the WordPress software downloads and the database accesses will be made in order to prepare the tests.

# Preparing the environment

Before performing the first test, let’s update all the components. This process can be run before each test in this environment if wanted to keep it up to date, although it will depend more if it is in a production environment.

```
cd /home/wptestrunner/phpunit-test-runner/
git pull
source .env
```
