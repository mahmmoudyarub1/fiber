FROM php:8.2-apache

# تثبيت مكتبات MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نسخ ملف إعدادات أباتشي وتفعيله
COPY default.conf /etc/apache2/sites-available/000-default.conf

# تفعيل الـ Rewrite Module
RUN a2enmod rewrite
