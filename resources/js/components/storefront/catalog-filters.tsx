import { useEffect, useId, useState } from 'react';
import { Search, X, ChevronDown, ArrowUpRight } from 'lucide-react';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Checkbox } from '@/components/ui/checkbox';
import type { CatalogProps } from '@/types/catalog';

export function CatalogFilters({ filters, facets, loading, onApply }: Pick<CatalogProps, 'filters' | 'facets'> & { loading: boolean; onApply: (changes: Partial<CatalogProps['filters']>) => void }) {
    const id = useId();
    const [min, setMin] = useState(String(filters.price_min ?? ''));
    const [max, setMax] = useState(String(filters.price_max ?? ''));
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState(false);
    useEffect(() => { setMin(String(filters.price_min ?? '')); setMax(String(filters.price_max ?? '')); }, [filters.price_min, filters.price_max]);
    useEffect(() => { setSearch(''); setExpanded(false); }, [filters.category]);
    const selectedBrands = filters.brands ?? [];
    const brands = [...(facets?.brands ?? []), ...selectedBrands.filter(name => !facets?.brands.some(brand => brand.name === name)).map(name => ({ name, count: 0 }))];
    const visibleBrands = brands.filter(brand => brand.name.toLocaleLowerCase().includes(search.toLocaleLowerCase()));
    const displayedBrands = expanded || search.trim() ? visibleBrands : visibleBrands.filter((brand, index) => index < 6 || selectedBrands.includes(brand.name));
    const active = filters.price_min != null || filters.price_max != null || selectedBrands.length > 0 || filters.in_stock;
    return <div className="catalog-facets" aria-label="Фильтры товаров">

        <form className="catalog-facet" onSubmit={event => { event.preventDefault(); onApply({ price_min: min === '' ? null : Number(min), price_max: max === '' ? null : Number(max) }); }}>
            <h3>Цена, ₽</h3>
            <div className="catalog-price-fields"><label htmlFor={id + '-min'}><span>От</span><Input id={id + '-min'} type="number" inputMode="decimal" min="0" max="999999999" step="0.01" value={min} placeholder={String(Number(facets?.priceMin ?? 0))} onChange={event => setMin(event.target.value)} disabled={loading} /></label><label htmlFor={id + '-max'}><span>До</span><Input id={id + '-max'} type="number" inputMode="decimal" min="0" max="999999999" step="0.01" value={max} placeholder={facets?.priceMax == null ? '' : String(Number(facets.priceMax))} onChange={event => setMax(event.target.value)} disabled={loading} /></label></div>
            <Button type="submit" variant="outline" size="sm" className="catalog-price-apply" disabled={loading || (min === String(filters.price_min ?? '') && max === String(filters.price_max ?? ''))}>Применить цену</Button>
        </form><Separator />
        <section className="catalog-facet" aria-label="Бренд"><h3>Бренд</h3>
            {brands.length > 6 && <div className="catalog-brand-search"><Search aria-hidden="true" /><Input aria-label="Найти бренд" placeholder="Найти бренд" value={search} onChange={event => setSearch(event.target.value)} /></div>}
            <ScrollArea fadeEdges data-expanded={expanded || Boolean(search.trim())} className="catalog-brand-scroll" type="auto"><div className="catalog-brand-options">{displayedBrands.map(brand => <label className="catalog-facet-option" key={brand.name} data-checked={selectedBrands.includes(brand.name)}><Checkbox checked={selectedBrands.includes(brand.name)} disabled={loading} onCheckedChange={checked => onApply({ brands: checked ? [...selectedBrands, brand.name] : selectedBrands.filter(name => name !== brand.name) })} /><span>{brand.name}</span><small>{brand.count}</small></label>)}{!visibleBrands.length && <p className="catalog-facet-empty">{search ? 'Бренд не найден' : 'В этом разделе нет брендов'}</p>}</div></ScrollArea>
            {!search.trim() && brands.length > 6 && <Button variant="ghost" size="sm" className="catalog-brand-expand" aria-expanded={expanded} onClick={() => setExpanded(value => !value)}>{expanded ? 'Свернуть' : `Показать все (${brands.length})`}<ChevronDown aria-hidden="true" className={expanded ? 'rotate-180' : ''} /></Button>}
        </section><Separator />
        <section className="catalog-facet" aria-label="Наличие"><h3>Наличие</h3><label className="catalog-facet-option" data-checked={filters.in_stock}><Checkbox checked={filters.in_stock ?? false} disabled={loading} onCheckedChange={checked => onApply({ in_stock: checked === true })} /><span>Только в наличии</span></label></section>
        <section className="catalog-sourcing-help" aria-label="Поставка под заказ">
            <h3>Нет в наличии?<br />Найдём и поставим</h3>
            <p>Укажите модель или задачу — найдём у поставщиков, проверим сроки и предложим решение.</p>
            <ContactDialog label="Поставить задачу" service={`Поставка под заказ${filters.category ? `: ${filters.category}` : ''}`} icon={<ArrowUpRight size={16} aria-hidden="true" />} />
        </section>
        <Separator /><div className="catalog-facet-footer"><Button variant="outline" size="sm" className="catalog-reset-filters" disabled={loading || !active} onClick={() => onApply({ price_min: null, price_max: null, brands: [], in_stock: false })}><X aria-hidden="true" />Сбросить фильтры</Button></div>
    </div>;
}
