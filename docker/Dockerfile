# Imagem base com PHP 8.4 + NGINX
FROM webdevops/php-nginx-dev:8.4

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
        unzip wget libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Diretório de trabalho
WORKDIR /app
COPY src /app

# Copia configuração do nginx
COPY docker/nginx/vhost.conf /opt/docker/etc/nginx/vhost.conf

# Expor portas
EXPOSE 80 443

# Supondo que /app já tenha os arquivos do Laravel
USER root

RUN mkdir -p /app/storage/framework/{sessions,views,cache/data} \
    /app/storage/logs \
    /app/bootstrap/cache && \
    chown -R 1000:1000 /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

USER application

RUN composer --version && \
    composer clear-cache && \
    COMPOSER_DISABLE_XDEBUG_WARN=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_PROCESS_TIMEOUT=1200 \
    composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    -vvv
