FROM ubuntu:20.04

ENV TIMEZONE=UTC

RUN ln -snf /usr/share/zoneinfo/$TIMEZONE /etc/localtime && \
    echo $TIMEZONE > /etc/timezone

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip zip git libcap2-bin software-properties-common netcat-traditional wget \
    && mkdir -p ~/.gnupg \
    && echo "disable-ipv6" >> ~/.gnupg/dirmngr.conf \
    && apt-key adv --homedir ~/.gnupg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys E5267A6C \
    && apt-key adv --homedir ~/.gnupg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C300EE8C \
    && echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu focal main" > /etc/apt/sources.list.d/ppa_ondrej_php.list 

RUN apt-get update \
    && apt-get install -y php7.3-cli \
       php7.3-gd php7.3-zip php7.3-curl \
       php7.3-mysql php7.3-mbstring php7.3-xml \
       php7.3-xdebug

RUN apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY php.ini /etc/php7.3/php.ini
COPY xdebug.ini /etc/php7.3/conf.d/xdebug.ini

RUN wget https://getcomposer.org/composer-stable.phar -O /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

ARG EXTENSION_PATH
ARG OPENCART_PATH
ARG OPENCART_STORAGE_PATH
ARG DOCKER_USER
ARG APP_PORT
ARG USER_ID
ARG GROUP_ID

COPY setup-container /usr/local/bin/setup-container
RUN chmod +x /usr/local/bin/setup-container

RUN groupadd -o -g ${GROUP_ID} $DOCKER_USER \
    && useradd -o -l -u ${USER_ID} -g $DOCKER_USER $DOCKER_USER \
    && install -d -m 0777 -o $DOCKER_USER -g $DOCKER_USER /home/$DOCKER_USER
USER $DOCKER_USER

VOLUME $OPENCART_PATH
VOLUME $OPENCART_STORAGE_PATH

RUN mkdir -p $OPENCART_STORAGE_PATH \
    && mkdir -p $OPENCART_PATH \
    && chown -R $DOCKER_USER:$DOCKER_USER $OPENCART_PATH $OPENCART_STORAGE_PATH \
    && chmod -R 777 $OPENCART_PATH $OPENCART_STORAGE_PATH

WORKDIR $EXTENSION_PATH

EXPOSE $APP_PORT
ENTRYPOINT ["setup-container"]
