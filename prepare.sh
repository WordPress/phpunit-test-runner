#!/bin/bash

###
# Prepares the environment for the test run.
###

set -ex

# Check required environment variables
bash check-env.sh

# Set the ssh private key if it's set
# Turn off command traces while dealing with the private key
set +x
if [ -n "$WPT_SSH_PRIVATE_KEY_BASE64" ]; then
	echo 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa'
	echo $WPT_SSH_PRIVATE_KEY_BASE64 | base64 --decode > ~/.ssh/id_rsa
	chmod 600 ~/.ssh/id_rsa
	echo 'Testing SSH connection with credentials'
	ssh -q -o StrictHostKeyChecking=no $WPT_SSH_CONNECT exit
fi
# Restore command traces for the rest of the script
set -x

# @todo create the database if one doesn't already exist



# @todo empty the database


# Clone WordPress develop at the target hash
mkdir -p $WPT_PREPARE_DIR
git clone --depth=1 https://github.com/WordPress/wordpress-develop.git $WPT_PREPARE_DIR

# Download phpunit.phar
wget -O $WPT_PREPARE_DIR/phpunit.phar https://phar.phpunit.de/phpunit-5.7.phar

# Generate wp-tests-config.php
WPT_TABLE_PREFIX=${WPT_TABLE_PREFIX-wptests_}
cp "$WPT_PREPARE_DIR"/wp-tests-config-sample.php "$WPT_PREPARE_DIR"/wp-tests-config.php
if [[ $(uname -s) == 'Darwin' ]]; then
	IOPTION='-i .bak'
else
	IOPTION='-i'
fi
sed $IOPTION "s/youremptytestdbnamehere/$WPT_DB_NAME/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $IOPTION "s/yourusernamehere/$WPT_DB_USER/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $IOPTION "s/yourpasswordhere/$WPT_DB_PASSWORD/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $IOPTION "s/localhost/$WPT_DB_HOST/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $IOPTION "s/wptests_/$WPT_TABLE_PREFIX/" "$WPT_PREPARE_DIR"/wp-tests-config.php

# Deliver all files to test environment
rsync -rv --exclude='.git/' -e "ssh -o StrictHostKeyChecking=no" $WPT_PREPARE_DIR $WPT_SSH_CONNECT:$WPT_TARGET_DIR
