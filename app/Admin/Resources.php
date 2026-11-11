<?php

namespace App\Admin;

use App\Helpers\IconHelper;
use App\Models\{Product, Service, ServiceCategory, BlogPost, Page, ContactRequest};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** The back-office contract shared by validation, forms and tables. */
class Resources
{
    public const MODELS = ['products' => Product::class, 'product-categories' => \App\Models\ProductCategory::class,
        'catalog-mappings' => \App\Models\CatalogImportMapping::class, 'services' => Service::class,
        'service-categories' => ServiceCategory::class, 'blog-posts' => BlogPost::class,
        'pages' => Page::class, 'contact-requests' => ContactRequest::class];
    public const LABELS = ['products' => 'Товары', 'product-categories' => 'Категории товаров', 'catalog-mappings' => 'Правила каталога', 'orders' => 'Заказы', 'contact-requests' => 'Заявки',
        'services' => 'Услуги', 'service-categories' => 'Категории услуг', 'blog-posts' => 'Блог', 'pages' => 'Страницы'];

    public static function model(string $resource): string
    {
        abort_unless(isset(self::MODELS[$resource]), 404);
        return self::MODELS[$resource];
    }

    public static function fields(string $resource): array
    {
        $key = 'admin.fields.'.$resource;
        if (! request()->attributes->has($key)) request()->attributes->set($key, self::buildFields($resource));
        return request()->attributes->get($key);
    }

