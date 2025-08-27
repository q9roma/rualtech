# Инструкция по настройке SSH для GitHub Actions

## 1. Генерация SSH ключа на сервере

Подключитесь к серверу через веб-панель хостинга или другим способом и выполните:

```bash
# Генерируем новый SSH ключ
ssh-keygen -t rsa -b 4096 -C "github-actions@rualtech.ru" -f ~/.ssh/github_actions_key

# Добавляем публичный ключ в authorized_keys
cat ~/.ssh/github_actions_key.pub >> ~/.ssh/authorized_keys

# Устанавливаем правильные права
chmod 600 ~/.ssh/authorized_keys
chmod 600 ~/.ssh/github_actions_key
chmod 700 ~/.ssh

# Показываем приватный ключ для копирования
echo "=== ПРИВАТНЫЙ КЛЮЧ (скопируйте весь текст) ==="
cat ~/.ssh/github_actions_key
echo "=== КОНЕЦ КЛЮЧА ==="
```

## 2. Добавление ключа в GitHub Secrets

1. Идите на https://github.com/q9roma/rualtech/settings/secrets/actions
2. Нажмите "New repository secret"
3. Name: `SERVER_SSH_KEY`
4. Value: вставьте весь приватный ключ (включая -----BEGIN и -----END строки)
5. Нажмите "Add secret"

## 3. Тестирование подключения

После настройки запустите workflow снова.

## Альтернативный способ - через панель хостинга

Если у вас есть доступ к панели управления хостингом:
1. Найдите раздел SSH ключи
2. Создайте новый ключ для GitHub Actions
3. Скопируйте приватный ключ в GitHub Secrets
