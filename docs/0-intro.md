# About the PHPUnit Test Runner

Hosting companies can have several to millions of websites hosted with WordPress, so it's important to make sure their configuration is as compatible as possible with the software.

To verify this compatibility, the WordPress Community provides a series of PHPUnit tests with which to check the operation of WordPress in any environment.

The Runner tests generates a report with the test results related to a bot user (a hosting company), and this captures and displays those test results at the [Host Test Result](https://make.wordpress.org/hosting/test-results/) page.

## What's the phpunit-test-runner

The [phpunit-test-runner](https://github.com/WordPress/phpunit-test-runner) is a tool designed to make it easier for hosting companies to run the WordPress project’s automated tests.

There is a [whole documentation about this tool](https://make.wordpress.org/hosting/test-results-getting-started/). Also, if you want, you can make your test results appear in the [Host Test Results](https://make.wordpress.org/hosting/test-results/) page of WordPress.

The tool can be run manually or through an automated system like Travis. To see how it works and the purpose of this document, will be shown how to run the tests manually.
