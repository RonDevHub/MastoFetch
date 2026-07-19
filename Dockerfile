FROM php:8.2-apache

# Installiere benötigte System-Abhängigkeiten für die DOM- und cURL-Erweiterungen
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    curl \
    && docker-php-ext-install dom curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache Module aktivieren (für .htaccess Nutzung im public-Ordner)
RUN a2enmod rewrite

# Setze das Webroot-Verzeichnis auf den öffentlichen 'public' Ordner um
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Kopiere den Anwendungscode in den Container
COPY . /var/www/html

# Erstelle die benötigten Ordnerstrukturen direkt im Image vor
RUN mkdir -p /var/www/html/storage/data /var/www/html/storage/media /var/www/html/config

# Setze die Besitzrechte konsequent auf www-data (UID 33)
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

USER root