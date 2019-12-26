FROM php:7.3-cli

RUN requirements="unzip procps inotify-tools curl libpng-dev libzip-dev zlib1g-dev libicu-dev libcurl3-dev libzstd-dev g++" \
    && requirementsToRemove="libpng-dev libzip-dev zlib1g-dev libicu-dev libcurl3-dev libzstd-dev g++" \
    && apt-get update \
    && apt-get install -y --no-install-recommends $requirements \
    && rm -rf /var/lib/apt/lists/*

# Install PHP Extensions
RUN docker-php-ext-install zip \
  && docker-php-ext-install opcache \
  && docker-php-ext-enable opcache \
  && docker-php-ext-configure intl \
  && docker-php-ext-install intl \
  && docker-php-ext-install pdo pdo_mysql \
  && docker-php-ext-install mbstring \
  && docker-php-ext-install iconv \
  && docker-php-ext-install curl \
  && docker-php-ext-install gd

# IGBinary for Redis
RUN pecl install igbinary \
    && docker-php-ext-enable igbinary

# Install Redis
RUN yes | pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php -r "if (hash_file('SHA384', 'composer-setup.php') === rtrim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
  && php composer-setup.php \
  && php -r "unlink('composer-setup.php');" \
  && mv composer.phar /usr/local/bin/composer

# Download RoadRunner
ENV RR_VERSION 1.5.2
RUN mkdir /tmp/rr \
  && cd /tmp/rr \
  && echo "{\"require\":{\"spiral/roadrunner\":\"${RR_VERSION}\"}}" >> composer.json \
  && composer install \
  && vendor/bin/rr get-binary -l /usr/local/bin \
  && rm -rf /tmp/rr

# Copy RoadRunner config
RUN mkdir /etc/roadrunner
COPY .rr.yaml /etc/roadrunner/.rr.yaml

# Clear libraries used only for build
RUN apt-get purge --auto-remove -y $requirementsToRemove

WORKDIR /var/www/app

# ENTRYPOINT ["php", "/var/www/app/bootstrap.php"]
ENTRYPOINT ["/usr/local/bin/rr", "serve", "-v", "-d", "-c", "/etc/roadrunner/.rr.yaml"]
