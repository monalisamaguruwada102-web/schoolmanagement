FROM php:8.2-apache

# Update and install required dependencies
RUN apt-get update && apt-get install -y \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Install the mysqli extension so PHP can talk to your MySQL database
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli

# Copy the source code into the container's web root
COPY . /var/www/html/

# Expose port 80 since Render expects web services to bind to port 80 or PORT env variable. 
EXPOSE 80

# Change ownership of the web files just in case
RUN chown -R www-data:www-data /var/www/html
