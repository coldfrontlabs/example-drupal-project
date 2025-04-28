# This file is managed by dropfort/dropfort_build.
# Modifications to this file will be overwritten by default.
ARG PHP_VERSION=8.3
FROM containerssh/agent AS agent

FROM webdevops/php-apache:$PHP_VERSION
COPY --from=agent /usr/bin/containerssh-agent /usr/bin/containerssh-agent
LABEL maintainer="Coldfront Labs Inc. <info@coldfrontlabs.ca>"
ARG CONTAINER_DIR=/var/www/html
WORKDIR $CONTAINER_DIR

# Allow PHP to read environment variables
ENV fpm.pool.clear_env no
ENV php.variables_order 'EGPCS'
ENV php.realpath_cache_size 256k
ENV PHP_OPCACHE_MEMORY_CONSUMPTION 256M
ENV DF_DRUPAL_SETTING_FILE_PRIVATE_PATH /var/www/files/private
ENV DF_DRUPAL_SETTING_FILE_TEMP_PATH /var/www/files/tmp

# Enable additional apache modules.
RUN a2enmod remoteip

# Set docroot to match Drupal's
# @todo decide if this is still required given this is set in the docker compose file.
ENV WEB_DOCUMENT_ROOT $CONTAINER_DIR/web

# Add setup scripts for Drupal settings files.
COPY container/release/config/drupal/default.settings.php web/sites/default/settings.php
COPY container/release/config/drupal/default.settings.local.php web/sites/default/settings.local.php
COPY container/release/config/drupal/default.sites.php web/sites/sites.php

# Allow lazy-load and other requests on external hostname to work within
# the container instance.
COPY container/release/config/apache/listen_8080.conf /opt/docker/etc/httpd/conf.d/listen_8080.conf

# Add composer bin directory to path.
ENV PATH "$PATH:$CONTAINER_DIR/bin:$CONTAINER_DIR/vendor/bin"

# Fix apache permissions so they work between debian and rhel.
RUN groupadd -g 48 apache; \
  usermod -g apache www-data

# Add MySQL Client for drush usage.
ARG MARIADB_TAG=10.11
RUN curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | bash -s -- --skip-maxscale --mariadb-server-version=$MARIADB_TAG

# Update packages.
# Install PHP library dependencies.;
ENV DEBIAN_FRONTEND=noninteractive
RUN apt update -y; apt upgrade -y; \
  apt update --allow-insecure-repositories --allow-unauthenticated; \
  apt install -y ca-certificates curl gnupg; \
  apt install -y libmemcached-dev zlib1g-dev libssl-dev; \
  apt install which mariadb-client -y; \
  ln -s /usr/bin/mariadb /usr/bin/mysql || true; \
  pecl uninstall memcached; \
  pecl install igbinary; \
  pecl install --configureoptions 'with-libmemcached-dir="no" with-zlib-dir="no" with-system-fastlz="no" enable-memcached-igbinary="yes" enable-memcached-msgpack="no" enable-memcached-json="no" enable-memcached-protocol="no" enable-memcached-sasl="yes" enable-memcached-session="yes"' memcached; \
  docker-php-ext-enable igbinary

# Fix arm/Apple Silicon support.
ARG ARCHITECTURE="linux/x86_64"
RUN if [ "$ARCHITECTURE" = "linux/arm64" ] ; then wget -O "/usr/local/bin/go-replace" "https://github.com/webdevops/goreplace/releases/download/1.1.2/gr-arm64-linux" \
  && chmod +x "/usr/local/bin/go-replace" \
  && "/usr/local/bin/go-replace" --version ; fi

# Add various base apache configs
COPY container/release/config/apache/listen_8080.conf /opt/docker/etc/httpd/conf.d/listen_8080.conf
COPY container/release/config/apache/drupal.conf /opt/docker/etc/httpd/vhost.common.d/drupal.conf
COPY container/release/config/apache/webuser.conf /opt/docker/etc/httpd/conf.d/webuser.conf
COPY container/release/config/apache/remoteip.conf /opt/docker/etc/httpd/conf.d/remoteip.conf

# Copy the application build artifacts into place.
COPY ./ $CONTAINER_DIR

# Create folder for storing private files outside webroot.
# Setup the public files directory.
RUN mkdir -p /var/www/files/private /var/www/files/public /var/www/files/tmp /var/www/files/secrets; \
  mv $CONTAINER_DIR/web/sites/default/files /var/www/files/public; \
  rm -rf $CONTAINER_DIR/web/sites/default/files; \
  ln -s /var/www/files/public $CONTAINER_DIR/web/sites/default/files; \
  rm -rf $CONTAINER_DIR/container
