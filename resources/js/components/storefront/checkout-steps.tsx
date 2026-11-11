import { Link } from '@inertiajs/react';

export function CheckoutSteps({ current }: { current: 1 | 2 | 3 }) {
    return <nav aria-label="Этапы оформления"><ol className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
        {(['Корзина', 'Оформление', 'Заказ оформлен'] as const).map((label, index) => <li key={label}
            aria-current={current === index + 1 ? 'step' : undefined} className={current === index + 1 ? 'font-semibold' : 'text-muted-foreground'}>
            {current === 2 && index === 0 ? <Link className="underline underline-offset-4" href="/cart">1. {label}</Link> : `${index + 1}. ${label}`}
        </li>)}
    </ol></nav>;
}
