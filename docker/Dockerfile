# -------------------------------------------------------------
# Stage 1: Base PHP + Extensions (FrankenPHP)
# -------------------------------------------------------------
ARG PHP_BASE_IMAGE=dunglas/frankenphp:1-php8.4
ARG COMPOSER_IMAGE=composer:2

# Build containers have no direct internet access (deb.debian.org is not
# routable from CI, only nexus.jedu.ir is), so Debian packages and Composer
# metadata are pulled through the Nexus proxies instead.
ARG DEBIAN_MIRROR=https://nexus.jedu.ir/repository/apt-debian-proxy/
ARG DEBIAN_SECURITY_MIRROR=https://nexus.jedu.ir/repository/apt-debian-security-proxy/
ARG COMPOSER_REPOSITORY=https://nexus.jedu.ir/repository/composer-proxy/

FROM ${PHP_BASE_IMAGE} AS php-base

ARG DEBIAN_MIRROR
ARG DEBIAN_SECURITY_MIRROR

# 1. Install required system build libraries
RUN sed -i \
        -e "s|^URIs: http://deb.debian.org/debian$|URIs: ${DEBIAN_MIRROR}|" \
        -e "s|^URIs: http://deb.debian.org/debian-security$|URIs: ${DEBIAN_SECURITY_MIRROR}|" \
        /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::Retries=3 -o Acquire::http::Timeout=30 update \
    && apt-get install -y --no-install-recommends \
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

ARG COMPOSER_REPOSITORY

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock* ./
RUN --mount=type=cache,target=/root/.composer \
    composer config --global repos.packagist composer "${COMPOSER_REPOSITORY}" \
    && composer install --no-dev --no-interaction --no-autoloader --no-scripts

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
    mkdir -p /data/caddy /config/caddy /var/log/supervisor \
        /app/public \
        /app/bootstrap/cache \
        /app/storage/app/private \
        /app/storage/app/public \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs && \
    chown -R ${USER}:${USER} /data /config /var/log/supervisor /app

WORKDIR /app

COPY --chown=${USER}:${USER} --from=vendor-builder /app /app

# Configuration files
COPY docker/supervisord-worker.conf /etc/supervisor/conf.d/supervisord-worker.conf
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY docker/docker-entrypoint.sh /docker-entrypoint.sh

RUN chown -R ${USER}:${USER} /app/storage /app/bootstrap/cache /app/public \
    && chmod +x /docker-entrypoint.sh /app/artisan

USER ${USER}
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=5s --start-period=120s --retries=5 \
    CMD curl -f http://localhost:8000/up || exit 1
ENTRYPOINT ["/docker-entrypoint.sh"]
