FROM php:8.2-apache

# Installiere benötigte cURL-Bibliotheken für Mastodon-Requests
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config libssl-dev && rm -rf /var/lib/apt/lists/*

# Apache Konfiguration anpassen: DocumentRoot zeigt strikt auf /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Setze korrekte Arbeitsumgebung
WORKDIR /var/www/html

# Kopiere das gesamte Projekt in den Container
COPY . /var/www/html/

# Berechtigungen für den storage-Ordner setzen, damit PHP cachen kann
RUN mkdir -p storage/data storage/media config \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/config

EXPOSE 80