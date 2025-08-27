#!/bin/bash

# Скрипт автоматического развертывания для rualtech.ru
# Запускается на сервере для обновления проекта из GitHub

echo "🚀 Начинаем развертывание..."

# Переходим в директорию проекта
cd /var/www/www-root/data/www/rualtech.ru

# Исправляем права доступа для Git
echo "🔧 Исправляем права доступа для Git..."
git config --global --add safe.directory /var/www/www-root/data/www/rualtech.ru
chown -R root:root /var/www/www-root/data/www/rualtech.ru
chmod -R 755 /var/www/www-root/data/www/rualtech.ru

# Создаем резервную копию .env файла
cp .env .env.backup

# Получаем последние изменения из GitHub (с принудительным обновлением)
echo "📥 Получаем обновления из GitHub..."
git fetch origin main --force
git reset --hard origin/main
git clean -fd

# Восстанавливаем .env файл
cp .env.backup .env

# Устанавливаем/обновляем зависимости Composer
echo "📦 Обновляем зависимости Composer..."
composer install --no-dev --optimize-autoloader

# Устанавливаем/обновляем зависимости npm
echo "📦 Обновляем зависимости npm..."
npm install --production

# Собираем фронтенд
echo "🔨 Собираем фронтенд..."
npm run build

# Очистка и оптимизация Laravel
echo "🧹 Очистка кэша Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "⚡ Оптимизация Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Выполнение миграций БД (если есть новые)
echo "🗄️ Проверяем миграции БД..."
php artisan migrate --force

# Публикация ресурсов Filament (если нужно)
echo "📋 Публикуем ресурсы Filament..."
php artisan filament:assets

# Установка прав доступа
echo "🔐 Устанавливаем права доступа..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Агрессивная очистка всех кэшей
echo "🧹 Очищаем все кэши..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Перезапуск веб-сервера
echo "🔄 Перезапускаем веб-сервер..."
systemctl reload nginx
systemctl reload apache2

echo "✅ Развертывание завершено успешно!"
echo "🌐 Сайт обновлен: https://rualtech.ru"

# Опционально: отправка уведомления
# curl -X POST -H 'Content-type: application/json' \
#   --data '{"text":"🚀 Развертывание rualtech.ru завершено!"}' \
#   YOUR_SLACK_WEBHOOK_URL
