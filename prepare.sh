#!/bin/bash

###
# Prepares the environment for the test run.
###

set -ex

# Check required environment variables
bash check-env.sh

# @todo create the database if one doesn't already exist



# @todo empty the database


# Clone WordPress develop at the target hash
mkdir -p $WPT_PREPARE_DIR
git clone --depth=1 git@github.com:WordPress/wordpress-develop.git $WPT_PREPARE_DIR

# Download phpunit.phar
wget -O $WPT_PREPARE_DIR/phpunit.phar https://phar.phpunit.de/phpunit-5.7.phar

# Generate wp-tests-config.php
cp "$WPT_PREPARE_DIR"/wp-tests-config-sample.php "$WPT_PREPARE_DIR"/wp-tests-config.php
if [[ $(uname -s) == 'Darwin' ]]; then
	local ioption='-i .bak'
else
	local ioption='-i'
fi
sed $ioption "s/youremptytestdbnamehere/$WPT_DB_NAME/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $ioption "s/yourusernamehere/$WPT_DB_USER/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $ioption "s/yourpasswordhere/$WPT_DB_PASS/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $ioption "s|localhost|${WPT_DB_HOST}|" "$WPT_PREPARE_DIR"/wp-tests-config.php

# Deliver all files to test environment
rsync -rv --exclude='.git/' $WPT_PREPARE_DIR $WPT_SSH_CONNECT:$WPT_TARGET_DIR
