# WordPress PHPUnit Test Runner container image.
#
# Bundles everything prepare.php, test.php, report.php, and cleanup.php need:
# PHP with the extensions WordPress tests exercise, Composer, Node.js/npm,
# Git, rsync, wget, unzip, and an SSH client for remote test environments.
#
# Build arguments:
# - PHP_VERSION:  PHP version of the official `php` image to build on.
# - NODE_VERSION: Major Node.js version used to build wordpress-develop.
# - WPT_UID:      UID of the non-root user that runs the tests. Match it to
#                 the host user when bind mounting the output directory.

ARG PHP_VERSION=8.3
ARG NODE_VERSION=24

FROM node:${NODE_VERSION}-bookworm-slim AS node

FROM php:${PHP_VERSION}-cli

ARG WPT_UID=1000

# System utilities used by the runner scripts.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		ca-certificates \
		git \
		openssh-client \
		rsync \
		unzip \
		wget \
	&& rm -rf /var/lib/apt/lists/*

# PHP extensions used by the WordPress PHPUnit test suite.
# Imagick is built from its GitHub release because older PECL clients cannot
# read the package metadata pecl.php.net currently serves.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
		bcmath \
		exif \
		gd \
		Imagick/imagick@3.8.1 \
		intl \
		mysqli \
		pdo_mysql \
		soap \
		sockets \
		zip \
	&& { \
		echo 'memory_limit=1G'; \
		echo 'error_reporting=E_ALL'; \
		echo 'display_errors=On'; \
	} > /usr/local/etc/php/conf.d/wpt-runner.ini

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node.js and npm.
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
	&& ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
	&& node --version \
	&& npm --version

# The test suite must not run as root, see https://core.trac.wordpress.org/ticket/44233.
RUN useradd --create-home --uid "${WPT_UID}" --shell /bin/bash wptests \
	&& mkdir -p /output \
	&& chown wptests:wptests /output

WORKDIR /opt/phpunit-test-runner
COPY --chown=wptests:wptests . .

USER wptests

ENV WPT_PREPARE_DIR=/tmp/wp-test-runner \
	WPT_TEST_DIR=/tmp/wp-test-runner \
	WPT_OUTPUT_DIR=/output

ENTRYPOINT ["/opt/phpunit-test-runner/docker/entrypoint.sh"]
