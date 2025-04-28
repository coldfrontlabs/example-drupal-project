# This file is managed by dropfort/dropfort_build.
# Modifications to this file will be overwritten by default.

ARG ZOOKEEPER_TAG=latest
FROM zookeeper:$ZOOKEEPER_TAG

RUN chmod -R a+rwx /conf /data /logs /datalog
