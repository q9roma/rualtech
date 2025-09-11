#!/bin/bash

# Скрипт для исправления доступа к storage файлам через nginx alias

echo "=== 🔧 ИСПРАВЛЕНИЕ NGINX STORAGE ==="

cd /var/www/www-root/data/www/rualtech.ru

echo "1. Создаем backup nginx конфигурации..."
cp /etc/nginx/vhosts/www-root/rualtech.ru.conf /etc/nginx/vhosts/www-root/rualtech.ru.conf.backup

echo "2. Настраиваем storage symlink..."
rm -f public/storage
ln -s ../../storage/app/public public/storage
chown -h www-data:www-data public/storage

echo "3. Исправляем права на storage..."
chmod 755 storage/app/public/
chmod 755 storage/app/public/categories/
chmod 644 storage/app/public/categories/*
chown -R www-data:www-data storage/app/public/

echo "4. Добавляем nginx location блок для storage..."

# Проверяем есть ли уже блок location /storage/
if ! grep -q "location /storage/" /etc/nginx/vhosts/www-root/rualtech.ru.conf; then
    echo "Добавляем location блок..."
    
    # Создаем временный файл с новой конфигурацией
    awk '
    /location \/ {/ {
        print "    # Storage files handling"
        print "    location /storage/ {"
        print "        alias /var/www/www-root/data/www/rualtech.ru/storage/app/public/;"
        print "        expires 1y;"
        print "        add_header Cache-Control \"public, immutable\";"
        print "        access_log off;"
        print "        try_files $uri =404;"
        print "    }"
        print ""
    }
    {print}
    ' /etc/nginx/vhosts/www-root/rualtech.ru.conf > /tmp/nginx_new.conf
    
    # Заменяем конфигурацию
    mv /tmp/nginx_new.conf /etc/nginx/vhosts/www-root/rualtech.ru.conf
    
    echo "✅ Location блок добавлен"
else
    echo "✅ Location блок уже существует"
fi

echo "5. Проверяем nginx конфигурацию..."
if nginx -t; then
    echo "6. Перезагружаем nginx..."
    systemctl reload nginx
    echo "✅ Nginx перезагружен"
    
    echo "7. Тестируем доступ к storage файлу..."
    sleep 2
    curl -I https://rualtech.ru/storage/categories/01K4WSP3RBBQMB3811KF25WRS6.png
    
else
    echo "❌ Ошибка в конфигурации nginx, восстанавливаем backup"
    cp /etc/nginx/vhosts/www-root/rualtech.ru.conf.backup /etc/nginx/vhosts/www-root/rualtech.ru.conf
    systemctl reload nginx
    exit 1
fi

echo "=== ✅ ИСПРАВЛЕНИЕ ЗАВЕРШЕНО ==="
