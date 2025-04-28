# This file is managed by dropfort/dropfort_build.
# Modifications to this file will be overwritten by default.

ARG SOLR_VERSION=8
FROM solr:$SOLR_VERSION

# @see https://github.com/docker-solr/docker-solr/issues/72#issuecomment-490131135
USER root
RUN mkdir -p /ssl \
  && chown 8983:8983 /ssl \
  && chmod ug+rwX /ssl
COPY ./config/solr/security.json /ssl/security.json
COPY ./config/solr/ssl/* /ssl/
RUN chown -R 8983:8983 /ssl/*
USER solr

#RUN keytool -importkeystore -srckeystore /ssl/solr-ssl.keystore.jks -destkeystore /ssl/solr-ssl.keystore.p12 -srcstoretype jks -deststoretype pkcs12
#RUN openssl pkcs12 -in /ssl/solr-ssl.keystore.p12 -out /ssl/solr-ssl.pem
