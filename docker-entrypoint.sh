#!/bin/sh
set -eu

# Render web services provide PORT (default 10000). Configure Apache at
# container startup so the public listener always matches Render's port.
PORT="${PORT:-10000}"

# Keep Apache's listener and vhost on the same runtime port.
sed -ri "s/^Listen[[:space:]]+[0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost[[:space:]]+\\*:([0-9]+)>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

# Avoid the noisy FQDN warning and fail fast if the generated Apache config is invalid.
printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null
apache2ctl -t

echo "Starting Apache on 0.0.0.0:${PORT}"

# Initialize/upgrade the Neon PostgreSQL schema once when the container starts.
# Do not run schema DDL on every customer request.
php /var/www/html/init_db.php || echo "Schema initialization warning; continuing startup."

exec apache2-foreground
