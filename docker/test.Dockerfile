# Nur für lokale Tests: PHP mit den Erweiterungen, die Contao zum Installieren verlangt.
# 8.4 deckt Contao 6 ab; für Contao 5.3 mit --build-arg PHP=8.2 bauen.
ARG PHP=8.4
FROM php:${PHP}-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install intl zip gd pdo_mysql \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
