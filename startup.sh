#!/bin/bash
cp /home/site/wwwroot/nginx-laravel.conf /etc/nginx/sites-available/default
service nginx reload
cd /home/site/wwwroot
php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan storage:link
chmod -R 775 storage bootstrap/cache
