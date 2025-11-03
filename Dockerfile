FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpq-dev nginx gettext-base && \
    docker-php-ext-install pdo pdo_mysql pdo_pgsql zip opcache && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction && \
    chown -R www-data:www-data /var/www/html/var

# Template Nginx (sera remplacé au runtime)
RUN echo 'server {\n\
    listen ${PORT};\n\
    root /var/www/html/public;\n\
    location / {\n\
        try_files $uri /index.php$is_args$args;\n\
    }\n\
    location ~ ^/index\.php(/|$) {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_split_path_info ^(.+\.php)(/.*)$;\n\
        include fastcgi_params;\n\
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;\n\
        internal;\n\
    }\n\
}' > /etc/nginx/sites-available/default.template

RUN echo '#!/bin/bash\n\
envsubst '\''$PORT'\'' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default\n\
php bin/console doctrine:migrations:migrate --no-interaction\n\
php-fpm -D\n\
nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]
