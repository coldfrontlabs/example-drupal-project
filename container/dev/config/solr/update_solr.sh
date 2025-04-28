#!/usr/bin/env bash

# This file is managed by dropfort/dropfort_build.
# Modifications to this file will be overwritten by default.

export SEARCH_API_SOLR_ID=default_solr_server
export SEARCH_API_SOLR_API_VERSION='8'
export SEARCH_API_SOLR_CONFIGSET=drupal
export SOLR_NUM_SHARDS=1
export DRUSH_ARB_BACKUP_DIR=/var/www/html
export SOLR_CURL_OPTS=' --user solr:SolrRocks'

    # Update any missing field types.
drush  search-api-solr:install-missing-fieldtypes
    # 1. Get config
drush  search-api-solr:get-server-config $SEARCH_API_SOLR_ID $DRUSH_ARB_BACKUP_DIR/solrconf.zip
    # Load info about solr server.
export SEARCH_API_SOLR_SERVER=$(drush  config:get search_api.server.$SEARCH_API_SOLR_ID backend_config.connector_config.host --include-overridden --format=string)
export SOLR_SCHEME=$(drush  config:get search_api.server.$SEARCH_API_SOLR_ID backend_config.connector_config.scheme --include-overridden --format=string)
    # Can't update the config set if a collection is using it.
    # Delete the existing collection
curl $SOLR_CURL_OPTS  -X POST "$SOLR_SCHEME://$SEARCH_API_SOLR_SERVER:8983/solr/admin/collections?action=DELETE&name=$SEARCH_API_SOLR_CONFIGSET"
    # Can't upload the same config set twice so we remove that one first.
    # Delete existing config set
curl $SOLR_CURL_OPTS  -X POST "$SOLR_SCHEME://$SEARCH_API_SOLR_SERVER:8983/solr/admin/configs?action=DELETE&name=$SEARCH_API_SOLR_CONFIGSET"
    # Recreate config set
curl $SOLR_CURL_OPTS  -X POST --header 'Content-Type:application/octet-stream' --data-binary @- "$SOLR_SCHEME://$SEARCH_API_SOLR_SERVER:8983/solr/admin/configs?action=UPLOAD&name=$SEARCH_API_SOLR_CONFIGSET" < $DRUSH_ARB_BACKUP_DIR/solrconf.zip
    # Recreate the collection
curl $SOLR_CURL_OPTS  -X POST "$SOLR_SCHEME://$SEARCH_API_SOLR_SERVER:8983/solr/admin/collections?action=CREATE&name=$SEARCH_API_SOLR_CONFIGSET&autoAddReplicas=true&numShards=$SOLR_NUM_SHARDS&collection.configName=$SEARCH_API_SOLR_CONFIGSET"
    # 5. Reindex content
drush  search-api:clear
drush  search-api:disable-all
drush  search-api:enable-all
drush  search-api-solr:finalize-index --force
drush  search-api-reindex
drush  search-api-index
