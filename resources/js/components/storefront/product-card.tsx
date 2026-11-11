import { ProgressiveProductImage } from './progressive-product-image';
import { productImage, fillProductImageBackground } from '@/lib/product-image';
import { ProductPlaceholder } from './product-placeholder';
import { ProductAction, ProductStock } from './product-purchase';
import { useState, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardHeader, CardContent, CardFooter } from '@/components/ui/card';
import type { Product } from '@/types/catalog';

function ProductCover({ images, previews, backgrounds, title, tone, url, brand, category }: { previews?: Record<string, string>; backgrounds?: Record<string, string>; category: string | null; brand: string | null; images: string[]; title: string; tone: Product['categoryTone']; url: string }) {
    const [active, setActive] = useState(0);
    const [failed, setFailed] = useState<string[]>([]);
    const image = images[active];
    const placeholder = !image || failed.includes(image);
    const background = (fillProductImageBackground && backgrounds?.[image]) || '#ffffff';
    const channels = [1, 3, 5].map(offset => parseInt(background.slice(offset, offset + 2), 16) / 255)
        .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    const darkBackground = !placeholder && channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722 < 0.179;
    return <div className="storefront-product-visual" data-placeholder={placeholder} data-tone={tone || 'neutral'} data-gallery={images.length > 1}
        onPointerMove={event => {
            if (event.pointerType !== 'mouse' || images.length < 2 || (event.target as HTMLElement).closest('button')) return;
            const rect = event.currentTarget.getBoundingClientRect();
            setActive(Math.max(0, Math.min(images.length - 1, Math.floor((event.clientX - rect.left) / rect.width * images.length))));
        }} onPointerLeave={event => { if (event.pointerType === 'mouse') setActive(0); }}>
        <Link href={url} className="storefront-product-cover-link" aria-label={title}>
            {!placeholder && <ProgressiveProductImage key={image} className="storefront-product-gallery-image" src={productImage(image, 'card')} preview={previews?.[image]} background={backgrounds?.[image]} alt={title + (images.length > 1 ? ' — фото ' + (active + 1) : '')} loading="lazy" onError={() => setFailed(previous => previous.includes(image) ? previous : [...previous, image])} />}
            {placeholder && <ProductPlaceholder brand={brand} category={category} />}
        </Link>
        {images.length > 1 && <div className="storefront-product-gallery-controls" data-dark={darkBackground} role="group" aria-label="Фотографии товара">
            {images.map((source, index) => <button key={source} type="button" aria-label={'Фото ' + (index + 1) + ' из ' + images.length} aria-pressed={active === index}
                onClick={() => setActive(index)} onKeyDown={event => {
                    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
                    event.preventDefault();
                    const next = (index + (event.key === 'ArrowRight' ? 1 : -1) + images.length) % images.length;
                    setActive(next);
                    (event.currentTarget.parentElement?.children[next] as HTMLButtonElement)?.focus();
                }}><span /></button>)}
        </div>}
    </div>;
}

/** Карточка каталога; действие передаётся отдельно от представления товара. */
export function ProductCard({ product, action, variant = 'standard' }: { product: Product; action?: ReactNode; variant?: 'standard' | 'compact' }) {
    const title = product.name.split(/\s+формат поставки\s*[-—:]?/i)[0];
    const delivery = product.name.match(/формат поставки\s*[-—:]?\s*(.+?)(?:\s*\(вкл\.|$)/i)?.[1];
    const images = [...new Set([product.image, ...(product.images ?? [])].filter((image): image is string => !!image?.trim()))];
    return <Card className="storefront-home-product" data-variant={variant}>
        {variant === 'standard' && <ProductCover key={JSON.stringify(images)} images={images} previews={product.imagePreviews} backgrounds={product.imageBackgrounds} category={product.category} brand={product.brand} title={product.imageAlt || title} tone={product.categoryTone} url={product.url} />}
        <CardHeader className="storefront-product-header">
            {product.category && <p className="storefront-product-category">{product.category}</p>}
            <h3><Link href={product.url}>{title}</Link></h3>
        </CardHeader>
        {(product.brand || product.sku || delivery) && <CardContent className="storefront-product-details"><dl>{product.brand && <div><dt>Бренд</dt><dd>{product.brand}</dd></div>}{product.sku && <div><dt>Артикул</dt><dd>{product.sku}</dd></div>}{delivery && <div><dt>Поставка</dt><dd>{delivery}</dd></div>}</dl></CardContent>}
        <CardFooter className="storefront-product-purchase">
            <div className="storefront-product-price-block"><strong>{product.formattedPrice}</strong><p><ProductStock value={product.availability} /></p></div>
            {action ?? <ProductAction product={product} />}
        </CardFooter>
    </Card>;
}
