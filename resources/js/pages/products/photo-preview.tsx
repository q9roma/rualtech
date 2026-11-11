import { Head, Link } from '@inertiajs/react';
import { Page, type PageProps } from '@/components/storefront/content';
import { ProductGallery } from '@/components/storefront/product-gallery';
import type { Product } from '@/types/catalog';

export default function PhotoPreview(props: PageProps & { products: Product[] }) {
    return <Page {...props}><Head><meta name="robots" content="noindex, nofollow" /></Head>
        <p className="mb-6 text-muted-foreground">Пробные фото Icecat на локальном сайте. Нажмите название, чтобы оценить галерею на странице товара. Совпадение EAN проверено; комплектация и ревизия требуют отдельной оценки.</p>
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">{props.products.map(product => <article key={product.id} className="rounded-xl border bg-card p-4">
            <ProductGallery product={product} />
            <h2 className="mt-4 font-semibold"><Link href={product.url} className="underline underline-offset-4">{product.name}</Link></h2>
            <p className="mt-2 text-sm text-muted-foreground">{product.sku} · {product.images?.length} фото</p>
        </article>)}</div>
    </Page>;
}
