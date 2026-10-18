#!/bin/sh
set -eu

# Only the dedicated persistent upload directory is mutable by Apache.
mkdir -p /var/www/html/public/uploads
chown www-data:www-data /var/www/html/public/uploads
chmod 0750 /var/www/html/public/uploads

# Fail closed until the database and forward migrations are ready.
php /var/www/html/docker/wait-for-db.php
php /var/www/html/bin/migrate.php
exec "$@"
