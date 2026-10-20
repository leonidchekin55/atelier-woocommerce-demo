FROM wordpress:php8.3-apache@sha256:81a34254acaa6ff3b9a67268c2745e9a041c4094cbe5ccd35b75ed3bbdc033b5

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        mariadb-server=1:11.8.6-0+deb13u1 \
        curl=8.14.1-2+deb13u7 \
        ca-certificates=20250419 \
        unzip=6.0-29+deb13u1 \
        git=1:2.47.3-0+deb13u1 \
        openssh-client=1:10.0p1-7+deb13u4 \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers

RUN curl -fsSL https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o /usr/local/bin/wp \
    && echo 'ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c  /usr/local/bin/wp' | sha256sum -c - \
    && chmod +x /usr/local/bin/wp

RUN curl -fsSL https://downloads.wordpress.org/plugin/woocommerce.11.2.1.zip -o /tmp/woocommerce.zip \
    && unzip -q /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/woocommerce.zip

# FluentSMTP is part of the image so it survives Render Free container resets.
RUN curl -fsSL https://downloads.wordpress.org/plugin/fluent-smtp.2.4.1.zip -o /tmp/fluent-smtp.zip \
    && unzip -q /tmp/fluent-smtp.zip -d /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/fluent-smtp.zip

# Bundle official Russian translations so the language switch works without
# a runtime download or a database change to the store's default language.
RUN set -eu; \
    wp_version="$(php -r 'include "/usr/src/wordpress/wp-includes/version.php"; echo $wp_version;')"; \
    core_url="$(curl -fsSL "https://api.wordpress.org/translations/core/1.0/?version=${wp_version}" | php -r '$d=json_decode(stream_get_contents(STDIN),true); foreach (($d["translations"] ?? []) as $t) if (($t["language"] ?? "") === "ru_RU") { echo $t["package"]; exit; } exit(1);')"; \
    mkdir -p /usr/src/wordpress/wp-content/languages /usr/src/wordpress/wp-content/languages/plugins; \
    curl -fsSL "$core_url" -o /tmp/core-ru.zip; \
    unzip -jo /tmp/core-ru.zip '*.mo' -d /usr/src/wordpress/wp-content/languages >/dev/null; \
    curl -fsSL https://downloads.wordpress.org/translation/plugin/woocommerce/11.2.1/ru_RU.zip -o /tmp/woocommerce-ru.zip; \
    unzip -jo /tmp/woocommerce-ru.zip '*.mo' -d /usr/src/wordpress/wp-content/languages/plugins >/dev/null; \
    rm /tmp/core-ru.zip /tmp/woocommerce-ru.zip

COPY wp-content/themes/atelier-shop /usr/src/wordpress/wp-content/themes/atelier-shop
COPY wp-content/mu-plugins /usr/src/wordpress/wp-content/mu-plugins
COPY apache-wordpress.conf /etc/apache2/conf-enabled/atelier-wordpress.conf
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
    WORDPRESS_CONFIG_EXTRA="define('DISALLOW_FILE_EDIT', true); define('WP_AUTO_UPDATE_CORE', 'minor'); if (getenv('BREVO_API_KEY')) { define('FLUENTMAIL_SENDINBLUE_API_KEY', getenv('BREVO_API_KEY')); }"

ENTRYPOINT ["atelier-entrypoint"]

# Google Search Console URL-prefix ownership verification file.
COPY google796020b0a86d58e7.html /usr/src/wordpress/google796020b0a86d58e7.html
