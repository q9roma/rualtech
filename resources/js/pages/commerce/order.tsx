import { CheckoutSteps } from '@/components/storefront/checkout-steps';
import { Head, Link } from '@inertiajs/react';
import { Page, type PageProps } from '@/components/storefront/content';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
export default function OrderPage(props: PageProps & { order: { isOwner: boolean; company: { name: string; inn: string; kpp: string | null } | null; delivery: { directions?: string|null; pickup_point?: {id:string}|null; method: string; carrier: string | null; city: string | null; address: string | null } | null; reference: string; statusLabel: string; total: string; lines: { id: number; name: string; sku: string | null; quantity: number; total: string }[] } }) {
    const { order } = props;
    const deliveryLabels: Record<string, string> = { pickup: 'Самовывоз со склада', courier: 'Курьер', terminal: 'До терминала', address: 'До адреса', yandex_pvz: 'Яндекс ПВЗ' };
    return <Page {...props} description={null}>
        <Head><meta name="robots" content="noindex, nofollow" /></Head>
        <div className="space-y-6">
            <div className="cart-progress"><CheckoutSteps current={3} /></div>
            <Badge variant="secondary">{order.statusLabel}</Badge>
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <Card className="min-w-0"><CardHeader><CardTitle>Состав заказа</CardTitle></CardHeader>
                    <CardContent><ul className="divide-y">{order.lines.map(line => <li key={line.id} className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0">
                        <div className="min-w-0 flex-1"><p className="break-words text-sm font-medium">{line.name}</p><p className="mt-2 text-xs text-muted-foreground">{line.sku && <>Артикул: {line.sku} · </>}{line.quantity} шт.</p></div>
                        <strong className="whitespace-nowrap text-sm font-medium">{line.total}</strong>
                    </li>)}</ul></CardContent>
                </Card>
                <div className="min-w-0 space-y-6">
                    <Card><CardHeader><CardTitle>Ваш заказ</CardTitle></CardHeader><CardContent className="space-y-5">
                        <dl className="space-y-3 text-sm"><div className="flex justify-between gap-4"><dt className="text-muted-foreground">Оплата</dt><dd>По счёту</dd></div>{order.delivery && order.delivery.method !== 'pickup' && <div className="flex justify-between gap-4"><dt className="text-muted-foreground">Доставка</dt><dd className="text-right">Рассчитывается отдельно</dd></div>}</dl>
                        <div className="border-t pt-5"><p className="text-xs text-muted-foreground">Стоимость товаров</p><p className="mt-2 text-2xl font-medium">{order.total}</p></div>
                    </CardContent></Card>
                    {order.delivery && <Card><CardHeader><CardTitle>Получение заказа</CardTitle></CardHeader><CardContent className="space-y-3 text-sm leading-relaxed break-words">
                        <p className="font-medium">{deliveryLabels[order.delivery.method] || order.delivery.method}</p>
                        {order.delivery.carrier && order.delivery.method !== 'yandex_pvz' && order.delivery.method !== 'courier' && <p>{order.delivery.carrier}</p>}
                        <p>{[order.delivery.city && !order.delivery.address?.includes(order.delivery.city) ? order.delivery.city : null, order.delivery.address].filter(Boolean).join(', ')}</p>
                        {order.delivery.directions && <div className="border-t pt-3"><p className="mb-1 text-xs text-muted-foreground">Как проехать</p><p>{order.delivery.directions}</p></div>}
                        {order.delivery.pickup_point && <p className="text-xs text-muted-foreground">Код ПВЗ: {order.delivery.pickup_point.id}</p>}
                    </CardContent></Card>}
                    {order.company && <Card><CardHeader><CardTitle>Организация</CardTitle></CardHeader><CardContent className="space-y-2 text-sm break-words"><p>{order.company.name}</p><p className="text-muted-foreground">ИНН {order.company.inn}{order.company.kpp ? ` · КПП ${order.company.kpp}` : ''}</p></CardContent></Card>}
                </div>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row"><Button asChild><Link href="/account">Мои заказы</Link></Button><Button variant="outline" asChild><Link href={props.navigation.catalog}>Продолжить покупки</Link></Button></div>
            {!order.isOwner && <p className="text-sm text-muted-foreground">Чтобы сохранить заказ в личном кабинете, войдите и привяжите его в разделе «Мои заказы».</p>}
        </div>
    </Page>;
}
