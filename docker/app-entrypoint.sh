#!/bin/sh
set -eu

cd /var/www/html
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ ! -s storage/app/runtime.key ]; then
    php -r 'file_put_contents("storage/app/runtime.key", "base64:".base64_encode(random_bytes(32)).PHP_EOL);'
fi

chown -R www-data:www-data storage bootstrap/cache
chmod 600 storage/app/runtime.key

su -s /bin/sh www-data -c 'php /usr/local/bin/check-census.php'
exec docker-php-entrypoint "$@"
