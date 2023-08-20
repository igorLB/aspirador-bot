FROM php:8.2-cli

ENV TZ="America/Sao_Paulo"
ENV ACCEPT_EULA=Y

USER root

LABEL maintainer=igorcedrolb@gmail.com

RUN apt-get update \
    && apt-get install -y --no-install-recommends --no-install-suggests \
    zlib1g-dev  \
    g++  \
    git  \
    libicu-dev  \
    zip  \
    libzip-dev  \
    zip \
    libpq-dev \
    && docker-php-ext-install intl opcache pdo pdo_mysql pgsql pdo_pgsql \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip \
    && apt purge -y --auto-remove

# install composer
COPY --from=composer:2.5.8 /usr/bin/composer /usr/bin/composer

# copy php.ini
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/php.ini
COPY docker/php/php-cli.ini $PHP_INI_DIR/conf.d/php-cli.ini

# install symfony
RUN curl -sS https://get.symfony.com/cli/installer | bash
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

#COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
#RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# fix permissions
RUN useradd igor \
    && usermod -u 1000 igor && groupmod -g 1000 igor \
    && chown -R igor:igor /var/www \
    && chsh -s /bin/bash igor

USER igor

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER 1

COPY composer.json .
COPY composer.lock .
RUN composer install --no-scripts

ADD . .

USER root

RUN composer dumpautoload --optimize

#ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

CMD ["SHELL_VERBOSITY=2", "php", "bin/console", "app:daily-report"]