#!/bin/bash
# @file
# Simple script to load composer and run the tests.

set -e

DIR=$(dirname $0)
cd $DIR
composer install
./vendor/bin/phpunit
