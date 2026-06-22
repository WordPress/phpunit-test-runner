# WordPress PHPUnit Test Runner — Pantheon

This is Pantheon's fork of the [WordPress PHPUnit Test Runner](https://github.com/WordPress/phpunit-test-runner), adapted to run the WordPress core hosting test suite on a Pantheon site and report results to the [WordPress.org hosting test results page](https://make.wordpress.org/hosting/test-results/).

The Pantheon site used as the test target runs the empty WordPress upstream (`empty-wp`).

For general background on the hosting tests, see the [Getting Started documentation](https://make.wordpress.org/hosting/handbook/tests/).

## How it works

Tests run directly on the Pantheon site via Terminus. The workflow:

1. **Prepare** — clones `wordpress-develop` on the Pantheon site via `terminus remote:wp eval`, generates `wp-tests-config.php` using Pantheon's internal DB credentials, and installs PHPUnit dependencies via `terminus remote:composer`.
2. **Test** — runs the full WordPress PHPUnit suite on the Pantheon site via `terminus remote:wp eval`.
3. **Report** — retrieves results from the Pantheon site, fetches the current WordPress SVN revision, and uploads to WordPress.org.
4. **Cleanup** — removes the test directory from the Pantheon site.

## GitHub Actions secrets

| Secret | Description |
|--------|-------------|
| `PANTHEON_SITE_NAME` | Pantheon site name (e.g. `my-site`) |
| `PANTHEON_SITE_ENV` | Pantheon environment (e.g. `dev`) |
| `TERMINUS_MACHINE_TOKEN` | Terminus machine token for the Pantheon account that owns the site |
| `WPT_SSH_PRIVATE_KEY_BASE64` | RSA private key (base64-encoded) registered on the Pantheon account — required for `terminus remote:wp` and `terminus remote:composer` |
| `WPT_PREPARE_DIR` | Local GHA path for temporary files (e.g. `/tmp/wp-test-runner`) |
| `WPT_TEST_DIR` | Path on the Pantheon site where tests run (e.g. `/code/wp-test-runner`) |
| `WPT_REPORT_API_KEY` | WordPress.org API key in `username:password` format |

## Running the tests

### Scheduled runs

The workflow runs automatically on an hourly schedule. Results appear on the [WordPress.org hosting test results page](https://make.wordpress.org/hosting/test-results/).

### Manual runs

Trigger a run manually from **Actions → WordPress PHPUnit tests → Run workflow**.

Two optional inputs are available for targeted runs:

#### `test_group` (dropdown)

Runs only tests tagged with a specific `@group` annotation. Useful for quickly validating a specific feature area without running the full ~29,000 test suite. Available groups include `post`, `query`, `user`, `taxonomy`, `comment`, `db`, `formatting`, `rest-api`, and others.

Leave blank to run the full suite.

#### `test_filter`

A PHPUnit `--filter` pattern matched against class and method names. More flexible than `test_group` — accepts any pattern PHPUnit's `--filter` supports.

Examples:
- `Tests_DB` — all tests in the `Tests_DB` class
- `Tests_Options` — all options tests
- `Tests_Post_Types::test_register` — a specific method

If both inputs are set, `test_filter` takes precedence over `test_group`.

> **Note:** Partial runs still report to WordPress.org. Use these inputs for pipeline testing only, not as your regular scheduled run.

## Upstream sync

This fork tracks [WordPress/phpunit-test-runner](https://github.com/WordPress/phpunit-test-runner). To pull in upstream changes:

```bash
git fetch upstream
git merge upstream/master
```

Conflicts will occur in `prepare.php`, `test.php`, `report.php`, `cleanup.php`, and the workflow — these files contain Pantheon-specific logic that differs from the upstream approach. Everything else should merge cleanly.

## Contributing

For issues specific to this Pantheon fork, open an issue in this repository. For issues with the upstream test runner, open an issue in [WordPress/phpunit-test-runner](https://github.com/WordPress/phpunit-test-runner/issues).

For questions about the WordPress hosting tests generally, visit the `#hosting` channel on [WordPress.org Slack](https://make.wordpress.org/chat/).

## License

See [LICENSE](LICENSE) for project license.
