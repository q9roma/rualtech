import { ProgressiveProductImage } from './progressive-product-image';
import { productImage } from '@/lib/product-image';
import { useState } from 'react';
import { usePhotoWheel } from '@/hooks/use-photo-wheel';
import { ProductPlaceholder } from './product-placeholder';
import { Package } from 'lucide-react';
import type { Product } from '@/types/catalog';
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

export function ProductGallery({ product }: { product: Product }) {
    const images = [...new Set([product.image, ...(product.images ?? [])].filter((src): src is string => !!src?.trim()))];
    const [active, setActive] = useState(0);
    const [failed, setFailed] = useState<string[]>([]);
    const previewRef = usePhotoWheel(images.length, setActive);
    const current = images[active];
    const hasPhoto = !!current && !failed.includes(current);
    const thumbnails = images.length > 1 && <div className="flex flex-wrap gap-2" role="group" aria-label="Фотографии товара">{images.map((src, index) => <button key={src} type="button" aria-label={`Фото ${index + 1} из ${images.length}`} aria-pressed={index === active} onClick={() => setActive(index)} onPointerEnter={event => {
        if (event.pointerType === 'mouse' && window.matchMedia('(hover: hover) and (pointer: fine)').matches) setActive(index);
    }} className="h-14 w-14 overflow-hidden rounded-md border p-1 aria-pressed:border-foreground focus-visible:outline-2 focus-visible:outline-offset-2">
        {failed.includes(src) ? <Package aria-hidden="true" /> : <img src={productImage(src, 'thumb')} alt="" className="h-full w-full object-contain" onError={() => setFailed(previous => previous.includes(src) ? previous : [...previous, src])} />}
    </button>)}</div>;
    return <Dialog><div className="space-y-3">
        <div className="shop-product-art" data-placeholder={!current || failed.includes(current)}>
            {current && !failed.includes(current)
                ? <ProgressiveProductImage key={current} preview={product.imagePreviews?.[current]} background={product.imageBackgrounds?.[current]} src={productImage(current, 'detail')} alt={product.imageAlt || product.name} className="shop-product-gallery-image" onError={() => setFailed(previous => [...previous, current])} />
                : <ProductPlaceholder brand={product.brand} category={product.category} />}
            {hasPhoto && <DialogTrigger asChild><button type="button" aria-label="Открыть фото крупнее" className="absolute inset-0 cursor-zoom-in rounded-[inherit] focus-visible:outline-2 focus-visible:-outline-offset-4" /></DialogTrigger>}
        </div>
        {thumbnails}
    </div>
        <DialogContent ref={previewRef} className="w-[calc(100%-2rem)] sm:max-w-5xl max-h-[90dvh] overflow-y-auto p-4 pt-12" aria-describedby={undefined} onKeyDown={event => {
            if (images.length > 1 && (event.key === 'ArrowRight' || event.key === 'ArrowLeft')) {
                event.preventDefault();
                setActive(previous => (previous + (event.key === 'ArrowRight' ? 1 : -1) + images.length) % images.length);
            }
        }}>
            <DialogTitle className="sr-only">{product.name} — фотографии</DialogTitle>
            <div className="relative h-[60dvh] overflow-hidden">
                {hasPhoto ? <ProgressiveProductImage key={current} preview={product.imagePreviews?.[current]} background={product.imageBackgrounds?.[current]} src={productImage(current, 'full')} alt={product.imageAlt || product.name} className="h-full w-full object-contain" /> : <ProductPlaceholder brand={product.brand} category={product.category} />}
            </div>
            {thumbnails}
        </DialogContent>
    </Dialog>;
}
