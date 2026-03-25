#!/bin/sh
set -eu

# Run Laravel migrations
php artisan migrate

exec php-fpm