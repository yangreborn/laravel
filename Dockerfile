ARG PHP_VERSION
FROM php:${PHP_VERSION}-fpm-alpine

ARG TZ
ARG PHP_EXTENSIONS
ARG MORE_EXTENSION_INSTALLER
ARG ALPINE_REPOSITORIES
ARG HOST_UID

RUN if [ "${ALPINE_REPOSITORIES}" != "" ]; then \
        sed -i "s/dl-cdn.alpinelinux.org/${ALPINE_REPOSITORIES}/g" /etc/apk/repositories; \
    fi


RUN apk --no-cache add tzdata \
    && cp "/usr/share/zoneinfo/$TZ" /etc/localtime \
    && echo "$TZ" > /etc/timezone

COPY ./extensions /tmp/extensions

# Windows上构建使用
# RUN find /tmp/extensions/ -name "*.sh" | xargs dos2unix
# RUN chmod -R +w /tmp/extensions

WORKDIR /tmp/extensions

ENV EXTENSIONS=",${PHP_EXTENSIONS},"
ENV MC="-j$(nproc)"

RUN export MC="-j$(nproc)" \
    && chmod +x install.sh \
    && chmod +x "${MORE_EXTENSION_INSTALLER}" \
    && chmod +x tool.sh \
    && sh install.sh \
    && sh "${MORE_EXTENSION_INSTALLER}" \
    && sh tool.sh
    # && rm -rf /tmp/extensions

ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

RUN curl -sS https://mirrors.aliyun.com/composer/composer.phar -o /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

COPY ./conf/fonts/chinese /usr/share/fonts/

RUN adduser -D -u "${HOST_UID}" -g www www

USER www

RUN npm config set registry https://registry.npm.taobao.org

WORKDIR /var/www
