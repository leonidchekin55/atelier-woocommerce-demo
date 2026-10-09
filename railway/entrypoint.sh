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

# Render Free can discard its container filesystem when the service spins down
# or redeploys. When credentials are supplied, rebuild the disposable demo
# automatically instead of leaving visitors at the WordPress installer.
if [[ -n "${ATELIER_ADMIN_PASSWORD:-}" && -n "${ATELIER_ADMIN_USER:-}" ]]; then
  # Reuse the official image entrypoint's file/config preparation without
  # exposing its installer page before the one-time setup is complete.
  cat >/usr/local/bin/apache2-prepare <<'EOF'
#!/bin/sh
exit 0
EOF
  chmod +x /usr/local/bin/apache2-prepare
  docker-entrypoint.sh apache2-prepare
  rm -f /usr/local/bin/apache2-prepare

  if [[ ! -f /var/www/html/wp-config.php ]]; then
    echo 'WordPress configuration was not created by the official entrypoint.' >&2
    exit 1
  fi

  site_url="${ATELIER_SITE_URL:-http://localhost:${PORT:-8080}}"
  wp_args=(--path=/var/www/html --url="$site_url" --allow-root)
  database_ready=0
  for attempt in $(seq 1 300); do
    if wp db check "${wp_args[@]}" >/dev/null 2>&1; then
      database_ready=1
      break
    fi
    kill -0 "$apache_pid" 2>/dev/null || exit 1
    sleep 1
  done

  if [[ "$database_ready" -eq 0 ]]; then
    echo 'WordPress database did not become available.' >&2
    exit 1
  fi

  if ! wp core is-installed "${wp_args[@]}" >/dev/null 2>&1; then
    if ! printf '%s\n' "$ATELIER_ADMIN_PASSWORD" | wp core install \
      --url="$site_url" \
      --title="${ATELIER_SITE_TITLE:-Atelier Home}" \
      --admin_user="$ATELIER_ADMIN_USER" \
      --admin_email="${ATELIER_ADMIN_EMAIL:-atelier-preview@example.com}" \
      --skip-email --prompt=admin_password "${wp_args[@]}" >/dev/null 2>&1; then
      echo 'WordPress installation failed during demo bootstrap.' >&2
      exit 1
    fi
  fi

  wp plugin is-active woocommerce "${wp_args[@]}" >/dev/null 2>&1 || wp plugin activate woocommerce "${wp_args[@]}"
  wp theme is-active atelier-shop "${wp_args[@]}" >/dev/null 2>&1 || wp theme activate atelier-shop "${wp_args[@]}"
  wp eval 'do_action("init");' "${wp_args[@]}"
  echo 'Atelier demo bootstrap finished.'
fi

exec docker-entrypoint.sh apache2-foreground
