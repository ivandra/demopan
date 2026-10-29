FROM php:8.3-cli-bookworm
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update \
    && apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends \
       libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libzip-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 mysqli pdo_mysql gd mbstring zip exif dom \
    && rm -rf /var/lib/apt/lists/*
RUN printf 'short_open_tag=On\nmemory_limit=512M\nmax_execution_time=120\ndisplay_errors=Off\nlog_errors=On\nerror_reporting=E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE\nsendmail_path=/bin/true\n' > /usr/local/etc/php/conf.d/netcat-test.ini
WORKDIR /workspace
