FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpq-dev libicu-dev libxml2-dev && \
    docker-php-ext-install pdo pdo_mysql pdo_pgsql zip intl && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction && \
    chown -R www-data:www-data /var/www/html/var && \
    php bin/console cache:clear --env=prod

CMD php bin/console doctrine:migrations:migrate --no-interaction && \
    php -S 0.0.0.0:${PORT:-8080} -t public
