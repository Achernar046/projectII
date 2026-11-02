FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite for .htaccess clean URLs
RUN a2enmod rewrite

# Set ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure Apache VirtualHost: allow .htaccess and set DirectoryIndex
RUN sed -i 's|<Directory /var/www/>|<Directory /var/www/html/>|g' /etc/apache2/apache2.conf || true
RUN printf '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.html index.php\n\
</Directory>\n' > /etc/apache2/conf-available/northwind.conf \
 && a2enconf northwind

# Ensure the default VirtualHost also uses DirectoryIndex index.html
RUN sed -i 's|DirectoryIndex index.php|DirectoryIndex index.html index.php|g' \
    /etc/apache2/mods-enabled/dir.conf 2>/dev/null || true

# Copy project source code
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html

# Expose default port
EXPOSE 80

# Dynamically bind Apache to $PORT assigned by Render (default 80)
CMD bash -c "\
  PORT=\${PORT:-80}; \
  sed -i \"s/Listen 80/Listen \$PORT/g\" /etc/apache2/ports.conf; \
  sed -i \"s/:80/:${PORT}/g\" /etc/apache2/sites-available/000-default.conf; \
  apache2-foreground"
