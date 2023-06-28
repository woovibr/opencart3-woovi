#!/bin/bash

# Don't run the install if config.php is not empty, indicating an existing install.
if [ ! -s config.php ]; then
    echo "Waiting for MySQL..."

    # Wait for MySQL.
    curl -sSL https://raw.githubusercontent.com/eficode/wait-for/v2.2.3/wait-for | sh -s -- $MYSQL_HOST:$MYSQL_PORT -t 0

    echo "Installing OpenCart..."

    php install/cli_install.php install --username $OPENCART_USER_NAME \
        --email       $OPENCART_USER_EMAIL \
        --password    $OPENCART_USER_PASSWORD \
        --http_server $APP_URL \
        --db_driver   mysqli \
        --db_hostname $MYSQL_HOST \
        --db_username $MYSQL_USER \
        --db_password $MYSQL_PASSWORD \
        --db_database $MYSQL_DATABASE \
        --db_port     $MYSQL_PORT \
        --db_prefix   oc_

    rm -rf install

    echo "Moving storage out of public folder..."

    mkdir -p /opt/opencart-storage
    chmod -R 777 /opt/opencart-storage

    cp -rp system/storage/* /opt/opencart-storage
    rm -rf /opt/opencart/system/storage

    sed -i "s/define('DIR_STORAGE', .*);/define('DIR_STORAGE', '\/opt\/opencart-storage\/');/g" config.php
    sed -i "s/define('DIR_STORAGE', .*);/define('DIR_STORAGE', '\/opt\/opencart-storage\/');/g" admin/config.php

    echo "Renaming admin folder to administration..."

    mv admin administration
    sed -i "s/admin\//administration\//g" administration/config.php

    echo "Current admin URL: ${APP_URL}administration/"
fi

echo "Starting Apache..."

docker-php-entrypoint apache2-foreground
