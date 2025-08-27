#!/bin/bash

# Простой и надёжный скрипт деплоя
echo "🚀 Начинаем простой деплой..."

# Переходим в директорию проекта
cd /var/www/www-root/data/www/rualtech.ru || exit 1

echo "📂 Текущая директория: $(pwd)"

# Показываем текущий коммит
echo "📊 Текущий коммит:"
git log --oneline -1 || echo "Git log failed"

# Исправляем права доступа для Git
echo "🔧 Исправляем права доступа Git..."
git config --global --add safe.directory /var/www/www-root/data/www/rualtech.ru
git config --global --add safe.directory '*'

# Сохраняем .env
echo "💾 Сохраняем .env файл..."
cp .env .env.backup 2>/dev/null || echo "No .env file to backup"

# Принудительно обновляем код
echo "📥 Обновляем код..."
git fetch origin main --force
git reset --hard origin/main
git clean -fd

# Восстанавливаем .env
echo "🔄 Восстанавливаем .env файл..."
cp .env.backup .env 2>/dev/null || echo "No .env backup to restore"

# Проверяем что файл обновился
echo "✅ Проверяем файл home.blade.php:"
if [ -f "resources/views/frontend/home.blade.php" ]; then
    grep -n "Алтех\|Altech" resources/views/frontend/home.blade.php
else
    echo "❌ Файл home.blade.php не найден!"
fi

# Очищаем кэши
echo "🧹 Очищаем кэши..."
php artisan cache:clear 2>/dev/null || echo "Cache clear failed"
php artisan view:clear 2>/dev/null || echo "View clear failed"
php artisan config:clear 2>/dev/null || echo "Config clear failed"

# Перезапускаем веб-сервер
echo "🔄 Перезапускаем веб-сервер..."
systemctl reload nginx 2>/dev/null || echo "Nginx reload failed"
systemctl reload apache2 2>/dev/null || echo "Apache reload failed"

echo "✅ Деплой завершён!"
echo "🌐 Проверьте сайт: https://rualtech.ru"
