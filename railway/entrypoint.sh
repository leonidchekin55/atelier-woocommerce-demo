#!/bin/bash
set -Eeuo pipefail

DATA_DIR=/var/lib/atelier
mkdir -p "$DATA_DIR/mysql" "$DATA_DIR/uploads" /run/mysqld
chown -R mysql:mysql /run/mysqld "$DATA_DIR/mysql"
chown -R www-data:www-data "$DATA_DIR/uploads"

if [ ! -d "$DATA_DIR/mysql/mysql" ]; then
  mariadb-install-db --user=mysql --datadir="$DATA_DIR/mysql" --auth-root-authentication-method=normal --skip-test-db >/dev/null
fi

mysqld --user=mysql --datadir="$DATA_DIR/mysql" --socket=/run/mysqld/mysqld.sock --bind-address=127.0.0.1 --port=3306 &
for attempt in $(seq 1 60); do
  if mariadb-admin --socket=/run/mysqld/mysqld.sock ping >/dev/null 2>&1; then break; fi
  sleep 1
done
mariadb-admin --socket=/run/mysqld/mysqld.sock ping >/dev/null

mariadb --socket=/run/mysqld/mysqld.sock -e "CREATE DATABASE IF NOT EXISTS \`$WORDPRESS_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$WORDPRESS_DB_USER'@'127.0.0.1' IDENTIFIED BY '$WORDPRESS_DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$WORDPRESS_DB_NAME\`.* TO '$WORDPRESS_DB_USER'@'127.0.0.1'; FLUSH PRIVILEGES;"

mkdir -p /var/www/html/wp-content/uploads
if [ -d /var/www/html/wp-content/uploads ] && [ ! -L /var/www/html/wp-content/uploads ]; then
  cp -a /var/www/html/wp-content/uploads/. "$DATA_DIR/uploads/"
  rm -rf /var/www/html/wp-content/uploads
fi
ln -sfn "$DATA_DIR/uploads" /var/www/html/wp-content/uploads
chown -R www-data:www-data "$DATA_DIR/uploads"

exec docker-entrypoint.sh apache2-foreground
