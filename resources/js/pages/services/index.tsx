import { Link } from '@inertiajs/react';
import { Page, Entries, type Entry, type PageProps } from '@/components/storefront/content';
export default function Services(props: PageProps & { categories: (Entry & { services: Entry[] })[] }) {
    return <Page {...props}>{props.categories.length ? props.categories.map(category => <section key={category.id} className="space-y-4"><h2 className="text-xl font-semibold"><Link className="hover:underline" href={category.url}>{category.name}</Link></h2>{category.description && <p className="text-muted-foreground">{category.description}</p>}<Entries items={category.services} empty="Услуги в этой категории скоро появятся." /></section>) : <p className="text-muted-foreground">Услуги пока не опубликованы.</p>}</Page>;
}
