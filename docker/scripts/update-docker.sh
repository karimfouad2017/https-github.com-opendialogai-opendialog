#!/bin/bash

set -e

echo "Setting up the database..."
php artisan migrate --force

echo "Initializing dgraph schema..."
php artisan schema:init --yes

echo "Creating admin user"
php artisan user:create --userCheck

echo "Creating default component configurations"
php artisan configurations:create

echo "Creating webchat interface setting content"
php artisan component:settings

echo "Ensuring log directory is writable..."
chmod -R 777 /var/www/storage/logs
