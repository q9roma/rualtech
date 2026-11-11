import { useState } from 'react';
import { ArrowUpRight, Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { CheckoutSteps } from '@/components/storefront/checkout-steps';
import { ProductPlaceholder } from '@/components/storefront/product-placeholder';
import { ProgressiveProductImage } from '@/components/storefront/progressive-product-image';
import { productImage } from '@/lib/product-image';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Page, type PageProps } from '@/components/storefront/content';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Cart, CartLine } from '@/types/commerce';

function Line({ line, onDirty }: { line: CartLine; onDirty: (id: number, dirty: boolean) => void }) {
    const form = useForm({ quantity: String(line.quantity) });
    const [imageFailed, setImageFailed] = useState(false);
    const cover = line.image && !imageFailed ? <ProgressiveProductImage src={productImage(line.image, 'thumb')} preview={line.imagePreview ?? undefined} background={line.imageBackground ?? undefined} alt={line.name} onError={() => setImageFailed(true)} /> : <ProductPlaceholder brand={line.brand} category={line.category} />;
    const quantity = Number(form.data.quantity);
    const valid = Number.isInteger(quantity) && quantity >= 1 && quantity <= 99;
    const update = (value: string) => {
        if (form.processing) return;
        form.setData('quantity', value);
        const next = Number(value);
        onDirty(line.id, value !== String(line.quantity));
        if (!Number.isInteger(next) || next < 1 || next > 99 || next === line.quantity) return;
        form.transform(() => ({ quantity: value }));
        form.patch(`/cart/lines/${line.id}`, {
            preserveScroll: true,
            onError: () => form.setData('quantity', String(line.quantity)),
            onFinish: () => onDirty(line.id, false),
        });
    };
    return <article className="cart-line" aria-label={line.name} aria-busy={form.processing}>
        <div className="cart-product-cover">{line.url ? <Link href={line.url} aria-label={line.name}>{cover}</Link> : cover}</div>
        <div className="cart-product-copy"><h3>{line.url ? <Link href={line.url}>{line.name}</Link> : line.name}</h3>{line.sku && <p>Артикул: {line.sku}</p>}<span>{line.unitPrice} / шт.</span></div>
        <div className="cart-line-controls">
            <div className="cart-quantity">
                <Button type="button" variant="ghost" size="icon" aria-label={`Уменьшить количество: ${line.name}`} disabled={form.processing || !valid || quantity <= 1} onClick={() => update(String(quantity - 1))}><Minus size={14} /></Button>
                <Input aria-label={`Количество: ${line.name}`} type="number" min={1} max={99} step={1} required value={form.data.quantity} onChange={e => update(e.target.value)} onBlur={() => { if (!valid) { form.setData('quantity', String(line.quantity)); onDirty(line.id, false); } }} disabled={form.processing} aria-invalid={!valid || !!form.errors.quantity} />
                <Button type="button" variant="ghost" size="icon" aria-label={`Увеличить количество: ${line.name}`} disabled={form.processing || !valid || quantity >= 99} onClick={() => update(String(quantity + 1))}><Plus size={14} /></Button>
            </div>
        </div>
        <div className="cart-line-total"><strong aria-live="polite">{line.total}</strong><span>{form.processing ? 'Пересчитываем…' : `За ${line.quantity} шт.`}</span></div>
        <Button className="cart-remove" type="button" variant="ghost" size="icon" aria-label={`Удалить из корзины: ${line.name}`} disabled={form.processing} onClick={() => form.delete(`/cart/lines/${line.id}`, { preserveScroll: true, onSuccess: () => onDirty(line.id, false) })}><Trash2 size={17} /></Button>
        {Object.values(form.errors).map((error, i) => <p className="cart-line-error" role="alert" key={i}>{error}</p>)}
    </article>;
}

export default function CartPage(props: PageProps & { cart: Cart }) {
    const [dirty, setDirty] = useState<number[]>([]);
    const onDirty = (id: number, changed: boolean) => setDirty(ids => changed ? [...new Set([...ids, id])] : ids.filter(value => value !== id));
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const { cart } = props;
    const hasChanges = dirty.some(id => cart.lines.some(line => line.id === id));
    return <Page {...props} description={null}>
        <Head><meta name="robots" content="noindex, nofollow" /></Head>
        <div className="cart-page">
            <div className="cart-progress"><CheckoutSteps current={1} /></div>
            {errors.cart && <p role="alert" className="cart-alert">{errors.cart}</p>}
            {cart.issues.map(issue => <p role="alert" className="cart-alert" key={issue}>{issue}</p>)}
            {cart.lines.length ? <div className="cart-layout">
                <section className="cart-items" aria-labelledby="cart-items-title">
                    <div className="cart-section-heading"><h2 id="cart-items-title">Состав заказа <span>{cart.lines.length}</span></h2></div>
                    <div>{cart.lines.map(line => <Line key={`${line.id}-${line.quantity}`} line={line} onDirty={onDirty} />)}</div>
                </section>
                <aside className="cart-summary" aria-labelledby="cart-summary-title">
                    <h2 id="cart-summary-title">Ваш заказ</h2>
                    <dl><div><dt>Позиций в корзине</dt><dd>{cart.lines.length}</dd></div></dl>
                    <div className="cart-summary-total"><span>Стоимость товаров</span><strong aria-live="polite">{cart.total}</strong></div>
                    {cart.issues.length || hasChanges ? <Button className="cart-checkout" disabled>Оформить заказ<ArrowUpRight size={18} /></Button> : <Button className="cart-checkout" asChild><Link href="/checkout">Оформить заказ<ArrowUpRight size={18} /></Link></Button>}
                    
                </aside>
            </div> : <section className="cart-empty"><div className="cart-empty-icon"><ShoppingCart size={32} strokeWidth={1.3} aria-hidden="true" /></div><h2>Ваша корзина пока пуста</h2><p>Добавьте товары из каталога.</p><Button asChild><Link href={props.navigation.catalog}>В каталог<ArrowUpRight size={17} /></Link></Button></section>}
        </div>
    </Page>;
}
