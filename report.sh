#!/bin/bash

###
# Reports the test run results to WordPress.org
###

set -ex

# Check required environment variables
bash check-env.sh

# @todo process phpunit test run results; send to WordPress.org
