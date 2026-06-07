FROM php:5.6-apache

# Cài đặt các extension PHP cần thiết cho dự án cũ (mysql, mysqli)
RUN docker-php-ext-install mysql mysqli pdo pdo_mysql

# Bật mod_rewrite của Apache
RUN a2enmod rewrite

# Cập nhật DocumentRoot trỏ vào thư mục http/ thay vì thư mục gốc
ENV APACHE_DOCUMENT_ROOT /var/www/html/http
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Phân quyền cho thư mục web
RUN chown -R www-data:www-data /var/www/html
