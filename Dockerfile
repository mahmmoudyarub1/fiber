FROM php:8.2-apache

# تثبيت مكتبات الاتصال بقاعدة البيانات MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل الـ Rewrite Module في أباتشي
RUN a2enmod rewrite
