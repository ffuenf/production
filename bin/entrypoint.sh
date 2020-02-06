#!/bin/sh

set -e

if [ -z $1 ]; then
    bin/console assets:install || true
    bin/console system:install --force --drop-database --create-database --basic-setup || true
    rm -Rf var/cache
    mkdir -p var/cache var/queue
    php -r 'include_once "vendor/autoload.php"; echo (explode("@", PackageVersions\Versions::getVersion("shopware/core"))[0]);' > public/recovery/install/data/version
    bin/console theme:compile || true
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    bin/console $@
fi