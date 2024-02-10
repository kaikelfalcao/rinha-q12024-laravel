FROM dunglas/frankenphp

RUN echo "variables_order = \"EGPCS\"" >> $PHP_INI_DIR/conf.d/990-php.ini

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

RUN install-php-extensions pcntl pdo pdo_pgsql

WORKDIR /app

COPY . /app
COPY Caddyfile /etc/caddy/Caddyfile

RUN composer install --no-ansi --no-dev --no-interaction --optimize-autoloader

RUN php artisan config:cache && php artisan route:cache

ENTRYPOINT ["php", "artisan", "octane:start", "--host=0.0.0.0", "--max-requests=100000"]




