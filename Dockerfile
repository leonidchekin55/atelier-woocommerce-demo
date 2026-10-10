FROM wordpress:php8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-server curl ca-certificates unzip git openssh-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers

RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

RUN curl -fsSL https://downloads.wordpress.org/plugin/woocommerce.11.2.0.zip -o /tmp/woocommerce.zip \
    && unzip -q /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/woocommerce.zip

# Bundle official Russian translations so the language switch works without
# a runtime download or a database change to the store's default language.
RUN set -eu; \
    wp_version="$(php -r 'include "/usr/src/wordpress/wp-includes/version.php"; echo $wp_version;')"; \
    core_url="$(curl -fsSL "https://api.wordpress.org/translations/core/1.0/?version=${wp_version}" | php -r '$d=json_decode(stream_get_contents(STDIN),true); foreach (($d["translations"] ?? []) as $t) if (($t["language"] ?? "") === "ru_RU") { echo $t["package"]; exit; } exit(1);')"; \
    mkdir -p /usr/src/wordpress/wp-content/languages /usr/src/wordpress/wp-content/languages/plugins; \
    curl -fsSL "$core_url" -o /tmp/core-ru.zip; \
    unzip -jo /tmp/core-ru.zip '*.mo' -d /usr/src/wordpress/wp-content/languages >/dev/null; \
    curl -fsSL https://downloads.wordpress.org/translation/plugin/woocommerce/11.2.0/ru_RU.zip -o /tmp/woocommerce-ru.zip; \
    unzip -jo /tmp/woocommerce-ru.zip '*.mo' -d /usr/src/wordpress/wp-content/languages/plugins >/dev/null; \
    rm /tmp/core-ru.zip /tmp/woocommerce-ru.zip

COPY wp-content/themes/atelier-shop /usr/src/wordpress/wp-content/themes/atelier-shop
COPY wp-content/mu-plugins /usr/src/wordpress/wp-content/mu-plugins
COPY scripts/atelier-state.php scripts/atelier-request.php scripts/github-known-hosts /usr/local/lib/
RUN mv /usr/local/lib/github-known-hosts /usr/local/lib/atelier-github-known-hosts \
    && printf 'auto_prepend_file=/usr/local/lib/atelier-request.php\n' > /usr/local/etc/php/conf.d/atelier-state.ini

COPY railway/entrypoint.sh /usr/local/bin/atelier-entrypoint
RUN chmod +x /usr/local/bin/atelier-entrypoint \
    && mkdir -p /var/lib/atelier/mysql /var/lib/atelier/uploads /var/lib/atelier/state \
    && chown -R www-data:www-data /var/lib/atelier/uploads /var/lib/atelier/state

ENV WORDPRESS_DB_HOST=127.0.0.1 \
    WORDPRESS_DB_NAME=atelier \
    WORDPRESS_DB_USER=atelier \
    WORDPRESS_CONFIG_EXTRA="define('DISALLOW_FILE_EDIT', true); define('WP_AUTO_UPDATE_CORE', 'minor');"

ENTRYPOINT ["atelier-entrypoint"]

# Google Search Console URL-prefix ownership verification file.
COPY google796020b0a86d58e7.html /usr/src/wordpress/google796020b0a86d58e7.html
