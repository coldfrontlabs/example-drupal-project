FROM alpine:edge

RUN \
  # Install required packages
  echo "http://dl-3.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories && \
  apk --update --upgrade add \
  bash \
  fluxbox \
  git \
  supervisor \
  xvfb \
  x11vnc \
  curl \
  wget \
  openjdk8-jre \
  bash

# Install noVNC
RUN git clone --depth 1 https://github.com/novnc/noVNC.git /root/noVNC \
  && ln -s /root/noVNC/vnc.html /root/noVNC/index.html
COPY ../config/jmeter/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
ARG VNC_DISPLAY_WIDTH=1280
ARG VNC_DISPLAY_HEIGHT=1024
# Setup environment variables
ENV HOME=/root \
  DEBIAN_FRONTEND=noninteractive \
  LANG=en_US.UTF-8 \
  LANGUAGE=en_US.UTF-8 \
  LC_ALL=C.UTF-8 \
  DISPLAY=:0.0 \
  DISPLAY_WIDTH=$VNC_DISPLAY_WIDTH \
  DISPLAY_HEIGHT=$VNC_DISPLAY_HEIGHT
ARG JMETER_VERSION=5.6.3
RUN \
  curl -L "https://archive.apache.org/dist/jmeter/binaries/apache-jmeter-$JMETER_VERSION.tgz" >  /tmp/jmeter.tgz \
  && mkdir -p /opt \
  && tar -xvf /tmp/jmeter.tgz -C /opt \
  && rm /tmp/jmeter.tgz \
  && cd /etc/supervisor/conf.d \
  && echo '[program:jmeter]' >> supervisord.conf \
  && echo "command=/opt/apache-jmeter-$JMETER_VERSION/bin/jmeter" >> supervisord.conf \
  && echo 'autorestart=true' >> supervisord.conf

# Install extentions manager.
RUN \
  cd /opt/apache-jmeter-$JMETER_VERSION/lib/ext \
  && wget --content-disposition https://jmeter-plugins.org/get/
EXPOSE 8888
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
