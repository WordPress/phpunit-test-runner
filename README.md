# WP-Unit-Test-Runner

Thanks for running the WordPress PHPUnit test suite on your infrastructure. We appreciate you helping to ensure WordPress’ compatibility for your users.

At a high level, the test suite runner:

1. Prepares the test environment for the test suite.
2. Runs the PHPUnit tests in the test environment.
3. Reports the PHPUnit test results to WordPress.org
4. Cleans up the test suite environment.

## Configuring

The test suite runner can be used in one of two ways:

1. With Travis (or Circle or some other CI service) as the controller that connects to the remote test environment.
2. With the runner cloned to and run directly within the test environment.

The test runner is configured through environment variables, documented in [`.env.default`](.env.default). In a CI service, you can set these environment variables through the service's web console. With a direct clone, you can:

    # Copy the default .env file.
    cp .env.default .env
    # Edit the .env file to define your variables.
    vim .env
    # Load your variables into scope.
    source .env

If the controller needs to connect to a remote environment, you'll need to:

    # 1. Create a SSH key pair for the controller to use
    ssh-keygen -t rsa -b 4096 -C "travis@travis-ci.org"
    # 2. base64 encode the private key for use with the environment variable
    cat ~/.ssh/id_rsa | base64 --wrap=0

To configure the test suite to run from Travis:

1. Fork the example repository found here (Link to example Travis setup).
2.

To configure the test suite to run directly within the test environment…

## Running

The test suite runner is run in four steps.

### 1. Prepare

The prepare step:

1. Creates the database if one doesn’t already exist.
2. Clones the WordPress develop git repo at the specified hash, delivers the files to the test environment.
3. Downloads `phpunit.phar` and puts it in the test environment.
4. Generates `wp-tests-config.php` and puts it in the test environment.

### 2. Run

Calls `phpunit` to produce tests/phpunit/build/logs/junit.xml as defined by the core phpunit.xml.

### 3. Report

The report step:

1. Processes PHPUnit result output to generate JSON to send to WordPress.org
2. Sends the JSON to WordPress.org

### 4. Cleanup

The cleanup step:

1. Resets the database.
2. Deletes all files delivered to the test environment.

## Contributing

tk
