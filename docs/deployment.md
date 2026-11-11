# Выпуск и откат Алтех

Настройка закрытого тестового окружения: [GitHub Actions для staging](github-staging.md). Production-публикация не разрешена; нижеприведённые условия production остаются требованиями будущего выпуска.

Подготовленный процесс собирает один проверенный коммит, сохраняет резервную копию, применяет совместимые миграции и переключает символьную ссылку current. Выпуск выполняется с коротким окном обслуживания. Скрипты подготовлены в репозитории; сервер и GitHub Environments этим изменением не настраиваются.

## Что изменилось

Старые workflow с SSH-командами, диагностикой, встроенными паролями и созданием стандартных администраторов удалены. Остались Tests и Deploy verified release. Автоматического деплоя при push нет. Ручной workflow запускает тот же набор проверок, затем создаёт артефакт и только после этого получает секреты выбранного окружения. Выпуск разрешён только с main.

Все Actions закреплены SHA. В проверках выполняются Composer audit, npm audit, TypeScript, клиентская и SSR-сборки, тесты на SQLite/PHP 8.4 и MySQL/PHP 8.4, ShellCheck и проверка возвращения секретов в Git. Ошибка останавливает выпуск. CI не отключает advisories и не использует --ignore-platform-reqs.

## Обязательные условия первого выпуска

1. Ротировать секреты, ранее попавшие в историю: SSH-ключи деплоя, пароль базы, пароли административных аккаунтов, ключ приложения и иные credentials, если они использовались из старых env/workflow. Для APP_KEY сначала проверить использование шифрованных данных, подготовить перенос и завершить старые сессии. Не менять ключ вслепую. Удалить старый публичный SSH-ключ из authorized_keys после установки нового.
2. Проверить историю репозитория секрет-сканером. Удаление текущих файлов и переписывание истории не заменяют ротацию. Не считать прежние ключи безопасными из-за приватности репозитория.
3. Подготовить staging на отдельной базе и с MAIL_MAILER=log, без production-платежей и внешних уведомлений.
4. Установить актуальный security-patch PHP 8.4, Composer 2, Node.js 24, MySQL client, Python 3, curl, tar, gzip, flock, systemd и Nginx. У PHP нужны intl, bcmath, dom, curl, mbstring, zip, pdo_mysql и pcntl для очереди.
5. Выполнить и подтвердить восстановление резервной копии на отдельном экземпляре БД. Скрипты проверяют читаемость gzip/tar, но это не проверка восстановления.

После фактической ротации записать DEPLOY_SECRETS_ROTATED=true в серверный shared/.env. Пока значение false, deploy:check блокирует выпуск. Это подтверждение оператора, а не автоматическая проверка провайдеров.

## Размещение на сервере

Для каждого окружения используются отдельные каталоги:

```text
/srv/altech/production/
  current -> releases/<commit>-<timestamp>
  previous-release
  releases/
  incoming/<commit>/
  backups/
  shared/
    .env
    mysql.cnf
    storage/
```

Для staging заменить production на staging. Пользователь деплоя — altech, группа чтения приложения — www-data. Код доступен FPM на чтение; запись нужна только в shared/storage и bootstrap/cache каждого релиза. .env: режим 640, владелец altech:www-data; mysql.cnf и резервные копии: 600, доступны только altech. Родительские каталоги закрыты для посторонних пользователей. Для файлов, создаваемых FPM, настроить совместимые group/umask или default ACL, чтобы queue и deploy-пользователь могли работать с storage.

До первого запуска создать releases, incoming, backups, shared/storage/app/public, shared/storage/framework/cache/data, shared/storage/framework/sessions, shared/storage/framework/views и shared/storage/logs. Содержимое текущего хранилища копируется отдельно с проверкой прав и файлов. Не переносить старые env через артефакт.

Nginx должен обслуживать только /srv/altech/production/current/public. Шаблон: deploy/nginx.conf.example. Прямое исполнение любых других PHP-файлов запрещено, скрытые файлы закрыты. Проверить nginx -t до reload. HTTP перенаправляется на HTTPS. Staging должен быть закрыт от индексации и посторонних посетителей средствами сервера.

## Конфигурация окружения

