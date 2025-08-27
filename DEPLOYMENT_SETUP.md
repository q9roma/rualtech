# Инструкция по настройке SSH для автоматического развертывания

## 1. Подключение к серверу rualtech.ru

Подключитесь к серверу через SSH или панель управления Reg.ru

## 2. Создание SSH-ключа

```bash
# Создаем новый SSH-ключ для GitHub
ssh-keygen -t rsa -b 4096 -C "github-actions-deploy" -f ~/.ssh/github_deploy_key

# НЕ устанавливайте пароль для ключа (просто нажмите Enter)
```

## 3. Настройка авторизации

```bash
# Добавляем публичный ключ в authorized_keys
cat ~/.ssh/github_deploy_key.pub >> ~/.ssh/authorized_keys

# Устанавливаем правильные права
chmod 600 ~/.ssh/authorized_keys
chmod 600 ~/.ssh/github_deploy_key
chmod 644 ~/.ssh/github_deploy_key.pub
```

## 4. Получение приватного ключа для GitHub

```bash
# Выводим приватный ключ (СКОПИРУЙТЕ ВЕСЬ ТЕКСТ)
cat ~/.ssh/github_deploy_key
```

## 5. Настройка репозитория на сервере

```bash
# Переходим в директорию сайта
cd /var/www/www-root/data/www/

# Делаем резервную копию текущего кода
mv rualtech.ru rualtech.ru.backup.$(date +%Y%m%d)

# Клонируем репозиторий с GitHub
git clone https://github.com/q9roma/rualtech.git rualtech.ru

# Переходим в папку проекта
cd rualtech.ru

# Создаем .env файл из backup
cp ../rualtech.ru.backup.*/​.env .env

# Устанавливаем зависимости
composer install --no-dev --optimize-autoloader
npm install --production
npm run build

# Устанавливаем права доступа
chown -R www-data:www-data /var/www/www-root/data/www/rualtech.ru
chmod -R 775 storage bootstrap/cache
chmod +x deploy.sh

# Очищаем кэш Laravel
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

## 6. Настройка GitHub Secrets

Зайдите на https://github.com/q9roma/rualtech/settings/secrets/actions

Добавьте секреты:
- HOST: rualtech.ru (или IP сервера)
- USERNAME: root (или ваше имя пользователя SSH)
- SSH_KEY: содержимое файла ~/.ssh/github_deploy_key (приватный ключ)

## 7. Тестирование

После настройки всех секретов запустите GitHub Action вручную:
https://github.com/q9roma/rualtech/actions
