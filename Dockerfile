FROM php:8.2-apache

# Activer le module PostgreSQL pour PHP
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

# Copier tous les fichiers dans le dossier Apache
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Apache écoute sur le port 10000 (Render)
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-enabled/000-default.conf

# Autoriser .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

EXPOSE 10000

CMD ["apache2-foreground"]
