import { Link, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { ArrowUpRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ProductCard } from './product-card';
import { CatalogEmpty } from './catalog-empty';
import type { Product } from '@/types/catalog';

type Props = { products: Product[]; catalogUrl: string; homeUrl: string; activeFilter: string; filters: string[]; total: number; title?: string; showFilters?: boolean };

export function HomeCatalog({ products, catalogUrl, homeUrl, activeFilter, filters, total, title = 'популярное', showFilters = true }: Props) {
    const headingId = useId();
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const filteredUrl = activeFilter ? `${catalogUrl}?category=${encodeURIComponent(activeFilter)}` : catalogUrl;
    function filter(category: string) {
        setFailed(false);
        router.get(homeUrl, category ? { category } : {}, {
            only: ['catalogProducts', 'catalogFilter', 'catalogTotal'], preserveScroll: true, preserveState: true, replace: true,
            onStart: () => setLoading(true), onFinish: () => setLoading(false), onError: () => setFailed(true),
        });
    }
    return <section className="storefront-home-catalog" aria-labelledby={headingId}>
        <div className={`storefront-section-heading${showFilters ? ' storefront-popular-heading' : ''}`}><h2 id={headingId}>{title}</h2></div>
        {showFilters && <div className="storefront-product-filters" role="group" aria-label="Фильтр по категории">{['', ...filters].map(category => <Button key={category} size="sm" variant={category === activeFilter ? 'default' : 'outline'} aria-pressed={category === activeFilter} disabled={loading} onClick={() => filter(category)}>{category || 'Все категории'}</Button>)}</div>}
        <span className="sr-only" role="status">{loading ? 'Обновляем товары…' : ''}</span>
        {failed && <p role="alert">Не удалось обновить товары. Попробуйте выбрать категорию ещё раз.</p>}
        <div aria-busy={loading} className="storefront-product-results" data-loading={loading}>
        {products.length > 0 ? <div className="storefront-home-products">{products.map(product => {
            return <ProductCard variant="standard" key={product.id} product={product} />;
        })}</div> : <CatalogEmpty onReset={showFilters ? () => filter('') : undefined} loading={loading} />}
        </div>
        {total > products.length && <div className="storefront-catalog-more"><Button asChild variant="outline"><Link href={filteredUrl}>Все товары{activeFilter ? ' категории' : ''} <ArrowUpRight size={16} /></Link></Button></div>}
    </section>;
}
