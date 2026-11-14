# Закрытый staging — 12 ноября 2026

Текущий выпуск выполнен GitHub Actions: коммит `fa1586e658aeab655a4627c9b20cbe6e1200ac11`, ветка `codex/staging-actions`, [успешный запуск](https://github.com/q9roma/rualtech/actions/runs/43119062465). SHA в `/ready` совпадает с Git-коммитом. Production-код не выпускался, main сохранён, оба старых production-deploy workflow отключены, `PRODUCTION_DEPLOY_ENABLED=false`.

Первоначальная ручная репетиция использовала снимок `1d58e7874ab5150be12e9507b8089a0c9d9cbe31` (идентификатор содержимого, не Git-коммит). Результаты этой репетиции ниже сохранены как история проверок.

Для Actions создан отдельный пользователь `altech-staging`, без группы www-data и без доступа к production .env/каталогам. Staging FPM, очередь и SSR теперь выполняются от него. Ключ `C:\Users\romam\.ssh\rualtech_actions_staging_2026` передан только в environment staging; environment разрешает только staging-ветку. Многострочные secrets записаны побайтово с LF. Старый общий пользователь altech для staging больше не используется.

GitHub CI успешно проверил SQLite и MySQL, Python, TypeScript, сборку и аудиты. MySQL выявил и помог исправить зависимость сохранения подтверждённых фото от порядка ключей JSON; тесты также отвязаны от фиксированных ID и SQLite-only предположений. После Actions-деплоя повторно проверены SHA, TLS, SSR, вход администратора, закрытые файлы и очередь.

## Доступ

Основной адрес: **https://staging.rualtech.ru**. Туннель больше не нужен. Сертификат Let's Encrypt подключён к ежедневному продлению; внешний доступ защищён HTTP Basic Auth. Логин `staging`, пароль в `C:\aggregator\data\staging\rualtech\browser-access.json` (закрытая локальная папка). Внутренний вход администратора использует прежний `operator-credentials.json`, URL в нём обновлён.

Только на сервере staging.rualtech.ru разрешается в 127.0.0.1 для локальных проверок CI. Nginx разрешает обход Basic Auth только с loopback; внешний `/ready` также требует пароль. Mail MX/SPF/DKIM не менялись. A-запись поддомена добавлена в REG.RU и локальную копию зоны ispmanager.

Прежний адрес через SSH-туннель https://localhost:8443 теперь перенаправляет на поддомен. При необходимости старый туннель запускается так:

```powershell
powershell -NoProfile -File C:\xampp\htdocs\altech\scripts\open-staging-tunnel.ps1
```

Самоподписанный сертификат сохранён только у старого localhost-перенаправления. Для основного поддомена используется обычное системное доверие TLS; устаревшая диагностическая команда для localhost:

```powershell
curl.exe --cacert C:\aggregator\data\staging\rualtech\localhost.crt https://localhost:8443/ready
```

Данные отдельного тестового администратора находятся в `C:\aggregator\data\staging\rualtech\operator-credentials.json`. Папка доступна только текущему пользователю Windows и SYSTEM. Пароль в документацию и Git не включён.

## Изоляция

- Корень `/srv/altech/staging`, отдельная БД и пользователь `altech_staging`, новые APP_KEY и пароль.
- Nginx обслуживает staging.rualtech.ru на 443 с обязательной внешней парольной защитой, отдельный PHP-пул `/run/php/altech-staging.sock`.
- Внешняя почта отключена: `MAIL_MAILER=log`, FPM sendmail также заблокирован. Экспериментальный checkout выключен.
- На всех ответах Nginx установлен `X-Robots-Tag: noindex, nofollow, noarchive`.
- Очередь `altech-staging-queue` и SSR `altech-staging-ssr` включены в автозапуск. SSR слушает 127.0.0.1:13715.
- База чистая, без копирования реальных пользователей, заказов, заданий и секретов production. Это проверка инфраструктуры и выпуска, а не приёмка наполненного каталога.

## Проверено

- Linux: PHP 8.4.27; 170 тестов, 2712 assertions, повторный прогон без предупреждений.
- TypeScript, клиентская сборка и SSR; Composer/npm audit без известных уязвимостей.
- Полный запуск миграций на новой MySQL-базе.
- Деплой реальным ограниченным SSH-пользователем `altech`: checksum, проверка платформы, резервные копии, кеши, переключение current, запуск служб и `/ready` с ожидаемым идентификатором.
- HTTPS главной, каталога, входа и ready; SSR-HTML каталога; действительный вход тестового администратора; анонимный доступ в админку закрыт; .env и произвольный PHP недоступны.
- Реальное тестовое задание обработано staging-очередью. Обе службы работали с NRestarts=0.
- Повторный выпуск того же снимка и настоящий откат к предыдущему каталогу релиза. Ссылка current проверена, повторные HTTP/login/SSR-проверки прошли; данные и storage сохранены. Откат между разными схемами БД этим не проверялся.
- Production после проверок отвечал HTTP 200.

Первый запуск выявил неверного владельца родительских каталогов staging storage; права исправлены только внутри staging. Неудачный первоначальный каталог оставлен для диагностики.

## Следующий выпуск в staging

```powershell
powershell -NoProfile -File C:\xampp\htdocs\altech\scripts\deploy-staging.ps1
```

Команда создаёт снимок по явному списку исходников, исключает env/БД/загрузки/локальные зависимости, загружает его, проверяет SHA256, собирает Linux-зависимости, запускает тесты и выпускает **только staging**. Полный цикл исходного выпуска проверен командами на сервере; новая PowerShell-обёртка проверена синтаксически, а её Bash-часть — Bash/ShellCheck. Обёртка не запускалась повторно целиком после добавления.

При повторении неизменного снимка сборщик остановится, если каталог builds уже существует. Для повторного выпуска готового артефакта использовать существующий incoming/<snapshot>/deploy-release.sh; не удалять старые каталоги вслепую.

Откат:

```bash
bash /srv/altech/staging/current/scripts/rollback-release.sh staging
```

Журналы: `/srv/altech/staging/build.log`, `test-warnings.log` (успешный повторный прогон), `deploy-first.log`, `deploy-second.log`, `rollback.log`. Резервные копии выпуска: `/srv/altech/staging/backups`; локальная копия: `C:\aggregator\data\staging\rualtech\backups`.

На время Linux-сборок добавлен swap 2 ГБ `/swapfile-altech-build`; он активен, но пока не прописан в fstab и не включится автоматически после перезагрузки. Перед следующей тяжёлой сборкой после перезагрузки проверить память/swap.

Для будущего production нужны отдельное разрешение, приёмка сценариев и данных, проверенный Git-коммит и настройка GitHub Environments. Staging-доступ не открывает production gate.
