export const fillProductImageBackground = true;

/** Only our checked media endpoints support derived sizes; external URLs stay intact. */
export function productImage(source: string, size: 'thumb' | 'card' | 'detail' | 'full'): string {
    const url = new URL(source, 'http://localhost');
    if (! /^\/(catalog-photo-quality-image\/|product-media-pilot\/)/.test(url.pathname)) return source;
    url.searchParams.set('size', size);
    return source.startsWith('/') ? url.pathname + url.search + url.hash : url.href;
}
