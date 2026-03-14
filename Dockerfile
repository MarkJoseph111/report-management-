FROM php:8.2-cli

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN echo "output_buffering=On" > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /app
COPY . /app/

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080"]
