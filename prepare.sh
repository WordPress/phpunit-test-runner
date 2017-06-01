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

# @todo Download phpunit.phar

# @todo Generate wp-tests-config.php

# Deliver all files to test environment
rsync -rv --exclude='.git/' $WPT_PREPARE_DIR $WPT_SSH_CONNECT:$WPT_TARGET_DIR
