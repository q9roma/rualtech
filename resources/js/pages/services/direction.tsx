import '../../../css/direction.css';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ServerServiceFaq } from '@/components/storefront/server-service-faq';
import { ServerServiceIntro } from '@/components/storefront/server-service-intro';

import { Page, Products, Pagination, type PageProps, type PaginationData } from '@/components/storefront/content';
import { ServicesOverview, type Direction } from '@/components/storefront/services-overview';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import type { Product } from '@/types/catalog';

type Section = { key: string; title: string; text: string; total: number; products: Product[]; url: string; pagination: Omit<PaginationData<never>, 'data'> | null };
type Props = PageProps & { brief: string; directionKey: string; directionUrl: string; selectedCollection: string; sections: Section[]; directions: Direction[] };

export default function DirectionPage(props: Props) {
    const available = props.sections.filter(section => section.total > 0);
    const visible = props.sections.filter(section => props.selectedCollection ? section.key === props.selectedCollection : section.total > 0);
    const showServiceIntro = props.directionKey === 'servers-storage' && !props.selectedCollection;
    return <Page {...props} hideSubheader>
        <div className="direction-page">
            <div className="direction-intro">
                <nav className="direction-breadcrumbs" aria-label="Хлебные крошки"><Link href={props.navigation.home}>Главная</Link><span>/</span><Link href={props.navigation.services}>Услуги</Link>{props.selectedCollection && <><span>/</span><Link href={props.directionUrl}>Все подборки направления</Link></>}</nav>
                {!showServiceIntro && <div className="direction-intro-row"><h1>{props.title}</h1><div className="direction-intro-copy"><p>{props.description}</p><ContactDialog service={props.title} label="Запросить подбор" /></div></div>}
            </div>
            {showServiceIntro && <ServerServiceIntro service={props.title} />}
            <section id="equipment" className="direction-equipment" aria-label="Оборудование">
            {props.selectedCollection && <nav className="direction-index" aria-label="Подборки направления">
                {available.map(section => <Button key={section.key} asChild variant={props.selectedCollection === section.key ? "secondary" : "ghost"} size="sm"><Link href={section.url} aria-current={props.selectedCollection === section.key ? 'page' : undefined}>{section.title}</Link></Button>)}
            </nav>}
            <div className="direction-collections">
                {visible.map(section => <section key={section.key} id={section.key} className="direction-collection" aria-labelledby={`${section.key}-heading`}>
                    <header className="direction-collection-heading"><h2 id={`${section.key}-heading`}>{!section.pagination && section.total > section.products.length ? <Link href={section.url} aria-label={`${section.title} — открыть всю подборку`}>{section.title}</Link> : section.title}</h2><p>{section.text}</p></header>
                    {section.products.length ? <Products products={section.products} /> : <p>Сейчас в этой подборке нет опубликованных товаров. Оставьте запрос — уточним варианты поставки.</p>}
                    {section.pagination && <Pagination data={section.pagination} />}
                </section>)}
                {visible.length === 0 && <p>Сейчас товары этого направления доступны по запросу. Расскажите о задаче — уточним варианты поставки.</p>}
            </div>
            </section>
            {showServiceIntro && <ServerServiceFaq />}
            <aside className="direction-help"><div className="direction-help-copy"><h2>Подберём под вашу задачу</h2><p>{props.brief}</p><ContactDialog service={props.title} label="Обсудить задачу" /></div></aside>
            <ServicesOverview directions={props.directions} title="Другие решения" />
        </div>
    </Page>;
}

