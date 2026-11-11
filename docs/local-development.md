# Локальная разработка Алтех

## Требования

PHP 8.4 с расширениями из Composer (включая bcmath, exif, intl, gd), Composer 2, Node.js 24, npm и MySQL. После подключения Lunar системный PHP 8.2 из XAMPP больше не совместим. Для Windows подготовлен изолированный runtime .tools/php84. В текущем checkout база настроена как altech. Не копируйте production-секреты для разработки.

## Запуск на Windows

Запустите MySQL в XAMPP. Из C:\xampp\htdocs\altech:

```powershell
composer install
npm ci
```

Существующий dev-local.bat копирует .env.local поверх .env. Если в .env есть нужные индивидуальные настройки, сохраните их перед запуском. Скрипт использует локальную базу и MAIL_MAILER=log, затем запускает PHP на порту 8000:

```powershell
.\dev-local.bat
```

Во втором терминале:

```powershell
npm run dev
```

Откройте http://127.0.0.1:8000/catalog. При APP_ENV=local React-каталог включён по умолчанию. Админка остаётся на /admin. Каталог читает существующие товары; установка frontend не создаёт демонстрационные товары и не меняет базу.

Оба скрипта dev-local.bat и start-server.bat теперь запускают один и тот же сервер на порту 8000. start-server.bat можно открыть двойным щелчком: при ошибке окно останется открытым. Проверка .env.local использует dotenv и поддерживает LF/CRLF; прежняя проверка findstr могла ошибочно отклонять корректный файл. Очистка кеша базы при каждом старте больше не выполняется. Если порт 8000 занят, скрипт сообщит об ошибке вместо незаметного перехода на 8001.

Если базы нет, создайте пустую локальную базу altech, затем выполните .\php-local.bat artisan migrate. Для полноценного каталога загрузите CSV через /admin/products → «Импорт из файла». Новая админка работает на React/shadcn независимо от переключателя витрины. Если используются фоновые импорты, в отдельном терминале запустите .\php-local.bat artisan queue:work. Не запускайте рабочую очередь, подключённую к production.

После обновления backend выполните .\php-local.bat artisan migrate для добавления is_admin. Миграция не назначает права существующим пользователям. Для нового сотрудника используйте .\php-local.bat artisan admin:create "Имя" "email@example.com" и введите сложный пароль в скрытом приглашении. Для проверенного существующего аккаунта: .\php-local.bat artisan admin:access "email@example.com". Обычный пользователь и результат make:filament-user без явного назначения is_admin не имеют доступа к панели.

.env.local больше не хранится в Git, но существующий локальный файл сохранён на диске. Для нового checkout скопируйте .env.example в .env.local, задайте APP_ENV=local, APP_URL=http://127.0.0.1:8000, параметры отдельной локальной MySQL-базы и MAIL_MAILER=log. Выполните .\php-local.bat artisan key:generate --env=local, затем запустите dev-local.bat. Скрипт откажется запускаться без файла или при другом APP_ENV.

## Сборка без Vite dev server

```powershell
npm run typecheck
npm run build
```

После остановки Vite убедитесь, что не остался public/hot от аварийного завершения. Laravel должен использовать public/build/manifest.json.

## Проверка SSR

```powershell
npm run build:ssr
npm run ssr
```

В .env задайте INERTIA_SSR_ENABLED=true и выполните .\php-local.bat artisan config:clear. Node-процесс должен работать параллельно с PHP. На Windows используем npm run ssr напрямую, без зависимости от pcntl. При отключённом SSR каталог работает на клиенте; публикация требует проверки SSR на staging.

## Переключатель витрины

```dotenv
STOREFRONT_INERTIA=true
INERTIA_SSR_ENABLED=false
```

STOREFRONT_INERTIA=false возвращает все публичные страницы на Blade по прежним адресам. После изменения выполните .\php-local.bat artisan config:clear и обновите страницу полностью. В production новая витрина по умолчанию выключена. Данные товаров и URL карточек общие для обоих вариантов.

## Проверки

Обновление XLSX-прайса: `/admin/catalog-prices`. Для обработки нужны Python с `openpyxl` и `CATALOG_PYTHON` в локальном env. `composer dev` слушает обе очереди (`default,catalog-prices`). При отдельном запуске PHP-сервера запустите также `php-local.bat artisan queue:work --queue=catalog-prices --tries=1 --timeout=600`. Повторите запуск обработчика после перезагрузки компьютера. Подробности — в [инструкции импорта](catalog-preview.md).

