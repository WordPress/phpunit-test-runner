# Security Policy

## Supported scope

The WordPress PHPUnit Test Runner is the tooling hosting providers use to run the WordPress core test suite and report results. This repository contains those scripts and their GitHub configuration.

This policy covers security-sensitive issues in the code and configuration of this repository. It does not define support for WordPress core, plugins, themes, hosting stacks, server packages, or hosting platforms.

## Reporting vulnerabilities

For WordPress core, plugins, themes, WordPress.org, or the wider WordPress ecosystem, follow the official [WordPress security reporting guidance](https://wordpress.org/about/security/). WordPress core vulnerabilities should be reported through the [WordPress HackerOne program](https://hackerone.com/wordpress).

Do not report exploitable security vulnerabilities in public GitHub issues or pull requests. For a vulnerability in the test runner scripts themselves, use the reporting channels from the official guidance above rather than a public issue, so hosts running the tooling are not exposed before a fix is available.

If the vulnerability is in a hosting platform, server package, or other third-party project, report it to that project or vendor through their security reporting process.

## Other issues

If you find a bug or an insecure default in the test runner that is not an exploitable vulnerability, open a public issue in this repository:

https://github.com/WordPress/phpunit-test-runner/issues

Include the affected script, the behaviour you observed, and any safer replacement you are suggesting. Do not include exploit details or private vulnerability information in public issues.
