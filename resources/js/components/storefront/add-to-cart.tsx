import { Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Minus, Plus } from 'lucide-react';

type CartSummary = {
    count: number;
    maxQuantity: number;
    lines: { addUrl: string; updateUrl: string; removeUrl: string; quantity: number }[];
};

export function AddToCart({ url }: { url: string }) {
    const { cartSummary, cartUrl } = usePage<{ cartSummary: CartSummary; cartUrl: string }>().props;
    const form = useForm({ quantity: 1, stay: true });
    const line = cartSummary?.lines.find(item => item.addUrl === url);
    function change(quantity: number) {
        if (!line || form.processing) return;
        form.clearErrors();
        form.transform(() => ({ quantity, stay: true }));
        if (quantity === 0) form.delete(line.removeUrl, { preserveScroll: true });
        else form.patch(line.updateUrl, { preserveScroll: true });
    }
    return <div className="flex flex-col gap-2">{line ? <div className="storefront-cart-controls" aria-busy={form.processing}>
        <div className="storefront-quantity" role="group" aria-label="Количество товара">
            <Button variant="outline" size="icon" disabled={form.processing} aria-label={line.quantity === 1 ? 'Убрать из корзины' : 'Уменьшить количество'} onClick={() => change(line.quantity - 1)}><Minus size={16} /></Button>
            <Button asChild variant="secondary" className="storefront-quantity-summary"><Link href={cartUrl}>В корзине <span aria-live="polite">{line.quantity}</span></Link></Button>
            <Button variant="outline" size="icon" disabled={form.processing || line.quantity >= cartSummary.maxQuantity} aria-label="Увеличить количество" onClick={() => change(line.quantity + 1)}><Plus size={16} /></Button>
        </div>
    </div> : <Button disabled={form.processing} onClick={() => { form.clearErrors(); form.transform(() => ({ quantity: 1, stay: true })); form.post(url, { preserveScroll: true }); }}>{form.processing ? 'Добавляем…' : 'В корзину'}</Button>}<span className="sr-only" role="status">{line ? 'Товар в корзине' : ''}</span>{Object.values(form.errors).map((error, i) => <p key={i} role="alert" className="text-sm text-destructive">{error}</p>)}</div>;
}
