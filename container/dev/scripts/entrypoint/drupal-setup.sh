#!/bin/bash

# This file is managed by dropfort/dropfort_build.
# Modifications to this file will be overwritten by default.

# Ensure the sites/default, files and private folder exists at minimum.
mkdir -p "$WEB_DOCUMENT_ROOT/sites/default/files/private"
mkdir -p "$WEB_DOCUMENT_ROOT/sites/default/files/translations"

# Set the sites.php file.
cp /tmp/default.sites.php "$WEB_DOCUMENT_ROOT/sites/sites.php"

for directory in $WEB_DOCUMENT_ROOT/sites/*/;
do
  if [ -d "$directory" ] # if it's a directory
  then
    # Ensure additional sites, files and private folder exists.
    mkdir -p "$directory/files/private"
    mkdir -p "$directory/files/translations"
    chown -R application:application "$directory/files"
    chmod -R ug+rwX "$directory/files"

    # Set the settings.php file.
    cp /tmp/default.settings.php "$directory/settings.php"

    # Provision a settings.local.php file if there isn't one already.
    if [ ! -f "$directory/settings.local.php" ]; then
      cp /tmp/default.settings.local.php "$directory/settings.local.php"
    fi

    # Remove the development.services.yml file if there is one.
    # Developers should use the services/default directory instead.
    if [ -f "$directory/development.services.yml" ]; then
      rm -f "$directory/development.services.yml"
    fi
  fi
done
