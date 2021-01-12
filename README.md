# phpunit-test-runner

Thanks for running the WordPress PHPUnit test suite on your infrastructure. We appreciate you helping to ensure WordPress’s compatibility for your users.

If you haven't already, [please first read through the "Getting Started" documentation](https://make.wordpress.org/hosting/test-results-getting-started/).

The test suite runner is designed to be used without any file modification. Configuration happens with a series of environment variables (see [.env.default](.env.default) for an annotated overview). Use the [repository wiki](../../wiki) to document implementation details, to avoid README conflicts with the upstream.

At a high level, the test suite runner:

1. Prepares the test environment for the test suite.
2. Runs the PHPUnit tests in the test environment.
3. Reports the PHPUnit test results to WordPress.org
4. Cleans up the test suite environment.

## Configuring

The test suite runner can be used in one of two ways:

1. With Travis (or Circle or some other CI service) as the controller that connects to the remote test environment.
2. With the runner cloned to and run directly within the test environment.

The test runner is configured through environment variables, documented in [`.env.default`](.env.default). It shouldn't need any code modifications; in fact, please refrain from editing the scripts entirely, as it will make it easier to stay up to date.

With a direct Git clone, you can:

    # Copy the default .env file.
    cp .env.default .env
    # Edit the .env file to define your variables.
    vim .env
    # Load your variables into scope.
    source .env

In a CI service, you can set these environment variables through the service's web console. Importantly, the `WPT_SSH_CONNECT` environment variable determines whether the test suite is run locally or against a remote environment.

Concurrently run tests in the same environment by appending build ids to the test directory and table prefix:

    export WPT_TEST_DIR=wp-test-runner-$TRAVIS_BUILD_NUMBER
    export WPT_TABLE_PREFIX=wptests_$TRAVIS_BUILD_NUMBER\_

Connect to a remote environment over SSH by having the CI job provision the SSH key:

    # 1. Create a SSH key pair for the controller to use
    ssh-keygen -t rsa -b 4096 -C "travis@travis-ci.org"
    # 2. base64 encode the private key for use with the environment variable
    cat ~/.ssh/id_rsa | base64 --wrap=0
    # 3. Append id_rsa.pub to authorized_keys so the CI service can SSH in
    cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys

Use a more complex SSH connection process by creating a SSH alias:

    # 1. Add the following to ~/.ssh/config to create a 'wpt' alias
    Host wpt
      Hostname 123.45.67.89
      User wpt
      Port 1234
    # 2. Use 'wpt' wherever you might normally use a SSH connection string
    ssh wpt

## Running

The test suite runner is run in four steps.

### 0. Requirements

Both the prep and test environments must meet some basic requirements.

Prep environment:

* PHP 5.6 or greater (to run scripts).
* Utilities: `git` version 1.8.5 or greater, `rsync`, `wget`, `unzip`.
* Node.js including `npm` and `grunt` packages

Test environment:

* PHP 5.6 or greater with Phar support enabled (for PHPUnit).
* MySQL or MariaDB with access to a writable database.
* Writable filesystem for the entire test directory (see [#40910](https://core.trac.wordpress.org/ticket/40910)).
* Run with a non-root user, both for security and practical purposes (see [#44233](https://core.trac.wordpress.org/ticket/44233#comment:34)/[#46577](https://core.trac.wordpress.org/ticket/46577)).

### 1. Prepare

The [`prepare.php`](prepare.php) step:

1. Extracts the base64-encoded SSH private key, if necessary.
2. Clones the master branch of the WordPress develop git repo into the preparation directory.
3. Downloads `phpunit.phar` to the preparation directory.
4. Generates `wp-tests-config.php` and puts it into the preparation directory.
5. Delivers the prepared test directory to the test environment.

### 2. Test

The [`test.php`](test.php) step:

1. Calls `php phpunit.phar` to produce `tests/phpunit/build/logs/junit.xml`.

### 3. Report

The [`report.php`](report.php) step:

1. Processes PHPUnit XML log into a JSON blob.
2. Sends the JSON to WordPress.org.

### 4. Cleanup

The [`cleanup.php`](cleanup.php) step:

1. Resets the database.
2. Deletes all files delivered to the test environment.

## Contributing

tk

## License

See [LICENSE](LICENSE) for project license.
