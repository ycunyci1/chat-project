FROM php:8.2.12-fpm

RUN apt-get update && apt-get install -y \
      apt-utils \
      libpq-dev \
      libpng-dev \
      libzip-dev \
      zip unzip \
      cron \
      git && \
      docker-php-ext-install pdo_mysql && \
      docker-php-ext-install bcmath && \
      docker-php-ext-install gd && \
      docker-php-ext-install zip && \
      apt-get clean && \
      rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY ./_docker/app/php.ini /usr/local/etc/php/conf.d/php.ini

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

# Добавление файла crontab
COPY ./_docker/app/crontab /etc/cron.d/my-crontab

# Предоставление прав на исполнение и запуск cron
RUN chmod 0644 /etc/cron.d/my-crontab
RUN crontab /etc/cron.d/my-crontab
RUN touch /var/log/cron.log
RUN service cron start

WORKDIR /var/www/chat
