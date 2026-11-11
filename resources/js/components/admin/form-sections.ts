import type { Field } from './types';

type Group = { title: string; description: string; keys: string[] };
const groups: Record<string, Group[]> = {
    services: [
        { title: 'Услуга', description: '', keys: ['name', 'slug', 'service_category_id'] },
        { title: 'Содержание', description: '', keys: ['short_description', 'description', 'features'] },
        { title: 'Стоимость', description: '', keys: ['price_from', 'price_type'] },
        { title: 'Размещение', description: '', keys: ['icon', 'sort_order', 'is_active', 'is_featured'] },
    ],
    'service-categories': [
        { title: 'Категория', description: '', keys: ['name', 'slug', 'description'] },
        { title: 'Оформление', description: '', keys: ['image', 'icon'] },
        { title: 'Размещение', description: '', keys: ['sort_order', 'is_active'] },
    ],
    'blog-posts': [
        { title: 'Статья', description: '', keys: ['title', 'slug'] },
        { title: 'Содержание', description: '', keys: ['excerpt', 'content', 'featured_image', 'tags'] },
        { title: 'Публикация', description: '', keys: ['is_published', 'published_at'] },
    ],
    pages: [
        { title: 'Страница', description: '', keys: ['title', 'slug'] },
        { title: 'Содержание', description: '', keys: ['content'] },
        { title: 'Публикация', description: '', keys: ['is_active'] },
    ],
    'contact-requests': [
        { title: 'Контакт', description: '', keys: ['name', 'email', 'phone', 'company'] },
        { title: 'Обращение', description: '', keys: ['subject', 'service', 'source', 'message'] },
        { title: 'Обработка', description: '', keys: ['status', 'processed_at', 'admin_notes'] },
    ],
    'catalog-mappings': [
        { title: 'Источник правила', description: '', keys: ['name', 'source', 'source_category', 'rule_code'] },
        { title: 'Обработка', description: '', keys: ['decision', 'catalog_category_id', 'manual_locked'] },
    ],
};

export function formSections(resource: string, fields: Field[]) {
    if (resource==='catalog-mappings') fields = fields.filter(field=>field.key!=='name');
    const definitions = [...(groups[resource] ?? [{title: 'Основное', description: '', keys: fields.filter(f => !f.key.startsWith('seo_')).map(f => f.key)}]),
        {title: 'Поисковая оптимизация', description: '', keys: fields.filter(f => f.key.startsWith('seo_')).map(f => f.key)}];
    const used = new Set(definitions.flatMap(group => group.keys));
    const remaining = fields.filter(field => !used.has(field.key));
    return [...definitions.map(group => ({...group, fields: group.keys.flatMap(key => fields.filter(field => field.key === key))})),
        {title: 'Дополнительно', description: '', fields: remaining}].filter(group => group.fields.length);
}
