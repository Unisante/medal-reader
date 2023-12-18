FROM php:8.1-fpm

# Arguments defined in docker-compose.yml
#ARG user
#ARG uid

# Install system dependencies
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip git curl unzip

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd pdo pdo_mysql

RUN mkdir -p /var/www/html
WORKDIR /var/www/html
COPY . /var/www/html

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN chown -R www-data:www-data /var/www/html

RUN chmod -R 755 /var/www/html/storage

# Install Laravel dependencies
RUN composer install

# Set the user
#USER $user


CMD ["php-fpm"]

EXPOSE 9000
