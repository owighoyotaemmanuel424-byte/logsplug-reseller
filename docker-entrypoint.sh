#!/bin/sh
set -eu

# Initialize/upgrade the Neon PostgreSQL schema once when the container starts.
# Do not run schema DDL on every customer request.
php /var/www/html/init_db.php || echo "Schema initialization warning; continuing startup."

exec apache2-foreground
