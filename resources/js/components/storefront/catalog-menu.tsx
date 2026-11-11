import { useEffect, useId, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { CatalogProps } from '@/types/catalog';

import { categoryIcon } from './category-icon';
import { categoryLabel } from './category-label';

const categoryLabels: Record<string, string> = {
    'Сетевое оборудование': 'Сети',
    'Программное обеспечение': 'ПО',
};

export function CatalogMenu({ compact = false }: { compact?: boolean }) {
    const { navigation } = usePage<Pick<CatalogProps, 'navigation'>>().props;
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [container, setContainer] = useState<HTMLElement | null>(null);
    useEffect(() => {
        setContainer(triggerRef.current?.closest<HTMLElement>('.storefront-search-bar') ?? null);
    }, []);
    const [activeName, setActiveName] = useState('');
    const hoverTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cancelHover = () => {
        if (hoverTimer.current !== null) clearTimeout(hoverTimer.current);
        hoverTimer.current = null;
    };
    useEffect(() => () => cancelHover(), []);
    const titleId = useId();
    const categories = navigation.catalogCategories ?? [];
    const active = categories.find(category => category.name === activeName) ?? categories[0];

    return <Popover open={open} onOpenChange={value => { cancelHover(); setOpen(value); if (!value) setActiveName(''); }}>
        <PopoverTrigger asChild>
            {compact ? <button ref={triggerRef} type="button" className="storefront-nav-link">Каталог<img src="/images/figma-chevron-down.svg" width="12" height="12" alt="" className={open ? 'rotate-180 transition-transform duration-300' : 'transition-transform duration-300'} /></button>
                : <Button ref={triggerRef} type="button" className="storefront-hero-catalog"><span className="catalog-trigger-icon" aria-hidden="true"><span /><span /><span /></span>Каталог</Button>}
        </PopoverTrigger>
        <PopoverContent container={container} align="start" side="bottom" avoidCollisions={false} aria-labelledby={titleId} className="catalog-menu-panel">
            <h2 id={titleId} className="sr-only">Каталог</h2>
            <div className="catalog-menu-body">
                <nav aria-label="Разделы каталога" className="catalog-menu-sections">
                    {categories.map((category, index) => { const Icon = categoryIcon(category.name); return <button type="button" key={category.name}
                        className="catalog-menu-category" data-active={active?.name === category.name}
                        aria-expanded={active?.name === category.name} aria-controls={titleId + '-subcategories'}
                        onPointerEnter={event => { if (event.pointerType === 'mouse') { cancelHover(); hoverTimer.current = setTimeout(() => setActiveName(category.name), 180); } }}
                        onPointerLeave={cancelHover}
                        onFocus={() => { cancelHover(); setActiveName(category.name); }} onClick={() => { cancelHover(); setActiveName(category.name); }}
                        onKeyDown={event => {
                            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                                event.preventDefault();
                                const buttons = event.currentTarget.parentElement?.querySelectorAll('button');
                                const next = (index + (event.key === 'ArrowDown' ? 1 : -1) + categories.length) % categories.length;
                                buttons?.[next]?.focus();
                            }
                        }}>
                        <span className="catalog-menu-category-icon"><Icon size={18} aria-hidden="true" /></span>
                        <span className="catalog-menu-category-name" title={categoryLabel(category.name)}>{categoryLabels[category.name] ?? categoryLabel(category.name)}</span><ChevronRight size={14} aria-hidden="true" />
                    </button>; })}
                    <Link href={navigation.catalog} onClick={() => setOpen(false)} className="catalog-menu-all">Весь каталог<ArrowUpRight size={14} /></Link>
                </nav>
                <div id={titleId + '-subcategories'} className="catalog-menu-subcategories">
                    {active && <>
                        <Link href={active.url} onClick={() => setOpen(false)} className="catalog-menu-section-title">{categoryLabel(active.name)}<ArrowUpRight size={18} /></Link>
                        <nav aria-label={categoryLabel(active.name)} className="catalog-menu-subcategory-links">
                            {(active.children ?? []).map(child => <Link key={child.name} href={child.url} onClick={() => setOpen(false)}>{child.name}</Link>)}
                        </nav>
                        <Link href={active.url} onClick={() => setOpen(false)} className="catalog-menu-all">Все товары раздела<ChevronRight size={14} /></Link>
                    </>}
                </div>
            </div>
        </PopoverContent>
    </Popover>;
}