Значения секретов задаются на сервере, а не в репозитории. Пример несекретных параметров:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shop.example.com
DB_CONNECTION=mysql
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=file
QUEUE_CONNECTION=database
STOREFRONT_INERTIA=false
INERTIA_SSR_ENABLED=true
INERTIA_SSR_URL=http://127.0.0.1:13714
TRUSTED_PROXIES=
DEPLOY_SECRETS_ROTATED=false
```

APP_URL задаётся без завершающего слеша. Для staging также APP_ENV=production, но другой URL, БД, секреты и MAIL_MAILER=log. Если staging и production на одном сервере, использовать SSR-порт 13715 для staging и 13714 для production. В unit staging задать INERTIA_SSR_PORT=13715 и соответствующий INERTIA_SSR_URL в .env. SSR слушает только 127.0.0.1.

TRUSTED_PROXIES содержит через запятую только известные адреса/CIDR reverse proxy. При прямом Nginx → FPM оставить пустым. Значения * и ** запрещены проверкой выпуска; X-Forwarded-Host не принимается даже от доверенных прокси. В production Laravel принимает только Host, указанный в APP_URL; health-check должен использовать этот домен.

В shared/mysql.cnf задать [client], host, user, password для резервного копирования. База определяется из конфигурации Laravel и передаётся отдельным аргументом; не добавлять database в mysql.cnf. Учётная запись backup должна иметь права для mysqldump с routines/triggers/events, но credentials не передаются аргументами процесса. Требуется совместимый с сервером MySQL/MariaDB клиент; для согласованного snapshot таблицы приложения должны использовать InnoDB.

## Службы

Использовать шаблоны deploy/altech-queue.service.example и deploy/altech-ssr.service.example. Установить их под именами altech-production-queue.service и altech-production-ssr.service. Для staging изменить имя, WorkingDirectory и SSR-порт. Проверить пути /usr/bin/php и /usr/bin/node и фактическую версию PHP. Службы читает root, приложение выполняется от altech. Настроить systemctl daemon-reload и enable; до первого current они могут оставаться остановленными.

Пользователю altech разрешить через отдельное sudoers-правило только точные команды systemctl stop/restart для этих четырёх service и reload php-fpm84.service. Не давать sudo на произвольный shell, systemctl или изменение unit-файлов. Шаблон deploy/sudoers.example нужно проверить visudo и адаптировать к пути systemctl на сервере.

Планировщик Laravel при появлении scheduled jobs запускается отдельным systemd timer; текущий релиз не содержит обязательных scheduled business jobs. Внешние интеграции не должны писать в базу во время резервирования/восстановления без согласованного процесса.

## GitHub Environments

Создать staging и production. Ограничить деплой веткой main. Для production настроить required reviewers и запрет self-review, если тариф/тип репозитория поддерживает эти ограничения. Если reviewers недоступны, не считать environment достаточным барьером: использовать внешний согласованный ручной выпуск с тем же артефактом.

В каждом окружении хранить DEPLOY_HOST, DEPLOY_USER, DEPLOY_KEY и DEPLOY_KNOWN_HOSTS. Последний содержит заранее проверенную запись known_hosts; ключ хоста сверяется по независимому каналу. Workflow не использует StrictHostKeyChecking=no или доверие ssh-keyscan во время деплоя. Старые repo-level HOST/USERNAME/SSH_KEY после перехода отозвать и удалить.

Настроить branch protection: проверки Tests обязательны для merge, прямой push в main ограничен. Эти настройки не создаются YAML-файлами автоматически.

## Первый релиз и назначение администратора

Миграция is_admin оставляет всех существующих пользователей без доступа к панели. Это намеренно: автоматически считать прежние учётные записи сотрудниками нельзя.

Первый deploy может остановиться после миграции на проверке отсутствия администратора. Подготовленный каталог releases/<commit>-<timestamp> сохраняется, сайт остаётся в maintenance. Через доверенную SSH-консоль перейти в этот каталог и выполнить:

```bash
php artisan admin:create "Имя сотрудника" "operator@example.com"
```

Пароль вводится скрыто и не передаётся аргументом. Минимум 12 символов, верхний/нижний регистр, цифры и символы. Для проверенного существующего аккаунта после смены ранее раскрытого пароля:

```bash
php artisan admin:access "operator@example.com"
```

Смена пароля существующего аккаунта: php artisan admin:password "operator@example.com". Новый пароль вводится скрыто; remember token отзывается. Сессии админки проверяются AuthenticateSession при следующем запросе. Отзыв доступа: php artisan admin:access "operator@example.com" --revoke. Не использовать старые create_admin.php, seeders или автоматическое назначение всем пользователям. Команда make:filament-user сама по себе не назначает is_admin.

После исправления причины первого сбоя выполнить php artisan up в подготовленном релизе и повторить workflow. До первого успешного переключения current приложение по новому document root ещё недоступно. Первую миграцию существующего production в эту структуру выполнять в согласованное окно обслуживания: старый vhost не переводится автоматически.

## Обычный выпуск

1. Пройти проверки на PR и испытать точный коммит на staging. Убедиться, что миграции обратно совместимы с предыдущим кодом.
2. Запустить Deploy verified release с main для нужного environment. Зафиксировать SHA; перед production проверить, что main не изменился после испытания staging.
3. Workflow проверяет код и зависимости, собирает артефакт с production vendor, production node_modules, public/build и bootstrap/ssr. Корневые диагностики, .env, .git, пользовательские загрузки и локальные базы не включаются.
4. Сервер проверяет checksum, запрещает пути traversal/ссылки в tar, проверяет PHP, конфигурацию и release SHA, останавливает очередь/SSR, включает maintenance, сохраняет БД и storage.
5. После миграций, кеширования и проверки администратора переключается current. Службы перезапускаются; /ready должен вернуть SHA именно этого релиза. При React-витрине дополнительно проверяется серверный HTML каталога.

При ошибке после включения maintenance скрипт пытается вернуть предыдущий код и службы, если предыдущий релиз существует. Миграции автоматически не откатываются. Ошибки восстановления нужно проверить вручную по логам. Не удалять старые релизы или backups автоматически до подтверждения здоровья и внешней копии резервных данных.

## Откат и восстановление

Для отката на предыдущий успешный релиз через SSH:

```bash
bash /srv/altech/production/current/scripts/rollback-release.sh production
```

Команда проверяет target, берёт lock, создаёт текущую резервную копию, переключает код и проверяет /ready. Данные не откатываются. После разрушающей миграции такой откат запрещён без плана совместимости; для текущего этапа миграция только добавляет поле.

Для восстановления данных сначала остановить трафик и все источники записи, сохранить копию текущего состояния, проверить sha256/gzip/tar backup. На отдельном экземпляре импортировать SQL в специально созданную пустую БД, восстановить storage в отдельный каталог, проверить количества записей, вход, каталог и загрузки. Только после успешной репетиции выполнять согласованное production-восстановление. SQL и storage должны относиться к одной резервной точке. Заказы после этой точки будут потеряны без отдельного переноса — восстановление не запускается автоматически.

## Приёмка перед разрешением production

- Ротация секретов подтверждена, старый доступ отозван.
- Пройдены локальные тесты, CI с MySQL/PHP 8.4 и аудит зависимостей.
- Staging-релиз проходит health, SSR, вход сотрудника, отказ покупателю, импорт и обработку очереди.
- Снимок данных восстановлен на отдельном экземпляре, сохранён результат проверки.
- Выполнен пробный откат к совместимому релизу.
- Проверены environment protection, branch protection и SSH host key.
- Мониторинг /ready, ошибок приложения, failed_jobs, служб SSR/queue и места на диске настроен.

Официальные ссылки: [развёртывание Laravel](https://laravel.com/docs/12.x/deployment), [GitHub Environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments), [доступ Filament](https://filamentphp.com/docs/4.x/users/overview).

## Изменения после прототипа Lunar

PHP 8.4 теперь обязателен и для разработки; нужны bcmath, exif, intl, gd. CI и release build обновлены. Lunar core 1.5 добавляет собственные таблицы; перед миграциями сохраняйте backup. LUNAR_PROTOTYPE_ENABLED=false обязателен для выпуска (проверяется deploy:check). Установка торгового ядра не означает приёмку checkout для production.
