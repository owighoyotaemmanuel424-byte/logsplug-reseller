#!/bin/sh
set -eu
PORT="${PORT:-10000}"
sed -ri "s/^Listen[[:space:]]+[0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost[[:space:]]+\*:([0-9]+)>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf
printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null
apache2ctl -t
echo "Running PostgreSQL migrations..."
php /var/www/html/init_db.php
echo "Starting Apache on 0.0.0.0:${PORT}"
exec apache2-foreground
