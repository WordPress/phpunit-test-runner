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
fi
# Restore command traces for the rest of the script
set -x

# @todo create the database if one doesn't already exist



# @todo empty the database


# Clone WordPress develop at the target hash
mkdir -p $WPT_PREPARE_DIR
# Add github's public key for cloning
echo "|1|qPmmP7LVZ7Qbpk7AylmkfR0FApQ=|WUy1WS3F4qcr3R5Sc728778goPw= ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAq2A7hRGmdnm9tUDbO9IDSwBK6TbQa+PXYPCPy6rbTrTtw7PHkccKrpp0yVhp5HdEIcKr6pLlVDBfOLX9QUsyCOV0wzfjIJNlGEYsdlLJizHhbn2mUjvSAHQqZETYP81eFzLQNnPHt4EVVUh7VfDESU84KezmD5QlWpXLmvU31/yMf+Se8xhHTvKSCZIFImWwoG6mbUoWf9nzpIoaSjB+weqqUUmpaaasXVal72J+UX2B+2RPW3RcT0eOzQgqlJL3RKrTJvdsjE3JEAvGq3lGHSZXy28G3skua2SmVi/w4yCE6gbODqnTWlg7+wC604ydGXA8VJiS5ap43JXiUFFAaQ==" >> ~/.ssh/known_hosts
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
sed $ioption "s/yourpasswordhere/$WPT_DB_PASSWORD/" "$WPT_PREPARE_DIR"/wp-tests-config.php
sed $ioption "s|localhost|${WPT_DB_HOST}|" "$WPT_PREPARE_DIR"/wp-tests-config.php

# Deliver all files to test environment
rsync -rv --exclude='.git/' $WPT_PREPARE_DIR $WPT_SSH_CONNECT:$WPT_TARGET_DIR
