#!/bin/bash

###
# Checks required environment variables
###

set -e
set +x

if [ -z "$WPT_PREPARE_DIR" ]; then
	echo "WPT_PREPARE_DIR must be set to a writeable preparation directory"
	exit 1
fi

if [ -z "$WPT_TEST_DIR" ]; then
	echo "WPT_TEST_DIR must be set to an accessible test directory"
	exit 1
fi

echo "Environment variables pass checks"
