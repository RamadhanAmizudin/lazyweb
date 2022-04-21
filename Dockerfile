FROM php:7.2-apache

RUN apt-get update && apt-get install -y
RUN docker-php-ext-install mysqli pdo_mysql
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY . /var/www/html/
WORKDIR /var/www/html/
RUN a2enmod rewrite
RUN chmod 0777 /var/www/html/user/avatar
RUN chmod 0777 /var/www/html/templates_c