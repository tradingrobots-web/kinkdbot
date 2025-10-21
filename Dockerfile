# Use official PHP CLI image (with built-in web server)
FROM php:8.1-cli

# Install PDO PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql

# Set working directory inside container
WORKDIR /app

# Copy all project files into container
COPY . .

# Expose the port Render expects
EXPOSE 10000

# Start PHP's built-in server on port 10000
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
