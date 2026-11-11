import { useEffect, useRef, useState } from 'react';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { MetricCard } from '@/components/admin/metric-card';
import { Link, router } from '@inertiajs/react';
import { ArrowUpRight, Plus } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { date } from '@/components/admin/controls';

type Point = { date: string; requests: number; orders: number };
export default function Dashboard({ stats, series, recent, days }: { stats: { label: string; value: number; hint: string; url: string }[]; series: Point[]; recent: { id: number; name: string; email: string; service: string; statusLabel: string; created_at: string }[]; days: number }) {
    const chart = useRef<SVGSVGElement>(null);
    const [width, setWidth] = useState(960);
    useEffect(() => {
        const element = chart.current;
        if (!element) return;
        const observer = new ResizeObserver(() => setWidth(Math.max(240, element.clientWidth - 48)));
        observer.observe(element);
        return () => observer.disconnect();
    }, []);
    const height = 220, max = Math.max(4, Math.ceil(Math.max(0, ...series.flatMap(p => [p.requests, p.orders])) / 4) * 4);
    const points = (key: 'orders' | 'requests') => series.map((p, i) => `${i * width / Math.max(1, series.length - 1)},${height - p[key] / max * height}`).join(' ');
    return <AdminLayout title="Обзор" actions={<Button variant="outline" asChild><Link href="/admin/contact-requests/create"><Plus/>Новая заявка</Link></Button>}>
        <div className="admin-stats">{stats.map(stat => <Link key={stat.label} href={stat.url} className="admin-stat-link" aria-label={stat.label+': '+stat.value}><MetricCard title={stat.label} value={stat.value.toLocaleString('ru-RU')}/><ArrowUpRight size={16} aria-hidden="true"/></Link>)}</div>
        <section className="admin-chart"><div className="flex flex-wrap items-start justify-between gap-4"><div><h2>Активность клиентов</h2></div><Tabs value={String(days)} onValueChange={value => router.get('/admin', { days: Number(value) }, { preserveScroll: true })}><TabsList aria-label="Период активности">{[90, 30, 7].map(value => <TabsTrigger key={value} value={String(value)}>{value === 90 ? '3 месяца' : value+' дней'}</TabsTrigger>)}</TabsList></Tabs></div>
            <div className="admin-chart-legend"><span><i className="bg-blue-500"/>Заявки</span><span><i className="bg-blue-300"/>Заказы</span></div><svg ref={chart} className="admin-chart-svg" viewBox={`-32 -10 ${width+48} ${height+40}`} role="img" aria-label={`Динамика: ${series.reduce((n,p) => n+p.requests,0)} заявок и ${series.reduce((n,p) => n+p.orders,0)} заказов за ${days} дней`}>
            <defs><linearGradient id="requests-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#3b82f6" stopOpacity=".4"/><stop offset="100%" stopColor="#3b82f6" stopOpacity=".02"/></linearGradient><linearGradient id="orders-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#93c5fd" stopOpacity=".5"/><stop offset="100%" stopColor="#93c5fd" stopOpacity=".03"/></linearGradient></defs>
            {[0, 1, 2, 3, 4].map(i => <g key={i}><line x1="0" x2={width} y1={i * height/4} y2={i * height/4} stroke="var(--border)"/><text x="-10" y={i * height/4+4} textAnchor="end" fill="var(--muted-foreground)" fontSize="12">{Math.round(max * (1-i/4))}</text></g>)}
            <polygon points={`0,${height} ${points('orders')} ${width},${height}`} fill="url(#orders-fill)"/><polyline points={points('orders')} fill="none" stroke="#93c5fd" strokeWidth="1.5"/>
            <polygon points={`0,${height} ${points('requests')} ${width},${height}`} fill="url(#requests-fill)"/><polyline points={points('requests')} fill="none" stroke="#3b82f6" strokeWidth="1.5"/>
            {series.filter((_, i) => i % Math.max(1, Math.ceil(series.length / (width < 500 ? 3 : 7))) === 0).map(p => <text key={p.date} x={series.indexOf(p)*width/Math.max(1,series.length-1)} y={height+24} textAnchor="middle" fontSize="12" fill="var(--muted-foreground)">{new Date(p.date).toLocaleDateString('ru-RU', {day:'numeric',month:'short'})}</text>)}</svg>
            {series.every(p => !p.requests && !p.orders) && <p className="text-center text-muted-foreground">За выбранный период новых заявок и заказов нет.</p>}
        </section><section className="admin-surface mt-4" aria-label="Последние заявки"><div className="admin-list-tabs"><h2 className="text-base font-semibold">Последние заявки</h2><Button variant="outline" className="ml-auto" asChild><Link href="/admin/contact-requests">Все заявки <ArrowUpRight/></Link></Button></div>
        <div className="admin-table-wrap"><Table className="admin-table"><TableHeader><TableRow><TableHead>Клиент</TableHead><TableHead>Email</TableHead><TableHead>Услуга</TableHead><TableHead>Статус</TableHead><TableHead>Создана</TableHead><TableHead><span className="sr-only">Открыть</span></TableHead></TableRow></TableHeader><TableBody>{recent.map(r => <TableRow key={r.id}><TableCell><Link className="font-medium" href={`/admin/contact-requests/${r.id}`}>{r.name}</Link></TableCell><TableCell>{r.email}</TableCell><TableCell>{r.service || '—'}</TableCell><TableCell><Badge variant="outline">{r.statusLabel}</Badge></TableCell><TableCell>{date(r.created_at)}</TableCell><TableCell><Button asChild variant="ghost" size="icon"><Link href={`/admin/contact-requests/${r.id}`} aria-label={`Открыть заявку ${r.name}`}><ArrowUpRight/></Link></Button></TableCell></TableRow>)}{!recent.length && <TableRow><TableCell colSpan={6}><div className="admin-empty"><h2>Пока нет заявок</h2><p>Новые обращения клиентов появятся здесь.</p></div></TableCell></TableRow>}</TableBody></Table></div>
    </section></AdminLayout>;
}
