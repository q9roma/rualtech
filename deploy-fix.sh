#!/bin/bash

# Скрипт автоматического деплоя с исправлением прав и nginx storage

echo "=== 🚀 ДЕПЛОЙ RUALTECH.RU ==="

# Переходим в папку проекта
cd /var/www/www-root/data/www/rualtech.ru

echo "Обновляем код..."
git pull origin main

echo "Исправляем права доступа..."
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 777 storage/
chmod -R 777 bootstrap/cache/

echo "Настраиваем storage symlink..."
rm -f public/storage
ln -s ../../storage/app/public public/storage
chown -h www-data:www-data public/storage

echo "Настраиваем nginx для storage..."
# Создаем backup конфигурации
cp /etc/nginx/vhosts/www-root/rualtech.ru.conf /etc/nginx/vhosts/www-root/rualtech.ru.conf.backup

# Проверяем есть ли уже блок location /storage/
if ! grep -q "location /storage/" /etc/nginx/vhosts/www-root/rualtech.ru.conf; then
    echo "Добавляем location блок для storage..."
    
    # Находим строку с location / и добавляем перед ней блок для storage
    sed -i '/location \/ {/i\
    # Storage files handling\
    location /storage/ {\
        alias /var/www/www-root/data/www/rualtech.ru/storage/app/public/;\
        expires 1y;\
        add_header Cache-Control "public, immutable";\
        access_log off;\
        try_files $uri =404;\
    }\
' /etc/nginx/vhosts/www-root/rualtech.ru.conf

    echo "Проверяем nginx конфигурацию..."
    if nginx -t; then
        echo "Перезагружаем nginx..."
        systemctl reload nginx
        echo "✅ Nginx настроен для storage"
    else
        echo "❌ Ошибка в конфигурации nginx, восстанавливаем backup"
        cp /etc/nginx/vhosts/www-root/rualtech.ru.conf.backup /etc/nginx/vhosts/www-root/rualtech.ru.conf
        systemctl reload nginx
    fi
else
    echo "✅ Location блок для storage уже существует"
fi

echo "Очищаем кеши..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

echo "Тестируем storage доступ..."
sleep 2
echo "Проверка storage файла:"
curl -I https://rualtech.ru/storage/categories/01K4WSP3RBBQMB3811KF25WRS6.png

echo "✅ Деплой завершен!"
