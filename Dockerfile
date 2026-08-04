FROM php:8.2-apache

ARG VERSION
ARG VCS_REF

LABEL \
    org.opencontainers.image.title="MastoFetch" \
    org.opencontainers.image.description="MastoFetch ist ein leichtgewichtiges, ressourcensparendes und datenschutzkonformes Widget in Vanilla-PHP, um chronologische Feeds von mehreren Mastodon-Accounts zu aggregieren und in einer ansprechenden Masonry-Grid-Ansicht darzustellen." \
    org.opencontainers.image.url="https://github.com/RonDevHub/MastoFetch" \
    org.opencontainers.image.source="https://codeberg.org/RonDevHub/MastoFetch" \
    org.opencontainers.image.documentation="https://codeberg.org/RonDevHub/MastoFetch" \
    org.opencontainers.image.version=$VERSION \
    org.opencontainers.image.revision=$VCS_REF \
    org.opencontainers.image.licenses="MIT" \
    org.opencontainers.image.authors="RonDevHub <ron.dev@posteo.de>"

RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libcurl4-openssl-dev \
    curl \
    && docker-php-ext-install dom curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/data /var/www/html/storage/media /var/www/html/config

RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

USER root