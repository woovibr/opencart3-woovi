#!/bin/bash

mkdir -p $WOOVI_EXTENSION_PATH
cd $WOOVI_EXTENSION_PATH

# Install Composer dependencies.
composer install

# Wait for MySQL because of OpenCart installation.
echo "Waiting for MySQL..."

curl -sSL https://raw.githubusercontent.com/eficode/wait-for/v2.2.3/wait-for | sh -s -- $MYSQL_HOST:$MYSQL_PORT -t 0

# Install the OpenCart if not installed.
composer robo opencart:setup

# Fix OpenCart warnings.
composer robo opencart:fix

# Link the Woovi extension directory into the OpenCart `extension` directory.
composer robo extension:link

# Enable Woovi extension if not enabled.
composer robo extension:enable

# Start Apache HTTP server.
echo "Starting Apache..."

docker-php-entrypoint apache2-foreground
