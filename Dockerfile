FROM php:8.3-fpm

WORKDIR /app

# Extensions PHP
RUN apt-get update && apt-get install -y \
    libicu-dev libzip-dev zip unzip git nginx \
    && docker-php-ext-install intl pdo_mysql opcache zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier et installer dépendances
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copier le reste
COPY . .

# Permissions
RUN chown -R www-data:www-data /app/var

# Config nginx
COPY nginx.conf /etc/nginx/nginx.conf

# Variables d'env (à surcharger sur Render)
ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 8080

# Script de démarrage
COPY start.sh /start.sh
RUN chmod +x /start.sh
CMD ["/start.sh"]
