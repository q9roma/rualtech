import type { Product } from '@/types/catalog';
import { AddToCart } from './add-to-cart';
import { ContactDialog } from './contact-dialog';
import { Button } from '@/components/ui/button';

export function hasPrice(product: Product) {
    return product.hasPrice ?? product.formattedPrice !== 'По запросу';
}

export function stockLabel(value: string | null) {
    const stock = value?.trim() || '';
    if (/^>\s*\d+$/.test(stock)) return 'В наличии · более ' + stock.replace(/^>\s*/, '') + ' шт.';
    if (/^\d+$/.test(stock)) return Number(stock) > 0 ? 'В наличии · ' + Number(stock) + ' шт.' : 'Нет в наличии';
    if (/^в наличии/i.test(stock)) return 'В наличии';
    if (/^(под заказ|на заказ)$/i.test(stock)) return 'Под заказ';
    if (/^(нет|нет в наличии|отсутствует)$/i.test(stock)) return 'Нет в наличии';
    return stock ? 'Наличие: ' + stock : 'Уточняйте наличие';
}

export function ProductAction({ product }: { product: Product }) {
    const available = product.inStock ?? stockLabel(product.availability).startsWith('В наличии');
    if (hasPrice(product) && available) {
        return product.canAddToCart && product.addToCartUrl
            ? <AddToCart url={product.addToCartUrl} />
            : <Button disabled title="Добавление в корзину для этого товара недоступно">В корзину</Button>;
    }
    const label = !hasPrice(product) ? 'Запросить цену' : 'Запросить поставку';
    return <ContactDialog productId={product.id} service={`${label}: ${product.sku || ''} — ${product.name}`} label={label} secondary={hasPrice(product)} />;
}

export function ProductStock({ value }: { value: string | null }) {
    const label = stockLabel(value);
    return <span className="product-stock" data-available={label.startsWith("В наличии")}><span aria-hidden="true" className="product-stock-dot" />{label}</span>;
}
