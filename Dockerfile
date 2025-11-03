# Étape 1 : base Apache + PHP 8.3
FROM php:8.3-apache

# Dossier de travail
WORKDIR /var/www/html

# Installer les extensions PHP nécessaires à Symfony
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpq-dev libonig-dev zip \
    && docker-php-ext-install intl pdo pdo_mysql opcache zip \
    && a2enmod rewrite

# Installer Composer depuis l’image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier les fichiers de ton projet
COPY . .

# Installer les dépendances Symfony sans dev
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Donner les bons droits d’accès
RUN chown -R www-data:www-data var

# Symfony utilise /public comme racine web
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Expose le port par défaut
EXPOSE 80

# Démarre Apache
CMD ["apache2-foreground"]
