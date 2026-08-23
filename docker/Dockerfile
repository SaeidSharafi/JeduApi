# -------------------------------------------------------------
# Stage 1: Base PHP + Extensions (FrankenPHP)
# -------------------------------------------------------------
ARG PHP_BASE_IMAGE=dunglas/frankenphp:1-php8.4
ARG COMPOSER_IMAGE=composer:2

FROM ${PHP_BASE_IMAGE} AS php-base

# 1. Install required system build libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    supervisor \
    curl \
    unzip \
    git \
    libicu-dev \
    libpq-dev \
    libxml2-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    postgresql-client \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# 2. Compile Core PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_mysql \
        soap \
        gd \
        zip

# 3. Install Redis extension directly from source (Bypasses PECL completely)
RUN git clone --depth=1 -b 6.1.0 https://git.jedu.ir/kuro/phpredis.git /usr/src/php/ext/redis \
    && docker-php-ext-install -j$(nproc) redis \
    && rm -rf /usr/src/php/ext/redis

# -------------------------------------------------------------
# Stage 2: Vendor Build
# -------------------------------------------------------------
FROM ${COMPOSER_IMAGE} AS composer

FROM php-base AS vendor-builder
WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock* ./
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-dev --no-interaction --no-autoloader --no-scripts

COPY . /app

RUN --mount=type=cache,target=/root/.composer \
    composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

# -------------------------------------------------------------
# Stage 3: Production Image
# -------------------------------------------------------------
FROM php-base AS production

ARG USER=appuser
ARG UID=1000
ARG GID=1000

ENV DEPLOYMENT_TYPE=web
ENV XDG_CONFIG_HOME=/config
ENV XDG_DATA_HOME=/data
ENV SERVER_NAME=:8000

RUN groupadd -g ${GID} ${USER} && \
    useradd -u ${UID} -g ${USER} -m -s /bin/bash ${USER} && \
    setcap -r /usr/local/bin/frankenphp 2>/dev/null || true && \
    mkdir -p /data/caddy /config/caddy /var/log/supervisor /app/storage/logs /app/bootstrap/cache /app/public && \
    chown -R ${USER}:${USER} /data /config /var/log/supervisor

WORKDIR /app

COPY --chown=${USER}:${USER} --from=vendor-builder /app /app

# Configuration files
COPY docker/supervisord-worker.conf /etc/supervisor/conf.d/supervisord-worker.conf
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY docker/docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh /app/artisan && \
    chown -R ${USER}:${USER} /app/storage /app/bootstrap/cache /app/public

USER ${USER}
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=5s --start-period=120s --retries=5 \
    CMD curl -f http://localhost:8000/up || exit 1
ENTRYPOINT ["/docker-entrypoint.sh"]
