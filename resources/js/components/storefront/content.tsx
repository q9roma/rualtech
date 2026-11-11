import { Head, Link } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import type { CatalogProps, Product } from '@/types/catalog';
import StorefrontLayout from '@/layouts/storefront-layout';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Subheader, type Crumb } from '@/components/storefront/subheader';
import { ProductCard } from './product-card';

export type Entry = { id: number; name: string; url: string; description?: string; price?: string; date?: string; image?: string | null };
export type PaginationData<T> = { data: T[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
export type PageProps = Pick<CatalogProps, 'navigation'> & { title: string; seoTitle?: string | null; description?: string | null; canonicalUrl: string; structuredData?: Record<string, unknown>; content?: string };

export function Page({ children, hero = false, hideSubheader = false, breadcrumbs, actions, ...props }: PropsWithChildren<PageProps & { hero?: boolean; hideSubheader?: boolean; breadcrumbs?: Crumb[]; actions?: ReactNode }>) {
    return <StorefrontLayout navigation={props.navigation} subheader={hero || hideSubheader ? undefined : <Subheader title={props.title} description={props.description} navigation={props.navigation} breadcrumbs={breadcrumbs} actions={actions} />}>
        <Head title={props.seoTitle || props.title}><link rel="canonical" href={props.canonicalUrl} />{props.description && <meta name="description" content={props.description} />}{props.structuredData && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(props.structuredData).replace(/</g, '\u003c') }} />}<meta property="og:type" content="website" /><meta property="og:site_name" content="Алтех" /><meta property="og:title" content={props.seoTitle || props.title} /><meta property="og:url" content={props.canonicalUrl} />{props.description && <meta property="og:description" content={props.description} />}</Head>
        {children}
    </StorefrontLayout>;
}
export function RichContent({ html }: { html?: string }) {
    return html ? <div className="rich-content" dangerouslySetInnerHTML={{ __html: html }} /> : null;
}
export function Entries({ items, empty = 'Пока нет опубликованных материалов.' }: { items: Entry[]; empty?: string }) {
    return items.length ? <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">{items.map(item => <Card key={item.id}>
        {item.image && <CardContent><img src={item.image} alt="" loading="lazy" className="aspect-video w-full rounded-md object-cover" /></CardContent>}
        <CardHeader><CardTitle className="leading-6"><Link href={item.url} className="hover:underline">{item.name}</Link></CardTitle>{item.date && <CardDescription>{item.date}</CardDescription>}</CardHeader>
        {item.description && <CardContent className="text-sm text-muted-foreground">{item.description}</CardContent>}
        <CardFooter className="mt-auto flex flex-wrap justify-between gap-3">{item.price && <span className="font-medium">{item.price}</span>}<Button asChild variant="outline"><Link href={item.url}>Подробнее</Link></Button></CardFooter>
    </Card>)}</div> : <Card><CardContent className="text-sm text-muted-foreground">{empty}</CardContent></Card>;
}
export function Pagination({ data }: { data: Omit<PaginationData<never>, 'data'> }) {
    if (data.last_page <= 1) return null;
    return <nav aria-label="Пагинация" className="flex flex-wrap items-center justify-between gap-4">
        <span className="text-sm text-muted-foreground">Страница {data.current_page} из {data.last_page}</span>
        <div className="flex gap-2">{([['Назад', data.prev_page_url], ['Далее', data.next_page_url]] as const).map(([label, url]) => url ? <Button key={label} variant="outline" asChild><Link href={url}>{label}</Link></Button> : <Button key={label} variant="outline" disabled>{label}</Button>)}</div>
    </nav>;
}
export function Products({ products }: { products: Product[] }) {
    return <div className="storefront-home-products">{products.map(product => <ProductCard key={product.id} product={product} variant="standard" />)}</div>;
}