```powershell
npm run typecheck
npm run build:ssr
.\php-local.bat artisan test
 .\composer-local.bat validate --no-check-publish
npm audit
.\composer-local.bat audit
```

PHPUnit использует SQLite в памяти и APP_URL=http://localhost; локальная MySQL-база тестами не изменяется. Если config был закеширован для другого окружения, сначала выполните .\php-local.bat artisan config:clear.

## Структура frontend

- resources/js/storefront.tsx — клиентская точка входа.
- resources/js/ssr.tsx — серверный рендеринг.
- resources/js/pages — страницы Inertia.
- resources/js/layouts — общие каркасы страниц.
- resources/js/components/ui — локальные исходники shadcn/ui.
- resources/js/lib/utils.ts — объединение классов Tailwind.
- resources/css/storefront.css — тема и стили новой витрины.
- components.json — конфигурация генератора shadcn/ui.

Добавление компонентов: npx shadcn@latest add имя-компонента. После генерации проверьте импорт cn: он должен ссылаться на @/lib/utils, а не добавлять отдельный пакет cn. Запустите typecheck и сборку.

## Публичные страницы после переноса

React обслуживает /, /catalog, /product/{id}, /services, /services/{category}, /services/{category}/{service}, /blog, /blog/{slug} и /{slug} для активных CMS-страниц. Filament остаётся на /admin. Существующие маршруты, опубликованные данные и формы заявок сохранены.

Компоненты интерфейса — стандартные shadcn/ui new-york с baseColor neutral. Пользовательская зелёная палитра удалена. Общие карточки, пагинация и диалоги находятся в resources/js/components/storefront. Публичные DTO и очистка CMS HTML — app/Support/Storefront.php.

Заявки товаров и услуг отправляются в существующий /contact/order-service с CSRF и ограничением частоты. Общая форма связи использует прежнюю интеграцию Bitrix24; ей нужен доступ к внешнему сервису. Метрика подключается только в production и учитывает переходы Inertia.

## Lunar и Windows PHP 8.4

Корзина /cart и оформление /checkout включены локально. Подробности и ограничения: [прототип Lunar](lunar-prototype.md). В production LUNAR_PROTOTYPE_ENABLED должен оставаться false; deploy:check отклонит включённый экспериментальный checkout.

Launcher использует --no-reload: это сохраняет системное окружение дочернего PHP-процесса на Windows и устраняет Failed to listen. После изменения .env остановите dev-local.bat через Ctrl+C и запустите заново. XAMPP нужен для MySQL, а не для PHP этого проекта.

## React-админка

Вход: /admin/login. Права сотрудников и команды admin:create / admin:access прежние. После обновления выполните npm ci, npm run build:ssr и php artisan optimize:clear; перезапустите PHP, SSR и очередь. Новой миграции данных для интерфейса не требуется.

Импорт принимает CSV UTF-8 и Windows-1251 до 20 МБ с разделителями ;, запятая или табуляция. Пара (артикул, наименование) определяет обновление товара. Цены с пробелами и десятичной запятой поддерживаются. Отчёты хранятся на приватном local-диске, доступны только автору; старые отчёты Filament открываются из уведомлений. Воркеры должны иметь доступ к тому же приватному диску. Для database/redis/beanstalkd retry_after не меньше 660 секунд; импорт имеет timeout 600 секунд, tries=1 и блокировку повторного выполнения. Для SQS установите visibility timeout больше 600 секунд. В systemd предусмотрено 660 секунд на завершение задания.

Проверки админки: php vendor/phpunit/phpunit/phpunit --filter ReactAdminTest; вся регрессия: php vendor/phpunit/phpunit/phpunit. На Windows используйте php-local.bat. Нельзя использовать рабочую базу для этих тестов: phpunit.xml задаёт SQLite :memory:.

Загрузки: PHP должен использовать upload_max_filesize=20M и post_max_size=32M (новый scripts/setup-php.ps1 уже задаёт эти значения). Для существующего PHP обновите php.ini и перезапустите сервер. Для production примените deploy/php-uploads.ini.example к FPM и client_max_body_size 32m из примера nginx; лимиты приложения остаются 20 МБ для CSV и 10 МБ для изображений.
