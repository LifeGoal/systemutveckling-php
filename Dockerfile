FROM php:8.4-apache

RUN a2enmod rewrite && docker-php-ext-install pdo_mysql

EXPOSE 80