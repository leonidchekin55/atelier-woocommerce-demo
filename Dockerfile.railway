FROM wordpress:php8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-server curl ca-certificates unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers

RUN curl -fsSL https://downloads.wordpress.org/plugin/woocommerce.11.2.0.zip -o /tmp/woocommerce.zip \
    && unzip -q /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/woocommerce.zip

COPY wp-content/themes/atelier-shop /usr/src/wordpress/wp-content/themes/atelier-shop

COPY railway/entrypoint.sh /usr/local/bin/atelier-entrypoint
RUN chmod +x /usr/local/bin/atelier-entrypoint \
    && mkdir -p /var/lib/atelier/mysql /var/lib/atelier/uploads \
    && chown -R www-data:www-data /var/lib/atelier/uploads

ENV WORDPRESS_DB_HOST=127.0.0.1 \
    WORDPRESS_DB_NAME=atelier \
    WORDPRESS_DB_USER=atelier \
    WORDPRESS_CONFIG_EXTRA="define('DISALLOW_FILE_EDIT', true); define('WP_AUTO_UPDATE_CORE', 'minor');"

ENTRYPOINT ["atelier-entrypoint"]
