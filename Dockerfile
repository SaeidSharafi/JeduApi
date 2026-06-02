FROM php:8.4-cli-alpine

# Add nodejs here (needed by Gitea Actions to run checkout & cache steps)
RUN apk add --no-cache git unzip bash nodejs

# Download the helper script to easily install PHP extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install your exact required extensions
RUN install-php-extensions \
    dom \
    curl \
    libxml \
    mbstring \
    zip \
    pcntl \
    pdo \
    pdo_pgsql \
    bcmath \
    soap \
    intl \
    gd \
    exif \
    iconv \
    xdebug

RUN echo -e "memory_limit = -1\n\
xdebug.mode = coverage\n\
error_reporting = E_ALL\n\
display_errors = On\n\
date.timezone = UTC" > /usr/local/etc/php/conf.d/99-ci-overrides.ini

RUN apk add --no-cache openssh-client rsync

# Install Composer by copying it from the official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
