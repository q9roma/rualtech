# КОМАНДЫ ДЛЯ РУЧНОГО ВОССТАНОВЛЕНИЯ САЙТА
# Выполняйте по порядку на сервере

# 1. Перейти в папку сайта
cd /var/www/www-root/data/www/rualtech.ru

# 2. Проверить что там сейчас
ls -la

# 3. Перезапустить веб-сервисы
sudo systemctl restart nginx
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm || sudo systemctl restart php8.0-fpm || sudo systemctl restart php7.4-fpm

# 4. Проверить статус сервисов
sudo systemctl status nginx
sudo systemctl status apache2

# 5. Если файлы сайта есть - очистить кеши Laravel
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 6. Проверить права доступа
sudo chown -R www-data:www-data /var/www/www-root/data/www/rualtech.ru
sudo chmod -R 755 /var/www/www-root/data/www/rualtech.ru
sudo chmod -R 775 /var/www/www-root/data/www/rualtech.ru/storage
sudo chmod -R 775 /var/www/www-root/data/www/rualtech.ru/bootstrap/cache

# 7. Если файлов нет - скачать свежие
rm -rf *
wget https://github.com/q9roma/rualtech/archive/refs/heads/main.zip
unzip main.zip
mv rualtech-main/* .
mv rualtech-main/.* . 2>/dev/null || true
rm -rf rualtech-main main.zip

# 8. Установить зависимости Composer (если файлы скачивали заново)
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader

# 9. Создать .env файл (если его нет)
cp .env.example .env || cat > .env << 'EOF'
APP_NAME=Altech
APP_ENV=production
APP_KEY=base64:your_app_key_here
APP_DEBUG=false
APP_URL=https://rualtech.ru

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
EOF

# 10. Финальная проверка
curl -I http://localhost
curl -I https://rualtech.ru

# 11. Посмотреть логи ошибок если сайт не работает
tail -20 /var/log/nginx/error.log
tail -20 storage/logs/laravel.log
