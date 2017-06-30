# WP-Unit-Test-Runner

Thanks for running the WordPress PHPUnit test suite on your infrastructure. We appreciate you helping to ensure WordPress’ compatibility for your users.

At a high level, the test suite runner:

1. Prepares the test environment for the test suite.
2. Runs the PHPUnit tests in the test environment.
3. Reports the PHPUnit test results to WordPress.org
4. Cleans up the test suite environment.

The test suite runner is designed to be used without any file modification. Configuration happens with a series of environment variables. Use the [repository wiki](../../wiki) to document implementation details, to avoid README conflicts with the upstream.

## Configuring

The test suite runner can be used in one of two ways:

1. With Travis (or Circle or some other CI service) as the controller that connects to the remote test environment.
2. With the runner cloned to and run directly within the test environment.

The test runner is configured through environment variables, documented in [`.env.default`](.env.default). It shouldn't need any code modifications; in fact, please refrain from editing the scripts entirely, as it will make it easier to stay up to date.

Importantly, the `WPT_SSH_CONNECT` environment variable determines whether the test suite is run locally or against a remote environment.

In a CI service, you can set these environment variables through the service's web console. With a direct Git clone, you can:

    # Copy the default .env file.
    cp .env.default .env
    # Edit the .env file to define your variables.
    vim .env
    # Load your variables into scope.
    source .env

If you only have one database for test runs, you can achieve concurrency by appending build ids:

    export WPT_TEST_DIR=wp-test-runner-$TRAVIS_BUILD_NUMBER
    export WPT_TABLE_PREFIX=wptests_$TRAVIS_BUILD_NUMBER\_

If the controller needs to connect to a remote environment, you'll need to have the CI job configure a SSH key:

    # 1. Create a SSH key pair for the controller to use
    ssh-keygen -t rsa -b 4096 -C "travis@travis-ci.org"
    # 2. base64 encode the private key for use with the environment variable
    cat ~/.ssh/id_rsa | base64 --wrap=0
    # 3. Append id_rsa.pub to authorized_keys so the CI service can SSH in
    cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys

## Running

The test suite runner is run in four steps.

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
