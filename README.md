# RualTech.ru - Laravel Application

Развёртывание тестовой версии: [GitHub Actions для staging](docs/github-staging.md). Новая версия не опубликована в production.

## Документация сайта

Алтех ориентирован на B2B-клиентов. Каталог с почти ежедневным обновлением прайса — основа сайта.

Начните с [оглавления документации](docs/README.md), [описания продукта](docs/product.md) и [правил каталога и импорта](docs/catalog.md). В них отдельно отмечены принятые требования, реализация и предложения.

Категории товаров и правила импорта доступны в `/admin/product-categories` и `/admin/catalog-mappings`. Самостоятельное обновление Excel-прайса — `/admin/catalog-prices`: загрузка, предпросмотр и применение с отчётом. Цены и остатки обновляются, новые товары создаются черновиками. Командный путь `catalog:import-drafts` также сохранён; процедура и настройки фонового обработчика описаны в [инструкции импорта](docs/catalog-preview.md).

## Развитие магазина

Витрина и админка: Laravel + Inertia 3 + React 19 + TypeScript + shadcn/ui. Все существующие публичные страницы перенесены на React: главная, каталог, карточка товара, услуги, блог и CMS. Админка `/admin` использует отдельную тему по shadcncraft Dashboard: товары, заказы, заявки, услуги, категории, блог и страницы. Модели, `is_admin`, политики и `OrderWorkflow` сохранены.

Админка включает поиск, фильтры, сортировку, выбор колонок, пагинацию, массовое удаление, визуальный редактор Tiptap, теги, загрузку и кадрирование обложек, SEO, публикацию статей, уведомления и CSV-импорт с сопоставлением колонок и отчётами ошибок. Новые импорты выполняет `ImportAdminProducts` через существующую очередь. Панель Filament больше не зарегистрирована; зависимость и старый импортёр пока сохранены для совместимости с ранее поставленными заданиями и отчётами.

- [План развития и архитектурные решения](docs/commerce-plan.md)
- [Локальный запуск, SSR и переключение на Blade](docs/local-development.md)
- [Что реализовано и проверено](docs/implementation-status.md)
- [Lunar: совместимость, корзина и тестовый заказ](docs/lunar-prototype.md)
- [Выпуск, настройка сервера и откат](docs/deployment.md)

Быстрый запуск: включить MySQL в XAMPP, выполнить `npm ci`, запустить `.\dev-local.bat`, затем в другом терминале `npm run dev`. Скрипт заменяет `.env` локальной конфигурацией. Открыть http://127.0.0.1:8000/catalog. Для установленного checkout повторная установка зависимостей не нужна.

После подключения Lunar нужен PHP 8.4: для нового Windows checkout выполните `powershell -ExecutionPolicy Bypass -File scripts/setup-php.ps1`, затем `.\composer-local.bat install`, `.\php-local.bat artisan migrate` и `.\php-local.bat artisan commerce:setup-prototype`. Команды PHP ниже на Windows запускайте через `php-local.bat`. Доступ к админке требует `is_admin`: создание сотрудника — `php artisan admin:create "Имя" "email@example.com"`, назначение проверенному существующему аккаунту — `php artisan admin:access "email@example.com"`. Пароль вводится скрыто. `.env.local` сохранён локально, но исключён из Git; подготовка нового checkout описана в инструкции запуска.

Деплой теперь запускается вручную через защищённое GitHub Environment после проверок. Перед первым production-выпуском обязательны ротация ранее раскрытых секретов, PHP 8.4 на сервере, staging и проверка восстановления данных. Старые workflow и инструкции автоматического деплоя ниже больше не действуют.

Новая витрина включена по умолчанию только при `APP_ENV=local`. Production остаётся на Blade до выполнения условий выпуска из плана. Для отката используйте `STOREFRONT_INERTIA=false` и `php artisan config:clear`.

Прототип Lunar 1.5: товар → корзина → тестовый заказ. Доступен локально, интерфейс shadcn/ui. Оплата, резервирование остатков и отправка заказов менеджеру не подключены. Проверки выпуска запрещают включать экспериментальный checkout в production.

Ниже сохранено исходное описание проекта; сведения о деплое за 2025 год являются историческими.

Приложение на Laravel с Filament админ-панелью, развернутое на сервере rualtech.ru с автоматическим развертыванием через GitHub Actions.

## Статус развертывания

✅ **Последнее обновление:** 27 августа 2025
✅ **SSH ключи:** Обновлены для GitHub Actions
✅ **Автоматическое развертывание:** Настроено

## Особенности

- Laravel 12 framework
- Filament Admin Panel
- Автоматическое развертывание
- HTTPS поддержка
- Nginx + Apache конфигурация

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Storybook — компоненты Алтех

`npm run storybook` запускает каталог на http://127.0.0.1:6006.
`npm run build-storybook` собирает автономную документацию в `storybook-static/`.

Stories находятся рядом с компонентами (`*.stories.tsx`). Каталог использует реальные
компоненты shadcn/ui из `resources/js/components/ui`, стили `resources/css/storefront.css`
и шрифты/изображения из `public`. Карточка `ProductCard` используется и в `HomeCatalog`,
и в Storybook. Не создавайте отдельные копии компонентов или CSS для документации.

Начальный набор: карточка товара (наличие, запрос цены, длинное название, неполные данные,
узкая ширина), сетка услуг, базовые Card/Button и палитра. Данные демонстрационные.
В stories переходы по ссылкам отключены, действия покупки заменены демонстрационными
кнопками без запросов к серверу. Проверку реальной корзины и форм выполняйте на сайте.
Для проверки адаптивных медиазапросов меняйте ширину окна браузера; узкий контейнер
сам по себе не переключает медиазапросы. Новые stories автоматически получают Autodocs.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
