#!/bin/bash
cp /home/site/wwwroot/nginx-laravel.conf /etc/nginx/sites-available/default
service nginx reload
cd /home/site/wwwroot
php artisan config:cache
php artisan route:cache
php artisan migrate --force
# Sembrar la BD con datos de ejemplo una sola vez (marca: storage/.seeded).
if [ ! -f storage/.seeded ]; then
    php artisan db:seed --force && touch storage/.seeded
fi
php artisan storage:link
chmod -R 775 storage bootstrap/cache
