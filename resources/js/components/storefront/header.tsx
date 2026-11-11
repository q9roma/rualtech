import { ShoppingCart } from 'lucide-react';
import { AccountButton } from '@/components/storefront/account-button';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import { CatalogMenu } from '@/components/storefront/catalog-menu';
import { Logo } from '@/components/storefront/logo';
import { Hero } from '@/components/storefront/hero';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { LiveSearch } from '@/components/storefront/live-search';
import type { CatalogProps } from '@/types/catalog';

export function StorefrontHeader({ navigation, subheader }: Pick<CatalogProps, 'navigation'> & { subheader?: ReactNode }) {
    const { url, props } = usePage<{ commerceEnabled: boolean; cartUrl: string; cartSummary: { count: number } }>();
    const isHome = url.split('?')[0] === '/';
    const headerRef = useRef<HTMLElement>(null);
    const metrics = useRef({ height: 0, threshold: 180 });
    const [compact, setCompact] = useState(false);
    useEffect(() => {
        const header = headerRef.current;
        if (!header) return;
        const measure = () => {
            if (!compact) {
                metrics.current = { height: header.offsetHeight, threshold: header.offsetTop + header.offsetHeight - 90 };
            }
        };
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(header);
        const scroll = () => setCompact(window.scrollY > metrics.current.threshold);
        scroll();
        window.addEventListener('scroll', scroll, { passive: true });
        return () => { observer.disconnect(); window.removeEventListener('scroll', scroll); };
    }, [compact]);
    return <header ref={headerRef} style={compact ? { height: metrics.current.height } : undefined} className={`storefront-header${compact ? ' storefront-header-compact' : ''}`}>
        <div className="storefront-header-row">
            <div className="storefront-header-contacts"><ContactDialog label="Написать" className="storefront-write-button" icon={<img src="/images/figma-message.svg" width="16" height="16" alt="" />} /><a href="mailto:office@rualtech.ru">office@rualtech.ru</a><a href="tel:+74952753533">+7 (495) 275-35-33</a></div>
            <div className="storefront-header-actions"><AccountButton enabled={props.commerceEnabled} home={navigation.home} /></div>
        </div>
        <div className={`storefront-search-bar storefront-themed-hero${isHome ? ' storefront-themed-hero-home' : ' storefront-themed-hero-inner'}`}>
        <form action={navigation.catalog} method="get" role="search" aria-label="Поиск по каталогу" className="storefront-hero-search">
            <Link href={navigation.home} aria-label="Алтех — главная" className="storefront-logo"><Logo /></Link>
            <CatalogMenu />
            <LiveSearch catalogUrl={navigation.catalog} />
            <Button type="submit" variant="secondary" size="icon" className="storefront-hero-submit" aria-label="Найти"><img src="/images/figma-search-button.svg" width="16" height="16" alt="" /></Button>
        {props.commerceEnabled && <Button asChild variant="outline" className="storefront-header-cart"><Link href={props.cartUrl} aria-label={`Корзина, товаров: ${props.cartSummary?.count ?? 0}`}><ShoppingCart size={18} /><span className="storefront-cart-label">Корзина</span><span className="storefront-cart-count" aria-live="polite">{props.cartSummary?.count ?? 0}</span></Link></Button>}
        </form>
        {isHome ? <Hero /> : subheader}
        </div>
        {isHome && <div className="storefront-delivery"><img src="/images/figma-delivery-lightning.svg" alt="" /><span>Доставка по Москве и МО за сутки · Бесплатно при заказе от 150 000 ₽</span></div>}
    </header>;
}
