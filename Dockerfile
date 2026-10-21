# Dockerfile - Apache + PHP para Railway
FROM php:8.2-apache

# Extensiones
RUN docker-php-ext-install pdo_mysql

# FIX del error AH00534: apaga event y deja solo prefork + rewrite
RUN a2dismod mpm_event && a2enmod mpm_prefork && a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Escucha en el puerto que da Railway en TIEMPO DE EJECUCIÓN
EXPOSE 80
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT:-80}/g\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
