#!/bin/bash

# Скрипт для исправления проблем с Git на сервере
# Запускать на сервере под root

echo "🔧 Исправляем проблемы с Git на сервере..."

# Переходим в директорию проекта
cd /var/www/www-root/data/www/rualtech.ru

echo "📂 Текущая директория: $(pwd)"

# Показываем текущий статус
echo "📊 Текущий статус Git:"
git status

# Исправляем проблему с владельцем директории
echo "🔐 Исправляем права доступа..."
chown -R root:root /var/www/www-root/data/www/rualtech.ru
chmod -R 755 /var/www/www-root/data/www/rualtech.ru

# Добавляем директорию в безопасные для Git
echo "🛡️ Добавляем директорию в безопасные для Git..."
git config --global --add safe.directory /var/www/www-root/data/www/rualtech.ru
git config --global --add safe.directory '*'

# Сбрасываем все локальные изменения
echo "🔄 Сбрасываем все локальные изменения..."
git reset --hard HEAD
git clean -fd

# Принудительно получаем последние изменения
echo "📥 Принудительно получаем последние изменения..."
git fetch origin main --force
git reset --hard origin/main

# Показываем что получилось
echo "✅ Проверяем результат..."
echo "📄 Содержимое файла home.blade.php:"
grep -n "Алтех\|Altech" resources/views/frontend/home.blade.php

# Очищаем кэши Laravel
echo "🧹 Очищаем кэши Laravel..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Перезапускаем веб-сервер
echo "🔄 Перезапускаем веб-сервер..."
systemctl reload nginx
systemctl reload apache2

echo "✅ Исправление завершено!"
echo "🌐 Проверьте сайт: https://rualtech.ru"
