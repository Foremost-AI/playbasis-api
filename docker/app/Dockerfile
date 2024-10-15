FROM php:5.6.40-fpm-stretch

# https://gist.github.com/guibranco/9342f83c5e51b7ec85d9046c652d1074

RUN sed -i -e 's/deb.debian.org/archive.debian.org/g' \
           -e 's|security.debian.org|archive.debian.org/|g' \
           -e '/stretch-updates/d' /etc/apt/sources.list

RUN apt-get update && apt-get install --yes --force-yes cron g++ gettext libicu-dev openssl libc-client-dev libkrb5-dev  libxml2-dev libfreetype6-dev libgd-dev libmcrypt-dev bzip2 libbz2-dev libtidy-dev libcurl4-openssl-dev libz-dev libmemcached-dev libxslt-dev

RUN docker-php-ext-install curl json && docker-php-ext-enable curl json

RUN pecl install mongo
RUN echo 'extension=mongo.so' | tee /usr/local/etc/php/conf.d/mongo.ini

COPY config/php.ini /usr/local/etc/php/
WORKDIR /var/www/api/

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

EXPOSE 9000
CMD ["php-fpm"]
