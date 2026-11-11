import { Link } from '@inertiajs/react';
import { Page, Entries, type Entry, type PageProps } from '@/components/storefront/content';
import { BrandRibbon } from '@/components/storefront/brand-ribbon';
import { WhyAltech } from '@/components/storefront/why-altech';
import { ServicesOverview, type Direction } from '@/components/storefront/services-overview';
import { HomeCatalog } from '@/components/storefront/home-sections';
import type { Product } from '@/types/catalog';

export default function Home(props: PageProps & { directions: Direction[]; categories: Entry[]; services: Entry[]; pages: Entry[]; catalogProducts: Product[]; catalogFilter: string; catalogFilters: string[]; catalogTotal: number; catalogSections: { name: string; total: number; products: Product[] }[] }) {
    const equipment = [
        ['Серверы', 'Серверы и хранение данных'],
        ['Сеть', 'Сетевое оборудование'],
        ['Хранение данных', 'Системы хранения данных'],
        ['Видеосвязь', 'Оборудование видеоконференций'],
        ['Рабочие места', 'Компьютеры и ноутбуки'],
        ['Софт', 'Программное обеспечение'],
    ];
    return <Page {...props} hero>
        <nav className="storefront-equipment" aria-label="Категории оборудования">{equipment.map(([name, category], index) => <Link key={category} href={`${props.navigation.catalog}?category=${encodeURIComponent(category)}`} className="storefront-equipment-card"><img className="storefront-equipment-icon" src={`/images/figma-category-${index}.svg`} width="28" height="28" alt="" /><span>{name}</span><img className="storefront-equipment-glow" src="/images/figma-category-glow.svg" width="263" height="263" alt="" /></Link>)}</nav>
        <BrandRibbon />
        <ServicesOverview directions={props.directions} />
        <HomeCatalog products={props.catalogProducts} catalogUrl={props.navigation.catalog} homeUrl={props.navigation.home} activeFilter={props.catalogFilter} filters={props.catalogFilters} total={props.catalogTotal} />
        <WhyAltech />
        <div className="storefront-category-sections">{props.catalogSections.map(section => <HomeCatalog key={section.name} title={section.name} showFilters={false} products={section.products} total={section.total} filters={[]} activeFilter={section.name} homeUrl={props.navigation.home} catalogUrl={props.navigation.catalog} />)}</div>
        {props.pages.length > 0 && <section className="space-y-4"><h2 className="text-xl font-semibold">О компании</h2><Entries items={props.pages} /></section>}
    </Page>;
}
