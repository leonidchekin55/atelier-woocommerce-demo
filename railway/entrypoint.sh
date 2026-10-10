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
mysql_pid=$!
for attempt in $(seq 1 60); do
  if mariadb-admin --socket=/run/mysqld/mysqld.sock ping >/dev/null 2>&1; then break; fi
  sleep 1
done
mariadb-admin --socket=/run/mysqld/mysqld.sock ping >/dev/null

# Validate identifiers and pass the password through stdin, never process arguments.
if [[ ! "$WORDPRESS_DB_NAME" =~ ^[a-zA-Z0-9_]+$ || ! "$WORDPRESS_DB_USER" =~ ^[a-zA-Z0-9_]+$ ]]; then
  echo 'Invalid database identifiers.' >&2
  exit 1
fi
db_password="${WORDPRESS_DB_PASSWORD//\\/\\\\}"
db_password="${db_password//\'/\'\'}"
if ! mariadb --socket=/run/mysqld/mysqld.sock >/dev/null 2>&1 <<SQL
CREATE DATABASE IF NOT EXISTS \`$WORDPRESS_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$WORDPRESS_DB_USER'@'127.0.0.1' IDENTIFIED BY '$db_password';
GRANT ALL PRIVILEGES ON \`$WORDPRESS_DB_NAME\`.* TO '$WORDPRESS_DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
then
  echo 'Database provisioning failed.' >&2
  exit 1
fi
unset db_password

if [[ -n "${ATELIER_STATE_REPO:-}" ]]; then
  php /usr/local/lib/atelier-state.php restore
fi

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
    kill -0 "$mysql_pid" 2>/dev/null || exit 1
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

  wp eval 'update_option("active_plugins", array_values(array_filter((array)get_option("active_plugins"), function ($plugin) { return !preg_match("~^atelier-(export|bridge|hold)-plugin/~", $plugin); })));' "${wp_args[@]}" >/dev/null
  wp plugin is-active woocommerce "${wp_args[@]}" >/dev/null 2>&1 || wp plugin activate woocommerce "${wp_args[@]}"
  wp theme is-active atelier-shop "${wp_args[@]}" >/dev/null 2>&1 || wp theme activate atelier-shop "${wp_args[@]}"
  wp option update home "$site_url" "${wp_args[@]}" >/dev/null
  wp option update siteurl "$site_url" "${wp_args[@]}" >/dev/null
  if [[ -n "${ATELIER_STATE_REPO:-}" ]]; then
    wp option delete atelier_product_media_seeded "${wp_args[@]}" >/dev/null 2>&1 || true
  fi
  wp eval 'do_action("init");' "${wp_args[@]}"
  if [[ -n "${ATELIER_STATE_REPO:-}" ]]; then
    php /usr/local/lib/atelier-state.php save
  fi
  # WP-CLI runs as root; Apache must still be able to write into dated folders.
  chown -R www-data:www-data "$DATA_DIR/uploads"
  chown -R www-data:www-data "$DATA_DIR/state" 2>/dev/null || true
  echo 'Atelier demo bootstrap finished.'
fi

exec docker-entrypoint.sh apache2-foreground
