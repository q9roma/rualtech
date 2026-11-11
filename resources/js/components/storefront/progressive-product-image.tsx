import { useState, type CSSProperties } from 'react';
import { fillProductImageBackground } from '@/lib/product-image';

/** Inline preview is available with the page, without waiting for another image request. */
export function ProgressiveProductImage({ src, preview, background, alt, className = '', loading = 'eager', onError }: {
    src: string; preview?: string; background?: string; alt: string; className?: string;
    loading?: 'eager' | 'lazy'; onError?: () => void;
}) {
    const [ready, setReady] = useState(false);
    return <div className={`progressive-product-image ${className}`} data-ready={ready} style={{ '--product-image-bg': (fillProductImageBackground && background) || '#fff' } as CSSProperties}>
        <div className="progressive-product-backdrop" aria-hidden="true" />
        {preview && <img className="progressive-product-preview" src={preview} alt="" aria-hidden="true" />}
        <img className="progressive-product-full" src={src} alt={alt} loading={loading} decoding="async"
            onLoad={async event => {
                const image = event.currentTarget;
                try { await image.decode(); } catch { /* onError handles failed loads. */ }
                if (image.naturalWidth > 0) setReady(true);
            }} onError={onError} />
    </div>;
}
