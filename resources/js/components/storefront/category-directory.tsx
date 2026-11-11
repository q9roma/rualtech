import { LayoutGrid } from 'lucide-react';
import { SidebarMenu, SidebarMenuItem, SidebarMenuButton } from '@/components/ui/sidebar';
import { categoryIcon } from './category-icon';
import { categoryLabel } from './category-label';
import type { CatalogProps } from '@/types/catalog';

export function CategoryDirectory({ categories, selected, loading, onSelect }: Pick<CatalogProps, 'categories'> & { selected: string; loading: boolean; onSelect: (name: string) => void }) {
    const root = categories.find(item => item.category === selected || item.children?.some(child => child.name === selected));
    const activeCategory = root?.category ?? selected;
    const rows = [{ name: '', label: 'Все товары', url: '/catalog' }, ...categories.map(item => ({ name: item.category, label: categoryLabel(item.category), url: item.url || `/catalog?category=${encodeURIComponent(item.category)}` }))];
    return <nav className="catalog-market-directory" aria-label="Категории товаров">
        <SidebarMenu className="catalog-market-list">
            {rows.map(item => { const Icon = item.name ? categoryIcon(item.name) : LayoutGrid; return <SidebarMenuItem key={item.name}><SidebarMenuButton asChild isActive={activeCategory === item.name} className="catalog-market-link" aria-current={activeCategory === item.name ? 'page' : undefined} aria-disabled={loading}><a href={item.url} onClick={event => { if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return; event.preventDefault(); if (!loading && selected !== item.name) onSelect(item.name); }}><span className="catalog-category-label"><Icon aria-hidden="true" /><span>{item.label}</span></span></a></SidebarMenuButton></SidebarMenuItem>; })}
        </SidebarMenu>
    </nav>;
}
