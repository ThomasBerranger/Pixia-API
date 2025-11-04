FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpq-dev libicu-dev libxml2-dev nginx && \
    docker-php-ext-install pdo pdo_mysql pdo_pgsql zip opcache intl xml && \
    docker-php-ext-enable opcache && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction && \
    chown -R www-data:www-data /var/www/html/var

# Config Nginx avec buffers augmentés pour Symfony
RUN echo 'server {\n\
    listen ${PORT:-8080};\n\
    root /var/www/html/public;\n\
    \n\
    # Buffers pour Symfony\n\
    fastcgi_buffer_size 128k;\n\
    fastcgi_buffers 4 256k;\n\
    fastcgi_busy_buffers_size 256k;\n\
    \n\
    location / {\n\
        try_files \$uri /index.php\$is_args\$args;\n\
    }\n\
    \n\
    location ~ ^/index\\.php(/|\$) {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_split_path_info ^(.+\\.php)(/.*)\$;\n\
        include fastcgi_params;\n\
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n\
        fastcgi_param DOCUMENT_ROOT \$document_root;\n\
        internal;\n\
    }\n\
}' > /etc/nginx/sites-available/default.template

RUN echo '#!/bin/bash\n\
set -e\n\
envsubst '\''$PORT'\'' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default\n\
php bin/console doctrine:migrations:migrate --no-interaction || true\n\
php-fpm -D\n\
exec nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]
