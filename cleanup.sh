#!/bin/bash

###
# Cleans up the environment after the test run.
###

set -ex

# Check required environment variables
bash check-env.sh

# @todo Reset the database to its original condition by dropping tables

# Clean up the preparation directory
rm -r $WPT_PREPARE_DIR

# Delete all files delivered to the test environment.
