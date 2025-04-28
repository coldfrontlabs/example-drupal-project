# This file is managed by dropfort/dropfort_build.
# Modifications to this file will be overwritten by default.

ARG PHP_VERSION=8.3
FROM webdevops/php-apache-dev:$PHP_VERSION
LABEL maintainer="Coldfront Labs Inc. <info@coldfrontlabs.ca>"
WORKDIR /var/www/html

# Allow PHP to read environment variables
ENV fpm.pool.clear_env no
ENV php.variables_order 'EGPCS'
ENV php.realpath_cache_size 256k
ENV PHP_OPCACHE_MEMORY_CONSUMPTION 256M

# Set docroot to match Drupal's
# @todo decide if this is still required given this is set in the docker compose file.
ENV WEB_DOCUMENT_ROOT /var/www/html/web

# Allow lazy-load and other requests on external hostname to work within
# the container instance.
COPY config/apache/listen_8000.conf /opt/docker/etc/httpd/conf.d/listen_8000.conf
COPY config/apache/proxy_vhost.conf /opt/docker/etc/httpd/conf.d/proxy_vhost.conf

# Add setup scripts for Drupal settings files.
COPY scripts/entrypoint/drupal-setup.sh /opt/docker/provision/entrypoint.d/25-drupal-setup.sh
COPY config/drupal/default.settings.php /tmp/default.settings.php
COPY config/drupal/default.settings.local.php /tmp/default.settings.local.php
COPY config/drupal/default.sites.php /tmp/default.sites.php

# Add composer bin directory to path.
ENV PATH "$PATH:/var/www/html/bin"

# Update packages.
ENV DEBIAN_FRONTEND=noninteractive
RUN apt update -y; apt upgrade -y; \
  # @todo determine which keys are insecure and update them.; \
  apt update --allow-insecure-repositories --allow-unauthenticated; \
  apt install -y ca-certificates curl gnupg

# Fix arm/Apple Silicon support.
ARG ARCHITECTURE="linux/x86_64"
RUN if [ "$ARCHITECTURE" = "linux/arm64" ] ; then wget -O "/usr/local/bin/go-replace" "https://github.com/webdevops/goreplace/releases/download/1.1.2/gr-arm64-linux" \
  && chmod +x "/usr/local/bin/go-replace" \
  && "/usr/local/bin/go-replace" --version ; fi

# Install profiling tools
RUN pecl install xhprof igbinary
COPY config/php/php.ini /opt/docker/etc/php/php.ini

# Add MySQL Client for drush usage.
ARG MARIADB_TAG=10.11
RUN curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | bash -s -- --skip-maxscale --mariadb-server-version=$MARIADB_TAG; \
  # Create folder for storing private files outside webroot.; \
  mkdir -p /var/www/files/private; \
  # Add xdebug configuration.; \
  touch /tmp/xdebug_remote_log; \
  chmod a+rw /tmp/xdebug_remote_log

# Add git, drush, phpcs, symfony, composer autocomplete support.
ENV SHELL bash
RUN composer global require bamarni/symfony-console-autocomplete; \
  curl -skL https://raw.githubusercontent.com/git/git/master/contrib/completion/git-completion.bash -o ~/.git-completion.bash; \
  echo 'source ~/.git-completion.bash' >> ~/.bashrc; \
  mkdir -p /etc/bash_completion.d/composer; composer completion bash > /etc/bash_completion.d/composer/completion.bash || true;

# Add nodejs, git-lfs and other dev tools.
ARG NODE_MAJOR_VERSION="20"
RUN mkdir -p /etc/apt/keyrings; \
  curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg; \
  echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR_VERSION.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list; \
  apt update -y; \
  apt install nodejs build-essential git-lfs jq mariadb-client bash-completion -y; \
  ln -s /usr/bin/mariadb /usr/bin/mysql || true;

# Customize NPM if need be.
ARG NPM_MAJOR_VERSION=""
RUN if [ ! -z "$NPM_MAJOR_VERSION" ] ; then npm i -g npm@$NPM_MAJOR_VERSION ; fi
