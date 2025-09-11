#!/bin/bash

# Скрипт для восстановления storage доступа

echo "=== 🔧 ВОССТАНАВЛИВАЕМ STORAGE ==="

cd /var/www/www-root/data/www/rualtech.ru

echo "1. Создаем абсолютный symlink..."
rm -f public/storage
ln -s /var/www/www-root/data/www/rualtech.ru/storage/app/public public/storage
chown -h www-data:www-data public/storage

echo "2. Проверяем права на файлы..."
chmod 755 storage/app/public/
chmod 755 storage/app/public/categories/
chmod 644 storage/app/public/categories/*
chown -R www-data:www-data storage/app/public/

echo "3. Проверяем symlink..."
ls -la public/ | grep storage

echo "4. Проверяем доступ к файлам через symlink..."
ls -la public/storage/categories/ | head -5

echo "5. Тестируем веб доступ..."
curl -I https://rualtech.ru/storage/categories/01K4WSP3RBBQMB3811KF25WRS6.png

echo "=== ✅ STORAGE ВОССТАНОВЛЕН ==="
