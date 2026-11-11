import { Head, Link, useForm } from '@inertiajs/react';
import { Page, Pagination, type PageProps, type PaginationData } from '@/components/storefront/content';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';

type Order = { id: number; reference: string; status: string; total: string; date: string; url: string };
function GuestOrder({ order }: { order: Order }) {
    const form = useForm({ order_id: order.id });
    return <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-3"><p>№{order.reference} · {order.date} · {order.total}</p>
        <form onSubmit={e => { e.preventDefault(); form.post('/account/claim'); }}><Button variant="outline" disabled={form.processing}>{form.processing ? "Привязываем…" : "Это мой заказ — привязать"}</Button></form>
        {form.errors.order_id && <p role="alert">{form.errors.order_id}</p>}</div>;
}
export default function AccountPage(props: PageProps & { buyer: { name: string; email: string }; orders: PaginationData<Order>; guestOrders: Order[] }) {
    const logout = useForm({});
    return <Page {...props}><Head><meta name="robots" content="noindex, nofollow" /></Head>
        <div className="flex flex-wrap items-center justify-between gap-4"><p className="min-w-0 break-words">{props.buyer.name} · {props.buyer.email}</p><form onSubmit={e => { e.preventDefault(); logout.post('/logout'); }}><Button variant="outline" disabled={logout.processing}>Выйти</Button></form></div>
        {props.guestOrders.length > 0 && <Card><CardHeader><CardTitle>Гостевые заказы этого браузера</CardTitle></CardHeader><CardContent className="space-y-4"><p className="text-sm text-muted-foreground">Подтвердите только свои заказы. После привязки они будут доступны через ваш аккаунт, а гостевой доступ закроется.</p>{props.guestOrders.map(order => <GuestOrder key={order.id} order={order} />)}</CardContent></Card>}
        {props.orders.data.length ? <div className="space-y-4">{props.orders.data.map(order => <Card key={order.id}><CardHeader><CardTitle><Link href={order.url}>Заказ №{order.reference}</Link></CardTitle></CardHeader><CardContent className="flex flex-wrap items-center gap-4"><span>{order.date}</span><Badge variant="secondary">{order.status}</Badge><span>{order.total}</span><Button asChild variant="outline"><Link href={order.url} aria-label={`Подробнее о заказе №${order.reference}`}>Подробнее</Link></Button></CardContent></Card>)}</div> : <Card><CardContent>У вас пока нет привязанных заказов. <Link className="underline" href="/catalog">Перейти в каталог</Link></CardContent></Card>}
        <Pagination data={props.orders} />
    </Page>;
}
