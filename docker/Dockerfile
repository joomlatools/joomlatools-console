FROM php:7.2-apache

# Disable remote database security requirements.
ENV JOOMLA_INSTALLATION_DISABLE_LOCALHOST_CHECK=1

# Enable Apache Rewrite Module
RUN a2enmod rewrite

# Install PHP extensions
RUN set -ex; \
  \
  savedAptMark="$(apt-mark showmanual)"; \
  \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    libbz2-dev \
    libjpeg-dev \
    libldap2-dev \
    libmemcached-dev \
    libpng-dev \
    libpq-dev \
  ; \
  \
  docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr; \
  debMultiarch="$(dpkg-architecture --query DEB_BUILD_MULTIARCH)"; \
  docker-php-ext-configure ldap --with-libdir="lib/$debMultiarch"; \
  docker-php-ext-install \
    bz2 \
    gd \
    ldap \
    mysqli \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    zip \
  ; \
  \
# pecl will claim success even if one install fails, so we need to perform each install separately
  pecl install APCu-5.1.12; \
  pecl install memcached-3.0.4; \
  pecl install redis-4.1.1; \
  \
  docker-php-ext-enable \
    apcu \
    memcached \
    redis \
  ; \
  \
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
  apt-mark auto '.*' > /dev/null; \
  apt-mark manual $savedAptMark; \
  ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
    | awk '/=>/ { print $3 }' \
    | sort -u \
    | xargs -r dpkg-query -S \
    | cut -d: -f1 \
    | sort -u \
    | xargs -rt apt-mark manual; \
  \
  apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
  rm -rf /var/lib/apt/lists/*

# avoids the apache unknown servername domain warning
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# set composer
COPY --from=composer:1.7.2 /usr/bin/composer /usr/bin/composer

# set joomlatools
RUN apt-get update \
  && apt-get install -y \
  git \
  unzip \
  mysql-client

VOLUME /joomlatools
WORKDIR /joomlatools
ENV PATH="$PATH:/joomlatools/bin"

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

CMD ["apache2-foreground"]