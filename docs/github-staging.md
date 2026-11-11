# GitHub Actions: staging

Workflow `.github/workflows/staging.yml` публикует только staging при push в `codex/staging-actions`. Перед выпуском проходят проверки PHP 8.4 на SQLite и MySQL, Python, TypeScript, сборка, аудит зависимостей и проверки release-скриптов. Артефакт собирается из точного Git SHA и сохраняется на 14 дней.

Environment `staging` разрешает только эту ветку. Secrets: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KEY`, `DEPLOY_KNOWN_HOSTS`. Ключ принадлежит отдельному Unix-пользователю `altech-staging`, без доступа к production-файлам. SSH host key закреплён заранее, автоматическое доверие ssh-keyscan не используется.

Проверка `/ready` выполняется на сервере с явным доверием локальному staging-сертификату. Сервер остаётся закрытым: Nginx слушает localhost, просмотр возможен через SSH-туннель. Код проверяется на идентификатор выпуска до и после переключения `current`.

Ручной повторный запуск уже зарегистрированного workflow:

```bash
gh workflow run staging.yml --ref codex/staging-actions --repo q9roma/rualtech
```

Если GitHub ещё не позволяет workflow_dispatch для файла вне default branch, использовать Re-run jobs существующего запуска либо новый push в staging-ветку. Workflow намеренно не добавляется в main ради обхода этого ограничения.

Production не является целью этого workflow. Старые серверные workflow отключены, `PRODUCTION_DEPLOY_ENABLED=false`. Слияние в main и выпуск production требуют отдельного решения.
