FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev locales \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pgsql pdo_pgsql intl mbstring gd zip gettext \
    && echo 'fr_FR.UTF-8 UTF-8' >> /etc/locale.gen \
    && echo 'en_US.UTF-8 UTF-8' >> /etc/locale.gen \
    && locale-gen && rm -rf /var/lib/apt/lists/*
RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/local-server.conf && a2enconf local-server
