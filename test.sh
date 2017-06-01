#!/bin/bash

###
# Runs the PHPUnit test suite in the test environment.
###

set -ex

# Check required environment variables
bash check-env.sh

# @todo run phpunit in the test environment with the --log-xml flag
