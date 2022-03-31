#!/usr/bin/env bash

# Copy composer.lock to keep versions the same
cp composer.lock composer-dev.lock

COMPOSER=composer-dev.json composer update opendialogai/core opendialogai/webchat opendialogai/voice --prefer-source

# Clean up the temp lock file
#rm composer-dev.lock
