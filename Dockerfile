FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    libgd-dev \
    libmagickwand-dev \
    imagemagick \
    libldap2-dev \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    ldap \
    opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Imagick extension
RUN pecl install imagick && docker-php-ext-enable imagick

# Install Node.js (NodeSource) — required by the DOCX generation scripts
# (scripts/gen_*_docx.js), which PHP invokes via exec('node ...').
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install pdftk (PDF Toolkit) — required by PDF form-filling features,
# which PHP invokes via exec()/shell_exec('pdftk ...'). Debian's `pdftk`
# package is backed by pdftk-java and pulls in a headless JRE automatically.
RUN apt-get update && apt-get install -y --no-install-recommends \
    pdftk \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/temp_uploads \
    && mkdir -p /var/www/html/vendor \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Default command (will be overridden by docker-compose)
CMD ["php-fpm"]
