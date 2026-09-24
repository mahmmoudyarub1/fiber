FROM php:8.2-apache

# تثبيت متطلبات MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نسخ إعدادات الأباتشي
COPY default.conf /etc/apache2/sites-available/000-default.conf

# نسخ ملفات المشروع مباشرة داخل مجلد الويب الخاص بأباتشي
COPY html/ /var/www/html/

# تفعيل الـ Rewrite Module
RUN a2enmod rewrite
