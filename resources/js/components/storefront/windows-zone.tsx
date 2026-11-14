import { Link } from '@inertiajs/react';
import { useId } from 'react';
import { AppWindow, ArrowUpRight, Cloud, FileText, Server } from 'lucide-react';
import { Tabs } from 'radix-ui';
import { ProductCard } from './product-card';
import { ContactDialog } from './contact-dialog';
import type { Product } from '@/types/catalog';

export type WindowsTab = { value: string; label: string; url: string; products: Product[] };
const icons = { windows: AppWindow, '365': Cloud, office: FileText, server: Server };
// Original app artwork: microsoft.com/content/dam/microsoft/bade/images/icons/en-us/m365-app-icons-fy26/

export function WindowsZone({ tabs }: { tabs: WindowsTab[] }) {
    const headingId = useId();
    if (!tabs.length) return null;
    return <section className="storefront-windows-zone" aria-labelledby={headingId}>
        <Tabs.Root defaultValue={tabs[0].value}>
            <header className="storefront-windows-header">
                <div className="storefront-windows-brand"><img src="/images/partners/microsoft.svg" width="32" height="32" alt="" /><span>Microsoft для бизнеса</span></div>
                <h2 id={headingId}>Windows и всё для работы</h2>
                <p>Операционные системы, офисные приложения<br className="hidden sm:block" /> и серверные лицензии для вашей команды.</p>
                <div className="storefront-windows-art" aria-hidden="true">{['word', 'excel', 'powerpoint'].map(app => <img key={app} src={`/images/microsoft/${app}.svg`} width="72" height="72" alt="" />)}</div>
                <Tabs.List data-slot="tabs-list" className="storefront-windows-tabs" aria-label="Программное обеспечение Microsoft">
                    {tabs.map(tab => {
                        const Icon = icons[tab.value as keyof typeof icons] || AppWindow;
                        return <Tabs.Trigger data-slot="tabs-trigger" key={tab.value} value={tab.value}><Icon size={16} /><span>{tab.label}</span></Tabs.Trigger>;
                    })}
                </Tabs.List>
            </header>
            {tabs.map(tab => <Tabs.Content key={tab.value} value={tab.value}>
                {tab.products.length > 0 ? <div className="storefront-windows-products" role="region" aria-label={`Товары ${tab.label}`} tabIndex={0}>{tab.products.map(product => <ProductCard key={product.id} product={product} />)}</div>
                    : <div className="storefront-windows-empty"><h3>Подберём {tab.label} под вашу задачу</h3><p>Сейчас в этой подборке нет товаров. Оставьте заявку — уточним варианты поставки.</p><ContactDialog service={`Подбор ${tab.label}`} label="Обсудить подбор" secondary /></div>}
                <footer className="storefront-windows-footer"><Link href={tab.url}>Все товары {tab.label}<ArrowUpRight size={16} /></Link></footer>
            </Tabs.Content>)}
        </Tabs.Root>
    </section>;
}
