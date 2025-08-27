#!/bin/bash

# Скрипт для исправления .env файла на сервере

echo "🔧 Создаем правильный .env файл на сервере..."

# Переходим в папку проекта
cd /var/www/rualtech.ru/

# Создаем .env файл из .env.production
echo "📋 Копируем .env.production в .env..."
cp .env.production .env

# Проверяем содержимое
echo "✅ Проверяем настройки базы данных:"
grep "DB_DATABASE" .env

# Очищаем кеш Laravel
echo "🧹 Очищаем кеш..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "🚀 .env файл создан! Проверяем статус сайта..."
