
import { Check, LayoutGrid } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { categoryIcon } from './category-icon';
import type { CatalogProps } from '@/types/catalog';

export function SubcategoryCards({ categories, selected, loading, onSelect }: Pick<CatalogProps, 'categories'> & { selected: string; loading: boolean; onSelect: (category: string) => void }) {
    const root = categories.find(item => item.category === selected || item.children?.some(child => child.name === selected));
    if (!root?.children?.length) return null;
    const rows = [{ name: root.category, label: 'Все товары раздела', count: root.products_count, url: root.url || `/catalog?category=${encodeURIComponent(root.category)}` }, ...root.children.map(child => ({ name: child.name, label: child.name, count: child.count, url: child.url }))];
    return <nav className="catalog-subcategories" aria-label="Подкатегории">
        <div className="catalog-subcategory-grid">{rows.map(row => {
            const Icon = row.name === root.category ? LayoutGrid : categoryIcon(row.name);
            const active = selected === row.name;
            return <Button asChild key={row.name} variant="outline" className="catalog-subcategory-card" aria-current={active ? 'page' : undefined} aria-disabled={loading}><a href={row.url} onClick={event => { if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return; event.preventDefault(); if (!loading && !active) onSelect(row.name); }}>
                <Icon className="catalog-subcategory-icon" aria-hidden="true" />
                <span className="catalog-subcategory-text"><span>{row.label}</span></span>
                {active && <Check className="catalog-subcategory-check" aria-hidden="true" />}
            </a></Button>;
        })}</div>
    </nav>;
}
