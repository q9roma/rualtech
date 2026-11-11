import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { X, PackageSearch, SlidersHorizontal, ArrowUpRight } from 'lucide-react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SidebarProvider } from '@/components/ui/sidebar';
import { CatalogFilters } from '@/components/storefront/catalog-filters';
import { SubcategoryCards } from '@/components/storefront/subcategory-cards';
import { CategoryDirectory } from '@/components/storefront/category-directory';
import { Sheet, SheetTrigger, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetClose } from '@/components/ui/sheet';
import { categoryLabel } from '@/components/storefront/category-label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Page } from '@/components/storefront/content';
import { ProductCard } from '@/components/storefront/product-card';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import type { CatalogProps } from '@/types/catalog';

export default function Catalog(props: CatalogProps) {
    const { navigation, products, filters, categories, facets } = props;
    const listingRef = useRef<HTMLElement>(null);
    const [loading, setLoading] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const apply = (changes: Partial<CatalogProps['filters']>) => {
        if (changes.category !== undefined) setMobileOpen(false);
        const query = new URL(window.location.href).searchParams;
        const current = { ...filters, search: query.get('search') ?? filters.search, category: query.get('category') ?? filters.category, sort: query.get('sort') ?? filters.sort };
        router.get(navigation.catalog, { ...current, ...changes, in_stock: (changes.in_stock ?? current.in_stock) ? 1 : undefined }, {
            preserveState: true, preserveScroll: true, reset: ['products'], only: ['products', 'filters', 'facets', 'canonicalUrl', 'categoryGuide'],
            onSuccess: () => { if (listingRef.current && listingRef.current.getBoundingClientRect().top < 112) listingRef.current.scrollIntoView({ block: 'start' }); },
            onStart: () => setLoading(true), onFinish: () => setLoading(false),
        });
    };
    const filtered = Boolean(filters.search || filters.price_min != null || filters.price_max != null || filters.brands?.length || filters.in_stock);
    const directoryProps = { categories, selected: filters.category, loading, onSelect: (category: string) => apply({ category }) };
    const title = (filters.search ? `Поиск: «${filters.search}»` : categoryLabel(filters.category) || 'Каталог товаров') + (products.current_page > 1 ? ` — страница ${products.current_page}` : '');
    const sortOptions = [...(filters.search || filters.sort === 'relevance' ? [['relevance', 'По соответствию']] : []), ['name', 'По названию'], ['price_asc', 'Сначала дешевле'], ['price_desc', 'Сначала дороже'], ['brand', 'По бренду']];
    return <Page {...props} hideSubheader title={title} description={`${categoryLabel(filters.category) || 'Оборудование, комплектующие и программное обеспечение'} — каталог Алтех. Цены, характеристики и наличие.${products.current_page > 1 ? ` Страница ${products.current_page}.` : ''}`}>
        {filtered && <Head><meta name="robots" content="noindex, follow" /></Head>}
        <SidebarProvider className="catalog-workspace catalog-with-directory min-h-0">
            <aside className="catalog-quiet-sidebar" aria-label="Разделы каталога">
                <div className="catalog-category-section"><CategoryDirectory {...directoryProps} /></div>
                <CatalogFilters filters={filters} facets={facets} loading={loading} onApply={apply} />
            </aside>
            <section ref={listingRef} aria-label="Товары" aria-busy={loading} className="catalog-listing">
                <div className="catalog-directory-mobile">
                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}><SheetTrigger asChild><Button variant="outline"><SlidersHorizontal aria-hidden="true" />Категории и фильтры</Button></SheetTrigger>
                        <SheetContent side="left" className="catalog-directory-sheet catalog-filter-sheet" showCloseButton={false}>
                            <SheetHeader><SheetTitle>Категории и фильтры</SheetTitle><SheetDescription>Выберите раздел и уточните параметры товаров.</SheetDescription><SheetClose asChild><Button variant="ghost" size="icon" className="catalog-sheet-close" aria-label="Закрыть категории"><X /></Button></SheetClose></SheetHeader>
                            <ScrollArea fadeEdges className="catalog-mobile-scroll" type="auto"><div className="catalog-category-section"><CategoryDirectory {...directoryProps} /></div>
                <CatalogFilters filters={filters} facets={facets} loading={loading} onApply={apply} />
                            </ScrollArea><div className="catalog-filter-footer"><SheetClose asChild><Button disabled={loading}>{loading ? 'Обновляем…' : `Показать товары (${products.total})`}</Button></SheetClose></div>
                        </SheetContent>
                    </Sheet>
                </div>
                <div className="catalog-toolbar catalog-toolbar-plain catalog-heading-toolbar">
                    <div className="catalog-context"><h1>{categoryLabel(filters.category) || (filters.search ? 'Результаты поиска' : 'Все товары')}</h1></div>
                    <div className="catalog-content-sort"><Select value={filters.sort} disabled={loading} onValueChange={sort => apply({ sort })}><SelectTrigger aria-label="Сортировка товаров"><SelectValue /></SelectTrigger><SelectContent align="end">{sortOptions.map(([value, label]) => <SelectItem key={value} value={value}>{label}</SelectItem>)}</SelectContent></Select></div>
                    <span className="sr-only" role="status">{loading ? 'Обновляем выдачу…' : `Товаров: ${products.total}`}</span>
                </div>
                <SubcategoryCards {...directoryProps} />
                {(filters.price_min != null || filters.price_max != null || filters.brands?.length > 0 || filters.in_stock) && <div className="catalog-active-filters" aria-label="Выбранные параметры">
                    
                {(filters.price_min != null || filters.price_max != null) && <Button variant="outline" size="sm" disabled={loading} aria-label="Убрать фильтр цены" onClick={() => apply({ price_min: null, price_max: null })}>{filters.price_min != null ? 'От ' + filters.price_min.toLocaleString('ru-RU') : ''}{filters.price_max != null ? ' до ' + filters.price_max.toLocaleString('ru-RU') : ''} ₽<X size={14} /></Button>}
                    {filters.brands?.map(brand => <Button key={brand} variant="outline" size="sm" disabled={loading} aria-label={'Убрать бренд ' + brand} onClick={() => apply({ brands: filters.brands.filter(name => name !== brand) })}>{brand}<X size={14} /></Button>)}
                    {filters.in_stock && <Button variant="outline" size="sm" disabled={loading} onClick={() => apply({ in_stock: false })}>В наличии<X size={14} /></Button>}
                </div>}
                {filters.search && <div className="catalog-active-filters" aria-label="Выбранные фильтры">
                    {filters.search && <Button variant="outline" size="sm" disabled={loading} aria-label={`Убрать поиск: ${filters.search}`} onClick={() => apply({ search: '', sort: filters.sort === 'relevance' ? 'name' : filters.sort })}>Поиск: {filters.search}<X size={14} /></Button>}
                    <Button variant="ghost" size="sm" disabled={loading} onClick={() => apply({ search: '', category: '', sort: 'name', price_min: null, price_max: null, brands: [], in_stock: false })}>Сбросить всё</Button>
                </div>}
                {products.data.length ? <InfiniteScroll key={JSON.stringify(filters)} data="products" buffer={400} preserveUrl itemsElement=".catalog-products" next={({ loading: fetching, fetch, hasMore }) => hasMore && <div className="catalog-load-more"><Button variant="ghost" onClick={fetch} disabled={fetching}>{fetching ? 'Загружаем товары…' : 'Показать ещё'}</Button></div>}><div className="catalog-products storefront-home-products">{products.data.map(product => <ProductCard key={product.id} product={product} variant="standard" />)}</div></InfiniteScroll> : <div className="catalog-no-results"><PackageSearch size={32} /><h2>Товары не найдены</h2><p>Попробуйте расширить диапазон цены или убрать часть фильтров.</p><Button variant="outline" disabled={loading} onClick={() => apply({ search: '', category: '', sort: 'name', price_min: null, price_max: null, brands: [], in_stock: false })}>Показать все товары</Button></div>}
                {(products.prev_page_url || products.next_page_url) && <nav className="flex justify-between gap-4 py-4 text-sm" aria-label="Страницы каталога">
                    {products.prev_page_url && <a className="underline" href={products.prev_page_url}>Предыдущая страница</a>}
                    {products.next_page_url && <a className="underline" href={products.next_page_url}>Следующая страница</a>}
                </nav>}
                <section className="equipment-help" aria-label="Помощь с подбором оборудования"><div className="catalog-help-copy"><h2>Не нашли нужное оборудование? Найдем и поставим</h2><p>Укажите модель или задачу — мы займемся и реализуем</p></div><ContactDialog label="Поставить задачу" secondary service="Подбор оборудования из каталога" className="catalog-help-action" icon={<ArrowUpRight aria-hidden="true" />} /></section>
            </section>
        </SidebarProvider>
        {props.categoryGuide && <section className="mt-8 space-y-4 rounded-xl border p-6" aria-label={props.categoryGuide.heading}>
            <h2 className="text-xl font-semibold">{props.categoryGuide.heading}</h2>
            <p className="text-muted-foreground">{props.categoryGuide.intro}</p>
            <ul className="list-disc space-y-2 pl-5">{props.categoryGuide.checks.map(check => <li key={check}>{check}</li>)}</ul>
        </section>}
    </Page>;
}
