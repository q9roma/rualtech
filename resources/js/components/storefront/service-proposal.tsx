import { Link } from '@inertiajs/react';
import { ArrowUpRight, Package } from 'lucide-react';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import type { Product } from '@/types/catalog';

export type ServiceBundle = { items: { sku: string; label: string; quantity: number; product: Product }[]; formattedTotal: string | null };
const details: Record<string, { title: string; spec: string }> = {
    DS423: { title: 'Synology DS423', spec: 'Хранилище · 4 отсека' },
    ST4000NT001: { title: 'Seagate IronWolf Pro', spec: 'Диски · 2 × 4 ТБ' },
    BR700ELCD: { title: 'CyberPower BR700ELCD', spec: 'Резервное питание · 420 Вт' },
};

export function ServiceProposal({ bundle }: { bundle?: ServiceBundle | null }) {
    if (!bundle) return null;
    return <section className="service-proposal" aria-labelledby="service-proposal-title">
        <header className="service-proposal-heading"><h3 id="service-proposal-title">К NAS подберём диски и ИБП</h3></header>
        <div className="service-bundle-items">{bundle.items.map(({ product, label, sku }) => {
            const image = product.image || product.images?.[0];
            const detail = details[sku];
            return <Link className="service-bundle-item" key={product.id} href={product.url}>
                <div className="service-bundle-image">{image ? <img src={image} alt="" loading="lazy" /> : <Package size={48} aria-hidden="true" />}</div>
                <div className="service-bundle-copy"><h4>{detail?.title || label}</h4><p className="service-bundle-spec">{detail?.spec}</p></div>
                <ArrowUpRight className="service-bundle-arrow" size={16} aria-hidden="true" />
            </Link>;
        })}</div>
        <footer className="service-bundle-footer"><div><span>Весь комплект</span><strong>{bundle.formattedTotal ?? 'Стоимость по запросу'}</strong></div><ContactDialog service="Комплект для общих файлов" label="Подобрать под мои задачи" initialMessage="Нужно хранилище для общих файлов. Подберите NAS, диски и ИБП под наш офис." /></footer>
        
    </section>;
}
