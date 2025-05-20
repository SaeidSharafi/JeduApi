# Jedu EShop API

<p align="center">
<a href="https://github.com/SaeidSharafi/JeduApi/actions"><img src="https://github.com/SaeidSharafi/JeduApi/workflows/Tests/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/SaeidSharafi/JeduApi" > 
 <img src="https://codecov.io/gh/SaeidSharafi/JeduApi/graph/badge.svg?token=Tm2qNDCYx1"/> 
</a>
<a href="#"><img src="https://img.shields.io/badge/PHP-8.2-777BB4.svg?style=flat&logo=php" alt="PHP Version"></a>
<a href="#"><img src="https://img.shields.io/badge/License-Proprietary-red.svg" alt="License: ACECR Qazvin Proprietary"></a>
</p>

This project is a new Jedu EShop API built with Laravel, owned and maintained by جهاددانشگاهی قزوین (ACECR Qazvin Branch).

## Requirements

- PHP 8.2+
- PostgreSQL 15+
- Composer

## Setup with Sail

```bash
# Copy environment file
cp .env.example .env

# Install dependencies
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs

# Start the application
./vendor/bin/sail up -d

# Generate application key
./vendor/bin/sail artisan key:generate

# Run migrations
./vendor/bin/sail artisan migrate

# Run tests
./vendor/bin/sail pest
```