    private static function buildFields(string $resource): array
    {
        $f = fn ($key, $label, $type = 'text', $required = false, $extra = []) =>
            ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required, ...$extra];
        $seo = [$f('seo_title', 'SEO заголовок'), $f('seo_description', 'SEO описание', 'textarea')];
        $active = $f('is_active', 'Активен', 'boolean', false, ['default' => true]);
        $slug = $f('slug', 'URL адрес', 'text', true, ['hint' => 'Можно оставить пустым: создадим из названия при сохранении.']);
        $icon = $f('icon', 'Иконка', 'select', false, ['options' => IconHelper::getAvailableIcons(), 'searchable' => true]);
        $sort = $f('sort_order', 'Порядок сортировки', 'number', true, ['default' => 0]);
        return match ($resource) {
            'products' => [$f('name', 'Наименование', 'text', true), $f('sku', 'Артикул'), $f('category', 'Прежняя категория / текст'),
                $f('description', 'Описание', 'textarea', false, ['hint' => 'Если оставить пустым, на витрине будет описание поставщика.']),
                $f('specifications', 'Характеристики', 'specifications'),
                $f('photos', 'Фотографии', 'gallery', false, ['hint' => 'До 12 фото JPEG, PNG или WebP, каждое до 10 МБ. Первое — обложка.']),
                $f('catalog_category_id', 'Подкатегория каталога', 'select', false, ['options' => \App\Models\ProductCategory::leafOptions(), 'searchable' => true]),
                $f('source_category', 'Исходная категория прайса', 'text', false, ['readOnly' => true]),
                $f('brand', 'Бренд'), $f('slug', 'URL адрес'), $f('availability', 'Наличие'), $f('price', 'Цена, ₽', 'number'),
                $f('stock_free', 'Свободно (прайс)'), $f('stock_transit', 'Транзит (прайс)'),
                $f('currency', 'Валюта', 'text', false, ['default' => 'RUB']), $f('is_active', 'Опубликован', 'boolean', false, ['default' => false])],
            'product-categories' => [$f('name', 'Название', 'text', true),
                $f('parent_id', 'Родительский раздел', 'select', false, ['options' => \App\Models\ProductCategory::whereNull('parent_id')->orderBy('sort_order')->pluck('name', 'id')->all(), 'searchable' => true, 'hint' => 'Пустое значение — корневой раздел.']),
                $f('slug', 'Постоянный адрес', 'text', false, ['readOnly' => true]), $sort, $active],
            'catalog-mappings' => [$f('name', 'Правило', 'text', false, ['readOnly' => true]),
                $f('source', 'Источник', 'text', false, ['readOnly' => true]),
                $f('source_category', 'Категория источника', 'text', false, ['readOnly' => true]),
                $f('rule_code', 'Код правила', 'text', false, ['readOnly' => true]),
                $f('decision', 'Действие', 'select', true, ['options' => ['include' => 'Включать как черновик', 'exclude' => 'Исключать', 'request' => 'Только под запрос', 'review' => 'На проверку']]),
                $f('catalog_category_id', 'Подкатегория', 'select', false, ['options' => \App\Models\ProductCategory::leafOptions(), 'searchable' => true]),
                $f('manual_locked', 'Ручное решение', 'boolean', false, ['readOnly' => true])],
            'services' => [$f('name', 'Название услуги', 'text', true), $slug,
                $f('service_category_id', 'Категория услуги', 'select', true, ['options' => ServiceCategory::orderBy('name')->pluck('name', 'id')->all(), 'searchable' => true]),
                $f('short_description', 'Краткое описание', 'textarea', true), $f('description', 'Полное описание', 'richtext', true),
                $f('price_from', 'Цена от, ₽', 'number'), $f('price_type', 'Тип цены', 'select', true, ['options' => array_combine(['от', 'за час', 'фиксированная', 'по запросу'], ['от', 'за час', 'фиксированная', 'по запросу']), 'default' => 'от']),
                $icon, $f('features', 'Особенности услуги', 'tags'), $sort, $active, $f('is_featured', 'Рекомендуемая', 'boolean'), ...$seo],
            'service-categories' => [$f('name', 'Название категории', 'text', true), $slug, $f('description', 'Описание', 'textarea'),
                $f('image', 'Обложка категории', 'image', false, ['hint' => 'Рекомендуемый размер: 800 × 450 пикселей.', 'crop' => true]), $icon, $sort, $active, ...$seo],
            'blog-posts' => [$f('title', 'Заголовок', 'text', true), $slug, $f('excerpt', 'Краткое описание', 'textarea', true),
                $f('content', 'Содержание', 'richtext', true), $f('featured_image', 'Изображение', 'image'), $f('tags', 'Теги', 'tags'),
                $f('is_published', 'Опубликовано', 'boolean'), $f('published_at', 'Дата публикации', 'datetime-local', false, ['hint' => 'При публикации пустая дата заполняется автоматически.']), ...$seo],
            'pages' => [$f('title', 'Заголовок', 'text', true), $slug, $f('content', 'Содержимое', 'richtext'), $active, ...$seo],
            'contact-requests' => [$f('name', 'Имя', 'text', true), $f('email', 'Email', 'email', true), $f('phone', 'Телефон', 'tel'),
                $f('company', 'Компания'), $f('subject', 'Тема'), $f('service', 'Услуга'),
                $f('source', 'Источник', 'select', false, ['options' => ContactRequest::getSources()]), $f('message', 'Сообщение', 'textarea'),
                $f('status', 'Статус', 'select', true, ['options' => ContactRequest::getStatuses(), 'default' => 'new']),
                $f('processed_at', 'Дата обработки', 'datetime-local'), $f('admin_notes', 'Заметки администратора', 'textarea')],
        };
    }

    public static function columns(string $resource): array
    {
        $fields = collect(self::fields($resource))->keyBy('key');
        $keys = match ($resource) {
            'products' => ['name', 'brand', 'sku', 'catalog_category_id', 'price', 'is_active', 'availability', 'category', 'source_category', 'stock_free', 'stock_transit', 'slug', 'updated_at'],
            'product-categories' => ['name', 'parent_id', 'slug', 'sort_order', 'is_active', 'updated_at'],
            'catalog-mappings' => ['name', 'decision', 'catalog_category_id', 'manual_locked', 'source', 'updated_at'],
            'services' => ['name', 'category_name', 'price_from', 'price_type', 'is_active', 'is_featured', 'slug', 'icon', 'sort_order', 'seo_title', 'seo_description', 'created_at', 'updated_at'],
            'service-categories' => ['image', 'name', 'slug', 'sort_order', 'is_active', 'active_services_count', 'created_at', 'updated_at'],
            'blog-posts' => ['title', 'featured_image', 'is_published', 'published_at', 'slug', 'seo_title', 'seo_description', 'created_at', 'updated_at'],
            'pages' => ['title', 'slug', 'is_active', 'created_at', 'updated_at'],
            'contact-requests' => ['name', 'email', 'phone', 'service', 'status', 'created_at'],
        };
        $visible = match ($resource) { 'products' => 7, 'services', 'service-categories', 'contact-requests' => 6, default => 4 };
        return array_map(fn ($key, $index) => ['key' => $key, 'label' => $fields[$key]['label'] ?? match ($key) {
            'category_name' => 'Категория', 'active_services_count' => 'Активных услуг', 'created_at' => 'Создано', 'updated_at' => 'Обновлено', default => $key,
        }, 'type' => $fields[$key]['type'] ?? (str_ends_with($key, '_at') ? 'datetime-local' : 'text'),
            'options' => $fields[$key]['options'] ?? null, 'visible' => $index < $visible, 'sortable' => true,
            'copyable' => in_array($key, ['sku', 'slug', 'email', 'phone'])], $keys, array_keys($keys));
    }

    public static function rules(string $resource, ?Model $record = null): array
    {
        $rules = [];
        foreach (self::fields($resource) as $field) {
            $key = $field['key'];
            if (in_array($field['type'], ['specifications', 'gallery'])) continue;
            if ($field['readOnly'] ?? false) continue;
            $rules[$key] = [$field['required'] ? 'required' : 'nullable'];
            $rules[$key][] = match ($field['type']) {
                'boolean' => 'boolean', 'number' => $key === 'sort_order' ? 'integer' : 'numeric',
                'tags' => 'array', 'datetime-local' => 'date', default => 'string',
            };
            if ($field['type'] === 'select') $rules[$key][] = Rule::in(array_map('strval', array_keys($field['options'])));
            if (in_array($field['type'], ['text', 'email', 'tel', 'select', 'image'])) $rules[$key][] = 'max:'.($resource === 'products' && $key === 'name' ? 2000 : 255);
            if ($field['type'] === 'email') $rules[$key][] = 'email';
            if ($field['type'] === 'tags') { $rules[$key][] = 'max:100'; $rules[$key.'.*'] = ['string', 'max:255']; }
            if ($field['type'] === 'richtext' || $field['type'] === 'textarea') $rules[$key][] = 'max:200000';
        }
        if (isset($rules['slug'])) $rules['slug'][] = Rule::unique((new (self::model($resource)))->getTable(), 'slug')->ignore($record?->getKey());
        if ($resource !== 'pages' && isset($rules['seo_title'])) { $rules['seo_title'][] = 'max:60'; $rules['seo_description'][] = 'max:160'; }
        if ($resource === 'products') {
            $rules['availability'][] = 'max:100'; $rules['currency'][] = 'max:10';
            $rules['specifications'] = ['nullable', 'array', 'max:100'];
            $rules['specifications.*'] = ['array:name,value'];
            $rules['specifications.*.name'] = ['required', 'string', 'max:255'];
            $rules['specifications.*.value'] = ['required', 'string', 'max:2000'];
            $rules['photos'] = ['nullable', 'array', 'max:12'];
            $rules['photos.*'] = [function ($attribute, $value, $fail) use ($record) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $validator = validator(['photo' => $value], ['photo' => 'required|image|mimes:jpeg,png,webp|max:10240']);
                    if ($validator->fails()) $fail('Выберите JPEG, PNG или WebP до 10 МБ.');
                } elseif (! is_string($value) || ! in_array($value, $record?->photos ?? [], true)) {
                    $fail('Загрузите фотографию файлом.');
                }
            }];
        }
        if ($resource === 'product-categories') {
            $rules['name'][] = Rule::unique('product_categories', 'name')->ignore($record?->id);
            $rules['sort_order'][] = 'min:0';
        }
        if ($resource === 'catalog-mappings') $rules['catalog_category_id'][] = 'required_if:decision,include';
        return $rules;
    }
}
