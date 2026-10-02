FROM php:8.3-apache

# --- Dépendances système requises par dompdf (gd) et phpoffice/phpword (zip, xml) ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
        unzip \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# --- Extensions PHP utilisées par l'application ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        mbstring \
        opcache

# --- Réglages PHP alignés sur le .htaccess du projet ---
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 300'; \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 20M'; \
        echo 'display_errors = On'; \
        echo 'display_startup_errors = On'; \
        echo 'error_reporting = E_ALL'; \
        echo 'date.timezone = Indian/Antananarivo'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# mod_rewrite pour les règles du .htaccess (administrateur, responsable, agent...)
RUN a2enmod rewrite

# Autoriser les surcharges .htaccess du projet
RUN printf '<Directory /var/www/html>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/app-override.conf \
    && a2enconf app-override

# Composer (pour régénérer vendor/ si besoin)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
