# Utiliser une image de base avec Apache
FROM php:8.2-apache


RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libonig-dev libxml2-dev zip unzip && \
    docker-php-ext-install pdo pdo_mysql

# Activer les modules Apache nécessaires et modifier la configuration d'Apache pour écouter sur le port 3000
RUN a2enmod rewrite headers ssl && \
    sed -i 's/80/8000/g' /etc/apache2/ports.conf && \
    sed -i 's/:80/:8000/g' /etc/apache2/sites-enabled/000-default.conf && \
    sed -i 's/:80/:8000/g' /etc/apache2/sites-available/000-default.conf

# Ajout du certificat SSL auto-signé
RUN mkdir -p /etc/apache2/ssl
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=FR/ST=Paris/L=Paris/O=EcoRide/CN=localhost"

# Ajout configuration SSL
RUN echo '<VirtualHost *:443>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html/public\n\
    SSLEngine on\n\
    SSLCertificateFile /etc/apache2/ssl/apache.crt\n\
    SSLCertificateKeyFile /etc/apache2/ssl/apache.key\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/default-ssl.conf \
    && a2ensite default-ssl

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définit le répertoire de travail
WORKDIR /var/www/html

# Copie les fichiers de l'application mais ignore les fichiers inutiles
COPY . .

# Donne les permissions finales
RUN chown -R www-data:www-data /var/www/html

RUN pecl install mongodb && docker-php-ext-enable mongodb

USER www-data

# Installe les dépendances
#RUN composer install --no-scripts --optimize-autoloader --ignore-platform-req=ext-mongodb
RUN composer install --no-scripts --optimize-autoloader
RUN composer dump-autoload

USER root

# Expose le port 8000
EXPOSE 8000

# Commande par défaut
CMD ["apache2-foreground"]