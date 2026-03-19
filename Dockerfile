# AulaBot - PHP no Render
FROM php:8.2-apache

# Extensão PDO PostgreSQL (para Supabase)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Ativar mod_rewrite
RUN a2enmod rewrite

# Permitir .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copiar código
COPY . /var/www/html/

# Script de arranque (Render usa PORT=10000)
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

ENTRYPOINT ["/start.sh"]
